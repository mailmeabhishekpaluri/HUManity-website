<?php
require_once __DIR__ . '/../config.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$body              = getBody();
$pocName           = trim($body['pocName']           ?? '');
$companyName       = trim($body['companyName']       ?? '');
$email             = trim($body['email']             ?? '');
$phone             = trim($body['phone']             ?? '');
$interest          = $body['interest']               ?? [];
$engagementType    = trim($body['engagementType']    ?? '');
$locationType      = trim($body['locationType']      ?? '');
$existingLocation  = trim($body['existingLocation']  ?? '');
$customLocation    = trim($body['customLocation']    ?? '');
$expectedEmployees = trim($body['expectedEmployees'] ?? '');
$additionalNotes   = trim($body['additionalNotes']   ?? '');

if (!$pocName || !$companyName || !$email || !$phone || empty($interest) || !$engagementType || !$expectedEmployees) {
    jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
}

$location = $locationType === 'existing' ? $existingLocation : $customLocation;

try {
    $db   = getDB();
    $stmt = $db->prepare("INSERT INTO corporate_partnerships
        (id, poc_name, company_name, email, phone, interest, engagement_type, location, expected_employees, additional_notes, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        generateUUID(), $pocName, $companyName, $email, $phone,
        implode(', ', $interest), $engagementType, $location,
        $expectedEmployees, $additionalNotes,
    ]);

    sendEmail(
        "[HUManity] Corporate Partnership: {$companyName}",
        "New Corporate Partnership Inquiry\n----------------------------------\nCompany: {$companyName}\nContact: {$pocName}\nEmail: {$email}\nPhone: {$phone}\nInterests: " . implode(', ', $interest) . "\nEngagement: {$engagementType}\nLocation: {$location}\nEmployees: {$expectedEmployees}\nNotes: {$additionalNotes}\nSubmitted at: " . date('Y-m-d H:i:s')
    );

    jsonResponse(['success' => true, 'message' => 'Partnership inquiry submitted successfully']);

} catch (Exception $e) {
    error_log("corporate form error: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
}
