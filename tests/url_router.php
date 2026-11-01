<?php
declare(strict_types=1);
require __DIR__.'/../app/core/UrlRouter.php';
$cases=[
    ['/attendance','/index.php',[],false,'attendance',false],
    ['/dashbord','/index.php',[],false,'dashbord',false], ['/dashboard','/index.php',[],true,'dashboard',false],
    ['/attendance?date=2026-01-01','/index.php',['date'=>'2026-01-01'],true,'attendance',false],
    ['/public/attendance','/public/index.php',[],true,'attendance',false],
    ['/index.php?page=attendance','/index.php',['page'=>'attendance'],false,'attendance',true],
    ['/public/?page=member_card&id=5','/public/index.php',['page'=>'member_card','id'=>'5'],true,'member_card',true],
    ['/','/index.php',[],false,'home',false], ['/public/','/public/index.php',[],true,'home',false],
    ['/attendance?page=payments','/index.php',['page'=>'payments'],true,'attendance',false],
    ['/something/else','/index.php',[],true,null,false], ['/attendance/','/index.php',[],true,null,false],
    ['/index.php?page=x','/index.php',['page'=>['x']],true,null,false],
    ['/index.php?page=%2F%2Fevil','/index.php',['page'=>'//evil'],true,null,false],
    ['/public2/attendance','/public/index.php',[],true,null,false],
];
foreach ($cases as [$uri,$script,$query,$auth,$page,$legacy]) {
    $result=UrlRouter::resolve($uri,$script,$query,$auth); if ($result!==['page'=>$page,'legacy'=>$legacy]) throw new RuntimeException('Routage incorrect : '.$uri);
}
if (UrlRouter::canonical('/public/index.php','attendance',['page'=>'attendance','date'=>'2026-01-01','q'=>'été'])!=='/public/attendance?date=2026-01-01&q=%C3%A9t%C3%A9') throw new RuntimeException('Redirection historique incorrecte');
if (UrlRouter::redirectTarget('member_card&id=12')!=='member_card?id=12' || UrlRouter::redirectTarget('attendance')!=='attendance') throw new RuntimeException('Redirection POST incorrecte');
foreach (["//example.com", "login\r\nLocation: https://example.com", '../login'] as $route) { try { UrlRouter::redirectTarget($route); throw new RuntimeException('Redirection dangereuse acceptée'); } catch (InvalidArgumentException $ex) {} }
echo "URL : routes propres, sous-dossier, paramètres et compatibilité OK\n";
