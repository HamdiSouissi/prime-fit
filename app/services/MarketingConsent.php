<?php
declare(strict_types=1);
/** Autorisation explicite pour les messages marketing WhatsApp uniquement. */
class MarketingConsent {
    public function __construct(private PDO $db,private int $actor,private string $ip) {}
    public static function phone(?string $phone): ?string {
        $number=preg_replace('/[\s().-]+/u','',trim($phone ?? ''));
        if (is_string($number) && preg_match('/^\+?216([0-9]{8})$/D',$number,$m)) return '216'.$m[1];
        if (is_string($number) && preg_match('/^[0-9]{8}$/D',$number)) return '216'.$number;
        return null;
    }
    public function latest(int $member): ?array {
        $stmt=$this->db->prepare('SELECT * FROM marketing_consent_events WHERE member_id=? ORDER BY id DESC LIMIT 1'); $stmt->execute([$member]); return $stmt->fetch() ?: null;
    }
    private function log(string $message): void { $this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$this->actor,$message,substr($this->ip,0,45)]); }
    /** Toute modification du téléphone enregistrée dans l'application invalide une autorisation existante. */
    public function revokeForPhoneChange(int $member,?string $old,?string $new): void {
        if (!$this->db->inTransaction()) throw new LogicException('Une transaction est nécessaire.');
        if (($old ?? '')===($new ?? '')) return;
        $this->db->prepare("INSERT INTO marketing_consent_events (member_id,decision,phone_value,evidence,created_by) VALUES (?,'invalidated',?,'Numéro modifié',?)")->execute([$member,$old ?? '',$this->actor]);
        $this->log('Préférence WhatsApp invalidée après changement de numéro membre #'.$member);
    }
    public function record(int $member,int $version,string $decision,string $evidence,string $expectedPhone): void {
        if (!in_array($decision,['accepted','revoked'],true) || !mb_check_encoding($evidence,'UTF-8') || preg_match('/[\x00-\x1f\x7f]/u',$evidence)) throw new DomainException('Vérifiez la décision et sa provenance.');
        $evidence=trim($evidence);
        if (mb_strlen($evidence)>250 || ($decision==='accepted' && mb_strlen($evidence)<3)) throw new DomainException('Pour une autorisation, précisez sa provenance (3 à 250 caractères).');
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare('SELECT id,phone FROM members WHERE id=? FOR UPDATE'); $stmt->execute([$member]); $row=$stmt->fetch(); if (!$row) throw new DomainException('Membre introuvable.');
            $last=$this->latest($member);
            if ((int)($last['id'] ?? 0)!==$version || (string)($row['phone'] ?? '')!==$expectedPhone) throw new DomainException('Les préférences ou le numéro ont changé. Vérifiez les informations actualisées avant de réessayer.');
            if ($decision==='accepted' && self::phone($row['phone'])===null) throw new DomainException('Un numéro tunisien valide est nécessaire avant de recueillir une autorisation WhatsApp.');
            $this->db->prepare('INSERT INTO marketing_consent_events (member_id,decision,phone_value,evidence,created_by) VALUES (?,?,?,?,?)')->execute([$member,$decision,$row['phone'] ?? '',$evidence,$this->actor]);
            $this->log('Préférence WhatsApp '.($decision==='accepted' ? 'autorisée' : 'retirée').' membre #'.$member); $this->db->commit();
        } catch (Throwable $ex) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $ex; }
    }
}
