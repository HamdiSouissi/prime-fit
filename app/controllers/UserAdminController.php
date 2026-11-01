<?php
require_once __DIR__.'/../services/UserAdminService.php';
class UserAdminController {
    private function requireAdmin(): void { if (($_SESSION['user']['role'] ?? '')!=='Admin') { http_response_code(403); exit('Accès interdit.'); } }
    private function attempt(callable $operation): string {
        verify_csrf();
        $attempts=array_values(array_filter($_SESSION['user_admin_attempts'] ?? [],fn($t)=>$t>time()-900));
        if (count($attempts)>=5) return 'Trop de tentatives. Réessayez dans 15 minutes.';
        $adminPassword=$_POST['admin_password'] ?? '';
        try {
            if (!is_string($adminPassword)) throw new DomainException('Mot de passe administrateur invalide.');
            $route=$operation($adminPassword); unset($_SESSION['user_admin_attempts']); redirect($route);
        } catch (DomainException $ex) { $error=$ex->getMessage(); }
          catch (PDOException $ex) { if ($ex->getCode()==='23000' && (int)($ex->errorInfo[1] ?? 0)===1062) $error='Cette adresse e-mail est déjà utilisée.'; else throw $ex; }
        $attempts[]=time(); $_SESSION['user_admin_attempts']=$attempts; return $error;
    }
    public function index(): void {
        $this->requireAdmin(); header('Cache-Control: no-store, private'); $pdo=db(); $error='';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            $error=$this->attempt(function(string $adminPassword) use ($pdo) {
                $id=(new UserAdminService($pdo,(int)$_SESSION['user']['id'],$_SERVER['REMOTE_ADDR'] ?? ''))->create($_POST,$adminPassword);
                $_SESSION['flash']='Compte créé. Transmettez le mot de passe par un canal sécurisé.'; return 'user&id='.$id;
            });
        }
        $total=(int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(); $page=min(max(1,(int)($_GET['p'] ?? 1)),max(1,(int)ceil($total/25))); $offset=($page-1)*25;
        $users=$pdo->query('SELECT id,name,email,role,is_active,created_at FROM users ORDER BY name,id LIMIT 25 OFFSET '.$offset)->fetchAll();
        $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/users.php';
    }
    public function edit(): void {
        $this->requireAdmin(); header('Cache-Control: no-store, private'); $pdo=db(); $error='';
        $id=filter_var($_GET['id'] ?? '',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id) { http_response_code(404); exit('Compte introuvable.'); }
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            $error=$this->attempt(function(string $adminPassword) use ($pdo,$id) {
                $version=$_POST['version'] ?? ''; $action=$_POST['action'] ?? '';
                if (!is_string($version) || !preg_match('/^[a-f0-9]{64}$/D',$version) || !is_string($action)) throw new DomainException('Formulaire invalide. Actualisez la page.');
                $service=new UserAdminService($pdo,(int)$_SESSION['user']['id'],$_SERVER['REMOTE_ADDR'] ?? '');
                if ($action==='edit') { $service->edit($id,$version,$_POST,$adminPassword); $_SESSION['flash']='Compte mis à jour. Si le statut a changé, l’ancien mot de passe est invalidé : après réactivation, définissez-en un nouveau.'; }
                elseif ($action==='reset') {
                    $password=$_POST['password'] ?? ''; $confirmation=$_POST['confirmation'] ?? '';
                    if (!is_string($password) || !is_string($confirmation)) throw new DomainException('Mot de passe invalide.');
                    $service->reset($id,$version,$password,$confirmation,$adminPassword); $_SESSION['flash']='Mot de passe réinitialisé. Les sessions existantes devront se reconnecter.';
                } else throw new DomainException('Action invalide.');
                return 'user&id='.$id;
            });
        }
        $stmt=$pdo->prepare('SELECT id,name,email,role,is_active,password_hash,created_at FROM users WHERE id=?'); $stmt->execute([$id]); $account=$stmt->fetch();
        if (!$account) { http_response_code(404); exit('Compte introuvable.'); }
        $version=UserAdminService::version($account); unset($account['password_hash']);
        $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/user.php';
    }
}
