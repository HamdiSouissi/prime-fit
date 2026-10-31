<?php
require_once __DIR__.'/../services/SubscriptionPriceService.php';
class SubscriptionPriceController {
    public function index(): void {
        if (!in_array($_SESSION['user']['role'],['Admin','Gérant'],true)) { http_response_code(403); exit('Accès interdit.'); }
        $error=''; $pdo=db();
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf(); $id=filter_var($_POST['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); $price=$_POST['price'] ?? '';
            if (!$id || !is_string($price)) $error='Vérifiez l’abonnement et le prix.';
            else {
                try { (new SubscriptionPriceService($pdo,(int)$_SESSION['user']['id'],$_SERVER['REMOTE_ADDR'] ?? ''))->verify($id,$price);
                    $_SESSION['flash']='Prix historique fixé. Le solde peut maintenant être calculé.'; redirect('subscription_prices');
                } catch (DomainException $ex) { $error=$ex->getMessage(); }
            }
        }
        $total=(int)$pdo->query('SELECT COUNT(*) FROM subscriptions WHERE agreed_price IS NULL')->fetchColumn();
        $pageNumber=min(max(1,(int)($_GET['p'] ?? 1)),max(1,(int)ceil($total/10))); $offset=($pageNumber-1)*10;
        $rows=$pdo->query('SELECT s.id,s.start_date,s.end_date,m.first_name,m.last_name,p.name AS plan,p.price AS current_price,(SELECT COALESCE(SUM(pay.amount),0) FROM payments pay WHERE pay.subscription_id=s.id) AS paid FROM subscriptions s JOIN members m ON m.id=s.member_id JOIN plans p ON p.id=s.plan_id WHERE s.agreed_price IS NULL ORDER BY s.id DESC LIMIT 10 OFFSET '.$offset)->fetchAll();
        $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/subscription_prices.php';
    }
}
