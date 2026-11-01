<?php
declare(strict_types=1);
require_once __DIR__.'/PlanService.php';
require_once __DIR__.'/SubscriptionPricing.php';
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
    // Cette lecture précède la transaction : la première lecture cohérente du contrôle
    // de chevauchement ne doit pas créer un instantané avant l'obtention du verrou membre.
    private function subscriptionMember(int $id): int {
        $stmt=$this->db->prepare('SELECT member_id FROM subscriptions WHERE id=?'); $stmt->execute([$id]); $member=$stmt->fetchColumn();
        if (!$member) $this->fail('Abonnement introuvable.'); return (int)$member;
    }
    private function lockSubscription(int $id,int $member): array {
        $this->lockMember($member,false);
        $stmt=$this->db->prepare('SELECT * FROM subscriptions WHERE id=? FOR UPDATE'); $stmt->execute([$id]); $s=$stmt->fetch();
        if (!$s || (int)$s['member_id']!==$member) $this->fail('Abonnement introuvable ou modifié.'); return $s;
    }
    public static function version(array $s): string {
        return hash('sha256',json_encode([(int)$s['id'],(int)$s['member_id'],(int)$s['plan_id'],$s['start_date'],$s['end_date'],$s['status'],$s['agreed_price']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }
    private function ensureNoOverlap(int $member,string $start,string $end,?int $exclude=null): void {
        $stmt=$this->db->prepare("SELECT id FROM subscriptions WHERE member_id=? AND status='frozen' AND id<>? LIMIT 1"); $stmt->execute([$member,$exclude ?? 0]);
        if ($stmt->fetch()) $this->fail('Un abonnement est gelé pour ce membre. Réactivez-le ou annulez-le avant de continuer.');
        $stmt=$this->db->prepare("SELECT id FROM subscriptions WHERE member_id=? AND status='active' AND start_date<=? AND end_date>=? AND id<>? LIMIT 1"); $stmt->execute([$member,$end,$start,$exclude ?? 0]);
        if ($stmt->fetch()) $this->fail('Les dates chevauchent un autre abonnement actif de ce membre.');
    }
    private function insert(int $member,int $plan,string $start,?int $renewedFrom=null,?string $expectedPlan=null,?int $promotionId=null,?string $promotionVersion=null,?string $expectedPrice=null): array {
        // Ordre de verrouillage partagé : membre, abonnement éventuel, formule, promotion.
        $quote=(new SubscriptionPricing($this->db))->quote($plan,$expectedPlan,$promotionId,$promotionVersion,$this->today->format('Y-m-d'),true);
        $planData=$quote['plan']; $days=(int)$planData['duration_days'];
        if ($expectedPrice!==null && !hash_equals($quote['price'],$expectedPrice)) $this->fail('Le prix a changé. Vérifiez le récapitulatif avant de continuer.');
        $date=DateTimeImmutable::createFromFormat('!Y-m-d',$start);
        if (!$date || $date->format('Y-m-d')!==$start || $start<'1900-01-01' || $start>'2099-12-31') $this->fail('Date de début invalide.');
        $end=$date->modify('+'.((int)$days-1).' days')->format('Y-m-d');
        if ($end>'2099-12-31') $this->fail('La date de fin dépasse la limite autorisée.');
        $this->ensureNoOverlap($member,$start,$end);
        $stmt=$this->db->prepare("INSERT INTO subscriptions (member_id,plan_id,start_date,end_date,agreed_price,status) VALUES (?,?,?,?,?,'active')"); $stmt->execute([$member,$plan,$start,$end,$quote['price']]);
        $id=(int)$this->db->lastInsertId();
        if ($quote['promotion']) {
            $promo=$quote['promotion'];
            $this->db->prepare('INSERT INTO subscription_discounts (subscription_id,promotion_id,code,label,base_price,discount_percent,discount_amount) VALUES (?,?,?,?,?,?,?)')->execute([$id,$promo['id'],$promo['code'],$promo['label'],$quote['base'],$promo['discount_percent'],$quote['discount']]);
        }
        $this->log(($renewedFrom ? 'Renouvellement abonnement #'.$renewedFrom.' vers #' : 'Abonnement créé #').$id);
        return ['id'=>$id,'start'=>$start,'end'=>$end,'price'=>$quote['price']];
    }
    public function create(int $member,int $plan,string $start,?string $expectedPlan=null,?int $promotionId=null,?string $promotionVersion=null,?string $expectedPrice=null): array {
        return $this->atomic(function() use ($member,$plan,$start,$expectedPlan,$promotionId,$promotionVersion,$expectedPrice) { $this->lockMember($member); return $this->insert($member,$plan,$start,null,$expectedPlan,$promotionId,$promotionVersion,$expectedPrice); });
    }
    public function renew(int $id,?string $expectedPlan=null,?int $selectedPlan=null,?string $expectedSubscription=null,?string $expectedStart=null,?int $promotionId=null,?string $promotionVersion=null,?string $expectedPrice=null): array {
        $member=$this->subscriptionMember($id);
        return $this->atomic(function() use ($id,$member,$expectedPlan,$selectedPlan,$expectedSubscription,$expectedStart,$promotionId,$promotionVersion,$expectedPrice) {
            $s=$this->lockSubscription($id,$member); $this->lockMember($member);
            if ($expectedSubscription!==null && !hash_equals(self::version($s),$expectedSubscription)) $this->fail('L’abonnement a changé depuis l’ouverture de la page. Vérifiez ses informations avant de continuer.');
            if ($s['status']==='frozen') $this->fail('Réactivez ou annulez cet abonnement avant de le renouveler.');
            if ($s['status']!=='active') $this->fail('Un abonnement annulé ne peut pas être renouvelé. Créez une nouvelle adhésion.');
            if ($selectedPlan!==null && $selectedPlan<1) $this->fail('Formule invalide.');
            $start=max($this->today->format('Y-m-d'),(new DateTimeImmutable($s['end_date']))->modify('+1 day')->format('Y-m-d'));
            if ($expectedStart!==null && $expectedStart!==$start) $this->fail('La date de début a changé. Vérifiez le renouvellement avant de continuer.');
            return $this->insert($member,$selectedPlan ?? (int)$s['plan_id'],$start,$id,$expectedPlan,$promotionId,$promotionVersion,$expectedPrice);
        });
    }
    public function freeze(int $id): void {
        $member=$this->subscriptionMember($id);
        $this->atomic(function() use ($id,$member) {
            $s=$this->lockSubscription($id,$member); $today=$this->today->format('Y-m-d');
            if ($s['status']!=='active' || $s['start_date']>$today || $s['end_date']<$today) $this->fail('Seul un abonnement actif et en cours peut être gelé.');
            $this->db->prepare("INSERT INTO subscription_freezes (subscription_id,frozen_on,state,created_by) VALUES (?,?,'open',?)")->execute([$id,$today,$this->userId]);
            $this->db->prepare("UPDATE subscriptions SET status='frozen' WHERE id=?")->execute([$id]); $this->log('Abonnement gelé #'.$id);
        });
    }
    public function resume(int $id): array {
        $member=$this->subscriptionMember($id);
        return $this->atomic(function() use ($id,$member) {
            $s=$this->lockSubscription($id,$member); $this->lockMember((int)$s['member_id']);
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
        $member=$this->subscriptionMember($id);
        $this->atomic(function() use ($id,$member) {
            $s=$this->lockSubscription($id,$member); if ($s['status']==='cancelled') $this->fail('Cet abonnement est déjà annulé.');
            $this->db->prepare("UPDATE subscriptions SET status='cancelled' WHERE id=?")->execute([$id]);
            $this->db->prepare("UPDATE subscription_freezes SET state='cancelled',resumed_on=?,credited_days=0,closed_by=? WHERE subscription_id=? AND state='open'")->execute([$this->today->format('Y-m-d'),$this->userId,$id]);
            $this->log('Abonnement annulé #'.$id);
        });
    }
}
