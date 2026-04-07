<?php
include 'admin_guard.php';

$stmt = $conn->prepare("SELECT * FROM messages ORDER BY created_at DESC");
$stmt->execute();
$r = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
<title>Messages - Admin</title>
<link rel="stylesheet" href="../assets/css/style_organized.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

<header class="navbar">
    <div class="logo">ArtfyCanvas - Admin</div>
    <nav>
        <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="products.php"><i class="fas fa-box"></i> Products</a>
        <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
        <a href="messages.php"><i class="fas fa-envelope"></i> Messages</a>
        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>
</header>

<section class="admin-section">
    <h2>Contact Messages</h2>

    <div class="admin-content">
        <?php while($m = $r->fetch_assoc()){ ?>
        <div class="admin-item">
            <h3>From: <?= htmlspecialchars($m['name']) ?> (<?= htmlspecialchars($m['email']) ?>)</h3>
            <p><strong>Date:</strong> <?= $m['created_at'] ?></p>
            <p><strong>Message:</strong> <?= nl2br(htmlspecialchars($m['message'])) ?></p>
        </div>
        <?php } ?>
    </div>
</section>

<div class="center-back">
    <a href="dashboard.php" class="btn">Back to Dashboard</a>
</div>


</body>
</html>