<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once "includes/db.php";
require_once "config/khalti.php";

class KhaltiPayment {
    private $public_key;
    private $secret_key;
    private $initiate_url;
    private $verify_url;

    public function __construct() {
        $this->public_key = KHALTI_PUBLIC_KEY;
        $this->secret_key = KHALTI_SECRET_KEY;
        $this->initiate_url = KHALTI_INITIATE_URL;
        $this->verify_url = KHALTI_VERIFY_URL;
    }

    /**
     * Initiate Khalti payment
     */
    public function initiatePayment($order_id, $amount, $customer_info) {
        $payload = [
            'return_url' => SUCCESS_URL,
            'website_url' => WEBSITE_URL,
            'amount' => $amount * 100, // Khalti expects amount in paisa (multiply by 100)
            'purchase_order_id' => $order_id,
            'purchase_order_name' => 'Art Purchase - Order #' . $order_id,
            'customer_info' => $customer_info
        ];

        $headers = [
            'Authorization: Key ' . $this->secret_key,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->initiate_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($http_code == 200) {
            $result = json_decode($response, true);
            return [
                'success' => true,
                'payment_url' => $result['payment_url'] ?? null,
                'pidx' => $result['pidx'] ?? null
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Payment initiation failed',
                'response' => $response
            ];
        }
    }

    /**
     * Verify Khalti payment
     */
    public function verifyPayment($pidx) {
        $payload = ['pidx' => $pidx];

        $headers = [
            'Authorization: Key ' . $this->secret_key,
            'Content-Type: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->verify_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($http_code == 200) {
            $result = json_decode($response, true);
            return [
                'success' => true,
                'status' => $result['status'] ?? null,
                'transaction_id' => $result['transaction_id'] ?? null,
                'total_amount' => $result['total_amount'] ?? null,
                'data' => $result
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Payment verification failed',
                'response' => $response
            ];
        }
    }
}

// Handle payment initiation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $khalti = new KhaltiPayment();

    if ($_POST['action'] === 'initiate') {
        $order_id = intval($_POST['order_id']);
        $amount = floatval($_POST['amount']);
        $customer_name = $_POST['customer_name'];
        $customer_email = $_POST['customer_email'];
        $customer_phone = $_POST['customer_phone'];

        $customer_info = [
            'name' => $customer_name,
            'email' => $customer_email,
            'phone' => $customer_phone
        ];

        $result = $khalti->initiatePayment($order_id, $amount, $customer_info);

        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

    if ($_POST['action'] === 'verify') {
        $pidx = $_POST['pidx'];

        $result = $khalti->verifyPayment($pidx);

        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }
}
?>