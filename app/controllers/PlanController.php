<?php
require_once __DIR__.'/../services/PlanService.php';
class PlanController {
    public function index(): void {
        if (!in_array($_SESSION['user']['role'],['Admin','Gérant'],true)) { http_response_code(403); exit('Accès interdit.'); }
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id) { http_response_code(404); exit('Formule introuvable.'); }
        $pdo=db(); $error='';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf(); $version=$_POST['version'] ?? ''; $action=$_POST['action'] ?? '';
            if (!is_string($version) || !preg_match('/^[a-f0-9]{64}$/D',$version) || !is_string($action)) $error='Formulaire invalide. Rechargez la page.';
            else {
                try {
                    $service=new PlanService($pdo,(int)$_SESSION['user']['id'],$_SERVER['REMOTE_ADDR'] ?? '');
                    if ($action==='edit') { $service->edit($id,$version,$_POST); $_SESSION['flash']='Formule mise à jour. Les abonnements existants conservent leurs prix et dates.'; }
                    elseif ($action==='activate' || $action==='deactivate') { $service->setActive($id,$version,$action==='activate'); $_SESSION['flash']=$action==='activate' ? 'Formule réactivée.' : 'Formule désactivée : elle ne peut plus être souscrite ou renouvelée.'; }
                    else throw new DomainException('Action invalide.');
                    redirect('plan&id='.$id);
                } catch (DomainException $ex) { $error=$ex->getMessage(); }
            }
        }
        $stmt=$pdo->prepare('SELECT id,name,duration_days,price,is_active FROM plans WHERE id=?'); $stmt->execute([$id]); $plan=$stmt->fetch();
        if (!$plan) { http_response_code(404); exit('Formule introuvable.'); }
        $version=PlanService::version($plan); $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/plan.php';
    }
}
