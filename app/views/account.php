<?php $title='Mon compte'; $subtitle='Gérez vos coordonnées et protégez votre accès.'; $section='account'; require __DIR__.'/management_header.php'; ?>
<section class="card form-card account-card" id="coordonnees"><div class="card-head"><div><h3>Mes informations</h3><p>Modifiez votre nom et vos coordonnées de connexion.</p></div></div>
<?php if (!$profileAvailable): ?><p class="form-hint">Coordonnées indisponibles. Vérifiez la connexion à la base et, si nécessaire, après sauvegarde appliquez la migration <code>database/user_contact_phone.sql</code> à votre base, puis rechargez la page.</p><?php else: ?>
<form method="post" action="account#coordonnees" class="form-grid account-form"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="profile"><input type="hidden" name="version" value="<?= e(AccountProfileService::version($profile)) ?>">
<label>Nom et prénom *<input name="name" type="text" autocomplete="name" maxlength="120" required value="<?= e($profile['name']) ?>"></label>
<label>Adresse e-mail *<input name="email" type="email" autocomplete="email" maxlength="190" required value="<?= e($profile['email']) ?>"></label>
<label>Téléphone du compte<input name="phone" type="tel" autocomplete="tel" maxlength="30" placeholder="+216 XX XXX XXX" value="<?= e($profile['phone'] ?? '') ?>"></label>
<label>Mot de passe actuel *<input name="current_password" type="password" autocomplete="current-password" required></label>
<p class="form-hint">Votre e-mail sert à la connexion. Confirmez votre mot de passe pour enregistrer les changements. Le téléphone du compte est distinct du téléphone professionnel du profil coach.</p><div class="form-actions"><button type="submit" class="primary">Enregistrer mes informations</button></div></form><?php endif; ?></section>
<section class="card form-card account-card"><div class="card-head"><div><h3>Changer mon mot de passe</h3><p>Compte : <?= e($_SESSION['user']['email']) ?> · <?= e($_SESSION['user']['role']) ?></p></div></div>
<form method="post" action="account" class="form-grid account-form"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="password">
<label>Mot de passe actuel *<input name="current_password" type="password" autocomplete="current-password" required></label>
<label>Nouveau mot de passe *<input name="new_password" type="password" autocomplete="new-password" required></label>
<label>Confirmer le nouveau mot de passe *<input name="confirmation" type="password" autocomplete="new-password" required></label>
<p class="form-hint">Choisissez une phrase de passe unique de 12 à 72 octets (les caractères accentués peuvent occuper plusieurs octets). Les autres sessions seront déconnectées lors de leur prochaine requête.</p><div class="form-actions"><button type="submit" class="primary">Mettre à jour le mot de passe</button></div></form></section>
<?php require __DIR__.'/management_footer.php'; ?>
