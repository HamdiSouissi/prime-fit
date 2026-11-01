<?php
declare(strict_types=1);
function db(): PDO { throw new RuntimeException('Accès inattendu à la base'); }
require __DIR__.'/../app/controllers/CourseBookingsController.php';
$_SESSION=['user'=>['id'=>1,'role'=>'Comptable']]; $_SERVER['REQUEST_METHOD']='GET'; $_GET=['id'=>'1'];
register_shutdown_function(function (): void { if (http_response_code()!==403 || error_get_last()!==null) echo "\nÉCHEC permissions inscriptions\n"; else echo "\nCours : accès Comptable refusé OK\n"; });
(new CourseBookingsController())->index(); echo 'ÉCHEC : accès non bloqué';
