<?php
declare(strict_types=1);
// La sortie du contrôleur doit intervenir avant tout accès aux données.
function db(): PDO { throw new RuntimeException('Accès à la base non autorisé'); }
require __DIR__.'/../app/controllers/ShopController.php';
$_SESSION=['user'=>['id'=>1,'role'=>'Coach']]; $_SERVER['REQUEST_METHOD']='GET'; $_GET=['id'=>'1'];
register_shutdown_function(function (): void {
    if (http_response_code()!==403 || error_get_last()!==null) { echo "\nÉCHEC accès reçu boutique\n"; return; }
    echo "\nReçu boutique : accès Coach refusé OK\n";
});
(new ShopController())->receipt();
echo 'ÉCHEC : contrôleur non interrompu';
