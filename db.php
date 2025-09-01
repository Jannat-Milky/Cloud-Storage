<?php
$host = "localhost";
$user = "root";
$pass = ""; // XAMPP default has no password
$db = "file_repo";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
<<<<<<< HEAD
=======
?>
>>>>>>> 0e0bc586e62e850a8ff3052f88b6cc39b8523735
