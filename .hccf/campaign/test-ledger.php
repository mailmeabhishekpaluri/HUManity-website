<?php
// Synthetic SQLite integration test; translates only MySQL locking/upsert syntax.
$apiDir=is_dir(__DIR__.'/deploy/backend')?__DIR__.'/deploy/backend/api/donations':dirname(__DIR__,2).'/backend/api/donations';
require $apiDir.'/donation-records.php';
require $apiDir.'/donation-admin.php';
define('RAZORPAY_KEY_ID','rzp_live_fixture');
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
class LedgerTestDB extends PDO {
    public function prepare(string $query,array $options=[]): PDOStatement|false {
        $query=str_replace([' FOR UPDATE','NOW()','BINARY '],['','CURRENT_TIMESTAMP',''],$query);
        $query=str_replace('ON DUPLICATE KEY UPDATE refunded_paise=GREATEST(refunded_paise,VALUES(refunded_paise))','ON CONFLICT(payment_id) DO UPDATE SET refunded_paise=MAX(refunded_paise,excluded.refunded_paise)',$query);
        return parent::prepare($query,$options);
    }
}
$db=new LedgerTestDB('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$columns="id TEXT PRIMARY KEY,name TEXT,email TEXT,phone TEXT,id_type TEXT,id_number TEXT,donation_type TEXT,subscription_status TEXT,razorpay_order_id TEXT,razorpay_payment_id TEXT,razorpay_subscription_id TEXT,razorpay_plan_id TEXT,status TEXT DEFAULT 'pending',created_at TEXT DEFAULT CURRENT_TIMESTAMP";
$db->exec("CREATE TABLE donations ($columns,amount INTEGER)");
$db->exec("CREATE TABLE cci_fund_donations ($columns,amount_paise INTEGER,mode TEXT,campaign TEXT,commitment_months INTEGER DEFAULT 12,confirmed_at TEXT)");
$db->exec('CREATE TABLE donation_payments (payment_id TEXT PRIMARY KEY,source TEXT,donation_id TEXT,mode TEXT,amount_paise INTEGER,refunded_paise INTEGER,paid_at TEXT)');
$db->exec("INSERT INTO cci_fund_donations(id,name,email,phone,id_type,id_number,donation_type,subscription_status,razorpay_subscription_id,razorpay_plan_id,amount_paise,mode,campaign) VALUES('cci1','Synthetic donor','fixture@example.invalid','0000000000','ration','TEST-ONLY','monthly','active','sub_1','plan_1',100000,'live','home1')");
$row=$db->query("SELECT * FROM cci_fund_donations WHERE id='cci1'")->fetch();
$pay=['id'=>'pay_1','amount'=>100000,'currency'=>'INR','status'=>'captured','captured'=>true,'invoice_id'=>'inv_1','amount_refunded'=>0];
$invoice=['id'=>'inv_1','subscription_id'=>'sub_1','payment_id'=>'pay_1'];
donationRecordPayment($db,'hccf',$row,$pay,$invoice);
donationRecordPayment($db,'hccf',$row,$pay,$invoice);
check((int)$db->query('SELECT COUNT(*) FROM donation_payments')->fetchColumn()===1,'Checkout/webhook replay must create one payment');
$totals=donationCampaignTotals($db,'home1',60000000);
check($totals['received']==1000&&$totals['committed']==12000,'First receipt and annual commitment');
$pay2=array_merge($pay,['id'=>'pay_2','invoice_id'=>'inv_2']);$inv2=array_merge($invoice,['id'=>'inv_2','payment_id'=>'pay_2']);
donationRecordPayment($db,'hccf',$row,$pay2,$inv2);
$totals=donationCampaignTotals($db,'home1',60000000);
check($totals['received']==2000&&$totals['committed']==12000,'Renewal adds received money without doubling annual commitment');
donationRecordPayment($db,'hccf',$row,array_merge($pay,['amount_refunded'=>50000]),$invoice);
donationRecordPayment($db,'hccf',$row,$pay,$invoice);
$totals=donationCampaignTotals($db,'home1',60000000);
check($totals['received']==1500&&$totals['committed']==11500,'Out-of-order events cannot undo refunds');
$db->exec("INSERT INTO donations(id,name,email,phone,id_type,id_number,donation_type,razorpay_order_id,amount) VALUES('main1','Other synthetic donor','main@example.invalid','0000000000','ration','TEST-MAIN','once','order_1',500)");
$main=$db->query("SELECT * FROM donations WHERE id='main1'")->fetch();
donationRecordPayment($db,'main',$main,['id'=>'pay_3','order_id'=>'order_1','amount'=>50000,'currency'=>'INR','captured'=>true,'status'=>'captured']);
$admin=donationAdminRows($db);$history=donationAdminPayments($db);
check(count($admin)===2&&count($history)===3,'Both pages and all individual payments appear in admin');
check(in_array('TEST-ONLY',array_column($admin,'id_number'),true)&&in_array('TEST-MAIN',array_column($history,'id_number'),true),'Donor ID fields remain attached to their payments');
check(!array_key_exists('id_number',$totals),'Public totals contain no donor IDs');
$db->exec("UPDATE cci_fund_donations SET subscription_status='cancelled' WHERE id='cci1'");
$totals=donationCampaignTotals($db,'home1',60000000);
check($totals['committed']==1500,'Cancellation removes future instalments');
echo "Passed ledger replay, renewal, refund ordering, cancellation, combined dashboard and donor-field integration.\n";
$campaigns=require $apiDir.'/cci-campaigns.php';
check(count($campaigns)===7,'Seven registered campaigns');
foreach($campaigns as $id=>$settings)check($settings['goal']===60000000,'Each goal is six lakh rupees');
$homeIds=array_keys($campaigns);
$db->exec('ALTER TABLE cci_fund_donations ADD COLUMN attribution_json TEXT');
$add=$db->prepare("INSERT INTO cci_fund_donations(id,campaign,name,email,phone,id_type,id_number,amount_paise,donation_type,razorpay_order_id,mode,attribution_json) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)");
foreach($homeIds as $i=>$home){
    $add->execute(['home'.$i,$home,'Synthetic donor','test@example.invalid','0000000000','ration','FIXTURE-ONLY',($i+1)*10000,'once','order_home'.$i,'live',json_encode(['utm_source'=>'instagram','utm_campaign'=>$home,'ad_id'=>'fixture-ad'])]);
    $record=$db->query("SELECT * FROM cci_fund_donations WHERE id='home$i'")->fetch();
    donationRecordPayment($db,'hccf',$record,['id'=>'pay_home'.$i,'order_id'=>'order_home'.$i,'amount'=>($i+1)*10000,'currency'=>'INR','captured'=>true,'status'=>'captured']);
}
foreach($homeIds as $i=>$home){
    $stats=donationCampaignTotals($db,$home,60000000);
    check($stats['received']===($i+1)*100&&$stats['committed']===($i+1)*100,'Each home retains only its own donation');
}
$add->execute(['sandbox',$homeIds[0],'Synthetic donor','test@example.invalid','0000000000','ration','FIXTURE-ONLY',900000,'once','order_test','test','{}']);
$db->exec("INSERT INTO donation_payments VALUES('pay_sandbox','hccf','sandbox','test',900000,0,CURRENT_TIMESTAMP)");
check(donationCampaignTotals($db,$homeIds[0],60000000)['received']===100,'Test-mode money excluded from public live goal');
$rows=donationAdminRows($db);
$matched=array_values(array_filter($rows,fn($r)=>$r['id']==='hccf:home0'))[0];
check($matched['home_name']===$campaigns[$homeIds[0]]['name']&&$matched['utm_source']==='instagram'&&$matched['ad_id']==='fixture-ad','Home and campaign attribution available in dashboard');
check(isset($campaigns['indian-gypsy-children-home-suryapet']),'Original home ID preserved for existing donors');
echo "Passed seven-home isolation, campaign goals, live/test separation, original campaign ID and dashboard attribution.\n";
