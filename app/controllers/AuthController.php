<?php
class AuthController {
    public function login(): void {
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            verify_csrf();
            // Limitation simple par session ; utiliser également une limite au niveau serveur en production.
            $attempts = $_SESSION['login_attempts'] ?? [];
            $attempts = array_values(array_filter($attempts, fn($t) => $t > time()-900));
            if (count($attempts) >= 5) { $error = 'Trop de tentatives. Réessayez dans 15 minutes.'; }
            else {
                $stmt = db()->prepare('SELECT id, name, email, password_hash, role, is_active FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([trim($_POST['email'] ?? '')]); $user = $stmt->fetch();
                if ($user && $user['is_active'] && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
                    session_regenerate_id(true); $_SESSION['auth_fingerprint'] = hash('sha256', $user['password_hash']); unset($user['password_hash']); $_SESSION['user'] = $user; unset($_SESSION['login_attempts']);
                    db()->prepare('INSERT INTO activity_logs (user_id, action, ip_address) VALUES (?, ?, ?)')->execute([$user['id'], 'Connexion', substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45)]);
                    redirect('dashboard');
                }
                $attempts[] = time(); $_SESSION['login_attempts'] = $attempts; $error = 'Identifiants incorrects.';
            }
        }
        require __DIR__ . '/../views/login.php';
    }
    public function logout(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
        verify_csrf(); $_SESSION = []; session_destroy(); redirect('login');
    }
}
