<?php
declare(strict_types=1);
/** Opérations administratives de stock : verrou produit, mouvement et audit atomiques. */
class StockManager {
    private PDO $db; private int $userId; private string $ip;
    public function __construct(PDO $db,int $userId,string $ip) { $this->db=$db; $this->userId=$userId; $this->ip=substr($ip,0,45); }
    private function fail(string $message): void { throw new DomainException($message); }
    private function atomic(callable $fn) { $this->db->beginTransaction(); try { $result=$fn(); $this->db->commit(); return $result; } catch (Throwable $ex) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $ex; } }
    private function lock(int $id): array { $stmt=$this->db->prepare('SELECT * FROM products WHERE id=? FOR UPDATE'); $stmt->execute([$id]); $p=$stmt->fetch(); if (!$p) $this->fail('Produit introuvable.'); return $p; }
    private function audit(string $action): void { $this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$this->userId,$action,$this->ip]); }
    public function edit(int $id,array $input): void {
        foreach (['name','sku','price','threshold'] as $key) if (!isset($input[$key]) || !is_string($input[$key])) $this->fail('Vérifiez les champs du produit.');
        $name=trim($input['name']); $sku=trim($input['sku']); $price=str_replace(',','.',trim($input['price']));
        $threshold=filter_var($input['threshold'],FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>1000000]]);
        if ($name==='' || mb_strlen($name)>120 || $sku==='' || mb_strlen($sku)>50 || !preg_match('/^\d{1,7}(\.\d{1,3})?$/D',$price) || $threshold===false) $this->fail('Vérifiez le nom, la référence, le prix et le seuil.');
        $this->atomic(function() use ($id,$name,$sku,$price,$threshold) {
            $this->lock($id); $this->db->prepare('UPDATE products SET name=?,sku=?,price=?,low_stock_threshold=? WHERE id=?')->execute([$name,$sku,$price,$threshold,$id]);
            $this->audit('Produit modifié #'.$id);
        });
    }
    public function setActive(int $id,bool $active): void {
        $this->atomic(function() use ($id,$active) {
            $p=$this->lock($id); if ((bool)$p['is_active']===$active) $this->fail('Le produit est déjà dans cet état.');
            $this->db->prepare('UPDATE products SET is_active=? WHERE id=?')->execute([(int)$active,$id]);
            $this->audit('Produit '.($active?'activé':'désactivé').' #'.$id);
        });
    }
    public function adjust(int $id,int $counted,int $expected,string $note): int {
        $note=trim($note);
        if ($counted<0 || $counted>1000000 || $expected<0 || $expected>1000000 || $note==='' || mb_strlen($note)>255) $this->fail('Indiquez une quantité comptée et un motif valide.');
        return $this->atomic(function() use ($id,$counted,$expected,$note) {
            $product=$this->lock($id); $current=(int)$product['stock'];
            if ($current!==$expected) $this->fail('Le stock a changé depuis l’ouverture du formulaire. Rechargez la page et vérifiez votre comptage.');
            $change=$counted-$current; if ($change===0) $this->fail('Le stock compté est identique au stock actuel.');
            $this->db->prepare('UPDATE products SET stock=? WHERE id=?')->execute([$counted,$id]);
            $this->db->prepare("INSERT INTO stock_movements (product_id,quantity_change,reason,note,created_by) VALUES (?,?,'adjustment',?,?)")->execute([$id,$change,$note,$this->userId]);
            $this->audit('Correction de stock produit #'.$id.' : '.($change>0?'+':'').$change); return $change;
        });
    }
}
