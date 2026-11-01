<?php
declare(strict_types=1);
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec("CREATE TABLE gym_settings (id INTEGER PRIMARY KEY,phone TEXT,email TEXT,address TEXT,opening_hours TEXT); INSERT INTO gym_settings VALUES (1,'+216 12 345 678','contact@example.tn','<Rue> test','Lundi 08h–20h\nMardi 08h–20h')");
function db(): PDO { global $db; return $db; } function e($v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); } function gym_name(): string { return 'Horizon <Sport>'; }
$db->exec("CREATE TABLE courses (id INTEGER PRIMARY KEY, title TEXT, starts_at TEXT, status TEXT)");
$stmt=$db->prepare('INSERT INTO courses (title,starts_at,status) VALUES (?,?,?)');
$stmt->execute(['Yoga <doux>',date('Y-m-d H:i:s',time()+86400),'scheduled']);
$stmt->execute(['Cours annulé',date('Y-m-d H:i:s',time()+86400),'cancelled']);
$stmt->execute(['Cours passé',date('Y-m-d H:i:s',time()-86400),'scheduled']);
require __DIR__.'/../app/controllers/HomeController.php'; $_SERVER['REQUEST_METHOD']='GET'; $_SESSION=[];
ob_start(); (new HomeController())->index(); $html=ob_get_clean();
foreach (['Horizon &lt;Sport&gt;','href="login"','href="#contact"','href="tel:+21612345678"','href="mailto:contact@example.tn"','&lt;Rue&gt; test','Lundi 08h–20h','assets/landing','assets/home.css','data-carousel-track','data-carousel-toggle','data-parallax','data-reveal','welcome-dialog','id="welcome-title"','assets/photo-movement.jpg','Yoga &lt;doux&gt;','id="disciplines"','data-hero-next'] as $needle) {
    // The hero image is referenced by the stylesheet rather than inline markup.
    if ($needle==='assets/landing') continue;
    if (!str_contains($html,$needle)) throw new RuntimeException('Accueil : élément manquant '.$needle);
}
if (substr_count($html, 'class="discipline-media"') !== 12 || !str_contains($html, 'assets/discipline-boxing.jpg')) throw new RuntimeException('Images des disciplines manquantes');
if (substr_count($html, 'data-hero-message') !== 4 || !str_contains($html, 'hero-slide-four') || !str_contains($html, 'PILATES')) throw new RuntimeException('Bannière ou disciplines incomplètes');
if (substr_count($html, 'data-carousel role=') !== 2) throw new RuntimeException('Les deux carrousels sont attendus');
if (str_contains($html,'Cours annulé') || str_contains($html,'Cours passé') || str_contains($html,'Yoga <doux>')) throw new RuntimeException('Cours publics incorrects');
if (str_contains($html,'<Rue>') || str_contains($html,'<Sport>') || str_contains($html,'19€') || str_contains($html,'page=login')) throw new RuntimeException('Contenu ou échappement incorrect');
$db->exec("UPDATE gym_settings SET phone='javascript:alert(1)',email='evil?subject=bad@example.tn'"); ob_start(); (new HomeController())->index(); $html=ob_get_clean(); if (str_contains($html,'href="tel:javascript') || str_contains($html,'href="mailto:evil?subject=bad')) throw new RuntimeException('Coordonnées non sécurisées');
$db->exec('DELETE FROM gym_settings'); ob_start(); (new HomeController())->index(); $html=ob_get_clean();
if (!str_contains($html,'Les coordonnées du club seront bientôt disponibles.') || str_contains($html,'href="tel:')) throw new RuntimeException('Coordonnées absentes incorrectes');
$db->exec('DROP TABLE gym_settings'); ob_start(); (new HomeController())->index(); $html=ob_get_clean(); if (!str_contains($html,'Les coordonnées du club seront bientôt disponibles.')) throw new RuntimeException('Repli sans paramètres incorrect');
echo "Accueil public : coordonnées, échappement, liens et repli OK\n";
