<?php
declare(strict_types=1);
require_once __DIR__.'/MarketingConsent.php';
/** Modification de la fiche du membre connecté, avec réauthentification et contrôle de version. */
final class MemberPortalProfile {
    public const GOVERNORATES=['Ariana','Béja','Ben Arous','Bizerte','Gabès','Gafsa','Jendouba','Kairouan','Kasserine','Kébili','Kef','Mahdia','Manouba','Médenine','Monastir','Nabeul','Sfax','Sidi Bouzid','Siliana','Sousse','Tataouine','Tozeur','Tunis','Zaghouan'];
    public function __construct(private PDO $db) {}
    public static function version(array $row): string {
        return hash('sha256',json_encode([(int)$row['id'],$row['first_name'],$row['last_name'],$row['phone'],$row['email'],$row['governorate'],$row['birth_date'],$row['status'],$row['account_email'],$row['password_hash']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }
    private function find(int $id,bool $lock=false): ?array {
        $stmt=$this->db->prepare('SELECT m.id,m.first_name,m.last_name,m.phone,m.email,m.governorate,m.birth_date,m.status,a.email AS account_email,a.password_hash FROM members m JOIN member_portal_accounts a ON a.member_id=m.id WHERE m.id=?'.($lock ? ' FOR UPDATE' : ''));
        $stmt->execute([$id]); return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    private static function validAccount(?array $row,string $fingerprint): bool {
        return $row && $row['status']==='active' && is_string($row['password_hash']) && trim((string)$row['email'])===$row['account_email'] && hash_equals(hash('sha256',$row['password_hash']),$fingerprint);
    }
    /** Le hash est conservé côté serveur seulement ; seul le jeton de version est affiché. */
    public function get(array $session): array {
        $id=$session['member_id'] ?? null; $fp=$session['fingerprint'] ?? null;
        if (!is_int($id) || $id<1 || !is_string($fp)) throw new DomainException('Session membre indisponible.');
        $row=$this->find($id);
        if (!self::validAccount($row,$fp)) throw new DomainException('Session membre indisponible.');
        $row['version']=self::version($row); unset($row['password_hash'],$row['account_email'],$row['status']); return $row;
    }
    public function update(array $session,array $input,string $ip,?DateTimeImmutable $now=null): void {
        $id=$session['member_id'] ?? null; $fp=$session['fingerprint'] ?? null;
        if (!is_int($id) || $id<1 || !is_string($fp)) throw new DomainException('Session membre indisponible.');
        foreach (['first_name','last_name','phone','governorate','birth_date','current_password','version'] as $key) if (!is_string($input[$key] ?? null)) throw new DomainException('Vérifiez les champs du formulaire.');
        $first=trim($input['first_name']); $last=trim($input['last_name']); $phone=trim($input['phone']); $governorate=trim($input['governorate']); $birth=trim($input['birth_date']); $password=$input['current_password'];
        foreach ([$first,$last] as $name) if ($name==='' || !mb_check_encoding($name,'UTF-8') || mb_strlen($name,'UTF-8')>100 || preg_match('/[\x00-\x1F\x7F]/u',$name)) throw new DomainException('Indiquez un prénom et un nom valides (100 caractères maximum).');
        if ($phone!=='' && (strlen($phone)>30 || !preg_match('/\A\+?[0-9 ()-]{6,30}\z/D',$phone) || strlen(preg_replace('/\D/','',$phone))<6)) throw new DomainException('Indiquez un numéro de téléphone valide.');
        if ($password==='' || strlen($password)>1024) throw new DomainException('Mot de passe actuel incorrect.');
        $now=$now ?? new DateTimeImmutable();
        if ($birth!=='') { $date=DateTimeImmutable::createFromFormat('!Y-m-d',$birth); if (!$date || $date->format('Y-m-d')!==$birth || $birth>$now->format('Y-m-d')) throw new DomainException('Indiquez une date de naissance valide.'); }
        $this->db->beginTransaction();
        try {
            $row=$this->find($id,true);
            if (!self::validAccount($row,$fp) || !password_verify($password,$row['password_hash'])) throw new DomainException('Mot de passe actuel incorrect ou compte indisponible.');
            if (!hash_equals(self::version($row),$input['version'])) throw new DomainException('La fiche a été modifiée entre-temps. Rechargez la page et vérifiez les informations avant de réessayer.');
            // Une valeur historique inconnue peut être conservée mais pas nouvellement saisie.
            if ($governorate!=='' && !in_array($governorate,self::GOVERNORATES,true) && $governorate!==($row['governorate'] ?? '')) throw new DomainException('Sélectionnez un gouvernorat valide.');
            $newPhone=$phone!=='' ? $phone : null;
            (new MarketingConsent($this->db,null,$ip))->revokeForPhoneChange($id,$row['phone'],$newPhone);
            $stmt=$this->db->prepare('UPDATE members SET first_name=?,last_name=?,phone=?,governorate=?,birth_date=? WHERE id=?');
            $stmt->execute([$first,$last,$newPhone,$governorate!=='' ? $governorate : null,$birth!=='' ? $birth : null,$id]);
            $this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (NULL,?,?)')->execute(['Espace membre : fiche modifiée membre #'.$id,substr($ip,0,45)]);
            $this->db->commit();
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
}
