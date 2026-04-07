<?php
session_start();
require_once "includes/db.php";

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user']['id'];

// Handle cancel order request
$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order_id'])) {
    $cancel_id = intval($_POST['cancel_order_id']);
    $chk = $conn->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
    $chk->bind_param("ii", $cancel_id, $user_id);
    $chk->execute();
    $chkRes = $chk->get_result();
    if ($row = $chkRes->fetch_assoc()) {
        if ($row['status'] === 'pending') {
            $u = $conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
            $u->bind_param("i", $cancel_id);
            if ($u->execute()) {
                $message = ['type' => 'success', 'text' => 'Order #' . $cancel_id . ' cancelled successfully.'];
            } else {
                $message = ['type' => 'error', 'text' => 'Failed to cancel the order. Please try again.'];
            }
        } else {
            $message = ['type' => 'error', 'text' => 'Only pending orders can be cancelled.'];
        }
    } else {
        $message = ['type' => 'error', 'text' => 'Order not found.'];
    }
}

// Get user's orders
$stmt = $conn->prepare("SELECT o.* FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders_result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - ArtStore</title>
    <link rel="stylesheet" href="assets/css/style_organized.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
<?php include "includes/header.php"; ?>

<section class="main-section">
    <div class="container">
        <div class="page-header">
            <h2><i class="fas fa-shopping-cart"></i> My Orders</h2>
            <p>Track your order history and status</p>
        </div>

        <?php if ($message): ?>
            <div class="alert <?= $message['type'] === 'success' ? 'alert-success' : 'alert-error' ?>">
                <?= htmlspecialchars($message['text']) ?>
            </div>
        <?php endif; ?>

        <?php if ($orders_result->num_rows > 0): ?>
            <div class="orders-grid">
                <?php while($order = $orders_result->fetch_assoc()): ?>
                    <div class="order-card">
                        <div class="order-header">
                            <div class="order-title">
                                Order #<?= $order['id'] ?>
                            </div>
                            <div class="order-date">
                                <i class="fas fa-calendar"></i> <?= date('M d, Y H:i', strtotime($order['created_at'])) ?>
                            </div>
                        </div>

                        <div class="order-summary">
                            <div class="summary-item">
                                <span class="label">Total Amount</span>
                                <span class="value">Rs. <?= number_format($order['total'], 2) ?></span>
                            </div>
                            <div class="summary-item">
                                <span class="label">Payment</span>
                                <span class="value"><?= htmlspecialchars($order['payment_method'] ?: '—') ?>
                                    <?php if($order['payment_status'] === 'paid'): ?>
                                        <span class="payment-pill paid"><i class="fas fa-check-circle"></i> Paid</span>
                                    <?php else: ?>
                                        <span class="payment-pill unpaid"><i class="fas fa-clock"></i> <?= ucfirst($order['payment_status']) ?></span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <div class="summary-item">
                                <span class="label">Status</span>
                                <span class="status-badge status-<?= $order['status'] ?>"><?= ucfirst($order['status']) ?></span>
                            </div>
                        </div>

                        <div class="order-details">
                            <div class="detail-section">
                                <h4><i class="fas fa-map-marker-alt"></i> Shipping Information</h4>
                                <div class="info-content">
                                    <p><strong>Address:</strong><br><?= nl2br(htmlspecialchars($order['address'])) ?></p>
                                    <p><strong>Phone:</strong> <?= htmlspecialchars($order['phone']) ?></p>
                                    <?php if ($order['khalti_token']): ?>
                                    <p><strong>Transaction ID:</strong> <?= htmlspecialchars($order['khalti_token']) ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="detail-section">
                                <h4><i class="fas fa-box"></i> Order Items</h4>
                                <div class="items-list">
                                    <?php
                                    $item_stmt = $conn->prepare("
                                        SELECT oi.*, a.title, a.image FROM order_items oi
                                        JOIN artworks a ON oi.artwork_id = a.id
                                        WHERE oi.order_id = ?");
                                    $item_stmt->bind_param("i", $order['id']);
                                    $item_stmt->execute();
                                    $items_result = $item_stmt->get_result();

                                    while($item = $items_result->fetch_assoc()):
                                        $image_path = "assets/images/" . $item['image'];
                                        if (!file_exists($image_path)) {
                                            $image_path = "assets/images/default.jpg";
                                        }
                                    ?>
                                        <div class="item-row">
                                            <img src="<?= $image_path ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="item-image">
                                            <div class="item-details">
                                                <h5><?= htmlspecialchars($item['title']) ?></h5>
                                                <p class="muted">Quantity: <?= $item['quantity'] ?> × Rs. <?= number_format($item['price'], 2) ?> each</p>
                                                <p class="item-subtotal">Subtotal: Rs. <?= number_format($item['price'] * $item['quantity'], 2) ?></p>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                        <div class="order-footer">
                            <div class="order-actions">
                                <a href="order_view.php?id=<?= $order['id'] ?>" class="btn btn-outline">View Details</a>
                                <?php if ($order['status'] === 'pending'): ?>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this order?');" style="display:inline-block;">
                                        <input type="hidden" name="cancel_order_id" value="<?= $order['id'] ?>">
                                        <button type="submit" class="btn btn-danger">Cancel Order</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                            <div class="order-totals">
                                <div class="totals-row"><span>Items:</span><strong><?= $items_result->num_rows ?></strong></div>
                                <div class="totals-row"><span>Order Total</span><strong>Rs. <?= number_format($order['total'], 2) ?></strong></div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h3>No Orders Yet</h3>
                <p>You haven't placed any orders yet. Start shopping to see your orders here!</p>
                <a href="shop.php" class="btn btn-primary">Browse Artworks</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include "includes/footer.php"; ?>
</body>
</html>