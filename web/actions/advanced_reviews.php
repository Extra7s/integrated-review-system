<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../includes/db.php";
require_once "../includes/review_analyzer.php";

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$analyzer = new ReviewAnalyzer($conn);

switch ($action) {
    case 'getRiskScore':
        handleGetRiskScore();
        break;
    
    case 'calculateRisks':
        handleCalculateRisks();
        break;
    
    case 'bulkAction':
        handleBulkAction();
        break;
    
    case 'getReviewerProfile':
        handleGetReviewerProfile();
        break;
    
    case 'createAppeal':
        handleCreateAppeal();
        break;
    
    case 'respondToAppeal':
        handleRespondToAppeal();
        break;
    
    case 'getAppeals':
        handleGetAppeals();
        break;
    
    case 'getFlaggedProducts':
        handleGetFlaggedProducts();
        break;
    
    case 'getUserRiskProfile':
        handleGetUserRiskProfile();
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Get risk score and details for a review
 */
function handleGetRiskScore() {
    global $conn, $analyzer;
    
    $review_id = intval($_GET['review_id'] ?? 0);
    
    if (!$review_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'review_id required']);
        return;
    }
    
    $risk_data = $analyzer->calculateRiskScore($review_id);
    
    if (!$risk_data) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Review not found']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'risk_data' => $risk_data
    ]);
}

/**
 * Calculate and store risk scores for all reviews
 */
function handleCalculateRisks() {
    global $conn, $analyzer;
    
    $limit = intval($_POST['limit'] ?? 100);
    $offset = intval($_POST['offset'] ?? 0);
    
    // Get reviews that need risk calculation
    $stmt = $conn->prepare("
        SELECT id FROM reviews 
        WHERE risk_score = 0 OR risk_score IS NULL
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $processed = 0;
    $flagged = 0;
    
    while ($row = $result->fetch_assoc()) {
        $risk_data = $analyzer->calculateRiskScore($row['id']);
        $analyzer->updateReviewRiskScore($row['id'], $risk_data);
        
        if ($risk_data['should_flag']) {
            $flagged++;
        }
        $processed++;
    }
    
    echo json_encode([
        'success' => true,
        'processed' => $processed,
        'flagged' => $flagged,
        'message' => "Processed $processed reviews, flagged $flagged"
    ]);
}

/**
 * Bulk moderation actions
 */
function handleBulkAction() {
    global $conn;
    
    $review_ids = $_POST['review_ids'] ?? [];
    $action = $_POST['bulk_action'] ?? null;
    
    if (!is_array($review_ids) || empty($review_ids) || !$action) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        return;
    }
    
    // Sanitize IDs
    $review_ids = array_map(fn($id) => intval($id), $review_ids);
    $placeholders = implode(',', array_fill(0, count($review_ids), '?'));
    
    $success = false;
    
    switch ($action) {
        case 'approve':
            $stmt = $conn->prepare("UPDATE reviews SET status = 'approved', approved = 1 WHERE id IN ($placeholders)");
            break;
        
        case 'reject':
            $stmt = $conn->prepare("UPDATE reviews SET status = 'rejected', approved = 0 WHERE id IN ($placeholders)");
            break;
        
        case 'flag':
            $stmt = $conn->prepare("UPDATE reviews SET status = 'flagged', approved = 0 WHERE id IN ($placeholders)");
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            return;
    }
    
    $stmt->bind_param(str_repeat('i', count($review_ids)), ...$review_ids);
    $success = $stmt->execute();
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => "Successfully $action " . count($review_ids) . " reviews",
            'count' => count($review_ids)
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get detailed reviewer profile
 */
function handleGetReviewerProfile() {
    global $conn, $analyzer;
    
    $user_id = intval($_GET['user_id'] ?? 0);
    
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'user_id required']);
        return;
    }
    
    // Get user info
    $stmt = $conn->prepare("SELECT id, name, email, created_at FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    
    // Get all reviews by user
    $stmt = $conn->prepare("
        SELECT 
            r.id, r.artwork_id, r.rating, r.comment, r.risk_score, r.status, 
            r.created_at, a.title as artwork_title,
            (SELECT COUNT(*) FROM order_items oi 
             JOIN orders o ON oi.order_id = o.id 
             WHERE oi.artwork_id = r.artwork_id AND o.user_id = r.user_id) as purchased
        FROM reviews r
        JOIN artworks a ON r.artwork_id = a.id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get purchase history
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT a.id) as purchased_count
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN artworks a ON oi.artwork_id = a.id
        WHERE o.user_id = ? AND o.payment_status = 'paid' AND o.status = 'completed'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $purchase_info = $stmt->get_result()->fetch_assoc();
    
    // Calculate risk profile
    $risk_profile = $analyzer->calculateUserRiskProfile($user_id);
    $analyzer->updateUserRiskProfile($user_id);
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'created_at' => $user['created_at'],
            'account_age_days' => intval((strtotime(date('Y-m-d H:i:s')) - strtotime($user['created_at'])) / 86400)
        ],
        'reviews' => $reviews,
        'purchase_info' => [
            'total_purchased' => intval($purchase_info['purchased_count']),
            'total_reviews' => count($reviews),
            'review_to_purchase_ratio' => count($reviews) > 0 ? (count($reviews) / max(1, intval($purchase_info['purchased_count']))) : 0
        ],
        'risk_profile' => $risk_profile
    ]);
}

/**
 * Create appeal for flagged review
 */
function handleCreateAppeal() {
    global $conn, $user_id;
    
    $review_id = intval($_POST['review_id'] ?? 0);
    $appeal_reason = trim($_POST['appeal_reason'] ?? '');
    $user_id = intval($_POST['user_id'] ?? 0);
    
    if (!$review_id || !$appeal_reason || !$user_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    // Verify review belongs to user
    $stmt = $conn->prepare("SELECT user_id FROM reviews WHERE id = ?");
    $stmt->bind_param("i", $review_id);
    $stmt->execute();
    $review = $stmt->get_result()->fetch_assoc();
    
    if (!$review || $review['user_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    $stmt = $conn->prepare("
        INSERT INTO review_appeals (review_id, user_id, appeal_reason)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iis", $review_id, $user_id, $appeal_reason);
    
    if ($stmt->execute()) {
        $stmt = $conn->prepare("UPDATE reviews SET status = 'appealed' WHERE id = ?");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Appeal submitted successfully. Admins will review within 24 hours.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Respond to appeal (admin only)
 */
function handleRespondToAppeal() {
    global $conn, $_SESSION;
    
    $appeal_id = intval($_POST['appeal_id'] ?? 0);
    $response = trim($_POST['response'] ?? '');
    $decision = $_POST['decision'] ?? null; // 'approved' or 'rejected'
    $admin_id = $_SESSION['user']['id'];
    
    if (!$appeal_id || !$response || !$decision) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    // Get appeal and review
    $stmt = $conn->prepare("
        SELECT a.review_id FROM review_appeals a WHERE a.id = ?
    ");
    $stmt->bind_param("i", $appeal_id);
    $stmt->execute();
    $appeal = $stmt->get_result()->fetch_assoc();
    
    if (!$appeal) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Appeal not found']);
        return;
    }
    
    // Update appeal
    $stmt = $conn->prepare("
        UPDATE review_appeals 
        SET appeal_status = ?, admin_response = ?, admin_id = ?, resolved_at = NOW()
        WHERE id = ?
    ");
    $stmt->bind_param("ssii", $decision, $response, $admin_id, $appeal_id);
    
    if ($stmt->execute()) {
        // Update review status based on appeal decision
        $new_status = ($decision === 'approved') ? 'approved' : 'rejected';
        $approved = ($decision === 'approved') ? 1 : 0;
        
        $stmt = $conn->prepare("
            UPDATE reviews SET status = ?, approved = ? WHERE id = ?
        ");
        $stmt->bind_param("sii", $new_status, $approved, $appeal['review_id']);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => "Appeal $decision successfully"
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get pending appeals
 */
function handleGetAppeals() {
    global $conn;
    
    $status = $_GET['status'] ?? 'pending';
    
    $stmt = $conn->prepare("
        SELECT 
            a.id,
            a.review_id,
            a.user_id,
            a.appeal_reason,
            a.appeal_status,
            a.created_at,
            r.comment as review_comment,
            r.rating,
            r.risk_score,
            u.name as user_name,
            u.email as user_email,
            art.title as artwork_title
        FROM review_appeals a
        JOIN reviews r ON a.review_id = r.id
        JOIN users u ON a.user_id = u.id
        JOIN artworks art ON r.artwork_id = art.id
        WHERE a.appeal_status = ?
        ORDER BY a.created_at ASC
    ");
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $appeals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'appeals' => $appeals,
        'count' => count($appeals)
    ]);
}

/**
 * Get products with suspicious review patterns
 */
function handleGetFlaggedProducts() {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            artwork_id,
            COUNT(*) as review_count,
            AVG(risk_score) as avg_risk_score,
            SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged_reviews,
            a.title,
            a.price,
            MAX(r.created_at) as latest_review
        FROM reviews r
        JOIN artworks a ON r.artwork_id = a.id
        GROUP BY artwork_id
        HAVING avg_risk_score > 40 OR flagged_reviews > 2
        ORDER BY avg_risk_score DESC
    ");
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get burst data
    $stmt = $conn->prepare("
        SELECT 
            artwork_id,
            SUM(CASE WHEN is_suspicious = 1 THEN 1 ELSE 0 END) as burst_count,
            MAX(detected_at) as latest_burst
        FROM product_review_bursts
        GROUP BY artwork_id
    ");
    $stmt->execute();
    $bursts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $burst_map = array_column($bursts, null, 'artwork_id');
    
    // Merge burst data
    foreach ($products as &$product) {
        if (isset($burst_map[$product['artwork_id']])) {
            $product['burst_data'] = $burst_map[$product['artwork_id']];
        }
    }
    
    echo json_encode([
        'success' => true,
        'products' => $products,
        'count' => count($products)
    ]);
}

/**
 * Get user risk profile
 */
function handleGetUserRiskProfile() {
    global $conn, $analyzer;
    
    $user_id = intval($_GET['user_id'] ?? 0);
    
    if (!$user_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'user_id required']);
        return;
    }
    
    $profile = $analyzer->calculateUserRiskProfile($user_id);
    
    echo json_encode([
        'success' => true,
        'profile' => $profile
    ]);
}
?>
