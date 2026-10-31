<?php
declare(strict_types=1);
$config = require __DIR__ . '/../config/config.php';
if (is_file(__DIR__ . '/../config/local.php')) {
    $config = array_replace_recursive($config, require __DIR__ . '/../config/local.php');
}
date_default_timezone_set($config['timezone']);
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax']);
session_start();
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function verify_csrf(): void { if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(419); exit('Session expirée. Actualisez la page.'); } }
function db(): PDO {
    global $config;
    static $pdo = null;
    if ($pdo === null) {
        $d = $config['db'];
        $pdo = new PDO("mysql:host={$d['host']};port={$d['port']};dbname={$d['name']};charset={$d['charset']}", $d['user'], $d['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    }
    return $pdo;
}
function redirect(string $route): void { header('Location: index.php?page=' . $route); exit; }
function dt($amount): string { return number_format((float)$amount, 3, ',', ' ') . ' DT'; }
