<?php
declare(strict_types=1);
// Called only after backend/admin.php has authenticated the administrator.
function donationAdminRows(PDO $db): array {
    $rows=[];
    foreach(['main','hccf'] as $source){
        $table=donationTable($source);
        $stmt=$db->prepare("SELECT d.*,COALESCE(p.gross,0) gross,COALESCE(p.refunds,0) refunds,COALESCE(p.payments,0) payments
            FROM `$table` d LEFT JOIN (SELECT donation_id,SUM(amount_paise) gross,SUM(refunded_paise) refunds,COUNT(*) payments
            FROM donation_payments WHERE source=? GROUP BY donation_id) p ON p.donation_id=d.id ORDER BY d.created_at DESC");
        $stmt->execute([$source]);
        foreach($stmt->fetchAll() as $row){
            $row['amount_paise']=$source==='hccf'?(int)$row['amount_paise']:(int)$row['amount']*100;
            $rows[]=['id'=>$source.':'.$row['id'],'source'=>$source==='hccf'?'HCCF fundraiser':'Main donation page',
                'campaign'=>$row['campaign']??'General HUManity donations','name_as_per_id'=>$row['name'],'email'=>$row['email'],'phone'=>$row['phone'],
                'id_proof_type'=>$row['id_type']??'','id_number'=>$row['id_number']??'',
                'donation_type'=>$row['donation_type'],'amount_per_payment_inr'=>$row['amount_paise']/100,
                'received_inr'=>max(0,(int)$row['gross']-(int)$row['refunds'])/100,
                'yearly_funded_and_committed_inr'=>donationCommitment($row,(int)$row['gross'],(int)$row['refunds'])/100,
                'refunds_inr'=>(int)$row['refunds']/100,'payments_received'=>$row['payments'],'status'=>$row['status'],
                'subscription_status'=>$row['subscription_status']??'','razorpay_order_id'=>$row['razorpay_order_id']??'',
                'razorpay_subscription_id'=>$row['razorpay_subscription_id']??'','first_payment_id'=>$row['razorpay_payment_id']??'',
                'mode'=>$row['mode']??donationMode('main'),'created_at'=>$row['created_at']];
        }
    }
    usort($rows,fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));return $rows;
}
function donationAdminPayments(PDO $db): array {
    $rows=[];
    foreach(['main','hccf'] as $source){
        $table=donationTable($source);
        $stmt=$db->prepare("SELECT p.*,d.name,d.email,d.phone,d.id_type,d.id_number,d.donation_type FROM donation_payments p JOIN `$table` d ON d.id=p.donation_id WHERE p.source=? ORDER BY p.paid_at DESC");$stmt->execute([$source]);
        foreach($stmt->fetchAll() as $row){$rows[]=['id'=>$row['payment_id'],'source'=>$source==='hccf'?'HCCF fundraiser':'Main donation page',
            'name_as_per_id'=>$row['name'],'email'=>$row['email'],'phone'=>$row['phone'],'id_proof_type'=>$row['id_type'],'id_number'=>$row['id_number'],
            'donation_type'=>$row['donation_type'],'paid_inr'=>(int)$row['amount_paise']/100,'refunded_inr'=>(int)$row['refunded_paise']/100,
            'net_received_inr'=>((int)$row['amount_paise']-(int)$row['refunded_paise'])/100,'mode'=>$row['mode'],'created_at'=>$row['paid_at']];}
    }
    usort($rows,fn($a,$b)=>strcmp($b['created_at'],$a['created_at']));return $rows;
}
