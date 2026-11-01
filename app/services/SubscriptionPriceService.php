<?php
declare(strict_types=1);
/** Régularisation ponctuelle des prix historiques inconnus. */
class SubscriptionPriceService {
    public function __construct(private PDO $db,private int $userId,private string $ip) {}
    public static function millimes(string $value): int { $parts=explode('.',$value,2); return (int)$parts[0]*1000+(int)str_pad($parts[1] ?? '',3,'0'); }
    public function verify(int $id,string $price): void {
        $price=str_replace(',','.',trim($price));
        if (!preg_match('/^\d{1,7}(\.\d{1,3})?$/D',$price)) throw new DomainException('Indiquez un prix compris entre 0 et 9 999 999,999 DT, avec trois décimales au maximum.');
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare('SELECT agreed_price FROM subscriptions WHERE id=? FOR UPDATE'); $stmt->execute([$id]); $s=$stmt->fetch();
            if (!$s) throw new DomainException('Abonnement introuvable.');
            if ($s['agreed_price']!==null) throw new DomainException('Le prix de cet abonnement a déjà été fixé et ne peut pas être modifié ici.');
            $stmt=$this->db->prepare('SELECT COALESCE(SUM(amount),0) FROM payments WHERE subscription_id=?'); $stmt->execute([$id]); $paid=(string)$stmt->fetchColumn();
            $stmt=$this->db->prepare('SELECT COALESCE(SUM(r.amount),0) FROM payment_refunds r JOIN payments p ON p.id=r.payment_id WHERE p.subscription_id=?'); $stmt->execute([$id]); $netPaid=self::millimes($paid)-self::millimes((string)$stmt->fetchColumn());
            if (self::millimes($price)<$netPaid) throw new DomainException('Le prix est inférieur aux paiements déjà enregistrés. Vérifiez les pièces et régularisez la situation avant de continuer.');
            $this->db->prepare('UPDATE subscriptions SET agreed_price=? WHERE id=?')->execute([$price,$id]);
            $this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$this->userId,'Prix historique fixé abonnement #'.$id.' : '.$price.' DT',substr($this->ip,0,45)]);
            $this->db->commit();
        } catch (Throwable $ex) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $ex; }
    }
}
