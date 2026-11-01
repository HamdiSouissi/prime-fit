<?php
declare(strict_types=1);
/** Lecture ciblée : chaque rubrique respecte les droits des modules existants. */
class MemberProfile {
    public function __construct(private PDO $db) {}
    public function get(int $id,string $role): ?array {
        if (!in_array($role,['Admin','Gérant','Réceptionniste','Coach','Comptable'],true)) throw new DomainException('Accès interdit.');
        $contacts=in_array($role,['Admin','Gérant','Réceptionniste'],true);
        $payments=in_array($role,['Admin','Gérant','Réceptionniste','Comptable'],true);
        $attendance=in_array($role,['Admin','Gérant','Réceptionniste','Coach'],true);
        $columns='id,first_name,last_name,phone,card_number,governorate,status,created_at'.($contacts ? ',email,birth_date' : '');
        $stmt=$this->db->prepare('SELECT '.$columns.' FROM members WHERE id=?'); $stmt->execute([$id]); $member=$stmt->fetch(); if (!$member) return null;
        $get=function(string $sql) use ($id): array { $stmt=$this->db->prepare($sql); $stmt->execute([$id]); return $stmt->fetchAll(); };
        $count=function(string $table) use ($id): int { $stmt=$this->db->prepare('SELECT COUNT(*) FROM '.$table.' WHERE member_id=?'); $stmt->execute([$id]); return (int)$stmt->fetchColumn(); };
        $data=['member'=>$member, 'canContacts'=>$contacts, 'canPayments'=>$payments, 'canAttendance'=>$attendance,
            'subscriptions'=>$get('SELECT s.id,s.start_date,s.end_date,s.status,p.name AS plan FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE s.member_id=? ORDER BY s.start_date DESC,s.id DESC LIMIT 10'),
            'subscriptionCount'=>$count('subscriptions')];
        if ($payments) {
            $data['payments']=$get('SELECT p.id,p.subscription_id,p.amount,p.method,p.paid_at,(SELECT COALESCE(SUM(r.amount),0) FROM payment_refunds r WHERE r.payment_id=p.id) AS refunded FROM payments p WHERE p.member_id=? ORDER BY p.paid_at DESC,p.id DESC LIMIT 10');
            $data['paymentCount']=$count('payments');
        }
        if ($attendance) {
            $data['attendance']=$get('SELECT checked_in_at,result FROM attendance WHERE member_id=? ORDER BY checked_in_at DESC,id DESC LIMIT 10');
            $data['attendanceCount']=$count('attendance');
        }
        return $data;
    }
}
