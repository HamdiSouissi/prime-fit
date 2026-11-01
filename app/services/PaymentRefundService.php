<?php
declare(strict_types=1);
require_once __DIR__.'/SubscriptionPriceService.php';
/** Enregistre un remboursement déjà effectué ; n'effectue aucun transfert d'argent. */
class PaymentRefundService {
    public function __construct(private PDO $db,private int $userId,private string $ip) {}
    public function record(int $paymentId,string $amount,string $reason,string $token): int {
        $amount=str_replace(',','.',trim($amount));
        if (!mb_check_encoding($reason,'UTF-8') || preg_match('/[\x00-\x1f\x7f]/u',$reason)) throw new DomainException('Le motif contient des caractères invalides.');
        $reason=trim($reason);
        if ($paymentId<1 || !preg_match('/^\d{1,7}(\.\d{1,3})?$/D',$amount) || SubscriptionPriceService::millimes($amount)<=0 || !mb_check_encoding($reason,'UTF-8') || mb_strlen($reason)<3 || mb_strlen($reason)>500 || preg_match('/[\x00-\x1f\x7f]/u',$reason) || !preg_match('/^[a-f0-9]{64}$/D',$token)) throw new DomainException('Vérifiez le montant, le motif (3 à 500 caractères) et le formulaire.');
        // Le verrou d'abonnement est pris avant le paiement, comme pour un encaissement.
        $stmt=$this->db->prepare('SELECT subscription_id FROM payments WHERE id=?'); $stmt->execute([$paymentId]); $subscription=$stmt->fetchColumn();
        if (!$subscription) throw new DomainException('Paiement introuvable ou non rattaché à un abonnement.');
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare('SELECT id FROM subscriptions WHERE id=? FOR UPDATE'); $stmt->execute([$subscription]); if (!$stmt->fetch()) throw new DomainException('Abonnement introuvable.');
            $stmt=$this->db->prepare('SELECT amount,subscription_id FROM payments WHERE id=? FOR UPDATE'); $stmt->execute([$paymentId]); $payment=$stmt->fetch();
            if (!$payment || (int)$payment['subscription_id']!==(int)$subscription) throw new DomainException('Paiement introuvable ou modifié.');
            $stmt=$this->db->prepare('SELECT id FROM payment_refunds WHERE request_token=?'); $stmt->execute([$token]); if ($stmt->fetch()) throw new DomainException('Cette demande a déjà été enregistrée. Consultez le reçu avant de réessayer.');
            $stmt=$this->db->prepare('SELECT COALESCE(SUM(amount),0) FROM payment_refunds WHERE payment_id=?'); $stmt->execute([$paymentId]); $refunded=SubscriptionPriceService::millimes((string)$stmt->fetchColumn());
            if (SubscriptionPriceService::millimes($amount)>SubscriptionPriceService::millimes((string)$payment['amount'])-$refunded) throw new DomainException('Le montant dépasse le montant encore remboursable. Actualisez la page.');
            $this->db->prepare('INSERT INTO payment_refunds (payment_id,amount,reason,request_token,created_by) VALUES (?,?,?,?,?)')->execute([$paymentId,$amount,$reason,$token,$this->userId]); $id=(int)$this->db->lastInsertId();
            $this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$this->userId,'Remboursement #'.$id.' paiement #'.$paymentId,substr($this->ip,0,45)]);
            $this->db->commit(); return $id;
        } catch (Throwable $ex) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $ex; }
    }
}
