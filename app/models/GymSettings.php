<?php
declare(strict_types=1);
require_once __DIR__.'/../services/GymBrand.php';
/** Informations d'une seule salle. TVA et timbre ne sont pas appliqués aux paiements. */
class GymSettings {
    private PDO $db;
    public function __construct(PDO $db) { $this->db=$db; }
    public function get(): array {
        $row=$this->db->query('SELECT * FROM gym_settings WHERE id=1')->fetch();
        if (!$row) throw new RuntimeException('Paramètres de salle absents.');
        return $row;
    }
    public function save(array $input,int $userId,string $ip): void {
        foreach (['gym_name','phone','email','address','tax_id','opening_hours','vat_enabled','vat_rate','fiscal_stamp'] as $key) {
            if (isset($input[$key]) && !is_string($input[$key])) throw new DomainException('Format de champ invalide.');
        }
        $name=GymBrand::clean($input['gym_name'] ?? null);
        if ($name===null) throw new DomainException('Indiquez un nom de salle valide, de 1 à 120 caractères, sans saut de ligne.');
        $phone=trim((string)($input['phone'] ?? '')); $email=trim((string)($input['email'] ?? ''));
        $address=trim((string)($input['address'] ?? '')); $taxId=trim((string)($input['tax_id'] ?? '')); $hours=trim((string)($input['opening_hours'] ?? ''));
        $vat=str_replace(',','.',trim((string)($input['vat_rate'] ?? ''))); $stamp=str_replace(',','.',trim((string)($input['fiscal_stamp'] ?? '')));
        $enabled=$input['vat_enabled'] ?? '0';
        if (mb_strlen($phone)>30 || mb_strlen($email)>190 || mb_strlen($address)>255 || mb_strlen($taxId)>80 || mb_strlen($hours)>1000 || preg_match('/[\r\n\x00-\x1f]/u',$phone.$email.$address.$taxId) || ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) || !in_array($enabled,['0','1'],true) || !preg_match('/^\d{1,3}(\.\d{1,2})?$/D',$vat) || (float)$vat>100 || !preg_match('/^\d{1,7}(\.\d{1,3})?$/D',$stamp)) {
            throw new DomainException('Vérifiez les coordonnées, les horaires et les montants. Le taux de TVA doit être compris entre 0 et 100 %.');
        }
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare('SELECT id FROM gym_settings WHERE id=1 FOR UPDATE'); $stmt->execute();
            if (!$stmt->fetch()) throw new DomainException('Paramètres absents : importez la migration de la salle.');
            $stmt=$this->db->prepare('UPDATE gym_settings SET gym_name=?,phone=?,email=?,address=?,tax_id=?,opening_hours=?,vat_enabled=?,vat_rate=?,fiscal_stamp=?,updated_by=? WHERE id=1');
            $stmt->execute([$name,$phone,$email,$address,$taxId,$hours,(int)$enabled,$vat,$stamp,$userId]);
            $this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$userId,'Paramètres de la salle mis à jour',substr($ip,0,45)]);
            $this->db->commit();
        } catch (Throwable $ex) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $ex; }
    }
}
