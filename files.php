<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    die("Please <a href='login.php'>login</a> first.");
}

$user_id = $_SESSION['user_id'];
$result = $conn->query("SELECT * FROM files WHERE user_id = $user_id");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Files | Cloud Storage</title>
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
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        h2 {
            color: var(--primary);
            text-align: center;
        }
        
        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .file-actions a {
            margin-left: 10px;
            text-decoration: none;
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .view-btn {
            background-color: var(--accent);
            color: white;
        }
        
        .delete-btn {
            background-color: var(--danger);
            color: white;
        }
        
        .upload-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            text-decoration: none;
            color: var(--primary);
            font-weight: 500;
        }
        
        .logout-link {
            display: block;
            text-align: right;
            margin-bottom: 20px;
            color: var(--dark);
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Logout</a>
        
        <h2><i class="fas fa-folder-open"></i> Your Uploaded Files</h2>
        
        <?php
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $file_path = 'uploads/' . htmlspecialchars($row['filename']);
                echo '<div class="file-item">';
                echo '<span>' . htmlspecialchars($row['original_name']) . '</span>';
                echo '<div class="file-actions">';
                echo '<a href="' . $file_path . '" class="view-btn" target="_blank"><i class="fas fa-eye"></i> View</a>';
                echo '<a href="delete.php?file=' . urlencode($row['filename']) . '" class="delete-btn" onclick="return confirm(\'Are you sure?\')"><i class="fas fa-trash"></i> Delete</a>';
                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<p style="text-align: center;">No files uploaded yet.</p>';
        }
        ?>
        
        <a href="upload.php" class="upload-link"><i class="fas fa-cloud-upload-alt"></i> Upload New File</a>
    </div>
</body>
</html>