<?php
/**
 * ML Model Training Data Logger
 * Tracks admin decisions for model retraining
 * Logs discrepancies between ML predictions and admin rulings
 */

class AdminTrainingLogger {
    private $conn;

    public function __construct($database_connection) {
        $this->conn = $database_connection;
    }

    /**
     * Log admin decision for training
     */
    public function logAdminDecision($review_id, $admin_id, $admin_decision, $reason = null) {
        // Get review's original ML prediction
        $stmt = $this->conn->prepare("
            SELECT is_fake, fake_confidence FROM reviews WHERE id = ?
        ");
        $stmt->bind_param("i", $review_id);
        $stmt->execute();
        $review = $stmt->get_result()->fetch_assoc();

        if (!$review) return false;

        $original_ml_prediction = $review['is_fake'];
        $original_ml_confidence = $review['fake_confidence'];

        // Insert training log
        $stmt = $this->conn->prepare("
            INSERT INTO admin_training_log 
            (review_id, admin_id, original_ml_prediction, original_ml_confidence, admin_decision, reason)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("iiidss", $review_id, $admin_id, $original_ml_prediction, 
                         $original_ml_confidence, $admin_decision, $reason);
        
        return $stmt->execute();
    }

    /**
     * Get training data for model retraining
     * Returns misclassified examples and confidence data
     */
    public function getTrainingData($since_date = null, $limit = 1000) {
        $query = "
            SELECT 
                r.id,
                r.comment,
                r.rating,
                r.is_fake as ml_label,
                r.fake_confidence as ml_confidence,
                atl.admin_decision as correct_label,
                atl.reason,
                atl.created_at,
                u.account_age_days,
                urp.risk_score,
                (SELECT COUNT(*) FROM helpful_votes hv WHERE hv.review_id = r.id) as helpful_count,
                (SELECT COUNT(*) FROM review_similarities rs WHERE rs.review_id_1 = r.id OR rs.review_id_2 = r.id) as similarity_count
            FROM admin_training_log atl
            JOIN reviews r ON atl.review_id = r.id
            JOIN users u ON r.user_id = u.id
            LEFT JOIN user_risk_profiles urp ON u.id = urp.user_id
            WHERE 1=1
        ";

        if ($since_date) {
            $query .= " AND atl.created_at >= ?";
        }

        $query .= " ORDER BY atl.created_at DESC LIMIT ?";

        if ($since_date) {
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("si", $since_date, $limit);
        } else {
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("i", $limit);
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Export training data as CSV for Python ML pipeline
     */
    public function exportTrainingDataAsCSV($output_file = null) {
        $data = $this->getTrainingData(null, 10000);

        if (!$data) return false;

        $file_path = $output_file ?? '/tmp/review_training_data_' . date('Y-m-d-His') . '.csv';
        
        $csv_file = fopen($file_path, 'w');
        
        // Write header
        $headers = ['review_id', 'comment', 'rating', 'ml_prediction', 'ml_confidence', 
                   'correct_label', 'account_age', 'risk_score', 'helpful_count', 'similarity_count'];
        fputcsv($csv_file, $headers);

        // Write data
        foreach ($data as $row) {
            fputcsv($csv_file, [
                $row['id'],
                $row['comment'],
                $row['rating'],
                $row['ml_label'],
                $row['ml_confidence'],
                $row['correct_label'],
                $row['account_age_days'],
                $row['risk_score'],
                $row['helpful_count'],
                $row['similarity_count']
            ]);
        }

        fclose($csv_file);
        return $file_path;
    }

    /**
     * Get model performance metrics
     */
    public function getModelPerformance($since_date = null) {
        $query = "
            SELECT 
                COUNT(*) as total_decisions,
                SUM(CASE WHEN 
                    (original_ml_prediction = 1 AND admin_decision = 'rejected') OR
                    (original_ml_prediction = 0 AND admin_decision = 'approved')
                    THEN 1 ELSE 0 END) as correct_predictions,
                SUM(CASE WHEN 
                    (original_ml_prediction = 1 AND admin_decision = 'approved') OR
                    (original_ml_prediction = 0 AND admin_decision = 'rejected')
                    THEN 1 ELSE 0 END) as incorrect_predictions,
                AVG(CASE WHEN original_ml_prediction = 1 THEN original_ml_confidence ELSE NULL END) as avg_confidence_true,
                AVG(CASE WHEN original_ml_prediction = 0 THEN original_ml_confidence ELSE NULL END) as avg_confidence_false
            FROM admin_training_log
            WHERE 1=1
        ";

        if ($since_date) {
            $query .= " AND created_at >= ?";
        }

        if ($since_date) {
            $stmt = $this->conn->prepare($query);
            $stmt->bind_param("s", $since_date);
        } else {
            $stmt = $this->conn->prepare($query);
        }

        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result['total_decisions'] == 0) {
            return null;
        }

        return [
            'total_decisions' => intval($result['total_decisions']),
            'correct' => intval($result['correct_predictions']),
            'incorrect' => intval($result['incorrect_predictions']),
            'accuracy' => intval($result['correct_predictions']) / intval($result['total_decisions']),
            'avg_confidence_on_fakes' => floatval($result['avg_confidence_true']),
            'avg_confidence_on_legitimates' => floatval($result['avg_confidence_false'])
        ];
    }

    /**
     * Log retraining event
     */
    public function logRetrainingEvent($model_name, $training_samples, $accuracy, $precision, $recall, $model_version = null, $notes = null) {
        $stmt = $this->conn->prepare("
            INSERT INTO model_retraining_history 
            (model_name, training_samples, accuracy, precision, recall, model_version, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sidddss", $model_name, $training_samples, $accuracy, 
                         $precision, $recall, $model_version, $notes);
        
        return $stmt->execute();
    }

    /**
     * Get latest deployed models
     */
    public function getLatestModels() {
        $stmt = $this->conn->prepare("
            SELECT * FROM model_retraining_history
            GROUP BY model_name
            ORDER BY deployed_at DESC
        ");
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get model history
     */
    public function getModelHistory($model_name, $limit = 20) {
        $stmt = $this->conn->prepare("
            SELECT * FROM model_retraining_history
            WHERE model_name = ?
            ORDER BY deployed_at DESC
            LIMIT ?
        ");
        $stmt->bind_param("si", $model_name, $limit);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}
?>
