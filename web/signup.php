<?php
require_once "includes/db.php";

$message = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,'user')");
    if ($stmt->execute([$name, $email, $password])) {
        $message = "Account created successfully! <a href='login.php'>Login here</a>";
    } else {
        $message = "Error creating account. Email may already exist.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up | ArtfyCanvas</title>
    <meta name="description" content="Create your ArtfyCanvas account to start collecting and purchasing original artwork from talented artists worldwide.">
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
        <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
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
        <a href="login.php" onclick="toggleMobileMenu()"><i class="fas fa-sign-in-alt"></i> Login</a>
    </div>
</header>

<section class="auth-section">
    <div class="auth-container">
        <h2>Create Your Account</h2>
        <?php if ($message): ?>
            <p class="message <?= strpos($message, 'successfully') !== false ? 'success' : 'error' ?>"><?= $message ?></p>
        <?php endif; ?>
        <form method="POST">
            <label for="name">Name:</label>
            <input name="name" id="name" placeholder="Full Name" required>

            <label for="email">Email:</label>
            <input name="email" type="email" id="email" placeholder="Email" required>

            <label for="password">Password:</label>
            <input name="password" type="password" id="password" placeholder="Password" required>

            <button type="submit" class="btn">Sign Up</button>
        </form>
        <p>Already have an account? <a href="login.php">Login here</a></p>
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
