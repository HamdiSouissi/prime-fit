<?php
declare(strict_types=1);
function db(): PDO { throw new RuntimeException('Accès à la base inattendu'); }
require __DIR__.'/../app/controllers/StockHistoryController.php';
$_SESSION=['user'=>['id'=>1,'role'=>'Coach']]; $_SERVER['REQUEST_METHOD']='GET'; $_GET=['id'=>'1'];
register_shutdown_function(function (): void { if (http_response_code()!==403 || error_get_last()!==null) echo "\nÉCHEC permissions stock\n"; else echo "\nHistorique stock : accès Coach refusé OK\n"; });
(new StockHistoryController())->index(); echo 'ÉCHEC : accès non bloqué';
