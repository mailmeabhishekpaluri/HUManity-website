<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/donation-records.php';
setCorsHeaders();
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(['success'=>false,'error'=>'Method not allowed'],405);
try {
    $body=getBody();$id=$body['donation_id']??'';
    if(!is_string($id)||$id==='')jsonResponse(['success'=>false,'error'=>'Missing donation ID'],400);
    $db=getDB();donationEnsureSchema($db);
    $stmt=$db->prepare('SELECT * FROM donations WHERE id=?');$stmt->execute([$id]);$row=$stmt->fetch();
    if(!$row||($row['donation_type']??'once')!==$expectedDonationType||!donationValidSignature($row,$body,donationSecret('main')))jsonResponse(['success'=>false,'error'=>'Payment confirmation could not be verified'],400);
    $result=donationVerify($db,'main',$row,$body);
    jsonResponse($result+['message'=>'Payment verified successfully']);
}catch(Throwable $e){error_log('Main donation verification: '.$e->getMessage());jsonResponse(['success'=>false,'error'=>'Payment confirmation is pending. If money was debited, please do not pay again.'],503);}
