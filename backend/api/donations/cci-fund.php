<?php
declare(strict_types=1);
require_once __DIR__.'/../config.php';
require_once __DIR__.'/donation-records.php';
require_once __DIR__.'/donation-webhook.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
const CCI_CAMPAIGN='indian-gypsy-children-home-suryapet';
const CCI_GOAL=60000000;
function cciReply(array $body,int $status=200): void {http_response_code($status);echo json_encode($body);exit;}
try {
    $method=$_SERVER['REQUEST_METHOD'];$action=$_GET['action']??'totals';
    if($method==='POST'&&$action==='webhook'){donationHandleWebhook();exit;}
    $db=getDB();donationEnsureSchema($db);
    if($method==='GET'&&$action==='totals')cciReply(donationCampaignTotals($db,CCI_CAMPAIGN,CCI_GOAL));
    if($method!=='POST')cciReply(['success'=>false,'error'=>'Method not allowed'],405);
    $origin=$_SERVER['HTTP_ORIGIN']??'';
    if($origin!==''&&!in_array($origin,['https://humanityorg.foundation','https://www.humanityorg.foundation'],true))cciReply(['success'=>false,'error'=>'Origin not allowed'],403);
    $raw=file_get_contents('php://input',false,null,0,65537);
    if(!is_string($raw)||strlen($raw)>65536)cciReply(['success'=>false,'error'=>'Request too large'],413);
    $body=json_decode($raw,true);
    if(!is_array($body))cciReply(['success'=>false,'error'=>'Invalid request'],400);
    if($action==='create'){
        $amount=$body['amount']??null;$type=$body['donationType']??'once';
        if(!is_int($amount)||$amount<1||$amount>CCI_GOAL/100||!in_array($type,['once','monthly'],true))cciReply(['success'=>false,'error'=>'Choose a valid donation amount and type.'],400);
        foreach(['name','email','phone','idType','idNumber'] as $field){if(!is_string($body[$field]??null)||trim($body[$field])==='')cciReply(['success'=>false,'error'=>'Please complete all personal and ID fields.'],400);}
        $name=trim($body['name']);$email=trim($body['email']);$phone=trim($body['phone']);$idType=$body['idType'];$idNumber=trim($body['idNumber']);
        if(strlen($name)<2||strlen($name)>120||strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL)||!preg_match('/^\+?[0-9 ()-]{10,20}$/',$phone)||!in_array($idType,['pan','aadhaar','ration'],true)||strlen($idNumber)>100)cciReply(['success'=>false,'error'=>'Please check your name, email, phone and ID details.'],400);
        $compact=str_replace(' ','',$idNumber);
        if(($idType==='pan'&&!preg_match('/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/',$compact))||($idType==='aadhaar'&&!preg_match('/^[0-9]{12}$/',$compact))||($idType==='ration'&&strlen($compact)<4))cciReply(['success'=>false,'error'=>'Please enter a valid ID number for the selected ID type.'],400);
        $totals=donationCampaignTotals($db,CCI_CAMPAIGN,CCI_GOAL);
        if($totals['committed']>=CCI_GOAL/100)cciReply(['success'=>false,'error'=>'This home’s yearly goal has been funded and committed. Thank you.'],409);
        $limit=$db->prepare('SELECT COUNT(*) FROM cci_fund_donations WHERE email=? AND created_at>DATE_SUB(NOW(),INTERVAL 10 MINUTE)');$limit->execute([$email]);
        if((int)$limit->fetchColumn()>=5)cciReply(['success'=>false,'error'=>'Please wait a few minutes before starting another payment.'],429);
        $id=bin2hex(random_bytes(16));
        $insert=$db->prepare('INSERT INTO cci_fund_donations (id,campaign,name,email,phone,id_type,id_number,amount_paise,currency,mode,donation_type,commitment_months) VALUES (?,?,?,?,?,?,?,?,?,?,?,12)');
        $insert->execute([$id,CCI_CAMPAIGN,$name,$email,$phone,$idType,$idNumber,$amount*100,'INR',donationMode(),$type]);
        if($type==='monthly'){
            $plan=donationProvider('POST','plans',['period'=>'monthly','interval'=>1,'item'=>['name'=>'HCCF Suryapet monthly support','amount'=>$amount*100,'currency'=>'INR']]);
            if(empty($plan['id']))throw new RuntimeException('Missing Razorpay plan');
            $sub=donationProvider('POST','subscriptions',['plan_id'=>$plan['id'],'total_count'=>12,'quantity'=>1,'customer_notify'=>1,'notes'=>['campaign'=>CCI_CAMPAIGN,'donation_id'=>$id]]);
            if(empty($sub['id'])||($sub['plan_id']??'')!==$plan['id'])throw new RuntimeException('Unexpected subscription response');
            $update=$db->prepare('UPDATE cci_fund_donations SET razorpay_subscription_id=?,razorpay_plan_id=?,subscription_status=? WHERE id=?');$update->execute([$sub['id'],$plan['id'],$sub['status']??'created',$id]);
            cciReply(['success'=>true,'isSubscription'=>true,'subscriptionId'=>$sub['id'],'donationId'=>$id,'amount'=>$amount*100,'annualCommitment'=>$amount*12,'keyId'=>donationKey()]);
        }
        $order=donationProvider('POST','orders',['amount'=>$amount*100,'currency'=>'INR','receipt'=>$id,'notes'=>['campaign'=>CCI_CAMPAIGN,'donation_id'=>$id]]);
        if(empty($order['id'])||($order['amount']??null)!==$amount*100||($order['currency']??'')!=='INR')throw new RuntimeException('Unexpected order response');
        $update=$db->prepare('UPDATE cci_fund_donations SET razorpay_order_id=? WHERE id=?');$update->execute([$order['id'],$id]);
        cciReply(['success'=>true,'isSubscription'=>false,'orderId'=>$order['id'],'donationId'=>$id,'amount'=>$amount*100,'keyId'=>donationKey()]);
    }
    if($action==='verify'){
        if(!is_string($body['donation_id']??null)||!preg_match('/^[a-f0-9]{32}$/',$body['donation_id']))cciReply(['success'=>false,'error'=>'Invalid donation confirmation'],400);
        $stmt=$db->prepare('SELECT * FROM cci_fund_donations WHERE id=? AND campaign=? AND mode=?');$stmt->execute([$body['donation_id'],CCI_CAMPAIGN,donationMode()]);$row=$stmt->fetch();
        if(!$row||!donationValidSignature($row,$body,donationSecret()))cciReply(['success'=>false,'error'=>'Payment confirmation could not be verified'],400);
        cciReply(donationVerify($db,'hccf',$row,$body));
    }
    cciReply(['success'=>false,'error'=>'Unknown action'],404);
}catch(Throwable $e){error_log('CCI fundraiser: '.$e->getMessage());cciReply(['success'=>false,'error'=>'We could not confirm the payment yet. If money was debited, please do not pay again; use Check payment confirmation or contact HUManity with your payment reference.'],503);}
