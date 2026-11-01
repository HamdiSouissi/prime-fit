<?php
$user = $_SESSION['user']; $role = $user['role'];
$title = 'Tableau de bord'; $subtitle = ''; $section = 'dashboard'; $hideHeading = true;
require __DIR__.'/management_header.php';

$first = explode(' ', $user['name'])[0];
$canFinance=$data['canFinance']; $canAttendance=$data['canAttendance'];
if ($canFinance) {
    $values=array_map('floatval',$data['values']); $max=max(0,...$values); $min=min(0,...$values); $range=max(1,$max-$min); $n=max(1,count($values)-1); $points=[];
    foreach ($values as $i=>$v) $points[]=round($i*(100/$n),2).','.round(36-(($v-$min)/$range)*30,2);
    $line=implode(' ',$points); $area='0,40 '.$line.' 100,40';
}
$summaryParts=[];
if ($canAttendance) $summaryParts[]=(int)$data['visits'].' passage(s) accepté(s) aujourd’hui';
if ($canFinance) $summaryParts[]=dt($data['today']).' nets aujourd’hui pour les abonnements';
$summaryParts[]=(int)$data['expiring']>0 ? (int)$data['expiring'].' abonnement(s) expirent d’ici 7 jours' : 'Aucun abonnement n’expire d’ici 7 jours';
$summary=implode(' · ',$summaryParts).'.';
$colors = ['#FFD400', '#EF4444', '#9CA3AF', '#22C55E'];
?>
<section class="hero" aria-label="Résumé du jour"><div class="hero-copy"><h1>Bonjour, <?= e($first) ?></h1><p class="hero-summary"><?= e($summary) ?></p><div class="hero-actions">
<?php if (in_array($role, ['Admin','Gérant','Réceptionniste','Comptable'], true)): ?><a class="btn btn-primary" href="payments"><i data-lucide="wallet-cards"></i>Enregistrer un paiement</a><?php endif; ?>
<?php if (in_array($role, ['Admin','Gérant','Réceptionniste'], true)): ?><a class="btn btn-outline" href="attendance"><i data-lucide="scan-line"></i>Pointer un membre</a><?php endif; ?>
<a class="btn btn-outline" href="members"><i data-lucide="users-round"></i>Voir les membres</a>
<a class="btn btn-outline" href="./"><i data-lucide="globe" aria-hidden="true"></i>Voir le site principal</a></div></div>
<?php if ($canFinance): ?><div class="hero-panel"><span>Encaissements nets abonnements · mois</span><strong><?= e(dt($data['month'])) ?></strong>
<svg class="spark" viewBox="0 0 100 40" preserveAspectRatio="none" role="img" aria-label="Encaissements des abonnements sur six mois"><defs><linearGradient id="sparkStroke" x1="0" x2="1"><stop offset="0" stop-color="#FFD400"/><stop offset="1" stop-color="#FFFFFF"/></linearGradient><linearGradient id="sparkFill" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#FFD400" stop-opacity=".28"/><stop offset="1" stop-color="#FFD400" stop-opacity="0"/></linearGradient></defs><polygon class="area" points="<?= e($area) ?>"/><polyline class="line" points="<?= e($line) ?>"/></svg>
<div class="spark-labels"><?php foreach ($data['labels'] as $l): ?><span><?= e($l) ?></span><?php endforeach; ?></div></div><?php else: ?><div class="hero-panel"><span>Membres actifs</span><strong><?= (int)$data['active'] ?></strong><p>Avec un abonnement en cours</p></div><?php endif; ?></section>

<div class="section-heading"><div><h2>Vue d’ensemble</h2><p>Les chiffres clés de votre activité</p></div><span class="live"><span></span> Données en direct</span></div>
<section class="stats dashboard-stats <?= $canFinance && $canAttendance ? '' : 'dashboard-stats-reduced' ?>">
<article class="stat-card"><div class="stat-top"><span class="stat-icon mint"><i data-lucide="users-round"></i></span><span class="stat-note">Aujourd’hui</span></div><div class="stat-value"><?= (int)$data['active'] ?></div><div class="stat-label">Membres actifs</div><div class="stat-footer"><span class="dot green-dot"></span>Abonnements en cours</div></article>
<?php if ($canFinance): ?><article class="stat-card"><div class="stat-top"><span class="stat-icon amber"><i data-lucide="wallet"></i></span><span class="stat-note">Aujourd’hui</span></div><div class="stat-value"><?= e(dt($data['today'])) ?></div><div class="stat-label">Abonnements · jour</div><div class="stat-footer">Encaissements moins remboursements</div></article>
<article class="stat-card"><div class="stat-top"><span class="stat-icon purple"><i data-lucide="chart-no-axes-combined"></i></span><span class="stat-note">Ce mois</span></div><div class="stat-value"><?= e(dt($data['month'])) ?></div><div class="stat-label">Abonnements · mois</div><div class="stat-footer">Encaissements moins remboursements</div></article><?php endif; ?>
<?php if ($canAttendance): ?><article class="stat-card"><div class="stat-top"><span class="stat-icon blue-icon"><i data-lucide="scan-line"></i></span><span class="stat-note">Aujourd’hui</span></div><div class="stat-value"><?= (int)$data['visits'] ?></div><div class="stat-label">Présences du jour</div><div class="stat-footer">Passages acceptés</div></article><?php endif; ?>
</section>

<section class="middle-grid <?= $canFinance ? '' : 'dashboard-middle-single' ?>"><?php if ($canFinance): ?><article class="card chart-card"><div class="card-head"><div><h3>Encaissements nets abonnements</h3><p>Remboursements déduits · hors ventes boutique et dépenses</p></div><span class="pill-outline">6 derniers mois</span></div><div class="chart-summary"><strong><?= e(dt($data['month'])) ?></strong><span>Ce mois-ci</span></div><div class="chart-wrap skeleton"><canvas id="revenueChart" aria-label="Encaissements des abonnements sur six mois" role="img"></canvas></div></article><?php endif; ?>
<article class="card membership-card"><div class="card-head"><div><h3>Abonnements actifs</h3><p>Répartition par formule</p></div></div><div class="donut-wrap skeleton"><canvas id="planChart" aria-label="Répartition des abonnements actifs" role="img"></canvas><div class="donut-center"><strong><?= (int)$data['active'] ?></strong><span>membres</span></div></div>
<div class="legend"><?php foreach ($data['plans'] as $i => $plan): ?><div><span><b style="background:<?= e($colors[$i % 4]) ?>"></b><?= e($plan['name']) ?></span><strong><?= (int)$plan['total'] ?></strong></div><?php endforeach; ?></div></article></section>

<section class="bottom-grid"><article class="card table-card"><div class="card-head"><div><h3>Abonnements récents</h3><p>Les dernières adhésions enregistrées</p></div><span class="subtle-label">Derniers ajouts</span></div><div class="table-scroll"><table><thead><tr><th>MEMBRE</th><th>FORMULE</th><th>EXPIRATION</th><th>STATUT</th></tr></thead><tbody>
<?php foreach ($data['recent'] as $row): $display=$row['status']==='active' && $row['end_date']<date('Y-m-d') ? 'Expiré' : ($row['status']==='active' && $row['start_date']>date('Y-m-d') ? 'À venir' : (['active'=>'Actif','frozen'=>'Gelé','cancelled'=>'Annulé'][$row['status']] ?? $row['status'])); ?><tr><td><div class="member"><div class="avatar <?= e($row['avatar_color']) ?>"><?= e(mb_substr($row['first_name'],0,1).mb_substr($row['last_name'],0,1)) ?></div><div><strong><?= e($row['first_name'].' '.$row['last_name']) ?></strong><small><?= e($row['phone']) ?></small></div></div></td><td><?= e($row['plan']) ?></td><td><?= e(date('d/m/Y',strtotime($row['end_date']))) ?></td><td><span class="status <?= $display==='Actif' ? '' : (in_array($display,['À venir','Gelé'],true) ? 'waiting-status' : 'expired') ?>"><?= e($display) ?></span></td></tr><?php endforeach; ?>
<?php if (!$data['recent']): ?><tr><td colspan="4" class="empty">Aucun abonnement pour le moment.</td></tr><?php endif; ?></tbody></table></div></article>
<article class="card alerts-card"><div class="card-head"><div><h3>À surveiller <span class="alert-count"><?= (int)$data['expiring'] ?></span></h3><p>Abonnements expirant sous 7 jours</p></div><span class="alert-icon"><i data-lucide="bell-ring"></i></span></div><div class="alert-list">
<?php foreach ($data['alerts'] as $row): $days=(int)(new DateTimeImmutable('today'))->diff(new DateTimeImmutable($row['end_date']))->format('%a'); ?><div class="alert-row"><div class="avatar rose"><?= e(mb_substr($row['first_name'],0,1).mb_substr($row['last_name'],0,1)) ?></div><div class="alert-person"><strong><?= e($row['first_name'].' '.$row['last_name']) ?></strong><small><?= e($row['plan']) ?> · <?= e(date('d/m/Y',strtotime($row['end_date']))) ?></small></div><span><?= $days === 0 ? 'Aujourd’hui' : 'J − '.$days ?></span></div><?php endforeach; ?>
<?php if (!$data['alerts']): ?><p class="empty">Tout est à jour. Aucun abonnement n’expire prochainement.</p><?php endif; ?></div><div class="alert-bottom"><i data-lucide="info"></i> Anticipez les renouvellements de vos membres.</div></article></section>

<?php $chartData=['planLabels'=>array_column($data['plans'],'name'),'planValues'=>array_map('intval',array_column($data['plans'],'total'))]; if ($canFinance) { $chartData['labels']=$data['labels']; $chartData['values']=$data['values']; } ?>
<script>window.dashboardData = <?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<?php require __DIR__.'/management_footer.php'; ?>