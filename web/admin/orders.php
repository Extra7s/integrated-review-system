<?php
include 'admin_guard.php';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = intval($_POST['order_id']);
    $status = $_POST['status'];

    $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $order_id);
    $stmt->execute();
    $stmt->close();

    header("Location: orders.php");
    exit();
}

// Get orders with user details
$stmt = $conn->prepare("
SELECT o.*, u.name, u.email FROM orders o
LEFT JOIN users u ON o.user_id = u.id
ORDER BY o.created_at DESC");
$stmt->execute();
$orders_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>View Orders - Admin</title>
    <link rel="stylesheet" href="../assets/css/style_organized.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .order-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }

        .order-title {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }

        .order-meta {
            display: flex;
            gap: 20px;
            font-size: 14px;
            color: #666;
        }

        .order-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .order-info {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .order-status {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            text-transform: uppercase;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-completed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }

        .payment-pending { background: #fff3cd; color: #856404; }
        .payment-paid { background: #d4edda; color: #155724; }
        .payment-failed { background: #f8d7da; color: #721c24; }

        .order-items {
            margin-top: 20px;
        }

        .order-items h4 {
            margin-bottom: 15px;
            color: #333;
            font-size: 16px;
        }

        .item-row {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .item-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }

        .item-details {
            flex: 1;
        }

        .item-details h5 {
            margin: 0 0 5px 0;
            font-size: 14px;
            color: #333;
        }

        .item-details p {
            margin: 0;
            font-size: 12px;
            color: #666;
        }

        .status-form {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 15px;
        }

        .status-form label {
            font-weight: 500;
            color: #333;
        }

        .status-form select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .status-form button {
            background: #f4b400;
            color: #000;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }

        .status-form button:hover {
            background: #e6a800;
        }

        .toggle-details {
            background: #f8f9fa;
            color: #666;
            border: 1px solid #ddd;
            padding: 6px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            margin-left: auto;
        }

        .toggle-details:hover {
            background: #e9ecef;
        }

        .order-expanded {
            display: block;
        }

        .order-collapsed {
            display: none;
        }

        @media (max-width: 768px) {
            .order-details {
                grid-template-columns: 1fr;
            }

            .order-meta {
                flex-direction: column;
                gap: 10px;
            }

            .status-form {
                flex-direction: column;
                align-items: stretch;
            }

            .status-form select {
                width: 100%;
            }
        }
    </style>
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

    <!-- Mobile Menu Toggle -->
    <div class="menu-toggle" onclick="toggleMobileMenu()">
        <span></span>
        <span></span>
        <span></span>
    </div>

    <!-- Mobile Menu Overlay -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="close-menu" onclick="toggleMobileMenu()">&times;</div>
        <a href="dashboard.php" onclick="toggleMobileMenu()"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
        <a href="products.php" onclick="toggleMobileMenu()"><i class="fas fa-box"></i> Products</a>
        <a href="orders.php" onclick="toggleMobileMenu()"><i class="fas fa-shopping-cart"></i> Orders</a>
        <a href="messages.php" onclick="toggleMobileMenu()"><i class="fas fa-envelope"></i> Messages</a>
        <a href="../logout.php" onclick="toggleMobileMenu()"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</header>

<section class="admin-section">
    <h2><i class="fas fa-shopping-cart"></i> Orders Management</h2>

    <div class="admin-content">
        <?php if ($orders_result->num_rows > 0): ?>
            <?php while($order = $orders_result->fetch_assoc()): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-title">
                            Order #<?= $order['id'] ?> - <?= htmlspecialchars($order['name']) ?>
                        </div>
                        <button class="toggle-details" onclick="toggleOrderDetails(<?= $order['id'] ?>)">
                            <i class="fas fa-chevron-down"></i> Details
                        </button>
                    </div>

                    <div class="order-meta">
                        <span><i class="fas fa-calendar"></i> <?= date('M d, Y H:i', strtotime($order['created_at'])) ?></span>
                        <span><i class="fas fa-envelope"></i> <?= htmlspecialchars($order['email']) ?></span>
                        <span><i class="fas fa-dollar-sign"></i> Total: Rs. <?= number_format($order['total'], 2) ?></span>
                    </div>

                    <div class="order-details order-collapsed" id="order-details-<?= $order['id'] ?>">
                        <div class="order-info">
                            <div>
                                <strong>Shipping Address:</strong><br>
                                <?= nl2br(htmlspecialchars($order['address'])) ?>
                            </div>
                            <div>
                                <strong>Phone:</strong> <?= htmlspecialchars($order['phone']) ?>
                            </div>
                            <div>
                                <strong>Payment Method:</strong> <?= htmlspecialchars($order['payment_method'] ?: 'Not specified') ?>
                            </div>
                            <?php if ($order['khalti_token']): ?>
                            <div>
                                <strong>Khalti Transaction ID:</strong> <?= htmlspecialchars($order['khalti_token']) ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="order-status">
                            <div>
                                <strong>Order Status:</strong>
                                <span class="status-badge status-<?= $order['status'] ?>">
                                    <?= ucfirst($order['status']) ?>
                                </span>
                            </div>
                            <div>
                                <strong>Payment Status:</strong>
                                <span class="status-badge payment-<?= $order['payment_status'] ?>">
                                    <?= ucfirst($order['payment_status']) ?>
                                </span>
                            </div>
                        </div>

                        <div class="order-items">
                            <h4>Order Items:</h4>
                            <?php
                            $item_stmt = $conn->prepare("
                                SELECT oi.*, a.title, a.image FROM order_items oi
                                JOIN artworks a ON oi.artwork_id = a.id
                                WHERE oi.order_id = ?");
                            $item_stmt->bind_param("i", $order['id']);
                            $item_stmt->execute();
                            $items_result = $item_stmt->get_result();

                            while($item = $items_result->fetch_assoc()):
                                $image_path = "../assets/images/" . $item['image'];
                                if (!file_exists($image_path)) {
                                    $image_path = "../assets/images/default.jpg";
                                }
                            ?>
                                <div class="item-row">
                                    <img src="<?= $image_path ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="item-image">
                                    <div class="item-details">
                                        <h5><?= htmlspecialchars($item['title']) ?></h5>
                                        <p>Quantity: <?= $item['quantity'] ?> × Rs. <?= number_format($item['price'], 2) ?> each</p>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>

                        <form method="POST" class="status-form">
                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                            <label for="status-<?= $order['id'] ?>">Update Order Status:</label>
                            <select name="status" id="status-<?= $order['id'] ?>">
                                <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="completed" <?= $order['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                            <button type="submit" name="update_status">
                                <i class="fas fa-save"></i> Update Status
                            </button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="order-card">
                <p style="text-align: center; color: #666; padding: 40px;">
                    <i class="fas fa-shopping-cart" style="font-size: 48px; color: #ddd; margin-bottom: 20px;"></i><br>
                    No orders found.
                </p>
            </div>
        <?php endif; ?>
    </div>

<script>
function toggleOrderDetails(orderId) {
    const details = document.getElementById('order-details-' + orderId);
    const header = details.previousElementSibling.previousElementSibling;
    const button = header.querySelector('.toggle-details');
    const icon = button.querySelector('i');

    if (details.classList.contains('order-collapsed')) {
        details.classList.remove('order-collapsed');
        details.classList.add('order-expanded');
        icon.className = 'fas fa-chevron-up';
        button.innerHTML = '<i class="fas fa-chevron-up"></i> Hide Details';
    } else {
        details.classList.remove('order-expanded');
        details.classList.add('order-collapsed');
        icon.className = 'fas fa-chevron-down';
        button.innerHTML = '<i class="fas fa-chevron-down"></i> Details';
    }
}

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
