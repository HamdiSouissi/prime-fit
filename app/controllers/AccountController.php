<?php
require_once __DIR__.'/../services/PasswordService.php';
class AccountController {
    public function index(): void {
        $error='';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf();
            $attempts=array_values(array_filter($_SESSION['password_attempts'] ?? [],fn($t)=>$t>time()-900));
            if (count($attempts)>=5) $error='Trop de tentatives. Réessayez dans 15 minutes.';
            else {
                $current=$_POST['current_password'] ?? ''; $new=$_POST['new_password'] ?? ''; $confirmation=$_POST['confirmation'] ?? '';
                if (!is_string($current) || !is_string($new) || !is_string($confirmation)) $error='Vérifiez les champs du formulaire.';
                else {
                    try {
                        $fingerprint=(new PasswordService(db()))->change((int)$_SESSION['user']['id'],$current,$new,$confirmation,$_SERVER['REMOTE_ADDR'] ?? '');
                        session_regenerate_id(true); $_SESSION['auth_fingerprint']=$fingerprint; unset($_SESSION['password_attempts'],$_SESSION['csrf']);
                        $_SESSION['flash']='Mot de passe modifié. Les autres sessions devront se reconnecter.'; redirect('account');
                    } catch (DomainException $ex) { $error=$ex->getMessage(); }
                }
                if ($error!=='') { $attempts[]=time(); $_SESSION['password_attempts']=$attempts; }
            }
        }
        $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/account.php';
    }
}
