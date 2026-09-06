<?php
require_once __DIR__ . '/../config.php';
setCorsHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}

$body = getBody();
$name         = trim($body['name'] ?? '');
$email        = trim($body['email'] ?? '');
$phone        = trim($body['phone'] ?? '');
$amount       = intval($body['amount'] ?? 0);
$idType       = trim($body['idType'] ?? '');
$idNumber     = trim($body['idNumber'] ?? '');
$donationType = trim($body['donationType'] ?? 'once');

// Validate
if (!$name || !$email || !$phone || $amount <= 0 || !$idType || !$idNumber) {
    jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
}

try {
    $db = getDB();
    $id = generateUUID();

    if ($donationType === 'monthly') {
        // Create Razorpay Plan
        $plan = razorpayRequest('POST', 'plans', [
            'period'   => 'monthly',
            'interval' => 1,
            'item'     => [
                'name'        => "Monthly Donation - ₹{$amount}",
                'amount'      => $amount * 100,
                'currency'    => 'INR',
                'description' => "Monthly recurring donation of ₹{$amount} to HUManity Foundation",
            ],
        ]);

        // Create Subscription
        $subscription = razorpayRequest('POST', 'subscriptions', [
            'plan_id'         => $plan['id'],
            'total_count'     => 120,
            'quantity'        => 1,
            'customer_notify' => 1,
            'notes'           => [
                'donor_name'  => $name,
                'donor_email' => $email,
                'donor_phone' => $phone,
            ],
        ]);

        // Store in DB
        $stmt = $db->prepare("INSERT INTO donations
            (id, name, email, phone, amount, id_type, id_number, donation_type, razorpay_subscription_id, razorpay_plan_id, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'monthly', ?, ?, 'pending', NOW())");
        $stmt->execute([$id, $name, $email, $phone, $amount, $idType, $idNumber, $subscription['id'], $plan['id']]);

        jsonResponse([
            'success'        => true,
            'isSubscription' => true,
            'subscriptionId' => $subscription['id'],
            'amount'         => $amount * 100,
            'currency'       => 'INR',
            'donationId'     => $id,
            'razorpayKeyId'  => RAZORPAY_KEY_ID,
        ]);

    } else {
        // Create one-time order
        $order = razorpayRequest('POST', 'orders', [
            'amount'   => $amount * 100,
            'currency' => 'INR',
            'receipt'  => 'receipt_' . time(),
        ]);

        // Store in DB
        $stmt = $db->prepare("INSERT INTO donations
            (id, name, email, phone, amount, id_type, id_number, donation_type, razorpay_order_id, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'once', ?, 'pending', NOW())");
        $stmt->execute([$id, $name, $email, $phone, $amount, $idType, $idNumber, $order['id']]);

        jsonResponse([
            'success'       => true,
            'isSubscription'=> false,
            'orderId'       => $order['id'],
            'amount'        => $order['amount'],
            'currency'      => $order['currency'],
            'donationId'    => $id,
            'razorpayKeyId' => RAZORPAY_KEY_ID,
        ]);
    }

} catch (Exception $e) {
    error_log("create-order error: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
}
