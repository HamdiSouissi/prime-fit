<?php
require_once __DIR__.'/../services/StockManager.php';
require_once __DIR__.'/../services/ShopSaleService.php';
/** Boutique : stocks et ventes d'un seul produit par opération. */
class ShopController {
    private function fail(string $message): void { throw new DomainException($message); }
    private function audit(PDO $pdo, string $action): void {
        $pdo->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([$_SESSION['user']['id'],$action,substr($_SERVER['REMOTE_ADDR'] ?? '',0,45)]);
    }
    public function index(): void {
        $role=$_SESSION['user']['role'];
        if (!in_array($role,['Admin','Gérant','Réceptionniste','Comptable'],true)) { http_response_code(403); exit('Accès interdit.'); }
        $canSell=in_array($role,['Admin','Gérant','Réceptionniste'],true);
        $canManage=in_array($role,['Admin','Gérant'],true);
        $error=''; $pdo=db();
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf(); $action=$_POST['action'] ?? '';
            if (($action==='sale' && !$canSell) || (in_array($action,['product','restock','edit_product','toggle_product','adjust'],true) && !$canManage)) { http_response_code(403); exit('Accès interdit.'); }
            try {
                if ($action==='product') {
                    $name=trim($_POST['name'] ?? ''); $sku=trim($_POST['sku'] ?? ''); $price=str_replace(',','.',trim($_POST['price'] ?? ''));
                    $stock=filter_var($_POST['stock'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>1000000]]);
                    $threshold=filter_var($_POST['threshold'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>1000000]]);
                    if ($name==='' || mb_strlen($name)>120 || $sku==='' || mb_strlen($sku)>50 || !preg_match('/^\d{1,7}(\.\d{1,3})?$/',$price) || $stock===false || $threshold===false) $this->fail('Vérifiez le nom, la référence, le prix et les quantités.');
                    $pdo->beginTransaction();
                    $pdo->prepare('INSERT INTO products (name,sku,price,stock,low_stock_threshold) VALUES (?,?,?,?,?)')->execute([$name,$sku,$price,$stock,$threshold]);
                    $id=(int)$pdo->lastInsertId();
                    if ($stock>0) $pdo->prepare("INSERT INTO stock_movements (product_id,quantity_change,reason,created_by) VALUES (?,?,'initial',?)")->execute([$id,$stock,$_SESSION['user']['id']]);
                    $this->audit($pdo,'Produit créé #'.$id); $pdo->commit(); $_SESSION['flash']='Produit créé.';
                } elseif (in_array($action,['edit_product','toggle_product','adjust'],true)) {
                    $id=filter_var($_POST['product_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                    if (!$id) $this->fail('Produit invalide.');
                    $service=new StockManager($pdo,(int)$_SESSION['user']['id'],$_SERVER['REMOTE_ADDR'] ?? '');
                    if ($action==='edit_product') { $service->edit($id,$_POST); $_SESSION['flash']='Produit mis à jour. Les ventes passées conservent leur prix d’origine.'; }
                    elseif ($action==='toggle_product') {
                        $active=$_POST['active'] ?? ''; if (!in_array($active,['0','1'],true)) $this->fail('Statut invalide.');
                        $service->setActive($id,$active==='1'); $_SESSION['flash']=$active==='1' ? 'Produit réactivé.' : 'Produit désactivé. Il ne peut plus être vendu.';
                    } else {
                        $counted=filter_var($_POST['counted'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>1000000]]);
                        $expected=filter_var($_POST['expected_stock'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>1000000]]);
                        $note=$_POST['note'] ?? ''; if ($counted===false || $expected===false || !is_string($note)) $this->fail('Quantité ou motif invalide.');
                        $change=$service->adjust($id,$counted,$expected,$note); $_SESSION['flash']='Stock corrigé de '.($change>0?'+':'').$change.' unité(s).';
                    }
                    redirect('shop&product='.$id);
                } elseif ($action==='sale') {
                    $selection=$_POST['product_id'] ?? ''; $quantity=filter_var($_POST['quantity'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]);
                    $method=$_POST['method'] ?? ''; $token=$_POST['request_token'] ?? '';
                    if (!is_string($selection) || !preg_match('/^(\d+)\|([a-f0-9]{64})$/D',$selection,$matches) || (int)$matches[1]<1 || !$quantity || !is_string($method) || !is_string($token)) $this->fail('Vérifiez le produit, la quantité et le moyen de paiement.');
                    $result=(new ShopSaleService($pdo,(int)$_SESSION['user']['id'],$_SERVER['REMOTE_ADDR'] ?? ''))->sell((int)$matches[1],$matches[2],$quantity,$method,$token);
                    $_SESSION['shop_sale_notice']=['id'=>$result['id'],'message'=>$result['replayed'] ? 'Cette vente avait déjà été enregistrée : aucun nouveau stock n’a été déduit.' : 'Vente enregistrée et stock mis à jour.'];
                    redirect('shop_receipt&id='.$result['id']);
                } elseif ($action==='restock') {
                    $product=filter_var($_POST['product_id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                    $quantity=filter_var($_POST['quantity'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]);
                    if (!$product || !$quantity) $this->fail('Vérifiez le produit et la quantité.');
                    $pdo->beginTransaction();
                    $stmt=$pdo->prepare('SELECT * FROM products WHERE id=? FOR UPDATE'); $stmt->execute([$product]); $item=$stmt->fetch();
                    if (!$item || !$item['is_active']) $this->fail('Produit introuvable ou inactif.');
                    if ((int)$item['stock']+$quantity>1000000) $this->fail('Le stock ne peut pas dépasser 1 000 000 unités.');
                    $pdo->prepare('UPDATE products SET stock=stock+? WHERE id=?')->execute([$quantity,$product]);
                    $pdo->prepare("INSERT INTO stock_movements (product_id,quantity_change,reason,created_by) VALUES (?,?,'restock',?)")->execute([$product,$quantity,$_SESSION['user']['id']]);
                    $this->audit($pdo,'Réapprovisionnement produit #'.$product); $pdo->commit(); $_SESSION['flash']='Stock mis à jour.';
                } else $this->fail('Action invalide.');
                redirect('shop');
            } catch (DomainException $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); $error=$ex->getMessage(); }
              catch (PDOException $ex) {
                  if ($pdo->inTransaction()) $pdo->rollBack(); $info=$ex->errorInfo ?? [];
                  if (($info[0] ?? '')==='42S22' && (int)($info[1] ?? 0)===1054 && stripos((string)($info[2] ?? ''),'note')!==false) $error='Après sauvegarde, importez database/shop_inventory.sql dans la base utilisée puis réessayez.';
                  elseif ($ex->getCode()==='23000') $error='Cette référence existe déjà.'; else throw $ex;
              }
        }
        $products=$pdo->query('SELECT * FROM products ORDER BY name,id')->fetchAll();
        $requestToken=bin2hex(random_bytes(32));
        $selectedId=filter_var($_GET['product'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $selectedProduct=null; $movements=[];
        if ($selectedId) {
            $stmt=$pdo->prepare('SELECT * FROM products WHERE id=?'); $stmt->execute([$selectedId]); $selectedProduct=$stmt->fetch();
            if (!$selectedProduct) { http_response_code(404); exit('Produit introuvable.'); }
            $stmt=$pdo->prepare('SELECT m.*,u.name AS author FROM stock_movements m LEFT JOIN users u ON u.id=m.created_by WHERE m.product_id=? ORDER BY m.created_at DESC,m.id DESC LIMIT 100'); $stmt->execute([$selectedId]); $movements=$stmt->fetchAll();
        }

        $date=$_GET['date'] ?? date('Y-m-d'); $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
        if (!$parsed || $parsed->format('Y-m-d')!==$date) $date=date('Y-m-d');
        $stmt=$pdo->prepare('SELECT COALESCE(SUM(total),0) FROM shop_sales WHERE sold_at>=? AND sold_at<DATE_ADD(?,INTERVAL 1 DAY)'); $stmt->execute([$date,$date]); $salesTotal=$stmt->fetchColumn();
        $stmt=$pdo->prepare('SELECT COUNT(*) FROM shop_sales WHERE sold_at>=? AND sold_at<DATE_ADD(?,INTERVAL 1 DAY)'); $stmt->execute([$date,$date]); $total=(int)$stmt->fetchColumn();
        $pageNumber=max(1,(int)($_GET['p'] ?? 1)); $pageNumber=min($pageNumber,max(1,(int)ceil($total/10))); $offset=($pageNumber-1)*10;
        $stmt=$pdo->prepare('SELECT s.*,COALESCE(s.product_name_snapshot,p.name) AS name,COALESCE(s.product_sku_snapshot,p.sku) AS sku,u.name AS seller FROM shop_sales s JOIN products p ON p.id=s.product_id LEFT JOIN users u ON u.id=s.sold_by WHERE s.sold_at>=? AND s.sold_at<DATE_ADD(?,INTERVAL 1 DAY) ORDER BY s.sold_at DESC,s.id DESC LIMIT 10 OFFSET '.$offset); $stmt->execute([$date,$date]); $sales=$stmt->fetchAll();
        $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/shop.php';
    }
    /** Lecture seule : mêmes rôles que la boutique, y compris sur l'URL directe. */
    public function receipt(): void {
        if (!in_array($_SESSION['user']['role'] ?? '',['Admin','Gérant','Réceptionniste','Comptable'],true)) { http_response_code(403); exit('Accès interdit.'); }
        if ($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); exit('Méthode non autorisée.'); }
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id) { http_response_code(404); exit('Reçu introuvable.'); }
        $stmt=db()->prepare('SELECT s.*,s.product_name_snapshot,s.product_sku_snapshot,p.name AS current_name,p.sku AS current_sku FROM shop_sales s LEFT JOIN products p ON p.id=s.product_id WHERE s.id=?');
        $stmt->execute([$id]); $sale=$stmt->fetch();
        if (!$sale) { http_response_code(404); exit('Reçu introuvable.'); }
        $gymSettings=[];
        try { $gymSettings=db()->query('SELECT address,phone,email,tax_id FROM gym_settings WHERE id=1')->fetch() ?: []; }
        catch (PDOException $ex) {
            $info=$ex->errorInfo ?? [];
            if (!(($info[0] ?? '')==='42S02' && (int)($info[1] ?? 0)===1146 && stripos((string)($info[2] ?? ''),'gym_settings')!==false)) throw $ex;
        }
        header('Cache-Control: private, no-store');
        $notice=''; $savedNotice=$_SESSION['shop_sale_notice'] ?? null;
        if (is_array($savedNotice) && ($savedNotice['id'] ?? null)===(int)$sale['id']) { $notice=$savedNotice['message']; unset($_SESSION['shop_sale_notice']); }
        require __DIR__.'/../views/shop_receipt.php';
    }

}
