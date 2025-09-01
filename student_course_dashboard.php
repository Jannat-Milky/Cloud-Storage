<?php
// student_course_dashboard.php — shows a student's status in a course: group, members, leader.
session_start();
require 'db.php';
require 'student_utils.php';

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($course_id <= 0) {
    header('Location: files.php');
    exit();
}

$student_uid = getStudentUniversityId($conn, $user_id);
if (!$student_uid) {
    $_SESSION['error'] = 'Could not detect your university id.';
    header('Location: files.php');
    exit();
}

// Course info
$c = $conn->prepare("SELECT id, name, code, professor_id, created_at FROM courses WHERE id = ? LIMIT 1");
$c->bind_param('i', $course_id);
$c->execute();
$course = $c->get_result()->fetch_assoc();
$c->close();
if (!$course) {
    $_SESSION['error'] = 'Course not found.';
    header('Location: files.php');
    exit();
}

// Ensure student exists in course_enrollments
$en = $conn->prepare("SELECT id, student_cgpa, user_id FROM course_enrollments WHERE course_id = ? AND student_university_id = ? LIMIT 1");
$en->bind_param('is', $course_id, $student_uid);
$en->execute();
$enrollment = $en->get_result()->fetch_assoc();
$en->close();

if (!$enrollment) {
    $_SESSION['error'] = "You are not enrolled in this course (ID: {$student_uid}).";
    header('Location: files.php');
    exit();
}

// Find student's group (if any)
$g = $conn->prepare("
    SELECT g.id, g.name, g.leader_student_id
    FROM group_members gm
    JOIN `groups` g ON g.id = gm.group_id
    WHERE g.course_id = ? AND gm.student_university_id = ?
    LIMIT 1
");
$g->bind_param('is', $course_id, $student_uid);
$g->execute();
$group = $g->get_result()->fetch_assoc();
$g->close();

$group_members = [];
if ($group) {
    $mg = $conn->prepare("SELECT student_university_id FROM group_members WHERE group_id = ? ORDER BY student_university_id ASC");
    $gid = (int)$group['id'];
    $mg->bind_param('i', $gid);
    $mg->execute();
    $rs = $mg->get_result();
    while ($row = $rs->fetch_assoc()) {
        $group_members[] = $row['student_university_id'];
    }
    $mg->close();
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($course['name']) ?> | Educollab</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
            --danger: #ef4444;
            --warning: #f59e0b;
            --border-radius: 16px;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 5px 10px -5px rgba(0, 0, 0, 0.04);
            --hover-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7ff 0%, #f0fdfa 100%);
            color: var(--educollab-dark);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--educollab-primary);
            text-decoration: none;
            font-weight: 500;
            margin-bottom: 1.5rem;
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius);
            transition: var(--transition);
        }

        .back-link:hover {
            background-color: var(--educollab-light);
        }

        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 2rem;
            margin-bottom: 1.5rem;
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: var(--hover-shadow);
        }

        .course-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 1.5rem;
        }

        .course-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--educollab-dark);
            margin-bottom: 0.5rem;
        }

        .course-meta {
            color: #6b7280;
            font-size: 1rem;
        }

        .course-code {
            display: inline-block;
            background: #eef2ff;
            color: #3730a3;
            padding: 0.5rem 1rem;
            border-radius: 999px;
            font-weight: 500;
            margin-top: 0.5rem;
        }

        .student-info {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }

        .info-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--educollab-light);
            color: var(--educollab-primary);
            padding: 0.75rem 1.25rem;
            border-radius: 999px;
            font-weight: 500;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--educollab-dark);
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: var(--educollab-primary);
        }

        .group-info {
            margin-bottom: 1.5rem;
        }

        .group-name {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--educollab-dark);
            margin-bottom: 0.75rem;
        }

        .leader-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.25rem;
            padding: 1rem;
            background: #f0fdf4;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--success);
        }

        .leader-label {
            font-weight: 600;
            color: var(--educollab-dark);
        }

        .leader-id {
            font-weight: 700;
            color: var(--success);
        }

        .members-list {
            list-style: none;
            margin-top: 1rem;
        }

        .member-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid #f3f4f6;
            transition: var(--transition);
        }

        .member-item:hover {
            background-color: #f9fafb;
            border-radius: 8px;
        }

        .member-item:last-child {
            border-bottom: none;
        }

        .leader-badge {
            background: #f0fdf4;
            color: var(--success);
            padding: 0.35rem 0.75rem;
            border-radius: 999px;
            font-size: 0.875rem;
            font-weight: 600;
            border: 1px solid #bbf7d0;
        }

        .no-group {
            padding: 2rem;
            text-align: center;
            background: #fffbeb;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--warning);
        }

        .no-group i {
            font-size: 2.5rem;
            color: var(--warning);
            margin-bottom: 1rem;
        }

        .no-group h3 {
            font-size: 1.25rem;
            color: #92400e;
            margin-bottom: 0.5rem;
        }

        .no-group p {
            color: #b45309;
        }

        .tools-section {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .tool-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 1.5rem;
            background: var(--educollab-primary);
            color: white;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }

        .tool-btn:hover {
            background: var(--educollab-secondary);
            transform: translateY(-2px);
            box-shadow: var(--hover-shadow);
        }

        .alert {
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background-color: #f0fdf4;
            color: #166534;
            border-left: 4px solid #22c55e;
        }

        .alert-error {
            background-color: #fef2f2;
            color: #b91c1c;
            border-left: 4px solid #ef4444;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .course-header {
                flex-direction: column;
            }
            
            .student-info {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .tools-section {
                flex-direction: column;
            }
            
            .tool-btn {
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <a href="files.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Home Page
        </a>

        <?php if (!empty($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="course-header">
                <div>
                    <h1 class="course-title"><?= htmlspecialchars($course['name']) ?></h1>
                    <div class="course-meta">
                        Created: <?= htmlspecialchars($course['created_at']) ?>
                        <span class="course-code"><?= htmlspecialchars($course['code']) ?></span>
                    </div>
                </div>
            </div>
            
            <div class="student-info">
                <span class="info-pill">
                    <i class="fas fa-id-card"></i> Your ID: <?= htmlspecialchars($student_uid) ?>
                </span>
                
                <?php if (!is_null($enrollment['student_cgpa'])): ?>
                    <span class="info-pill">
                        <i class="fas fa-chart-line"></i> Your CGPA: <?= htmlspecialchars(number_format((float)$enrollment['student_cgpa'], 2)) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <h2 class="section-title">
                <i class="fas fa-users"></i> My Group
            </h2>
            
            <?php if ($group): ?>
                <div class="group-info">
                    <h3 class="group-name"><?= htmlspecialchars($group['name']) ?></h3>
                    
                    <?php if (!empty($group['leader_student_id'])): ?>
                        <div class="leader-info">
                            <i class="fas fa-crown"></i>
                            <span class="leader-label">Group Leader:</span>
                            <span class="leader-id"><?= htmlspecialchars($group['leader_student_id']) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($group_members)): ?>
                        <h4 class="section-title" style="font-size: 1.25rem;">
                            <i class="fas fa-user-friends"></i> Group Members
                        </h4>
                        <ul class="members-list">
                            <?php foreach ($group_members as $sid): ?>
                                <li class="member-item">
                                    <span><?= htmlspecialchars($sid) ?></span>
                                    <?php if ($sid === $group['leader_student_id']): ?>
                                        <span class="leader-badge">Leader</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p style="color: #6b7280; text-align: center; padding: 1.5rem;">
                            No members listed yet.
                        </p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="no-group">
                    <i class="fas fa-users-slash"></i>
                    <h3>Not Assigned to a Group Yet</h3>
                    <p>Please check back later for group assignment updates.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2 class="section-title">
                <i class="fas fa-tools"></i> Course Tools
            </h2>
            <div class="tools-section">
                <a class="tool-btn" href="taskboard.php?course_id=<?= (int)$course_id ?>">
                    <i class="fas fa-tasks"></i> Task Board
                </a>
            </div>
        </div>
    </div>

    <script>
        // Add subtle animations
        document.addEventListener('DOMContentLoaded', function() {
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
        });
    </script>
</body>

</html>