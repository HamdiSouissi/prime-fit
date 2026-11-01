<?php
declare(strict_types=1);
/** Profil professionnel seulement : les accès au compte sont gérés séparément. */
class CoachProfileService {
    public function __construct(private PDO $db,private int $actorId,private string $ip) {}
    public static function version(array $coach): string {
        return hash('sha256',json_encode([(int)$coach['id'],$coach['name'],$coach['email'],(int)$coach['is_active'],$coach['phone'],$coach['specialties'],(string)$coach['commission_rate']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }
    public function get(int $id): ?array {
        $stmt=$this->db->prepare("SELECT u.id,u.name,u.email,u.is_active,cp.phone,cp.specialties,cp.commission_rate FROM users u JOIN coach_profiles cp ON cp.user_id=u.id WHERE u.id=? AND u.role='Coach'");
        $stmt->execute([$id]); return $stmt->fetch() ?: null;
    }
    public function update(int $id,string $version,array $input): void {
        foreach (['phone','specialties','commission_rate'] as $key) if (!isset($input[$key]) || !is_string($input[$key])) throw new DomainException('Vérifiez les champs du profil.');
        if ($id<1 || !preg_match('/^[a-f0-9]{64}$/D',$version)) throw new DomainException('Formulaire invalide. Actualisez la page.');
        $phone=trim($input['phone']); $specialties=trim($input['specialties']); $rate=str_replace(',','.',trim($input['commission_rate']));
        if (mb_strlen($phone)>30 || mb_strlen($specialties)>255 || !preg_match('/^\d{1,3}(?:\.\d{1,2})?$/D',$rate)) throw new DomainException('Vérifiez le téléphone, les spécialités et la commission.');
        $parts=explode('.',$rate,2); $points=(int)$parts[0]*100+(int)str_pad($parts[1] ?? '',2,'0');
        if ($points>10000) throw new DomainException('La commission doit être comprise entre 0 et 100 %.');
        $rate=intdiv($points,100).'.'.str_pad((string)($points%100),2,'0',STR_PAD_LEFT);
        $this->db->beginTransaction();
        try {
            // Même ordre que la planification des séances : compte coach, puis profil.
            $stmt=$this->db->prepare("SELECT id,name,email,is_active FROM users WHERE id=? AND role='Coach' FOR UPDATE"); $stmt->execute([$id]); $user=$stmt->fetch();
            if (!$user) throw new DomainException('Coach introuvable.');
            $stmt=$this->db->prepare('SELECT phone,specialties,commission_rate FROM coach_profiles WHERE user_id=? FOR UPDATE'); $stmt->execute([$id]); $profile=$stmt->fetch();
            if (!$profile) throw new DomainException('Profil coach introuvable.');
            if (!hash_equals(self::version($user+$profile),$version)) throw new DomainException('Le profil ou le compte a changé depuis l’ouverture de la page. Vérifiez les valeurs actuelles.');
            $this->db->prepare('UPDATE coach_profiles SET phone=?,specialties=?,commission_rate=? WHERE user_id=?')->execute([$phone===''?null:$phone,$specialties,$rate,$id]);
            $this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$this->actorId,'Profil coach modifié #'.$id,substr($this->ip,0,45)]);
            $this->db->commit();
        } catch (Throwable $ex) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $ex; }
    }
}
