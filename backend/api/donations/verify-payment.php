<?php
require_once __DIR__ . '/../config.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$body               = getBody();
$orderId            = $body['razorpay_order_id']   ?? '';
$paymentId          = $body['razorpay_payment_id'] ?? '';
$signature          = $body['razorpay_signature']  ?? '';
$donationId         = $body['donation_id']         ?? '';

if (!$orderId || !$paymentId || !$signature || !$donationId) {
    jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
}

try {
    // Verify signature
    $expectedSignature = hash_hmac('sha256', $orderId . '|' . $paymentId, RAZORPAY_KEY_SECRET);
    $isAuthentic       = hash_equals($expectedSignature, $signature);

    $db   = getDB();
    $stmt = $db->prepare("UPDATE donations SET razorpay_payment_id=?, razorpay_signature=?, status=? WHERE id=?");

    if ($isAuthentic) {
        $stmt->execute([$paymentId, $signature, 'completed', $donationId]);

        // Fetch donation for email
        $row = $db->query("SELECT * FROM donations WHERE id = " . $db->quote($donationId))->fetch();

        // Send email notification
        if ($row) {
            sendEmail(
                "[HUManity] Donation ₹{$row['amount']} from {$row['name']}",
                "New Donation Received\n---------------------\nName: {$row['name']}\nEmail: {$row['email']}\nPhone: {$row['phone']}\nAmount: ₹{$row['amount']}\nID Type: {$row['id_type']}\nID Number: {$row['id_number']}\nPayment ID: {$paymentId}\nOrder ID: {$orderId}\nCompleted at: " . date('Y-m-d H:i:s')
            );
        }

        jsonResponse([
            'success'    => true,
            'message'    => 'Payment verified successfully',
            'donation'   => $row,
            'receiptUrl' => "/api/donations/receipt.php?id={$donationId}",
        ]);
    } else {
        $stmt->execute([$paymentId, $signature, 'failed', $donationId]);
        jsonResponse(['success' => false, 'message' => 'Payment verification failed'], 400);
    }

} catch (Exception $e) {
    error_log("verify-payment error: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
}
