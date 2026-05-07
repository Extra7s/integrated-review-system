<?php
// Buffer all output so PHP warnings/notices don't corrupt JSON responses
ob_start();

ini_set('display_errors', '0');
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'A fatal error occurred while processing the review request.',
            'error' => $error['message'] ?? 'Unknown fatal error'
        ]);
    }
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../includes/db.php";
require_once "../config/review_api.php";
require_once "../includes/review_analyzer.php";
require_once "../config/review_token_service.php";

// Discard any output from requires (warnings, notices etc.) and send clean JSON header
ob_clean();
header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? null;

// Check if user is logged in
$is_logged_in = isset($_SESSION['user']);
$user_id = $is_logged_in ? $_SESSION['user']['id'] : null;

if (!$is_logged_in && $action !== 'get') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'You must be logged in to submit reviews']);
    exit;
}

try {
    switch ($action) {
        case 'submit':
            handleSubmitReview();
            break;
        
        case 'get':
            handleGetReviews();
            break;
        
        case 'delete':
            handleDeleteReview();
            break;
        
        case 'update':
            handleUpdateReview();
            break;
        
        case 'helpful':
            handleHelpful();
            break;
        
        case 'checkCanReview':
            handleCheckCanReview();
            break;
        
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Throwable $e) {
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected error occurred while handling the request.',
        'error' => $e->getMessage()
    ]);
}

/**
 * Handle review submission
 */
function handleSubmitReview() {
    global $conn, $user_id, $review_api_service;

    $artwork_id = intval($_POST['artwork_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    // Validation
    if (!$artwork_id || !$rating || !$comment) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields: artwork_id=' . $artwork_id . ', rating=' . $rating . ', comment=' . strlen($comment)]);
        return;
    }

    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'User not authenticated']);
        return;
    }

    if ($rating < 1 || $rating > 5) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5']);
        return;
    }

    if (strlen($comment) < 10 || strlen($comment) > 5000) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Comment must be between 10 and 5000 characters']);
        return;
    }

    // Verify artwork exists
    $stmt = $conn->prepare("SELECT id FROM artworks WHERE id = ?");
    $stmt->bind_param("i", $artwork_id);
    $stmt->execute();
    if (!$stmt->get_result()->fetch_assoc()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Artwork not found']);
        return;
    }

    // Check if user has purchased this artwork
    // Accept orders that are paid (status='completed') OR paid (payment_status='paid') for backward compatibility
    $stmt = $conn->prepare("
        SELECT COUNT(*) as purchase_count 
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE oi.artwork_id = ? AND o.user_id = ? 
          AND o.payment_status = 'paid'
          AND o.status != 'cancelled'
    ");
    $stmt->bind_param("ii", $artwork_id, $user_id);
    $stmt->execute();
    $purchase = $stmt->get_result()->fetch_assoc();

    if ($purchase['purchase_count'] == 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only review artworks you have purchased']);
        return;
    }

    // Check if user has a valid review token for this artwork.
    // If no token exists but the user has a verified purchase (checked above),
    // auto-generate one — this covers purchases made before the token system was added.
    try {
        $tokenService = new ReviewTokenService($conn);
        
        if (!$tokenService->hasValidToken($user_id, $artwork_id)) {
            error_log("No valid token for user $user_id, artwork $artwork_id. Auto-generating token because purchase is verified.");
            
            // Find the order_id for this user+artwork purchase (same conditions as purchase check above)
            $order_stmt = $conn->prepare("
                SELECT o.id as order_id
                FROM orders o
                JOIN order_items oi ON oi.order_id = o.id
                WHERE oi.artwork_id = ? AND o.user_id = ?
                  AND o.payment_status = 'paid'
                  AND o.status != 'cancelled'
                ORDER BY o.id DESC
                LIMIT 1
            ");
            $order_stmt->bind_param("ii", $artwork_id, $user_id);
            $order_stmt->execute();
            $order_row = $order_stmt->get_result()->fetch_assoc();
            
            if ($order_row) {
                // Generate a token directly for this user/artwork pair
                $token_val = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+365 days'));
                $ins = $conn->prepare("
                    INSERT INTO review_tokens (order_id, user_id, artwork_id, token, expires_at, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $ins->bind_param("iiiss", $order_row['order_id'], $user_id, $artwork_id, $token_val, $expires_at);
                if (!$ins->execute()) {
                    error_log("Failed to auto-generate token: " . $ins->error);
                    http_response_code(500);
                    echo json_encode(['success' => false, 'message' => 'Could not create review token. Please contact support.']);
                    return;
                }
                error_log("Auto-generated token for user $user_id, artwork $artwork_id");
            } else {
                // Should never reach here since purchase was already validated above
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Purchase verification mismatch. Please contact support.']);
                return;
            }
        }
        
        // Get the token for consumption after review creation
        $tokenData = $tokenService->getValidToken($user_id, $artwork_id);
        if (!$tokenData) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Unable to retrieve review token after generation.']);
            return;
        }
    } catch (Exception $e) {
        error_log("Token validation error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error processing review token: ' . $e->getMessage()]);
        return;
    }

    // Check if user already reviewed this artwork
    $stmt = $conn->prepare("SELECT id FROM reviews WHERE artwork_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $artwork_id, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->fetch_assoc()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'You have already reviewed this artwork']);
        return;
    }

    // Check fake review with Flask API
    $api_result = $review_api_service->checkReviewFakeness($comment, 'ensemble');
    
    // Insert review
    $algorithm = $api_result['algorithm'];
    $is_fake = $api_result['is_fake'] ?? 0;
    $confidence = $api_result['confidence'] ?? 0;
    $detection_checked = $api_result['success'] ? 1 : 0;

    // Verified purchase is enforced above (completed + paid order)
    $verified_purchase = 1;
    $status = 'pending';
    $approved = 0;

    // Authenticity score is normalized to 0..1 where 1 = very authentic
    // - If model says Genuine with confidence c -> authenticity = c
    // - If model says Fake with confidence c -> authenticity = 1 - c
    $authenticity_score = $is_fake ? max(0.0, 1.0 - floatval($confidence)) : floatval($confidence);
    // Mark as "authentic" only when model flags genuine with high confidence
    $is_authentic = (!$is_fake && floatval($confidence) >= 0.70) ? 1 : 0;

    $stmt = $conn->prepare("
        INSERT INTO reviews (
            artwork_id,
            user_id,
            rating,
            comment,
            is_fake,
            fake_confidence,
            fake_detection_checked,
            detection_algorithm,
            verified_purchase,
            is_authentic,
            authenticity_score,
            status,
            approved
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        return;
    }
    
    $stmt->bind_param(
        "iiisidisiidsi",
        $artwork_id,
        $user_id,
        $rating,
        $comment,
        $is_fake,
        $confidence,
        $detection_checked,
        $algorithm,
        $verified_purchase,
        $is_authentic,
        $authenticity_score,
        $status,
        $approved
    );

    if ($stmt->execute()) {
        $review_id = $stmt->insert_id;
        
        // Consume the review token now that review is created
        try {
            $tokenService = new ReviewTokenService($conn);
            $token_result = $tokenService->validateAndConsumeToken($tokenData['token'], $user_id, $artwork_id, $review_id);
            if (!$token_result['valid']) {
                error_log("Token validation failed for review $review_id: " . $token_result['error']);
                // Log but don't fail the review submission - token and review are already created
            }
        } catch (Exception $e) {
            error_log("Error consuming review token: " . $e->getMessage());
            // Continue anyway - token consumption shouldn't block review submission
        }
        
        // Calculate risk score asynchronously
        $analyzer = new ReviewAnalyzer($GLOBALS['conn']);
        $risk_data = $analyzer->calculateRiskScore($review_id);
        $analyzer->updateReviewRiskScore($review_id, $risk_data);
        $analyzer->updateUserRiskProfile($user_id);
        
        // Check for product burst
        $analyzer->detectProductBurst($artwork_id, 60);
        
        echo json_encode([
            'success' => true,
            'message' => 'Review submitted successfully',
            'review_id' => $review_id,
            'detection_result' => [
                'is_fake' => $is_fake,
                'confidence' => $confidence,
                'status' => $detection_checked ? 'checked' : 'offline'
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $stmt->error]);
    }
}

/**
 * Handle getting reviews for an artwork
 */
function handleGetReviews() {
    global $conn, $user_id;

    $artwork_id = intval($_GET['artwork_id'] ?? 0);
    $page = intval($_GET['page'] ?? 1);
    $limit = 10;
    $offset = ($page - 1) * $limit;

    if (!$artwork_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'artwork_id required']);
        return;
    }

    // Get total count
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM reviews WHERE artwork_id = ? AND status != 'rejected'");
    $stmt->bind_param("i", $artwork_id);
    $stmt->execute();
    $total = $stmt->get_result()->fetch_assoc()['total'];

    // Determine if helpful_votes table exists (avoid failing joins on older DBs)
    $hasVoteTable = false;
    $tableCheck = $conn->query("SHOW TABLES LIKE 'helpful_votes'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $hasVoteTable = true;
    }

    // Get reviews with user info, authenticity data, and the current user's vote (if logged in)
    if ($user_id && $hasVoteTable) {
        $stmt = $conn->prepare("
            SELECT 
                r.id,
                r.rating,
                r.comment,
                r.is_fake,
                r.fake_confidence,
                r.helpful_count,
                r.unhelpful_count,
                r.verified_purchase,
                r.is_authentic,
                r.authenticity_score,
                r.status,
                r.created_at,
                u.name as user_name,
                hv.is_helpful as user_vote
            FROM reviews r
            JOIN users u ON r.user_id = u.id
            LEFT JOIN helpful_votes hv
                ON hv.review_id = r.id AND hv.user_id = ?
            WHERE r.artwork_id = ? AND r.status != 'rejected'
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param("iiii", $user_id, $artwork_id, $limit, $offset);
    } else {
        $stmt = $conn->prepare("
            SELECT 
                r.id,
                r.rating,
                r.comment,
                r.is_fake,
                r.fake_confidence,
                r.helpful_count,
                r.unhelpful_count,
                r.verified_purchase,
                r.is_authentic,
                r.authenticity_score,
                r.status,
                r.created_at,
                u.name as user_name
            FROM reviews r
            JOIN users u ON r.user_id = u.id
            WHERE r.artwork_id = ? AND r.status != 'rejected'
            ORDER BY r.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bind_param("iii", $artwork_id, $limit, $offset);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    
    $reviews = [];
    while ($row = $result->fetch_assoc()) {
        $user_vote = null;
        if (array_key_exists('user_vote', $row) && $row['user_vote'] !== null) {
            $user_vote = intval($row['user_vote']); // 1 helpful, 0 unhelpful
        }
        $reviews[] = [
            'id'                => $row['id'],
            'user_name'         => htmlspecialchars($row['user_name']),
            'rating'            => intval($row['rating']),
            'comment'           => htmlspecialchars($row['comment']),
            'is_fake'           => boolval($row['is_fake']),
            'fake_confidence'   => floatval($row['fake_confidence']),
            'helpful_count'     => intval($row['helpful_count']),
            'unhelpful_count'   => intval($row['unhelpful_count'] ?? 0),
            'verified_purchase' => boolval($row['verified_purchase']),
            'is_authentic'      => boolval($row['is_authentic']),
            'authenticity_score'=> floatval($row['authenticity_score'] ?? 0),
            'status'            => $row['status'],   // needed for flagged-badge logic in JS
            'user_vote'         => $user_vote,
            'created_at'        => $row['created_at']
        ];
    }

    // Calculate average rating and authenticity stats
    // Use approved reviews for rating stats, but all visible reviews for other stats
    $stmt = $conn->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as count FROM reviews WHERE artwork_id = ? AND approved = 1");
    $stmt->bind_param("i", $artwork_id);
    $stmt->execute();
    $approved_stats = $stmt->get_result()->fetch_assoc();

    // Get total visible reviews (same as pagination total)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reviews WHERE artwork_id = ? AND status != 'rejected'");
    $stmt->bind_param("i", $artwork_id);
    $stmt->execute();
    $total_visible = $stmt->get_result()->fetch_assoc()['count'];

    // Get authenticity breakdown (use all visible reviews)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reviews WHERE artwork_id = ? AND status != 'rejected' AND is_authentic = 1");
    $stmt->bind_param("i", $artwork_id);
    $stmt->execute();
    $authentic_count = $stmt->get_result()->fetch_assoc()['count'];

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reviews WHERE artwork_id = ? AND status != 'rejected' AND is_fake = 1");
    $stmt->bind_param("i", $artwork_id);
    $stmt->execute();
    $suspicious_count = $stmt->get_result()->fetch_assoc()['count'];

    // Also count reviews that have been flagged by risk analysis as suspicious
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reviews WHERE artwork_id = ? AND status != 'rejected' AND status = 'flagged'");
    $stmt->bind_param("i", $artwork_id);
    $stmt->execute();
    $flagged_count = $stmt->get_result()->fetch_assoc()['count'];

    // Total suspicious = ML-detected fake + risk analysis flagged
    $suspicious_count = $suspicious_count + $flagged_count;

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM reviews WHERE artwork_id = ? AND status != 'rejected' AND verified_purchase = 1");
    $stmt->bind_param("i", $artwork_id);
    $stmt->execute();
    $verified_count = $stmt->get_result()->fetch_assoc()['count'];

    // compute average authenticity score (0.0-1.0) from all visible reviews
    $stmt = $conn->prepare("SELECT AVG(authenticity_score) as avg_authentic FROM reviews WHERE artwork_id = ? AND status != 'rejected'");
    $stmt->bind_param("i", $artwork_id);
    $stmt->execute();
    $avg_authentic = floatval($stmt->get_result()->fetch_assoc()['avg_authentic']);

    echo json_encode([
        'success' => true,
        'reviews' => $reviews,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => intval($total),
            'pages' => ceil($total / $limit)
        ],
        'stats' => [
            'average_rating' => round(floatval($approved_stats['avg_rating']), 1),
            'total_reviews' => intval($total_visible),
            'authentic_reviews' => intval($authentic_count),
            'suspicious_reviews' => intval($suspicious_count),
            'verified_purchase_reviews' => intval($verified_count),
            'average_authenticity' => $avg_authentic
        ]
    ]);
}

/**
 * Handle deleting a review (only by owner or admin)
 */
function handleDeleteReview() {
    global $conn, $user_id;

    $review_id = intval($_POST['review_id'] ?? 0);

    if (!$review_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'review_id required']);
        return;
    }

    // Check if review belongs to user or user is admin
    $stmt = $conn->prepare("SELECT u.role FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.id = ?");
    $stmt->bind_param("i", $review_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if (!$row) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Review not found']);
        return;
    }

    // Check permissions
    $stmt = $conn->prepare("SELECT user_id FROM reviews WHERE id = ?");
    $stmt->bind_param("i", $review_id);
    $stmt->execute();
    $review = $stmt->get_result()->fetch_assoc();

    if ($review['user_id'] != $user_id && $row['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only delete your own reviews']);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM reviews WHERE id = ?");
    $stmt->bind_param("i", $review_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Review deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Handle updating a review
 */
function handleUpdateReview() {
    global $conn, $user_id;

    $review_id = intval($_POST['review_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if (!$review_id || !$rating || !$comment) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }

    // Verify ownership
    $stmt = $conn->prepare("SELECT user_id FROM reviews WHERE id = ?");
    $stmt->bind_param("i", $review_id);
    $stmt->execute();
    $review = $stmt->get_result()->fetch_assoc();

    if (!$review || $review['user_id'] != $user_id) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You can only update your own reviews']);
        return;
    }

    $stmt = $conn->prepare("UPDATE reviews SET rating = ?, comment = ? WHERE id = ?");
    $stmt->bind_param("isi", $rating, $comment, $review_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Review updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
}

/**
 * Handle marking review as helpful
 */
function handleHelpful() {
    global $conn, $user_id;

    $review_id = intval($_POST['review_id'] ?? 0);
    $is_helpful = intval($_POST['is_helpful'] ?? 1);

    if (!$review_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'review_id required']);
        return;
    }

    // Ensure helpful_votes table exists (older installs may not have it)
    $hasVoteTable = false;
    $tableCheck = $conn->query("SHOW TABLES LIKE 'helpful_votes'");
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $hasVoteTable = true;
    } else {
        $createSql = "
            CREATE TABLE IF NOT EXISTS helpful_votes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                review_id INT NOT NULL,
                user_id INT NOT NULL,
                is_helpful TINYINT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                UNIQUE KEY unique_voter_review (user_id, review_id),
                INDEX idx_review (review_id),
                INDEX idx_user (user_id)
            )
        ";
        // If creation succeeds, enable vote tracking
        if ($conn->query($createSql)) {
            $hasVoteTable = true;
        }
    }

    // if user not logged in or vote tracking unavailable, just increment the counter
    if (!$user_id || !$hasVoteTable) {
        if ($is_helpful) {
            $stmt = $conn->prepare("UPDATE reviews SET helpful_count = helpful_count + 1 WHERE id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE reviews SET unhelpful_count = unhelpful_count + 1 WHERE id = ?");
        }
        $stmt->bind_param("i", $review_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Thank you for your feedback']);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
        return;
    }

    // Logged-in user & vote table exists: track individual vote (toggle / switch supported)
    $conn->begin_transaction();
    try {
        // Lock the review row so counters stay consistent under concurrent votes
        $lockStmt = $conn->prepare("SELECT id FROM reviews WHERE id = ? FOR UPDATE");
        $lockStmt->bind_param("i", $review_id);
        $lockStmt->execute();
        if (!$lockStmt->get_result()->fetch_assoc()) {
            $conn->rollback();
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Review not found']);
            return;
        }

        // find existing vote
        $stmt = $conn->prepare("SELECT is_helpful FROM helpful_votes WHERE review_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $review_id, $user_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        $current_vote = null;
        if ($existing) {
            $existingVote = intval($existing['is_helpful']);

            // same vote requested -> remove (toggle off)
            if ($existingVote === $is_helpful) {
                $del = $conn->prepare("DELETE FROM helpful_votes WHERE review_id = ? AND user_id = ?");
                $del->bind_param("ii", $review_id, $user_id);
                $del->execute();

                if ($is_helpful) {
                    $upd = $conn->prepare("UPDATE reviews SET helpful_count = GREATEST(helpful_count - 1, 0) WHERE id = ?");
                } else {
                    $upd = $conn->prepare("UPDATE reviews SET unhelpful_count = GREATEST(unhelpful_count - 1, 0) WHERE id = ?");
                }
                $upd->bind_param("i", $review_id);
                $upd->execute();
                $current_vote = null;
            } else {
                // different vote -> switch
                $sw = $conn->prepare("UPDATE helpful_votes SET is_helpful = ? WHERE review_id = ? AND user_id = ?");
                $sw->bind_param("iii", $is_helpful, $review_id, $user_id);
                $sw->execute();

                if ($is_helpful) {
                    $upd = $conn->prepare("
                        UPDATE reviews
                        SET helpful_count = helpful_count + 1,
                            unhelpful_count = GREATEST(unhelpful_count - 1, 0)
                        WHERE id = ?
                    ");
                } else {
                    $upd = $conn->prepare("
                        UPDATE reviews
                        SET unhelpful_count = unhelpful_count + 1,
                            helpful_count = GREATEST(helpful_count - 1, 0)
                        WHERE id = ?
                    ");
                }
                $upd->bind_param("i", $review_id);
                $upd->execute();
                $current_vote = $is_helpful;
            }
        } else {
            // no existing vote -> insert and increment
            $ins = $conn->prepare("INSERT INTO helpful_votes (review_id, user_id, is_helpful) VALUES (?, ?, ?)");
            $ins->bind_param("iii", $review_id, $user_id, $is_helpful);
            $ins->execute();

            if ($is_helpful) {
                $upd = $conn->prepare("UPDATE reviews SET helpful_count = helpful_count + 1 WHERE id = ?");
            } else {
                $upd = $conn->prepare("UPDATE reviews SET unhelpful_count = unhelpful_count + 1 WHERE id = ?");
            }
            $upd->bind_param("i", $review_id);
            $upd->execute();
            $current_vote = $is_helpful;
        }

        $countsStmt = $conn->prepare("SELECT helpful_count, unhelpful_count FROM reviews WHERE id = ?");
        $countsStmt->bind_param("i", $review_id);
        $countsStmt->execute();
        $counts = $countsStmt->get_result()->fetch_assoc();

        $conn->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Thank you for your feedback',
            'helpful_count' => intval($counts['helpful_count'] ?? 0),
            'unhelpful_count' => intval($counts['unhelpful_count'] ?? 0),
            'current_vote' => $current_vote
        ]);
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('handleHelpful error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error saving vote']);
    }
}

/**
 * Check if user can review the artwork (must have purchased it)
 */
function handleCheckCanReview() {
    global $conn, $user_id;

    $artwork_id = intval($_GET['artwork_id'] ?? 0);

    if (!$artwork_id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'artwork_id required']);
        return;
    }

    if (!$user_id) {
        echo json_encode([
            'success' => true,
            'canReview' => false,
            'reason' => 'not_logged_in'
        ]);
        return;
    }

    // Check if user has purchased this artwork
    // Accept paid orders with any non-cancelled status (covers older orders where status stayed 'pending')
    $stmt = $conn->prepare("
        SELECT COUNT(*) as purchase_count 
        FROM order_items oi
        JOIN orders o ON oi.order_id = o.id
        WHERE oi.artwork_id = ? AND o.user_id = ?
          AND o.payment_status = 'paid'
          AND o.status != 'cancelled'
    ");
    $stmt->bind_param("ii", $artwork_id, $user_id);
    $stmt->execute();
    $purchase = $stmt->get_result()->fetch_assoc();

    // Check if user already reviewed this artwork
    $stmt = $conn->prepare("SELECT id FROM reviews WHERE artwork_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $artwork_id, $user_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        echo json_encode([
            'success' => true,
            'canReview' => false,
            'reason' => 'already_reviewed'
        ]);
    } elseif ($purchase['purchase_count'] > 0) {
        echo json_encode([
            'success' => true,
            'canReview' => true,
            'reason' => 'purchased'
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'canReview' => false,
            'reason' => 'not_purchased'
        ]);
    }
}

