<?php
/* Shows a list of people in their year to elders with some basic info.
 * Strictly speaking not necessary - they already have that info - but it's
 * nice to have. */

require(__DIR__ . '/../src/common.php');

$sessToken = MySession::requireLogin();

html_header('iiet.pl');

$elderStmt = Database::getInstance()->runStmt('
	SELECT "group"
	FROM usergroups
	WHERE user = ? AND elder = 1
', [$sessToken->getUserID()]);
$isElder = false;
while (([$group] = $elderStmt->fetch())) {
	$isElder = true; ?>
	<h3><?=hsc($group)?></h3>
	<table class="table table-striped table-hover">
		<thead><tr>
			<th>Imię i nazwisko</th>
			<th>Numer indeksu</th>
			<th>Nazwa użytkownika</th>
		</tr></thead>
		<tbody>
	<?php
	$groupStmt = Database::getInstance()->runStmt('
		SELECT fullname, transcript_id, username
		FROM users
		JOIN usergroups on users.id == usergroups.user
		WHERE usergroups."group" = ?
	', [$group]);
	while (([$fullname, $transcript, $username] = $groupStmt->fetch())) { ?>
		<tr>
			<td><?= hsc($fullname); ?></td>
			<td><?= hsc($transcript); ?></td>
			<td><?= hsc($username); ?></td>
		</tr>
	<?php } ?>
		</tbody>
	</table>
	<?php
}

if (!$isElder) { ?>
<div class="alert alert-danger"> Nie jesteś starost(k)ą. </div>
<?php }
