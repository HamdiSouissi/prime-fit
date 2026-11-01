<?php
$title='Modifier un cours'; $subtitle='Mettez à jour le planning et les places disponibles.'; $section='courses';
$keep=$error && !str_starts_with($error,'Le cours a changé');
$field=static function(string $key,$default) use ($keep) { return $keep && is_string($_POST[$key] ?? null) ? $_POST[$key] : (string)$default; };
$editable=$course['status']==='scheduled' && $course['starts_at']>date('Y-m-d H:i:s');
require __DIR__.'/management_header.php';
?>
<section class="card form-card account-card"><div class="card-head"><div><h3><?= e($course['title']) ?></h3><p><?= $confirmed ?> confirmé(s) · <?= $waiting ?> en attente</p></div><a class="text-link" href="index.php?page=courses&amp;course=<?= (int)$course['id'] ?>">← Inscriptions</a></div>
<?php if (!$editable): ?><p class="notice" role="status">Ce cours est annulé ou a déjà commencé ; il ne peut plus être modifié.</p><?php else: ?>
<?php if ($error && !$keep): ?><p class="form-hint">Les champs affichent les valeurs actuelles. Vérifiez-les avant de réessayer.</p><?php endif; ?>
<form method="post" action="index.php?page=course_edit&amp;id=<?= (int)$course['id'] ?>" class="form-grid account-form"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="version" value="<?= e($version) ?>">
<label>Nom du cours *<input name="title" maxlength="120" required value="<?= e($field('title',$course['title'])) ?>"></label><label>Salle *<select name="room_id" required><option value="">Sélectionner</option><?php foreach ($rooms as $room): ?><option value="<?= (int)$room['id'] ?>" <?= $field('room_id',$course['room_id'])===(string)$room['id'] ? 'selected' : '' ?>><?= e($room['name']) ?></option><?php endforeach; ?></select></label>
<label>Coach<select name="coach_id"><option value="">Non attribué</option><?php foreach ($coaches as $coach): ?><option value="<?= (int)$coach['id'] ?>" <?= $field('coach_id',$course['coach_id'] ?? '')===(string)$coach['id'] ? 'selected' : '' ?>><?= e($coach['name']) ?><?= $coach['is_active'] ? '' : ' · inactif' ?></option><?php endforeach; ?></select></label><label>Capacité *<input type="number" name="capacity" min="1" max="1000" required value="<?= e($field('capacity',$course['capacity'])) ?>"></label>
<label>Début *<input type="datetime-local" name="starts_at" required value="<?= e($field('starts_at',str_replace(' ','T',substr($course['starts_at'],0,16)))) ?>"></label><label>Fin *<input type="datetime-local" name="ends_at" required value="<?= e($field('ends_at',str_replace(' ','T',substr($course['ends_at'],0,16)))) ?>"></label>
<p class="form-hint span-all">Les horaires ne peuvent pas changer si des membres sont inscrits ou en attente. La capacité ne peut pas descendre sous le nombre de réservations confirmées. Si elle augmente, les membres éligibles sans séance privée conflictuelle sont confirmés dans l’ordre d’inscription. Aucun message automatique n’est envoyé : prévenez les participants. Un coach inactif doit être remplacé ou retiré.</p><div class="form-actions"><button class="primary" type="submit">Enregistrer le cours</button></div></form><?php endif; ?></section>
<?php require __DIR__.'/management_footer.php'; ?>
