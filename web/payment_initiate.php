<?php
session_start();
require_once "includes/db.php";
require_once "config/khalti.php";

if (!isset($_SESSION['user']) || !isset($_SESSION['pending_order'])) {
    header("Location: login.php");
    exit;
}

$order_id = intval($_GET['order_id'] ?? 0);
$pending_order = $_SESSION['pending_order'];

if ($order_id != $pending_order['order_id']) {
    header("Location: checkout.php");
    exit;
}

// Verify order exists
$stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
$stmt->bind_param("ii", $order_id, $_SESSION['user']['id']);
$stmt->execute();
$res = $stmt->get_result();
$order = $res->fetch_assoc();

if (!$order) {
    header("Location: checkout.php");
    exit;
}

// Get customer info from session or order
$name = $_SESSION['user']['name'] ?? $pending_order['customer_name'];
$email = $_SESSION['user']['email'] ?? $pending_order['customer_email'];
$phone = $pending_order['customer_phone'] ?? '9800000000';

$amount = $pending_order['total']; // in rupees
$purchase_order_id = $order_id;
$purchase_order_name = 'Art Purchase - Order #' . $order_id;

$postFields = array(
    "return_url" => SUCCESS_URL . "?order_id=" . $order_id,
    "website_url" => WEBSITE_URL,
    "amount" => $amount * 100, // convert to paisa
    "purchase_order_id" => $purchase_order_id,
    "purchase_order_name" => $purchase_order_name,
    "customer_info" => array(
        "name" => $name,
        "email" => $email,
        "phone" => $phone
    )
);

$jsonData = json_encode($postFields);

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => KHALTI_INITIATE_URL,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $jsonData,
    CURLOPT_HTTPHEADER => array(
        'Authorization: key ' . KHALTI_SECRET_KEY,
        'Content-Type: application/json',
    ),
));

$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

if (curl_errno($curl)) {
    echo 'Error:' . curl_error($curl);
} else {
    $responseArray = json_decode($response, true);

    if (isset($responseArray['error'])) {
        echo 'Error: ' . $responseArray['error'];
        echo '<br><a href="checkout.php">Go back to checkout</a>';
    } elseif (isset($responseArray['payment_url'])) {
        // Redirect the user to the payment page
        header('Location: ' . $responseArray['payment_url']);
        exit;
    } else {
        echo 'Unexpected response: ' . $response;
        echo '<br><a href="checkout.php">Go back to checkout</a>';
    }
}

curl_close($curl);
?></content>
<parameter name="filePath">c:\xampp\htdocs\artstore_deploy_ready\art\payment_initiate.php