<?php
declare(strict_types=1);

// Shared by the main donation page, HCCF, webhooks and the authenticated admin.
// This file has no public response and never returns donor identities in totals.
function donationKey(string $source = 'hccf'): string {
    return $source === 'hccf' ? (getenv('CCI_RAZORPAY_KEY_ID') ?: RAZORPAY_KEY_ID) : RAZORPAY_KEY_ID;
}
function donationSecret(string $source = 'hccf'): string {
    return $source === 'hccf' ? (getenv('CCI_RAZORPAY_KEY_SECRET') ?: RAZORPAY_KEY_SECRET) : RAZORPAY_KEY_SECRET;
}
function donationMode(string $source = 'hccf'): string { return strpos(donationKey($source), 'rzp_live_') === 0 ? 'live' : 'test'; }
function donationProvider(string $method, string $path, ?array $data = null, string $source = 'hccf'): array {
    $ch = curl_init('https://api.razorpay.com/v1/' . $path);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_USERPWD=>donationKey($source).':'.donationSecret($source),
        CURLOPT_CUSTOMREQUEST=>$method, CURLOPT_HTTPHEADER=>['Content-Type: application/json'], CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>20]);
    if ($data !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $raw = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $result = is_string($raw) ? json_decode($raw, true) : null;
    if ($raw === false || $code < 200 || $code >= 300 || !is_array($result)) throw new RuntimeException('Payment provider request failed');
    return $result;
}
function donationTable(string $source): string {
    if ($source === 'hccf') return 'cci_fund_donations';
    if ($source === 'main') return 'donations';
    throw new InvalidArgumentException('Invalid donation source');
}
function donationAddColumns(PDO $db, string $table, array $definitions): void {
    $present = $db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($definitions as $column=>$definition) {
        if (in_array($column, $present, true)) continue;
        try { $db->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition"); }
        catch (PDOException $e) { if (($e->errorInfo[1] ?? null) !== 1060) throw $e; }
    }
}
function donationEnsureSchema(PDO $db): void {
    static $ready = false;
    if ($ready) return;
    $db->exec("CREATE TABLE IF NOT EXISTS cci_fund_donations (
        id CHAR(32) PRIMARY KEY, campaign VARCHAR(80) NOT NULL, name VARCHAR(120) NOT NULL,
        email VARCHAR(254) NOT NULL, phone VARCHAR(20) NOT NULL, amount_paise BIGINT UNSIGNED NOT NULL,
        currency CHAR(3) NOT NULL DEFAULT 'INR', mode VARCHAR(4) NOT NULL,
        razorpay_order_id VARCHAR(100) UNIQUE, razorpay_payment_id VARCHAR(100) UNIQUE,
        amount_refunded_paise BIGINT UNSIGNED NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'pending', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        confirmed_at DATETIME NULL, INDEX campaign_status (campaign,mode,status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    donationAddColumns($db, 'cci_fund_donations', [
        'id_type'=>"VARCHAR(50) NOT NULL DEFAULT ''", 'id_number'=>"VARCHAR(100) NOT NULL DEFAULT ''",
        'donation_type'=>"VARCHAR(10) NOT NULL DEFAULT 'once'", 'commitment_months'=>'INT NOT NULL DEFAULT 12',
        'razorpay_subscription_id'=>'VARCHAR(100) NULL', 'razorpay_plan_id'=>'VARCHAR(100) NULL',
        'subscription_status'=>"VARCHAR(24) NOT NULL DEFAULT ''",
        'attribution_json'=>'TEXT NULL',
    ]);
    donationAddColumns($db, 'donations', ['subscription_status'=>"VARCHAR(24) NOT NULL DEFAULT ''"]);
    $db->exec("CREATE TABLE IF NOT EXISTS donation_payments (
        payment_id VARCHAR(100) PRIMARY KEY, source VARCHAR(10) NOT NULL, donation_id VARCHAR(36) NOT NULL,
        mode VARCHAR(4) NOT NULL, amount_paise BIGINT UNSIGNED NOT NULL, refunded_paise BIGINT UNSIGNED NOT NULL DEFAULT 0,
        paid_at DATETIME NOT NULL, recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX donation_lookup (source,donation_id,mode)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // Preserve already-recorded first payments; never seed pending entries or multiply receipts.
    $db->exec("INSERT IGNORE INTO donation_payments (payment_id,source,donation_id,mode,amount_paise,refunded_paise,paid_at)
        SELECT razorpay_payment_id,'hccf',id,mode,amount_paise,amount_refunded_paise,COALESCE(confirmed_at,created_at)
        FROM cci_fund_donations WHERE status='completed' AND razorpay_payment_id IS NOT NULL AND razorpay_payment_id<>''");
    $seed = $db->prepare("INSERT IGNORE INTO donation_payments (payment_id,source,donation_id,mode,amount_paise,refunded_paise,paid_at)
        SELECT razorpay_payment_id,'main',id,?,amount*100,0,created_at FROM donations
        WHERE status='completed' AND razorpay_payment_id IS NOT NULL AND razorpay_payment_id<>''");
    $seed->execute([donationMode('main')]);
    $ready = true;
}
function donationValidSignature(array $row, array $body, string $secret): bool {
    $paymentId = $body['razorpay_payment_id'] ?? '';
    if (!is_string($paymentId) || !preg_match('/^pay_[A-Za-z0-9]+$/', $paymentId)) return false;
    if (($row['donation_type'] ?? 'once') === 'monthly') {
        $stored = $row['razorpay_subscription_id'] ?? '';
        if (!$stored || $stored !== ($body['razorpay_subscription_id'] ?? '')) return false;
        $message = $paymentId.'|'.$stored;
    } else {
        $stored = $row['razorpay_order_id'] ?? '';
        if (!$stored || $stored !== ($body['razorpay_order_id'] ?? '')) return false;
        $message = $stored.'|'.$paymentId;
    }
    return is_string($body['razorpay_signature'] ?? null) && hash_equals(hash_hmac('sha256',$message,$secret),$body['razorpay_signature']);
}
function donationValidatePayment(array $row, array $payment, ?array $invoice = null): void {
    $amount = isset($row['amount_paise']) ? (int)$row['amount_paise'] : (int)$row['amount']*100;
    if (!is_string($payment['id'] ?? null) || !preg_match('/^pay_[A-Za-z0-9]+$/',$payment['id']) ||
        (int)($payment['amount'] ?? -1) !== $amount || ($payment['currency'] ?? '') !== 'INR' ||
        !in_array($payment['status'] ?? '',['captured','refunded'],true) || ($payment['captured'] ?? false) !== true ||
        (int)($payment['amount_refunded'] ?? 0) < 0 || (int)($payment['amount_refunded'] ?? 0) > $amount) {
        throw new RuntimeException('Payment amount, currency or capture status does not match');
    }
    if (($row['donation_type'] ?? 'once') === 'monthly') {
        if (!$invoice || empty($row['razorpay_subscription_id']) ||
            ($invoice['subscription_id'] ?? '') !== $row['razorpay_subscription_id'] ||
            ($invoice['id'] ?? '') !== ($payment['invoice_id'] ?? null) ||
            ($invoice['payment_id'] ?? '') !== $payment['id']) throw new RuntimeException('Payment belongs to a different subscription');
    } elseif (empty($row['razorpay_order_id']) || ($payment['order_id'] ?? '') !== $row['razorpay_order_id']) {
        throw new RuntimeException('Payment belongs to a different order');
    }
}
function donationCommitment(array $row, int $gross, int $refunds): int {
    $received = max(0,$gross-$refunds);
    if (($row['donation_type'] ?? 'once') !== 'monthly' || $received === 0) return $received;
    if (!in_array($row['subscription_status'] ?? '', ['active','authenticated'], true)) return $received;
    return max($received,(int)$row['amount_paise']*(int)($row['commitment_months'] ?? 12)-$refunds);
}
function donationRecordPayment(PDO $db, string $source, array $row, array $payment, ?array $invoice = null): void {
    donationValidatePayment($row,$payment,$invoice);
    $table = donationTable($source);
    $db->beginTransaction();
    try {
        $lock = $db->prepare("SELECT * FROM `$table` WHERE id=? FOR UPDATE"); $lock->execute([$row['id']]);
        $stored = $lock->fetch();
        if (!$stored) throw new RuntimeException('Unknown donation');
        donationValidatePayment($stored,$payment,$invoice);
        $check = $db->prepare('SELECT * FROM donation_payments WHERE payment_id=? FOR UPDATE'); $check->execute([$payment['id']]); $prior=$check->fetch();
        if ($prior && ($prior['source'] !== $source || $prior['donation_id'] !== $row['id'])) throw new RuntimeException('Payment already assigned');
        if (($stored['donation_type'] ?? 'once') !== 'monthly' && !empty($stored['razorpay_payment_id']) && $stored['razorpay_payment_id'] !== $payment['id']) throw new RuntimeException('Order already paid');
        $refunds=max((int)($prior['refunded_paise'] ?? 0),(int)($payment['amount_refunded'] ?? 0));
        $write=$db->prepare('INSERT INTO donation_payments (payment_id,source,donation_id,mode,amount_paise,refunded_paise,paid_at)
            VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE refunded_paise=GREATEST(refunded_paise,VALUES(refunded_paise))');
        $write->execute([$payment['id'],$source,$row['id'],$source==='hccf'?$row['mode']:donationMode('main'),$payment['amount'],$refunds,gmdate('Y-m-d H:i:s',(int)($payment['created_at'] ?? time()))]);
        // Verify ownership after the unique-key write for concurrent requests too.
        $check->execute([$payment['id']]);$assigned=$check->fetch();
        if(!$assigned||$assigned['source']!==$source||$assigned['donation_id']!==$row['id'])throw new RuntimeException('Payment already assigned');
        $update=$db->prepare("UPDATE `$table` SET status='completed',razorpay_payment_id=COALESCE(NULLIF(razorpay_payment_id,''),?) WHERE id=?");
        $update->execute([$payment['id'],$row['id']]);
        if ($source==='hccf') {
            $update=$db->prepare('UPDATE cci_fund_donations SET confirmed_at=COALESCE(confirmed_at,NOW()) WHERE id=?');$update->execute([$row['id']]);
        }
        $db->commit();
        if (!$prior && function_exists('sendEmail')) {
            try { sendEmail('[HUManity] Donation received: ₹'.($payment['amount']/100),
                "Source: $source\nName: ".$row['name']."\nAmount: ₹".($payment['amount']/100)."\nPayment reference: ".$payment['id']); }
            catch (Throwable $mailError) { error_log('Donation recorded; email notification failed'); }
        }
    } catch (Throwable $e) { if($db->inTransaction())$db->rollBack();throw $e; }
}
function donationRefreshSubscription(PDO $db, string $source, array $row): array {
    $sub=donationProvider('GET','subscriptions/'.rawurlencode($row['razorpay_subscription_id']),null,$source);
    if (($sub['id'] ?? '') !== $row['razorpay_subscription_id'] || ($sub['plan_id'] ?? '') !== $row['razorpay_plan_id']) throw new RuntimeException('Subscription mismatch');
    $table=donationTable($source);$stmt=$db->prepare("UPDATE `$table` SET subscription_status=? WHERE id=?");$stmt->execute([$sub['status'],$row['id']]);
    return $sub;
}
function donationFetchInvoice(array $payment, string $source): ?array {
    if (empty($payment['invoice_id'])) return null;
    return donationProvider('GET','invoices/'.rawurlencode($payment['invoice_id']),null,$source);
}
function donationVerify(PDO $db, string $source, array $row, array $body): array {
    if (!donationValidSignature($row,$body,donationSecret($source))) throw new RuntimeException('Invalid signature or donation association');
    $payment=donationProvider('GET','payments/'.rawurlencode($body['razorpay_payment_id']),null,$source);
    if (($payment['id'] ?? '') !== $body['razorpay_payment_id']) throw new RuntimeException('Unexpected payment response');
    $monthly=($row['donation_type'] ?? 'once')==='monthly';
    donationRecordPayment($db,$source,$row,$payment,$monthly?donationFetchInvoice($payment,$source):null);
    if($monthly)donationRefreshSubscription($db,$source,$row);
    return ['success'=>true,'paymentId'=>$payment['id'],'amount'=>$payment['amount']/100,'donationType'=>$monthly?'monthly':'once'];
}
function donationCampaignTotals(PDO $db, string $campaign, int $goal): array {
    $stmt=$db->prepare("SELECT d.*,COALESCE(p.gross,0) gross,COALESCE(p.refunds,0) refunds FROM cci_fund_donations d
        LEFT JOIN (SELECT donation_id,SUM(amount_paise) gross,SUM(refunded_paise) refunds FROM donation_payments WHERE source='hccf' AND mode='live' GROUP BY donation_id) p ON BINARY p.donation_id=BINARY d.id
        WHERE d.campaign=? AND d.mode='live'");$stmt->execute([$campaign]);
    $received=0;$committed=0;
    foreach($stmt->fetchAll() as $row){$received+=max(0,(int)$row['gross']-(int)$row['refunds']);$committed+=donationCommitment($row,(int)$row['gross'],(int)$row['refunds']);}
    return ['success'=>true,'raised'=>$received/100,'received'=>$received/100,'committed'=>$committed/100,'goal'=>$goal/100,'acceptingDonations'=>true];
}
