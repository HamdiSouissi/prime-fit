<?php
return [
    'app_name' => 'Horizon Sportif de Bir El Hafey',
    'timezone' => 'Africa/Tunis',
    // URL publique de confiance du dossier public, sans slash final. HTTPS requis hors localhost.
    'base_url' => '',
    // Activer uniquement après configuration d’un service d’envoi sur le serveur.
    'mail' => ['enabled' => false, 'from' => ''],
    'db' => ['host' => '127.0.0.1', 'port' => 3306, 'name' => 'prime_fit', 'user' => 'root', 'password' => '', 'charset' => 'utf8mb4'],
];
