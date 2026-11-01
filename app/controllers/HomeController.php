<?php
declare(strict_types=1);
require_once __DIR__.'/../services/ContactFormService.php';
require_once __DIR__.'/../services/ContactRateLimiter.php';
/** Page publique et formulaire de contact. */
class HomeController {
    public function index(): void {
        if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET',['GET','HEAD','POST'],true)) { http_response_code(405); header('Allow: GET, HEAD, POST'); exit('Méthode non autorisée.'); }
        $contact=['phone'=>'','email'=>'','address'=>'','opening_hours'=>''];
        try {
            $row=db()->query('SELECT phone,email,address,opening_hours FROM gym_settings WHERE id=1')->fetch();
            if ($row) foreach ($contact as $key=>$_) $contact[$key]=is_string($row[$key] ?? null) ? trim($row[$key]) : '';
        } catch (PDOException $ex) { error_log('Coordonnées du site indisponibles : '.$ex->getMessage()); }
        global $config;
        $formService=new ContactFormService($config['mail'] ?? []);
        $contactAvailable=$formService->configured($contact['email']);
        $contactFields=['name'=>'','email'=>'','phone'=>'','message'=>''];
        $contactError=''; $contactSuccess=!empty($_SESSION['contact_success']);
        unset($_SESSION['contact_success']);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET')==='POST') {
            verify_csrf();
            [$contactFields,$contactError]=$formService->validate($_POST);
            if (!empty($_POST['website'])) $contactError='Impossible d’envoyer ce message.';
            if (!$contactError && !$contactAvailable) $contactError='Le formulaire est temporairement indisponible. Contactez directement le club.';
            if (!$contactError) {
                try {
                    if (!(new ContactRateLimiter(db()))->reserve((string)($_SERVER['REMOTE_ADDR'] ?? ''))) {
                        http_response_code(429); header('Retry-After: 900'); $contactError='Trop de messages envoyés. Réessayez dans quelques minutes.';
                    } elseif (!$formService->send($contact['email'],$contactFields)) {
                        $contactError='L’envoi a échoué. Veuillez réessayer ou contacter directement le club.';
                    } else {
                        $_SESSION['contact_success']=true;
                        header('Location: ./#contact-form',true,303); exit;
                    }
                } catch (Throwable $ex) {
                    error_log('Formulaire de contact indisponible : vérifiez la table auth_rate_limits et le transport mail.');
                    http_response_code(503); $contactError='Le formulaire est temporairement indisponible. Veuillez réessayer ou contacter directement le club.';
                }
            }
        }
        // Only publish upcoming scheduled course titles and times, never member information.
        $publicCourses=[];
        try {
            $stmt=db()->prepare("SELECT title,starts_at FROM courses WHERE status='scheduled' AND starts_at >= ? ORDER BY starts_at ASC, id ASC LIMIT 6");
            $stmt->execute([date('Y-m-d H:i:s')]);
            $publicCourses=$stmt->fetchAll();
        } catch (PDOException $ex) { error_log('Cours du site indisponibles : '.$ex->getMessage()); }
        require __DIR__.'/../views/home.php';
    }
}
