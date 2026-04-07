<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../includes/db.php";

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$user_id = $_SESSION['user']['id'];

switch ($action) {
    case 'getMyReviews':
        handleGetMyReviews();
        break;
    
    case 'getMyAppeals':
        handleGetMyAppeals();
        break;
    
    case 'submitAppeal':
        handleSubmitAppeal();
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Get user's reviews
 */
function handleGetMyReviews() {
    global $conn, $user_id;
    
    $stmt = $conn->prepare("
        SELECT 
            r.id, r.artwork_id, r.rating, r.comment, r.status,
            r.risk_score, r.risk_factors, r.flagged_reason, r.created_at,
            a.title as artwork_title
        FROM reviews r
        JOIN artworks a ON r.artwork_id = a.id
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
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
 * Get user's appeals
 */
function handleGetMyAppeals() {
    global $conn, $user_id;
    
    $stmt = $conn->prepare("
        SELECT 
            a.id, a.review_id, a.appeal_reason, a.appeal_status, a.admin_response, a.created_at,
            r.comment as review_comment, r.rating,
            art.title as artwork_title
        FROM review_appeals a
        JOIN reviews r ON a.review_id = r.id
        JOIN artworks art ON r.artwork_id = art.id
        WHERE a.user_id = ?
        ORDER BY a.created_at DESC
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $appeals = [];
    while ($row = $stmt->get_result()->fetch_assoc()) {
        $appeals[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'appeals' => $appeals
    ]);
}

/**
 * Submit appeal for flagged review
 */
function handleSubmitAppeal() {
    global $conn, $user_id;
    
    $review_id = intval($_POST['review_id'] ?? 0);
    $appeal_reason = trim($_POST['appeal_reason'] ?? '');
    
    if (!$review_id || !$appeal_reason) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    if (strlen($appeal_reason) < 20) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Appeal must be at least 20 characters']);
        return;
    }
    
    // Verify review belongs to user and is flagged
    $stmt = $conn->prepare("
        SELECT id, user_id, status FROM reviews WHERE id = ?
    ");
    $stmt->bind_param("i", $review_id);
    $stmt->execute();
    $review = $stmt->get_result()->fetch_assoc();
    
    if (!$review) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Review not found']);
        return;
    }
    
    if ($review['user_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        return;
    }
    
    if ($review['status'] !== 'flagged' && $review['status'] !== 'rejected') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Only flagged or rejected reviews can be appealed']);
        return;
    }
    
    // Check if user already has a pending appeal for this review
    $stmt = $conn->prepare("
        SELECT id FROM review_appeals 
        WHERE review_id = ? AND appeal_status = 'pending'
    ");
    $stmt->bind_param("i", $review_id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'You already have a pending appeal for this review']);
        return;
    }
    
    // Create appeal
    $stmt = $conn->prepare("
        INSERT INTO review_appeals (review_id, user_id, appeal_reason)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param("iis", $review_id, $user_id, $appeal_reason);
    
    if ($stmt->execute()) {
        // Update review status to appealed
        $stmt = $conn->prepare("UPDATE reviews SET status = 'appealed' WHERE id = ?");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Appeal submitted successfully'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}
?>
