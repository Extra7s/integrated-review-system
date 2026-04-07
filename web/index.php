<?php
session_start();
require_once "includes/db.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ArtfyCanvas | Buy Original Art</title>
    <meta name="description" content="Discover and buy original artworks from talented artists worldwide. Premium art collection with secure checkout.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style_organized.css">

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome Icons -->
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
            <a href="logout.php" onclick="toggleMobileMenu()"><i class="fas fa-sign-out-alt"></i> Logout</a>
        <?php else: ?>
            <a href="login.php" onclick="toggleMobileMenu()"><i class="fas fa-sign-in-alt"></i> Login</a>
        <?php endif; ?>
    </div>
</header>

<!-- ================= HERO SECTION ================= -->
<section class="hero">
    <div class="hero-text">
        <h1>Discover & Buy Original Art</h1>
        <p>Support independent artists. Own timeless creativity.</p>
        <a href="#gallery" class="btn">Explore Art</a>
    </div>
</section>

<!-- ================= CATEGORIES ================= -->
<section class="categories">
    <h2>Browse Categories</h2>

    <div class="category-grid">
        <?php
        $catResult = $conn->query("SELECT * FROM categories LIMIT 6");
        while ($cat = $catResult->fetch_assoc()):
        ?>
            <a href="shop.php?category=<?= $cat['id'] ?>" class="category-card">
                <?= htmlspecialchars($cat['name']) ?>
            </a>
        <?php endwhile; ?>
    </div>
</section>

<!-- ================= ARTWORK GALLERY ================= -->
<section class="gallery" id="gallery">
    <h2>Featured Artworks</h2>

    <div class="art-grid">
        <?php
        $artResult = $conn->query("SELECT * FROM artworks ORDER BY id DESC LIMIT 12");

        while ($art = $artResult->fetch_assoc()):
            $imagePath = "assets/images/" . $art['image'];
            if (!file_exists($imagePath)) {
                $imagePath = "assets/images/default.jpg";
            }
        ?>
            <div class="art-card">
                <img src="<?= $imagePath ?>" alt="<?= htmlspecialchars($art['title']) ?>">

                <div class="art-info">
                    <h3><?= htmlspecialchars($art['title']) ?></h3>
                    <p class="artist"><?= htmlspecialchars($art['artist']) ?></p>
                    <p class="price">Rs. <?= number_format($art['price'], 2) ?></p>
                    <a href="product.php?id=<?= $art['id'] ?>" class="btn-sm">View Details</a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</section>

<!-- ================= BEST SELLERS ================= -->
<section class="best-sellers">
    <h2>Best Sellers</h2>

    <div class="art-grid">
        <?php
        $bestResult = $conn->query("SELECT * FROM artworks ORDER BY price DESC LIMIT 6");

        while ($art = $bestResult->fetch_assoc()):
            $imagePath = "assets/images/" . $art['image'];
            if (!file_exists($imagePath)) {
                $imagePath = "assets/images/default.jpg";
            }
        ?>
            <div class="art-card">
                <img src="<?= $imagePath ?>" alt="<?= htmlspecialchars($art['title']) ?>">

                <div class="art-info">
                    <h3><?= htmlspecialchars($art['title']) ?></h3>
                    <p class="artist"><?= htmlspecialchars($art['artist']) ?></p>
                    <p class="price">Rs. <?= number_format($art['price'], 2) ?></p>
                    <a href="product.php?id=<?= $art['id'] ?>" class="btn-sm">View Details</a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</section>

<!-- ================= NEW ARRIVALS ================= -->
<section class="new-arrivals">
    <h2>New Arrivals</h2>

    <div class="art-grid">
        <?php
        $newResult = $conn->query("SELECT * FROM artworks ORDER BY id DESC LIMIT 6");

        while ($art = $newResult->fetch_assoc()):
            $imagePath = "assets/images/" . $art['image'];
            if (!file_exists($imagePath)) {
                $imagePath = "assets/images/default.jpg";
            }
        ?>
            <div class="art-card">
                <img src="<?= $imagePath ?>" alt="<?= htmlspecialchars($art['title']) ?>">

                <div class="art-info">
                    <h3><?= htmlspecialchars($art['title']) ?></h3>
                    <p class="artist"><?= htmlspecialchars($art['artist']) ?></p>
                    <p class="price">Rs. <?= number_format($art['price'], 2) ?></p>
                    <a href="product.php?id=<?= $art['id'] ?>" class="btn-sm">View Details</a>
                </div>
            </div>
        <?php endwhile; ?>
    </div>
</section>

<!-- ================= WHY US ================= -->
<section class="why-us">
    <h2>Why Choose ArtfyCanvas?</h2>

    <div class="features">
        <div class="feature">
            <i class="fas fa-palette"></i>
            <span>100% Original Art</span>
        </div>
        <div class="feature">
            <i class="fas fa-credit-card"></i>
            <span>Secure Khalti Payment</span>
        </div>
        <div class="feature">
            <i class="fas fa-shipping-fast"></i>
            <span>Safe & Fast Delivery</span>
        </div>
        <div class="feature">
            <i class="fas fa-star"></i>
            <span>Trusted Artists</span>
        </div>
        <div class="feature">
            <i class="fas fa-shield-alt"></i>
            <span>Quality Guarantee</span>
        </div>
        <div class="feature">
            <i class="fas fa-headset"></i>
            <span>24/7 Customer Support</span>
        </div>
    </div>
</section>

<!-- ================= TESTIMONIALS ================= -->
<section class="testimonials">
    <h2>What Our Customers Say</h2>

    <div class="testimonial-grid">
        <div class="testimonial">
            <p>"Amazing collection of art! Found the perfect piece for my living room."</p>
            <cite>- Sarah Johnson</cite>
        </div>
        <div class="testimonial">
            <p>"Fast delivery and excellent quality. Highly recommend ArtfyCanvas."</p>
            <cite>- Michael Chen</cite>
        </div>
        <div class="testimonial">
            <p>"Supporting local artists has never been easier. Love the platform!"</p>
            <cite>- Priya Sharma</cite>
        </div>
    </div>
</section>
    </div>
</section>

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

<script>
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
