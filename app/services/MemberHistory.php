<?php
declare(strict_types=1);
/** Lecture paginée des rubriques de la fiche membre selon les droits existants. */
class MemberHistory {
    private const LIMIT=20;
    public function __construct(private PDO $db) {}
    public static function allowed(string $role,string $type): bool {
        $roles=[
            'subscriptions'=>['Admin','Gérant','Réceptionniste','Coach','Comptable'],
            'payments'=>['Admin','Gérant','Réceptionniste','Comptable'],
            'attendance'=>['Admin','Gérant','Réceptionniste','Coach'],
        ];
        return isset($roles[$type]) && in_array($role,$roles[$type],true);
    }
    public function get(int $memberId,string $role,string $type,int $page): ?array {
        // Le refus précède toute lecture, y compris la recherche du membre.
        if (!self::allowed($role,$type)) throw new DomainException('Accès interdit.');
        $stmt=$this->db->prepare('SELECT id,first_name,last_name,card_number FROM members WHERE id=?'); $stmt->execute([$memberId]); $member=$stmt->fetch();
        if (!$member) return null;
        $queries=[
            'subscriptions'=>['subscriptions','SELECT s.id,s.start_date,s.end_date,s.status,p.name AS plan FROM subscriptions s JOIN plans p ON p.id=s.plan_id WHERE s.member_id=? ORDER BY s.start_date DESC,s.id DESC'],
            'payments'=>['payments','SELECT p.id,p.subscription_id,p.amount,p.method,p.paid_at,(SELECT COALESCE(SUM(r.amount),0) FROM payment_refunds r WHERE r.payment_id=p.id) AS refunded FROM payments p WHERE p.member_id=? ORDER BY p.paid_at DESC,p.id DESC'],
            'attendance'=>['attendance','SELECT id,checked_in_at,result FROM attendance WHERE member_id=? ORDER BY checked_in_at DESC,id DESC'],
        ];
        [$table,$sql]=$queries[$type];
        $stmt=$this->db->prepare('SELECT COUNT(*) FROM '.$table.' WHERE member_id=?'); $stmt->execute([$memberId]); $total=(int)$stmt->fetchColumn();
        $pages=max(1,(int)ceil($total/self::LIMIT)); $page=min(max(1,$page),$pages); $offset=($page-1)*self::LIMIT;
        $stmt=$this->db->prepare($sql.' LIMIT '.self::LIMIT.' OFFSET '.$offset); $stmt->execute([$memberId]);
        return ['member'=>$member,'type'=>$type,'rows'=>$stmt->fetchAll(),'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
}
