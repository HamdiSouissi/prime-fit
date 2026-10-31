<?php
declare(strict_types=1);
/** Les créations, renouvellements et gels sont sérialisés par membre puis abonnement. */
class SubscriptionService {
    private PDO $db; private int $userId; private string $ip; private DateTimeImmutable $today;
    public function __construct(PDO $db, int $userId, string $ip, ?DateTimeImmutable $today=null) {
        $this->db=$db; $this->userId=$userId; $this->ip=substr($ip,0,45); $this->today=($today ?? new DateTimeImmutable('today'))->setTime(0,0);
    }
    private function fail(string $message): void { throw new DomainException($message); }
    private function log(string $action): void { $this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$this->userId,$action,$this->ip]); }
    private function atomic(callable $fn) {
        $this->db->beginTransaction();
        try { $result=$fn(); $this->db->commit(); return $result; }
        catch (Throwable $ex) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $ex; }
    }
    private function lockMember(int $id, bool $requireActive=true): array {
        $stmt=$this->db->prepare('SELECT id,status FROM members WHERE id=? FOR UPDATE'); $stmt->execute([$id]); $m=$stmt->fetch();
        if (!$m || ($requireActive && $m['status']!=='active')) $this->fail('Membre introuvable ou inactif.'); return $m;
    }
    private function lockSubscription(int $id): array {
        $stmt=$this->db->prepare('SELECT member_id FROM subscriptions WHERE id=?'); $stmt->execute([$id]); $member=$stmt->fetchColumn();
        if (!$member) $this->fail('Abonnement introuvable.');
        $this->lockMember((int)$member,false);
        $stmt=$this->db->prepare('SELECT * FROM subscriptions WHERE id=? FOR UPDATE'); $stmt->execute([$id]); $s=$stmt->fetch();
        if (!$s || (int)$s['member_id']!==(int)$member) $this->fail('Abonnement introuvable.'); return $s;
    }
    private function ensureNoOverlap(int $member,string $start,string $end,?int $exclude=null): void {
        $stmt=$this->db->prepare("SELECT id FROM subscriptions WHERE member_id=? AND status='frozen' AND id<>? LIMIT 1"); $stmt->execute([$member,$exclude ?? 0]);
        if ($stmt->fetch()) $this->fail('Un abonnement est gelé pour ce membre. Réactivez-le ou annulez-le avant de continuer.');
        $stmt=$this->db->prepare("SELECT id FROM subscriptions WHERE member_id=? AND status='active' AND start_date<=? AND end_date>=? AND id<>? LIMIT 1"); $stmt->execute([$member,$end,$start,$exclude ?? 0]);
        if ($stmt->fetch()) $this->fail('Les dates chevauchent un autre abonnement actif de ce membre.');
    }
    private function insert(int $member,int $plan,string $start,?int $renewedFrom=null): array {
        $stmt=$this->db->prepare('SELECT duration_days FROM plans WHERE id=? AND is_active=1'); $stmt->execute([$plan]); $days=$stmt->fetchColumn();
        if (!$days || (int)$days<1 || (int)$days>3650) $this->fail('Formule inactive ou invalide.');
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$start);
        if (!$date || $date->format('Y-m-d')!==$start || $start<'1900-01-01' || $start>'2099-12-31') $this->fail('Date de début invalide.');
        $end=$date->modify('+'.((int)$days-1).' days')->format('Y-m-d');
        $this->ensureNoOverlap($member,$start,$end);
        $stmt=$this->db->prepare("INSERT INTO subscriptions (member_id,plan_id,start_date,end_date,status) VALUES (?,?,?,?,'active')"); $stmt->execute([$member,$plan,$start,$end]);
        $id=(int)$this->db->lastInsertId();
        $this->log(($renewedFrom ? 'Renouvellement abonnement #'.$renewedFrom.' vers #' : 'Abonnement créé #').$id);
        return ['id'=>$id,'start'=>$start,'end'=>$end];
    }
    public function create(int $member,int $plan,string $start): array {
        return $this->atomic(function() use ($member,$plan,$start) { $this->lockMember($member); return $this->insert($member,$plan,$start); });
    }
    public function renew(int $id): array {
        return $this->atomic(function() use ($id) {
            $s=$this->lockSubscription($id); $this->lockMember((int)$s['member_id']);
            if ($s['status']==='frozen') $this->fail('Réactivez ou annulez cet abonnement avant de le renouveler.');
            if ($s['status']==='cancelled') $this->fail('Un abonnement annulé ne peut pas être renouvelé. Créez une nouvelle adhésion.');
            $start=max($this->today->format('Y-m-d'),(new DateTimeImmutable($s['end_date']))->modify('+1 day')->format('Y-m-d'));
            return $this->insert((int)$s['member_id'],(int)$s['plan_id'],$start,$id);
        });
    }
    public function freeze(int $id): void {
        $this->atomic(function() use ($id) {
            $s=$this->lockSubscription($id); $today=$this->today->format('Y-m-d');
            if ($s['status']!=='active' || $s['start_date']>$today || $s['end_date']<$today) $this->fail('Seul un abonnement actif et en cours peut être gelé.');
            $this->db->prepare("INSERT INTO subscription_freezes (subscription_id,frozen_on,state,created_by) VALUES (?,?,'open',?)")->execute([$id,$today,$this->userId]);
            $this->db->prepare("UPDATE subscriptions SET status='frozen' WHERE id=?")->execute([$id]); $this->log('Abonnement gelé #'.$id);
        });
    }
    public function resume(int $id): array {
        return $this->atomic(function() use ($id) {
            $s=$this->lockSubscription($id); $this->lockMember((int)$s['member_id']);
            if ($s['status']!=='frozen') $this->fail('Cet abonnement n’est pas gelé.');
            $stmt=$this->db->prepare("SELECT id,frozen_on FROM subscription_freezes WHERE subscription_id=? AND state='open' ORDER BY id DESC LIMIT 1 FOR UPDATE"); $stmt->execute([$id]); $freeze=$stmt->fetch();
            if (!$freeze) $this->fail('Ce gel ancien n’a pas de date enregistrée. Contactez un administrateur pour régulariser son historique.');
            $start=new DateTimeImmutable($freeze['frozen_on']);
            if ($start>$this->today) $this->fail('La date de gel est dans le futur : vérifiez l’horloge du serveur.');
            $days=(int)$start->diff($this->today)->format('%a'); $end=(new DateTimeImmutable($s['end_date']))->modify('+'.$days.' days')->format('Y-m-d');
            $this->ensureNoOverlap((int)$s['member_id'],$s['start_date'],$end,$id);
            $this->db->prepare("UPDATE subscriptions SET status='active',end_date=? WHERE id=?")->execute([$end,$id]);
            $this->db->prepare("UPDATE subscription_freezes SET resumed_on=?,credited_days=?,state='resumed',closed_by=? WHERE id=?")->execute([$this->today->format('Y-m-d'),$days,$this->userId,$freeze['id']]);
            $this->log('Abonnement réactivé #'.$id.' : '.$days.' jour(s)'); return ['days'=>$days,'end'=>$end];
        });
    }
    public function cancel(int $id): void {
        $this->atomic(function() use ($id) {
            $s=$this->lockSubscription($id); if ($s['status']==='cancelled') $this->fail('Cet abonnement est déjà annulé.');
            $this->db->prepare("UPDATE subscriptions SET status='cancelled' WHERE id=?")->execute([$id]);
            $this->db->prepare("UPDATE subscription_freezes SET state='cancelled',resumed_on=?,credited_days=0,closed_by=? WHERE subscription_id=? AND state='open'")->execute([$this->today->format('Y-m-d'),$this->userId,$id]);
            $this->log('Abonnement annulé #'.$id);
        });
    }
}
