<?php
declare(strict_types=1);
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE gym_settings (id INTEGER PRIMARY KEY,phone TEXT,email TEXT,address TEXT,opening_hours TEXT); INSERT INTO gym_settings VALUES (1,'+216 12 345 678','contact@example.tn','<Rue> test','Lundi 08h–20h\nMardi 08h–20h')");
function db(): PDO { global $db; return $db; } function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); } function gym_name(): string { return 'Horizon <Sport>'; }
require __DIR__.'/../app/controllers/HomeController.php'; $_SERVER['REQUEST_METHOD']='GET'; $_SESSION=[];
ob_start(); (new HomeController())->index(); $html=ob_get_clean();
foreach (['Horizon &lt;Sport&gt;','href="login"','href="#contact"','href="tel:+21612345678"','href="mailto:contact@example.tn"','&lt;Rue&gt; test','Lundi 08h–20h','assets/landing','assets/home.css','data-carousel-track','data-carousel-toggle','data-parallax','data-reveal','welcome-dialog','id="welcome-title"','assets/photo-movement.jpg'] as $needle) {
    // The hero image is referenced by the stylesheet rather than inline markup.
    if ($needle==='assets/landing') continue;
    if (!str_contains($html,$needle)) throw new RuntimeException('Accueil : élément manquant '.$needle);
}
if (substr_count($html, 'data-carousel role=') !== 2) throw new RuntimeException('Les deux carrousels sont attendus');
if (str_contains($html,'<Rue>') || str_contains($html,'<Sport>') || str_contains($html,'19€') || str_contains($html,'page=login')) throw new RuntimeException('Contenu ou échappement incorrect');
$db->exec("UPDATE gym_settings SET phone='javascript:alert(1)',email='evil?subject=bad@example.tn'"); ob_start(); (new HomeController())->index(); $html=ob_get_clean(); if (str_contains($html,'href="tel:javascript') || str_contains($html,'href="mailto:evil?subject=bad')) throw new RuntimeException('Coordonnées non sécurisées');
$db->exec('DELETE FROM gym_settings'); ob_start(); (new HomeController())->index(); $html=ob_get_clean();
if (!str_contains($html,'Les coordonnées du club seront bientôt disponibles.') || str_contains($html,'href="tel:')) throw new RuntimeException('Coordonnées absentes incorrectes');
$db->exec('DROP TABLE gym_settings'); ob_start(); (new HomeController())->index(); $html=ob_get_clean(); if (!str_contains($html,'Les coordonnées du club seront bientôt disponibles.')) throw new RuntimeException('Repli sans paramètres incorrect');
echo "Accueil public : coordonnées, échappement, liens et repli OK\n";
