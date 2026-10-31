<?php
declare(strict_types=1);
/** Les modifications de catalogue sont atomiques et ne touchent pas aux adhésions existantes. */
class PlanService {
    public function __construct(private PDO $db,private int $userId,private string $ip) {}
    public static function version(array $plan): string {
        return hash('sha256',json_encode([(int)$plan['id'],(string)$plan['name'],(int)$plan['duration_days'],(string)$plan['price'],(int)$plan['is_active']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }
    private function atomic(callable $action): void { $this->db->beginTransaction(); try { $action(); $this->db->commit(); } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; } }
    private function lock(int $id,string $version): array {
        $stmt=$this->db->prepare('SELECT id,name,duration_days,price,is_active FROM plans WHERE id=? FOR UPDATE'); $stmt->execute([$id]); $p=$stmt->fetch();
        if (!$p) throw new DomainException('Formule introuvable.');
        if (!hash_equals(self::version($p),$version)) throw new DomainException('La formule a changé depuis l’ouverture de la page. Vérifiez les valeurs actuelles avant de réessayer.');
        return $p;
    }
    private function audit(string $action): void { $this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$this->userId,$action,substr($this->ip,0,45)]); }
    public function edit(int $id,string $version,array $input): void {
        foreach (['name','duration_days','price'] as $key) if (!isset($input[$key]) || !is_string($input[$key])) throw new DomainException('Vérifiez les champs de la formule.');
        $name=trim($input['name']); $duration=filter_var($input['duration_days'],FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>3650]]); $price=str_replace(',','.',trim($input['price']));
        if ($name==='' || mb_strlen($name)>100 || $duration===false || !preg_match('/^\d{1,7}(\.\d{1,3})?$/D',$price)) throw new DomainException('Vérifiez le nom, la durée (1 à 3 650 jours) et le prix (jusqu’à trois décimales).');
        $this->atomic(function() use ($id,$version,$name,$duration,$price) {
            $this->lock($id,$version);
            $this->db->prepare('UPDATE plans SET name=?,duration_days=?,price=? WHERE id=?')->execute([$name,$duration,$price,$id]);
            $this->audit('Formule modifiée #'.$id.' : durée '.$duration.' jours, prix '.$price.' DT');
        });
    }
    public function setActive(int $id,string $version,bool $active): void {
        $this->atomic(function() use ($id,$version,$active) {
            $p=$this->lock($id,$version); if ((bool)$p['is_active']===$active) throw new DomainException('La formule est déjà dans cet état.');
            $this->db->prepare('UPDATE plans SET is_active=? WHERE id=?')->execute([(int)$active,$id]);
            $this->audit('Formule '.($active?'activée':'désactivée').' #'.$id);
        });
    }
}
