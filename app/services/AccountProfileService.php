<?php
declare(strict_types=1);
/** Coordonnées du compte : contrôle du mot de passe, verrou et audit atomiques. */
final class AccountProfileService {
    public function __construct(private PDO $db) {}
    public static function version(array $user): string {
        return hash('sha256',json_encode([(int)$user['id'],$user['name'],$user['email'],$user['phone'] ?? '',$user['password_hash'],(int)$user['is_active']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }
    public function get(int $id): array {
        $stmt=$this->db->prepare('SELECT id,name,email,phone,password_hash,is_active FROM users WHERE id=?');
        $stmt->execute([$id]); $row=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !$row['is_active']) throw new DomainException('Compte indisponible.');
        return $row;
    }
    public function update(int $id,array $input,string $ip): array {
        foreach (['name','email','phone','current_password','version'] as $key) if (!is_string($input[$key] ?? null)) throw new DomainException('Vérifiez les champs du formulaire.');
        $name=trim($input['name']); $email=trim($input['email']); $phone=trim($input['phone']); $password=$input['current_password'];
        if ($name==='' || !mb_check_encoding($name,'UTF-8') || mb_strlen($name,'UTF-8')>120 || preg_match('/[\x00-\x1F\x7F]/',$name)) throw new DomainException('Indiquez un nom valide de 120 caractères maximum.');
        if (strlen($email)>190 || preg_match('/[\r\n]/',$email) || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new DomainException('Indiquez une adresse e-mail valide.');
        if ($phone!=='' && (strlen($phone)>30 || !preg_match('/^\+?[0-9 ()-]{6,30}$/D',$phone))) throw new DomainException('Indiquez un numéro de téléphone valide.');
        if ($password==='' || strlen($password)>1024) throw new DomainException('Mot de passe actuel incorrect.');
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare('SELECT id,name,email,phone,password_hash,is_active FROM users WHERE id=? FOR UPDATE');
            $stmt->execute([$id]); $user=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || !$user['is_active'] || !password_verify($password,$user['password_hash'])) throw new DomainException('Mot de passe actuel incorrect ou compte inactif.');
            if (!hash_equals(self::version($user),$input['version'])) throw new DomainException('Le compte a été modifié entre-temps. Rechargez la page avant de réessayer.');
            try { $this->db->prepare('UPDATE users SET name=?,email=?,phone=? WHERE id=?')->execute([$name,$email,$phone!=='' ? $phone : null,$id]); }
            catch (PDOException $ex) {
                if (($ex->errorInfo[0] ?? '')==='23000' && in_array((int)($ex->errorInfo[1] ?? 0),[19,1062],true)) throw new DomainException('Cette adresse e-mail est déjà utilisée.');
                throw $ex;
            }
            $this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$id,'Coordonnées du compte modifiées',substr($ip,0,45)]);
            $this->db->commit(); return ['name'=>$name,'email'=>$email,'phone'=>$phone];
        } catch (Throwable $ex) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $ex; }
    }
}
