<?php
require_once __DIR__ . '/../config.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$body        = getBody();
$firstName   = trim($body['firstName']   ?? '');
$lastName    = trim($body['lastName']    ?? '');
$email       = trim($body['email']       ?? '');
$phone       = trim($body['phone']       ?? '');
$city        = trim($body['city']        ?? '');
$dob         = trim($body['dob']         ?? '');
$occupation  = trim($body['occupation']  ?? '');
$volunteerType = $body['volunteerType']  ?? [];
$projects    = $body['projects']         ?? [];

if (!$firstName || !$lastName || !$email || !$phone || !$city || !$dob || !$occupation || empty($volunteerType) || empty($projects)) {
    jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
}

try {
    $db   = getDB();
    $stmt = $db->prepare("INSERT INTO volunteer_applications
        (id, first_name, last_name, email, phone, city, dob, occupation, volunteer_type, projects, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([
        generateUUID(), $firstName, $lastName, $email, $phone, $city, $dob,
        $occupation,
        implode(', ', $volunteerType),
        implode(', ', $projects),
    ]);

    sendEmail(
        "[HUManity] New Volunteer: {$firstName} {$lastName}",
        "New Volunteer Application\n-------------------------\nName: {$firstName} {$lastName}\nEmail: {$email}\nPhone: {$phone}\nCity: {$city}\nDOB: {$dob}\nOccupation: {$occupation}\nVolunteer Type: " . implode(', ', $volunteerType) . "\nProjects: " . implode(', ', $projects) . "\nSubmitted at: " . date('Y-m-d H:i:s')
    );

    jsonResponse(['success' => true, 'message' => 'Volunteer application submitted successfully']);

} catch (Exception $e) {
    error_log("volunteer form error: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
}
