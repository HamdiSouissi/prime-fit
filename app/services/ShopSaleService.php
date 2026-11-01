<?php
declare(strict_types=1);
/** Une demande de vente est réservée avant le verrou produit, puis validée atomiquement. */
class ShopSaleService {
    public function __construct(private PDO $db,private int $userId,private string $ip) {}
    public static function productVersion(array $product): string {
        return hash('sha256',json_encode([(int)$product['id'],$product['name'],$product['sku'],(string)$product['price'],(int)$product['is_active']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
    }
    private function existing(string $token,string $hash): ?int {
        $stmt=$this->db->prepare('SELECT request_hash,sale_id FROM shop_sale_requests WHERE request_token=?'); $stmt->execute([$token]); $request=$stmt->fetch();
        if (!$request) return null;
        if (!hash_equals($request['request_hash'],$hash)) throw new DomainException('Cette demande a déjà été utilisée pour une autre vente. Actualisez la boutique et vérifiez les ventes.');
        if (!$request['sale_id']) throw new RuntimeException('Demande de vente incomplète. Vérifiez les données avant de réessayer.');
        return (int)$request['sale_id'];
    }
    /** Retourne la vente existante si la même demande est rejouée. */
    public function sell(int $product,string $version,int $quantity,string $method,string $token): array {
        if (PHP_INT_SIZE<8) throw new RuntimeException('La vente requiert PHP 64 bits.');
        if ($product<1 || $quantity<1 || $quantity>1000000 || !preg_match('/^[a-f0-9]{64}$/D',$version) || !preg_match('/^[a-f0-9]{64}$/D',$token) || !in_array($method,['espèces','chèque','virement','D17','Flouci','carte bancaire'],true)) throw new DomainException('Vérifiez le produit, la quantité et le moyen de paiement.');
        $hash=hash('sha256',json_encode([$product,$version,$quantity,$method],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        $this->db->beginTransaction();
        try {
            // L'unicité réserve la demande ; un concurrent attend le commit ou le rollback.
            $this->db->prepare('INSERT INTO shop_sale_requests (request_token,request_hash,sale_id) VALUES (?,?,NULL)')->execute([$token,$hash]);
            $stmt=$this->db->prepare('SELECT * FROM products WHERE id=? FOR UPDATE'); $stmt->execute([$product]); $item=$stmt->fetch();
            if (!$item || !$item['is_active']) throw new DomainException('Produit introuvable ou inactif.');
            if (!hash_equals(self::productVersion($item),$version)) throw new DomainException('Le produit ou son prix a changé. Vérifiez les informations actualisées avant de vendre.');
            if ((int)$item['stock']<$quantity) throw new DomainException('Stock insuffisant pour cette vente.');
            $price=(string)$item['price'];
            if (!preg_match('/^\d{1,7}(?:\.\d{1,3})?$/D',$price)) throw new DomainException('Prix du produit invalide.');
            $parts=explode('.',$price,2); $millimes=(int)$parts[0]*1000+(int)str_pad($parts[1] ?? '',3,'0');
            if ($millimes*$quantity>9999999999999) throw new DomainException('Le total dépasse le montant maximal autorisé.');
            $this->db->prepare('INSERT INTO shop_sales (product_id,product_name_snapshot,product_sku_snapshot,quantity,unit_price,total,method,sold_by) VALUES (?,?,?,?,?,CAST(? AS DECIMAL(10,3))*?,?,?)')->execute([$product,$item['name'],$item['sku'],$quantity,$price,$price,$quantity,$method,$this->userId]);
            $sale=(int)$this->db->lastInsertId();
            $this->db->prepare('UPDATE products SET stock=stock-? WHERE id=?')->execute([$quantity,$product]);
            $this->db->prepare("INSERT INTO stock_movements (product_id,quantity_change,reason,sale_id,created_by) VALUES (?,?,'sale',?,?)")->execute([$product,-$quantity,$sale,$this->userId]);
            $this->db->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$this->userId,'Vente boutique #'.$sale,substr($this->ip,0,45)]);
            $this->db->prepare('UPDATE shop_sale_requests SET sale_id=? WHERE request_token=?')->execute([$sale,$token]);
            $this->db->commit(); return ['id'=>$sale,'replayed'=>false];
        } catch (Throwable $ex) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            // Ne reconnaître une répétition qu'après une erreur d'unicité et un contrôle du contenu.
            if ($ex instanceof PDOException && $ex->getCode()==='23000') {
                $existing=$this->existing($token,$hash);
                if ($existing!==null) return ['id'=>$existing,'replayed'=>true];
            }
            throw $ex;
        }
    }
}
