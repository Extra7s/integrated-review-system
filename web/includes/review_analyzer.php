<?php
/**
 * Advanced Review Analysis and Detection
 * Handles:
 * - Duplicate/near-duplicate detection using TF-IDF + Cosine Similarity
 * - Review burst detection (spike in reviews)
 * - Risk scoring with breakdown
 */

require_once __DIR__ . "/db.php";

class ReviewAnalyzer {
    private $conn;
    private $stopwords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'is', 'was', 'are', 'be', 'been', 'by', 'from'];

    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }

    /**
     * Calculate risk score for a review based on multiple factors
     */
    public function calculateRiskScore($review_id) {
        $review = $this->getReviewData($review_id);
        if (!$review) return null;

        $risk_factors = [];
        $risk_score = 0;

        // 1. Text similarity to other reviews (30 points)
        $similarity_risk = $this->checkDuplicateReviews($review_id);
        if ($similarity_risk > 0) {
            $risk_score += $similarity_risk;
            $risk_factors[] = [
                'factor' => 'duplicate_review',
                'score' => $similarity_risk,
                'description' => 'Similar or duplicate content detected'
            ];
        }

        // 2. Account age risk (20 points)
        $age_risk = $this->checkAccountAge($review['user_id']);
        if ($age_risk > 0) {
            $risk_score += $age_risk;
            $risk_factors[] = [
                'factor' => 'new_account',
                'score' => $age_risk,
                'description' => 'Account is very new'
            ];
        }

        // 3. Review burst on product (25 points)
        $burst_risk = $this->checkProductBurst($review['artwork_id'], $review['created_at']);
        if ($burst_risk > 0) {
            $risk_score += $burst_risk;
            $risk_factors[] = [
                'factor' => 'review_burst',
                'score' => $burst_risk,
                'description' => 'Suspicious spike in reviews for this product'
            ];
        }

        // 4. User burst posting (20 points)
        $user_burst_risk = $this->checkUserBurst($review['user_id'], $review['created_at']);
        if ($user_burst_risk > 0) {
            $risk_score += $user_burst_risk;
            $risk_factors[] = [
                'factor' => 'user_burst',
                'score' => $user_burst_risk,
                'description' => 'User posted multiple reviews in a short time'
            ];
        }

        // 5. Rating patterns (15 points)
        $pattern_risk = $this->checkRatingPatterns($review['user_id']);
        if ($pattern_risk > 0) {
            $risk_score += $pattern_risk;
            $risk_factors[] = [
                'factor' => 'suspicious_pattern',
                'score' => $pattern_risk,
                'description' => 'Unusual rating patterns detected'
            ];
        }

        // 6. ML fake review detection (if available) (30 points)
        $ml_risk = intval($review['fake_confidence'] * 30);
        if ($ml_risk > 0) {
            $risk_score += $ml_risk;
            $risk_factors[] = [
                'factor' => 'ml_detection',
                'score' => $ml_risk,
                'description' => 'ML model flagged as potentially fake'
            ];
        }

        // Cap at 100
        $risk_score = min(100, $risk_score);

        return [
            'risk_score' => $risk_score,
            'risk_factors' => $risk_factors,
            'should_flag' => $risk_score >= 50
        ];
    }

    /**
     * Check for duplicate/near-duplicate reviews using TF-IDF + Cosine Similarity
     */
    public function checkDuplicateReviews($review_id, $threshold = 0.70) {
        $review = $this->getReviewData($review_id);
        if (!$review) return 0;

        // Get all other reviews
        $stmt = $this->conn->prepare("
            SELECT id, comment FROM reviews 
            WHERE id != ? AND artwork_id = ? AND approved = 1
            ORDER BY created_at DESC LIMIT 20
        ");
        $stmt->bind_param("ii", $review_id, $review['artwork_id']);
        $stmt->execute();
        $result = $stmt->get_result();

        $max_similarity = 0;
        $review_comment = $review['comment'];
        $review_tfidf = $this->getTFIDF($review_comment);

        while ($row = $result->fetch_assoc()) {
            $other_tfidf = $this->getTFIDF($row['comment']);
            $similarity = $this->cosineSimilarity($review_tfidf, $other_tfidf);

            if ($similarity > $threshold) {
                // Store similarity score
                $this->storeSimilarity($review_id, $row['id'], $similarity);
                $max_similarity = max($max_similarity, $similarity);
            }
        }

        // Also check across other products by same user
        $stmt = $this->conn->prepare("
            SELECT id, comment FROM reviews 
            WHERE user_id = ? AND id != ? AND approved = 1
            ORDER BY created_at DESC LIMIT 10
        ");
        $stmt->bind_param("ii", $review['user_id'], $review_id);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $other_tfidf = $this->getTFIDF($row['comment']);
            $similarity = $this->cosineSimilarity($review_tfidf, $other_tfidf);

            if ($similarity > 0.85) { // Higher threshold for cross-product duplicates
                $this->storeSimilarity($review_id, $row['id'], $similarity);
                $max_similarity = max($max_similarity, $similarity);
            }
        }

        // Return risk based on similarity
        if ($max_similarity >= 0.95) return 30; // Very similar
        if ($max_similarity >= 0.85) return 20;
        if ($max_similarity >= 0.70) return 10;
        return 0;
    }

    /**
     * Detect review spikes on a product
     */
    public function detectProductBurst($artwork_id, $time_window_minutes = 60) {
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(*) as review_count,
                AVG(rating) as avg_rating,
                MAX(created_at) as latest,
                MIN(created_at) as earliest
            FROM reviews 
            WHERE artwork_id = ? 
            AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
            AND approved = 1
        ");
        $stmt->bind_param("ii", $artwork_id, $time_window_minutes);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        $review_count = intval($result['review_count']);
        $avg_rating = floatval($result['avg_rating']);

        // Get historical average
        $stmt = $this->conn->prepare("
            SELECT AVG(rating) as historical_avg FROM reviews 
            WHERE artwork_id = ? AND approved = 1
        ");
        $stmt->bind_param("i", $artwork_id);
        $stmt->execute();
        $historical = $stmt->get_result()->fetch_assoc();
        $historical_avg = floatval($historical['historical_avg']);

        $burst_score = 0;
        $is_suspicious = false;

        // Burst detected if >5 reviews in the time window
        if ($review_count > 5) {
            $burst_score = min(25, $review_count * 3);

            // Additional suspicion if ratings are extreme (all 5 stars or all 1 star)
            if (abs($avg_rating - 5) < 0.1 || abs($avg_rating - 1) < 0.1) {
                $burst_score = 25;
                $is_suspicious = true;
            }

            // Store burst record
            $this->storeBurst($artwork_id, $review_count, $avg_rating, $burst_score, $is_suspicious);
        }

        return [
            'burst_detected' => $review_count > 5,
            'review_count' => $review_count,
            'burst_score' => $burst_score,
            'is_suspicious' => $is_suspicious,
            'avg_rating' => $avg_rating,
            'historical_avg' => $historical_avg
        ];
    }

    /**
     * Check if user account is very new
     */
    private function checkAccountAge($user_id) {
        $stmt = $this->conn->prepare("
            SELECT DATEDIFF(NOW(), created_at) as age_days FROM users WHERE id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $age_days = intval($result['age_days']);

        if ($age_days <= 7) return 20;    // Brand new account
        if ($age_days <= 30) return 10;   // Recently created
        return 0;
    }

    /**
     * Check for review burst by user (posting multiple reviews quickly)
     */
    private function checkUserBurst($user_id, $review_created_at) {
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count FROM reviews 
            WHERE user_id = ? 
            AND created_at >= DATE_SUB(?, INTERVAL 24 HOUR)
            AND created_at <= DATE_ADD(?, INTERVAL 1 HOUR)
        ");
        $stmt->bind_param("iss", $user_id, $review_created_at, $review_created_at);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $count = intval($result['count']);

        if ($count > 5) return 20;
        if ($count > 3) return 10;
        return 0;
    }

    /**
     * Check for suspicious rating patterns
     */
    private function checkRatingPatterns($user_id) {
        $stmt = $this->conn->prepare("
            SELECT rating FROM reviews WHERE user_id = ? ORDER BY created_at DESC LIMIT 20
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $ratings = [];
        while ($row = $result->fetch_assoc()) {
            $ratings[] = intval($row['rating']);
        }

        if (count($ratings) < 3) return 0;

        // All 5 stars or all 1 star is suspicious
        if (count(array_unique($ratings)) === 1) {
            return 15;
        }

        // Alternating extreme ratings
        $count_5 = count(array_filter($ratings, fn($r) => $r == 5));
        $count_1 = count(array_filter($ratings, fn($r) => $r == 1));

        if ($count_5 + $count_1 === count($ratings)) {
            return 10;
        }

        return 0;
    }

    /**
     * Check for product burst and return risk score
     */
    private function checkProductBurst($artwork_id, $review_created_at) {
        $burst = $this->detectProductBurst($artwork_id, 60);
        return $burst['is_suspicious'] ? $burst['burst_score'] : 0;
    }

    /**
     * Calculate TF-IDF vector for text
     */
    private function getTFIDF($text) {
        $text = strtolower($text);
        $words = preg_split('/\W+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Remove stopwords
        $words = array_filter($words, fn($w) => !in_array($w, $this->stopwords) && strlen($w) > 2);
        
        // Count occurrences
        $tf = array_count_values($words);
        
        // Normalize
        $doc_length = count($words);
        if ($doc_length === 0) return [];
        
        foreach ($tf as &$count) {
            $count = $count / $doc_length;
        }
        
        return $tf;
    }

    /**
     * Calculate cosine similarity between two TF-IDF vectors
     */
    private function cosineSimilarity($vec1, $vec2) {
        if (empty($vec1) || empty($vec2)) return 0;

        $dot_product = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;

        // Calculate dot product and magnitudes
        foreach ($vec1 as $term => $val1) {
            $magnitude1 += $val1 * $val1;
            if (isset($vec2[$term])) {
                $dot_product += $val1 * $vec2[$term];
            }
        }

        foreach ($vec2 as $val2) {
            $magnitude2 += $val2 * $val2;
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 === 0 || $magnitude2 === 0) return 0;

        return $dot_product / ($magnitude1 * $magnitude2);
    }

    /**
     * Store similarity scores in database
     */
    private function storeSimilarity($review_id_1, $review_id_2, $similarity) {
        $stmt = $this->conn->prepare("
            INSERT IGNORE INTO review_similarities (review_id_1, review_id_2, similarity_score)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param("iid", $review_id_1, $review_id_2, $similarity);
        return $stmt->execute();
    }

    /**
     * Store burst detection results
     */
    private function storeBurst($artwork_id, $review_count, $avg_rating, $burst_score, $is_suspicious) {
        $stmt = $this->conn->prepare("
            INSERT INTO product_review_bursts (artwork_id, review_count, average_rating, burst_score, is_suspicious)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iidii", $artwork_id, $review_count, $avg_rating, $burst_score, $is_suspicious);
        return $stmt->execute();
    }

    /**
     * Get review data
     */
    private function getReviewData($review_id) {
        $stmt = $this->conn->prepare("
            SELECT id, artwork_id, user_id, comment, rating, fake_confidence, created_at 
            FROM reviews WHERE id = ?
        ");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Update review with risk score
     */
    public function updateReviewRiskScore($review_id, $risk_data) {
        $risk_factors_json = json_encode($risk_data['risk_factors']);

        // Only update status if the review should be flagged for suspicious activity
        // Leave legitimate reviews as 'pending' for manual admin approval
        $status = $risk_data['should_flag'] ? 'flagged' : null;
        $flagged_reason = $risk_data['should_flag'] ? $this->generateFlagReason($risk_data['risk_factors']) : NULL;

        if ($status === 'flagged') {
            // Only update status and flagged_reason if flagging the review
            $stmt = $this->conn->prepare("
                UPDATE reviews
                SET risk_score = ?, risk_factors = ?, status = ?, flagged_reason = ?
                WHERE id = ?
            ");
            $stmt->bind_param("isssi", $risk_data['risk_score'], $risk_factors_json, $status, $flagged_reason, $review_id);
        } else {
            // For non-flagged reviews, only update risk score and factors, keep status as 'pending'
            $stmt = $this->conn->prepare("
                UPDATE reviews
                SET risk_score = ?, risk_factors = ?
                WHERE id = ?
            ");
            $stmt->bind_param("isi", $risk_data['risk_score'], $risk_factors_json, $review_id);
        }

        return $stmt->execute();
    }

    /**
     * Generate human-readable flag reason
     */
    private function generateFlagReason($risk_factors) {
        $reasons = [];
        foreach ($risk_factors as $factor) {
            if ($factor['score'] > 0) {
                $reasons[] = $factor['description'];
            }
        }
        return implode('; ', $reasons);
    }

    /**
     * Calculate user risk profile
     */
    public function calculateUserRiskProfile($user_id) {
        // Get basic stats
        $stmt = $this->conn->prepare("
            SELECT 
                COUNT(*) as review_count,
                SUM(CASE WHEN status = 'flagged' THEN 1 ELSE 0 END) as flagged_count,
                AVG(rating) as avg_rating,
                AVG(risk_score) as avg_risk
            FROM reviews WHERE user_id = ?
        ");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stats = $stmt->get_result()->fetch_assoc();

        // Calculate account age
        $stmt = $this->conn->prepare("SELECT DATEDIFF(NOW(), created_at) as age_days FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        // Calculate risk score
        $risk_score = 0;
        if (intval($stats['review_count']) > 0) {
            $flagged_ratio = intval($stats['flagged_count']) / intval($stats['review_count']);
            $risk_score = intval($flagged_ratio * 50 + intval($stats['avg_risk']));
        }

        return [
            'user_id' => $user_id,
            'risk_score' => min(100, $risk_score),
            'review_count' => intval($stats['review_count']),
            'flagged_review_count' => intval($stats['flagged_count']),
            'average_rating' => floatval($stats['avg_rating']),
            'account_age_days' => intval($user['age_days'])
        ];
    }

    /**
     * Store user risk profile
     */
    public function updateUserRiskProfile($user_id) {
        $profile = $this->calculateUserRiskProfile($user_id);

        $stmt = $this->conn->prepare("
            INSERT INTO user_risk_profiles (user_id, risk_score, review_count, flagged_review_count, average_rating, account_age_days)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                risk_score = VALUES(risk_score),
                review_count = VALUES(review_count),
                flagged_review_count = VALUES(flagged_review_count),
                average_rating = VALUES(average_rating),
                account_age_days = VALUES(account_age_days),
                last_calculated = NOW()
        ");
        $stmt->bind_param("iiiidi", $profile['user_id'], $profile['risk_score'], $profile['review_count'], 
                         $profile['flagged_review_count'], $profile['average_rating'], $profile['account_age_days']);
        return $stmt->execute();
    }
}
?>
