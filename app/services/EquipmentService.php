<?php
declare(strict_types=1);
/** Modifications atomiques des équipements et de leurs interventions. */
class EquipmentService {
    public function __construct(private PDO $db,private int $userId,private string $ip) {}
    private function fail(string $message): void { throw new DomainException($message); }
    private function dateValid(string $date): bool { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date); return $d && $d->format('Y-m-d')===$date; }
    private function atomic(callable $action) { $this->db->beginTransaction(); try { $result=$action(); $this->db->commit(); return $result; } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; } }
    private function lock(int $id): array { $stmt=$this->db->prepare('SELECT * FROM equipment WHERE id=? FOR UPDATE'); $stmt->execute([$id]); $row=$stmt->fetch(); if (!$row) $this->fail('Équipement introuvable.'); return $row; }
    private function audit(string $action): void { $this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$this->userId,$action,substr($this->ip,0,45)]); }
    public function edit(int $id,array $input): void {
        foreach (['name','reference_code','location','purchased_on'] as $key) if (!isset($input[$key]) || !is_string($input[$key])) $this->fail('Vérifiez les champs de la fiche.');
        $name=trim($input['name']); $reference=trim($input['reference_code']); $location=trim($input['location']); $purchase=trim($input['purchased_on']);
        if ($name==='' || mb_strlen($name)>120 || $reference==='' || mb_strlen($reference)>50 || mb_strlen($location)>120 || ($purchase!=='' && (!$this->dateValid($purchase) || $purchase>date('Y-m-d')))) $this->fail('Vérifiez le nom, la référence, l’emplacement et la date d’achat.');
        $this->atomic(function() use ($id,$name,$reference,$location,$purchase) {
            $this->lock($id);
            $this->db->prepare('UPDATE equipment SET name=?,reference_code=?,location=?,purchased_on=? WHERE id=?')->execute([$name,$reference,$location,$purchase ?: null,$id]);
            $this->audit('Fiche équipement modifiée #'.$id);
        });
    }
    public function intervention(int $id,array $input): void {
        foreach (['intervention_date','type','description','status','next_maintenance','cost','expected_status','expected_next','expected_intervention'] as $key) if (!isset($input[$key]) || !is_string($input[$key])) $this->fail('Vérifiez les champs de l’intervention.');
        $date=trim($input['intervention_date']); $type=$input['type']; $description=trim($input['description']); $status=$input['status']; $next=trim($input['next_maintenance']); $cost=str_replace(',','.',trim($input['cost']));
        $expectedId=filter_var($input['expected_intervention'],FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
        if (!$this->dateValid($date) || $date>date('Y-m-d') || !in_array($type,['maintenance','breakdown','repair'],true) || $description==='' || mb_strlen($description)>1000 || !in_array($status,['operational','out_of_service','maintenance'],true) || ($next!=='' && !$this->dateValid($next)) || ($cost!=='' && !preg_match('/^\d{1,7}(\.\d{1,3})?$/D',$cost)) || $expectedId===false) $this->fail('Vérifiez la date, le type, la description, le statut et le coût.');
        $this->atomic(function() use ($id,$date,$type,$description,$status,$next,$cost,$expectedId,$input) {
            $item=$this->lock($id);
            $stmt=$this->db->prepare('SELECT MAX(id) AS last_id,MAX(intervention_date) AS last_date FROM equipment_interventions WHERE equipment_id=?'); $stmt->execute([$id]); $latest=$stmt->fetch();
            if ((int)($latest['last_id'] ?? 0)!==$expectedId || $item['status']!==$input['expected_status'] || ($item['next_maintenance'] ?? '')!==$input['expected_next']) $this->fail('La fiche a changé depuis l’ouverture du formulaire. Vérifiez les informations actuelles avant de réessayer.');
            if ($latest['last_date'] && $date<$latest['last_date']) $this->fail('La date ne peut pas précéder la dernière intervention : celle-ci détermine l’état actuel de l’équipement.');
            $this->db->prepare('INSERT INTO equipment_interventions (equipment_id,intervention_date,type,description,cost,created_by) VALUES (?,?,?,?,?,?)')->execute([$id,$date,$type,$description,$cost==='' ? null : $cost,$this->userId]);
            $intervention=(int)$this->db->lastInsertId();
            $this->db->prepare('UPDATE equipment SET status=?,next_maintenance=? WHERE id=?')->execute([$status,$next ?: null,$id]);
            $this->audit('Intervention #'.$intervention.' équipement #'.$id);
        });
    }
}
