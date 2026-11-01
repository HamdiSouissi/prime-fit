<?php
// Variables attendues : $title, $subtitle, $section. Optionnelles : $flash, $error, $hideHeading, $data (dashboard).
$flash = $flash ?? ''; $error = $error ?? ''; $hideHeading = $hideHeading ?? false;
$__role = $_SESSION['user']['role']; $__name = $_SESSION['user']['name'];
require_once __DIR__.'/../services/AccountInitials.php'; $__initials=AccountInitials::fromName($__name);
$__weekday = ['Sunday'=>'dimanche','Monday'=>'lundi','Tuesday'=>'mardi','Wednesday'=>'mercredi','Thursday'=>'jeudi','Friday'=>'vendredi','Saturday'=>'samedi'];
$__months = [1=>'janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
$__dateLabel = ucfirst($__weekday[date('l')]).' '.date('j').' '.$__months[(int)date('n')].' '.date('Y');
// [section, page, icône Lucide, libellé, rôles autorisés (null = tous)] ; une chaîne = titre de groupe.
$__items = [
    ['dashboard','dashbord','layout-dashboard','Tableau de bord',null],
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
    ['settings','settings','settings-2','Paramètres',['Admin','Gérant','Comptable']],
    ['users','users','users-round','Comptes du personnel',['Admin']],
    ['activity','activity','clipboard-list','Journal d’activité',['Admin']],
    ['removals','removals','trash-2','Suppressions protégées',['Admin']],
    ['account','account','shield-check','Mon compte',null],
];
$__needsCharts = in_array($section, ['fitness','dashboard','reports'], true);
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#000000"><title><?= e($title) ?> — <?= e(gym_name()) ?></title><link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
<script>try{var d=document.documentElement;if(localStorage.getItem('prime-theme')==='light')d.classList.add('light');if(localStorage.getItem('prime-compact')==='1')d.classList.add('compact');}catch(e){}</script>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600;700&family=Barlow+Condensed:wght@600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/style.css"><script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js" defer></script><?php if ($__needsCharts): ?><script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js" defer></script><?php endif; ?><script src="assets/notifications.js" defer></script><script src="assets/app.js" defer></script></head><body data-app-name="<?= e(gym_name()) ?>"<?= !empty($bodyClass) ? ' class="'.e($bodyClass).'"' : '' ?>>
<a class="skip-link" href="#main">Aller au contenu</a>
<div class="app"><aside class="sidebar" id="sidebar" aria-label="Navigation principale"><a class="brand" href="dashbord"><span class="brand-icon">✳</span><span class="brand-text"><?= gym_brand_markup() ?></span></a><div class="side-label">Menu principal</div><nav class="navigation">
<?php foreach ($__items as $__it): ?>
<?php if (is_string($__it)): ?><div class="side-label"><?= e($__it) ?></div><?php continue; endif; ?>
<?php [$__sec,$__page,$__icon,$__label,$__roles] = $__it; if ($__roles !== null && !in_array($__role, $__roles, true)) continue; $__active = $section === $__sec; ?>
<a class="nav-item<?= $__active ? ' active' : '' ?>" href="<?= e($__page) ?>" title="<?= e($__label) ?>"<?= $__active ? ' aria-current="page"' : '' ?>><i data-lucide="<?= e($__icon) ?>"></i><span><?= e($__label) ?></span></a>
<?php endforeach; ?>
</nav>
<div class="side-profile"><div class="avatar gold"><?= e(mb_substr($__name,0,1)) ?></div><div><strong><?= e($__name) ?></strong><small><?= e($__role) ?></small></div><form method="post" action="logout"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><button title="Se déconnecter" aria-label="Se déconnecter"><i data-lucide="log-out"></i></button></form></div></aside>
<div class="backdrop" id="backdrop"></div>
<div class="main-wrap"><header class="topbar"><button class="icon-button menu-toggle" id="menuToggle" aria-label="Afficher ou réduire le menu" aria-controls="sidebar" aria-expanded="true"><i data-lucide="panel-left"></i></button><div class="breadcrumb">Espace de gestion <span>/</span> <strong><?= e($title) ?></strong></div>
<div class="quick-search" role="search"><i data-lucide="search"></i><input id="quickSearch" type="text" placeholder="Aller à une page…" aria-label="Rechercher une page" autocomplete="off"><kbd>Ctrl K</kbd><div class="quick-results" id="quickResults" hidden></div></div>
<div class="top-actions"><div class="top-date"><i data-lucide="calendar-days"></i> <?= e($__dateLabel) ?></div>
<?php if (in_array($__role, ['Admin','Gérant','Réceptionniste','Comptable'], true)): ?><a class="quick-action" href="payments"><i data-lucide="plus"></i><span>Paiement</span></a><?php endif; ?>
<?php if (in_array($__role, ['Admin','Gérant','Réceptionniste'], true)): ?><a class="quick-action" href="attendance"><i data-lucide="scan-line"></i><span>Pointage</span></a><?php endif; ?>
<?php if (isset($data['expiring']) && in_array($__role, ['Admin','Gérant','Réceptionniste'], true)): ?><a class="notification" href="marketing" title="<?= (int)$data['expiring'] ?> abonnement(s) expirent bientôt" aria-label="<?= (int)$data['expiring'] ?> abonnement(s) expirent bientôt"><i data-lucide="bell"></i><?php if ($data['expiring']): ?><b></b><?php endif; ?></a><?php endif; ?>
<a class="icon-button site-home-link" href="./" title="Voir le site principal" aria-label="Voir le site principal"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18"></path></svg></a>
<button class="icon-button" id="themeToggle" aria-label="Changer de thème"><i data-lucide="sun" class="i-sun"></i><i data-lucide="moon" class="i-moon"></i></button>
<a class="top-profile account-link" href="account" aria-label="Modifier les informations de mon compte" title="Mon compte"><span class="avatar gold"><?= e($__initials) ?></span><span class="top-profile-details"><strong><?= e($__name) ?></strong><small><?= e($__role) ?></small></span></a><form class="header-logout" method="post" action="logout"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><button class="icon-button" type="submit" title="Se déconnecter" aria-label="Se déconnecter"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v2M14 16l4-4-4-4M18 12H8"/></svg></button></form></div></header>
<main class="content" id="main">
<?php if (!$hideHeading): ?><div class="heading"><div><h1><?= e($title) ?></h1><p><?= e($subtitle) ?></p></div></div><?php endif; ?>
<?php if ($flash): ?><div class="notice" role="status" data-notification="<?= mb_stripos($flash, 'non envoyé') !== false ? 'warning' : 'success' ?>"><?= e($flash) ?></div><?php endif; ?><?php if ($error): ?><div class="error" role="alert" data-notification="error"<?= !empty($unknownCard) ? ' data-notification-kind="access" data-notification-title="Carte non reconnue"' : '' ?>><?= e($error) ?></div><?php endif; ?>