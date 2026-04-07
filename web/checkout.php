<?php
session_start();
require_once "includes/db.php";

if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user']['id'];

$cart_items = [];
$total = 0;

if (isset($_SESSION['user'])) {
    $user_id = $_SESSION['user']['id'];
    $stmt = $conn->prepare("SELECT c.quantity, a.* FROM cart c JOIN artworks a ON c.artwork_id = a.id WHERE c.user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $qty = $row['quantity'];
        $subtotal = $row['price'] * $qty;
        $total += $subtotal;
        $cart_items[] = ['artwork' => $row, 'qty' => $qty, 'subtotal' => $subtotal];
    }
} else {
    foreach ($_SESSION['cart'] as $id => $qty) {
        $stmt = $conn->prepare("SELECT * FROM artworks WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $art = $res->fetch_assoc();
        $subtotal = $art['price'] * $qty;
        $total += $subtotal;
        $cart_items[] = ['artwork' => $art, 'qty' => $qty, 'subtotal' => $subtotal];
    }
}

if (empty($cart_items)) {
    header("Location: cart.php");
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $address = $_POST['address'];
    $phone = $_POST['phone'];
    $customer_name = $_POST['customer_name'];
    $customer_email = $_POST['customer_email'];

    // Create order
    $stmt = $conn->prepare("INSERT INTO orders (user_id, total, address, phone, payment_method, payment_status, created_at) VALUES (?, ?, ?, ?, 'khalti', 'pending', NOW())");
    $stmt->bind_param("idss", $user_id, $total, $address, $phone);
    $stmt->execute();
    $order_id = $conn->insert_id;

    // Insert order items
    foreach ($cart_items as $item) {
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, artwork_id, quantity, price) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiid", $order_id, $item['artwork']['id'], $item['qty'], $item['artwork']['price']);
        $stmt->execute();
    }

    // Store order details in session for Khalti callback
    $_SESSION['pending_order'] = [
        'order_id' => $order_id,
        'total' => $total,
        'customer_name' => $customer_name,
        'customer_email' => $customer_email,
        'customer_phone' => $phone
    ];

    // Redirect to Khalti payment initiation
    header("Location: payment.php?order_id=$order_id");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Checkout | ArtfyCanvas</title>
    <meta name="description" content="Complete your purchase and securely checkout your selected artwork from ArtfyCanvas.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

<section class="checkout-section">
    <h2>Checkout</h2>

    <?php if ($message): ?>
        <p class="message"><?= $message ?></p>
    <?php endif; ?>

    <div class="checkout-container">
        <div class="order-summary">
            <h3>Order Summary</h3>
            <?php foreach ($cart_items as $item): ?>
                <div class="order-item">
                    <img src="assets/images/<?= $item['artwork']['image'] ?>" alt="<?= $item['artwork']['title'] ?>">
                    <div class="order-item-details">
                        <h4><?= $item['artwork']['title'] ?></h4>
                        <p>Artist: <?= $item['artwork']['artist'] ?></p>
                        <p>Quantity: <?= $item['qty'] ?></p>
                        <p>$<?= number_format($item['subtotal'], 2) ?></p>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="order-total">
                <p>Total: <strong>$<?= number_format($total, 2) ?></strong></p>
            </div>
        </div>

        <div class="shipping-form">
            <h3>Shipping & Payment Information</h3>
            <form method="POST">
                <label for="customer_name">Full Name:</label>
                <input type="text" name="customer_name" id="customer_name" required value="<?= $_SESSION['user']['name'] ?? '' ?>">

                <label for="customer_email">Email:</label>
                <input type="email" name="customer_email" id="customer_email" required value="<?= $_SESSION['user']['email'] ?? '' ?>">

                <label for="address">Address:</label>
                <textarea name="address" id="address" required></textarea>

                <label for="phone">Phone:</label>
                <input type="text" name="phone" id="phone" required>

                <button type="submit" class="btn">Proceed to Payment</button>
            </form>
        </div>
    </div>
</section>

<!-- ================= FOOTER ================= -->
<footer>
    <div class="footer-content">
        <div class="footer-section">
            <h3><i class="fas fa-palette"></i> ArtfyCanvas</h3>
            <p>Discover and buy original art from talented artists around the world.</p>
        </div>
        <div class="footer-section">
            <h3><i class="fas fa-link"></i> Quick Links</h3>
            <a href="index.php"><i class="fas fa-home"></i> Home</a>
            <a href="shop.php"><i class="fas fa-store"></i> Shop</a>
            <a href="about.php"><i class="fas fa-info-circle"></i> About</a>
            <a href="contact.php"><i class="fas fa-envelope"></i> Contact</a>
        </div>
        <div class="footer-section">
            <h3><i class="fas fa-question-circle"></i> Support</h3>
            <a href="#"><i class="fas fa-question"></i> FAQ</a>
            <a href="#"><i class="fas fa-shipping-fast"></i> Shipping</a>
            <a href="#"><i class="fas fa-undo"></i> Returns</a>
            <a href="#"><i class="fas fa-file-contract"></i> Terms of Service</a>
        </div>
        <div class="footer-section">
            <h3><i class="fas fa-share-alt"></i> Follow Us</h3>
            <a href="#"><i class="fab fa-facebook"></i> Facebook</a>
            <a href="#"><i class="fab fa-instagram"></i> Instagram</a>
            <a href="#"><i class="fab fa-twitter"></i> Twitter</a>
        </div>
    </div>
    <p>© <?= date("Y") ?> ArtfyCanvas. All Rights Reserved.</p>
</footer>

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

</body>
</html>