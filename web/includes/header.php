<?php
if (session_status() == PHP_SESSION_NONE) session_start();
// Note: many pages include 'includes/db.php' before this header; only query if $conn exists
?>
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
            $pending_count = 0;
            if (isset($conn)) {
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND status = 'pending'");
                $stmt->bind_param("i", $_SESSION['user']['id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $pending_count = $result->fetch_assoc()['count'];
                $stmt->close();
            }
            ?>
            <a href="my_orders.php" class="notification-bell">
                <i class="fas fa-bell"></i>
                <?php if($pending_count > 0): ?>
                <span class="notification-count"><?= $pending_count ?></span>
                <?php endif; ?>
            </a>
            <a href="my_orders.php"><?= htmlspecialchars($_SESSION['user']['name']) ?></a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            <?php if(isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'): ?>
                <a href="admin/dashboard.php"><i class="fas fa-user-shield"></i> Admin</a>
            <?php endif; ?>
        <?php else: ?>
            <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
            <a href="signup.php"><i class="fas fa-user-plus"></i> Sign Up</a>
        <?php endif; ?>
    </nav>

    <div class="menu-toggle" onclick="toggleMobileMenu()">
        <span></span>
        <span></span>
        <span></span>
    </div>

    <div class="mobile-menu" id="mobileMenu">
        <div class="close-menu" onclick="toggleMobileMenu()">&times;</div>
        <a href="index.php" onclick="toggleMobileMenu()"><i class="fas fa-home"></i> Home</a>
        <a href="shop.php" onclick="toggleMobileMenu()"><i class="fas fa-store"></i> Shop</a>
        <a href="about.php" onclick="toggleMobileMenu()"><i class="fas fa-info-circle"></i> About</a>
        <a href="contact.php" onclick="toggleMobileMenu()"><i class="fas fa-envelope"></i> Contact</a>
        <?php if(isset($_SESSION['user'])): ?>
            <a href="cart.php" onclick="toggleMobileMenu()"><i class="fas fa-shopping-cart"></i> Cart</a>
            <a href="my_orders.php" onclick="toggleMobileMenu()" class="notification-bell"><i class="fas fa-bell"></i> My Orders
                <?php if($pending_count > 0): ?>
                <span class="notification-count"><?= $pending_count ?></span>
                <?php endif; ?>
            </a>
            <a href="logout.php" onclick="toggleMobileMenu()"><i class="fas fa-sign-out-alt"></i> Logout</a>
        <?php else: ?>
            <a href="login.php" onclick="toggleMobileMenu()"><i class="fas fa-sign-in-alt"></i> Login</a>
            <a href="signup.php" onclick="toggleMobileMenu()"><i class="fas fa-user-plus"></i> Sign Up</a>
        <?php endif; ?>
    </div>
</header>

<script>
function toggleMobileMenu() {
    const mobileMenu = document.getElementById('mobileMenu');
    const menuToggle = document.querySelector('.menu-toggle');
    if (!mobileMenu) return;
    mobileMenu.classList.toggle('active');
    menuToggle.classList.toggle('active');
}
// Close mobile menu when clicking outside
document.addEventListener('click', function(event) {
    const mobileMenu = document.getElementById('mobileMenu');
    const navbar = document.querySelector('.navbar');
    if (!mobileMenu || !navbar) return;
    if (!navbar.contains(event.target) && mobileMenu.classList.contains('active')) {
        toggleMobileMenu();
    }
});
</script>
