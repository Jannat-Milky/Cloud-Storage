<?php
session_start();
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier']);
    $password = $_POST['password'];
<<<<<<< HEAD
    $role = $_POST['role']; // selected role

    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE (email = ? OR phone = ?) AND role = ?");
    $stmt->bind_param("sss", $identifier, $identifier, $role);
=======

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ? OR phone = ?");
    $stmt->bind_param("ss", $identifier, $identifier);
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
<<<<<<< HEAD
            $_SESSION['role'] = $user['role'];

            // Redirect based on role
            if ($user['role'] === 'student') {
                header("Location: files.php");
            } elseif ($user['role'] === 'professor') {
                header("Location: professor_dashboard.php");
            } else {
                header("Location: files.php"); // fallback
            }
=======
            header("Location: files.php");
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            exit();
        } else {
            $error = "Incorrect password.";
        }
    } else {
<<<<<<< HEAD
        $error = "No user found with that email, phone, or role.";
=======
        $error = "No user found with that email or phone.";
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<<<<<<< HEAD

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Educollab</title>
=======
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Login | Cloud Storage</title>
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
<<<<<<< HEAD
            --educollab-primary: #4a6fa5;
            --educollab-secondary: #166088;
            --educollab-accent: #4cb5ae;
=======
            --primary: #4361ee;
            --secondary: #3f37c9;
            --accent: #4895ef;
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            --danger: #f72585;
            --success: #4cc9f0;
            --light: #f8f9fa;
            --dark: #212529;
        }
<<<<<<< HEAD

=======
        
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
            padding: 15px;
        }
<<<<<<< HEAD

        .login-container {
            background: white;
=======
        
        .login-container {
            background-color: white;
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
            padding: 30px;
            text-align: center;
        }
<<<<<<< HEAD

        .logo {
            font-size: 24px;
            font-weight: 600;
            color: var(--educollab-primary);
=======
        
        .logo {
            font-size: 24px;
            font-weight: 600;
            color: var(--primary);
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            margin-bottom: 25px;
            display: flex;
            justify-content: center;
            align-items: center;
        }
<<<<<<< HEAD

        .logo i {
            margin-right: 10px;
            color: var(--educollab-accent);
            font-size: 28px;
        }

        .illustration {
            margin-bottom: 25px;
            font-size: 50px;
            color: var(--educollab-accent);
        }

        h2 {
            color: var(--educollab-secondary);
            margin-bottom: 20px;
            font-size: 22px;
        }

=======
        
        .logo i {
            margin-right: 10px;
            color: var(--accent);
            font-size: 28px;
        }
        
        .illustration {
            margin-bottom: 25px;
            font-size: 50px;
            color: var(--accent);
        }
        
        h2 {
            color: var(--secondary);
            margin-bottom: 20px;
            font-size: 22px;
        }
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        .form-group {
            margin-bottom: 18px;
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
            font-size: 14px;
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
<<<<<<< HEAD
        }

        input:focus {
            border-color: var(--educollab-accent);
            outline: none;
        }

        .btn {
            background-color: var(--educollab-primary);
=======
            -webkit-appearance: none;
        }
        
        input:focus {
            border-color: var(--accent);
            outline: none;
        }
        
        .btn {
            background-color: var(--primary);
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            color: white;
            border: none;
            padding: 14px 20px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
            transition: background-color 0.3s;
            margin-top: 10px;
            min-height: 48px;
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
<<<<<<< HEAD

=======
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        .register-link {
            margin-top: 20px;
            font-size: 14px;
        }
<<<<<<< HEAD

        .register-link a {
            color: var(--educollab-accent);
            text-decoration: none;
            font-weight: 500;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .radio-option {
            display: flex;
            align-items: center;
        }

        .radio-option input {
            width: auto;
            margin-right: 8px;
        }

        @media(max-width:480px) {
            .login-container {
                padding: 25px 20px;
            }

            .logo {
                font-size: 22px;
            }

=======
        
        .register-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 500;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 25px 20px;
            }
            
            .logo {
                font-size: 22px;
            }
            
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            .illustration {
                font-size: 45px;
                margin-bottom: 20px;
            }
<<<<<<< HEAD

            h2 {
                font-size: 20px;
            }

            input {
                padding: 12px;
            }

=======
            
            h2 {
                font-size: 20px;
            }
            
            input {
                padding: 12px;
            }
            
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            .btn {
                padding: 12px;
                font-size: 15px;
            }
        }
    </style>
</head>
<<<<<<< HEAD

<body>
    <div class="login-container">
        <div class="logo">
            <i class="fas fa-users"></i>
            <span>Educollab</span>
        </div>

        <div class="illustration">
            <i class="fas fa-sign-in-alt"></i>
        </div>

        <h2>Login to Your Account</h2>

=======
<body>
    <div class="login-container">
        <div class="logo">
            <i class="fas fa-cloud-upload-alt"></i>
            <span>CloudStorage</span>
        </div>
        
        <div class="illustration">
            <i class="fas fa-sign-in-alt"></i>
        </div>
        
        <h2>Login to Your Account</h2>
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        <?php if (isset($error)): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>
<<<<<<< HEAD

        <form method="POST" autocomplete="off">
            <div class="radio-group">
                <label class="radio-option">
                    <input type="radio" name="role" value="student" checked> Student
                </label>
                <label class="radio-option">
                    <input type="radio" name="role" value="professor"> Instructor
                </label>
            </div>

=======
        
        <form method="POST" autocomplete="off">
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            <div class="form-group">
                <label for="identifier">Email or Phone</label>
                <input type="text" id="identifier" name="identifier" required>
            </div>
<<<<<<< HEAD

=======
            
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
<<<<<<< HEAD

=======
            
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
            <button type="submit" class="btn">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
<<<<<<< HEAD

=======
        
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
        <div class="register-link">
            Not registered yet? <a href="register.php">Create an account</a>
        </div>
    </div>
</body>
<<<<<<< HEAD

=======
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
</html>