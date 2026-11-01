<?php
declare(strict_types=1);
function db(): PDO { throw new RuntimeException('Accès inattendu'); }
require __DIR__.'/../app/controllers/CoachProfileController.php';
$_SESSION=['user'=>['id'=>3,'role'=>'Coach']]; $_GET=['id'=>'1']; $_SERVER['REQUEST_METHOD']='POST';
register_shutdown_function(function (): void { if (http_response_code()!==403 || error_get_last()!==null) echo "\nÉCHEC permissions profil coach\n"; else echo "\nProfil coach : accès non autorisé refusé OK\n"; });
(new CoachProfileController())->index(); echo 'ÉCHEC : accès non bloqué';
