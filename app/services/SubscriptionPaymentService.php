<?php
declare(strict_types=1);
require_once __DIR__.'/SubscriptionPriceService.php';
/** Encaissement sérialisé sur l'abonnement, au tarif fixé. */
class SubscriptionPaymentService {
    private string $failureStage='préparation';
    public function __construct(private PDO $db,private int $userId,private string $ip) {}
    /** Étape technique sans données personnelles, pour diagnostiquer un échec SQL. */
    public function failureStage(): string { return $this->failureStage; }
    public function pay(int $subscription,string $amount,string $method): int {
        $amount=str_replace(',','.',trim($amount));
        if ($subscription<1 || !preg_match('/^\d{1,7}(\.\d{1,3})?$/D',$amount) || SubscriptionPriceService::millimes($amount)<=0 || !in_array($method,['espèces','chèque','virement','D17','Flouci','carte bancaire'],true)) throw new DomainException('Sélectionnez un abonnement et indiquez un montant positif et un moyen de paiement valides.');
        $this->failureStage='ouverture de la transaction';
        try {
            $this->db->beginTransaction();
            $this->failureStage='lecture de l’abonnement';
            $stmt=$this->db->prepare('SELECT id,member_id,agreed_price FROM subscriptions WHERE id=? FOR UPDATE'); $stmt->execute([$subscription]); $sub=$stmt->fetch();
            if (!$sub) throw new DomainException('Abonnement introuvable.');
            if ($sub['agreed_price']===null) throw new DomainException('Prix historique non vérifié : un Admin ou Gérant doit le fixer avant tout encaissement.');
            $this->failureStage='lecture des paiements';
            $stmt=$this->db->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE subscription_id=?'); $stmt->execute([$subscription]);
            $paid=SubscriptionPriceService::millimes((string)$stmt->fetchColumn());
            $this->failureStage='lecture des remboursements';
            $stmt=$this->db->prepare('SELECT COALESCE(SUM(r.amount),0) FROM payment_refunds r JOIN payments p ON p.id=r.payment_id WHERE p.subscription_id=?'); $stmt->execute([$subscription]);
            $remaining=SubscriptionPriceService::millimes((string)$sub['agreed_price'])-$paid+SubscriptionPriceService::millimes((string)$stmt->fetchColumn());
            if (SubscriptionPriceService::millimes($amount)>$remaining) throw new DomainException('Le montant dépasse le reste à payer de cet abonnement.');
            $this->failureStage='enregistrement du paiement';
            $this->db->prepare('INSERT INTO payments (member_id,subscription_id,amount,method) VALUES (?,?,?,?)')->execute([$sub['member_id'],$subscription,$amount,$method]); $id=(int)$this->db->lastInsertId();
            $this->failureStage='journal d’activité';
            $this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$this->userId,'Paiement enregistré #'.$id,substr($this->ip,0,45)]);
            $this->failureStage='validation de la transaction';
            $this->db->commit(); return $id;
        } catch (Throwable $ex) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $ex; }
    }
}
