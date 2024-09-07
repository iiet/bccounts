<?php
require(__DIR__ . '/../src/common.php');

$sessToken = MySession::requireLogin();
$userinfo = Database::getInstance()->getUser($sessToken->getUserID());
$groups = Database::getInstance()->getGroups($sessToken->getUserID());
assert($userinfo !== null && $groups !== null);

html_header('iiet.pl');
?>
<div class="w-100" style="max-width: 400px;">
<div class="card w-100 my-4">
	<div class="card-header">Nasze serwisy</div>
	<ul class="list-group list-group-flush">
<?php foreach ($conf['frontservices'] as $k => $v) { ?>
		<li class="list-group-item"><a href="<?=hsc($v)?>"><?=hsc($k)?></a></li>
<?php } ?>
	</ul>
</div>
<!-- I'm using <details> to hide sensitive data by default.
     The "standard" way to do this in Bootstrap would be an Accordion,
     but that requires Javascript, which is stupid.
     This is a hack, but it works surprisingly well. -->
<details class="card w-100 my-4">
	<summary class="card-header user-select-none">Twoje dane</summary>
	<table class="table">
<?php
$data = array(
	'fullname' => '',
	'email' => 'Email:',
	'start_year' => 'Rocznik:',
	'transcript_id' => 'Indeks:',
);
foreach ($data as $k => $name) {
?>
		<tr>
			<th class="text-end"><?= hsc($name) ?></th>
			<td><?= hsc($userinfo[$k]) ?></td>
		</tr>
<?php } ?>
		<tr>
			<th class="text-end">Grupy:</th>
			<td>
<?php foreach ($groups as $group) {
	echo '<span class="badge bg-dark me-1">' . hsc($group) . '</span>';
} ?>
			</td>
		</tr>
	</table>
	<div class="card-body pt-0">
		<a class="btn btn-outline-primary" href="/chpass.php">Zmień hasło</a>
	</div>
</details>
<details class="card w-100 my-4">
	<summary class="card-header user-select-none">Aktywne sesje</summary>
	<ul class="list-group list-group-flush">
<?php
$stmt = Database::getInstance()->runStmt('
	SELECT id, ctime, ip
	FROM sessions
	WHERE user = ?
', [$sessToken->getUserID()]);
while (([$session, $ctime, $ip] = $stmt->fetch())) { ?>
		<li class="list-group-item d-flex justify-content-between align-items-center">
			<?=
				(new DateTimeImmutable())
				->setTimestamp($ctime) /* why is this not a constructor. */
				->setTimezone(new DateTimeZone('Europe/Warsaw'))
				->format('Y-m-d H:i:s')
			?>,
			<?= hsc($ip) ?>
			<?php
			if ($sessToken->getSessionID() != $session) {
				// $session is currently only an integer, but the urlencode
				// will make this safe even if the schema changes
				$url = '/logout.php?session=' . urlencode($session);
				echo '<a class="btn btn-danger btn-sm" href="' . $url . '">Wyloguj</a>';
			}
			?>
		</li>
<?php } ?>
	</ul>
</details>
</div>
<?php html_footer();
