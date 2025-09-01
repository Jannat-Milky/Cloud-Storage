<?php
// course_dashboard.php — enrollments, custom groups, auto-group by CGPA, and group management
session_start();
require 'db.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);
if (function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

// ---- Auth (professor only) ----
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header('Location: login.php'); exit();
}
$professor_id = (int)($_SESSION['user_id'] ?? 0);

// ---- Course param + ownership check ----
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($course_id <= 0) { header('Location: professor_dashboard.php'); exit(); }

$stmt = $conn->prepare("SELECT id,name,code,created_at,professor_id FROM courses WHERE id=? LIMIT 1");
$stmt->bind_param('i', $course_id);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course || (int)$course['professor_id'] !== $professor_id) {
    header('Location: professor_dashboard.php'); exit();
}

function redirect_with($cid, $msg){
    header("Location: course_dashboard.php?id={$cid}&msg=".urlencode($msg));
    exit();
}

$flash = isset($_GET['msg']) ? trim($_GET['msg']) : '';

// ---- Remember last used group size in session ----
if (isset($_POST['group_size']) && is_numeric($_POST['group_size'])) {
    $_SESSION['last_group_size'] = max(2, min(10, (int)$_POST['group_size']));
}

/* =========================================================
   Actions
   ========================================================= */

// Create custom group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_custom_group') {
    $group_name = trim($_POST['group_name'] ?? '');
    $members    = isset($_POST['members']) && is_array($_POST['members']) ? array_values($_POST['members']) : [];
    if ($group_name === '') redirect_with($course_id, 'Group name is required.');
    if (empty($members))    redirect_with($course_id, 'Select at least one student.');

    // Validate students belong to this course and grab CGPAs
    $placeholders = implode(',', array_fill(0, count($members), '?'));
    $types = 'i'.str_repeat('s', count($members));
    $sql = "SELECT student_university_id, student_cgpa
            FROM course_enrollments
            WHERE course_id=? AND student_university_id IN ($placeholders)";
    $stmt = $conn->prepare($sql);
    $params = array_merge([$course_id], $members);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $valid_rows = []; $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $valid_rows[] = $r;
    $stmt->close();
    if (!$valid_rows) redirect_with($course_id, 'Selected students are not in this course.');

    // Create group
    $stmt = $conn->prepare("INSERT INTO `groups` (course_id, name) VALUES (?, ?)");
    $stmt->bind_param('is', $course_id, $group_name);
    $stmt->execute();
    $gid = (int)$stmt->insert_id;
    $stmt->close();

    // Add members
    $ins = $conn->prepare("INSERT IGNORE INTO group_members (group_id, student_university_id) VALUES (?, ?)");
    foreach ($valid_rows as $row) {
        $sid = $row['student_university_id'];
        $ins->bind_param('is', $gid, $sid);
        $ins->execute();
    }
    $ins->close();

    // Leader = highest CGPA in this group
    $leader=null; $cgmax=-INF;
    foreach ($valid_rows as $row){
        $cg=$row['student_cgpa'];
        if (is_numeric($cg) && (float)$cg>$cgmax){ $cgmax=(float)$cg; $leader=$row['student_university_id']; }
    }
    if ($leader){
        $u=$conn->prepare("UPDATE `groups` SET leader_student_id=? WHERE id=?");
        $u->bind_param('si',$leader,$gid);
        $u->execute(); $u->close();
    }

    redirect_with($course_id, "Group '{$group_name}' created" . ($leader ? " (Leader: {$leader})" : ""));
}

// Auto-group (and "Assign remaining")
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'auto_group') {
    $group_size = (int)($_POST['group_size'] ?? ($_SESSION['last_group_size'] ?? 3));
    $group_size = max(2, min(10, $group_size));
    $_SESSION['last_group_size'] = $group_size;

    // Unassigned students ordered by CGPA desc (NULLs last)
    $sql = "
      SELECT ce.student_university_id, ce.student_cgpa
      FROM course_enrollments ce
      WHERE ce.course_id = ?
        AND NOT EXISTS (
            SELECT 1 FROM group_members gm
            JOIN `groups` g ON g.id = gm.group_id
            WHERE g.course_id = ce.course_id AND gm.student_university_id = ce.student_university_id
        )
      ORDER BY (ce.student_cgpa IS NULL) ASC, ce.student_cgpa DESC, ce.student_university_id ASC
    ";
    $s = $conn->prepare($sql);
    $s->bind_param('i', $course_id);
    $s->execute();
    $r = $s->get_result();
    $unassigned=[]; while($row=$r->fetch_assoc()) $unassigned[]=$row;
    $s->close();

    if (count($unassigned) < 2) redirect_with($course_id, 'Not enough unassigned students to form groups.');

    $conn->begin_transaction();
    try {
        $insG = $conn->prepare("INSERT INTO `groups` (course_id, name) VALUES (?, ?)");
        $insM = $conn->prepare("INSERT INTO group_members (group_id, student_university_id) VALUES (?, ?)");
        $updL = $conn->prepare("UPDATE `groups` SET leader_student_id = ? WHERE id = ?");

        // Calculate number of groups needed
        $num_groups = ceil(count($unassigned) / $group_size);
        
        // Initialize empty groups
        $groups = array_fill(0, $num_groups, []);
        
        // Distribute students in round-robin fashion
        $current_group = 0;
        foreach ($unassigned as $student) {
            $groups[$current_group][] = $student;
            $current_group = ($current_group + 1) % $num_groups;
        }

        $created=0; $seq=1;
        foreach ($groups as $group_students) {
            if (empty($group_students)) continue;
            
            $gname = "Auto Group {$seq}";
            $insG->bind_param('is',$course_id,$gname);
            $insG->execute(); 
            $gid=(int)$insG->insert_id;

            $leader=null; $cgmax=-INF;
            foreach($group_students as $student){
                $sid=$student['student_university_id']; 
                $cg=$student['student_cgpa'];
                $insM->bind_param('is',$gid,$sid); 
                $insM->execute();
                if (is_numeric($cg) && (float)$cg>$cgmax){ 
                    $cgmax=(float)$cg; 
                    $leader=$sid; 
                }
            }
            if ($leader){ 
                $updL->bind_param('si',$leader,$gid); 
                $updL->execute(); 
            }
            $created++; 
            $seq++;
        }

        $conn->commit();
        redirect_with($course_id, "Auto-grouped {$created} group(s) (size {$group_size}).");
    } catch (Throwable $e) {
        $conn->rollback();
        redirect_with($course_id, 'Auto-group failed: '.$e->getMessage());
    }
}

// Rename / Delete group / Remove member / Change leader
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['action'] ?? ''), ['delete_group','remove_member','rename_group','change_leader'], true)) {
    $gid = (int)($_POST['group_id'] ?? 0);
    if ($gid <= 0) redirect_with($course_id, 'Invalid group.');

    $chk = $conn->prepare("SELECT id FROM `groups` WHERE id=? AND course_id=?");
    $chk->bind_param('ii',$gid,$course_id);
    $chk->execute();
    $ok = $chk->get_result()->fetch_assoc();
    $chk->close();
    if (!$ok) redirect_with($course_id, 'Group not found for this course.');

    $act = $_POST['action'];

    if ($act === 'delete_group') {
        $d1=$conn->prepare("DELETE FROM group_members WHERE group_id=?");
        $d1->bind_param('i',$gid); $d1->execute(); $d1->close();
        $d2=$conn->prepare("DELETE FROM `groups` WHERE id=?");
        $d2->bind_param('i',$gid); $d2->execute(); $d2->close();
        redirect_with($course_id,'Group deleted.');
    }

    if ($act === 'rename_group') {
        $new = trim($_POST['new_name'] ?? '');
        if ($new==='') redirect_with($course_id,'New name cannot be empty.');
        $u=$conn->prepare("UPDATE `groups` SET name=? WHERE id=?");
        $u->bind_param('si',$new,$gid); $u->execute(); $u->close();
        redirect_with($course_id,'Group renamed.');
    }

    if ($act === 'remove_member') {
        $sid = trim($_POST['student_university_id'] ?? '');
        if ($sid==='') redirect_with($course_id,'Invalid member.');
        $rm=$conn->prepare("DELETE FROM group_members WHERE group_id=? AND student_university_id=?");
        $rm->bind_param('is',$gid,$sid); $rm->execute(); $rm->close();

        // Recompute leader (highest CGPA among remaining)
        $leader=null; $cgmax=-INF;
        $q=$conn->prepare("SELECT gm.student_university_id, ce.student_cgpa
                           FROM group_members gm
                           LEFT JOIN course_enrollments ce ON ce.course_id=? AND ce.student_university_id=gm.student_university_id
                           WHERE gm.group_id=?");
        $q->bind_param('ii',$course_id,$gid);
        $q->execute(); $res=$q->get_result();
        while($row=$res->fetch_assoc()){
            $cg=$row['student_cgpa'];
            if(is_numeric($cg) && (float)$cg>$cgmax){ $cgmax=(float)$cg; $leader=$row['student_university_id']; }
        }
        $q->close();
        $u=$conn->prepare("UPDATE `groups` SET leader_student_id=? WHERE id=?");
        $u->bind_param('si',$leader,$gid); $u->execute(); $u->close();

        redirect_with($course_id,'Member removed' . ($leader ? " (Leader: {$leader})" : ''));
    }

    if ($act === 'change_leader') {
        $sid = trim($_POST['student_university_id'] ?? '');
        if ($sid==='') redirect_with($course_id,'Select a member to promote as leader.');

        // Ensure the student is a member of this group
        $ck=$conn->prepare("SELECT 1 FROM group_members WHERE group_id=? AND student_university_id=? LIMIT 1");
        $ck->bind_param('is',$gid,$sid); $ck->execute();
        $is_member = (bool)$ck->get_result()->fetch_row(); $ck->close();
        if (!$is_member) redirect_with($course_id,'Selected student is not a member of this group.');

        $u=$conn->prepare("UPDATE `groups` SET leader_student_id=? WHERE id=?");
        $u->bind_param('si',$sid,$gid); $u->execute(); $u->close();
        redirect_with($course_id,"Leader changed to {$sid}.");
    }
}

/* =========================================================
   Data for page
   ========================================================= */

// Enrollments (with search)
$search = trim($_GET['q'] ?? '');
$enroll_sql = "SELECT student_university_id, student_cgpa FROM course_enrollments WHERE course_id = ?";
$params = [$course_id]; $types='i';
if ($search!==''){ $enroll_sql.=" AND student_university_id LIKE ?"; $params[]="%$search%"; $types.='s'; }
$enroll_sql.=" ORDER BY student_university_id ASC";
$es=$conn->prepare($enroll_sql); $es->bind_param($types, ...$params); $es->execute(); $students=$es->get_result(); $es->close();

// Totals
$c=$conn->prepare("SELECT COUNT(*) cnt FROM course_enrollments WHERE course_id=?");
$c->bind_param('i',$course_id); $c->execute();
$total_count=(int)$c->get_result()->fetch_assoc()['cnt']; $c->close();

// Unassigned count
$u=$conn->prepare("
  SELECT COUNT(*) cnt
  FROM course_enrollments ce
  WHERE ce.course_id=?
    AND NOT EXISTS (
      SELECT 1 FROM group_members gm
      JOIN `groups` g ON g.id=gm.group_id
      WHERE g.course_id=ce.course_id AND gm.student_university_id=ce.student_university_id
    )
");
$u->bind_param('i',$course_id); $u->execute();
$unassigned_count=(int)$u->get_result()->fetch_assoc()['cnt']; $u->close();

// Which students already belong to a group
$assigned=[];
$asg=$conn->prepare("SELECT gm.student_university_id FROM group_members gm JOIN `groups` g ON g.id=gm.group_id WHERE g.course_id=?");
$asg->bind_param('i',$course_id); $asg->execute();
$ar=$asg->get_result(); while($a=$ar->fetch_assoc()) $assigned[$a['student_university_id']]=true; $asg->close();

// Groups
$groups=[]; 
$g=$conn->prepare("SELECT id,name,leader_student_id,created_at FROM `groups` WHERE course_id=? ORDER BY created_at ASC, id ASC");
$g->bind_param('i',$course_id); $g->execute(); $gr=$g->get_result();
while($row=$gr->fetch_assoc()) $groups[]=$row; 
$g->close();

// Members by group
$members_by_group=[];
if ($groups){
  $gm=$conn->prepare("SELECT student_university_id FROM group_members WHERE group_id=? ORDER BY student_university_id ASC");
  foreach($groups as $gg){
    $gid=(int)$gg['id'];
    $gm->bind_param('i',$gid); $gm->execute(); $rr=$gm->get_result();
    $members_by_group[$gid]=[];
    while($m=$rr->fetch_assoc()) $members_by_group[$gid][]=$m['student_university_id'];
  }
  $gm->close();
}

$last_size = (int)($_SESSION['last_group_size'] ?? 3);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Course Dashboard | Educollab</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<style>
  :root {
    --educollab-primary: #4a6fa5;
    --educollab-secondary: #166088;
    --educollab-accent: #4cb5ae;
    --educollab-light: #e8f4f3;
    --educollab-dark: #2c3e50;
    --success: #10b981;
    --warning: #f59e0b;
    --danger: #ef4444;
    --light-gray: #e2e8f0;
    --medium-gray: #94a3b8;
    --dark-gray: #334155;
    --white: #ffffff;
    --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    --hover-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
  }
  
  * {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
  }
  
  body {
    font-family: 'Poppins', sans-serif;
    background-color: #f8fafc;
    color: var(--educollab-dark);
    line-height: 1.6;
    padding: 0;
  }
  
  .container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
  }
  
  .back-link {
    display: inline-flex;
    align-items: center;
    color: var(--educollab-primary);
    text-decoration: none;
    font-weight: 500;
    margin-bottom: 20px;
    padding: 8px 12px;
    border-radius: 8px;
    transition: background-color 0.2s;
  }
  
  .back-link:hover {
    background-color: var(--educollab-light);
  }
  
  .back-link i {
    margin-right: 8px;
  }
  
  .notice {
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    font-weight: 500;
  }
  
  .notice.ok {
    background-color: #ecfdf5;
    color: #065f46;
    border: 1px solid #a7f3d0;
  }
  
  .notice.warn {
    background-color: #fffbeb;
    color: #9a3412;
    border: 1px solid #fde68a;
  }
  
  .notice i {
    margin-right: 12px;
    font-size: 20px;
  }
  
  .card {
    background-color: var(--white);
    border-radius: 16px;
    box-shadow: var(--card-shadow);
    padding: 24px;
    margin-bottom: 24px;
    transition: box-shadow 0.3s ease;
  }
  
  .card:hover {
    box-shadow: var(--hover-shadow);
  }
  
  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid var(--light-gray);
  }
  
  .card-title {
    font-size: 24px;
    font-weight: 600;
    color: var(--educollab-primary);
  }
  
  .card-subtitle {
    color: var(--medium-gray);
    font-size: 16px;
    margin-top: 4px;
  }
  
  .stats-container {
    display: flex;
    gap: 16px;
    margin-bottom: 20px;
  }
  
  .stat-box {
    background: linear-gradient(135deg, var(--educollab-primary) 0%, var(--educollab-secondary) 100%);
    color: white;
    padding: 16px;
    border-radius: 12px;
    text-align: center;
    flex: 1;
  }
  
  .stat-number {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 4px;
  }
  
  .stat-label {
    font-size: 14px;
    opacity: 0.9;
  }
  
  .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background-color: var(--educollab-primary);
    color: white;
    border: none;
    border-radius: 10px;
    padding: 12px 20px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
  }
  
  .btn:hover {
    background-color: var(--educollab-secondary);
    transform: translateY(-2px);
  }
  
  .btn i {
    margin-right: 8px;
  }
  
  .btn-outline {
    background-color: transparent;
    border: 2px solid var(--educollab-primary);
    color: var(--educollab-primary);
  }
  
  .btn-outline:hover {
    background-color: var(--educollab-primary);
    color: white;
  }
  
  .btn-danger {
    background-color: var(--danger);
  }
  
  .btn-danger:hover {
    background-color: #dc2626;
  }
  
  .btn-sm {
    padding: 8px 12px;
    font-size: 14px;
  }
  
  .form-group {
    margin-bottom: 16px;
  }
  
  .form-label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--educollab-dark);
  }
  
  .form-input {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid var(--light-gray);
    border-radius: 10px;
    font-size: 16px;
    transition: border-color 0.2s;
  }
  
  .form-input:focus {
    outline: none;
    border-color: var(--educollab-primary);
    box-shadow: 0 0 0 3px rgba(74, 111, 165, 0.2);
  }
  
  .form-select {
    width: 100%;
    padding: 12px 16px;
    border: 1px solid var(--light-gray);
    border-radius: 10px;
    font-size: 16px;
    background-color: var(--white);
  }
  
  table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 16px;
  }
  
  th, td {
    padding: 12px 16px;
    text-align: left;
    border-bottom: 1px solid var(--light-gray);
  }
  
  th {
    background-color: #f8fafc;
    font-weight: 600;
    color: var(--educollab-dark);
  }
  
  tr:hover {
    background-color: #f1f5f9;
  }
  
  .checkbox-cell {
    width: 40px;
    text-align: center;
  }
  
  .status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 500;
  }
  
  .status-assigned {
    background-color: #ecfdf5;
    color: #065f46;
  }
  
  .status-unassigned {
    background-color: #eff6ff;
    color: #1e40af;
  }
  
  .groups-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
    margin-top: 20px;
  }
  
  .group-card {
    background-color: var(--white);
    border-radius: 12px;
    box-shadow: var(--card-shadow);
    padding: 20px;
    border: 1px solid var(--light-gray);
    transition: transform 0.2s, box-shadow 0.2s;
  }
  
  .group-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--hover-shadow);
  }
  
  .group-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
  }
  
  .group-name {
    font-size: 18px;
    font-weight: 600;
    color: var(--educollab-primary);
  }
  
  .group-leader {
    margin-top: 8px;
    font-size: 14px;
    color: var(--medium-gray);
  }
  
  .group-actions {
    display: flex;
    gap: 8px;
  }
  
  .members-list {
    list-style: none;
    margin-top: 16px;
  }
  
  .member-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f1f5f9;
  }
  
  .member-item:last-child {
    border-bottom: none;
  }
  
  .leader-badge {
    background-color: #fef3c7;
    color: #92400e;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 12px;
    margin-left: 8px;
  }
  
  .search-form {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
  }
  
  .auto-group-form {
    display: flex;
    gap: 12px;
    align-items: center;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--light-gray);
  }
  
  @media (max-width: 768px) {
    .stats-container {
      flex-direction: column;
    }
    
    .groups-grid {
      grid-template-columns: 1fr;
    }
    
    .search-form {
      flex-direction: column;
    }
    
    .auto-group-form {
      flex-direction: column;
      align-items: stretch;
    }
  }
</style>
</head>
<body>
<div class="container">
  <a href="professor_dashboard.php" class="back-link">
    <i class="fas fa-arrow-left"></i> Back to Dashboard
  </a>

  <?php if ($flash): ?>
    <div class="notice <?= (stripos($flash,'created')!==false || stripos($flash,'Auto-grouped')!==false || stripos($flash,'renamed')!==false || stripos($flash,'deleted')!==false || stripos($flash,'removed')!==false || stripos($flash,'Leader changed')!==false) ? 'ok' : 'warn' ?>">
      <i class="fas <?= (stripos($flash,'created')!==false || stripos($flash,'Auto-grouped')!==false || stripos($flash,'renamed')!==false || stripos($flash,'deleted')!==false || stripos($flash,'removed')!==false || stripos($flash,'Leader changed')!==false) ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
      <?= htmlspecialchars($flash) ?>
    </div>
  <?php endif; ?>

  <!-- Course header -->
  <div class="card">
    <div class="card-header">
      <div>
        <h1 class="card-title"><?= htmlspecialchars($course['name']) ?></h1>
        <div class="card-subtitle">Code: <?= htmlspecialchars($course['code']) ?> • Created: <?= htmlspecialchars($course['created_at']) ?></div>
      </div>
      <a href="taskboard.php?course_id=<?= (int)$course_id ?>" class="btn">
        <i class="fas fa-tasks"></i> Task Board
      </a>
    </div>

    <div class="stats-container">
      <div class="stat-box">
        <div class="stat-number"><?= (int)$total_count ?></div>
        <div class="stat-label">Total Students</div>
      </div>
      <div class="stat-box">
        <div class="stat-number"><?= (int)$unassigned_count ?></div>
        <div class="stat-label">Unassigned Students</div>
      </div>
      <div class="stat-box">
        <div class="stat-number"><?= count($groups) ?></div>
        <div class="stat-label">Groups Created</div>
      </div>
    </div>

    <!-- Auto-Group control -->
    <form method="post" action="course_dashboard.php?id=<?= (int)$course_id ?>" class="auto-group-form">
      <input type="hidden" name="action" value="auto_group">
      <div style="display: flex; gap: 12px; align-items: center;">
        <label for="group_size" style="white-space: nowrap;">Group Size:</label>
        <input type="number" min="2" max="10" name="group_size" value="<?= (int)$last_size ?>" class="form-input" style="width: 80px;">
      </div>
      <button class="btn" type="submit">
        <i class="fas fa-users"></i> Auto-Group Students
      </button>
    </form>
  </div>

  <!-- Enrolled + Custom Group Creation -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Enrolled Students</h2>
      <form method="get" action="course_dashboard.php" class="search-form">
        <input type="hidden" name="id" value="<?= (int)$course_id ?>">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search students..." class="form-input">
        <button class="btn" type="submit">
          <i class="fas fa-search"></i> Search
        </button>
      </form>
    </div>

    <p class="card-subtitle">Select unassigned students to create a custom group</p>

    <form method="post" action="course_dashboard.php?id=<?= (int)$course_id ?>">
      <input type="hidden" name="action" value="create_custom_group">
      <div class="form-group">
        <label for="group_name" class="form-label">New Group Name</label>
        <div style="display: flex; gap: 12px;">
          <input type="text" name="group_name" placeholder="Enter group name" class="form-input" required>
          <button class="btn" type="submit">
            <i class="fas fa-plus"></i> Create Group
          </button>
        </div>
      </div>

      <?php if ($students->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th class="checkbox-cell">Select</th>
              <th>Student ID</th>
              <th>CGPA</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php while($s=$students->fetch_assoc()):
              $sid=$s['student_university_id']; $in_group=isset($assigned[$sid]); ?>
              <tr>
                <td class="checkbox-cell">
                  <input type="checkbox" name="members[]" value="<?= htmlspecialchars($sid) ?>" <?= $in_group ? 'disabled' : '' ?>>
                </td>
                <td><?= htmlspecialchars($sid) ?></td>
                <td><?= is_null($s['student_cgpa']) ? '<span style="color: var(--medium-gray);">—</span>' : htmlspecialchars(number_format((float)$s['student_cgpa'], 2)) ?></td>
                <td>
                  <span class="status-badge <?= $in_group ? 'status-assigned' : 'status-unassigned' ?>">
                    <?= $in_group ? 'Assigned' : 'Unassigned' ?>
                  </span>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p style="text-align: center; padding: 20px; color: var(--medium-gray);">
          No students found<?= $search ? ' matching your search' : '' ?>.
        </p>
      <?php endif; ?>
    </form>
  </div>

  <!-- Groups (manage) -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Groups</h2>
      <div class="card-subtitle">Manage student groups</div>
    </div>
    
    <?php if (!empty($groups)): ?>
      <div class="groups-grid">
        <?php foreach($groups as $g):
          $gid=(int)$g['id']; $members=$members_by_group[$gid] ?? []; ?>
          <div class="group-card">
            <div class="group-header">
              <div>
                <div class="group-name"><?= htmlspecialchars($g['name']) ?></div>
                <?php if (!empty($g['leader_student_id'])): ?>
                  <div class="group-leader">Leader: <?= htmlspecialchars($g['leader_student_id']) ?></div>
                <?php else: ?>
                  <div class="group-leader">No leader assigned</div>
                <?php endif; ?>
              </div>
              <div class="group-actions">
                <form method="post" action="course_dashboard.php?id=<?= (int)$course_id ?>" onsubmit="return confirm('Delete this group?');">
                  <input type="hidden" name="action" value="delete_group">
                  <input type="hidden" name="group_id" value="<?= $gid ?>">
                  <button class="btn btn-danger btn-sm" type="submit" title="Delete group">
                    <i class="fas fa-trash"></i>
                  </button>
                </form>
              </div>
            </div>

            <!-- Rename form -->
            <form method="post" action="course_dashboard.php?id=<?= (int)$course_id ?>" style="margin-bottom: 16px;">
              <input type="hidden" name="action" value="rename_group">
              <input type="hidden" name="group_id" value="<?= $gid ?>">
              <div style="display: flex; gap: 8px;">
                <input type="text" name="new_name" value="<?= htmlspecialchars($g['name']) ?>" class="form-input" style="flex: 1;">
                <button class="btn btn-sm" type="submit">
                  <i class="fas fa-edit"></i> Rename
                </button>
              </div>
            </form>

            <?php if ($members): ?>
              <!-- Change leader dropdown -->
              <form method="post" action="course_dashboard.php?id=<?= (int)$course_id ?>" style="margin-bottom: 16px;">
                <input type="hidden" name="action" value="change_leader">
                <input type="hidden" name="group_id" value="<?= $gid ?>">
                <div style="display: flex; gap: 8px;">
                  <select name="student_university_id" class="form-select" style="flex: 1;">
                    <?php foreach ($members as $sid): ?>
                      <option value="<?= htmlspecialchars($sid) ?>" <?= (!empty($g['leader_student_id']) && $g['leader_student_id']===$sid)?'selected':'' ?>>
                        <?= htmlspecialchars($sid) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <button class="btn btn-sm" type="submit">
                    <i class="fas fa-crown"></i> Set Leader
                  </button>
                </div>
              </form>

              <h3 style="font-size: 16px; margin-bottom: 12px;">Members (<?= count($members) ?>)</h3>
              <ul class="members-list">
                <?php foreach ($members as $sid): ?>
                  <li class="member-item">
                    <div>
                      <?= htmlspecialchars($sid) ?>
                      <?php if (!empty($g['leader_student_id']) && $g['leader_student_id'] === $sid): ?>
                        <span class="leader-badge">Leader</span>
                      <?php endif; ?>
                    </div>
                    <form method="post" action="course_dashboard.php?id=<?= (int)$course_id ?>" onsubmit="return confirm('Remove this student from the group?');">
                      <input type="hidden" name="action" value="remove_member">
                      <input type="hidden" name="group_id" value="<?= $gid ?>">
                      <input type="hidden" name="student_university_id" value="<?= htmlspecialchars($sid) ?>">
                      <button class="btn btn-danger btn-sm" type="submit" title="Remove member">
                        <i class="fas fa-times"></i>
                      </button>
                    </form>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else: ?>
              <p style="text-align: center; color: var(--medium-gray); padding: 16px 0;">
                No members in this group yet.
              </p>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <p style="text-align: center; padding: 20px; color: var(--medium-gray);">
        No groups created yet. Create your first group using the form above.
      </p>
    <?php endif; ?>
  </div>
</div>

<script>
  // Simple animations for better user experience
  document.addEventListener('DOMContentLoaded', function() {
    // Animate cards on page load
    const cards = document.querySelectorAll('.card');
    cards.forEach((card, index) => {
      card.style.opacity = '0';
      card.style.transform = 'translateY(20px)';
      
      setTimeout(() => {
        card.style.transition = 'opacity 0.5s, transform 0.5s';
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
      }, 100 * index);
    });
    
    // Add hover effects to group cards
    const groupCards = document.querySelectorAll('.group-card');
    groupCards.forEach(card => {
      card.addEventListener('mouseenter', () => {
        card.style.transform = 'translateY(-8px)';
      });
      card.addEventListener('mouseleave', () => {
        card.style.transform = 'translateY(0)';
      });
    });
  });
</script>
</body>
</html>