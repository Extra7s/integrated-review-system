<?php
/**
 * ReviewTokenService
 * Handles one-time review tokens for verified purchases
 * Ensures each purchase can only generate one review per artwork
 */

class ReviewTokenService {
    private $conn;
    private $token_expiration_days = 365; // Tokens valid for 1 year

    public function __construct($connection) {
        $this->conn = $connection;
    }

    /**
     * Generate tokens for each artwork in an order
     * Called after payment is confirmed
     */
    public function generateTokensForOrder($order_id) {
        error_log("=== generateTokensForOrder START: order_id=$order_id ===");
        try {
            // Get order and user ID
            error_log("Step 1: Fetching order with id=$order_id");
            $stmt = $this->conn->prepare("SELECT user_id FROM orders WHERE id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $order_result = $stmt->get_result()->fetch_assoc();
            
            if (!$order_result) {
                error_log("ERROR: Order not found with id=$order_id");
                throw new Exception("Order not found: $order_id");
            }
            
            $user_id = $order_result['user_id'];
            error_log("Step 2: Order found. user_id=$user_id, order_id=$order_id");
            
            // Get order items
            error_log("Step 3: Fetching order items for order_id=$order_id");
            $stmt = $this->conn->prepare("
                SELECT DISTINCT oi.artwork_id 
                FROM order_items oi 
                WHERE oi.order_id = ?
            ");
            if (!$stmt) {
                throw new Exception("Prepare order_items failed: " . $this->conn->error);
            }
            $stmt->bind_param("i", $order_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $items_array = [];
            while ($row = $result->fetch_assoc()) {
                $items_array[] = $row['artwork_id'];
            }
            $items_count = count($items_array);
            error_log("Step 4: Found $items_count order items. artwork_ids=[" . implode(',', $items_array) . "]");

            $tokens_generated = 0;
            foreach ($items_array as $artwork_id) {
                error_log("Step 5: Generating token for artwork_id=$artwork_id");
                $token = $this->generateUniqueToken();
                $expires_at = date('Y-m-d H:i:s', strtotime("+" . $this->token_expiration_days . " days"));
                
                error_log("Step 6: Token generated. token=" . substr($token, 0, 10) . "..., expires_at=$expires_at");

                $stmt = $this->conn->prepare("
                    INSERT INTO review_tokens 
                    (order_id, user_id, artwork_id, token, expires_at, created_at) 
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                if (!$stmt) {
                    error_log("ERROR: Prepare insert failed: " . $this->conn->error);
                    throw new Exception("Prepare insert failed: " . $this->conn->error);
                }
                
                $stmt->bind_param("iiiss", $order_id, $user_id, $artwork_id, $token, $expires_at);
                if ($stmt->execute()) {
                    $tokens_generated++;
                    error_log("Step 7: Token inserted successfully. Generated tokens so far: $tokens_generated");
                } else {
                    error_log("Step 7: INSERT FAILED for artwork_id=$artwork_id. Error: " . $stmt->error);
                    throw new Exception("Insert token failed: " . $stmt->error);
                }
            }

            error_log("=== generateTokensForOrder SUCCESS: Generated $tokens_generated review tokens for order $order_id ===");
            return $tokens_generated;
        } catch (Exception $e) {
            error_log("=== generateTokensForOrder EXCEPTION: " . $e->getMessage() . " ===");
            throw $e;
        }
    }

    /**
     * Validate and consume a review token
     * Returns array with validation result
     */
    public function validateAndConsumeToken($token, $user_id, $artwork_id, $review_id) {
        try {
            // Get the token
            $stmt = $this->conn->prepare("
                SELECT id, is_used, expires_at, user_id
                FROM review_tokens
                WHERE token = ? 
                AND artwork_id = ? 
                AND user_id = ?
            ");
            $stmt->bind_param("sii", $token, $artwork_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $token_record = $result->fetch_assoc();

            // Validate token exists
            if (!$token_record) {
                return [
                    'valid' => false,
                    'error' => 'Invalid token for this artwork',
                    'reason' => 'token_not_found'
                ];
            }

            // Check if already used
            if ($token_record['is_used']) {
                return [
                    'valid' => false,
                    'error' => 'This token has already been used for a review',
                    'reason' => 'token_already_used'
                ];
            }

            // Check expiration
            if (strtotime($token_record['expires_at']) < time()) {
                return [
                    'valid' => false,
                    'error' => 'This token has expired',
                    'reason' => 'token_expired'
                ];
            }

            // Mark token as used
            $stmt = $this->conn->prepare("
                UPDATE review_tokens 
                SET is_used = 1, used_for_review_id = ?, used_at = NOW() 
                WHERE id = ?
            ");
            $stmt->bind_param("ii", $review_id, $token_record['id']);
            $stmt->execute();

            return [
                'valid' => true,
                'token_id' => $token_record['id'],
                'message' => 'Token validated successfully'
            ];
        } catch (Exception $e) {
            error_log("Error in validateAndConsumeToken: " . $e->getMessage());
            return [
                'valid' => false,
                'error' => 'Token validation error',
                'reason' => 'validation_error'
            ];
        }
    }

    /**
     * Check if user has a valid unused token for an artwork
     * Used for UI gating
     */
    public function hasValidToken($user_id, $artwork_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT rt.id 
                FROM review_tokens rt
                WHERE rt.user_id = ? 
                AND rt.artwork_id = ? 
                AND rt.is_used = 0 
                AND rt.expires_at > NOW()
                LIMIT 1
            ");
            $stmt->bind_param("ii", $user_id, $artwork_id);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->num_rows > 0;
        } catch (Exception $e) {
            error_log("Error in hasValidToken: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get a valid token for user/artwork combo
     * Returns token data if found, null otherwise
     */
    public function getValidToken($user_id, $artwork_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT rt.id, rt.token, rt.expires_at
                FROM review_tokens rt
                WHERE rt.user_id = ? 
                AND rt.artwork_id = ? 
                AND rt.is_used = 0 
                AND rt.expires_at > NOW()
                LIMIT 1
            ");
            $stmt->bind_param("ii", $user_id, $artwork_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $token_data = $result->fetch_assoc();
            return $token_data;
        } catch (Exception $e) {
            error_log("Error in getValidToken: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Record a helpful/unhelpful vote on a review
     */
    public function recordVote($review_id, $user_id, $is_helpful) {
        try {
            // Check if user already voted on this review
            $stmt = $this->conn->prepare("
                SELECT id FROM helpful_votes 
                WHERE review_id = ? AND user_id = ? 
                LIMIT 1
            ");
            $stmt->bind_param("ii", $review_id, $user_id);
            $stmt->execute();
            $existing = $stmt->get_result();

            if ($existing->num_rows > 0) {
                return [
                    'success' => false,
                    'error' => 'You have already voted on this review'
                ];
            }

            // Record the vote
            $stmt = $this->conn->prepare("
                INSERT INTO helpful_votes (review_id, user_id, is_helpful, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->bind_param("iii", $review_id, $user_id, $is_helpful);
            $stmt->execute();

            // Update review counters
            if ($is_helpful) {
                $stmt = $this->conn->prepare("
                    UPDATE reviews 
                    SET helpful_count = COALESCE(helpful_count, 0) + 1 
                    WHERE id = ?
                ");
            } else {
                $stmt = $this->conn->prepare("
                    UPDATE reviews 
                    SET unhelpful_count = COALESCE(unhelpful_count, 0) + 1 
                    WHERE id = ?
                ");
            }
            $stmt->bind_param("i", $review_id);
            $stmt->execute();

            return [
                'success' => true,
                'message' => 'Vote recorded successfully'
            ];
        } catch (Exception $e) {
            error_log("Error in recordVote: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to record vote'
            ];
        }
    }

    /**
     * Get vote statistics for a review
     */
    public function getVoteStats($review_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    SUM(CASE WHEN is_helpful = 1 THEN 1 ELSE 0 END) as helpful_votes,
                    SUM(CASE WHEN is_helpful = 0 THEN 1 ELSE 0 END) as unhelpful_votes,
                    COUNT(*) as total_votes
                FROM helpful_votes 
                WHERE review_id = ?
            ");
            $stmt->bind_param("i", $review_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats = $result->fetch_assoc();

            $helpful = intval($stats['helpful_votes'] ?? 0);
            $unhelpful = intval($stats['unhelpful_votes'] ?? 0);
            $total = intval($stats['total_votes'] ?? 0);

            $helpfulness_ratio = $total > 0 ? ($helpful / $total) : 0;

            return [
                'helpful' => $helpful,
                'unhelpful' => $unhelpful,
                'total_votes' => $total,
                'helpfulness_ratio' => round($helpfulness_ratio, 2)
            ];
        } catch (Exception $e) {
            error_log("Error in getVoteStats: " . $e->getMessage());
            return [
                'helpful' => 0,
                'unhelpful' => 0,
                'total_votes' => 0,
                'helpfulness_ratio' => 0
            ];
        }
    }

    /**
     * Check if user has voted on a specific review
     */
    public function hasUserVoted($review_id, $user_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT id FROM helpful_votes 
                WHERE review_id = ? AND user_id = ? 
                LIMIT 1
            ");
            $stmt->bind_param("ii", $review_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->num_rows > 0;
        } catch (Exception $e) {
            error_log("Error in hasUserVoted: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get user's vote on a review (helpful, unhelpful, or null)
     */
    public function getUserVote($review_id, $user_id) {
        try {
            $stmt = $this->conn->prepare("
                SELECT is_helpful FROM helpful_votes 
                WHERE review_id = ? AND user_id = ? 
                LIMIT 1
            ");
            $stmt->bind_param("ii", $review_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $vote = $result->fetch_assoc();
            return $vote ? $vote['is_helpful'] : null;
        } catch (Exception $e) {
            error_log("Error in getUserVote: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate a unique random token
     */
    private function generateUniqueToken() {
        // Generate 256-bit random hex token
        $token = bin2hex(random_bytes(32));

        // Ensure uniqueness
        $attempts = 0;
        while ($this->tokenExists($token) && $attempts < 5) {
            $token = bin2hex(random_bytes(32));
            $attempts++;
        }

        if ($attempts >= 5) {
            throw new Exception("Failed to generate unique token after 5 attempts");
        }

        return $token;
    }

    /**
     * Check if token already exists
     */
    private function tokenExists($token) {
        try {
            $stmt = $this->conn->prepare("
                SELECT id FROM review_tokens 
                WHERE token = ? 
                LIMIT 1
            ");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            return $result->num_rows > 0;
        } catch (Exception $e) {
            error_log("Error in tokenExists: " . $e->getMessage());
            return false;
        }
    }
}
?>
