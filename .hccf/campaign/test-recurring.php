<?php
$apiDir=is_dir(__DIR__.'/deploy/backend')?__DIR__.'/deploy/backend/api/donations':dirname(__DIR__,2).'/backend/api/donations';
require $apiDir.'/donation-records.php';
function must($condition,$message){if(!$condition)throw new Exception($message);}
$row=['id'=>'d1','amount_paise'=>100000,'currency'=>'INR','donation_type'=>'monthly','commitment_months'=>12,'subscription_status'=>'active','razorpay_subscription_id'=>'sub_123','razorpay_plan_id'=>'plan_123'];
$body=['razorpay_subscription_id'=>'sub_123','razorpay_payment_id'=>'pay_123','razorpay_signature'=>hash_hmac('sha256','pay_123|sub_123','test')];
must(donationValidSignature($row,$body,'test'),'Monthly signature');
must(!donationValidSignature(array_merge($row,['razorpay_subscription_id'=>'sub_other']),$body,'test'),'Cross-subscription replay rejected');
$payment=['id'=>'pay_123','amount'=>100000,'currency'=>'INR','status'=>'captured','captured'=>true,'invoice_id'=>'inv_123','amount_refunded'=>0];
$invoice=['id'=>'inv_123','subscription_id'=>'sub_123','payment_id'=>'pay_123'];
donationValidatePayment($row,$payment,$invoice);
foreach([['subscription_id'=>'sub_other'],['payment_id'=>'pay_other'],['id'=>'inv_other']] as $change){$failed=false;try{donationValidatePayment($row,$payment,array_merge($invoice,$change));}catch(RuntimeException $e){$failed=true;}must($failed,'Invoice mismatch rejected');}
foreach([['amount'=>100],['currency'=>'USD'],['captured'=>false],['status'=>'authorized'],['amount_refunded'=>100001]] as $change){$failed=false;try{donationValidatePayment($row,array_merge($payment,$change),$invoice);}catch(RuntimeException $e){$failed=true;}must($failed,'Invalid capture rejected');}
must(donationCommitment($row,0,0)===0,'Unpaid pledge must not count');
must(donationCommitment($row,100000,0)===1200000,'₹1,000 first payment gives ₹12,000 commitment');
must(donationCommitment($row,200000,0)===1200000,'Second payment does not add another annual commitment');
must(donationCommitment(array_merge($row,['amount_paise'=>50000]),50000,0)===600000,'₹500 gives ₹6,000');
foreach(['cancelled','paused','halted','pending','completed'] as $status)must(donationCommitment(array_merge($row,['subscription_status'=>$status]),200000,0)===200000,'Inactive future commitment removed');
must(donationCommitment($row,100000,100000)===0,'Full refund removes commitment');
must(donationCommitment($row,200000,50000)===1150000,'Partial refund reduces commitment');
$once=['razorpay_order_id'=>'order_123','amount_paise'=>100000,'donation_type'=>'once'];
$onceBody=['razorpay_order_id'=>'order_123','razorpay_payment_id'=>'pay_123','razorpay_signature'=>hash_hmac('sha256','order_123|pay_123','test')];
must(donationValidSignature($once,$onceBody,'test'),'One-time signature');
must(!donationValidSignature(array_merge($once,['razorpay_order_id'=>'order_other']),$onceBody,'test'),'Cross-order replay rejected');
must(donationCommitment($once,100000,10000)===90000,'One-time payment not multiplied');
echo "Passed recurring commitment arithmetic, renewals, cancellation, refunds, and stored order/subscription verification.\n";
