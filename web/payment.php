<?php
session_start();
require_once "includes/db.php";
require_once "config/khalti.php";

if (!isset($_SESSION['user']) || !isset($_SESSION['pending_order'])) {
    header("Location: login.php");
    exit;
}

$order_id = intval($_GET['order_id'] ?? 0);
$pending_order = $_SESSION['pending_order'];

if ($order_id != $pending_order['order_id']) {
    header("Location: checkout.php");
    exit;
}

// Verify order exists
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $_SESSION['user']['id']);
$stmt->execute();
$res = $stmt->get_result();
$order = $res->fetch_assoc();

if (!$order) {
    header("Location: checkout.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment | ArtfyCanvas</title>
    <link rel="stylesheet" href="assets/css/style_organized.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
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
        <a href="logout.php" onclick="toggleMobileMenu()"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</header>

<section class="payment-section">
    <div class="payment-container">
        <h2>Complete Your Payment</h2>

        <div class="order-summary">
            <h3>Order Summary</h3>
            <p><strong>Order ID:</strong> #<?= $order_id ?></p>
            <p><strong>Total Amount:</strong> Rs. <?= number_format($pending_order['total'], 2) ?></p>
            <p><strong>Customer:</strong> <?= htmlspecialchars($pending_order['customer_name']) ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($pending_order['customer_email']) ?></p>
        </div>


        <div class="payment-methods">
            <h3>Choose Payment Method</h3>

            <div class="payment-option">
                <a href="payment_initiate.php?order_id=<?= $order_id ?>" class="btn khalti-btn">
                    <img src="data:image/png;base64,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" alt="Khalti" class="khalti-logo">
                    Pay with Khalti
                </a>
            </div>

            <div class="payment-info">
                <p><strong>Note:</strong> You will be redirected to Khalti's secure payment page to complete your transaction.</p>
            </div>
        </div>
    </div>
</section>

<script>
// Mobile Menu Functions
function toggleMobileMenu() {
    const mobileMenu = document.getElementById('mobileMenu');
    const menuToggle = document.querySelector('.menu-toggle');

    if (mobileMenu.classList.contains('active')) {
        mobileMenu.classList.remove('active');
        menuToggle.classList.remove('active');
    } else {
        mobileMenu.classList.add('active');
        menuToggle.classList.add('active');
    }
}

// Close mobile menu when clicking outside
document.addEventListener('click', function(event) {
    const mobileMenu = document.getElementById('mobileMenu');
    const menuToggle = document.querySelector('.menu-toggle');
    const navbar = document.querySelector('.navbar');

    if (!navbar.contains(event.target) && mobileMenu.classList.contains('active')) {
        toggleMobileMenu();
    }
});

// Close mobile menu on window resize if desktop size
window.addEventListener('resize', function() {
    const mobileMenu = document.getElementById('mobileMenu');
    if (window.innerWidth > 768 && mobileMenu.classList.contains('active')) {
        toggleMobileMenu();
    }
});
</script>

<!-- ================= FOOTER ================= -->
<footer>
    <div class="footer-content">
        <div class="footer-section">
            <h3>ArtfyCanvas</h3>
            <p>Discover and buy original art from talented artists around the world.</p>
        </div>
        <div class="footer-section">
            <h3>Quick Links</h3>
            <a href="index.php">Home</a>
            <a href="shop.php">Shop</a>
            <a href="about.php">About</a>
            <a href="contact.php">Contact</a>
        </div>
        <div class="footer-section">
            <h3>Support</h3>
            <a href="#">FAQ</a>
            <a href="#">Shipping</a>
            <a href="#">Returns</a>
            <a href="#">Terms of Service</a>
        </div>
        <div class="footer-section">
            <h3>Follow Us</h3>
            <a href="#">Facebook</a>
            <a href="#">Instagram</a>
            <a href="#">Twitter</a>
        </div>
    </div>
    <p>© <?= date("Y") ?> ArtfyCanvas. All Rights Reserved.</p>
</footer>

</body>
</html>