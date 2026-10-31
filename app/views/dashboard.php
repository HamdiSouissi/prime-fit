<?php
$user = $_SESSION['user']; $role = $user['role'];
$title = 'Tableau de bord'; $subtitle = ''; $section = 'dashboard'; $hideHeading = true;
require __DIR__.'/management_header.php';

$first = explode(' ', $user['name'])[0];
$values = array_map('floatval', $data['values']); $max = max($values) ?: 1; $n = max(1, count($values) - 1);
$points = [];
foreach ($values as $i => $v) { $points[] = round($i * (100 / $n), 2).','.round(36 - ($v / $max) * 30, 2); }
$line = implode(' ', $points); $area = '0,40 '.$line.' 100,40';
$summary = 'Aujourd’hui, '.(int)$data['visits'].' passage(s) accepté(s) et '.dt($data['today']).' encaissés. '
         .((int)$data['expiring'] > 0 ? (int)$data['expiring'].' abonnement(s) expirent d’ici 7 jours.' : 'Aucun abonnement n’expire d’ici 7 jours.');
$colors = ['#FFD400', '#EF4444', '#9CA3AF', '#22C55E'];
?>
<section class="hero" aria-label="Résumé du jour"><div class="hero-copy"><h1>Bonjour, <?= e($first) ?></h1><p class="hero-summary"><?= e($summary) ?></p><div class="hero-actions">
<?php if (in_array($role, ['Admin','Gérant','Réceptionniste','Comptable'], true)): ?><a class="btn btn-primary" href="index.php?page=payments"><i data-lucide="wallet-cards"></i>Enregistrer un paiement</a><?php endif; ?>
<?php if (in_array($role, ['Admin','Gérant','Réceptionniste'], true)): ?><a class="btn btn-outline" href="index.php?page=attendance"><i data-lucide="scan-line"></i>Pointer un membre</a><?php endif; ?>
<a class="btn btn-outline" href="index.php?page=members"><i data-lucide="users-round"></i>Voir les membres</a></div></div>
<div class="hero-panel"><span>Revenus du mois</span><strong><?= e(dt($data['month'])) ?></strong>
<svg class="spark" viewBox="0 0 100 40" preserveAspectRatio="none" role="img" aria-label="Revenus des six derniers mois"><defs><linearGradient id="sparkStroke" x1="0" x2="1"><stop offset="0" stop-color="#FFD400"/><stop offset="1" stop-color="#FFFFFF"/></linearGradient><linearGradient id="sparkFill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#FFD400" stop-opacity=".28"/><stop offset="1" stop-color="#FFD400" stop-opacity="0"/></linearGradient></defs><polygon class="area" points="<?= e($area) ?>"/><polyline class="line" points="<?= e($line) ?>"/></svg>
<div class="spark-labels"><?php foreach ($data['labels'] as $l): ?><span><?= e($l) ?></span><?php endforeach; ?></div></div></section>

<div class="section-heading"><div><h2>Vue d’ensemble</h2><p>Les chiffres clés de votre activité</p></div><span class="live"><span></span> Données en direct</span></div>
<section class="stats">
<article class="stat-card"><div class="stat-top"><span class="stat-icon mint"><i data-lucide="users-round"></i></span><span class="stat-note">Aujourd’hui</span></div><div class="stat-value"><?= (int)$data['active'] ?></div><div class="stat-label">Membres actifs</div><div class="stat-footer"><span class="dot green-dot"></span>Abonnements en cours</div></article>
<article class="stat-card"><div class="stat-top"><span class="stat-icon amber"><i data-lucide="wallet"></i></span><span class="stat-note">Aujourd’hui</span></div><div class="stat-value"><?= e(dt($data['today'])) ?></div><div class="stat-label">Revenus du jour</div><div class="stat-footer">Encaissements de la journée</div></article>
<article class="stat-card"><div class="stat-top"><span class="stat-icon purple"><i data-lucide="chart-no-axes-combined"></i></span><span class="stat-note">Ce mois</span></div><div class="stat-value"><?= e(dt($data['month'])) ?></div><div class="stat-label">Revenus du mois</div><div class="stat-footer">Total des encaissements</div></article>
<article class="stat-card"><div class="stat-top"><span class="stat-icon blue-icon"><i data-lucide="scan-line"></i></span><span class="stat-note">Aujourd’hui</span></div><div class="stat-value"><?= (int)$data['visits'] ?></div><div class="stat-label">Présences du jour</div><div class="stat-footer">Passages enregistrés</div></article>
</section>

<section class="middle-grid"><article class="card chart-card"><div class="card-head"><div><h3>Évolution des revenus</h3><p>Un aperçu de vos encaissements</p></div><span class="pill-outline">6 derniers mois</span></div><div class="chart-summary"><strong><?= e(dt($data['month'])) ?></strong><span>Ce mois-ci</span></div><div class="chart-wrap skeleton"><canvas id="revenueChart" aria-label="Revenus des six derniers mois" role="img"></canvas></div></article>
<article class="card membership-card"><div class="card-head"><div><h3>Abonnements actifs</h3><p>Répartition par formule</p></div></div><div class="donut-wrap skeleton"><canvas id="planChart" aria-label="Répartition des abonnements actifs" role="img"></canvas><div class="donut-center"><strong><?= (int)$data['active'] ?></strong><span>abonnements</span></div></div>
<div class="legend"><?php foreach ($data['plans'] as $i => $plan): ?><div><span><b style="background:<?= e($colors[$i % 4]) ?>"></b><?= e($plan['name']) ?></span><strong><?= (int)$plan['total'] ?></strong></div><?php endforeach; ?></div></article></section>

<section class="bottom-grid"><article class="card table-card"><div class="card-head"><div><h3>Abonnements récents</h3><p>Les dernières adhésions enregistrées</p></div><span class="subtle-label">Derniers ajouts</span></div><div class="table-scroll"><table><thead><tr><th>MEMBRE</th><th>FORMULE</th><th>EXPIRATION</th><th>STATUT</th></tr></thead><tbody>
<?php foreach ($data['recent'] as $row): $expired = $row['end_date'] < date('Y-m-d'); ?><tr><td><div class="member"><div class="avatar <?= e($row['avatar_color']) ?>"><?= e(mb_substr($row['first_name'],0,1).mb_substr($row['last_name'],0,1)) ?></div><div><strong><?= e($row['first_name'].' '.$row['last_name']) ?></strong><small><?= e($row['phone']) ?></small></div></div></td><td><?= e($row['plan']) ?></td><td><?= e(date('d/m/Y',strtotime($row['end_date']))) ?></td><td><span class="status <?= $expired || $row['status'] !== 'active' ? 'expired' : '' ?>"><?= $expired ? 'Expiré' : ($row['status'] === 'active' ? 'Actif' : e($row['status'])) ?></span></td></tr><?php endforeach; ?>
<?php if (!$data['recent']): ?><tr><td colspan="4" class="empty">Aucun abonnement pour le moment.</td></tr><?php endif; ?></tbody></table></div></article>
<article class="card alerts-card"><div class="card-head"><div><h3>À surveiller <span class="alert-count"><?= (int)$data['expiring'] ?></span></h3><p>Abonnements expirant sous 7 jours</p></div><span class="alert-icon"><i data-lucide="bell-ring"></i></span></div><div class="alert-list">
<?php foreach ($data['alerts'] as $row): $days=(int)(new DateTimeImmutable('today'))->diff(new DateTimeImmutable($row['end_date']))->format('%a'); ?><div class="alert-row"><div class="avatar rose"><?= e(mb_substr($row['first_name'],0,1).mb_substr($row['last_name'],0,1)) ?></div><div class="alert-person"><strong><?= e($row['first_name'].' '.$row['last_name']) ?></strong><small><?= e($row['plan']) ?> · <?= e(date('d/m/Y',strtotime($row['end_date']))) ?></small></div><span><?= $days === 0 ? 'Aujourd’hui' : 'J − '.$days ?></span></div><?php endforeach; ?>
<?php if (!$data['alerts']): ?><p class="empty">Tout est à jour. Aucun abonnement n’expire prochainement.</p><?php endif; ?></div><div class="alert-bottom"><i data-lucide="info"></i> Anticipez les renouvellements de vos membres.</div></article></section>

<script>window.dashboardData = <?= json_encode(['labels'=>$data['labels'],'values'=>$data['values'],'planLabels'=>array_column($data['plans'],'name'),'planValues'=>array_map('intval',array_column($data['plans'],'total'))], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php require __DIR__.'/management_footer.php'; ?>