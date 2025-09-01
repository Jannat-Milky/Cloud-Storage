<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("Please <a href='login.php'>login</a> first.");
}

$user_id = $_SESSION['user_id'];
$current_folder = isset($_GET['folder']) ? (int)$_GET['folder'] : null;

// Get all folders for the dropdown
$folders = $conn->query("SELECT * FROM folders WHERE user_id = $user_id ORDER BY name");

// Get files for the current folder or root
if ($current_folder) {
    $result = $conn->query("SELECT * FROM files WHERE user_id = $user_id AND folder_id = $current_folder");
} else {
    $result = $conn->query("SELECT * FROM files WHERE user_id = $user_id AND folder_id IS NULL");
}

// Handle messages
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
<<<<<<< HEAD
require_once 'student_utils.php';
$student_uid_for_list = null;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    $student_uid_for_list = getStudentUniversityId($conn, (int)$_SESSION['user_id']);
}
=======
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
?>

<!DOCTYPE html>
<html lang="en">
<<<<<<< HEAD

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Files | Educollab</title>
=======
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Files | Cloud Storage</title>
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
<<<<<<< HEAD
            --educollab-primary: #4a6fa5;
            --educollab-secondary: #166088;
            --educollab-accent: #4cb5ae;
            --educollab-light: #e8f4f3;
            --educollab-dark: #2c3e50;
            --educollab-gradient: linear-gradient(135deg, #4a6fa5 0%, #166088 100%);
            --educollab-accent-gradient: linear-gradient(135deg, #4cb5ae 0%, #3da89c 100%);
            --light: #f8fafc;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --border-radius: 16px;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 5px 10px -5px rgba(0, 0, 0, 0.04);
            --hover-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --transition: all 0.3s ease;
        }

=======
            --primary: #4361ee;
            --primary-light: #4895ef;
            --secondary: #3f37c9;
            --dark: #1a1a2e;
            --light: #f8f9fa;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #560bad;
            --border-radius: 8px;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
<<<<<<< HEAD

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7ff 0%, #f0fdfa 100%);
            color: var(--educollab-dark);
            line-height: 1.6;
            min-height: 100vh;
        }

=======
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f7ff;
            color: var(--dark);
            line-height: 1.6;
        }
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
<<<<<<< HEAD

=======
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
<<<<<<< HEAD
            padding: 1.5rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            animation: slideIn 0.5s ease;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.5rem;
            font-weight: 700;
            background: var(--educollab-gradient);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .logo i {
            font-size: 1.8rem;
            color: var(--educollab-accent);
        }

        .user-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

=======
            padding-bottom: 1rem;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
        }
        
        .logo i {
            font-size: 1.8rem;
        }
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        .logout-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
<<<<<<< HEAD
            padding: 0.75rem 1.25rem;
            border-radius: var(--border-radius);
            background-color: #fef2f2;
            color: var(--danger);
            font-weight: 500;
            transition: var(--transition);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .logout-link:hover {
            background-color: #fee2e2;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .page-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            animation: fadeIn 0.6s ease;
        }

        .page-title h1 {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 2rem;
            color: var(--educollab-dark);
            font-weight: 700;
        }

        .page-title i {
            color: var(--educollab-primary);
            background: var(--educollab-light);
            padding: 0.75rem;
            border-radius: 12px;
        }

        .join-course-form {
            display: flex;
            gap: 0.75rem;
            align-items: center;
            background: white;
            padding: 0.75rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
        }

        .join-course-form input {
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: var(--border-radius);
            min-width: 220px;
            font-family: inherit;
            transition: var(--transition);
        }

        .join-course-form input:focus {
            outline: none;
            border-color: var(--educollab-primary);
            box-shadow: 0 0 0 3px rgba(74, 111, 165, 0.2);
        }

        .message {
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
=======
            color: var(--danger);
            font-weight: 500;
            transition: var(--transition);
        }
        
        .logout-link:hover {
            color: #d0006f;
        }
        
        .page-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 1.75rem;
            margin-bottom: 1.5rem;
            color: var(--dark);
        }
        
        .page-title i {
            color: var(--primary);
        }
        
        .message {
            padding: 1rem;
            margin-bottom: 1.5rem;
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            gap: 0.75rem;
<<<<<<< HEAD
            animation: fadeIn 0.5s ease;
            box-shadow: var(--shadow);
        }

        .message.error {
            background-color: #fef2f2;
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .message.success {
            background-color: #f0fdf4;
            color: #166534;
            border-left: 4px solid #22c55e;
        }

        .folder-nav {
            background-color: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            animation: slideIn 0.6s ease;
        }

=======
        }
        
        .message.error {
            background-color: #fdecea;
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        
        .message.success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #2e7d32;
        }
        
        .folder-nav {
            background-color: white;
            padding: 1.25rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        .folder-controls {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
<<<<<<< HEAD

        .folder-select {
            flex: 1;
            min-width: 200px;
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius);
            border: 2px solid #e2e8f0;
            font-family: inherit;
            background-color: var(--light);
            transition: var(--transition);
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%234a6fa5' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1em;
        }

        .folder-select:focus {
            outline: none;
            border-color: var(--educollab-primary);
            box-shadow: 0 0 0 3px rgba(74, 111, 165, 0.2);
        }

=======
        
        .folder-select {
            flex: 1;
            min-width: 200px;
            padding: 0.75rem;
            border-radius: var(--border-radius);
            border: 1px solid #ddd;
            font-family: inherit;
            background-color: var(--light);
            transition: var(--transition);
        }
        
        .folder-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(67, 97, 238, 0.2);
        }
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
<<<<<<< HEAD
            padding: 0.75rem 1.5rem;
=======
            padding: 0.75rem 1.25rem;
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            border-radius: var(--border-radius);
            font-family: inherit;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            border: none;
<<<<<<< HEAD
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .btn-primary {
            background: var(--educollab-gradient);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--hover-shadow);
        }

        .btn-outline {
            background-color: transparent;
            border: 2px solid var(--educollab-primary);
            color: var(--educollab-primary);
        }

        .btn-outline:hover {
            background-color: var(--educollab-primary);
            color: white;
            transform: translateY(-2px);
        }

=======
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary);
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background-color: var(--primary);
            color: white;
        }
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        .file-list {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
<<<<<<< HEAD
            margin-bottom: 2rem;
            animation: fadeIn 0.7s ease;
        }

        .file-list-header {
            display: grid;
            grid-template-columns: 3fr 1fr;
            padding: 1.5rem;
            background: var(--educollab-gradient);
            color: white;
            font-weight: 600;
            border-bottom: 1px solid #e5e7eb;
        }

        .file-item {
            display: grid;
            grid-template-columns: 3fr 1fr;
            padding: 1.5rem;
            border-bottom: 1px solid #f3f4f6;
            transition: var(--transition);
        }

        .file-item:hover {
            background-color: #f9fafb;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .file-item:last-child {
            border-bottom: none;
        }

=======
        }
        
        .file-list-header {
            display: grid;
            grid-template-columns: 3fr 1fr;
            padding: 1rem 1.5rem;
            background-color: var(--light);
            font-weight: 600;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .file-item {
            display: grid;
            grid-template-columns: 3fr 1fr;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f0f0f0;
            transition: var(--transition);
        }
        
        .file-item:hover {
            background-color: #f9f9f9;
        }
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        .file-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
<<<<<<< HEAD

        .file-icon-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            background: var(--educollab-light);
            border-radius: 12px;
        }

        .file-icon {
            font-size: 1.5rem;
            color: var(--educollab-accent);
        }

        .file-name {
            font-weight: 500;
        }

=======
        
        .file-icon {
            font-size: 1.5rem;
            color: var(--primary-light);
        }
        
        .file-name {
            font-weight: 500;
        }
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        .file-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            justify-content: flex-end;
        }
<<<<<<< HEAD

=======
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
<<<<<<< HEAD
            color: var(--educollab-dark);
            transition: var(--transition);
            padding: 0.75rem;
            border-radius: 50%;
            width: 2.75rem;
            height: 2.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8fafc;
        }

        .action-btn:hover {
            background-color: #f1f5f9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .action-btn.view:hover {
            color: var(--educollab-primary);
        }

        .action-btn.rename:hover {
            color: var(--warning);
        }

        .action-btn.delete:hover {
            color: var(--danger);
        }

        .rename-form {
            display: flex;
            gap: 0.75rem;
            margin-top: 0.75rem;
            width: 100%;
        }

        .rename-form input {
            flex: 1;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: var(--border-radius);
            font-family: inherit;
            transition: var(--transition);
        }

        .rename-form input:focus {
            outline: none;
            border-color: var(--educollab-primary);
            box-shadow: 0 0 0 3px rgba(74, 111, 165, 0.2);
        }

        .rename-form .btn {
            padding: 0.75rem 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6b7280;
            animation: fadeIn 0.8s ease;
        }

        .empty-state i {
            font-size: 4rem;
            color: #d1d5db;
            margin-bottom: 1.5rem;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: #374151;
            font-weight: 600;
        }

        .empty-state p {
            font-size: 1.1rem;
        }

        .upload-section {
            text-align: center;
            margin-bottom: 2rem;
            animation: fadeIn 0.9s ease;
        }

=======
            color: var(--dark);
            transition: var(--transition);
            padding: 0.5rem;
            border-radius: 50%;
            width: 2.25rem;
            height: 2.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .action-btn:hover {
            background-color: #f0f0f0;
            color: var(--primary);
        }
        
        .action-btn.view {
            color: var(--success);
        }
        
        .action-btn.rename {
            color: var(--warning);
        }
        
        .action-btn.delete {
            color: var(--danger);
        }
        
        .rename-form {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
            width: 100%;
        }
        
        .rename-form input {
            flex: 1;
            padding: 0.5rem 0.75rem;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            font-family: inherit;
        }
        
        .rename-form input:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .rename-form .btn {
            padding: 0.5rem 1rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 1rem;
        }
        
        .upload-section {
            margin-top: 2rem;
            text-align: center;
        }
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        .upload-link {
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
<<<<<<< HEAD
            padding: 1.25rem 2.5rem;
            background: var(--educollab-accent-gradient);
            color: white;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
            box-shadow: var(--shadow);
            font-size: 1.1rem;
        }

        .upload-link:hover {
            transform: translateY(-3px);
            box-shadow: var(--hover-shadow);
        }

        .courses-card {
            background: white;
            margin-top: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 2rem;
            animation: slideIn 0.8s ease;
        }

        .courses-card h3 {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin: 0 0 1.5rem;
            color: var(--educollab-dark);
            font-size: 1.5rem;
            font-weight: 700;
        }

        .courses-card h3 i {
            color: var(--educollab-primary);
            background: var(--educollab-light);
            padding: 0.75rem;
            border-radius: 12px;
        }

        .course-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .course-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem;
            border-bottom: 1px solid #f3f4f6;
            transition: var(--transition);
        }

        .course-item:hover {
            background-color: #f9fafb;
            border-radius: 12px;
        }

        .course-item:last-child {
            border-bottom: none;
        }

        .course-info {
            flex: 1;
        }

        .course-name {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--educollab-dark);
        }

        .course-code {
            display: inline-block;
            background: #eef2ff;
            color: #3730a3;
            padding: 0.5rem 1rem;
            border-radius: 999px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .course-link {
            padding: 0.75rem 1.25rem;
            font-weight: 500;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideIn {
            from { transform: translateY(10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

=======
            padding: 1rem 2rem;
            background-color: var(--primary);
            color: white;
            border-radius: var(--border-radius);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .upload-link:hover {
            background-color: var(--secondary);
            transform: translateY(-2px);
        }
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        @media (max-width: 768px) {
            .file-item {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
<<<<<<< HEAD

            .file-actions {
                justify-content: flex-start;
            }

=======
            
            .file-actions {
                justify-content: flex-start;
            }
            
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            .folder-controls {
                flex-direction: column;
                align-items: stretch;
            }
<<<<<<< HEAD

            .page-title {
                flex-direction: column;
                align-items: flex-start;
            }

            .join-course-form {
                width: 100%;
                flex-direction: column;
            }

            .join-course-form input {
                width: 100%;
            }
            
            .container {
                padding: 1rem;
            }
        }
    </style>
</head>

=======
        }
    </style>
</head>
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
<body>
    <div class="container">
        <header>
            <div class="logo">
<<<<<<< HEAD
                <i class="fas fa-users"></i>
                <span>Educollab</span>
            </div>
            <div class="user-actions">
                <a href="logout.php" class="logout-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </header>

        <div class="page-title">
            <h1><i class="fas fa-folder-open"></i> <?= $current_folder ? "Folder Contents" : "My Files" ?></h1>

            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
                <form method="POST" action="join_course.php" class="join-course-form">
                    <input type="text" name="course_code" placeholder="Enter course code" required>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i> Join Course
                    </button>
                </form>
            <?php endif; ?>
        </div>

=======
                <i class="fas fa-cloud"></i>
                <span>CloudDrive</span>
            </div>
            <a href="logout.php" class="logout-link">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </header>
        
        <h1 class="page-title">
            <i class="fas fa-folder-open"></i> 
            <?= $current_folder ? "Folder Contents" : "My Files" ?>
        </h1>
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        <?php if (!empty($error)): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>
<<<<<<< HEAD

=======
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        <?php if (!empty($success)): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?= $success ?>
            </div>
        <?php endif; ?>
<<<<<<< HEAD

=======
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        <div class="folder-nav">
            <div class="folder-controls">
                <select name="folder" class="folder-select" onchange="window.location.href='?folder='+this.value">
                    <option value="">-- All Files (Root) --</option>
                    <?php while ($folder = $folders->fetch_assoc()): ?>
                        <option value="<?= $folder['id'] ?>" <?= $current_folder == $folder['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($folder['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
<<<<<<< HEAD

=======
                
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
                <a href="folders.php" class="btn btn-outline">
                    <i class="fas fa-folder-plus"></i> Manage Folders
                </a>
            </div>
        </div>
<<<<<<< HEAD

=======
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        <div class="file-list">
            <div class="file-list-header">
                <div>File Name</div>
                <div>Actions</div>
            </div>
<<<<<<< HEAD

=======
            
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <div class="file-item">
                        <div class="file-info">
<<<<<<< HEAD
                            <div class="file-icon-wrapper">
                                <i class="fas fa-file-alt file-icon"></i>
                            </div>
=======
                            <i class="fas fa-file-alt file-icon"></i>
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
                            <div>
                                <div class="file-name"><?= htmlspecialchars($row['original_name']) ?></div>
                                <div id="rename-form-<?= $row['id'] ?>" style="display: none;">
                                    <form method="POST" action="rename.php" class="rename-form">
                                        <input type="hidden" name="type" value="file">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <input type="text" name="new_name" value="<?= htmlspecialchars($row['original_name']) ?>" required>
<<<<<<< HEAD
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Save
                                        </button>
                                        <button type="button" onclick="hideRenameForm(<?= $row['id'] ?>)" class="btn btn-outline">
=======
                                        <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem;">
                                            <i class="fas fa-save"></i> Save
                                        </button>
                                        <button type="button" onclick="hideRenameForm(<?= $row['id'] ?>)" class="btn btn-outline" style="padding: 0.5rem 1rem;">
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <div class="file-actions">
                            <a href="uploads/<?= htmlspecialchars($row['filename']) ?>" class="action-btn view" target="_blank" title="View">
                                <i class="fas fa-eye"></i>
                            </a>
                            <button onclick="showRenameForm(<?= $row['id'] ?>)" class="action-btn rename" title="Rename">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="delete.php?file=<?= urlencode($row['filename']) ?>" class="action-btn delete" title="Delete" onclick="return confirm('Are you sure you want to delete this file?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <h3>No files found in this folder</h3>
                    <p>Upload your first file to get started</p>
                </div>
            <?php endif; ?>
        </div>
<<<<<<< HEAD

        <div class="upload-section">
            <a href="upload.php<?= $current_folder ? '?folder=' . $current_folder : '' ?>" class="upload-link">
                <i class="fas fa-cloud-upload-alt"></i> Upload New File
            </a>
        </div>

        <?php if ($student_uid_for_list): ?>
            <div class="courses-card">
                <h3><i class="fas fa-book"></i> My Courses</h3>
                <?php
                $mc = $conn->prepare("
                    SELECT c.id, c.name, c.code
                    FROM course_enrollments ce
                    JOIN courses c ON c.id = ce.course_id
                    WHERE ce.student_university_id = ?
                    ORDER BY c.created_at DESC
                ");
                $mc->bind_param('s', $student_uid_for_list);
                $mc->execute();
                $rs = $mc->get_result();
                ?>
                <?php if ($rs && $rs->num_rows > 0): ?>
                    <ul class="course-list">
                        <?php while ($row = $rs->fetch_assoc()): ?>
                            <li class="course-item">
                                <div class="course-info">
                                    <div class="course-name"><?= htmlspecialchars($row['name']) ?></div>
                                    <span class="course-code">Code: <?= htmlspecialchars($row['code']) ?></span>
                                </div>
                                <a href="student_course_dashboard.php?id=<?= (int)$row['id'] ?>" class="btn btn-primary course-link">Open</a>
                            </li>
                        <?php endwhile; ?>
                    </ul>
                <?php else: ?>
                    <p style="color:#6b7280; text-align: center; padding: 1.5rem;">No courses yet. Use the Join Course form above with a course code.</p>
                <?php endif;
                $mc->close(); ?>
            </div>
        <?php endif; ?>
=======
        
        <div class="upload-section">
            <a href="upload.php<?= $current_folder ? '?folder='.$current_folder : '' ?>" class="upload-link">
                <i class="fas fa-cloud-upload-alt"></i> Upload New File
            </a>
        </div>
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
    </div>

    <script>
        function showRenameForm(fileId) {
            // Hide all rename forms first
            document.querySelectorAll('[id^="rename-form-"]').forEach(form => {
                form.style.display = 'none';
            });
<<<<<<< HEAD

            // Show the selected rename form
            const renameForm = document.getElementById('rename-form-' + fileId);
            renameForm.style.display = 'block';

=======
            
            // Show the selected rename form
            const renameForm = document.getElementById('rename-form-' + fileId);
            renameForm.style.display = 'block';
            
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            // Focus the input field
            const inputField = renameForm.querySelector('input[type="text"]');
            inputField.focus();
            inputField.select();
        }
<<<<<<< HEAD

        function hideRenameForm(fileId) {
            document.getElementById('rename-form-' + fileId).style.display = 'none';
        }

        // Add subtle animations to elements as they come into view
        document.addEventListener('DOMContentLoaded', function() {
            const animatedElements = document.querySelectorAll('.file-item, .folder-nav, .courses-card');
            
            animatedElements.forEach((element, index) => {
                element.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</body>

=======
        
        function hideRenameForm(fileId) {
            document.getElementById('rename-form-' + fileId).style.display = 'none';
        }
    </script>
</body>
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
</html>