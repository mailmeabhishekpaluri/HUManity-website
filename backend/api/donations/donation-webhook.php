<?php
declare(strict_types=1);
// Included by cci-fund.php?action=webhook. Accepts authenticated events for both sources.
function donationFindByProviderId(PDO $db,string $source,string $field,string $value): ?array {
    if(!in_array($field,['razorpay_order_id','razorpay_subscription_id','razorpay_payment_id'],true))throw new InvalidArgumentException('Invalid lookup');
    $table=donationTable($source);$stmt=$db->prepare("SELECT * FROM `$table` WHERE `$field`=?");$stmt->execute([$value]);$row=$stmt->fetch();
    if($row&&$source==='hccf'&&$row['mode']!==donationMode('hccf'))return null;
    return $row?:null;
}
function donationSyncSubscription(PDO $db,string $source,array $row): void {
    donationRefreshSubscription($db,$source,$row);
    for($skip=0;$skip<1200;$skip+=100){
        $page=donationProvider('GET','invoices?subscription_id='.rawurlencode($row['razorpay_subscription_id']).'&count=100&skip='.$skip,null,$source);
        $items=$page['items']??[];
        foreach($items as $invoice){
            if(empty($invoice['payment_id']))continue;
            $payment=donationProvider('GET','payments/'.rawurlencode($invoice['payment_id']),null,$source);
            if(($payment['captured']??false)===true)donationRecordPayment($db,$source,$row,$payment,$invoice);
        }
        if(count($items)<100)break;
    }
}
function donationHandleWebhook(): void {
    $raw=file_get_contents('php://input',false,null,0,262145);
    $secret=getenv('CCI_RAZORPAY_WEBHOOK_SECRET')?:'';$sig=$_SERVER['HTTP_X_RAZORPAY_SIGNATURE']??'';
    if(!is_string($raw)||strlen($raw)>262144||$secret===''||!hash_equals(hash_hmac('sha256',$raw,$secret),$sig)){http_response_code(401);echo json_encode(['success'=>false]);return;}
    $body=json_decode($raw,true);$event=$body['event']??'';
    $db=getDB();donationEnsureSchema($db);
    $subId=$body['payload']['subscription']['entity']['id']??null;
    if(strpos($event,'subscription.')===0&&is_string($subId)){
        foreach(['hccf','main'] as $source){$row=donationFindByProviderId($db,$source,'razorpay_subscription_id',$subId);if($row){donationSyncSubscription($db,$source,$row);break;}}
        echo json_encode(['success'=>true]);return;
    }
    if(!in_array($event,['payment.captured','order.paid','refund.processed'],true)){echo json_encode(['success'=>true]);return;}
    $paymentId=$body['payload']['payment']['entity']['id']??$body['payload']['refund']['entity']['payment_id']??'';
    if(!is_string($paymentId)||!preg_match('/^pay_[A-Za-z0-9]+$/',$paymentId)){http_response_code(400);echo json_encode(['success'=>false]);return;}
    foreach(['hccf','main'] as $source){
        $payment=donationProvider('GET','payments/'.rawurlencode($paymentId),null,$source);
        $invoice=donationFetchInvoice($payment,$source);
        $row=!empty($invoice['subscription_id'])?donationFindByProviderId($db,$source,'razorpay_subscription_id',$invoice['subscription_id']):donationFindByProviderId($db,$source,'razorpay_order_id',$payment['order_id']??'');
        if(!$row)continue;
        donationRecordPayment($db,$source,$row,$payment,$invoice);
        if(($row['donation_type']??'once')==='monthly')donationRefreshSubscription($db,$source,$row);
        break;
    }
    echo json_encode(['success'=>true]);
}
