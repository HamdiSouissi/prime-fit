<?php
declare(strict_types=1);
function e($s): string { return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function gym_name(): string { return 'Salle'; }
function gym_brand_markup(): string { return 'Salle'; }
function csrf(): string { return 'test'; }
$_SESSION=['user'=>['id'=>1,'name'=>'Coach','role'=>'Coach']]; $_POST=[]; $error=''; $flash=''; $canWrite=false;
$filters=['q'=>'<test>&','from'=>'2026-01-01','to'=>'','status'=>'scheduled']; $filterError=''; $result=['total'=>21,'page'=>1,'pages'=>2];
$courseUrl=static fn(array $extra=[])=>'index.php?'.http_build_query(array_merge(['page'=>'courses','filter'=>'1'],$filters,['p'=>$result['page']],$extra));
$selectedCourse=null; $rooms=[]; $coaches=[]; $members=[]; $bookings=[];
$courses=[['id'=>7,'title'=>'<Yoga>','starts_at'=>'2030-01-01 23:00:00','ends_at'=>'2030-01-02 00:30:00','room'=>'Studio','coach'=>'Nadia','confirmed'=>0,'waiting'=>0,'capacity'=>10,'status'=>'scheduled']];
ob_start(); require __DIR__.'/../app/views/courses.php'; $html=ob_get_clean();
foreach (['value="&lt;test&gt;&amp;"','&lt;Yoga&gt;','02/01/2030 00:30','p=2','course=7'] as $part) if (!str_contains($html,$part)) throw new RuntimeException('Rendu manquant : '.$part);
if (str_contains($html,'>Modifier</a>') || str_contains($html,'Annuler le cours</button>') || str_contains($html,'<Yoga>')) throw new RuntimeException('Droits ou échappement incorrects');
echo "Cours : rendu des filtres, pagination, dates et droits Coach OK\n";
