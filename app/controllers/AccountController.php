<?php
require_once __DIR__.'/../services/PasswordService.php';
require_once __DIR__.'/../services/AccountProfileService.php';
require_once __DIR__.'/../services/LoginRateLimiter.php';
class AccountController {
    public function index(): void {
        $error=''; $profile=null; $profileAvailable=false;
        try { $profile=(new AccountProfileService(db()))->get((int)$_SESSION['user']['id']); $profileAvailable=true; }
        catch (PDOException $ex) { error_log('Coordonnées du compte indisponibles : '.$ex->getMessage()); }
        catch (DomainException $ex) { $error=$ex->getMessage(); }
        if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? 'password')==='profile') {
            verify_csrf();
            if (!$profileAvailable) $error='La modification des coordonnées est indisponible. Vérifiez la migration database/user_contact_phone.sql.';
            else {
                $address=is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '';
                try {
                    $retry=(new LoginRateLimiter(db()))->reserve($address,'user:'.(int)$_SESSION['user']['id']);
                    if ($retry>0) { http_response_code(429); header('Retry-After: '.$retry); $error='Trop de tentatives. Réessayez plus tard.'; }
                    else {
                        $updated=(new AccountProfileService(db()))->update((int)$_SESSION['user']['id'],$_POST,$address);
                        $_SESSION['user']['name']=$updated['name']; $_SESSION['user']['email']=$updated['email'];
                        $_SESSION['flash']='Vos coordonnées ont été mises à jour.'; redirect('account');
                    }
                } catch (DomainException $ex) { $error=$ex->getMessage(); }
                catch (PDOException $ex) { error_log('Modification du compte indisponible : '.$ex->getMessage()); http_response_code(503); $error='Modification temporairement indisponible. Vérifiez la configuration de la base.'; }
            }
        } elseif ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? 'password')==='password') {
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
        elseif ($_SERVER['REQUEST_METHOD']==='POST') { verify_csrf(); $error='Action invalide.'; }
        $flash=$_SESSION['flash'] ?? ''; unset($_SESSION['flash']); require __DIR__.'/../views/account.php';
    }
}
