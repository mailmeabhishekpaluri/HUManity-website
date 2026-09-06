<?php
require_once __DIR__ . '/../config.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$body    = getBody();
$name    = trim($body['name']    ?? '');
$email   = trim($body['email']   ?? '');
$subject = trim($body['subject'] ?? '');
$message = trim($body['message'] ?? '');

if (!$name || !$email || !$subject || !$message) {
    jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
}

try {
    $db   = getDB();
    $stmt = $db->prepare("INSERT INTO contact_messages (id, name, email, subject, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([generateUUID(), $name, $email, $subject, $message]);

    sendEmail(
        "[HUManity] Contact: {$subject}",
        "New Contact Message\n-------------------\nName: {$name}\nEmail: {$email}\nSubject: {$subject}\nMessage:\n{$message}\nSubmitted at: " . date('Y-m-d H:i:s')
    );

    jsonResponse(['success' => true, 'message' => 'Message sent successfully']);

} catch (Exception $e) {
    error_log("contact form error: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
}
