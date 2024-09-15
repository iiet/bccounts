<?php
/* Shows a list of people in their year to elders with some basic info.
 * Strictly speaking not necessary - they already have that info - but it's
 * nice to have. */

require(__DIR__ . '/../src/common.php');

$sessToken = MySession::requireLogin();

html_header('iiet.pl');

// TODO don't show this to people who aren't logged in
?>
<div class="d-md-none">
<p>
Jesteś na zbyt małym ekranie by cała tabelka się zmieściła,
więc ukryliśmy wewnętrzny ID.
Jeśli jesteś na telefonie,
możesz włączyć "Desktop mode"... czy jakkolwiek to się nazywa po polsku.
Jeśli jesteś na komputerze,
kup większy monitor.
</p>
</div>
<?php

$elderStmt = Database::getInstance()->runStmt('
	SELECT "group"
	FROM usergroups
	WHERE user = ? AND elder = 1
', [$sessToken->getUserID()]);
$isElder = false;
while (([$group] = $elderStmt->fetch())) {
	$isElder = true; ?>
	<h3><?=hsc($group)?></h3>
	<table class="table table-striped table-hover mb-5">
		<thead><tr>
			<th class="text-end">Indeks</th>
			<th>Imię i nazwisko</th>
			<th>Login</th>
			<th class="text-end d-none d-md-table-cell">Wewnętrzny ID</th>
		</tr></thead>
		<tbody>
	<?php
	$groupStmt = Database::getInstance()->runStmt('
		SELECT fullname, transcript_id, username, legacy_id
		FROM users
		JOIN usergroups on users.id == usergroups.user
		WHERE usergroups."group" = ?
	', [$group]);
	// TODO show the fallback ID if applicable.
	// benchmark if going through Database::getInstance()->getUser is slower
	while (([$fullname, $transcript, $username, $lid] = $groupStmt->fetch())) { ?>
		<tr>
			<td class="text-end"><?= hsc($transcript); ?></td>
			<td><?= hsc($fullname); ?></td>
			<td><?= hsc($username); ?></td>
			<td class="font-monospace text-end d-none d-md-table-cell"><?= hsc($lid); ?></td>
		</tr>
	<?php } ?>
		</tbody>
	</table>
<?php }

if (!$isElder) { ?>
<div class="alert alert-danger"> Nie jesteś starost(k)ą. </div>
<?php } else {
// Let elders see all other users with elder privileges.
// This doesn't handle elders of multiple groups too well, but it's good enough.

$stmt = Database::getInstance()->runStmt('
	SELECT users.username, users.fullname, usergroups."group"
	FROM usergroups
	JOIN users ON usergroups.user = users.id
	WHERE elder=1
', []);
?>
	<h3>Użytkownicy z uprawnieniami starosty:</h3>
	<table class="table table-striped table-hover">
		<thead><tr>
			<th>Grupa</th>
			<th>Imię i nazwisko</th>
			<th>Login</th>
		</tr></thead>
		<tbody>
<?php
while (([$username, $fullname, $group] = $stmt->fetch())) { ?>
		<tr>
			<td><?= hsc($group); ?></td>
			<td><?= hsc($fullname); ?></td>
			<td><?= hsc($username); ?></td>
		</tr>
<?php } ?>
		</tbody>
	</table>
<?php }

html_footer();
