<?php
require_once __DIR__ . '/../models/Dashboard.php';
class DashboardController {
    public function index(): void {
        $data = (new Dashboard(db()))->data($_SESSION['user']['role']);
        require __DIR__ . '/../views/dashboard.php';
    }
}
