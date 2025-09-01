<?php
session_start();
require 'db.php';

// Check if users table exists, if not create it
$checkTable = $conn->query("SHOW TABLES LIKE 'users'");
if ($checkTable->num_rows == 0) {
    $createTable = "CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(255) NULL,
        phone VARCHAR(20) NULL,
        password VARCHAR(255) NOT NULL,
<<<<<<< HEAD
        role ENUM('student','professor') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

=======
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
    if (!$conn->query($createTable)) {
        die("Error creating users table: " . $conn->error);
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = !empty($_POST['email']) ? trim($_POST['email']) : null;
    $phone = !empty($_POST['phone']) ? trim($_POST['phone']) : null;
    $password = $_POST['password'];
<<<<<<< HEAD
    $role = isset($_POST['role']) ? $_POST['role'] : '';

    // Validate role
    if ($role !== 'student' && $role !== 'professor') {
        $error = "Please select a valid role.";
    }
    // Validate email for students
    elseif ($role === 'student' && !preg_match("/^\d{4}-\d-\d{2}-\d{3}@gmail\.com$/", $email)) {
        $error = "Students must use a valid student email (e.g., 2022-1-60-102@gmail.com).";
    }
    // Validate at least one identifier is provided
    elseif (empty($email) && empty($phone)) {
        $error = "You must enter either an email or phone number.";
    }
=======

    // Validate at least one identifier is provided
    if (empty($email) && empty($phone)) {
        $error = "You must enter either an email or phone number.";
    } 
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
    // Validate password length
    elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        // Check if email or phone already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
        $stmt->bind_param("ss", $email, $phone);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $error = "Email or phone already registered. Please try logging in.";
        } else {
            // Hash the password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Insert new user
<<<<<<< HEAD
            $stmt = $conn->prepare("INSERT INTO users (email, phone, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $email, $phone, $hashed_password, $role);
=======
            $stmt = $conn->prepare("INSERT INTO users (email, phone, password) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $email, $phone, $hashed_password);
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735

            if ($stmt->execute()) {
                $success = "Registration successful! <a href='login.php'>Login here</a>";
                // Clear form fields
                $email = $phone = '';
            } else {
                $error = "Registration failed: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<<<<<<< HEAD

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Educollab</title>
=======
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register | Cloud Storage</title>
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --secondary: #3f37c9;
            --accent: #4895ef;
            --danger: #f72585;
            --success: #4cc9f0;
            --light: #f8f9fa;
            --dark: #212529;
<<<<<<< HEAD
            --educollab-primary: #4a6fa5;
            --educollab-secondary: #166088;
            --educollab-accent: #4cb5ae;
        }

=======
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
            background: linear-gradient(135deg, #f5f7fa 0%, #e4efe9 100%);
=======
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            color: var(--dark);
        }
<<<<<<< HEAD

        .register-container {
            background: white;
=======
        
        .register-container {
            background-color: white;
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
            padding: 40px;
            text-align: center;
        }
<<<<<<< HEAD

        .logo {
            font-size: 28px;
            font-weight: 600;
            color: var(--educollab-primary);
=======
        
        .logo {
            font-size: 28px;
            font-weight: 600;
            color: var(--primary);
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            margin-bottom: 30px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
<<<<<<< HEAD

        .logo i {
            margin-right: 10px;
            color: var(--educollab-accent);
        }

        h2 {
            color: var(--educollab-secondary);
            margin-bottom: 20px;
        }

=======
        
        .logo i {
            margin-right: 10px;
            color: var(--accent);
        }
        
        h2 {
            color: var(--secondary);
            margin-bottom: 20px;
        }
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
<<<<<<< HEAD

=======
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--dark);
        }
<<<<<<< HEAD

=======
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border 0.3s;
        }
<<<<<<< HEAD

        input:focus {
            border-color: var(--educollab-accent);
            outline: none;
        }

        .btn {
            background-color: var(--educollab-primary);
=======
        
        input:focus {
            border-color: var(--accent);
            outline: none;
        }
        
        .btn {
            background-color: var(--primary);
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s;
        }
<<<<<<< HEAD

        .btn:hover {
            background-color: var(--educollab-secondary);
        }

=======
        
        .btn:hover {
            background-color: var(--secondary);
        }
        
        .btn i {
            margin-right: 8px;
        }
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        .error {
            color: var(--danger);
            margin-bottom: 20px;
            font-size: 14px;
            padding: 10px;
            background-color: rgba(247, 37, 133, 0.1);
            border-radius: 5px;
        }
<<<<<<< HEAD

=======
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        .success {
            color: var(--success);
            margin-bottom: 20px;
            font-size: 14px;
            padding: 10px;
            background-color: rgba(76, 201, 240, 0.1);
            border-radius: 5px;
        }
<<<<<<< HEAD

=======
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        .login-link {
            margin-top: 20px;
            font-size: 14px;
        }
<<<<<<< HEAD

        .login-link a {
            color: var(--educollab-accent);
            text-decoration: none;
            font-weight: 500;
        }

        .login-link a:hover {
            text-decoration: underline;
        }

=======
        
        .login-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        .radio-group {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
<<<<<<< HEAD

=======
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        .radio-option {
            display: flex;
            align-items: center;
        }
<<<<<<< HEAD

=======
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        .radio-option input {
            width: auto;
            margin-right: 8px;
        }
    </style>
</head>
<<<<<<< HEAD

<body>
    <div class="register-container">
        <div class="logo">
            <i class="fas fa-users"></i>
            <span>Educollab</span>
        </div>

        <h2><i class="fas fa-user-plus"></i> Create Your Account</h2>

=======
<body>
    <div class="register-container">
        <div class="logo">
            <i class="fas fa-cloud-upload-alt"></i>
            <span>CloudStorage</span>
        </div>
        
        <h2><i class="fas fa-user-plus"></i> Create Your Account</h2>
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        <?php if (!empty($error)): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>
<<<<<<< HEAD

=======
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        <?php if (!empty($success)): ?>
            <div class="success">
                <i class="fas fa-check-circle"></i> <?= $success ?>
            </div>
        <?php endif; ?>
<<<<<<< HEAD

        <form method="POST" id="registerForm">
            <div class="form-group">
                <label>Select Role</label>
                <div class="radio-group">
                    <label class="radio-option">
                        <input type="radio" name="role" value="student" required> Student
                    </label>
                    <label class="radio-option">
                        <input type="radio" name="role" value="professor" required> Instructor
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>" required>
            </div>

=======
        
        <form method="POST" id="registerForm">
            <div class="radio-group">
                <label class="radio-option">
                    <input type="radio" name="reg_method" value="email" onclick="toggleField('email')" checked>
                    Email
                </label>
                <label class="radio-option">
                    <input type="radio" name="reg_method" value="phone" onclick="toggleField('phone')">
                    Phone
                </label>
            </div>
            
            <div class="form-group" id="emailField">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?= isset($email) ? htmlspecialchars($email) : '' ?>">
            </div>
            
            <div class="form-group" id="phoneField" style="display:none;">
                <label for="phone">Phone</label>
                <input type="text" id="phone" name="phone" value="<?= isset($phone) ? htmlspecialchars($phone) : '' ?>">
            </div>
            
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            <div class="form-group">
                <label for="password">Password (min 8 characters)</label>
                <input type="password" id="password" name="password" required minlength="8">
            </div>
<<<<<<< HEAD

=======
            
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            <button type="submit" class="btn">
                <i class="fas fa-user-plus"></i> Register
            </button>
        </form>
<<<<<<< HEAD

=======
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        <div class="login-link">
            Already have an account? <a href="login.php">Login here</a>
        </div>
    </div>

    <script>
<<<<<<< HEAD
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
=======
        function toggleField(method) {
            if (method === 'email') {
                document.getElementById('emailField').style.display = 'block';
                document.getElementById('phoneField').style.display = 'none';
                document.getElementById('email').required = true;
                document.getElementById('phone').required = false;
            } else {
                document.getElementById('emailField').style.display = 'none';
                document.getElementById('phoneField').style.display = 'block';
                document.getElementById('email').required = false;
                document.getElementById('phone').required = true;
            }
        }

        // Form validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const phone = document.getElementById('phone').value;
            const password = document.getElementById('password').value;
            
            if (!email && !phone) {
                alert('Please provide either email or phone number');
                e.preventDefault();
                return false;
            }
            
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            if (password.length < 8) {
                alert('Password must be at least 8 characters long');
                e.preventDefault();
                return false;
            }
<<<<<<< HEAD
        });
    </script>
</body>

=======
            
            return true;
        });
    </script>
</body>
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
</html>