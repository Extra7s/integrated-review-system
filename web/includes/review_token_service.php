<?php
/**
 * Review Token & Verification Service
 * Handles one-time review tokens for verified purchases
 * Validates tokens on review submission
 */

class ReviewTokenService {
    private $conn;

    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }

    /**
     * Generate review tokens for a completed order
     */
    public function generateTokensForOrder($order_id) {
        // Get order items
        $stmt = $this->conn->prepare("
            SELECT oi.artwork_id FROM order_items oi
            WHERE oi.order_id = ?
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $result = $stmt->get_result();

        // Get user_id from order
        $stmt = $this->conn->prepare("SELECT user_id FROM orders WHERE id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        
        if (!$order) return false;

        $user_id = $order['user_id'];
        $tokens = [];

        // Generate one token per artwork
        while ($item = $result->fetch_assoc()) {
            $artwork_id = $item['artwork_id'];
            $token = $this->generateUniqueToken($user_id, $artwork_id);
            
            $stmt = $this->conn->prepare("
                INSERT INTO review_tokens (token, order_id, user_id, artwork_id, expires_at)
                VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 YEAR))
            ");
            $stmt->bind_param("siii", $token, $order_id, $user_id, $artwork_id);
            $stmt->execute();
            
            $tokens[] = [
                'artwork_id' => $artwork_id,
                'token' => $token
            ];
        }

        return $tokens;
    }

    /**
     * Validate and consume a review token
     */
    public function validateAndConsumeToken($token, $user_id, $artwork_id, $review_id) {
        $stmt = $this->conn->prepare("
            SELECT id, is_used, expires_at FROM review_tokens
            WHERE token = ? AND user_id = ? AND artwork_id = ?
        ");
        $stmt->bind_param("sii", $token, $user_id, $artwork_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            return [
                'valid' => false,
                'reason' => 'Token not found or invalid for this artwork'
            ];
        }

        $token_data = $result->fetch_assoc();

        // Check if already used
        if ($token_data['is_used']) {
            return [
                'valid' => false,
                'reason' => 'This token has already been used'
            ];
        }

        // Check if expired
        if (strtotime($token_data['expires_at']) < time()) {
            return [
                'valid' => false,
                'reason' => 'This token has expired'
            ];
        }

        // Token is valid - consume it
        $stmt = $this->conn->prepare("
            UPDATE review_tokens 
            SET is_used = 1, used_at = NOW(), used_for_review_id = ?
            WHERE token = ?
        ");
        $stmt->bind_param("is", $review_id, $token);
        $stmt->execute();

        return [
            'valid' => true,
            'token_id' => $token_data['id']
        ];
    }

    /**
     * Check if user has valid token for artwork
     */
    public function hasValidToken($user_id, $artwork_id) {
        $stmt = $this->conn->prepare("
            SELECT id FROM review_tokens
            WHERE user_id = ? AND artwork_id = ? AND is_used = 0
            AND expires_at > NOW()
            LIMIT 1
        ");
        $stmt->bind_param("ii", $user_id, $artwork_id);
        $stmt->execute();
        
        return $stmt->get_result()->num_rows > 0;
    }

    /**
     * Generate unique token
     */
    private function generateUniqueToken($user_id, $artwork_id) {
        do {
            $token = bin2hex(random_bytes(32));
            $stmt = $this->conn->prepare("SELECT id FROM review_tokens WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
        } while ($stmt->get_result()->num_rows > 0);

        return $token;
    }

    /**
     * Mark review as verified purchase
     */
    public function markVerifiedPurchase($review_id, $user_id) {
        // Check if user actually purchased this artwork
        $stmt = $this->conn->prepare("
            SELECT r.artwork_id FROM reviews r WHERE r.id = ?
        ");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
        $review = $stmt->get_result()->fetch_assoc();

        if (!$review) return false;

        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            WHERE oi.artwork_id = ? AND o.user_id = ? AND o.payment_status = 'paid'
        ");
        $stmt->bind_param("ii", $review['artwork_id'], $user_id);
        $stmt->execute();
        $purchase = $stmt->get_result()->fetch_assoc();

        if ($purchase['count'] > 0) {
            $stmt = $this->conn->prepare("UPDATE reviews SET verified_purchase = 1 WHERE id = ?");
            $stmt->bind_param("i", $review_id);
            return $stmt->execute();
        }

        return false;
    }

    /**
     * Track helpful votes
     */
    public function recordVote($review_id, $user_id, $is_helpful) {
        // Check if user already voted
        $stmt = $this->conn->prepare("
            SELECT id FROM helpful_votes WHERE review_id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $review_id, $user_id);
        $stmt->execute();

        if ($stmt->get_result()->num_rows > 0) {
            return [
                'success' => false,
                'message' => 'You have already voted on this review'
            ];
        }

        // Insert vote
        $stmt = $this->conn->prepare("
            INSERT INTO helpful_votes (review_id, user_id, is_helpful)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iii", $review_id, $user_id, $is_helpful);
        
        if ($stmt->execute()) {
            // Update helpful/unhelpful counts
            if ($is_helpful) {
                $stmt = $this->conn->prepare("UPDATE reviews SET helpful_count = helpful_count + 1 WHERE id = ?");
            } else {
                $stmt = $this->conn->prepare("UPDATE reviews SET unhelpful_count = unhelpful_count + 1 WHERE id = ?");
            }
            $stmt->bind_param("i", $review_id);
            $stmt->execute();

            return [
                'success' => true,
                'message' => 'Vote recorded'
            ];
        }

        return [
            'success' => false,
            'message' => 'Error recording vote'
        ];
    }

    /**
     * Get vote statistics for review
     */
    public function getVoteStats($review_id) {
        $stmt = $this->conn->prepare("
            SELECT 
                helpful_count,
                unhelpful_count
            FROM reviews WHERE id = ?
        ");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if (!$result) return null;

        return [
            'helpful' => intval($result['helpful_count']),
            'unhelpful' => intval($result['unhelpful_count']),
            'total_votes' => intval($result['helpful_count']) + intval($result['unhelpful_count']),
            'helpfulness_ratio' => intval($result['helpful_count']) + intval($result['unhelpful_count']) > 0 
                ? intval($result['helpful_count']) / (intval($result['helpful_count']) + intval($result['unhelpful_count']))
                : 0
        ];
    }

    /**
     * Check user vote on review
     */
    public function getUserVote($review_id, $user_id) {
        $stmt = $this->conn->prepare("
            SELECT is_helpful FROM helpful_votes WHERE review_id = ? AND user_id = ?
        ");
        $stmt->bind_param("ii", $review_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        return $result ? intval($result['is_helpful']) : null;
    }
}
?>
