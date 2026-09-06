<?php
require_once __DIR__ . '/config.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$body            = getBody();
$projectInterest = trim($body['projectInterest'] ?? '');
$partnershipType = trim($body['partnershipType'] ?? '');
$name            = trim($body['name']            ?? '');
$email           = trim($body['email']           ?? '');
$phone           = trim($body['phone']           ?? '');
$company         = trim($body['company']         ?? '');
$city            = trim($body['city']            ?? '');
$numberOfSchools = trim($body['numberOfSchools'] ?? '');
$message         = trim($body['message']         ?? '');

if (!$projectInterest || !$partnershipType || !$name || !$email || !$phone || !$company || !$city || !$numberOfSchools) {
    jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
}

try {
    $db   = getDB();
    $stmt = $db->prepare("INSERT INTO partnership_inquiries
        (id, project_interest, partnership_type, name, email, phone, company, city, number_of_schools, message, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([generateUUID(), $projectInterest, $partnershipType, $name, $email, $phone, $company, $city, $numberOfSchools, $message]);

    sendEmail(
        "[HUManity] Partnership Inquiry: {$projectInterest} - {$company}",
        "New Partnership Inquiry\n-----------------------\nProject: {$projectInterest}\nType: {$partnershipType}\nName: {$name}\nEmail: {$email}\nPhone: {$phone}\nCompany: {$company}\nCity: {$city}\nSchools/CCIs: {$numberOfSchools}\nMessage: {$message}\nSubmitted at: " . date('Y-m-d H:i:s')
    );

    jsonResponse(['success' => true, 'message' => 'Partnership inquiry submitted successfully']);

} catch (Exception $e) {
    error_log("partnership-inquiry error: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
}
