<?php
define('DB_HOST',     'localhost');
define('DB_NAME',     'u553961924_websitedata');
define('DB_USER',     'u553961924_websitedata');
define('DB_PASS',     'DBpassword@123');

define('RAZORPAY_KEY_ID',     'rzp_live_SXo4Fp5VAVZAZt');
define('RAZORPAY_KEY_SECRET', 'YjEgWazaKidKEgPqRsqBOmZF');

define('RESEND_API_KEY',    're_ZTWAu8cC_QwZ1RstYEVTFSwU7rQE1PNTq');
define('RESEND_FROM_EMAIL', 'noreply@humanityorg.foundation');
define('NOTIFY_EMAIL',      'abhishek@humanityorg.foundation');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function setCorsHeaders(): void {
    $allowed = [
        'https://humanityorg.foundation',
        'https://www.humanityorg.foundation',
    ];
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowed) || str_ends_with($origin, '.netlify.app')) {
        header("Access-Control-Allow-Origin: $origin");
    }
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
    header("Access-Control-Allow-Credentials: true");
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

function jsonResponse(array $data, int $status = 200): void {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function getBody(): array {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

function generateUUID(): string {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function razorpayRequest(string $method, string $endpoint, array $data = []): array {
    $url = "https://api.razorpay.com/v1/" . ltrim($endpoint, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
    ]);
    if (!empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $result = json_decode($response, true) ?? [];
    if ($httpCode >= 400) {
        throw new Exception("Razorpay API error: " . ($result['error']['description'] ?? $response));
    }
    return $result;
}

function sendEmail(string $subject, string $body): void {
    if (!RESEND_API_KEY) return;
    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . RESEND_API_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'from'    => RESEND_FROM_EMAIL,
            'to'      => NOTIFY_EMAIL,
            'subject' => $subject,
            'text'    => $body,
        ]),
    ]);
    curl_exec($ch);
    curl_close($ch);
}
