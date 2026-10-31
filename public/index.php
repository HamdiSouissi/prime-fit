<?php
declare(strict_types=1);
require __DIR__ . '/../app/core/bootstrap.php';
require __DIR__ . '/../app/controllers/AuthController.php';
require __DIR__ . '/../app/controllers/DashboardController.php';
$page = $_GET['page'] ?? (isset($_SESSION['user']) ? 'dashboard' : 'login');
try {
    if ($page === 'logout') { (new AuthController())->logout(); }
    elseif ($page === 'login') { if (isset($_SESSION['user'])) redirect('dashboard'); (new AuthController())->login(); }
    elseif ($page === 'dashboard') { if (!isset($_SESSION['user'])) redirect('login'); (new DashboardController())->index(); }
    else { http_response_code(404); echo 'Page introuvable'; }
} catch (PDOException $ex) {
    error_log($ex->getMessage()); http_response_code(503); echo 'Service indisponible. Vérifiez la configuration de la base de données.';
}
