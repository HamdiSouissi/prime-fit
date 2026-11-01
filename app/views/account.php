<?php $title='Mon compte'; $subtitle='Protégez votre accès à l’espace de gestion.'; $section='account'; require __DIR__.'/management_header.php'; ?>
<section class="card form-card account-card"><div class="card-head"><div><h3>Changer mon mot de passe</h3><p>Compte : <?= e($_SESSION['user']['email']) ?> · <?= e($_SESSION['user']['role']) ?></p></div></div>
<form method="post" action="account" class="form-grid account-form"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>">
<label>Mot de passe actuel *<input name="current_password" type="password" autocomplete="current-password" required></label>
<label>Nouveau mot de passe *<input name="new_password" type="password" autocomplete="new-password" required></label>
<label>Confirmer le nouveau mot de passe *<input name="confirmation" type="password" autocomplete="new-password" required></label>
<p class="form-hint">Choisissez une phrase de passe unique de 12 à 72 octets (les caractères accentués peuvent occuper plusieurs octets). Les autres sessions seront déconnectées lors de leur prochaine requête.</p><div class="form-actions"><button type="submit" class="primary">Mettre à jour le mot de passe</button></div></form></section>
<?php require __DIR__.'/management_footer.php'; ?>
