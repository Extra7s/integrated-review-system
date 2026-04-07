<?php
session_start();
require_once "../includes/db.php";

$id = $_POST['artwork_id'];
$qty = intval($_POST['qty'] ?? 1);

if (isset($_SESSION['user'])) {
    $user_id = $_SESSION['user']['id'];
    // Insert or update cart in database
    $stmt = $conn->prepare("INSERT INTO cart (user_id, artwork_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?");
    $stmt->bind_param("iiii", $user_id, $id, $qty, $qty);
    $stmt->execute();
} else {
    // Use session
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + $qty;
}

header("Location: ../cart.php");
