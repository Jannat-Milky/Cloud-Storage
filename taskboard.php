<?php
// taskboard.php — Integrated Task Board for Student + Leader + Professor
session_start();
require 'db.php';
require 'student_utils.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
  header("Location: login.php");
  exit;
}

$user_id = $_SESSION['user_id'];
$ROLE = $_SESSION['role']; // 'student' or 'professor' in your system

// Map roles: professor becomes teacher, and we'll determine leader status later
if ($ROLE === 'professor') {
  $ROLE = 'teacher';
}

// For students and professors, get university_id
$university_id = null;
if ($ROLE === 'student' || $ROLE === 'teacher') {
  $university_id = getStudentUniversityId($conn, $user_id);
  if (!$university_id && $ROLE === 'student') {
    $_SESSION['error'] = 'Cannot determine your university ID.';
    header("Location: files.php");
    exit;
  }
}

// ---- Helpers ---------------------------------------------------------------
function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function ensureDir($path){
  if (!is_dir($path)) { 
    mkdir($path, 0775, true); 
  }
}

function saveUpload($fileKey, $destDir){
  if (!isset($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
    return [false, null, "No file or upload error."];
  }
  
  ensureDir($destDir);
  $name = basename($_FILES[$fileKey]['name']);
  $ext = pathinfo($name, PATHINFO_EXTENSION);
  $safe = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', pathinfo($name, PATHINFO_FILENAME));
  $final = $destDir . '/' . time() . '_' . bin2hex(random_bytes(4)) . ($ext ? ('.'.$ext) : '');
  
  if (!move_uploaded_file($_FILES[$fileKey]['tmp_name'], $final)) {
    return [false, null, "Failed to move uploaded file."];
  }
  
  return [true, $final, null];
}

// Get user's group ID within a course
function getUserGroupId($conn, $courseId, $universityId) {
  $sql = "SELECT g.id AS gid 
          FROM `groups` g 
          JOIN group_members gm ON gm.group_id = g.id
          WHERE g.course_id = ? AND gm.student_university_id = ?";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('is', $courseId, $universityId);
  
  if ($stmt->execute()) {
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
      return intval($row['gid']);
    }
  }
  return null;
}

function badge($status) {
  $map = [
    'pending'   => ['🔴','Pending'],
    'rejected'  => ['🔴','Rejected'],
    'accepted'  => ['🟢','Accepted'],
    'submitted' => ['🟡','Submitted'],
    'completed' => ['🟢','Completed'],
    'approved'  => ['🟢','Approved'],
  ];
  $d = $map[$status] ?? ['',''];
  return '<span class="badge '.$status.'">'.$d[0].' '.$d[1].'</span>';
}

function statusColorCss() {
  return <<<CSS
.badge { display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600; }
.badge.pending, .badge.rejected { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
.badge.accepted, .badge.completed, .badge.approved { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
.badge.submitted { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
.btn { display: inline-block; padding: 8px 12px; border-radius: 8px; border: 1px solid #e5e7eb; background: #fff; cursor: pointer; text-decoration: none; }
.btn:hover { background: #f9fafb; }
.btn.primary { background: #111827; color: #fff; border-color: #111827; }
.btn.danger { border-color: #fecaca; background: #fee2e2; }
.btn.success { border-color: #bbf7d0; background: #dcfce7; }
.btn.warn { border-color: #fde68a; background: #fef9c3; }
.card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px; margin-bottom: 16px; box-shadow: 0 1px 2px rgba(0,0,0,0.03); }
.input, select, textarea { width: 100%; padding: 8px 10px; border: 1px solid #e5e7eb; border-radius: 8px; }
.table { width: 100%; border-collapse: collapse; }
.table th, .table td { border-bottom: 1px solid #f1f5f9; padding: 10px; text-align: left; }
.toolbar { display: flex; gap: 8px; flex-wrap: wrap; }
.grid { display: grid; gap: 16px; }
.grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
.header { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
.small { font-size: 12px; color: #64748b; }
CSS;
}

// ---- Inputs / Filters ------------------------------------------------------
$courseId = null;
if ($ROLE === 'teacher') {
  $courseId = isset($_GET['course_id']) ? intval($_GET['course_id']) : null;
  $filterGroupId = isset($_GET['group_id']) ? intval($_GET['group_id']) : null;
} else {
  $courseId = isset($_GET['course_id']) ? intval($_GET['course_id']) : null;
  $filterGroupId = null;
}

// Check if student is a leader in their group
$myGroupId = null;
if ($ROLE === 'student' && $courseId) {
  $myGroupId = getUserGroupId($conn, $courseId, $university_id);
  
  if ($myGroupId) {
    // Check if this student is the leader of the group
    $stmt = $conn->prepare("SELECT leader_student_id FROM `groups` WHERE id = ?");
    $stmt->bind_param('i', $myGroupId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
      if ($row['leader_student_id'] === $university_id) {
        $ROLE = 'leader';
      }
    }
  }
}

// ---- POST Actions ----------------------------------------------------------
$errors = [];
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // 1) Leader assigns task
if ($action === 'assign_task' && $ROLE === 'leader') {
    $title    = trim($_POST['title'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $assignee = trim($_POST['assigned_to'] ?? '');
    $cid      = intval($_POST['course_id'] ?? 0);
    $gid      = intval($_POST['group_id'] ?? 0);

    if (!$title || !$assignee || !$cid || !$gid) {
        $errors[] = "Please fill in title, assignee, course, and group.";
    } else {
        // 🔎 Check if a similar task already exists
        $check = $conn->prepare("
            SELECT id, status 
            FROM tasks 
            WHERE course_id=? AND group_id=? AND assigned_to=? AND title=? 
            LIMIT 1
        ");
        $check->bind_param('iiss', $cid, $gid, $assignee, $title);
        $check->execute();
        $res = $check->get_result();

        if ($row = $res->fetch_assoc()) {
            // Task already exists
            $errors[] = "This task already exists for $assignee (status: ".$row['status'].").";
        } else {
            // Insert new task
            $stmt = $conn->prepare("
                INSERT INTO tasks (course_id, group_id, assigned_to, title, description, status, created_at) 
                VALUES (?,?,?,?,?,'pending', NOW())
            ");
            $stmt->bind_param('iisss', $cid, $gid, $assignee, $title, $desc);
            if ($stmt->execute()) {
                $messages[] = "Task assigned to $assignee.";
            } else {
                $errors[] = "DB error: ".$conn->error;
            }
        }
    }
}

  // 2) Student accepts / rejects
  if ($action === 'accept_task' && $ROLE === 'student') {
    $tid = intval($_POST['task_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE tasks SET status='accepted' WHERE id=? AND assigned_to=?");
    $stmt->bind_param('is', $tid, $university_id);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
      $messages[] = "Task accepted.";
    } else {
      $errors[] = "Unable to accept task.";
    }
  }
  
  if ($action === 'reject_task' && $ROLE === 'student') {
    $tid = intval($_POST['task_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE tasks SET status='rejected' WHERE id=? AND assigned_to=?");
    $stmt->bind_param('is', $tid, $university_id);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
      $messages[] = "Task rejected.";
    } else {
      $errors[] = "Unable to reject task.";
    }
  }

  // 3) Student upload & mark done (submitted)
  if ($action === 'submit_task' && $ROLE === 'student') {
    $tid = intval($_POST['task_id'] ?? 0);
    [$ok, $path, $err] = saveUpload('task_file', 'uploads/tasks');
    if (!$ok) { 
      $errors[] = $err; 
    } else {
      $stmt = $conn->prepare("UPDATE tasks SET file_path=?, status='submitted' WHERE id=? AND assigned_to=?");
      $stmt->bind_param('sis', $path, $tid, $university_id);
      $stmt->execute();
      if ($stmt->affected_rows > 0) {
        $messages[] = "Work submitted. Waiting for leader approval.";
      } else {
        $errors[] = "Unable to submit work.";
      }
    }
  }

  // 4) Leader approve / reject a submitted task
  if ($action === 'approve_task' && $ROLE === 'leader') {
    $tid = intval($_POST['task_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE tasks SET status='completed' WHERE id=?");
    $stmt->bind_param('i', $tid);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
      $messages[] = "Task approved.";
    } else {
      $errors[] = "Unable to approve task.";
    }
  }
  
  if ($action === 'reject_after_submit' && $ROLE === 'leader') {
    $tid = intval($_POST['task_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE tasks SET status='rejected' WHERE id=?");
    $stmt->bind_param('i', $tid);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
      $messages[] = "Task sent back (rejected).";
    } else {
      $errors[] = "Unable to reject.";
    }
  }

  // 5) Leader final group submission
  if ($action === 'final_submit' && $ROLE === 'leader') {
    $cid = intval($_POST['course_id'] ?? 0);
    $gid = intval($_POST['group_id'] ?? 0);
    [$ok, $path, $err] = saveUpload('final_file', 'uploads/finals');
    
    if (!$ok) { 
      $errors[] = $err; 
    } else {
      // Upsert: if exists, update; else insert
      $check = $conn->prepare("SELECT id FROM final_submissions WHERE course_id=? AND group_id=?");
      $check->bind_param('ii', $cid, $gid);
      $check->execute();
      $res = $check->get_result();
      
      if ($row = $res->fetch_assoc()) {
        $id = intval($row['id']);
        $up = $conn->prepare("UPDATE final_submissions SET file_path=?, status='submitted' WHERE id=?");
        $up->bind_param('si', $path, $id);
        $up->execute();
        if ($up->affected_rows >= 0) {
          $messages[] = "Final submission updated.";
        } else {
          $errors[] = "Unable to update final submission.";
        }
      } else {
        $ins = $conn->prepare("INSERT INTO final_submissions (course_id, group_id, file_path, status) VALUES (?,?,?,'submitted')");
        $ins->bind_param('iis', $cid, $gid, $path);
        if ($ins->execute()) {
          $messages[] = "Final submission uploaded.";
        } else {
          $errors[] = "Unable to create final submission.";
        }
      }
    }
  }

  // 6) Teacher approves/rejects final submission
  if ($action === 'approve_final' && $ROLE === 'teacher') {
    $fid = intval($_POST['final_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE final_submissions SET status='approved' WHERE id=?");
    $stmt->bind_param('i', $fid);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
      $messages[] = "Final submission approved.";
    } else {
      $errors[] = "Unable to approve final.";
    }
  }
  
  if ($action === 'reject_final' && $ROLE === 'teacher') {
    $fid = intval($_POST['final_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE final_submissions SET status='rejected' WHERE id=?");
    $stmt->bind_param('i', $fid);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
      $messages[] = "Final submission rejected.";
    } else {
      $errors[] = "Unable to reject final.";
    }
  }
}

// ---- Data Fetching ---------------------------------------------------------
// Teacher: list available courses and groups for dropdowns
$courses = [];
$groups = [];

if ($ROLE === 'teacher') {
  $crs = $conn->query("SELECT id, code as course_code, name as course_name FROM courses ORDER BY id DESC");
  while($row = $crs->fetch_assoc()) {
    $courses[] = $row;
  }

  if ($courseId) {
    $stmt = $conn->prepare("SELECT id, name as group_name FROM `groups` WHERE course_id=? ORDER BY id DESC");
    $stmt->bind_param('i', $courseId);
    $stmt->execute();
    $res = $stmt->get_result();
    while($r = $res->fetch_assoc()) {
      $groups[] = $r;
    }
  }
}

// Determine active group for student/leader
$myGroupId = null;
if ($ROLE !== 'teacher' && $courseId) {
  $myGroupId = getUserGroupId($conn, $courseId, $university_id);
}

// Fetch task lists
$myTasks = [];
$groupTasks = [];
$finalRow = null;

if ($ROLE === 'student' && $courseId && $myGroupId) {
  $stmt = $conn->prepare("SELECT * FROM tasks WHERE course_id=? AND group_id=? AND assigned_to=? ORDER BY created_at DESC");
  $stmt->bind_param('iis', $courseId, $myGroupId, $university_id);
  $stmt->execute();
  $myTasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

if ($ROLE === 'leader' && $courseId && $myGroupId) {
  $stmt = $conn->prepare("SELECT * FROM tasks WHERE course_id=? AND group_id=? ORDER BY created_at DESC");
  $stmt->bind_param('ii', $courseId, $myGroupId);
  $stmt->execute();
  $groupTasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

  // Group members for assignment
  $members = [];
  $mstmt = $conn->prepare("SELECT gm.student_university_id AS uid 
                           FROM group_members gm 
                           WHERE gm.group_id=? ORDER BY gm.student_university_id");
  $mstmt->bind_param('i', $myGroupId);
  $mstmt->execute();
  $mres = $mstmt->get_result();
  while($r = $mres->fetch_assoc()) {
    $members[] = $r['uid'];
  }

  // Final submission for this group
  $fs = $conn->prepare("SELECT * FROM final_submissions WHERE course_id=? AND group_id=?");
  $fs->bind_param('ii', $courseId, $myGroupId);
  $fs->execute();
  $finalRow = $fs->get_result()->fetch_assoc();
}

if ($ROLE === 'teacher' && $courseId) {
  // Teacher can see tasks by selected group (if any)
  if ($filterGroupId) {
    $stmt = $conn->prepare("SELECT * FROM tasks WHERE course_id=? AND group_id=? ORDER BY created_at DESC");
    $stmt->bind_param('ii', $courseId, $filterGroupId);
    $stmt->execute();
    $groupTasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $fs = $conn->prepare("SELECT * FROM final_submissions WHERE course_id=? AND group_id=?");
    $fs->bind_param('ii', $courseId, $filterGroupId);
    $fs->execute();
    $finalRow = $fs->get_result()->fetch_assoc();
  }
}

// ---- HTML ------------------------------------------------------------------
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Task Board</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style><?= statusColorCss(); ?></style>
</head>
<body style="font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial; background:#f8fafc; padding:24px;">

  <div class="header">
    <h1 style="margin:0;">Task Board</h1>
    <div class="small">Role: <strong><?=h(ucfirst($ROLE))?></strong> • User: <?=h($university_id ?? $user_id)?></div>
  </div>

  <?php if (!empty($messages)): ?>
    <div class="card" style="border-color:#bbf7d0;background:#ecfdf5;">
      <ul style="margin:0;padding-left:18px;">
        <?php foreach($messages as $m) echo "<li>".h($m)."</li>"; ?>
      </ul>
    </div>
  <?php endif; ?>
  
  <?php if (!empty($errors)): ?>
    <div class="card" style="border-color:#fecaca;background:#fef2f2;">
      <ul style="margin:0;padding-left:18px;">
        <?php foreach($errors as $e) echo "<li>".h($e)."</li>"; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($ROLE === 'teacher'): ?>
    <div class="card">
      <form method="get" class="grid grid-3" enctype="application/x-www-form-urlencoded">
        <div>
          <label>Course</label>
          <select name="course_id">
            <option value="">-- Select Course --</option>
            <?php foreach($courses as $c): ?>
              <option value="<?=$c['id']?>" <?=($courseId==$c['id']?'selected':'')?>>
                <?=h($c['course_code'] ?? ('Course #'.$c['id']))?> - <?=h($c['course_name'] ?? '')?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label>Group</label>
          <select name="group_id">
            <option value="">-- All / Select Group --</option>
            <?php foreach($groups as $g): ?>
              <option value="<?=$g['id']?>" <?=($filterGroupId==$g['id']?'selected':'')?>>
                <?=h($g['group_name'] ?? ('Group #'.$g['id']))?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div style="align-self:end;">
          <button class="btn primary" type="submit">Apply</button>
          <a class="btn" href="taskboard.php">Reset</a>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <?php if (($ROLE==='student' || $ROLE==='leader') && !$courseId): ?>
    <div class="card">Please select a course first.</div>
  <?php endif; ?>

  <!-- Student View -->
  <?php if ($ROLE==='student' && $courseId && $myGroupId): ?>
    <div class="card">
      <h2 style="margin-top:0;">My Assigned Tasks</h2>
      <?php if (!$myTasks): ?>
        <div class="small">No tasks yet.</div>
      <?php else: ?>
        <table class="table">
          <thead><tr>
            <th>Title</th><th>Description</th><th>Status</th><th>Actions</th><th>My Upload</th>
          </tr></thead>
          <tbody>
          <?php foreach($myTasks as $t): ?>
            <tr>
              <td><?=h($t['title'])?></td>
              <td><?=nl2br(h($t['description']))?></td>
              <td><?=badge($t['status'])?></td>
              <td class="toolbar">
                <?php if ($t['status']==='pending'): ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="action" value="accept_task">
                    <input type="hidden" name="task_id" value="<?=$t['id']?>">
                    <button class="btn success" type="submit">Accept</button>
                  </form>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="action" value="reject_task">
                    <input type="hidden" name="task_id" value="<?=$t['id']?>">
                    <button class="btn danger" type="submit">Reject</button>
                  </form>
                <?php endif; ?>

                <?php if ($t['status']==='accepted'): ?>
                  <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="submit_task">
                    <input type="hidden" name="task_id" value="<?=$t['id']?>">
                    <input class="input" type="file" name="task_file" required>
                    <div style="height:6px;"></div>
                    <button class="btn warn" type="submit">Upload & Mark as Done</button>
                  </form>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($t['file_path']): ?>
                  <a class="btn" href="<?=h($t['file_path'])?>" target="_blank">View Upload</a>
                <?php else: ?>
                  <span class="small">No file uploaded</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Leader View -->
  <?php if ($ROLE==='leader' && $courseId && $myGroupId): ?>
    <div class="grid grid-2">
      <div class="card">
        <h2 style="margin-top:0;">Assign a Task</h2>
        <?php
          if (!isset($members)) {
            $members = [];
            $mstmt2 = $conn->prepare("SELECT gm.student_university_id AS uid FROM group_members gm WHERE gm.group_id=? ORDER BY gm.student_university_id");
            $mstmt2->bind_param('i', $myGroupId);
            $mstmt2->execute();
            $mres2 = $mstmt2->get_result();
            while($r = $mres2->fetch_assoc()) {
              $members[] = $r['uid'];
            }
          }
        ?>
        <?php if (!$members): ?>
          <div class="small">No members found in your group.</div>
        <?php else: ?>
          <form method="post" class="grid" enctype="multipart/form-data">
            <input type="hidden" name="action" value="assign_task">
            <input type="hidden" name="course_id" value="<?=$courseId?>">
            <input type="hidden" name="group_id" value="<?=$myGroupId?>">
            <div>
              <label>Title</label>
              <input class="input" type="text" name="title" required>
            </div>
            <div>
              <label>Assign to</label>
              <select name="assigned_to" required>
                <option value="">-- Select Member --</option>
                <?php foreach($members as $m): ?>
                  <option value="<?=h($m)?>"><?=h($m)?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label>Description</label>
              <textarea class="input" name="description" rows="3" placeholder="What needs to be done? (optional)"></textarea>
            </div>
            <div>
              <button class="btn primary" type="submit">Assign Task</button>
            </div>
          </form>
        <?php endif; ?>
      </div>

      <div class="card">
        <h2 style="margin-top:0;">Final Group Submission</h2>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="final_submit">
          <input type="hidden" name="course_id" value="<?=$courseId?>">
          <input type="hidden" name="group_id" value="<?=$myGroupId?>">
          <input class="input" type="file" name="final_file" required>
          <div style="height:6px;"></div>
          <button class="btn primary" type="submit">Upload Final</button>
          <?php if ($finalRow): ?>
            <div style="margin-top:8px;">
              Current: <?=badge($finalRow['status'])?>
              <?php if (!empty($finalRow['file_path'])): ?>
                • <a class="btn" href="<?=h($finalRow['file_path'])?>" target="_blank">View</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <div class="card">
      <h2 style="margin-top:0;">Group Tasks</h2>
      <?php if (!$groupTasks): ?>
        <div class="small">No tasks yet.</div>
      <?php else: ?>
        <table class="table">
          <thead><tr>
            <th>Member</th><th>Title</th><th>Description</th><th>Status</th><th>Submission</th><th>Actions</th>
          </tr></thead>
          <tbody>
          <?php foreach($groupTasks as $t): ?>
            <tr>
              <td><?=h($t['assigned_to'])?></td>
              <td><?=h($t['title'])?></td>
              <td><?=nl2br(h($t['description']))?></td>
              <td><?=badge($t['status'])?></td>
              <td>
                <?php if ($t['file_path']): ?>
                  <a class="btn" href="<?=h($t['file_path'])?>" target="_blank">View File</a>
                <?php else: ?>
                  <span class="small">No file</span>
                <?php endif; ?>
              </td>
              <td class="toolbar">
                <?php if ($t['status']==='submitted'): ?>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="action" value="approve_task">
                    <input type="hidden" name="task_id" value="<?=$t['id']?>">
                    <button class="btn success" type="submit">Approve</button>
                  </form>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="action" value="reject_after_submit">
                    <input type="hidden" name="task_id" value="<?=$t['id']?>">
                    <button class="btn danger" type="submit">Reject</button>
                  </form>
                <?php else: ?>
                  <span class="small">Waiting for student</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Teacher View -->
  <?php if ($ROLE==='teacher' && $courseId): ?>
    <div class="card">
      <h2 style="margin-top:0;">Tasks (Course: <?=$courseId?><?= $filterGroupId ? ", Group: ".$filterGroupId : "" ?>)</h2>
      <?php if (!$filterGroupId): ?>
        <div class="small">Select a group to view tasks and final submission.</div>
      <?php endif; ?>

      <?php if ($filterGroupId): ?>
        <h3>Group Tasks</h3>
        <?php if (!$groupTasks): ?>
          <div class="small">No tasks in this group yet.</div>
        <?php else: ?>
          <table class="table">
            <thead><tr>
              <th>Member</th><th>Title</th><th>Description</th><th>Status</th><th>Submission</th>
            </tr></thead>
            <tbody>
              <?php foreach($groupTasks as $t): ?>
                <tr>
                  <td><?=h($t['assigned_to'])?></td>
                  <td><?=h($t['title'])?></td>
                  <td><?=nl2br(h($t['description']))?></td>
                  <td><?=badge($t['status'])?></td>
                  <td>
                    <?php if ($t['file_path']): ?>
                      <a class="btn" href="<?=h($t['file_path'])?>" target="_blank">View File</a>
                    <?php else: ?>
                      <span class="small">No file</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>

        <h3>Final Submission</h3>
        <?php if (!$finalRow): ?>
          <div class="small">No final submission from this group yet.</div>
        <?php else: ?>
          <div class="card" style="background:#f8fafc;">
            <div>Status: <?=badge($finalRow['status'])?></div>
            <div style="margin:8px 0;">
              <?php if (!empty($finalRow['file_path'])): ?>
                <a class="btn" href="<?=h($finalRow['file_path'])?>" target="_blank">View Final</a>
              <?php endif; ?>
            </div>
            <?php if ($finalRow['status']==='submitted'): ?>
              <form method="post" class="toolbar">
                <input type="hidden" name="final_id" value="<?=$finalRow['id']?>">
                <button class="btn success" name="action" value="approve_final" type="submit">Approve</button>
                <button class="btn danger"  name="action" value="reject_final"  type="submit">Reject</button>
              </form>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="small" style="margin-top:24px;">
    Tip: Color legend — 🔴 pending/rejected, 🟡 submitted (waiting), 🟢 accepted/completed/approved.
  </div>
</body>
</html>