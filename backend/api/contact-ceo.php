<?php
require_once __DIR__ . '/config.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$body        = getBody();
$name        = trim($body['name']        ?? '');
$phone       = trim($body['phone']       ?? '');
$company     = trim($body['company']     ?? '');
$designation = trim($body['designation'] ?? '');
$message     = trim($body['message']     ?? '');

if (!$name || !$phone || !$company) {
    jsonResponse(['success' => false, 'error' => 'Name, phone and company are required'], 400);
}

try {
    $db   = getDB();
    $stmt = $db->prepare("INSERT INTO ceo_contacts (id, name, phone, company, designation, message, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([generateUUID(), $name, $phone, $company, $designation, $message]);

    sendEmail(
        "[HUManity] CEO Contact: {$name} - {$company}",
        "New CEO Contact Request\n-----------------------\nName: {$name}\nPhone: {$phone}\nCompany: {$company}\nDesignation: {$designation}\nMessage:\n{$message}\nSubmitted at: " . date('Y-m-d H:i:s')
    );

    jsonResponse(['success' => true, 'message' => 'Your message has been received. Expect a response within 24 hours.']);

} catch (Exception $e) {
    error_log("contact-ceo error: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
}
