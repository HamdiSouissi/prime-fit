<?php
// Variables attendues : $title, $subtitle, $section. Optionnelles : $flash, $error, $hideHeading, $data (dashboard).
$flash = $flash ?? ''; $error = $error ?? ''; $hideHeading = $hideHeading ?? false;
$__role = $_SESSION['user']['role']; $__name = $_SESSION['user']['name'];
$__weekday = ['Sunday'=>'dimanche','Monday'=>'lundi','Tuesday'=>'mardi','Wednesday'=>'mercredi','Thursday'=>'jeudi','Friday'=>'vendredi','Saturday'=>'samedi'];
$__months = [1=>'janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
$__dateLabel = ucfirst($__weekday[date('l')]).' '.date('j').' '.$__months[(int)date('n')].' '.date('Y');
// [section, page, icône Lucide, libellé, rôles autorisés (null = tous)] ; une chaîne = titre de groupe.
$__items = [
    ['dashboard','dashboard','layout-dashboard','Tableau de bord',null],
    ['members','members','users-round','Membres',null],
    ['subscriptions','subscriptions','credit-card','Abonnements',null],
    ['payments','payments','wallet-cards','Paiements',['Admin','Gérant','Réceptionniste','Comptable']],
    ['attendance','attendance','scan-line','Accès & présences',['Admin','Gérant','Réceptionniste','Coach']],
    ['courses','courses','calendar-days','Cours collectifs',['Admin','Gérant','Réceptionniste','Coach']],
    ['coaches','coaches','dumbbell','Coachs',null],
    ['fitness','fitness','activity','Suivi sportif',['Admin','Gérant','Coach']],
    'Gestion',
    ['shop','shop','shopping-bag','Boutique',['Admin','Gérant','Réceptionniste','Comptable']],
    ['equipment','equipment','wrench','Équipements',null],
    ['finance','finance','receipt','Dépenses & trésorerie',['Admin','Gérant','Comptable']],
    ['marketing','marketing','megaphone','Marketing',['Admin','Gérant','Réceptionniste']],
    ['reports','reports','chart-no-axes-combined','Rapports',['Admin','Gérant','Comptable']],
];
$__needsCharts = in_array($section, ['fitness','dashboard','reports'], true);
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#000000"><title><?= e($title) ?> — Horizon Sportif de Bir El Hafey</title>
<script>try{var d=document.documentElement;if(localStorage.getItem('prime-theme')==='light')d.classList.add('light');if(localStorage.getItem('prime-compact')==='1')d.classList.add('compact');}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/style.css"><script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script><?php if ($__needsCharts): ?><script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" defer></script><?php endif; ?><script src="assets/notifications.js" defer></script><script src="assets/app.js" defer></script></head><body<?= !empty($bodyClass) ? ' class="'.e($bodyClass).'"' : '' ?>>
<a class="skip-link" href="#main">Aller au contenu</a>
<div class="app"><aside class="sidebar" id="sidebar" aria-label="Navigation principale"><a class="brand" href="index.php"><span class="brand-icon">✳</span><span class="brand-text">HORIZON <span class="brand-gold">SPORTIF</span><small>DE BIR EL HAFEY</small></span></a><div class="side-label">Menu principal</div><nav class="navigation">
<?php foreach ($__items as $__it): ?>
<?php if (is_string($__it)): ?><div class="side-label"><?= e($__it) ?></div><?php continue; endif; ?>
<?php [$__sec,$__page,$__icon,$__label,$__roles] = $__it; if ($__roles !== null && !in_array($__role, $__roles, true)) continue; $__active = $section === $__sec; ?>
<a class="nav-item<?= $__active ? ' active' : '' ?>" href="index.php?page=<?= e($__page) ?>" title="<?= e($__label) ?>"<?= $__active ? ' aria-current="page"' : '' ?>><i data-lucide="<?= e($__icon) ?>"></i><span><?= e($__label) ?></span></a>
<?php endforeach; ?>
<span class="nav-item disabled" title="Bientôt disponible"><i data-lucide="settings-2"></i><span>Paramètres</span></span></nav>
<div class="side-profile"><div class="avatar gold"><?= e(mb_substr($__name,0,1)) ?></div><div><strong><?= e($__name) ?></strong><small><?= e($__role) ?></small></div><form method="post" action="index.php?page=logout"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><button title="Se déconnecter" aria-label="Se déconnecter"><i data-lucide="log-out"></i></button></form></div></aside>
<div class="backdrop" id="backdrop"></div>
<div class="main-wrap"><header class="topbar"><button class="icon-button menu-toggle" id="menuToggle" aria-label="Afficher ou réduire le menu" aria-controls="sidebar" aria-expanded="true"><i data-lucide="panel-left"></i></button><div class="breadcrumb">Espace de gestion <span>/</span> <strong><?= e($title) ?></strong></div>
<div class="quick-search" role="search"><i data-lucide="search"></i><input id="quickSearch" type="text" placeholder="Aller à une page…" aria-label="Rechercher une page" autocomplete="off"><kbd>Ctrl K</kbd><div class="quick-results" id="quickResults" hidden></div></div>
<div class="top-actions"><div class="top-date"><i data-lucide="calendar-days"></i> <?= e($__dateLabel) ?></div>
<?php if (in_array($__role, ['Admin','Gérant','Réceptionniste','Comptable'], true)): ?><a class="quick-action" href="index.php?page=payments"><i data-lucide="plus"></i><span>Paiement</span></a><?php endif; ?>
<?php if (in_array($__role, ['Admin','Gérant','Réceptionniste'], true)): ?><a class="quick-action" href="index.php?page=attendance"><i data-lucide="scan-line"></i><span>Pointage</span></a><?php endif; ?>
<?php if (isset($data['expiring'])): ?><a class="notification" href="index.php?page=marketing" title="<?= (int)$data['expiring'] ?> abonnement(s) expirent bientôt" aria-label="<?= (int)$data['expiring'] ?> abonnement(s) expirent bientôt"><i data-lucide="bell"></i><?php if ($data['expiring']): ?><b></b><?php endif; ?></a><?php endif; ?>
<button class="icon-button" id="themeToggle" aria-label="Changer de thème"><i data-lucide="sun" class="i-sun"></i><i data-lucide="moon" class="i-moon"></i></button>
<div class="top-profile"><div class="avatar gold"><?= e(mb_substr($__name,0,1)) ?></div><div><strong><?= e($__name) ?></strong><small><?= e($__role) ?></small></div></div></div></header>
<main class="content" id="main">
<?php if (!$hideHeading): ?><div class="heading"><div><h1><?= e($title) ?></h1><p><?= e($subtitle) ?></p></div></div><?php endif; ?>
<?php if ($flash): ?><div class="notice" role="status" data-notification="<?= mb_stripos($flash, 'non envoyé') !== false ? 'warning' : 'success' ?>"><?= e($flash) ?></div><?php endif; ?><?php if ($error): ?><div class="error" role="alert" data-notification="error"<?= !empty($unknownCard) ? ' data-notification-kind="access" data-notification-title="Carte non reconnue"' : '' ?>><?= e($error) ?></div><?php endif; ?>