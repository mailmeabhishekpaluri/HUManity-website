<?php
// ─── Volunteer Applications Sync Bridge ───────────────────────────────────────
// Called by the Express admin server to fetch new volunteer applications.
// Protected by a shared secret token.

require_once __DIR__ . '/config.php';

// Shared secret — must match HOSTINGER_SYNC_SECRET in your .env
define('SYNC_SECRET', 'hum_sync_7x4k9mQ2pRvL8wZnJdYe');

header('Content-Type: application/json');

// Verify secret
$token = $_GET['secret'] ?? '';
if ($token !== SYNC_SECRET) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    $db = getDB();

    // Optional: only fetch rows newer than a given timestamp
    $since = $_GET['since'] ?? null;

    if ($since) {
        $stmt = $db->prepare(
            "SELECT id, first_name, last_name, email, phone, city, dob,
                    occupation, volunteer_type, projects, created_at
             FROM volunteer_applications
             WHERE created_at > :since
             ORDER BY created_at ASC"
        );
        $stmt->execute([':since' => $since]);
    } else {
        $stmt = $db->query(
            "SELECT id, first_name, last_name, email, phone, city, dob,
                    occupation, volunteer_type, projects, created_at
             FROM volunteer_applications
             ORDER BY created_at ASC"
        );
    }

    $rows = $stmt->fetchAll();
    echo json_encode(['ok' => true, 'count' => count($rows), 'data' => $rows]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
