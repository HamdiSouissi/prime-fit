<?php
require_once __DIR__.'/../services/StockHistory.php';
require_once __DIR__.'/../services/StockHistoryCsv.php';
class StockHistoryController {
    public function index(): void {
        if (!in_array($_SESSION['user']['role'] ?? '',['Admin','Gérant','Réceptionniste','Comptable'],true)) { http_response_code(403); exit('Accès interdit.'); }
        if ($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); exit('Méthode non autorisée.'); }
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if (!$id) { http_response_code(404); exit('Produit introuvable.'); }
        $service=new StockHistory(db()); $product=$service->product((int)$id); if (!$product) { http_response_code(404); exit('Produit introuvable.'); }
        $error=''; $filters=['from'=>'','to'=>'','reason'=>'']; $history=['rows'=>[],'total'=>0,'page'=>1,'pages'=>1];
        try {
            $filters=StockHistory::filters($_GET);
            $page=filter_var($_GET['p'] ?? 1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) ?: 1;
            $history=$service->page((int)$id,$filters,(int)$page);
        } catch (DomainException $ex) {
            $error=$ex->getMessage(); http_response_code(422);
            foreach ($filters as $key=>$_) if (is_string($_GET[$key] ?? null)) $filters[$key]=$_GET[$key];
        }
        header('Cache-Control: private, no-store'); require __DIR__.'/../views/stock_history.php';
    }
    public function export(): void {
        if (!in_array($_SESSION['user']['role'] ?? '',['Admin','Gérant','Réceptionniste','Comptable'],true)) { http_response_code(403); exit('Accès interdit.'); }
        if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit('Méthode non autorisée.'); }
        verify_csrf();
        $id=filter_var($_POST['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if (!$id) { http_response_code(404); exit('Produit introuvable.'); }
        try { $filters=StockHistory::filters($_POST); } catch (DomainException $ex) { http_response_code(422); exit(e($ex->getMessage())); }
        $pdo=db(); $service=new StockHistory($pdo); $product=$service->product((int)$id); if (!$product) { http_response_code(404); exit('Produit introuvable.'); }
        // Journaliser la demande, sans présumer que le téléchargement a abouti.
        $pdo->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (?,?,?)')->execute([(int)$_SESSION['user']['id'],'Export des mouvements demandé produit #'.(int)$id,substr($_SERVER['REMOTE_ADDR'] ?? '',0,45)]);
        header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="mouvements-stock-'.(int)$id.'.csv"'); header('Cache-Control: private, no-store');
        $out=fopen('php://output','w'); StockHistoryCsv::write($out,$service,$product,$filters); fclose($out);
    }

}
