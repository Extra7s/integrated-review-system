<?php
$host = "localhost";
$user = "root";
$pass = "root";
$db   = "artstore";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>
