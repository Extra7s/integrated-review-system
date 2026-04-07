<?php
session_start();
require_once "../includes/db.php";

$id = intval($_GET['id']);

if (isset($_SESSION['user'])) {
    $user_id = $_SESSION['user']['id'];
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND artwork_id = ?");
    $stmt->bind_param("ii", $user_id, $id);
    $stmt->execute();
} else {
    unset($_SESSION['cart'][$id]);
}

header("Location: ../cart.php");
?>