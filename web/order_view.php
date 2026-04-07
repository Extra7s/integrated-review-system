<?php
session_start();
require_once "includes/db.php";

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user']['id'];
$order_id = intval($_GET['id'] ?? 0);

if (!$order_id) {
    header("Location: my_orders.php");
    exit();
}

// Get order details
$stmt = $conn->prepare("
    SELECT o.*, u.name, u.email FROM orders o
    LEFT JOIN users u ON o.user_id = u.id
    WHERE o.id = ? AND o.user_id = ?
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order_result = $stmt->get_result();
$order = $order_result->fetch_assoc();

if (!$order) {
    header("Location: my_orders.php");
    exit();
}

// Get order items
$item_stmt = $conn->prepare("
    SELECT oi.*, a.title, a.image, a.artist 
    FROM order_items oi
    JOIN artworks a ON oi.artwork_id = a.id
    WHERE oi.order_id = ?
    ORDER BY oi.id
");
$item_stmt->bind_param("i", $order_id);
$item_stmt->execute();
$items_result = $item_stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order #<?= $order_id ?> - ArtfyCanvas</title>
    <link rel="stylesheet" href="assets/css/style_organized.css">
    <link rel="stylesheet" href="assets/css/order_view.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<!-- ================= NAVBAR ================= -->
<header class="navbar">
    <div class="logo">
        <i class="fas fa-palette"></i>
        ArtfyCanvas
    </div>
    <nav>
        <a href="index.php"><i class="fas fa-home"></i> Home</a>
        <a href="shop.php"><i class="fas fa-store"></i> Shop</a>
        <a href="about.php"><i class="fas fa-info-circle"></i> About</a>
        <a href="contact.php"><i class="fas fa-envelope"></i> Contact</a>
        <a href="cart.php"><i class="fas fa-shopping-cart"></i> Cart</a>
        <a href="my_orders.php"><i class="fas fa-history"></i> My Orders</a>
        <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </nav>

    <!-- Mobile Menu Toggle -->
    <div class="menu-toggle" onclick="toggleMobileMenu()">
        <span></span>
        <span></span>
        <span></span>
    </div>

    <!-- Mobile Menu Overlay -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="close-menu" onclick="toggleMobileMenu()">&times;</div>
        <a href="index.php" onclick="toggleMobileMenu()"><i class="fas fa-home"></i> Home</a>
        <a href="shop.php" onclick="toggleMobileMenu()"><i class="fas fa-store"></i> Shop</a>
        <a href="about.php" onclick="toggleMobileMenu()"><i class="fas fa-info-circle"></i> About</a>
        <a href="contact.php" onclick="toggleMobileMenu()"><i class="fas fa-envelope"></i> Contact</a>
        <a href="cart.php" onclick="toggleMobileMenu()"><i class="fas fa-shopping-cart"></i> Cart</a>
        <a href="my_orders.php" onclick="toggleMobileMenu()"><i class="fas fa-history"></i> My Orders</a>
        <a href="logout.php" onclick="toggleMobileMenu()"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</header>

<!-- ================= ORDER DETAILS ================= -->
<div class="order-detail-container">
    <div class="order-header">
        <h1>Order #<?= $order['id'] ?></h1>
        <div class="order-meta">
            <div><i class="fas fa-calendar"></i> <?= date('F d, Y H:i', strtotime($order['created_at'])) ?></div>
            <div>
                <span class="status-badge status-<?= $order['status'] ?>">
                    <?= ucfirst($order['status']) ?>
                </span>
                <span class="payment-status payment-<?= $order['payment_status'] ?>">
                    <?= ucfirst($order['payment_status']) ?>
                </span>
            </div>
        </div>
    </div>

    <div class="order-content">
        <!-- Order Items -->
        <div class="order-items-section">
            <h3><i class="fas fa-box"></i> Order Items (<?= $items_result->num_rows ?>)</h3>
            
            <?php while($item = $items_result->fetch_assoc()): 
                $image_path = "assets/images/" . $item['image'];
                if (!file_exists($image_path)) {
                    $image_path = "assets/images/default.jpg";
                }
            ?>
                <div class="order-item">
                    <img src="<?= $image_path ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="item-image">
                    <div class="item-details">
                        <div class="item-title"><?= htmlspecialchars($item['title']) ?></div>
                        <div class="item-artist">by <?= htmlspecialchars($item['artist']) ?></div>
                        <div class="item-price-qty">
                            <span>Rs. <?= number_format($item['price'], 2) ?> × <?= $item['quantity'] ?></span>
                        </div>
                        <div class="item-subtotal">
                            Rs. <?= number_format($item['quantity'] * $item['price'], 2) ?>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>

        <!-- Order Summary -->
        <div class="order-summary-section">
            <h3><i class="fas fa-receipt"></i> Order Summary</h3>

            <div class="shipping-info">
                <h4>Shipping Information</h4>
                <div class="info-item">
                    <div class="info-label">Address</div>
                    <div><?= nl2br(htmlspecialchars($order['address'])) ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Phone</div>
                    <div><?= htmlspecialchars($order['phone']) ?></div>
                </div>
                <?php if($order['khalti_token']): ?>
                <div class="info-item">
                    <div class="info-label">Transaction ID</div>
                    <div><?= htmlspecialchars($order['khalti_token']) ?></div>
                </div>
                <?php endif; ?>
            </div>

            <div class="summary-row">
                <span>Subtotal</span>
                <span>Rs. <?= number_format($order['total'], 2) ?></span>
            </div>
            <div class="summary-row">
                <span>Shipping</span>
                <span>Free</span>
            </div>
            <div class="summary-row total">
                <span>Total</span>
                <span>Rs. <?= number_format($order['total'], 2) ?></span>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="action-buttons">
        <a href="my_orders.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Orders
        </a>
        <?php if($order['status'] === 'completed' && $order['payment_status'] === 'paid'): ?>
        <a href="shop.php" class="btn btn-primary">
            <i class="fas fa-shopping-bag"></i> Continue Shopping
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ================= FOOTER ================= -->
<footer>
    <div class="footer-content">
        <div class="footer-section">
            <h3>ArtfyCanvas</h3>
            <p>Discover and buy original art from talented artists around the world.</p>
        </div>
        <div class="footer-section">
            <h3>Quick Links</h3>
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="shop.php">Shop</a></li>
                <li><a href="about.php">About Us</a></li>
                <li><a href="contact.php">Contact</a></li>
            </ul>
        </div>
        <div class="footer-section">
            <h3>Contact</h3>
            <p>Email: info@artfycanvas.com</p>
            <p>Phone: +977 1234567890</p>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; 2024 ArtfyCanvas. All rights reserved.</p>
    </div>
</footer>

<script>
function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    menu.style.display = menu.style.display === 'flex' ? 'none' : 'flex';
}
</script>

</body>
</html>
