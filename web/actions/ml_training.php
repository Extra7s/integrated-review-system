<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

// Admin guard
if (!isset($_SESSION['user']) || ($_SESSION['user']['role'] ?? null) !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? null;
$mlApiBase = rtrim(getenv('FLASK_API_URL') ?: 'http://localhost:5000', '/');

function curlJson($url, $method = 'GET', $payload = null, $timeout = 120) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

    $headers = [];
    if ($payload !== null) {
        $json = json_encode($payload);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($json);
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false) {
        return ['ok' => false, 'code' => 0, 'error' => $err ?: 'Request failed', 'data' => null];
    }

    $decoded = json_decode($resp, true);
    return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'error' => null, 'data' => $decoded ?? $resp];
}

switch ($action) {
    case 'status':
        $res = curlJson($mlApiBase . '/', 'GET', null, 10);
        echo json_encode([
            'success' => $res['ok'],
            'status_code' => $res['code'],
            'data' => $res['data'],
            'error' => $res['ok'] ? null : ($res['error'] ?? 'ML API offline')
        ]);
        exit;

    case 'uploadDataset':
        if (!isset($_FILES['dataset'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'dataset file is required']);
            exit;
        }
        $file = $_FILES['dataset'];
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Upload failed', 'error_code' => $file['error']]);
            exit;
        }
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Only .csv files are allowed']);
            exit;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $mlApiBase . '/api/upload');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_POST, true);
        $cfile = new CURLFile($file['tmp_name'], 'text/csv', $file['name']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => $cfile]);

        $resp = curl_exec($ch);
        $err = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false) {
            http_response_code(502);
            echo json_encode(['success' => false, 'message' => 'Failed to reach ML API', 'error' => $err]);
            exit;
        }

        $decoded = json_decode($resp, true);
        if ($code < 200 || $code >= 300) {
            http_response_code(502);
            echo json_encode(['success' => false, 'message' => 'ML API upload failed', 'status_code' => $code, 'data' => $decoded ?? $resp]);
            exit;
        }

        echo json_encode(['success' => true, 'message' => 'Dataset uploaded', 'data' => $decoded ?? $resp]);
        exit;

    case 'train':
        // training can take time; allow longer timeout
        $res = curlJson($mlApiBase . '/api/train', 'POST', new stdClass(), 600);
        if (!$res['ok']) {
            http_response_code(502);
            echo json_encode(['success' => false, 'message' => 'Training failed', 'status_code' => $res['code'], 'data' => $res['data'], 'error' => $res['error']]);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'Training complete', 'data' => $res['data']]);
        exit;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
}

