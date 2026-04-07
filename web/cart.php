<?php
session_start();
require_once "includes/db.php";

$total = 0;
$cart_items = [];

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
} elseif (!empty($_SESSION['cart'])) {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cart | ArtfyCanvas</title>
    <meta name="description" content="Review your shopping cart and proceed to checkout to purchase beautiful artwork from ArtfyCanvas.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style_organized.css">
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
        <?php if(isset($_SESSION['user'])): ?>
            <a href="cart.php"><i class="fas fa-shopping-cart"></i> Cart</a>
            <?php
            // Get pending orders count
            $pending_count = 0;
            $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND status = 'pending'");
            $stmt->bind_param("i", $_SESSION['user']['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $pending_count = $result->fetch_assoc()['count'];
            $stmt->close();
            ?>
            <a href="my_orders.php" class="notification-bell">
                <i class="fas fa-bell"></i>
                <?php if($pending_count > 0): ?>
                <span class="notification-count"><?= $pending_count ?></span>
                <?php endif; ?>
            </a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        <?php else: ?>
            <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
        <?php endif; ?>
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

        <?php if(isset($_SESSION['user'])): ?>
            <a href="cart.php" onclick="toggleMobileMenu()"><i class="fas fa-shopping-cart"></i> Cart</a>
            <a href="my_orders.php" onclick="toggleMobileMenu()" class="notification-bell">
                <i class="fas fa-bell"></i> My Orders
                <?php if($pending_count > 0): ?>
                <span class="notification-count"><?= $pending_count ?></span>
                <?php endif; ?>
            </a>
            <a href="logout.php" onclick="toggleMobileMenu()"><i class="fas fa-sign-out-alt"></i> Logout</a>
        <?php else: ?>
            <a href="login.php" onclick="toggleMobileMenu()"><i class="fas fa-sign-in-alt"></i> Login</a>
        <?php endif; ?>
    </div>
</header>

<section class="cart-section">
    <h2>Your Cart</h2>

    <?php if(empty($cart_items)): ?>
        <p>Your cart is empty.</p>
    <?php else: ?>
        <div class="cart-items">
            <?php foreach ($cart_items as $item): ?>
                <div class="cart-item">
                    <img src="assets/images/<?= $item['artwork']['image'] ?>" alt="<?= $item['artwork']['title'] ?>">
                    <div class="item-details">
                        <h3><?= $item['artwork']['title'] ?></h3>
                        <p>Artist: <?= $item['artwork']['artist'] ?></p>
                        <div class="quantity-controls">
                            <form method="POST" action="actions/update_cart.php" class="update-cart-form">
                                <input type="hidden" name="id" value="<?= $item['artwork']['id'] ?>">
                                <button type="submit" name="action" value="decrease">-</button>
                                <input type="number" name="qty" value="<?= $item['qty'] ?>" min="1" readonly>
                                <button type="submit" name="action" value="increase">+</button>
                            </form>
                        </div>
                        <p>Price: Rs. <?= number_format($item['subtotal'], 2) ?></p>
                    </div>
                    <button class="remove-btn" onclick="window.location.href='actions/remove_from_cart.php?id=<?= $item['artwork']['id'] ?>'">Remove</button>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="cart-total">
            <h3>Total: <span class="total-price">Rs. <?= number_format($total, 2) ?></span></h3>
            <a href="checkout.php" class="checkout-btn">Proceed to Checkout</a>
        </div>
    <?php endif; ?>
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
