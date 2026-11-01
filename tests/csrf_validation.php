<?php
declare(strict_types=1);
// Exécuter séparément : verify_csrf termine la requête si le jeton est invalide.
require __DIR__.'/../app/core/bootstrap.php';
$case=$argv[1] ?? '';
$_SESSION['csrf']='expected';
if ($case==='valid') $_POST['csrf']='expected';
elseif ($case==='array') $_POST['csrf']=['expected'];
elseif ($case==='missing-session') { unset($_SESSION['csrf']); $_POST['csrf']=''; }
else $_POST['csrf']='wrong';
verify_csrf(); echo 'ACCEPTED';
