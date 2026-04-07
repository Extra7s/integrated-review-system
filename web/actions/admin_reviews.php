<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../includes/db.php";
require_once "../includes/review_analyzer.php";
require_once "../config/admin_training_logger.php";

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$admin_id = $_SESSION['user']['id'];
$action = $_GET['action'] ?? $_POST['action'] ?? null;
$analyzer = new ReviewAnalyzer($conn);

switch ($action) {
    case 'getReviews':
        handleGetReviews();
        break;
    
    case 'getFlaggedReviews':
        handleGetFlaggedReviews();
        break;
    
    case 'updateReviewStatus':
        handleUpdateReviewStatus();
        break;
    
    case 'getReviewerProfile':
        handleGetReviewerProfile();
        break;
    
    case 'getAppeals':
        handleGetAppeals();
        break;
    
    case 'respondToAppeal':
        handleRespondToAppeal();
        break;
    
    case 'getFlaggedProducts':
        handleGetFlaggedProducts();
        break;
    
    case 'getDuplicates':
        handleGetDuplicates();
        break;
    
    case 'bulkAction':
        handleBulkAction();
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Get all reviews with risk scores
 */
function handleGetReviews() {
    global $conn, $analyzer;
    
    $offset = intval($_GET['offset'] ?? 0);
    $limit = intval($_GET['limit'] ?? 20);
    
    // Get total count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM reviews");
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];
    
    // Get reviews
    $stmt = $conn->prepare("
        SELECT 
            r.id, r.artwork_id, r.user_id, r.rating, r.comment,
            r.risk_score, r.risk_factors, r.status, r.created_at,
            u.name as user_name, a.title as artwork_title
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        JOIN artworks a ON r.artwork_id = a.id
        ORDER BY r.risk_score DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $reviews = [];
    while ($row = $stmt->get_result()->fetch_assoc()) {
        $row['risk_factors'] = json_decode($row['risk_factors'], true);
        $reviews[] = $row;
    }
    
    // Calculate stats
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN risk_score >= 70 THEN 1 ELSE 0 END) as high_risk,
            SUM(CASE WHEN risk_score >= 40 AND risk_score < 70 THEN 1 ELSE 0 END) as medium_risk,
            SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged
        FROM reviews
    ");
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'reviews' => $reviews,
        'stats' => $stats,
        'pagination' => [
            'page' => ($offset / $limit) + 1,
            'total' => intval($total),
            'pages' => ceil($total / $limit)
        ]
    ]);
}

/**
 * Get flagged reviews
 */
function handleGetFlaggedReviews() {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            r.id, r.artwork_id, r.user_id, r.rating, r.comment,
            r.risk_score, r.risk_factors, r.status, r.flagged_reason, r.created_at,
            u.name as user_name, a.title as artwork_title
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        JOIN artworks a ON r.artwork_id = a.id
        WHERE r.status IN ('flagged', 'rejected')
        ORDER BY r.risk_score DESC
        LIMIT 100
    ");
    $stmt->execute();
    $reviews = [];
    while ($row = $stmt->get_result()->fetch_assoc()) {
        $row['risk_factors'] = json_decode($row['risk_factors'], true);
        $reviews[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'reviews' => $reviews
    ]);
}

/**
 * Update review status
 */
function handleUpdateReviewStatus() {
    global $conn, $admin_id;
    
    $review_id = intval($_POST['review_id'] ?? 0);
    $status = $_POST['status'] ?? null;
    $reason = $_POST['reason'] ?? null;
    
    if (!$review_id || !$status) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        return;
    }
    
    $approved = ($status === 'approved') ? 1 : 0;
    
    $stmt = $conn->prepare("
        UPDATE reviews 
        SET status = ?, approved = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sii", $status, $approved, $review_id);
    
    if ($stmt->execute()) {
        // Log admin decision for ML training
        try {
            $logger = new AdminTrainingLogger($conn);
            $log_result = $logger->logAdminDecision($review_id, $admin_id, $status, $reason);
            if (!$log_result['success']) {
                error_log("Failed to log training data: " . $log_result['error']);
                // Don't fail the review update - training logging is non-critical
            }
        } catch (Exception $e) {
            error_log("Error logging admin decision: " . $e->getMessage());
            // Continue anyway - training logging shouldn't break review updates
        }
        
        echo json_encode(['success' => true, 'message' => "Review $status successfully"]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Get reviewer profile
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
    
    // Get reviews by user
    $stmt = $conn->prepare("
        SELECT 
            r.id, r.artwork_id, r.rating, r.comment, r.risk_score, r.status,
            r.created_at, a.title as artwork_title
        FROM reviews r
        JOIN artworks a ON r.artwork_id = a.id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
        LIMIT 20
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get purchase count
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT a.id) as purchase_count
        FROM orders o
        JOIN order_items oi ON o.id = oi.order_id
        JOIN artworks a ON oi.artwork_id = a.id
        WHERE o.user_id = ? AND o.payment_status = 'paid'
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $purchase_info = $stmt->get_result()->fetch_assoc();
    
    // Calculate risk profile
    $risk_profile = $analyzer->calculateUserRiskProfile($user_id);
    
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'created_at' => $user['created_at'],
            'account_age_days' => intval((time() - strtotime($user['created_at'])) / 86400)
        ],
        'reviews' => $reviews,
        'purchase_info' => [
            'total_purchased' => intval($purchase_info['purchase_count']),
            'total_reviews' => count($reviews),
            'review_to_purchase_ratio' => count($reviews) > 0 ? number_format(count($reviews) / max(1, intval($purchase_info['purchase_count'])), 2) : 0
        ],
        'risk_profile' => $risk_profile
    ]);
}

/**
 * Get pending appeals
 */
function handleGetAppeals() {
    global $conn;
    
    $status = $_GET['status'] ?? 'pending';
    
    $stmt = $conn->prepare("
        SELECT 
            a.id, a.review_id, a.user_id, a.appeal_reason, a.appeal_status, a.created_at,
            r.comment as review_comment, r.rating, r.risk_score,
            u.name as user_name, u.email as user_email,
            art.title as artwork_title
        FROM review_appeals a
        JOIN reviews r ON a.review_id = r.id
        JOIN users u ON a.user_id = u.id
        JOIN artworks art ON r.artwork_id = art.id
        WHERE a.appeal_status = ?
        ORDER BY a.created_at ASC
        LIMIT 50
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
 * Respond to appeal
 */
function handleRespondToAppeal() {
    global $conn, $admin_id;
    
    $appeal_id = intval($_POST['appeal_id'] ?? 0);
    $response = trim($_POST['response'] ?? '');
    $decision = $_POST['decision'] ?? null;
    
    if (!$appeal_id || !$response || !$decision) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        return;
    }
    
    // Get appeal and review
    $stmt = $conn->prepare("SELECT review_id FROM review_appeals WHERE id = ?");
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
    $stmt->execute();
    
    // Update review status
    $new_status = ($decision === 'approved') ? 'approved' : 'rejected';
    $approved = ($decision === 'approved') ? 1 : 0;
    
    $stmt = $conn->prepare("
        UPDATE reviews SET status = ?, approved = ? WHERE id = ?
    ");
    $stmt->bind_param("sii", $new_status, $approved, $appeal['review_id']);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'message' => "Appeal $decision successfully"]);
}

/**
 * Get flagged products
 */
function handleGetFlaggedProducts() {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            r.artwork_id,
            COUNT(*) as review_count,
            AVG(r.risk_score) as avg_risk_score,
            SUM(CASE WHEN r.status = 'flagged' THEN 1 ELSE 0 END) as flagged_reviews,
            a.title,
            a.price,
            MAX(r.created_at) as latest_review
        FROM reviews r
        JOIN artworks a ON r.artwork_id = a.id
        GROUP BY r.artwork_id
        HAVING avg_risk_score > 40 OR flagged_reviews > 2
        ORDER BY avg_risk_score DESC
        LIMIT 50
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
    $burst_map = [];
    foreach ($bursts as $burst) {
        $burst_map[$burst['artwork_id']] = $burst;
    }
    
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
 * Get duplicate/near-duplicate reviews
 */
function handleGetDuplicates() {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            rs.id, rs.review_id_1, rs.review_id_2, rs.similarity_score, rs.detected_at,
            r1.comment as comment_1, r1.id as id_1,
            r2.comment as comment_2, r2.id as id_2
        FROM review_similarities rs
        JOIN reviews r1 ON rs.review_id_1 = r1.id
        JOIN reviews r2 ON rs.review_id_2 = r2.id
        WHERE rs.similarity_score >= 0.75
        ORDER BY rs.similarity_score DESC
        LIMIT 50
    ");
    $stmt->execute();
    $similarities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'similarities' => $similarities,
        'count' => count($similarities)
    ]);
}

/**
 * Bulk actions on reviews
 */
function handleBulkAction() {
    global $conn;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $review_ids = $data['review_ids'] ?? [];
    $action = $data['bulk_action'] ?? null;
    
    if (!is_array($review_ids) || empty($review_ids) || !$action) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing parameters']);
        return;
    }
    
    $review_ids = array_map(fn($id) => intval($id), $review_ids);
    $placeholders = implode(',', array_fill(0, count($review_ids), '?'));
    
    switch ($action) {
        case 'approve':
            $status = 'approved';
            $approved = 1;
            break;
        case 'reject':
            $status = 'rejected';
            $approved = 0;
            break;
        case 'flag':
            $status = 'flagged';
            $approved = 0;
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            return;
    }
    
    $types = str_repeat('i', count($review_ids));
    $stmt = $conn->prepare("UPDATE reviews SET status = ?, approved = ? WHERE id IN ($placeholders)");
    $stmt->bind_param("si$types", $status, $approved, ...$review_ids);
    
    if ($stmt->execute()) {
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
?>
