<?php
declare(strict_types=1);
function db(): PDO { throw new RuntimeException('Accès inattendu à la base'); }
require __DIR__.'/../app/controllers/ShopController.php';
$_SESSION=['user'=>['id'=>1,'role'=>'Admin']]; $_SERVER['REQUEST_METHOD']='GET'; $_GET=['id'=>'-1'];
register_shutdown_function(function (): void {
    if (http_response_code()!==404 || error_get_last()!==null) { echo "\nÉCHEC reçu invalide\n"; return; }
    echo "\nReçu boutique : identifiant invalide refusé OK\n";
});
(new ShopController())->receipt();
echo 'ÉCHEC : contrôleur non interrompu';
