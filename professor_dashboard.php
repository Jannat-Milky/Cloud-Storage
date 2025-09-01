<?php
session_start();
require 'db.php';

// Redirect if not professor
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'professor') {
    header("Location: login.php");
    exit();
}

$professor_id = (int)($_SESSION['user_id'] ?? 0);
$success = $error = '';
$flash = isset($_GET['msg']) ? trim($_GET['msg']) : '';

// Fetch professor courses (show code + student counts)
$courses = $conn->query(
    "SELECT c.id, c.name, c.code,
            (SELECT COUNT(*) FROM course_enrollments ce WHERE ce.course_id = c.id) AS student_count
     FROM courses c
     WHERE c.professor_id = {$professor_id}
     ORDER BY c.created_at DESC"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Instructor Dashboard | Educollab</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    :root {
      --educollab-primary: #4a6fa5;
      --educollab-secondary: #166088;
      --educollab-accent: #4cb5ae;
      --danger: #ef4444;
      --success: #10b981;
      --warning: #f59e0b;
      --light: #f8fafc;
      --dark: #1e293b;
      --gray: #64748b;
      --light-gray: #e2e8f0;
      --card-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 5px 10px -5px rgba(0, 0, 0, 0.04);
      --gradient: linear-gradient(135deg, #4a6fa5 0%, #166088 100%);
    }
    
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body {
      font-family: 'Poppins', sans-serif;
      background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
      min-height: 100vh;
      padding: 0;
      color: var(--dark);
    }
    
    .container {
      max-width: 1200px;
      margin: 0 auto;
      padding: 20px;
    }
    
    header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 30px;
      padding: 20px;
      background: white;
      border-radius: 16px;
      box-shadow: var(--card-shadow);
    }
    
    .header-left h1 {
      background: var(--gradient);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      font-size: 32px;
      margin-bottom: 5px;
      font-weight: 700;
    }
    
    .header-left p {
      color: var(--gray);
      font-size: 16px;
    }
    
    .header-right {
      display: flex;
      gap: 15px;
    }
    
    .btn {
      background: var(--gradient);
      color: white;
      padding: 12px 24px;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.3s;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      font-size: 15px;
      box-shadow: 0 4px 6px rgba(74, 111, 165, 0.2);
    }
    
    .btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 7px 14px rgba(74, 111, 165, 0.3);
    }
    
    .btn-outline {
      background: transparent;
      border: 2px solid var(--educollab-primary);
      color: var(--educollab-primary);
    }
    
    .btn-outline:hover {
      background: var(--educollab-primary);
      color: white;
    }
    
    .btn-success {
      background: linear-gradient(135deg, var(--educollab-accent) 0%, #3da89c 100%);
    }
    
    .tag {
      display: inline-flex;
      align-items: center;
      background: #eef2ff;
      color: var(--educollab-primary);
      padding: 6px 12px;
      border-radius: 999px;
      font-weight: 500;
      font-size: 14px;
      gap: 5px;
    }
    
    .tag-warning {
      background: #fffbeb;
      color: #92400e;
    }
    
    .tag-success {
      background: #ecfdf5;
      color: #065f46;
    }
    
    .error {
      color: #b91c1c;
      padding: 15px;
      background: #fee2e2;
      border: 1px solid #fecaca;
      border-radius: 12px;
      margin: 15px 0;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .success {
      color: #065f46;
      padding: 15px;
      background: #ecfdf5;
      border: 1px solid #a7f3d0;
      border-radius: 12px;
      margin: 15px 0;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .flash {
      color: #065f46;
      padding: 15px;
      background: #ecfdf5;
      border: 1px solid #a7f3d0;
      border-radius: 12px;
      margin: 15px 0;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    
    .card {
      background: #fff;
      padding: 30px;
      border-radius: 20px;
      box-shadow: var(--card-shadow);
      margin-bottom: 30px;
      transition: transform 0.3s, box-shadow 0.3s;
      position: relative;
      overflow: hidden;
    }
    
    .card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 5px;
      background: var(--gradient);
    }
    
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.08);
    }
    
    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
      padding-bottom: 15px;
      border-bottom: 1px solid var(--light-gray);
    }
    
    .card-title {
      font-size: 24px;
      color: var(--educollab-primary);
      font-weight: 600;
    }
    
    .card-icon {
      width: 50px;
      height: 50px;
      background: rgba(74, 111, 165, 0.1);
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--educollab-primary);
      font-size: 24px;
    }
    
    .form-group {
      margin-bottom: 20px;
    }
    
    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 500;
      color: var(--dark);
    }
    
    input, select, textarea {
      width: 100%;
      padding: 15px;
      border: 1px solid var(--light-gray);
      border-radius: 12px;
      font-size: 16px;
      transition: border-color 0.3s, box-shadow 0.3s;
      background: var(--light);
    }
    
    input:focus, select:focus, textarea:focus {
      outline: none;
      border-color: var(--educollab-primary);
      box-shadow: 0 0 0 4px rgba(74, 111, 165, 0.1);
    }
    
    textarea {
      resize: vertical;
      min-height: 120px;
    }
    
    .courses-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
      gap: 25px;
      margin-top: 20px;
    }
    
    .course-card {
      background: #fff;
      border-radius: 16px;
      padding: 25px;
      box-shadow: var(--card-shadow);
      transition: transform 0.3s, box-shadow 0.3s;
      border: 1px solid var(--light-gray);
      position: relative;
      overflow: hidden;
    }
    
    .course-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--gradient);
    }
    
    .course-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
    }
    
    .course-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 15px;
    }
    
    .course-title {
      font-size: 20px;
      font-weight: 600;
      color: var(--dark);
      margin-bottom: 5px;
    }
    
    .course-code {
      color: var(--gray);
      font-size: 14px;
      font-weight: 500;
    }
    
    .course-stats {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 20px;
    }
    
    .course-actions {
      display: flex;
      gap: 12px;
      margin-top: 20px;
    }
    
    .course-action-btn {
      flex: 1;
      text-align: center;
      padding: 12px;
      background: #f9fafb;
      border-radius: 10px;
      color: var(--dark);
      text-decoration: none;
      font-weight: 500;
      font-size: 14px;
      transition: all 0.2s;
      border: 1px solid var(--light-gray);
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 5px;
    }
    
    .course-action-btn:hover {
      background: var(--educollab-primary);
      color: white;
      transform: translateY(-2px);
      box-shadow: 0 4px 6px rgba(74, 111, 165, 0.2);
    }
    
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: var(--gray);
    }
    
    .empty-state i {
      font-size: 64px;
      margin-bottom: 20px;
      color: var(--light-gray);
      opacity: 0.7;
    }
    
    .empty-state h3 {
      font-size: 22px;
      margin-bottom: 10px;
      color: var(--dark);
    }
    
    .empty-state p {
      margin-top: 10px;
      font-size: 16px;
    }
    
    .stats-container {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    
    .stat-card {
      background: white;
      padding: 25px;
      border-radius: 16px;
      box-shadow: var(--card-shadow);
      text-align: center;
    }
    
    .stat-number {
      font-size: 36px;
      font-weight: 700;
      background: var(--gradient);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      margin-bottom: 10px;
    }
    
    .stat-label {
      color: var(--gray);
      font-size: 16px;
    }
    
    .welcome-banner {
      background: var(--gradient);
      color: white;
      padding: 30px;
      border-radius: 16px;
      margin-bottom: 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    
    .welcome-text h2 {
      font-size: 28px;
      margin-bottom: 10px;
    }
    
    .welcome-text p {
      opacity: 0.9;
    }
    
    .welcome-icon {
      font-size: 60px;
      opacity: 0.8;
    }
    
    @media (max-width: 768px) {
      .courses-grid {
        grid-template-columns: 1fr;
      }
      
      header {
        flex-direction: column;
        text-align: center;
        gap: 15px;
      }
      
      .header-right {
        justify-content: center;
      }
      
      .welcome-banner {
        flex-direction: column;
        text-align: center;
        gap: 20px;
      }
      
      .stats-container {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
<div class="container">
  <header>
    <div class="header-left">
      <h1>Instructor Dashboard</h1>
      <p>Manage your courses and student enrollments</p>
    </div>
    <div class="header-right">
      <a href="logout.php" class="btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </header>

  <!-- Welcome Banner -->
  <div class="welcome-banner">
    <div class="welcome-text">
      <h2>Welcome back, Instructor!</h2>
      <p>Create and manage your courses with ease</p>
    </div>
    <div class="welcome-icon">
      <i class="fas fa-chalkboard-teacher"></i>
    </div>
  </div>

  <?php if ($flash): ?>
    <div class="flash">
      <i class="fas fa-check-circle"></i>
      <?= htmlspecialchars($flash) ?>
    </div>
  <?php endif; ?>
  
  <?php if ($error): ?>
    <div class="error">
      <i class="fas fa-exclamation-circle"></i>
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>
  
  <?php if ($success): ?>
    <div class="success">
      <i class="fas fa-check-circle"></i>
      <?= htmlspecialchars($success) ?>
    </div>
  <?php endif; ?>

  <!-- Stats Overview -->
  <div class="stats-container">
    <div class="stat-card">
      <div class="stat-number"><?= $courses->num_rows ?? 0 ?></div>
      <div class="stat-label">Total Courses</div>
    </div>
    <div class="stat-card">
      <div class="stat-number">
        <?php
          $total_students = 0;
          if ($courses && $courses->num_rows > 0) {
            while($c = $courses->fetch_assoc()) {
              $total_students += (int)$c['student_count'];
            }
            // Reset pointer for later use
            $courses->data_seek(0);
          }
          echo $total_students;
        ?>
      </div>
      <div class="stat-label">Total Students</div>
    </div>
  </div>

  <!-- Create Course & Upload Enrollment CSV -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">Create New Course</h3>
      <div class="card-icon">
        <i class="fas fa-plus"></i>
      </div>
    </div>
    <p style="margin: 0 0 20px; color: var(--gray);">
      Create a new course with a unique code and enroll students via CSV upload.
    </p>
    <form action="upload_csv.php" method="POST" enctype="multipart/form-data">
      <div class="form-group">
        <label for="course_name">Course Name <span style="color: var(--danger)">*</span></label>
        <input type="text" id="course_name" name="course_name" placeholder="e.g., Software Engineering" required>
      </div>
      <div class="form-group">
        <label for="course_description">Description (optional)</label>
        <textarea id="course_description" name="course_description" rows="3" placeholder="Brief course description..."></textarea>
      </div>
      <div class="form-group">
        <label for="csv_file">Enrollment CSV File <span style="color: var(--danger)">*</span></label>
        <input type="file" id="csv_file" name="csv_file" accept=".csv" required>
        <small style="display: block; margin-top: 8px; color: var(--gray);">
          Expected format: <code>student_university_id,cgpa</code>
        </small>
      </div>
      <input type="hidden" name="create_course" value="1">
      <button type="submit" class="btn btn-success">
        <i class="fas fa-plus-circle"></i> Create Course
      </button>
    </form>
  </div>

  <!-- My Courses Section -->
  <div class="card">
    <div class="card-header">
      <h3 class="card-title">My Courses</h3>
      <span class="tag"><i class="fas fa-book"></i> <?= $courses->num_rows ?? 0 ?> courses</span>
    </div>
    
    <?php if ($courses && $courses->num_rows > 0): ?>
      <div class="courses-grid">
        <?php while ($c = $courses->fetch_assoc()): ?>
          <div class="course-card">
            <div class="course-header">
              <div>
                <h3 class="course-title"><?= htmlspecialchars($c['name']) ?></h3>
                <div class="course-code"><?= htmlspecialchars($c['code']) ?></div>
              </div>
            </div>
            
            <div class="course-stats">
              <span class="tag tag-success">
                <i class="fas fa-users"></i> <?= (int)$c['student_count'] ?> students
              </span>
            </div>
            
            <div class="course-actions">
              <a href="course_dashboard.php?id=<?= (int)$c['id'] ?>" class="course-action-btn">
                <i class="fas fa-chalkboard"></i> Dashboard
              </a>
              <a href="taskboard.php?course_id=<?= (int)$c['id'] ?>" class="course-action-btn">
                <i class="fas fa-tasks"></i> Tasks
              </a>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <div class="empty-state">
        <i class="fas fa-book-open"></i>
        <h3>No courses yet</h3>
        <p>Create your first course using the form above</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<script>
  // Simple animations for better user experience
  document.addEventListener('DOMContentLoaded', function() {
    // Animate cards on page load
    const cards = document.querySelectorAll('.card, .stat-card, .welcome-banner');
    cards.forEach((card, index) => {
      card.style.opacity = '0';
      card.style.transform = 'translateY(20px)';
      
      setTimeout(() => {
        card.style.transition = 'opacity 0.5s, transform 0.5s';
        card.style.opacity = '1';
        card.style.transform = 'translateY(0)';
      }, 100 * index);
    });
    
    // Add hover effects to course cards
    const courseCards = document.querySelectorAll('.course-card');
    courseCards.forEach(card => {
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