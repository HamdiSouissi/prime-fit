<?php
declare(strict_types=1);
require_once __DIR__.'/../services/MemberPortalAuth.php';
require_once __DIR__.'/../services/MemberPortalInvitations.php';
require_once __DIR__.'/../services/MemberPortalData.php';
require_once __DIR__.'/../services/MemberPortalBooking.php';
require_once __DIR__.'/../services/MemberPortalProfile.php';
require_once __DIR__.'/../services/MemberPortalCard.php';
require_once __DIR__.'/../services/MemberCardMailer.php';
require_once __DIR__.'/../services/MemberPortalMailer.php';
require_once __DIR__.'/../services/MemberPortalPasswordReset.php';
require_once __DIR__.'/../services/MemberPortalQrLogin.php';
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
    public function forgot(): void {
        $this->secureHeaders(); $error=''; $sent=false;
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf(); $email=is_string($_POST['email'] ?? null) && strlen($_POST['email'])<=190 ? trim($_POST['email']) : '';
            try {
                $stmt=db()->prepare('SELECT member_id FROM member_portal_accounts WHERE email=? LIMIT 1'); $stmt->execute([$email]); $memberId=$stmt->fetchColumn();
                $this->limit($memberId ? 'forgot-id:'.$memberId : 'forgot-unknown:'.mb_strtolower($email,'UTF-8'));
                global $config;
                $mailer=new MemberPortalMailer(array_merge($config['mail'] ?? [],['base_url'=>$config['base_url'] ?? '', 'gym_name'=>gym_name()]));
                if ($mailer->configured()) {
                    $invite=(new MemberPortalPasswordReset(db()))->issue($email);
                    if ($invite && $mailer->sendReset($invite)!=='accepted') error_log('Envoi de réinitialisation membre impossible.');
                } else error_log('Réinitialisation membre : messagerie ou URL non configurée.');
                $sent=true;
            } catch (DomainException $e) { $error=$e->getMessage(); }
        }
        $mode='forgot'; require __DIR__.'/../views/member_portal_reset.php';
    }
    public function reset(): void {
        $this->secureHeaders(); $error=''; $success=false;
        if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['token'])) {
            $token=$_GET['token']; unset($_SESSION['member_reset']);
            if (is_string($token) && preg_match('/\A[a-f0-9]{64}\z/D',$token)) $_SESSION['member_reset']=$token;
            redirect('member_reset');
        }
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf();
            try {
                $token=$_SESSION['member_reset'] ?? '';
                $this->limit('reset:'.hash('sha256',is_string($token) ? $token : ''));
                $pass=$_POST['password'] ?? ''; $confirm=$_POST['confirmation'] ?? '';
                if (!is_string($token) || !is_string($pass) || !is_string($confirm)) throw new DomainException('Lien ou mot de passe invalide.');
                (new MemberPortalPasswordReset(db()))->reset($token,$pass,$confirm,(string)($_SERVER['REMOTE_ADDR'] ?? ''));
                unset($_SESSION['member_reset'],$_SESSION['member_portal'],$_SESSION['csrf']); session_regenerate_id(true); $success=true;
            } catch (DomainException $e) { $error=$e->getMessage(); }
        }
        $mode='reset'; require __DIR__.'/../views/member_portal_reset.php';
    }
    public function qrLogin(): void {
        $this->secureHeaders(); $error=''; $sent=false;
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf(); $qr=is_string($_POST['qr'] ?? null) ? $_POST['qr'] : '';
            if (strlen($qr)>68) $qr='';
            try {
                $id=null;
                if (preg_match('/\APF1:([a-f0-9]{64})\z/D',$qr,$match)) { $stmt=db()->prepare('SELECT id FROM members WHERE qr_token=?'); $stmt->execute([$match[1]]); $id=$stmt->fetchColumn(); }
                $this->limit($id ? 'qr-login-id:'.$id : 'qr-login-unknown:'.hash('sha256',$qr));
                global $config; $mailer=new MemberPortalMailer(array_merge($config['mail'] ?? [],['base_url'=>$config['base_url'] ?? '','gym_name'=>gym_name()]));
                if ($mailer->configured()) {
                    $invite=(new MemberPortalQrLogin(db()))->issue($qr);
                    if ($invite && $mailer->sendQrLogin($invite)!=='accepted') error_log('Envoi de confirmation de connexion membre impossible.');
                } else error_log('Connexion QR membre : messagerie ou URL non configurée.');
                $sent=true;
            } catch (DomainException $e) { $error=$e->getMessage(); }
        }
        $mode='scan'; require __DIR__.'/../views/member_portal_qr_login.php';
    }
    public function qrConfirm(): void {
        $this->secureHeaders(); $error='';
        if ($_SERVER['REQUEST_METHOD']==='GET' && isset($_GET['token'])) {
            $token=$_GET['token']; unset($_SESSION['member_qr_confirm']);
            if (is_string($token) && preg_match('/\A[a-f0-9]{64}\z/D',$token)) $_SESSION['member_qr_confirm']=$token;
            redirect('member_qr_confirm');
        }
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf();
            try {
                $token=$_SESSION['member_qr_confirm'] ?? '';
                $this->limit('qr-confirm:'.hash('sha256',is_string($token) ? $token : ''));
                if (!is_string($token)) throw new DomainException('Lien invalide ou expiré.');
                $session=(new MemberPortalQrLogin(db()))->confirm($token,(string)($_SERVER['REMOTE_ADDR'] ?? ''));
                session_regenerate_id(true); $_SESSION['member_portal']=$session;
                unset($_SESSION['member_qr_confirm'],$_SESSION['csrf'],$_SESSION['portal_notice'],$_SESSION['portal_profile_notice'],$_SESSION['portal_card_notice']);
                redirect('member_space');
            } catch (DomainException $e) { $error=$e->getMessage(); }
        }
        $mode='confirm'; require __DIR__.'/../views/member_portal_qr_login.php';
    }
    public function space(): void {
        $this->secureHeaders(); $auth=new MemberPortalAuth(db());
        if (!$auth->validSession($_SESSION['member_portal'] ?? null)) { unset($_SESSION['member_portal']); redirect('member_login'); }
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf();
            $id=filter_var($_POST['course_id'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            try {
                $action=$_POST['action'] ?? '';
                if ($action==='measurement_update') {
                    $this->limit('measurement:'.$_SESSION['member_portal']['member_id']);
                    (new MemberPortalMeasurements(db()))->update($_SESSION['member_portal'],$_POST,(string)($_SERVER['REMOTE_ADDR'] ?? ''));
                    $_SESSION['portal_notice']=['type'=>'success','message'=>'Votre mesure a été mise à jour.'];
                } elseif ($action==='book' && $id) {
                    $status=(new MemberPortalBooking(db()))->book($_SESSION['member_portal'],(int)$id,(string)($_SERVER['REMOTE_ADDR'] ?? ''));
                    $_SESSION['portal_notice']=['type'=>'success','message'=>$status==='confirmed' ? 'Votre inscription est confirmée.' : 'Ce cours est complet : vous êtes sur la liste d’attente.'];
                } else throw new DomainException('Demande invalide.');
            } catch (DomainException $e) {
                $_SESSION['portal_notice']=['type'=>'error','message'=>$e->getMessage()];
            } catch (PDOException $e) {
                // Ne pas exposer les erreurs SQL ; en cas de verrou concurrent, proposer un nouvel essai.
                error_log('Action espace membre indisponible : '.$e->getMessage());
                $_SESSION['portal_notice']=['type'=>'error','message'=>'La modification n’a pas pu être enregistrée. Vérifiez les données affichées puis réessayez.'];
            }
            $returnPage=filter_var($_POST['measurement_page'] ?? 1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]) ?: 1;
            redirect('member_space&measurement_page='.$returnPage);
        }
        $notice=$_SESSION['portal_notice'] ?? null; unset($_SESSION['portal_notice']);
        $measurePage=filter_var($_GET['measurement_page'] ?? 1,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000000]]) ?: 1;
        $data=(new MemberPortalData(db()))->get($_SESSION['member_portal']['member_id'],null,(int)$measurePage);
        require __DIR__.'/../views/member_portal.php';
    }
    public function card(): void {
        $this->secureHeaders();
        if (!(new MemberPortalAuth(db()))->validSession($_SESSION['member_portal'] ?? null)) { unset($_SESSION['member_portal']); redirect('member_login'); }
        $session=$_SESSION['member_portal']; $service=new MemberPortalCard(db());
        try { $member=$service->get($session); } catch (DomainException $e) { unset($_SESSION['member_portal']); redirect('member_login'); }
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            verify_csrf();
            try {
                $this->limit('qr-mail:'.$session['member_id']);
                // Relecture avant envoi : ni destinataire ni QR ne proviennent de la requête.
                $member=$service->get($session);
                global $config;
                $status=(new MemberCardMailer(array_merge($config['mail'] ?? [],['gym_name'=>gym_name()])))->send($member);
                $messages=['accepted'=>'Votre carte a été acceptée par le serveur de messagerie. Vérifiez votre boîte de réception.','not_configured'=>'La messagerie n’est pas configurée. Contactez votre salle.','missing_email'=>'Votre adresse e-mail est indisponible. Contactez votre salle.','missing_qr'=>'Aucune carte QR n’est disponible. Contactez votre salle.','failed'=>'L’envoi a échoué. Veuillez réessayer ou contacter votre salle.'];
                $_SESSION['portal_card_notice']=['type'=>$status==='accepted' ? 'success' : 'error','message'=>$messages[$status]];
                if ($status==='accepted') db()->prepare('INSERT INTO activity_logs (user_id,action,ip_address) VALUES (NULL,?,?)')->execute(['Espace membre : carte QR demandée par e-mail membre #'.$session['member_id'],substr((string)($_SERVER['REMOTE_ADDR'] ?? ''),0,45)]);
            } catch (DomainException $e) { $_SESSION['portal_card_notice']=['type'=>'error','message'=>$e->getMessage()]; }
            redirect('member_my_card');
        }
        $notice=$_SESSION['portal_card_notice'] ?? null; unset($_SESSION['portal_card_notice']);
        require __DIR__.'/../views/member_portal_card.php';
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
        verify_csrf(); unset($_SESSION['member_portal'],$_SESSION['member_activation'],$_SESSION['member_reset'],$_SESSION['member_qr_confirm'],$_SESSION['portal_notice'],$_SESSION['portal_profile_notice'],$_SESSION['portal_card_notice'],$_SESSION['csrf']); session_regenerate_id(true); redirect('member_login');
    }
}
