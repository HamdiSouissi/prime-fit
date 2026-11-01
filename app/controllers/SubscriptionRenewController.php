<?php
require_once __DIR__.'/../services/SubscriptionService.php';
class SubscriptionRenewController {
    public function index(): void {
        if (!in_array($_SESSION['user']['role'],['Admin','Gérant','Réceptionniste'],true)) { http_response_code(403); exit('Accès interdit.'); }
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]); if (!$id) { http_response_code(404); exit('Abonnement introuvable.'); }
        $pdo=db(); $error=''; $chosenPlan=null;
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf(); $plan=$_POST['plan'] ?? null; $version=$_POST['subscription_version'] ?? null; $start=$_POST['start'] ?? null;
            try {
                if (!is_string($plan) || !preg_match('/^(\d+)\|([a-f0-9]{64})$/D',$plan,$matches) || (int)$matches[1]<1 || !is_string($version) || !preg_match('/^[a-f0-9]{64}$/D',$version) || !is_string($start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$start)) throw new DomainException('Vérifiez la formule et actualisez la page.');
                $chosenPlan=(int)$matches[1];
                $created=(new SubscriptionService($pdo,(int)$_SESSION['user']['id'],$_SERVER['REMOTE_ADDR'] ?? ''))->renew((int)$id,$matches[2],(int)$matches[1],$version,$start);
                $_SESSION['flash']='Renouvellement enregistré du '.date('d/m/Y',strtotime($created['start'])).' au '.date('d/m/Y',strtotime($created['end'])).', prix fixé : '.dt($created['price']).'. Aucun paiement n’a été enregistré.'; redirect('subscriptions');
            } catch (DomainException $ex) { $error=$ex->getMessage(); }
        }
        $stmt=$pdo->prepare('SELECT s.*,m.first_name,m.last_name,m.status AS member_status,p.name AS plan FROM subscriptions s JOIN members m ON m.id=s.member_id JOIN plans p ON p.id=s.plan_id WHERE s.id=?'); $stmt->execute([$id]); $subscription=$stmt->fetch();
        if (!$subscription) { http_response_code(404); exit('Abonnement introuvable.'); }
        $plans=$pdo->query('SELECT id,name,duration_days,price,is_active FROM plans WHERE is_active=1 ORDER BY name,id')->fetchAll();
        $start=max(date('Y-m-d'),(new DateTimeImmutable($subscription['end_date']))->modify('+1 day')->format('Y-m-d'));
        $chosenPlan=$chosenPlan ?? (int)$subscription['plan_id'];
        $version=SubscriptionService::version($subscription); require __DIR__.'/../views/subscription_renew.php';
    }
}
