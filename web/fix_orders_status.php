<?php
/**
 * ONE-TIME FIX SCRIPT
 * Patches existing paid orders that were never set to status='completed'.
 * 
 * Background: payment_success.php was only updating payment_status='paid'
 * but not status='completed', causing purchase checks in reviews to fail.
 * 
 * Run this ONCE from the browser: http://localhost/integrated_artstore_review_system/web/fix_orders_status.php
 * Then DELETE this file.
 */
require_once 'includes/db.php';
require_once 'config/review_token_service.php';

// Security: only run from localhost
if (!in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('Access denied. This script can only be run from localhost.');
}

echo "<pre>\n";
echo "=== Order Status Fix Script ===\n\n";

// Count orders that need fixing
$result = $conn->query("SELECT COUNT(*) as cnt FROM orders WHERE payment_status = 'paid' AND status != 'completed' AND status != 'cancelled'");
$row = $result->fetch_assoc();
echo "Orders needing status fix: " . $row['cnt'] . "\n";

// Fix them
$fix = $conn->query("UPDATE orders SET status = 'completed' WHERE payment_status = 'paid' AND status != 'completed' AND status != 'cancelled'");
echo "Fixed orders: " . $conn->affected_rows . "\n\n";

// Now check for paid orders with no review tokens and generate them
echo "=== Checking for missing review tokens ===\n\n";
$orders = $conn->query("
    SELECT DISTINCT o.id as order_id
    FROM orders o
    JOIN order_items oi ON oi.order_id = o.id
    WHERE o.payment_status = 'paid'
      AND o.status = 'completed'
      AND NOT EXISTS (
          SELECT 1 FROM review_tokens rt WHERE rt.order_id = o.id
      )
");

$tokenService = new ReviewTokenService($conn);
$fixed_orders = 0;
while ($order = $orders->fetch_assoc()) {
    $order_id = $order['order_id'];
    try {
        $generated = $tokenService->generateTokensForOrder($order_id);
        echo "Order #$order_id: Generated $generated review token(s)\n";
        $fixed_orders++;
    } catch (Exception $e) {
        echo "Order #$order_id: ERROR - " . $e->getMessage() . "\n";
    }
}

if ($fixed_orders === 0 && $orders->num_rows === 0) {
    echo "All paid orders already have review tokens. Nothing to fix.\n";
}

echo "\n=== Done ===\n";
echo "\nIMPORTANT: Delete this file after running it!\n";
echo "</pre>";
?>
