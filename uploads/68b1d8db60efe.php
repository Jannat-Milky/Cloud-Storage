<?php
// taskboard.php — Group task board with per-member assignments, approvals, uploads, and leader review
// Drop this file into your project root next to files like files.php, db.php, etc.
// Uses existing session auth, mysqli $conn (db.php), and student_utils.php for student university id.
//
// Routes already exist in your project that link here:
// - student_course_dashboard.php?id=<course_id> → "Taskboard" link
// - course_dashboard.php?id=<course_id> → "Taskboard" link for professors
//
// Tables created automatically if missing:
//   - group_tasks
//   - task_work
//   - final_submissions
//
// Storage:
//   - Per-task uploads go to /uploads/tasks
//   - Final group submissions go to /uploads/final
//
// Color/status mapping shown in UI:
//   - Pending Approval (assignee must accept) → RED
//   - Accepted / In progress → GREEN
//   - Submitted / waiting leader review → YELLOW
//   - Approved (complete) → GREEN
//   - Changes requested → RED
//
session_start();
require 'db.php';           // provides $conn (mysqli)
require 'student_utils.php';// for getStudentUniversityId($conn, $user_id)

// Basic guards
if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
  header('Location: login.php');
  exit();
}

$user_id = (int)$_SESSION['user_id'];
$role    = $_SESSION['role']; // 'student' | 'professor'

// Course id must be provided (?course_id=)
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
if ($course_id <= 0) {
  http_response_code(400);
  echo "<p style='font-family:system-ui;padding:20px'>Missing or invalid course_id. Open this page via the Course “Taskboard” link.</p>";
  exit();
}

// ---------- Helpers ----------
function ensure_tables(mysqli $conn) {
  $conn->query("CREATE TABLE IF NOT EXISTS group_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    group_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    due_at DATETIME NULL,
    created_by_student_id VARCHAR(64) NOT NULL,
    assigned_student_id VARCHAR(64) NOT NULL,
    assignment_status ENUM('pending','accepted','declined') NOT NULL DEFAULT 'pending',
    accepted_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

  $conn->query("CREATE TABLE IF NOT EXISTS task_work (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    uploaded_by_student_id VARCHAR(64) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    mark_done_at DATETIME NULL,
    leader_review ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    leader_review_at DATETIME NULL,
    leader_reviewer_student_id VARCHAR(64) NULL,
    comment TEXT NULL,
    CONSTRAINT fk_task_work_task FOREIGN KEY (task_id) REFERENCES group_tasks(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

  $conn->query("CREATE TABLE IF NOT EXISTS final_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    group_id INT NOT NULL,
    uploaded_by_student_id VARCHAR(64) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
}

function get_student_uid(mysqli $conn, int $user_id): ?string {
  $uid = getStudentUniversityId($conn, $user_id);
  return $uid ?: null;
}

function get_group_for_student(mysqli $conn, int $course_id, string $student_uid): ?array {
  $sql = "SELECT g.* 
          FROM groups g 
          JOIN group_members gm ON gm.group_id = g.id 
          WHERE g.course_id = ? AND gm.student_university_id = ?
          LIMIT 1";
  $stmt = $conn->prepare($sql);
  $stmt->bind_param('is', $course_id, $student_uid);
  $stmt->execute();
  $res = $stmt->get_result();
  return $res ? $res->fetch_assoc() : null;
}

function list_group_members(mysqli $conn, int $group_id): array {
  $rows = [];
  $stmt = $conn->prepare("SELECT student_university_id FROM group_members WHERE group_id = ? ORDER BY student_university_id");
  $stmt->bind_param('i', $group_id);
  $stmt->execute();
  $r = $stmt->get_result();
  while ($r && $row = $r->fetch_assoc()) $rows[] = $row['student_university_id'];
  return $rows;
}

function get_course(mysqli $conn, int $course_id): ?array {
  $stmt = $conn->prepare("SELECT id, name, code, created_at FROM courses WHERE id = ? LIMIT 1");
  $stmt->bind_param('i', $course_id);
  $stmt->execute();
  $res = $stmt->get_result();
  return $res ? $res->fetch_assoc() : null;
}

function is_leader(array $group, ?string $student_uid): bool {
  return $student_uid && isset($group['leader_student_id']) && $group['leader_student_id'] === $student_uid;
}

function get_groups_for_course(mysqli $conn, int $course_id): array {
  $rows = [];
  $stmt = $conn->prepare("SELECT * FROM groups WHERE course_id = ? ORDER BY id");
  $stmt->bind_param('i', $course_id);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($res && $row = $res->fetch_assoc()) $rows[] = $row;
  return $rows;
}

function status_badge(string $assignment_status, ?array $work): array {
  // Returns [label, class]
  // Colors via CSS classes below
  if ($assignment_status === 'pending') return ['Pending approval', 'badge red'];

  if ($assignment_status === 'declined') return ['Declined', 'badge red'];

  // accepted
  if (!$work) return ['Assigned', 'badge green']; // assignee approved, not uploaded yet

  // Uploaded work exists
  if ($work['mark_done_at'] === null) return ['Uploaded (not marked done)', 'badge red']; // must click mark done

  // Marked done → waiting leader review
  if ($work['leader_review'] === 'pending') return ['Submitted (awaiting leader review)', 'badge yellow'];
  if ($work['leader_review'] === 'approved') return ['Approved ✓', 'badge green'];
  if ($work['leader_review'] === 'rejected') return ['Changes requested', 'badge red'];

  return ['Unknown', 'badge gray'];
}

function first_work_for_task(mysqli $conn, int $task_id): ?array {
  $stmt = $conn->prepare("SELECT * FROM task_work WHERE task_id = ? ORDER BY id DESC LIMIT 1");
  $stmt->bind_param('i', $task_id);
  $stmt->execute();
  $res = $stmt->get_result();
  return $res ? $res->fetch_assoc() : null;
}

// Ensure tables exist
ensure_tables($conn);

// Ensure upload directories exist
@mkdir(__DIR__ . '/uploads/tasks', 0777, true);
@mkdir(__DIR__ . '/uploads/final', 0777, true);

// Load course (for header)
$course = get_course($conn, $course_id);

// Who am I?
$student_uid = ($role === 'student') ? get_student_uid($conn, $user_id) : null;

// Determine group context
$group = null;
$view_group_id = null;

if ($role === 'student') {
  if (!$student_uid) {
    echo "<p style='font-family:system-ui;padding:20px'>Your account is missing a valid University ID. Please ensure your email/ID is set.</p>";
    exit();
  }
  $group = get_group_for_student($conn, $course_id, $student_uid);
  if ($group) $view_group_id = (int)$group['id'];
} else {
  // professor: can select any group in course
  $view_group_id = isset($_GET['group_id']) ? (int)$_GET['group_id'] : 0;
  if ($view_group_id <= 0) {
    // try first group
    $all = get_groups_for_course($conn, $course_id);
    if ($all) $view_group_id = (int)$all[0]['id'];
  }
  if ($view_group_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM groups WHERE id = ? AND course_id = ?");
    $stmt->bind_param('ii', $view_group_id, $course_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $group = $res ? $res->fetch_assoc() : null;
  }
}

// Flash messaging helper
function set_flash(string $msg) {
  $_SESSION['taskboard_flash'] = $msg;
}
$flash = $_SESSION['taskboard_flash'] ?? '';
unset($_SESSION['taskboard_flash']);

// ---------- Actions (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // CSRF-lite: allow only logged-in + same origin behaviors (basic project style)

  if ($action === 'create_task' && $role === 'student') {
    // Only group leader can create tasks for their group
    $title = trim($_POST['title'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    $due   = trim($_POST['due_at'] ?? '');
    $assign_to = trim($_POST['assigned_student_id'] ?? '');
    $gid = (int)($_POST['group_id'] ?? 0);

    if ($gid <= 0 || !$title || !$assign_to) {
      set_flash('Please fill all required fields.');
      header("Location: taskboard.php?course_id={$course_id}");
      exit();
    }

    // verify group + leader rights
    if (!$group || (int)$group['id'] !== $gid || !is_leader($group, $student_uid)) {
      set_flash('Only the team leader can create tasks for this group.');
      header("Location: taskboard.php?course_id={$course_id}");
      exit();
    }

    // insert
    $stmt = $conn->prepare("INSERT INTO group_tasks (course_id, group_id, title, description, due_at, created_by_student_id, assigned_student_id) VALUES (?,?,?,?,?,?,?)");
    $due_at = ($due !== '') ? $due : null;
    $stmt->bind_param('iisssss', $course_id, $gid, $title, $desc, $due_at, $student_uid, $assign_to);
    $stmt->execute();
    set_flash('Task created and assigned. Assignee must approve.');
    header("Location: taskboard.php?course_id={$course_id}");
    exit();
  }

  if ($action === 'approve_assignment' && $role === 'student') {
    $task_id = (int)($_POST['task_id'] ?? 0);
    // ensure this task is assigned to me
    $stmt = $conn->prepare("SELECT t.*, g.leader_student_id FROM group_tasks t JOIN groups g ON g.id=t.group_id WHERE t.id=? AND t.course_id=?");
    $stmt->bind_param('ii', $task_id, $course_id);
    $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc();
    if ($t && $t['assigned_student_id'] === $student_uid && $t['assignment_status'] === 'pending') {
      $stmt2 = $conn->prepare("UPDATE group_tasks SET assignment_status='accepted', accepted_at=NOW() WHERE id=?");
      $stmt2->bind_param('i', $task_id);
      $stmt2->execute();
      set_flash('Assignment approved. Status is now Assigned (green).');
    }
    header("Location: taskboard.php?course_id={$course_id}");
    exit();
  }

  if ($action === 'decline_assignment' && $role === 'student') {
    $task_id = (int)($_POST['task_id'] ?? 0);
    $stmt = $conn->prepare("SELECT * FROM group_tasks WHERE id=? AND course_id=?");
    $stmt->bind_param('ii', $task_id, $course_id);
    $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc();
    if ($t && $t['assigned_student_id'] === $student_uid && $t['assignment_status'] === 'pending') {
      $stmt2 = $conn->prepare("UPDATE group_tasks SET assignment_status='declined' WHERE id=?");
      $stmt2->bind_param('i', $task_id);
      $stmt2->execute();
      set_flash('Assignment declined.');
    }
    header("Location: taskboard.php?course_id={$course_id}");
    exit();
  }

  if ($action === 'upload_work' && $role === 'student') {
    $task_id = (int)($_POST['task_id'] ?? 0);

    // verify task is mine and accepted
    $stmt = $conn->prepare("SELECT * FROM group_tasks WHERE id=? AND course_id=?");
    $stmt->bind_param('ii', $task_id, $course_id);
    $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc();
    if (!$t || $t['assigned_student_id'] !== $student_uid || $t['assignment_status'] !== 'accepted') {
      set_flash('Cannot upload for this task.');
      header("Location: taskboard.php?course_id={$course_id}");
      exit();
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
      set_flash('Please select a valid file.');
      header("Location: taskboard.php?course_id={$course_id}");
      exit();
    }
    $original = $_FILES['file']['name'];
    $tmp = $_FILES['file']['tmp_name'];
    $ext = pathinfo($original, PATHINFO_EXTENSION);
    $stored = uniqid('task_', true) . ($ext ? "." . $ext : '');
    $dest = __DIR__ . '/uploads/tasks/' . $stored;

    if (!move_uploaded_file($tmp, $dest)) {
      set_flash('Failed to save the uploaded file.');
      header("Location: taskboard.php?course_id={$course_id}");
      exit();
    }

    // Insert/replace work (one active row is fine; we keep latest by id desc)
    $stmt = $conn->prepare("INSERT INTO task_work (task_id, uploaded_by_student_id, stored_name, original_name) VALUES (?,?,?,?)");
    $stmt->bind_param('isss', $task_id, $student_uid, $stored, $original);
    $stmt->execute();
    set_flash('File uploaded. Now click “Mark as Done” when ready.');
    header("Location: taskboard.php?course_id={$course_id}");
    exit();
  }

  if ($action === 'mark_done' && $role === 'student') {
    $task_id = (int)($_POST['task_id'] ?? 0);
    // verify ownership
    $stmt = $conn->prepare("SELECT * FROM group_tasks WHERE id=? AND course_id=?");
    $stmt->bind_param('ii', $task_id, $course_id);
    $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc();
    if ($t && $t['assigned_student_id'] === $student_uid && $t['assignment_status'] === 'accepted') {
      // must have an uploaded file
      $work = first_work_for_task($conn, $task_id);
      if ($work) {
        $stmt2 = $conn->prepare("UPDATE task_work SET mark_done_at = NOW(), leader_review='pending' WHERE id=?");
        $stmt2->bind_param('i', $work['id']);
        $stmt2->execute();
        set_flash('Marked as done. Waiting for leader approval (yellow).');
      } else {
        set_flash('Upload your work file before marking as done.');
      }
    }
    header("Location: taskboard.php?course_id={$course_id}");
    exit();
  }

  if ($action === 'leader_review' && $role === 'student') {
    // Only leader can approve/reject completed work
    $task_id = (int)($_POST['task_id'] ?? 0);
    $decision = $_POST['decision'] ?? 'pending'; // 'approved'|'rejected'
    $comment  = trim($_POST['comment'] ?? '');

    if (!$group || !is_leader($group, $student_uid)) {
      set_flash('Only the team leader can review submissions.');
      header("Location: taskboard.php?course_id={$course_id}");
      exit();
    }

    // verify task belongs to this group
    $stmt = $conn->prepare("SELECT * FROM group_tasks WHERE id=? AND course_id=? AND group_id=?");
    $gid = (int)$group['id'];
    $stmt->bind_param('iii', $task_id, $course_id, $gid);
    $stmt->execute();
    $t = $stmt->get_result()->fetch_assoc();
    if (!$t) {
      set_flash('Task not found for this group.');
      header("Location: taskboard.php?course_id={$course_id}");
      exit();
    }

    $work = first_work_for_task($conn, $task_id);
    if (!$work || $work['mark_done_at'] === null) {
      set_flash('Submission is not yet marked done.');
      header("Location: taskboard.php?course_id={$course_id}");
      exit();
    }

    $decision = ($decision === 'approved') ? 'approved' : 'rejected';
    $stmt2 = $conn->prepare("UPDATE task_work SET leader_review=?, leader_review_at=NOW(), leader_reviewer_student_id=?, comment=? WHERE id=?");
    $stmt2->bind_param('sssi', $decision, $student_uid, $comment, $work['id']);
    $stmt2->execute();
    set_flash($decision === 'approved' ? 'Work approved (green).' : 'Changes requested (red).');
    header("Location: taskboard.php?course_id={$course_id}");
    exit();
  }

  if ($action === 'final_submit' && $role === 'student') {
    // Only leader can submit final group file
    if (!$group || !is_leader($group, $student_uid)) {
      set_flash('Only the team leader can submit the final group file.');
      header("Location: taskboard.php?course_id={$course_id}");
      exit();
    }
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
      set_flash('Please choose a valid final file.');
      header("Location: taskboard.php?course_id={$course_id}");
      exit();
    }
    $original = $_FILES['file']['name'];
    $tmp = $_FILES['file']['tmp_name'];
    $ext = pathinfo($original, PATHINFO_EXTENSION);
    $stored = uniqid('final_', true) . ($ext ? "." . $ext : '');
    $dest = __DIR__ . '/uploads/final/' . $stored;
    if (!move_uploaded_file($tmp, $dest)) {
      set_flash('Failed to save the final file.');
      header("Location: taskboard.php?course_id={$course_id}");
      exit();
    }
    $gid = (int)$group['id'];
    $stmt = $conn->prepare("INSERT INTO final_submissions (course_id, group_id, uploaded_by_student_id, stored_name, original_name) VALUES (?,?,?,?,?)");
    $stmt->bind_param('iisss', $course_id, $gid, $student_uid, $stored, $original);
    $stmt->execute();
    set_flash('Final group file submitted.');
    header("Location: taskboard.php?course_id={$course_id}");
    exit();
  }
}

// ---------- Data for rendering ----------
$group_members = $group ? list_group_members($conn, (int)$group['id']) : [];
$tasks = [];
if ($group) {
  $gid = (int)$group['id'];
  $stmt = $conn->prepare("SELECT * FROM group_tasks WHERE course_id=? AND group_id=? ORDER BY created_at DESC");
  $stmt->bind_param('ii', $course_id, $gid);
  $stmt->execute();
  $result = $stmt->get_result();
  while ($result && $row = $result->fetch_assoc()) {
    $row['_work'] = first_work_for_task($conn, (int)$row['id']);
    $tasks[] = $row;
  }
}

// For professor view: groups dropdown
$groups_for_dropdown = ($role === 'professor') ? get_groups_for_course($conn, $course_id) : [];

// ---------- Render ----------
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Taskboard</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root {
      --primary:#4361ee; --secondary:#3f37c9;
      --green:#10b981; --red:#ef4444; --yellow:#f59e0b; --muted:#6b7280; --border:#e5e7eb;
    }
    *{box-sizing:border-box}
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif; background:#f6f7fb; margin:0; color:#111827}
    .container{max-width:1100px;margin:24px auto;padding:0 16px}
    header .title{display:flex;align-items:center;gap:10px}
    .card{background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 4px 12px rgba(0,0,0,.04);padding:16px;margin-bottom:16px}
    h1{margin:0;font-size:22px}
    h2{margin:0 0 8px;font-size:18px}
    .muted{color:var(--muted)}
    .row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
    .badge{display:inline-block;padding:4px 10px;border-radius:999px;font-weight:700;font-size:12px;border:1px solid transparent}
    .badge.green{background:#ecfdf5;color:#065f46;border-color:#a7f3d0}
    .badge.red{background:#fee2e2;color:#7f1d1d;border-color:#fecaca}
    .badge.yellow{background:#fff7ed;color:#92400e;border-color:#fed7aa}
    .badge.gray{background:#f3f4f6;color:#374151;border-color:#e5e7eb}
    .btn{background:#3f37c9;color:#fff;border:none;border-radius:10px;padding:8px 12px;text-decoration:none;display:inline-flex;align-items:center;gap:8px;cursor:pointer}
    .btn.outline{background:#f3f4f6;color:#111827}
    .btn.small{padding:6px 10px;font-size:13px;border-radius:8px}
    .btn.danger{background:#ef4444}
    .btn.success{background:#10b981}
    .btn.warn{background:#f59e0b}
    input,select,textarea{padding:10px;border:1px solid var(--border);border-radius:10px;width:100%}
    textarea{resize:vertical;min-height:80px}
    .grid{display:grid;grid-template-columns:repeat(12,1fr);gap:12px}
    .col-4{grid-column:span 4}
    .col-8{grid-column:span 8}
    .task{border:1px solid var(--border);border-radius:12px;padding:12px;margin-bottom:12px}
    .task h3{margin:0 0 6px}
    .help{font-size:12px;color:#6b7280}
    .file{display:inline-flex;align-items:center;gap:8px;padding:6px 10px;border:1px solid var(--border);border-radius:8px;background:#f9fafb}
    .flash{padding:10px;border-radius:10px;margin-bottom:12px}
    .flash.ok{background:#ecfdf5;color:#065f46;border:1px solid #a7f3d0}
    .flash.warn{background:#fff7ed;color:#9a3412;border:1px solid #fed7aa}
    .file-link{color:#1f2937;text-decoration:none}
    .pill{display:inline-block;background:#eef2ff;color:#3730a3;border-radius:999px;padding:4px 10px;font-weight:600}
    .right{margin-left:auto}
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="row" style="justify-content:space-between">
        <div class="title">
          <i class="fas fa-clipboard-list"></i>
          <div>
            <h1>Taskboard · <?= htmlspecialchars($course['name'] ?? 'Course') ?></h1>
            <?php if ($course): ?><div class="muted">Code: <code><?= htmlspecialchars($course['code']) ?></code></div><?php endif; ?>
          </div>
        </div>
        <div class="row">
          <a class="btn outline" href="<?= ($role==='student'?'student_course_dashboard.php':'course_dashboard.php') ?>?id=<?= (int)$course_id ?>"><i class="fas fa-arrow-left"></i> Back</a>
        </div>
      </div>
      <?php if ($flash): ?>
        <div class="flash <?= (stripos($flash,'fail')!==false || stripos($flash,'error')!==false || stripos($flash,'Cannot')!==false) ? 'warn' : 'ok' ?>">
          <?= htmlspecialchars($flash) ?>
        </div>
      <?php endif; ?>
      <?php if (!$group): ?>
        <p class="muted">No group context for this view<?php if ($role==='professor'):?>. Choose a group below.<?php endif; ?>.</p>
      <?php endif; ?>
      <div class="row">
        <?php if ($group): ?>
          <span class="pill">Group #<?= (int)$group['id'] ?></span>
          <?php if (!empty($group['leader_student_id'])): ?>
            <span class="pill" title="Leader university id">Leader: <code><?= htmlspecialchars($group['leader_student_id']) ?></code></span>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($role==='professor' && $groups_for_dropdown): ?>
          <form method="get" class="row right">
            <input type="hidden" name="course_id" value="<?= (int)$course_id ?>">
            <select name="group_id" onchange="this.form.submit()">
              <?php foreach ($groups_for_dropdown as $g): ?>
                <option value="<?= (int)$g['id'] ?>" <?= ((int)$g['id'] === (int)$view_group_id) ? 'selected':'' ?>>Group #<?= (int)$g['id'] ?> (Leader: <?= htmlspecialchars($g['leader_student_id']) ?>)</option>
              <?php endforeach; ?>
            </select>
            <noscript><button class="btn small" type="submit">Open</button></noscript>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($group): ?>
      <div class="grid">
        <!-- Left: Tasks -->
        <div class="col-8">
          <div class="card">
            <h2><i class="fas fa-list-check"></i> Group Tasks</h2>
            <?php if (!$tasks): ?>
              <p class="muted" style="margin:8px 0 0">No tasks yet.</p>
            <?php endif; ?>

            <?php foreach ($tasks as $t): 
              [$label,$cls] = status_badge($t['assignment_status'], $t['_work']);
              $mine = ($role==='student' && $student_uid && $t['assigned_student_id'] === $student_uid);
              $work = $t['_work'];
              $dueTxt = $t['due_at'] ? date('M j, Y g:i a', strtotime($t['due_at'])) : '—';
            ?>
              <div class="task">
                <div class="row" style="justify-content:space-between">
                  <div class="row">
                    <h3 style="margin:0"><?= htmlspecialchars($t['title']) ?></h3>
                    <span class="<?= $cls ?>"><?= htmlspecialchars($label) ?></span>
                  </div>
                  <div class="muted">Assigned to: <code><?= htmlspecialchars($t['assigned_student_id']) ?></code></div>
                </div>
                <?php if (!empty($t['description'])): ?>
                  <p style="margin:6px 0 0"><?= nl2br(htmlspecialchars($t['description'])) ?></p>
                <?php endif; ?>
                <div class="row" style="margin-top:8px;gap:16px">
                  <span class="help"><i class="far fa-calendar"></i> Due: <?= htmlspecialchars($dueTxt) ?></span>
                  <span class="help"><i class="far fa-clock"></i> Created: <?= htmlspecialchars(date('M j, Y g:i a', strtotime($t['created_at']))) ?></span>
                </div>

                <!-- Work display -->
                <?php if ($work): ?>
                  <div style="margin-top:10px">
                    <div class="file">
                      <i class="far fa-file"></i>
                      <a class="file-link" href="uploads/tasks/<?= htmlspecialchars($work['stored_name']) ?>" target="_blank">
                        <?= htmlspecialchars($work['original_name']) ?>
                      </a>
                      <span class="muted">· uploaded <?= htmlspecialchars(date('M j, Y g:i a', strtotime($work['uploaded_at']))) ?></span>
                    </div>
                    <?php if ($work['leader_review'] !== 'pending'): ?>
                      <div class="help" style="margin-top:6px">
                        Leader review: <strong><?= htmlspecialchars(ucfirst($work['leader_review'])) ?></strong>
                        <?php if (!empty($work['comment'])): ?> · Note: <?= htmlspecialchars($work['comment']) ?><?php endif; ?>
                      </div>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <!-- Actions for assignee -->
                <?php if ($role==='student' && $mine): ?>
                  <div style="margin-top:10px">
                    <?php if ($t['assignment_status']==='pending'): ?>
                      <form method="post" class="row" style="gap:8px">
                        <input type="hidden" name="action" value="approve_assignment">
                        <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                        <button class="btn success small" type="submit"><i class="fas fa-check"></i> Approve</button>
                      </form>
                      <form method="post" style="display:inline">
                        <input type="hidden" name="action" value="decline_assignment">
                        <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                        <button class="btn danger small" type="submit"><i class="fas fa-times"></i> Decline</button>
                      </form>
                    <?php elseif ($t['assignment_status']==='accepted'): ?>
                      <?php if (!$work): ?>
                        <!-- Upload form -->
                        <form method="post" enctype="multipart/form-data" class="row" style="gap:8px">
                          <input type="hidden" name="action" value="upload_work">
                          <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                          <input type="file" name="file" required>
                          <button class="btn small" type="submit"><i class="fas fa-upload"></i> Upload</button>
                          <span class="help">After uploading, click “Mark as Done”.</span>
                        </form>
                      <?php else: ?>
                        <?php if ($work['mark_done_at'] === null): ?>
                          <form method="post" class="row" style="gap:8px;margin-top:6px">
                            <input type="hidden" name="action" value="mark_done">
                            <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                            <button class="btn warn small" type="submit"><i class="fas fa-flag-checkered"></i> Mark as Done</button>
                            <span class="help">Shows <strong>yellow</strong> until leader approves.</span>
                          </form>
                        <?php endif; ?>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>

                <!-- Actions for leader -->
                <?php if ($role==='student' && $group && is_leader($group, $student_uid)): ?>
                  <?php if ($work && $work['mark_done_at'] !== null && $work['leader_review'] === 'pending'): ?>
                    <div style="margin-top:10px">
                      <form method="post" class="row" style="gap:8px">
                        <input type="hidden" name="action" value="leader_review">
                        <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                        <input type="text" name="comment" placeholder="Optional note to assignee" style="max-width:320px">
                        <button class="btn success small" name="decision" value="approved" type="submit"><i class="fas fa-check-double"></i> Approve</button>
                        <button class="btn danger small" name="decision" value="rejected" type="submit"><i class="fas fa-undo"></i> Request changes</button>
                      </form>
                    </div>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Right: Create task (leader) + Final submission + Members -->
        <div class="col-4">
          <?php if ($role==='student' && $group && is_leader($group, $student_uid)): ?>
            <div class="card">
              <h2><i class="fas fa-plus-circle"></i> Create Task</h2>
              <form method="post">
                <input type="hidden" name="action" value="create_task">
                <input type="hidden" name="group_id" value="<?= (int)$group['id'] ?>">
                <div style="margin-top:8px">
                  <label>Title<span style="color:#ef4444"> *</span></label>
                  <input type="text" name="title" required>
                </div>
                <div style="margin-top:8px">
                  <label>Description</label>
                  <textarea name="description" placeholder="What needs to be done?"></textarea>
                </div>
                <div style="margin-top:8px">
                  <label>Due (optional)</label>
                  <input type="datetime-local" name="due_at">
                </div>
                <div style="margin-top:8px">
                  <label>Assign to<span style="color:#ef4444"> *</span></label>
                  <select name="assigned_student_id" required>
                    <option value="">-- pick member --</option>
                    <?php foreach ($group_members as $sid): ?>
                      <option value="<?= htmlspecialchars($sid) ?>"><?= htmlspecialchars($sid) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div style="margin-top:10px">
                  <button class="btn" type="submit"><i class="fas fa-paper-plane"></i> Create & Assign</button>
                </div>
                <p class="help" style="margin-top:6px">
                  New task appears as <strong>red</strong> until the assignee approves, then it turns <strong>green</strong> (“Assigned”).
                </p>
              </form>
            </div>
          <?php endif; ?>

          <?php if ($group && $role==='student' && is_leader($group, $student_uid)): ?>
            <div class="card">
              <h2><i class="fas fa-file-upload"></i> Final Group Submission</h2>
              <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="final_submit">
                <input type="file" name="file" required>
                <div style="margin-top:10px">
                  <button class="btn" type="submit"><i class="fas fa-cloud-upload-alt"></i> Upload Final File</button>
                </div>
                <p class="help" style="margin-top:6px">This can be seen by the professor in “Teacher View”.</p>
              </form>
            </div>
          <?php endif; ?>

          <?php if ($group): ?>
            <div class="card">
              <h2><i class="fas fa-users"></i> Members</h2>
              <ul style="list-style:none;padding:0;margin:0">
                <?php foreach ($group_members as $sid): ?>
                  <li style="padding:8px 0;border-bottom:1px solid var(--border)">
                    <code><?= htmlspecialchars($sid) ?></code>
                    <?php if ($group['leader_student_id'] === $sid): ?>
                      <span class="badge green">Leader</span>
                    <?php endif; ?>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <?php if ($role==='professor'): ?>
            <div class="card">
              <h2><i class="fas fa-inbox"></i> Final Submissions (this group)</h2>
              <?php
                $rows = [];
                if ($group) {
                  $gid = (int)$group['id'];
                  $stmt = $conn->prepare("SELECT * FROM final_submissions WHERE course_id=? AND group_id=? ORDER BY uploaded_at DESC");
                  $stmt->bind_param('ii', $course_id, $gid);
                  $stmt->execute();
                  $rs = $stmt->get_result();
                  while ($rs && $row = $rs->fetch_assoc()) $rows[] = $row;
                }
              ?>
              <?php if (!$rows): ?>
                <p class="muted">No final submission uploaded yet.</p>
              <?php else: ?>
                <ul style="list-style:none;padding:0;margin:0">
                  <?php foreach ($rows as $f): ?>
                    <li style="padding:8px 0;border-bottom:1px solid var(--border)">
                      <a class="file-link" href="uploads/final/<?= htmlspecialchars($f['stored_name']) ?>" target="_blank">
                        <?= htmlspecialchars($f['original_name']) ?>
                      </a>
                      <div class="help">By <code><?= htmlspecialchars($f['uploaded_by_student_id']) ?></code> · <?= htmlspecialchars(date('M j, Y g:i a', strtotime($f['uploaded_at']))) ?></div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
