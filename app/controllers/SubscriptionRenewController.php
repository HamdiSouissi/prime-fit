<?php
require_once __DIR__.'/../services/SubscriptionService.php';
class SubscriptionRenewController {
    public function index(): void {
        if (!in_array($_SESSION['user']['role'],['Admin','Gérant','Réceptionniste'],true)) { http_response_code(403); exit('Accès interdit.'); }
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if (!$id) { http_response_code(404); exit('Abonnement introuvable.'); }
        $pdo=db(); $error=''; $chosenPlan=null;
        if ($_SERVER['REQUEST_METHOD']!=='GET') { http_response_code(405); exit('Méthode non autorisée.'); }
        $stmt=$pdo->prepare('SELECT s.*,m.first_name,m.last_name,m.status AS member_status,p.name AS plan FROM subscriptions s JOIN members m ON m.id=s.member_id JOIN plans p ON p.id=s.plan_id WHERE s.id=?'); $stmt->execute([$id]); $subscription=$stmt->fetch();
        if (!$subscription) { http_response_code(404); exit('Abonnement introuvable.'); }
        $plans=$pdo->query('SELECT id,name,duration_days,price,is_active FROM plans WHERE is_active=1 ORDER BY name,id')->fetchAll();
        $promotions=(new SubscriptionPricing($pdo))->available(date('Y-m-d'));
        $start=max(date('Y-m-d'),(new DateTimeImmutable($subscription['end_date']))->modify('+1 day')->format('Y-m-d'));
        $chosenPlan=$chosenPlan ?? (int)$subscription['plan_id'];
        $version=SubscriptionService::version($subscription); require __DIR__.'/../views/subscription_renew.php';
    }
}
