<?php
declare(strict_types=1);
require_once __DIR__.'/../services/MemberPortalAuth.php';
require_once __DIR__.'/../services/MemberPortalInvitations.php';
require_once __DIR__.'/../services/MemberPortalData.php';
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
                if ($session) { session_regenerate_id(true); $_SESSION['member_portal']=$session; unset($_SESSION['csrf']); redirect('member_space'); }
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
        $data=(new MemberPortalData(db()))->get($_SESSION['member_portal']['member_id']);
        require __DIR__.'/../views/member_portal.php';
    }
    public function logout(): void {
        $this->secureHeaders(); if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit; }
        verify_csrf(); unset($_SESSION['member_portal'],$_SESSION['member_activation'],$_SESSION['csrf']); session_regenerate_id(true); redirect('member_login');
    }
}
