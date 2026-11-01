<?php
require_once __DIR__.'/../services/LoginRateLimiter.php';
class AuthController {
    public function login(): void {
        $error = ''; $emailInput='';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            $rawEmail=$_POST['email'] ?? ''; $password=$_POST['password'] ?? '';
            $validEmail=is_string($rawEmail) && strlen($rawEmail)<=760 && mb_check_encoding($rawEmail,'UTF-8') && mb_strlen(trim($rawEmail),'UTF-8')<=190;
            $emailInput=$validEmail ? trim($rawEmail) : '';
            $validPassword=is_string($password) && strlen($password)<=1024;
            $address=is_string($_SERVER['REMOTE_ADDR'] ?? null) ? $_SERVER['REMOTE_ADDR'] : '';
            try {
                // La résolution préalable utilise la collation réelle de MySQL : les variantes d'une
                // adresse existante partagent le même compteur de compte.
                $stmt=db()->prepare('SELECT id, name, email, password_hash, role, is_active FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$emailInput]); $user=$emailInput!=='' ? $stmt->fetch() : false;
                $account=LoginRateLimiter::accountKey($user ? (int)$user['id'] : null,$emailInput,$address);
                $retry=(new LoginRateLimiter(db()))->reserve($address,$account);
                if ($retry>0) {
                    http_response_code(429); header('Retry-After: '.$retry);
                    $error='Trop de tentatives. Réessayez après quelques minutes.';
                } else {
                    // Coût comparable pour une adresse inconnue et aucun détail sur le compte.
                    $hash=$user ? $user['password_hash'] : '$2y$12$n0iQ5q6SVqUn8R.RruoQ0eYnbRc2Yly.bxm8Na3OpO1cg96.2DIoe';
                    $verified=password_verify($validPassword ? $password : '',$hash);
                    if ($validEmail && $validPassword && $user && $user['is_active'] && $verified) {
                        db()->prepare('INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)')->execute([$user['id'], 'Connexion', substr($address, 0, 45)]);
                        session_regenerate_id(true); $_SESSION['auth_fingerprint']=hash('sha256',$user['password_hash']); unset($user['password_hash']); $_SESSION['user']=$user; unset($_SESSION['login_attempts'],$_SESSION['csrf']);
                        redirect('dashbord');
                    }
                    $error='Identifiants incorrects.';
                }
            } catch (PDOException $ex) {
                error_log('Connexion indisponible : '.$ex->getMessage()); http_response_code(503);
                if (($ex->errorInfo[0] ?? '')==='42S02' && (int)($ex->errorInfo[1] ?? 0)===1146 && stripos((string)($ex->errorInfo[2] ?? ''),'auth_rate_limits')!==false) $error='La protection des connexions doit être installée : contactez l’administrateur (database/auth_rate_limits.sql).';
                else $error='Connexion temporairement indisponible. Contactez votre administrateur.';
            }
        }
        require __DIR__ . '/../views/login.php';
    }
    public function logout(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
        verify_csrf(); $_SESSION = []; session_destroy(); redirect('login');
    }
}
