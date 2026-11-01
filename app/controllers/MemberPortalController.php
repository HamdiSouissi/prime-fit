<?php
declare(strict_types=1);
require_once __DIR__.'/../services/MemberPortalAuth.php';
require_once __DIR__.'/../services/MemberPortalInvitations.php';
require_once __DIR__.'/../services/MemberPortalData.php';
require_once __DIR__.'/../services/MemberPortalBooking.php';
require_once __DIR__.'/../services/MemberPortalProfile.php';
require_once __DIR__.'/../services/LoginRateLimiter.php';
/** Pages isolées de l'espace du personnel ; chaque requête protégée revalide la session. */
final class MemberPortalController {
    private function secureHeaders(): void { header('Cache-Control: no-store, private'); header('Referrer-Policy: no-referrer'); header('X-Robots-Tag: noindex, nofollow'); }
    private function limit(string $key): void {
        $retry=(new LoginRateLimiter(db()))->reserve((string)($_SERVER['REMOTE_ADDR'] ?? ''),'portal:'.$key);
        if ($retry>0) { http_response_code(429); header('Retry-After: '.$retry); throw new DomainException('Trop de tentatives. Réessayez dans quelques minutes.'); }
    }
    public function login(): void {
        $this->secureHeaders(); $error=''; $email='';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf(); $raw=$_POST['email'] ?? ''; $pwd=$_POST['password'] ?? '';
            $email=is_string($raw) && strlen($raw)<=190 ? trim($raw) : '';
            try {
                // Utiliser l'identifiant trouvé par MySQL pour que les variantes de casse/collation
                // ne contournent pas la limite par compte.
                $stmt=db()->prepare('SELECT member_id FROM member_portal_accounts WHERE email=? LIMIT 1');
                $stmt->execute([$email]); $memberId=$stmt->fetchColumn();
                $this->limit($memberId ? 'login-id:'.$memberId : 'login-unknown:'.mb_strtolower($email,'UTF-8'));
                $session=(new MemberPortalAuth(db()))->authenticate($email,is_string($pwd) ? $pwd : '');
                if ($session) { session_regenerate_id(true); $_SESSION['member_portal']=$session; unset($_SESSION['csrf'],$_SESSION['portal_notice'],$_SESSION['portal_profile_notice']); redirect('member_space'); }
                $error='Identifiants incorrects.';
            } catch (DomainException $e) { $error=$e->getMessage(); }
        }
        $mode='login'; require __DIR__.'/../views/member_portal_auth.php';
    }
    public function activate(): void {
        $this->secureHeaders(); $error=''; $success=false;
        if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['token'])) {
            // Le secret n'est plus dans l'URL de la page de formulaire ni dans les liens sortants.
            $token=$_GET['token']; unset($_SESSION['member_activation']);
            if (is_string($token) && preg_match('/\A[a-f0-9]{64}\z/D',$token)) $_SESSION['member_activation']=$token;
            redirect('member_activate');
        }
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf();
            try {
                $token=$_SESSION['member_activation'] ?? '';
                $this->limit('activation:'.hash('sha256',is_string($token) ? $token : ''));
                $pass=$_POST['password'] ?? ''; $confirm=$_POST['confirmation'] ?? '';
                if (!is_string($token) || !is_string($pass) || !is_string($confirm)) throw new DomainException('Lien ou mot de passe invalide.');
                (new MemberPortalInvitations(db()))->activate($token,$pass,$confirm);
                unset($_SESSION['member_activation']); session_regenerate_id(true); unset($_SESSION['csrf']); $success=true;
            } catch (DomainException $e) { $error=$e->getMessage(); }
        }
        $mode='activate'; require __DIR__.'/../views/member_portal_auth.php';
    }
    public function space(): void {
        $this->secureHeaders(); $auth=new MemberPortalAuth(db());
        if (!$auth->validSession($_SESSION['member_portal'] ?? null)) { unset($_SESSION['member_portal']); redirect('member_login'); }
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf();
            $id=filter_var($_POST['course_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            try {
                if (($_POST['action'] ?? '')!=='book' || !$id) throw new DomainException('Demande de réservation invalide.');
                $status=(new MemberPortalBooking(db()))->book($_SESSION['member_portal'],(int)$id,(string)($_SERVER['REMOTE_ADDR'] ?? ''));
                $_SESSION['portal_notice']=['type'=>'success','message'=>$status==='confirmed' ? 'Votre inscription est confirmée.' : 'Ce cours est complet : vous êtes sur la liste d’attente.'];
            } catch (DomainException $e) {
                $_SESSION['portal_notice']=['type'=>'error','message'=>$e->getMessage()];
            } catch (PDOException $e) {
                // Ne pas exposer les erreurs SQL ; en cas de verrou concurrent, proposer un nouvel essai.
                error_log('Réservation membre indisponible : '.$e->getMessage());
                $_SESSION['portal_notice']=['type'=>'error','message'=>'La réservation n’a pas pu être enregistrée. Vérifiez vos inscriptions puis réessayez.'];
            }
            redirect('member_space');
        }
        $notice=$_SESSION['portal_notice'] ?? null; unset($_SESSION['portal_notice']);
        $data=(new MemberPortalData(db()))->get($_SESSION['member_portal']['member_id']);
        require __DIR__.'/../views/member_portal.php';
    }
    public function profile(): void {
        $this->secureHeaders(); $auth=new MemberPortalAuth(db());
        if (!$auth->validSession($_SESSION['member_portal'] ?? null)) { unset($_SESSION['member_portal']); redirect('member_login'); }
        $session=$_SESSION['member_portal']; $service=new MemberPortalProfile(db()); $error=''; $notice=$_SESSION['portal_profile_notice'] ?? ''; unset($_SESSION['portal_profile_notice']);
        try { $profile=$service->get($session); } catch (DomainException $e) { unset($_SESSION['member_portal']); redirect('member_login'); }
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf();
            try {
                // Réservation persistante de tentative, indépendante des sessions.
                $this->limit('profile:'.$session['member_id']);
                $service->update($session,$_POST,(string)($_SERVER['REMOTE_ADDR'] ?? ''));
                $_SESSION['portal_profile_notice']='Vos informations ont été enregistrées.'; redirect('member_details');
            } catch (DomainException $e) { $error=$e->getMessage(); }
            catch (PDOException $e) { error_log('Modification fiche membre indisponible : '.$e->getMessage()); $error='La modification n’a pas pu être enregistrée. Réessayez plus tard.'; }
            // Recharger la fiche et une version fraîche après erreur ; ne jamais réafficher le mot de passe.
            try { $profile=$service->get($session); } catch (DomainException $e) { unset($_SESSION['member_portal']); redirect('member_login'); }
        }
        require __DIR__.'/../views/member_portal_profile.php';
    }
    public function logout(): void {
        $this->secureHeaders(); if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit; }
        verify_csrf(); unset($_SESSION['member_portal'],$_SESSION['member_activation'],$_SESSION['portal_notice'],$_SESSION['portal_profile_notice'],$_SESSION['csrf']); session_regenerate_id(true); redirect('member_login');
    }
}
