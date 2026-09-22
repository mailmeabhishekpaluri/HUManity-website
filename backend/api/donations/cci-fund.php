<?php
declare(strict_types=1);
require_once __DIR__.'/../config.php';
require_once __DIR__.'/donation-records.php';
require_once __DIR__.'/donation-webhook.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
const CCI_CAMPAIGN='indian-gypsy-children-home-suryapet';
function cciReply(array $body,int $status=200): void {http_response_code($status);echo json_encode($body);exit;}
function cciAttribution($input): string {
    $out=[];
    foreach(['utm_source','utm_medium','utm_campaign','utm_content','utm_term','utm_id','campaign_id','adset_id','ad_id'] as $key){
        if(is_array($input)&&isset($input[$key])&&is_string($input[$key]))$out[$key]=substr(preg_replace('/[\x00-\x1f\x7f]/','',$input[$key]),0,160);
    }
    return json_encode($out,JSON_INVALID_UTF8_SUBSTITUTE);
}
try {
    $method=$_SERVER['REQUEST_METHOD'];$action=$_GET['action']??'totals';
    if($method==='POST'&&$action==='webhook'){donationHandleWebhook();exit;}
    $db=getDB();donationEnsureSchema($db);
    $campaigns=require __DIR__.'/cci-campaigns.php';
    $requested=$_GET['campaign']??CCI_CAMPAIGN;
    if(!is_string($requested)||!isset($campaigns[$requested]))cciReply(['success'=>false,'error'=>'This fundraiser could not be found.'],404);
    $campaign=$requested;$goal=$campaigns[$campaign]['goal'];$campaignName=$campaigns[$campaign]['name'];
    if($method==='GET'&&$action==='campaigns'){
        $totals=[];foreach($campaigns as $id=>$settings)$totals[$id]=donationCampaignTotals($db,$id,$settings['goal']);
        cciReply(['success'=>true,'campaigns'=>$totals]);
    }
    if($method==='GET'&&$action==='totals')cciReply(donationCampaignTotals($db,$campaign,$goal));
    if($method!=='POST')cciReply(['success'=>false,'error'=>'Method not allowed'],405);
    $origin=$_SERVER['HTTP_ORIGIN']??'';
    if($origin!==''&&!in_array($origin,['https://humanityorg.foundation','https://www.humanityorg.foundation'],true))cciReply(['success'=>false,'error'=>'Origin not allowed'],403);
    $raw=file_get_contents('php://input',false,null,0,65537);
    if(!is_string($raw)||strlen($raw)>65536)cciReply(['success'=>false,'error'=>'Request too large'],413);
    $body=json_decode($raw,true);
    if(!is_array($body))cciReply(['success'=>false,'error'=>'Invalid request'],400);
    if(isset($body['campaign'])&&$body['campaign']!==$campaign)cciReply(['success'=>false,'error'=>'The fundraiser does not match this request.'],400);
    if($action==='create'){
        $amount=$body['amount']??null;$type=$body['donationType']??'once';
        $maxAmount=$type==='monthly'?intdiv($goal,1200):intdiv($goal,100);
        if(!is_int($amount)||$amount<1||$amount>$maxAmount||!in_array($type,['once','monthly'],true))cciReply(['success'=>false,'error'=>'Choose a valid donation amount and type.'],400);
        foreach(['name','email','phone','idType','idNumber'] as $field){if(!is_string($body[$field]??null)||trim($body[$field])==='')cciReply(['success'=>false,'error'=>'Please complete all personal and ID fields.'],400);}
        $name=trim($body['name']);$email=trim($body['email']);$phone=trim($body['phone']);$idType=$body['idType'];$idNumber=trim($body['idNumber']);
        if(strlen($name)<2||strlen($name)>120||strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL)||!preg_match('/^\+?[0-9 ()-]{10,20}$/',$phone)||!in_array($idType,['pan','aadhaar','ration'],true)||strlen($idNumber)>100)cciReply(['success'=>false,'error'=>'Please check your name, email, phone and ID details.'],400);
        $compact=str_replace(' ','',$idNumber);
        if(($idType==='pan'&&!preg_match('/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/',$compact))||($idType==='aadhaar'&&!preg_match('/^[0-9]{12}$/',$compact))||($idType==='ration'&&strlen($compact)<4))cciReply(['success'=>false,'error'=>'Please enter a valid ID number for the selected ID type.'],400);
        $totals=donationCampaignTotals($db,$campaign,$goal);
        if($totals['committed']>=$goal/100)cciReply(['success'=>false,'error'=>'This home’s yearly goal has been funded and committed. Thank you.'],409);
        $limit=$db->prepare('SELECT COUNT(*) FROM cci_fund_donations WHERE email=? AND created_at>DATE_SUB(NOW(),INTERVAL 10 MINUTE)');$limit->execute([$email]);
        if((int)$limit->fetchColumn()>=5)cciReply(['success'=>false,'error'=>'Please wait a few minutes before starting another payment.'],429);
        $id=bin2hex(random_bytes(16));
        $insert=$db->prepare('INSERT INTO cci_fund_donations (id,campaign,name,email,phone,id_type,id_number,amount_paise,currency,mode,donation_type,commitment_months,attribution_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,12,?)');
        $insert->execute([$id,$campaign,$name,$email,$phone,$idType,$idNumber,$amount*100,'INR',donationMode(),$type,cciAttribution($body['attribution']??null)]);
        if($type==='monthly'){
            $plan=donationProvider('POST','plans',['period'=>'monthly','interval'=>1,'item'=>['name'=>'HCCF: '.$campaignName,'amount'=>$amount*100,'currency'=>'INR']]);
            if(empty($plan['id']))throw new RuntimeException('Missing Razorpay plan');
            $sub=donationProvider('POST','subscriptions',['plan_id'=>$plan['id'],'total_count'=>12,'quantity'=>1,'customer_notify'=>1,'notes'=>['campaign'=>$campaign,'donation_id'=>$id]]);
            if(empty($sub['id'])||($sub['plan_id']??'')!==$plan['id'])throw new RuntimeException('Unexpected subscription response');
            $update=$db->prepare('UPDATE cci_fund_donations SET razorpay_subscription_id=?,razorpay_plan_id=?,subscription_status=? WHERE id=?');$update->execute([$sub['id'],$plan['id'],$sub['status']??'created',$id]);
            cciReply(['success'=>true,'isSubscription'=>true,'subscriptionId'=>$sub['id'],'donationId'=>$id,'amount'=>$amount*100,'annualCommitment'=>$amount*12,'keyId'=>donationKey()]);
        }
        $order=donationProvider('POST','orders',['amount'=>$amount*100,'currency'=>'INR','receipt'=>$id,'notes'=>['campaign'=>$campaign,'donation_id'=>$id]]);
        if(empty($order['id'])||($order['amount']??null)!==$amount*100||($order['currency']??'')!=='INR')throw new RuntimeException('Unexpected order response');
        $update=$db->prepare('UPDATE cci_fund_donations SET razorpay_order_id=? WHERE id=?');$update->execute([$order['id'],$id]);
        cciReply(['success'=>true,'isSubscription'=>false,'orderId'=>$order['id'],'donationId'=>$id,'amount'=>$amount*100,'keyId'=>donationKey()]);
    }
    if($action==='verify'){
        if(!is_string($body['donation_id']??null)||!preg_match('/^[a-f0-9]{32}$/',$body['donation_id']))cciReply(['success'=>false,'error'=>'Invalid donation confirmation'],400);
        $stmt=$db->prepare('SELECT * FROM cci_fund_donations WHERE id=? AND campaign=? AND mode=?');$stmt->execute([$body['donation_id'],$campaign,donationMode()]);$row=$stmt->fetch();
        if(!$row||!donationValidSignature($row,$body,donationSecret()))cciReply(['success'=>false,'error'=>'Payment confirmation could not be verified'],400);
        cciReply(donationVerify($db,'hccf',$row,$body));
    }
    cciReply(['success'=>false,'error'=>'Unknown action'],404);
}catch(Throwable $e){error_log('CCI fundraiser: '.$e->getMessage());cciReply(['success'=>false,'error'=>'We could not confirm the payment yet. If money was debited, please do not pay again; use Check payment confirmation or contact HUManity with your payment reference.'],503);}
