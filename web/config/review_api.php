<?php
/**
 * Review API Service
 * Handles communication with the fake review detection Flask API
 */

class ReviewAPIService {
    private $flask_api_url;
    private $timeout;
    private $max_retries;

    public function __construct($flask_url = 'http://localhost:5000') {
        $this->flask_api_url = rtrim($flask_url, '/');
        $this->timeout = 30;
        $this->max_retries = 3;
    }

    /**
     * Check if a review is fake using the Flask API
     * 
     * @param string $review_text The review text to analyze
     * @param string $algorithm The ML algorithm to use (ensemble, logistic_regression, svm, decision_tree)
     * @return array Result array with keys: is_fake, confidence, result, algorithm
     */
    public function checkReviewFakeness($review_text, $algorithm = 'ensemble') {
        $payload = array(
            'review' => $review_text,
            'algorithm' => $algorithm
        );

        $response = $this->makeRequest('/api/predict', 'POST', $payload);
        
        if ($response['success']) {
            // Flask returns confidence as "XX.XX%" string, convert to decimal 0-1
            $confidence_str = $response['data']['confidence'];
            $confidence_numeric = floatval($confidence_str); // Removes the % sign and converts to float
            $confidence_decimal = $confidence_numeric / 100; // Convert from percentage to 0-1 range
            
            return array(
                'success' => true,
                'is_fake' => $response['data']['result'] === 'Fake' ? 1 : 0,
                'confidence' => $confidence_decimal,
                'result' => $response['data']['result'],
                'algorithm' => $algorithm,
                'recommendation' => $response['data']['recommendation'] ?? 'Review processed'
            );
        } else {
            // If API is unavailable, return neutral result
            return array(
                'success' => false,
                'is_fake' => 0,
                'confidence' => 0.00,
                'result' => 'Unknown',
                'algorithm' => 'offline',
                'error' => $response['error'],
                'recommendation' => 'API unavailable - Review saved for later analysis'
            );
        }
    }

    /**
     * Get API status
     * 
     * @return array Status result
     */
    public function getAPIStatus() {
        $response = $this->makeRequest('/', 'GET');
        return array(
            'online' => $response['success'],
            'error' => $response['error'] ?? null
        );
    }

    /**
     * Make HTTP request to Flask API
     * 
     * @param string $endpoint The API endpoint
     * @param string $method HTTP method (GET, POST)
     * @param array $data Request payload for POST
     * @return array Response array with success status and data
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null, $retry = 0) {
        $url = $this->flask_api_url . $endpoint;

        $options = array(
            'http' => array(
                'method' => $method,
                'timeout' => $this->timeout,
                'header' => array(
                    'Content-Type: application/json'
                )
            )
        );

        if ($method === 'POST' && $data) {
            $options['http']['content'] = json_encode($data);
        }

        try {
            $context = stream_context_create($options);
            $response = @file_get_contents($url, false, $context);

            if ($response === false) {
                if ($retry < $this->max_retries) {
                    sleep(1); // Wait before retry
                    return $this->makeRequest($endpoint, $method, $data, $retry + 1);
                }
                
                return array(
                    'success' => false,
                    'error' => 'Could not connect to detection API',
                    'data' => null
                );
            }

            $decoded = json_decode($response, true);
            
            return array(
                'success' => true,
                'error' => null,
                'data' => $decoded
            );

        } catch (Exception $e) {
            if ($retry < $this->max_retries) {
                sleep(1);
                return $this->makeRequest($endpoint, $method, $data, $retry + 1);
            }

            return array(
                'success' => false,
                'error' => $e->getMessage(),
                'data' => null
            );
        }
    }
}

// Initialize service if not in CLI mode
if (php_sapi_name() !== 'cli') {
    $review_api_service = new ReviewAPIService(
        getenv('FLASK_API_URL') ?: 'http://localhost:5000'
    );
}
?>
