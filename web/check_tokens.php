<?php
require_once 'includes/db.php';

echo "=== Database Token Check ===\n";

try {
    // Check total tokens
    $result = $conn->query('SELECT COUNT(*) as count FROM review_tokens');
    $row = $result->fetch_assoc();
    echo 'Total tokens: ' . $row['count'] . "\n";

    // Check valid unused tokens
    $result = $conn->query('SELECT COUNT(*) as count FROM review_tokens WHERE is_used = 0 AND expires_at > NOW()');
    $row = $result->fetch_assoc();
    echo 'Valid unused tokens: ' . $row['count'] . "\n";

    // Check paid orders
    $result = $conn->query('SELECT COUNT(*) as count FROM orders WHERE payment_status = "paid"');
    $row = $result->fetch_assoc();
    echo 'Paid orders: ' . $row['count'] . "\n";

    // Check recent tokens
    $result = $conn->query('SELECT id, user_id, artwork_id, is_used, expires_at, created_at FROM review_tokens ORDER BY created_at DESC LIMIT 5');
    echo "\nRecent tokens:\n";
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']}, User: {$row['user_id']}, Artwork: {$row['artwork_id']}, Used: {$row['is_used']}, Expires: {$row['expires_at']}, Created: {$row['created_at']}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>