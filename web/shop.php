<?php
session_start();
require_once "includes/db.php";

$category = isset($_GET['category']) ? intval($_GET['category']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$query = "SELECT * FROM artworks WHERE 1";
$params = [];
$types = '';

if ($category > 0) {
    $query .= " AND category_id = ?";
    $params[] = $category;
    $types .= 'i';
}
if ($search) {
    $query .= " AND (title LIKE ? OR artist LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}
$query .= " ORDER BY id DESC";

$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shop | ArtfyCanvas</title>
    <meta name="description" content="Browse our curated collection of original artworks. Filter by category and search for your favorite artists.">
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
                $pending_count = 0;
                if (isset($conn)) {
                    $pstmt = $conn->prepare("SELECT COUNT(*) as count FROM orders WHERE user_id = ? AND status = 'pending'");
                    $pstmt->bind_param("i", $_SESSION['user']['id']);
                    $pstmt->execute();
                    $pres = $pstmt->get_result();
                    $pending_count = $pres->fetch_assoc()['count'] ?? 0;
                    $pstmt->close();
                }
            ?>
            <a href="my_orders.php" class="notification-bell"><i class="fas fa-bell"></i>
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

<section class="shop-section">
    <!-- Shop Header -->
    <div class="shop-header">
        <div class="shop-title">
            <h1>
                <i class="fas fa-store"></i>
                Art Gallery
            </h1>
            <p>Discover unique artworks from talented artists around the world</p>
        </div>
        <div class="shop-stats">
            <div class="stat">
                <i class="fas fa-paint-brush"></i>
                <span>500+ Artworks</span>
            </div>
            <div class="stat">
                <i class="fas fa-user"></i>
                <span>100+ Artists</span>
            </div>
            <div class="stat">
                <i class="fas fa-star"></i>
                <span>Premium Quality</span>
            </div>
        </div>
    </div>

    <!-- Advanced Filters -->
    <div class="filters-container">
        <div class="filters-header">
            <h3>
                <i class="fas fa-filter"></i>
                Filter & Search
            </h3>
            <button type="button" class="filter-toggle" onclick="toggleFilters()">
                <i class="fas fa-sliders-h"></i>
                Filters
            </button>
        </div>

        <div class="filters-content" id="filters-content">
            <form method="GET" action="shop.php" class="filters-form">
                <div class="filter-group">
                    <label for="category">
                        <i class="fas fa-tag"></i>
                        Category
                    </label>
                    <select name="category" id="category">
                        <option value="">All Categories</option>
                        <?php
                        $catResult = $conn->query("SELECT * FROM categories");
                        while ($cat = $catResult->fetch_assoc()):
                        ?>
                            <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="search">
                        <i class="fas fa-search"></i>
                        Search
                    </label>
                    <input type="text" name="search" id="search" placeholder="Search by title or artist..." value="<?= htmlspecialchars($search) ?>">
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-search"></i>
                        Apply Filters
                    </button>
                    <a href="shop.php" class="btn-secondary">
                        <i class="fas fa-times"></i>
                        Clear All
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Header -->
    <div class="results-header">
        <div class="results-count">
            <i class="fas fa-list"></i>
            <span>Showing <?= $result->num_rows ?> artworks</span>
        </div>
        <div class="view-options">
            <button class="view-btn active" onclick="setView('grid')">
                <i class="fas fa-th"></i>
            </button>
            <button class="view-btn" onclick="setView('list')">
                <i class="fas fa-list-ul"></i>
            </button>
        </div>
    </div>

    <!-- Products Grid -->
    <div class="art-grid" id="products-grid">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($art = $result->fetch_assoc()):
                $imagePath = "assets/images/" . $art['image'];
                if (!file_exists($imagePath)) {
                    $imagePath = "assets/images/default.jpg";
                }
            ?>
                <div class="art-card">
                    <div class="art-image">
                        <img src="<?= $imagePath ?>" alt="<?= htmlspecialchars($art['title']) ?>" loading="lazy">
                        <div class="art-overlay">
                            <a href="product.php?id=<?= $art['id'] ?>" class="view-details-btn">
                                <i class="fas fa-eye"></i>
                                View Details
                            </a>
                        </div>
                        <div class="art-actions">
                            <button class="action-btn" onclick="quickView(<?= $art['id'] ?>)">
                                <i class="fas fa-search-plus"></i>
                            </button>
                            <button class="action-btn" onclick="addToWishlist(<?= $art['id'] ?>)">
                                <i class="far fa-heart"></i>
                            </button>
                        </div>
                    </div>
                    <div class="art-info">
                        <h3>
                            <a href="product.php?id=<?= $art['id'] ?>">
                                <?= htmlspecialchars($art['title']) ?>
                            </a>
                        </h3>
                        <p class="artist">
                            <i class="fas fa-user"></i>
                            <?= htmlspecialchars($art['artist']) ?>
                        </p>
                        <div class="art-footer">
                            <p class="price">Rs. <?= number_format($art['price'], 2) ?></p>
                            <form action="actions/add_to_cart.php" method="POST" class="quick-add">
                                <input type="hidden" name="artwork_id" value="<?= $art['id'] ?>">
                                <input type="hidden" name="qty" value="1">
                                <button type="submit" class="quick-add-btn" title="Add to Cart">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="no-results">
                <div class="no-results-content">
                    <i class="fas fa-search"></i>
                    <h3>No artworks found</h3>
                    <p>Try adjusting your search criteria or browse all categories.</p>
                    <a href="shop.php" class="btn-primary">
                        <i class="fas fa-store"></i>
                        Browse All Artworks
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ================= FOOTER ================= -->
<footer>
    <div class="footer-content">
        <div class="footer-section">
            <h3>
                <i class="fas fa-palette"></i>
                ArtfyCanvas
            </h3>
            <p>Discover and buy original art from talented artists around the world.</p>
        </div>
        <div class="footer-section">
            <h3>
                <i class="fas fa-link"></i>
                Quick Links
            </h3>
            <a href="index.php">
                <i class="fas fa-home"></i>
                Home
            </a>
            <a href="shop.php">
                <i class="fas fa-store"></i>
                Shop
            </a>
            <a href="about.php">
                <i class="fas fa-info-circle"></i>
                About
            </a>
            <a href="contact.php">
                <i class="fas fa-envelope"></i>
                Contact
            </a>
        </div>
        <div class="footer-section">
            <h3>
                <i class="fas fa-life-ring"></i>
                Support
            </h3>
            <a href="#">
                <i class="fas fa-question-circle"></i>
                FAQ
            </a>
            <a href="#">
                <i class="fas fa-shipping-fast"></i>
                Shipping
            </a>
            <a href="#">
                <i class="fas fa-undo"></i>
                Returns
            </a>
            <a href="#">
                <i class="fas fa-file-contract"></i>
                Terms of Service
            </a>
        </div>
        <div class="footer-section">
            <h3>
                <i class="fas fa-share-alt"></i>
                Follow Us
            </h3>
            <a href="#">
                <i class="fab fa-facebook"></i>
                Facebook
            </a>
            <a href="#">
                <i class="fab fa-instagram"></i>
                Instagram
            </a>
            <a href="#">
                <i class="fab fa-twitter"></i>
                Twitter
            </a>
        </div>
    </div>
    <p>© <?= date("Y") ?> ArtfyCanvas. All Rights Reserved.</p>
</footer>

<script>
function toggleFilters() {
    const filters = document.getElementById('filters-content');
    const toggleBtn = document.querySelector('.filter-toggle');

    if (filters.style.display === 'none' || filters.style.display === '') {
        filters.style.display = 'block';
        toggleBtn.innerHTML = '<i class="fas fa-times"></i> Hide Filters';
    } else {
        filters.style.display = 'none';
        toggleBtn.innerHTML = '<i class="fas fa-sliders-h"></i> Filters';
    }
}

function setView(viewType) {
    const grid = document.getElementById('products-grid');
    const buttons = document.querySelectorAll('.view-btn');

    buttons.forEach(btn => btn.classList.remove('active'));

    if (viewType === 'grid') {
        grid.className = 'art-grid';
        buttons[0].classList.add('active');
    } else {
        grid.className = 'art-list';
        buttons[1].classList.add('active');
    }
}

function quickView(artworkId) {
    // Placeholder for quick view modal
    window.location.href = 'product.php?id=' + artworkId;
}

function addToWishlist(artworkId) {
    // Placeholder for wishlist functionality
    alert('Wishlist feature coming soon!');
}

// Initialize filters state on mobile
if (window.innerWidth <= 768) {
    document.getElementById('filters-content').style.display = 'none';
}

// Lazy loading for images
document.addEventListener('DOMContentLoaded', function() {
    const images = document.querySelectorAll('img[loading="lazy"]');
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.src; // Trigger load
                observer.unobserve(img);
            }
        });
    });

    images.forEach(img => imageObserver.observe(img));
});

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