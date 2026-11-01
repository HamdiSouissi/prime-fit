<?php
declare(strict_types=1);
/** Administration des comptes ; les comptes et leur journal sont modifiés atomiquement. */
class UserAdminService {
    private const ROLES=['Admin','Gérant','Réceptionniste','Comptable','Coach'];
    public function __construct(private PDO $db,private int $actorId,private string $ip) {}
    public static function version(array $user): string {
        return hash('sha256',json_encode([(int)$user['id'],$user['name'],$user['email'],$user['role'],(int)$user['is_active'],$user['password_hash']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }
    private function fields(array $input,bool $creating): array {
        foreach (['name','email','role'] as $key) if (!isset($input[$key]) || !is_string($input[$key])) throw new DomainException('Vérifiez le nom, l’adresse e-mail et le rôle.');
        $name=trim($input['name']); $email=trim($input['email']); $role=$input['role'];
        if ($name==='' || mb_strlen($name)>120 || mb_strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL) || !in_array($role,self::ROLES,true) || ($creating && $role==='Coach')) throw new DomainException('Vérifiez le nom, l’adresse e-mail et le rôle. Les coachs sont créés depuis le module Coachs.');
        return [$name,$email,$role];
    }
    private function password(string $password,string $confirmation): string {
        if (strlen($password)<12 || strlen($password)>72 || str_contains($password,"\0") || !hash_equals($password,$confirmation)) throw new DomainException('Le nouveau mot de passe doit comporter 12 à 72 octets, sans caractère nul, et correspondre à sa confirmation.');
        $hash=password_hash($password,PASSWORD_DEFAULT); if ($hash===false) throw new RuntimeException('Impossible de créer le mot de passe.'); return $hash;
    }
    private function atomic(string $adminPassword,callable $action) {
        if ($adminPassword==='' || strlen($adminPassword)>1024) throw new DomainException('Mot de passe administrateur incorrect.');
        $this->db->beginTransaction();
        try {
            // Ordre commun : verrouiller tous les administrateurs actifs par id, puis le compte cible.
            $stmt=$this->db->prepare("SELECT id,password_hash FROM users WHERE role='Admin' AND is_active=1 ORDER BY id FOR UPDATE"); $stmt->execute(); $admins=$stmt->fetchAll();
            $actor=null; foreach ($admins as $row) if ((int)$row['id']===$this->actorId) $actor=$row;
            if (!$actor || !password_verify($adminPassword,$actor['password_hash'])) throw new DomainException('Mot de passe administrateur incorrect ou compte non autorisé.');
            $result=$action(count($admins)); $this->db->commit(); return $result;
        } catch (Throwable $ex) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $ex; }
    }
    private function lock(int $id,string $version): array {
        $stmt=$this->db->prepare('SELECT id,name,email,role,is_active,password_hash FROM users WHERE id=? FOR UPDATE'); $stmt->execute([$id]); $user=$stmt->fetch();
        if (!$user) throw new DomainException('Compte introuvable.');
        if (!hash_equals(self::version($user),$version)) throw new DomainException('Le compte a changé depuis l’ouverture de la page. Vérifiez les valeurs actuelles avant de réessayer.'); return $user;
    }
    private function audit(string $action): void { $this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$this->actorId,$action,substr($this->ip,0,45)]); }
    public function create(array $input,string $adminPassword): int {
        [$name,$email,$role]=$this->fields($input,true);
        if (!is_string($input['password'] ?? null) || !is_string($input['confirmation'] ?? null)) throw new DomainException('Mot de passe invalide.');
        $hash=$this->password($input['password'],$input['confirmation']);
        return $this->atomic($adminPassword,function() use ($name,$email,$role,$hash) {
            $this->db->prepare('INSERT INTO users (name,email,password_hash,role,is_active) VALUES (?,?,?,?,1)')->execute([$name,$email,$hash,$role]);
            $id=(int)$this->db->lastInsertId(); $this->audit('Compte créé #'.$id.' rôle '.$role); return $id;
        });
    }
    public function edit(int $id,string $version,array $input,string $adminPassword): void {
        [$name,$email,$role]=$this->fields($input,false); $active=$input['is_active'] ?? '';
        if (!is_string($active) || !in_array($active,['0','1'],true)) throw new DomainException('Statut invalide.');
        $this->atomic($adminPassword,function(int $admins) use ($id,$version,$name,$email,$role,$active) {
            $user=$this->lock($id,$version);
            if (($user['role']==='Coach')!==($role==='Coach')) throw new DomainException('Le changement vers ou depuis le rôle Coach n’est pas disponible : les profils et plannings doivent être préservés.');
            if ($id===$this->actorId && ($role!==$user['role'] || $active!==(string)(int)$user['is_active'])) throw new DomainException('Vous ne pouvez pas modifier votre propre rôle ni désactiver votre compte.');
            if ($user['role']==='Admin' && $user['is_active'] && ($role!=='Admin' || $active==='0') && $admins<=1) throw new DomainException('Au moins un administrateur actif doit être conservé.');
            if ((int)$user['is_active']!==(int)$active) {
                // Un secret aléatoire rend les anciennes sessions et l'ancien mot de passe
                // inutilisables même si le compte est réactivé avant leur prochaine requête.
                $rotated=password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT);
                if ($rotated===false) throw new RuntimeException('Impossible de sécuriser le compte.');
                $this->db->prepare('UPDATE users SET name=?,email=?,role=?,is_active=?,password_hash=? WHERE id=?')->execute([$name,$email,$role,(int)$active,$rotated,$id]);
            } else $this->db->prepare('UPDATE users SET name=?,email=?,role=?,is_active=? WHERE id=?')->execute([$name,$email,$role,(int)$active,$id]);
            $this->audit('Compte modifié #'.$id.' rôle '.$role.' statut '.($active==='1'?'actif':'inactif'));
        });
    }
    public function reset(int $id,string $version,string $password,string $confirmation,string $adminPassword): void {
        if ($id===$this->actorId) throw new DomainException('Utilisez Mon compte pour changer votre propre mot de passe.');
        $hash=$this->password($password,$confirmation);
        $this->atomic($adminPassword,function() use ($id,$version,$hash) {
            $this->lock($id,$version); $this->db->prepare('UPDATE users SET password_hash=? WHERE id=?')->execute([$hash,$id]);
            $this->audit('Mot de passe réinitialisé compte #'.$id);
        });
    }
}
