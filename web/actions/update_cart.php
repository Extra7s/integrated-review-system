<?php
session_start();
require_once "../includes/db.php";

$id = intval($_POST['id']);
$action = $_POST['action'] ?? 'set';
$qty = intval($_POST['qty'] ?? 1);

if (isset($_SESSION['user'])) {
    $user_id = $_SESSION['user']['id'];
    if ($action == 'increase') {
        $stmt = $conn->prepare("UPDATE cart SET quantity = quantity + 1 WHERE user_id = ? AND artwork_id = ?");
        $stmt->bind_param("ii", $user_id, $id);
        $stmt->execute();
    } elseif ($action == 'decrease') {
        $stmt = $conn->prepare("UPDATE cart SET quantity = GREATEST(quantity - 1, 0) WHERE user_id = ? AND artwork_id = ?");
        $stmt->bind_param("ii", $user_id, $id);
        $stmt->execute();
        // Remove if 0
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND artwork_id = ? AND quantity = 0");
        $stmt->bind_param("ii", $user_id, $id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO cart (user_id, artwork_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = ?");
        $stmt->bind_param("iiii", $user_id, $id, $qty, $qty);
        $stmt->execute();
    }
} else {
    if ($action == 'increase') {
        $_SESSION['cart'][$id] = ($_SESSION['cart'][$id] ?? 0) + 1;
    } elseif ($action == 'decrease') {
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]--;
            if ($_SESSION['cart'][$id] <= 0) {
                unset($_SESSION['cart'][$id]);
            }
        }
    } else {
        $_SESSION['cart'][$id] = $qty;
    }
}

header("Location: ../cart.php");
?>