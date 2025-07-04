<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("Please <a href='login.php'>login</a> first.");
}

// Create uploads directory if it doesn't exist
if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $user_id = $_SESSION['user_id'];
    $original = basename($_FILES['file']['name']);
    $tmp = $_FILES['file']['tmp_name'];
    $newName = time() . "_" . $original;
    
    if (move_uploaded_file($tmp, "uploads/" . $newName)) {
        $stmt = $conn->prepare("INSERT INTO files (user_id, filename, original_name) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $newName, $original);
        
        if ($stmt->execute()) {
            $success = "File uploaded successfully!";
        } else {
            $error = "Failed to save file information.";
        }
    } else {
        $error = "Failed to upload file.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload File | Cloud Storage</title>
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
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            margin: 0;
            padding: 20px;
            color: var(--dark);
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        h2 {
            color: var(--primary);
            text-align: center;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .file-input {
            display: block;
            width: 100%;
            padding: 15px;
            border: 2px dashed #ccc;
            border-radius: 5px;
            text-align: center;
            cursor: pointer;
        }
        
        .file-input:hover {
            border-color: var(--accent);
        }
        
        .submit-btn {
            background-color: var(--primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }
        
        .submit-btn:hover {
            background-color: var(--secondary);
        }
        
        .message {
            text-align: center;
            margin: 15px 0;
            padding: 10px;
            border-radius: 5px;
        }
        
        .success {
            background-color: rgba(76, 201, 240, 0.2);
            color: var(--success);
        }
        
        .error {
            background-color: rgba(247, 37, 133, 0.2);
            color: var(--danger);
        }
        
        .links {
            margin-top: 20px;
            text-align: center;
        }
        
        .links a {
            color: var(--primary);
            text-decoration: none;
            margin: 0 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2><i class="fas fa-cloud-upload-alt"></i> Upload File</h2>
        
        <?php if (isset($success)): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?= $success ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="file" class="file-input">
                    <i class="fas fa-file-upload" style="font-size: 24px;"></i><br>
                    Click to select a file or drag and drop here
                </label>
                <input type="file" name="file" id="file" required style="display: none;">
            </div>
            
            <button type="submit" class="submit-btn">
                <i class="fas fa-upload"></i> Upload File
            </button>
        </form>
        
        <div class="links">
            <a href="files.php"><i class="fas fa-folder-open"></i> View My Files</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <script>
        // Display selected file name
        document.getElementById('file').addEventListener('change', function(e) {
            if (this.files.length > 0) {
                const label = document.querySelector('.file-input');
                label.innerHTML = `<i class="fas fa-file" style="font-size: 24px;"></i><br>Selected: ${this.files[0].name}`;
            }
        });
    </script>
</body>
</html>