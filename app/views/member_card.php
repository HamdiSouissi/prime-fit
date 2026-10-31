<?php
$title = 'Carte adhérent';
$subtitle = 'Une carte personnelle pour le pointage du membre.';
$section = 'members';
$bodyClass = 'member-card-page';
require __DIR__.'/management_header.php';
?>
<div class="member-card-actions no-print">
  <a class="text-link" href="index.php?page=members">← Retour aux membres</a>
  <?php if ($member['qr_token']): ?>
    <button type="button" class="primary" id="print-card">Imprimer / enregistrer en PDF</button>
    <button type="button" class="scanner-stop" id="download-qr">Télécharger le QR</button>
  <?php endif; ?>
</div>
<div class="member-card-layout">
  <article class="member-card-preview" aria-label="Carte adhérent">
    <div class="member-card-brand">PRIME<span>FIT</span><small>CARTE D’ADHÉRENT</small></div>
    <p class="member-card-label">MEMBRE</p>
    <h2><?= e($member['first_name'].' '.$member['last_name']) ?></h2>
    <p class="member-card-number">N° <?= e($member['card_number'] ?: 'Membre #'.$member['id']) ?></p>
    <?php if ($member['qr_token']): ?>
      <div class="qr-shell"><div id="member-qr" data-qr="<?= e('PF1:'.$member['qr_token']) ?>" aria-label="Code QR personnel du membre"></div></div>
      <p class="member-card-hint">Présentez ce code à l’accueil pour votre pointage.</p>
    <?php else: ?>
      <p class="member-card-hint">Ce membre ne possède pas encore de carte QR.</p>
    <?php endif; ?>
  </article>
  <aside class="card member-card-side no-print">
    <h3>Votre carte QR</h3>
    <p>Ce QR est personnel : une copie peut être utilisée pour pointer. En cas de perte, renouvelez-le et imprimez la nouvelle carte.</p>
    <form method="post" action="index.php?page=member_card">
      <input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
      <input type="hidden" name="id" value="<?= (int)$member['id'] ?>">
      <button class="primary" type="submit" <?= $member['qr_token'] ? 'data-confirm="1"' : '' ?>><?= $member['qr_token'] ? 'Renouveler le QR' : 'Générer le QR de ce membre' ?></button>
    </form>
    <p id="card-error" class="error" role="alert" hidden></p>
  </aside>
</div>
<script src="assets/vendor/qrcode.min.js" defer></script>
<script src="assets/member-card.js" defer></script>
<?php require __DIR__.'/management_footer.php'; ?>
