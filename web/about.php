<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>About Us | ArtfyCanvas</title>
    <meta name="description" content="Learn about ArtfyCanvas - your premier destination for original artworks from talented artists worldwide.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style_organized.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php session_start(); ?>

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
            require_once "includes/db.php";
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

<section class="about-hero">
    <div class="hero-content">
        <h1>About ArtfyCanvas</h1>
        <p>Connecting Artists with Art Lovers Worldwide</p>
    </div>
</section>

<section class="about-section">
    <div class="about-intro">
        <h2>Our Story</h2>
        <p>Founded in 2020, ArtfyCanvas emerged from a simple belief: everyone deserves to experience the transformative power of original art. What started as a small online gallery has grown into a thriving community of artists, collectors, and art enthusiasts from around the globe.</p>
        <p>We curate exceptional artworks from emerging and established artists, ensuring each piece tells a unique story and brings lasting beauty to your space.</p>
    </div>
    
    <div class="stats-section">
        <div class="stat">
            <h3>500+</h3>
            <p>Artists Featured</p>
        </div>
        <div class="stat">
            <h3>10,000+</h3>
            <p>Artworks Sold</p>
        </div>
        <div class="stat">
            <h3>50+</h3>
            <p>Countries Reached</p>
        </div>
        <div class="stat">
            <h3>4.9/5</h3>
            <p>Customer Rating</p>
        </div>
    </div>
</section>

<section class="mission-vision">
    <div class="container">
        <div class="mission">
            <h2>Our Mission</h2>
            <p>To democratize art ownership by making original artworks accessible to everyone, while providing a platform for emerging and established artists to showcase their creativity and reach a global audience.</p>
        </div>
        <div class="vision">
            <h2>Our Vision</h2>
            <p>A world where art is an integral part of every home and workplace, fostering creativity, cultural appreciation, and emotional connection through beautiful, meaningful artworks.</p>
        </div>
    </div>
</section>

<section class="why-choose-us">
    <h2>Why Choose ArtfyCanvas?</h2>
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon"><i class="fas fa-palette"></i></div>
            <h3>Original Artworks</h3>
            <p>Every piece is handcrafted by talented artists, ensuring authenticity and uniqueness.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fas fa-user-friends"></i></div>
            <h3>Support Local Artists</h3>
            <p>Directly support independent artists and help them build sustainable creative careers.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fas fa-shipping-fast"></i></div>
            <h3>Worldwide Shipping</h3>
            <p>Careful packaging and global shipping to ensure your artwork arrives safely.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fas fa-credit-card"></i></div>
            <h3>Secure Payments</h3>
            <p>Safe and secure payment processing with multiple payment options.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
            <h3>Authenticity Guarantee</h3>
            <p>Every artwork comes with a certificate of authenticity and provenance.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fas fa-headset"></i></div>
            <h3>24/7 Customer Support</h3>
            <p>Dedicated support team ready to assist with any questions or concerns.</p>
        </div>
    </div>
</section>

<section class="team-section">
    <h2>Meet Our Team</h2>
    <p>The passionate individuals behind ArtfyCanvas who make it all possible.</p>
    <div class="team">
        <div class="team-member">
            <div class="member-avatar"><i class="fas fa-circle-user"></i></div>
            <h4>Sarah Johnson</h4>
            <p class="member-role">Founder & Chief Art Curator</p>
            <p class="member-bio">With over 15 years in the art world, Sarah curates our collection and ensures every piece meets our standards of excellence.</p>
        </div>
        <div class="team-member">
            <div class="member-avatar"><i class="fas fa-circle-user"></i></div>
            <h4>Mike Chen</h4>
            <p class="member-role">Operations Manager</p>
            <p class="member-bio">Mike oversees our logistics and ensures smooth operations from artist onboarding to customer delivery.</p>
        </div>
        <div class="team-member">
            <div class="member-avatar"><i class="fas fa-circle-user"></i></div>
            <h4>Emily Davis</h4>
            <p class="member-role">Lead Developer</p>
            <p class="member-bio">Emily builds and maintains our platform, ensuring a seamless experience for artists and collectors alike.</p>
        </div>
        <div class="team-member">
            <div class="member-avatar"><i class="fas fa-circle-user"></i></div>
            <h4>Alex Rodriguez</h4>
            <p class="member-role">Artist Relations</p>
            <p class="member-bio">Alex works directly with artists to help them showcase their work and grow their careers on our platform.</p>
        </div>
    </div>
</section>

<section class="testimonials">
    <h2>What Our Customers Say</h2>
    <div class="testimonial-grid">
        <div class="testimonial">
            <p>"ArtfyCanvas helped me discover incredible artists I never would have found otherwise. The quality and customer service are outstanding."</p>
            <cite>- Jennifer M., New York</cite>
        </div>
        <div class="testimonial">
            <p>"As an artist, ArtfyCanvas has been instrumental in connecting me with collectors worldwide. Their platform is professional and supportive."</p>
            <cite>- David K., London</cite>
        </div>
        <div class="testimonial">
            <p>"The authenticity guarantee gives me peace of mind when purchasing art. I've bought several pieces and each one has been perfect."</p>
            <cite>- Maria S., Barcelona</cite>
        </div>
    </div>
</section>

<section class="cta-section">
    <h2>Ready to Discover Your Next Favorite Artwork?</h2>
    <p>Join thousands of art lovers who have found their perfect piece through ArtfyCanvas.</p>
    <a href="shop.php" class="btn">Browse Artworks</a>
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