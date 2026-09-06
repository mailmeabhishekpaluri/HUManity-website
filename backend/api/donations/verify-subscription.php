<?php
require_once __DIR__ . '/../config.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$body           = getBody();
$subscriptionId = $body['razorpay_subscription_id'] ?? '';
$paymentId      = $body['razorpay_payment_id']      ?? '';
$signature      = $body['razorpay_signature']        ?? '';
$donationId     = $body['donation_id']               ?? '';

if (!$subscriptionId || !$paymentId || !$signature || !$donationId) {
    jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
}

try {
    // Verify signature: payment_id|subscription_id
    $expectedSignature = hash_hmac('sha256', $paymentId . '|' . $subscriptionId, RAZORPAY_KEY_SECRET);
    $isAuthentic       = hash_equals($expectedSignature, $signature);

    $db   = getDB();
    $stmt = $db->prepare("UPDATE donations SET razorpay_payment_id=?, razorpay_signature=?, status=? WHERE id=?");

    if ($isAuthentic) {
        $stmt->execute([$paymentId, $signature, 'completed', $donationId]);

        $row = $db->query("SELECT * FROM donations WHERE id = " . $db->quote($donationId))->fetch();

        if ($row) {
            sendEmail(
                "[HUManity] Monthly Donation ₹{$row['amount']} from {$row['name']}",
                "New Monthly Subscription Donation\n----------------------------------\nName: {$row['name']}\nEmail: {$row['email']}\nPhone: {$row['phone']}\nAmount: ₹{$row['amount']}/month\nPayment ID: {$paymentId}\nSubscription ID: {$subscriptionId}\nCompleted at: " . date('Y-m-d H:i:s')
            );
        }

        jsonResponse([
            'success'    => true,
            'message'    => 'Subscription verified successfully',
            'donation'   => $row,
            'receiptUrl' => "/api/donations/receipt.php?id={$donationId}",
        ]);
    } else {
        $stmt->execute([$paymentId, $signature, 'failed', $donationId]);
        jsonResponse(['success' => false, 'message' => 'Subscription verification failed'], 400);
    }

} catch (Exception $e) {
    error_log("verify-subscription error: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
}
