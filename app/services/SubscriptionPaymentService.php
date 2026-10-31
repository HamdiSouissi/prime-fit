<?php
declare(strict_types=1);
require_once __DIR__.'/SubscriptionPriceService.php';
/** Encaissement sérialisé sur l'abonnement, au tarif fixé. */
class SubscriptionPaymentService {
    public function __construct(private PDO $db,private int $userId,private string $ip) {}
    public function pay(int $subscription,string $amount,string $method): int {
        $amount=str_replace(',','.',trim($amount));
        if ($subscription<1 || !preg_match('/^\d{1,7}(\.\d{1,3})?$/D',$amount) || SubscriptionPriceService::millimes($amount)<=0 || !in_array($method,['espèces','chèque','virement','D17','Flouci','carte bancaire'],true)) throw new DomainException('Sélectionnez un abonnement et indiquez un montant positif et un moyen de paiement valides.');
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare('SELECT id,member_id,agreed_price FROM subscriptions WHERE id=? FOR UPDATE'); $stmt->execute([$subscription]); $sub=$stmt->fetch();
            if (!$sub) throw new DomainException('Abonnement introuvable.');
            if ($sub['agreed_price']===null) throw new DomainException('Prix historique non vérifié : un Admin ou Gérant doit le fixer avant tout encaissement.');
            $stmt=$this->db->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE subscription_id=?'); $stmt->execute([$subscription]);
            $remaining=SubscriptionPriceService::millimes((string)$sub['agreed_price'])-SubscriptionPriceService::millimes((string)$stmt->fetchColumn());
            if (SubscriptionPriceService::millimes($amount)>$remaining) throw new DomainException('Le montant dépasse le reste à payer de cet abonnement.');
            $this->db->prepare('INSERT INTO payments (member_id,subscription_id,amount,method) VALUES (?,?,?,?)')->execute([$sub['member_id'],$subscription,$amount,$method]); $id=(int)$this->db->lastInsertId();
            $this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$this->userId,'Paiement enregistré #'.$id,substr($this->ip,0,45)]);
            $this->db->commit(); return $id;
        } catch (Throwable $ex) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $ex; }
    }
}
