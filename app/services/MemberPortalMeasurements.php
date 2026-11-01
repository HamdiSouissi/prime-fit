<?php
declare(strict_types=1);
/** Correction des mesures du membre connecté ; les notes du coach restent inchangées. */
final class MemberPortalMeasurements {
    public function __construct(private PDO $db) {}
    public static function version(array $row): string {
        return hash('sha256',json_encode([(int)$row['id'],(int)$row['member_id'],$row['measured_on'],$row['weight_kg'],$row['height_cm'],$row['waist_cm']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }
    private static function decimal(string $raw,float $min,float $max): ?string {
        $value=str_replace(',','.',trim($raw));
        return preg_match('/\A\d{1,3}(?:\.\d{1,2})?\z/D',$value) && (float)$value >= $min && (float)$value <= $max ? $value : null;
    }
    public function update(array $session,array $input,string $ip,?DateTimeImmutable $now=null): void {
        $memberId=$session['member_id'] ?? null; $fingerprint=$session['fingerprint'] ?? null;
        if (!is_int($memberId) || $memberId<1 || !is_string($fingerprint)) throw new DomainException('Session membre indisponible.');
        foreach (['measurement_id','version','measured_on','weight_kg','height_cm','waist_cm','current_password'] as $key) if (!is_string($input[$key] ?? null)) throw new DomainException('Vérifiez les champs du formulaire.');
        $id=filter_var($input['measurement_id'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $date=trim($input['measured_on']); $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date); $now=$now ?? new DateTimeImmutable();
        $weight=self::decimal($input['weight_kg'],10,500); $height=self::decimal($input['height_cm'],50,250); $waistRaw=trim($input['waist_cm']); $waist=$waistRaw==='' ? null : self::decimal($waistRaw,20,300);
        if (!$id || !$parsed || $parsed->format('Y-m-d')!==$date || $date>$now->format('Y-m-d') || $weight===null || $height===null || ($waistRaw!=='' && $waist===null)) throw new DomainException('Vérifiez la date, le poids (10–500 kg), la taille (50–250 cm) et le tour de taille (20–300 cm).');
        if ($input['current_password']==='' || strlen($input['current_password'])>1024) throw new DomainException('Mot de passe actuel incorrect.');
        $this->db->beginTransaction();
        try {
            $stmt=$this->db->prepare('SELECT email,status FROM members WHERE id=? FOR UPDATE'); $stmt->execute([$memberId]); $member=$stmt->fetch();
            $stmt=$this->db->prepare('SELECT email,password_hash FROM member_portal_accounts WHERE member_id=? FOR UPDATE'); $stmt->execute([$memberId]); $account=$stmt->fetch();
            if (!$member || $member['status']!=='active' || !$account || !is_string($account['password_hash']) || trim((string)$member['email'])!==$account['email'] || !hash_equals(hash('sha256',$account['password_hash']),$fingerprint) || !password_verify($input['current_password'],$account['password_hash'])) throw new DomainException('Mot de passe incorrect ou session indisponible.');
            $stmt=$this->db->prepare('SELECT id,member_id,measured_on,weight_kg,height_cm,waist_cm FROM fitness_measurements WHERE id=? AND member_id=? FOR UPDATE'); $stmt->execute([(int)$id,$memberId]); $row=$stmt->fetch();
            if (!$row) throw new DomainException('Mesure introuvable.');
            if (!hash_equals(self::version($row),$input['version'])) throw new DomainException('Cette mesure a changé. Vérifiez les valeurs actuelles avant de réessayer.');
            $this->db->prepare('INSERT INTO member_measurement_revisions (measurement_id,member_id,previous_date,previous_weight,previous_height,previous_waist,new_date,new_weight,new_height,new_waist) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([(int)$id,$memberId,$row['measured_on'],$row['weight_kg'],$row['height_cm'],$row['waist_cm'],$date,$weight,$height,$waist]);
            $this->db->prepare('UPDATE fitness_measurements SET measured_on=?,weight_kg=?,height_cm=?,waist_cm=? WHERE id=? AND member_id=?')->execute([$date,$weight,$height,$waist,(int)$id,$memberId]);
            $this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (NULL,?,?)')->execute(['Espace membre : mesure #'.(int)$id.' modifiée par membre #'.$memberId,substr($ip,0,45)]);
            $this->db->commit();
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }
}
