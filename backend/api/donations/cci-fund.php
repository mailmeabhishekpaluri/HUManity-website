<?php
// Campaign-specific donations. Does not use the legacy verification handlers.
declare(strict_types=1);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/cci-fund-payment.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
const CCI_CAMPAIGN = 'indian-gypsy-children-home-suryapet';
const CCI_GOAL = 60000000; // paise
function cciReply(array $body, int $status = 200): void {
    http_response_code($status); echo json_encode($body); exit;
}
// This fundraiser shares the already-configured HUManity Razorpay account.
// Dedicated environment variables can override these later without changing code.
function cciKey(): string { return getenv('CCI_RAZORPAY_KEY_ID') ?: RAZORPAY_KEY_ID; }
function cciSecret(): string { return getenv('CCI_RAZORPAY_KEY_SECRET') ?: RAZORPAY_KEY_SECRET; }
function cciMode(): string { return strpos(cciKey(), 'rzp_live_') === 0 ? 'live' : 'test'; }
function cciEnabled(): bool { return cciKey() !== '' && cciSecret() !== ''; }
function cciEnsureSchema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS cci_fund_donations (
        id CHAR(32) PRIMARY KEY,
        campaign VARCHAR(80) NOT NULL,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(254) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        amount_paise BIGINT UNSIGNED NOT NULL,
        currency CHAR(3) NOT NULL DEFAULT 'INR',
        mode VARCHAR(4) NOT NULL,
        razorpay_order_id VARCHAR(100) UNIQUE,
        razorpay_payment_id VARCHAR(100) UNIQUE,
        amount_refunded_paise BIGINT UNSIGNED NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        confirmed_at DATETIME NULL,
        INDEX campaign_status (campaign, mode, status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function cciProvider(string $method, string $path, ?array $data = null): array {
    $ch = curl_init('https://api.razorpay.com/v1/' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => cciKey() . ':' . cciSecret(),
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20]);
    if ($data !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $result = is_string($raw) ? json_decode($raw, true) : null;
    if ($raw === false || $code < 200 || $code >= 300 || !is_array($result)) throw new RuntimeException('Payment provider request failed');
    return $result;
}
function cciTotals(PDO $db): array {
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount_paise - amount_refunded_paise),0) AS total FROM cci_fund_donations WHERE campaign=? AND mode='live' AND status='completed'");
    $stmt->execute([CCI_CAMPAIGN]);
    return ['success'=>true,'raised'=>((int)$stmt->fetchColumn())/100,'goal'=>CCI_GOAL/100,'acceptingDonations'=>cciEnabled()];
}
function cciConfirm(PDO $db, array $payment): array {
    $db->beginTransaction();
    try {
        $stmt = $db->prepare('SELECT * FROM cci_fund_donations WHERE razorpay_order_id=? AND campaign=? AND mode=? FOR UPDATE');
        $stmt->execute([$payment['order_id'] ?? '', CCI_CAMPAIGN, cciMode()]);
        $donation = $stmt->fetch();
        if (!$donation) throw new RuntimeException('Unknown campaign order');
        cciValidatePayment($donation, $payment);
        if ($donation['razorpay_payment_id'] && $donation['razorpay_payment_id'] !== $payment['id']) throw new RuntimeException('Order already has a different payment');
        $refund = max((int)$donation['amount_refunded_paise'], (int)($payment['amount_refunded'] ?? 0));
        $stmt = $db->prepare("UPDATE cci_fund_donations SET status='completed', razorpay_payment_id=?, amount_refunded_paise=?, confirmed_at=COALESCE(confirmed_at,NOW()) WHERE id=?");
        $stmt->execute([$payment['id'], $refund, $donation['id']]);
        $db->commit();
        return ['success'=>true,'paymentId'=>$payment['id'],'amount'=>((int)$donation['amount_paise'])/100];
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
}
try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'totals';
    $db = getDB();
    cciEnsureSchema($db);
    if ($method === 'GET' && $action === 'totals') cciReply(cciTotals($db));
    if ($method !== 'POST') cciReply(['success'=>false,'error'=>'Method not allowed'],405);
    if (!cciEnabled()) cciReply(['success'=>false,'error'=>'This fundraiser is not accepting payments yet. Please check back shortly.'],503);
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 65536) cciReply(['success'=>false,'error'=>'Request too large'],413);
    $raw = file_get_contents('php://input', false, null, 0, 65537);
    if (!is_string($raw) || strlen($raw) > 65536) cciReply(['success'=>false,'error'=>'Request too large'],413);
    $body = json_decode($raw, true);
    if (!is_array($body)) cciReply(['success'=>false,'error'=>'Invalid request'],400);
    if ($action === 'webhook') {
        $sig = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
        $webhookSecret = getenv('CCI_RAZORPAY_WEBHOOK_SECRET') ?: '';
        if ($webhookSecret === '' || !hash_equals(hash_hmac('sha256',$raw,$webhookSecret), $sig)) cciReply(['success'=>false],401);
        $event = $body['event'] ?? '';
        if (!in_array($event,['payment.captured','order.paid','refund.processed'],true)) cciReply(['success'=>true]);
        $paymentId = $body['payload']['payment']['entity']['id'] ?? $body['payload']['refund']['entity']['payment_id'] ?? '';
        if (!is_string($paymentId) || !preg_match('/^pay_[A-Za-z0-9]+$/',$paymentId)) cciReply(['success'=>false],400);
        $payment = cciProvider('GET','payments/'.rawurlencode($paymentId));
        $check = $db->prepare('SELECT id FROM cci_fund_donations WHERE razorpay_order_id=? AND campaign=? AND mode=?');
        $check->execute([$payment['order_id'] ?? '',CCI_CAMPAIGN,cciMode()]);
        if (!$check->fetch()) cciReply(['success'=>true]); // unrelated account payment
        cciReply(cciConfirm($db,$payment));
    }
    // Same-origin checkout; webhooks above are authenticated independently.
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && !in_array($origin,['https://humanityorg.foundation','https://www.humanityorg.foundation'],true)) cciReply(['success'=>false,'error'=>'Origin not allowed'],403);
    if ($action === 'create') {
        $amount = $body['amount'] ?? null;
        if (!is_int($amount) || $amount < 1 || $amount > CCI_GOAL/100) cciReply(['success'=>false,'error'=>'Choose a whole-rupee amount from ₹1 to ₹6,00,000.'],400);
        foreach (['name','email','phone'] as $field) if (!isset($body[$field]) || !is_string($body[$field])) cciReply(['success'=>false,'error'=>'Please enter your name, email and mobile number.'],400);
        $name=trim($body['name']); $email=trim($body['email']); $phone=trim($body['phone']);
        if (strlen($name)<2 || strlen($name)>120 || strlen($email)>254 || !filter_var($email,FILTER_VALIDATE_EMAIL) || !preg_match('/^[+0-9 ()-]{10,20}$/',$phone) || strlen(preg_replace('/\D/','',$phone)) < 10) cciReply(['success'=>false,'error'=>'Please check your name, email and mobile number.'],400);
        $totals=cciTotals($db);
        if ($totals['raised'] >= CCI_GOAL/100) cciReply(['success'=>false,'error'=>'This fundraiser has reached its goal. Thank you for your support.'],409);
        // Limit repeated order creation for a contact; also configure host-level request throttling.
        $limit=$db->prepare('SELECT COUNT(*) FROM cci_fund_donations WHERE email=? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)');
        $limit->execute([$email]);
        if ((int)$limit->fetchColumn() >= 5) cciReply(['success'=>false,'error'=>'Please wait a few minutes before starting another payment.'],429);
        $id=bin2hex(random_bytes(16));
        $insert=$db->prepare('INSERT INTO cci_fund_donations (id,campaign,name,email,phone,amount_paise,currency,mode) VALUES (?,?,?,?,?,?,?,?)');
        $insert->execute([$id,CCI_CAMPAIGN,$name,$email,$phone,$amount*100,'INR',cciMode()]);
        $order=cciProvider('POST','orders',['amount'=>$amount*100,'currency'=>'INR','receipt'=>$id,'notes'=>['campaign'=>CCI_CAMPAIGN,'donation_id'=>$id]]);
        if (!isset($order['id']) || !preg_match('/^order_[A-Za-z0-9]+$/',$order['id']) || ($order['amount'] ?? null) !== $amount*100 || ($order['currency'] ?? '') !== 'INR') throw new RuntimeException('Unexpected order response');
        $update=$db->prepare('UPDATE cci_fund_donations SET razorpay_order_id=? WHERE id=?'); $update->execute([$order['id'],$id]);
        cciReply(['success'=>true,'donationId'=>$id,'orderId'=>$order['id'],'amount'=>$amount*100,'keyId'=>cciKey()]);
    }
    if ($action === 'verify') {
        foreach (['donation_id','razorpay_order_id','razorpay_payment_id','razorpay_signature'] as $field) if (!isset($body[$field]) || !is_string($body[$field])) cciReply(['success'=>false,'error'=>'Incomplete payment confirmation'],400);
        if (!preg_match('/^[a-f0-9]{32}$/',$body['donation_id']) || !preg_match('/^pay_[A-Za-z0-9]+$/',$body['razorpay_payment_id'])) cciReply(['success'=>false,'error'=>'Invalid payment confirmation'],400);
        $stmt=$db->prepare('SELECT * FROM cci_fund_donations WHERE id=? AND campaign=? AND mode=?'); $stmt->execute([$body['donation_id'],CCI_CAMPAIGN,cciMode()]); $donation=$stmt->fetch();
        if (!$donation || !cciValidSignature($donation,$body,cciSecret())) cciReply(['success'=>false,'error'=>'Payment confirmation could not be verified'],400);
        // Fetch authoritative captured amount, currency and order from Razorpay.
        $payment=cciProvider('GET','payments/'.rawurlencode($body['razorpay_payment_id']));
        cciValidatePayment($donation,$payment);
        cciReply(cciConfirm($db,$payment));
    }
    cciReply(['success'=>false,'error'=>'Unknown action'],404);
} catch (Throwable $e) {
    error_log('CCI fundraiser: '.$e->getMessage());
    cciReply(['success'=>false,'error'=>'The donation service is temporarily unavailable. If money was debited, please do not pay again; contact HUManity with your payment reference.'],503);
}
