<?php
/**
 * AdminTrainingLogger
 * Logs admin decisions for ML model retraining
 * Creates labeled training data from actual admin corrections
 */

class AdminTrainingLogger {
    private $conn;

    public function __construct($connection) {
        $this->conn = $connection;
    }

    /**
     * Log an admin decision (approval/rejection/flagging)
     * Captures the ML model's original prediction vs admin's actual decision
     */
    public function logAdminDecision($review_id, $admin_id, $admin_decision, $reason = null) {
        try {
            // Get review details including ML prediction
            $stmt = $this->conn->prepare("
                SELECT id, is_fake, fake_confidence 
                FROM reviews 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $review_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $review = $result->fetch_assoc();

            if (!$review) {
                return [
                    'success' => false,
                    'error' => 'Review not found'
                ];
            }

            // Convert ML prediction to label (1 = fake, 0 = genuine)
            $ml_label = $review['is_fake'] ? 1 : 0;

            // Convert admin decision to label
            $correct_label = in_array($admin_decision, ['rejected', 'flagged']) ? 1 : 0;

            // Insert training log
            $stmt = $this->conn->prepare("
                INSERT INTO admin_training_log 
                (review_id, admin_id, admin_decision, ml_label, correct_label, ml_confidence, reason, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->bind_param(
                "iissids",
                $review_id,
                $admin_id,
                $admin_decision,
                $ml_label,
                $correct_label,
                $review['fake_confidence'],
                $reason
            );

            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Admin decision logged for training'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to log decision'
                ];
            }
        } catch (Exception $e) {
            error_log("Error in logAdminDecision: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error logging decision: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get training data for ML retraining
     * Returns labeled examples with rich context
     */
    public function getTrainingData($since_date = null, $limit = 1000) {
        try {
            $query = "
                SELECT 
                    r.id,
                    r.comment,
                    r.rating,
                    atl.ml_label,
                    atl.correct_label,
                    atl.ml_confidence,
                    DATEDIFF(NOW(), u.created_at) as account_age_days,
                    COALESCE(urp.risk_score, 0) as risk_score,
                    COALESCE(SUM(CASE WHEN hv.is_helpful = 1 THEN 1 ELSE 0 END), 0) as helpful_votes,
                    COALESCE(SUM(CASE WHEN hv.is_helpful = 0 THEN 1 ELSE 0 END), 0) as unhelpful_votes
                FROM admin_training_log atl
                JOIN reviews r ON atl.review_id = r.id
                JOIN users u ON r.user_id = u.id
                LEFT JOIN user_risk_profiles urp ON u.id = urp.user_id
                LEFT JOIN helpful_votes hv ON r.id = hv.review_id
                ";

            if ($since_date) {
                $query .= " WHERE atl.created_at >= ? ";
            }

            $query .= " GROUP BY r.id ORDER BY atl.created_at DESC LIMIT ? ";

            $stmt = $this->conn->prepare($query);

            if ($since_date) {
                $stmt->bind_param("si", $since_date, $limit);
            } else {
                $stmt->bind_param("i", $limit);
            }

            $stmt->execute();
            $result = $stmt->get_result();

            $data = [];
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }

            return $data;
        } catch (Exception $e) {
            error_log("Error in getTrainingData: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Export training data as CSV for Python ML pipeline
     */
    public function exportTrainingDataAsCSV($output_file = null) {
        try {
            $data = $this->getTrainingData(null, 10000);

            if (empty($data)) {
                return [
                    'success' => false,
                    'error' => 'No training data available'
                ];
            }

            // Generate output file path if not provided
            if (!$output_file) {
                $timestamp = date('Y-m-d_H-i-s');
                $output_file = "/tmp/review_training_data_{$timestamp}.csv";
            }

            // Open file for writing
            $fp = fopen($output_file, 'w');
            if ($fp === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to open output file'
                ];
            }

            // Write CSV header
            $headers = [
                'review_id',
                'comment',
                'rating',
                'ml_label',
                'correct_label',
                'ml_confidence',
                'account_age_days',
                'risk_score',
                'helpful_votes',
                'unhelpful_votes'
            ];
            fputcsv($fp, $headers);

            // Write data rows
            foreach ($data as $row) {
                $csv_row = [
                    $row['id'],
                    $row['comment'],
                    $row['rating'],
                    $row['ml_label'],
                    $row['correct_label'],
                    $row['ml_confidence'],
                    $row['account_age_days'],
                    $row['risk_score'],
                    $row['helpful_votes'],
                    $row['unhelpful_votes']
                ];
                fputcsv($fp, $csv_row);
            }

            fclose($fp);

            return [
                'success' => true,
                'file_path' => $output_file,
                'record_count' => count($data),
                'message' => 'Training data exported successfully'
            ];
        } catch (Exception $e) {
            error_log("Error in exportTrainingDataAsCSV: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error exporting data: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Calculate model performance metrics
     * Compares ML predictions to admin decisions
     */
    public function getModelPerformance($since_date = null) {
        try {
            $query = "
                SELECT 
                    COUNT(*) as total_decisions,
                    SUM(CASE WHEN ml_label = correct_label THEN 1 ELSE 0 END) as correct_predictions,
                    SUM(CASE WHEN ml_label = 1 AND correct_label = 1 THEN 1 ELSE 0 END) as true_positives,
                    SUM(CASE WHEN ml_label = 1 AND correct_label = 0 THEN 1 ELSE 0 END) as false_positives,
                    SUM(CASE WHEN ml_label = 0 AND correct_label = 1 THEN 1 ELSE 0 END) as false_negatives,
                    SUM(CASE WHEN ml_label = 0 AND correct_label = 0 THEN 1 ELSE 0 END) as true_negatives,
                    AVG(ml_confidence) as avg_confidence
                FROM admin_training_log
                ";

            if ($since_date) {
                $query .= " WHERE created_at >= ? ";
            }

            $stmt = $this->conn->prepare($query);

            if ($since_date) {
                $stmt->bind_param("s", $since_date);
            }

            $stmt->execute();
            $result = $stmt->get_result();
            $metrics = $result->fetch_assoc();

            // Calculate derived metrics
            $total = intval($metrics['total_decisions'] ?? 0);
            $tp = intval($metrics['true_positives'] ?? 0);
            $fp = intval($metrics['false_positives'] ?? 0);
            $fn = intval($metrics['false_negatives'] ?? 0);
            $tn = intval($metrics['true_negatives'] ?? 0);

            $accuracy = $total > 0 ? (($tp + $tn) / $total) : 0;
            $precision = ($tp + $fp) > 0 ? ($tp / ($tp + $fp)) : 0;
            $recall = ($tp + $fn) > 0 ? ($tp / ($tp + $fn)) : 0;
            $f1 = ($precision + $recall) > 0 ? (2 * ($precision * $recall) / ($precision + $recall)) : 0;

            return [
                'success' => true,
                'total_decisions' => $total,
                'correct_predictions' => intval($metrics['correct_predictions'] ?? 0),
                'accuracy' => round($accuracy, 4),
                'precision' => round($precision, 4),
                'recall' => round($recall, 4),
                'f1_score' => round($f1, 4),
                'true_positives' => $tp,
                'false_positives' => $fp,
                'false_negatives' => $fn,
                'true_negatives' => $tn,
                'avg_confidence' => round(floatval($metrics['avg_confidence'] ?? 0), 4)
            ];
        } catch (Exception $e) {
            error_log("Error in getModelPerformance: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error calculating performance metrics'
            ];
        }
    }

    /**
     * Log a model retraining event
     */
    public function logRetrainingEvent($model_name, $training_samples, $accuracy, $precision, $recall, $model_version = null, $notes = null) {
        try {
            if (!$model_version) {
                $model_version = date('Y-m-d_H-i-s');
            }

            $stmt = $this->conn->prepare("
                INSERT INTO model_retraining_history 
                (model_name, training_samples, accuracy, precision, recall, model_version, notes, deployed_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->bind_param(
                "sidddss",
                $model_name,
                $training_samples,
                $accuracy,
                $precision,
                $recall,
                $model_version,
                $notes
            );

            if ($stmt->execute()) {
                return [
                    'success' => true,
                    'message' => 'Retraining event logged',
                    'model_version' => $model_version
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to log retraining event'
                ];
            }
        } catch (Exception $e) {
            error_log("Error in logRetrainingEvent: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error logging retraining event'
            ];
        }
    }

    /**
     * Get the latest deployed model versions
     */
    public function getLatestModels($limit = 10) {
        try {
            $stmt = $this->conn->prepare("
                SELECT DISTINCT 
                    model_name,
                    model_version,
                    accuracy,
                    precision,
                    recall,
                    deployed_at
                FROM model_retraining_history
                ORDER BY model_name, deployed_at DESC
                LIMIT ?
            ");
            $stmt->bind_param("i", $limit);
            $stmt->execute();
            $result = $stmt->get_result();

            $models = [];
            while ($row = $result->fetch_assoc()) {
                $models[] = $row;
            }

            return [
                'success' => true,
                'models' => $models
            ];
        } catch (Exception $e) {
            error_log("Error in getLatestModels: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error retrieving model history',
                'models' => []
            ];
        }
    }

    /**
     * Get history for a specific model
     */
    public function getModelHistory($model_name, $limit = 20) {
        try {
            $stmt = $this->conn->prepare("
                SELECT 
                    model_version,
                    training_samples,
                    accuracy,
                    precision,
                    recall,
                    deployed_at,
                    notes
                FROM model_retraining_history
                WHERE model_name = ?
                ORDER BY deployed_at DESC
                LIMIT ?
            ");
            $stmt->bind_param("si", $model_name, $limit);
            $stmt->execute();
            $result = $stmt->get_result();

            $history = [];
            while ($row = $result->fetch_assoc()) {
                $history[] = $row;
            }

            return [
                'success' => true,
                'model_name' => $model_name,
                'history' => $history
            ];
        } catch (Exception $e) {
            error_log("Error in getModelHistory: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error retrieving model history',
                'history' => []
            ];
        }
    }

    /**
     * Get admin corrections count by status
     */
    public function getAdminDecisionSummary($since_date = null) {
        try {
            $query = "
                SELECT 
                    admin_decision,
                    COUNT(*) as count
                FROM admin_training_log
                ";

            if ($since_date) {
                $query .= " WHERE created_at >= ? ";
            }

            $query .= " GROUP BY admin_decision";

            $stmt = $this->conn->prepare($query);

            if ($since_date) {
                $stmt->bind_param("s", $since_date);
            }

            $stmt->execute();
            $result = $stmt->get_result();

            $summary = [];
            while ($row = $result->fetch_assoc()) {
                $summary[$row['admin_decision']] = intval($row['count']);
            }

            return [
                'success' => true,
                'summary' => $summary
            ];
        } catch (Exception $e) {
            error_log("Error in getAdminDecisionSummary: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Error retrieving summary',
                'summary' => []
            ];
        }
    }
}
?>
