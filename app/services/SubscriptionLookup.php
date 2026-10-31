<?php
declare(strict_types=1);
/** Recherche paginée et historique des gels, sans modifier les adhésions. */
class SubscriptionLookup {
    public function __construct(private PDO $db) {}
    public function search(string $query,string $filter,int $page,string $today): array {
        $query=mb_substr(trim($query),0,120);
        if (!in_array($filter,['','current','upcoming','expired','frozen','cancelled'],true)) $filter='';
        $where=[]; $params=[];
        if ($query!=='') { $where[]="(m.first_name LIKE ? OR m.last_name LIKE ? OR CONCAT(m.first_name,' ',m.last_name) LIKE ? OR m.card_number LIKE ? OR p.name LIKE ?)"; $params=array_fill(0,5,'%'.$query.'%'); }
        if ($filter==='current') { $where[]="s.status='active' AND s.start_date<=? AND s.end_date>=?"; $params[]=$today; $params[]=$today; }
        elseif ($filter==='upcoming') { $where[]="s.status='active' AND s.start_date>?"; $params[]=$today; }
        elseif ($filter==='expired') { $where[]="s.status='active' AND s.end_date<?"; $params[]=$today; }
        elseif ($filter==='frozen' || $filter==='cancelled') { $where[]='s.status=?'; $params[]=$filter; }
        $join=' FROM subscriptions s JOIN members m ON m.id=s.member_id JOIN plans p ON p.id=s.plan_id';
        $sqlWhere=$where ? ' WHERE '.implode(' AND ',$where) : '';
        $stmt=$this->db->prepare('SELECT COUNT(*)'.$join.$sqlWhere); $stmt->execute($params); $total=(int)$stmt->fetchColumn();
        $page=min(max(1,$page),max(1,(int)ceil($total/10))); $offset=($page-1)*10;
        $stmt=$this->db->prepare('SELECT s.*,s.agreed_price,m.first_name,m.last_name,p.name AS plan'.$join.$sqlWhere.' ORDER BY s.created_at DESC,s.id DESC LIMIT 10 OFFSET '.$offset); $stmt->execute($params);
        return ['query'=>$query,'filter'=>$filter,'total'=>$total,'page'=>$page,'subscriptions'=>$stmt->fetchAll()];
    }
    public function detail(int $id,int $page): ?array {
        $stmt=$this->db->prepare('SELECT s.id,s.member_id,s.start_date,s.end_date,s.status,s.agreed_price,m.first_name,m.last_name,m.card_number,p.name AS plan FROM subscriptions s JOIN members m ON m.id=s.member_id JOIN plans p ON p.id=s.plan_id WHERE s.id=?'); $stmt->execute([$id]); $subscription=$stmt->fetch();
        if (!$subscription) return null;
        $stmt=$this->db->prepare("SELECT COUNT(*),COALESCE(SUM(CASE WHEN state='open' THEN 1 ELSE 0 END),0) FROM subscription_freezes WHERE subscription_id=?"); $stmt->execute([$id]); $counts=$stmt->fetch(PDO::FETCH_NUM); $total=(int)$counts[0];
        $page=min(max(1,$page),max(1,(int)ceil($total/10))); $offset=($page-1)*10;
        $stmt=$this->db->prepare('SELECT f.frozen_on,f.resumed_on,f.credited_days,f.state,u.name AS created_name,c.name AS closed_name FROM subscription_freezes f LEFT JOIN users u ON u.id=f.created_by LEFT JOIN users c ON c.id=f.closed_by WHERE f.subscription_id=? ORDER BY f.frozen_on DESC,f.id DESC LIMIT 10 OFFSET '.$offset); $stmt->execute([$id]);
        return ['subscription'=>$subscription,'freezes'=>$stmt->fetchAll(),'total'=>$total,'page'=>$page,'openCount'=>(int)$counts[1]];
    }
}
