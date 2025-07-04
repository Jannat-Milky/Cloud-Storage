<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_GET['file'])) {
    $file = basename($_GET['file']);
    $user_id = $_SESSION['user_id'];

    // Fetch file and check ownership
    $stmt = $conn->prepare("SELECT * FROM files WHERE filename = ? AND user_id = ?");
    $stmt->bind_param("si", $file, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $filePath = "uploads/" . $file;

        if (file_exists($filePath)) {
            unlink($filePath); // delete file from folder
        }

        // delete from DB
        $stmt = $conn->prepare("DELETE FROM files WHERE filename = ? AND user_id = ?");
        $stmt->bind_param("si", $file, $user_id);
        $stmt->execute();
    }

    header("Location: files.php");
    exit();
} else {
    header("Location: files.php");
    exit();
}
