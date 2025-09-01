<?php
// join_course.php — student enters course code to "join" (i.e., claim CSV enrollment)
session_start();
require 'db.php';
require 'student_utils.php';

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request.';
    header('Location: files.php');
    exit();
}

$course_code = strtoupper(trim($_POST['course_code'] ?? ''));

if ($course_code === '') {
    $_SESSION['error'] = 'Please enter a course code.';
    header('Location: files.php');
    exit();
}

// Find course by code
$stmt = $conn->prepare("SELECT id, name, code FROM courses WHERE code = ? LIMIT 1");
$stmt->bind_param('s', $course_code);
$stmt->execute();
$course = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$course) {
    $_SESSION['error'] = 'Course not found. Check the code.';
    header('Location: files.php');
    exit();
}

$course_id = (int)$course['id'];

// Determine student's university id (from users.university_id or email "local part")
$student_uid = getStudentUniversityId($conn, $user_id);

if (!$student_uid) {
    $_SESSION['error'] = 'Your account email/university id is missing or invalid.';
    header('Location: files.php');
    exit();
}

// Check CSV enrollment: must exist in course_enrollments by student_university_id
$chk = $conn->prepare("SELECT id, user_id FROM course_enrollments WHERE course_id = ? AND student_university_id = ? LIMIT 1");
$chk->bind_param('is', $course_id, $student_uid);
$chk->execute();
$enr = $chk->get_result()->fetch_assoc();
$chk->close();

if (!$enr) {
    $_SESSION['error'] = "You are not listed for this course (ID: {$student_uid}). Contact your professor.";
    header('Location: files.php');
    exit();
}

// If not yet claimed, attach user_id (so we know which account "joined")
if (empty($enr['user_id'])) {
    $upd = $conn->prepare("UPDATE course_enrollments SET user_id = ?, updated_at = NOW() WHERE id = ?");
    $eid = (int)$enr['id'];
    $upd->bind_param('ii', $user_id, $eid);
    $upd->execute();
    $upd->close();
}

$_SESSION['success'] = "Joined course {$course['name']} ({$course['code']}).";
header("Location: student_course_dashboard.php?id={$course_id}");
exit();
