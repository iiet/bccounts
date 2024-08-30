<?php
require(__DIR__ . '/../src/common.php');

MySession::requireLogin();
$sessToken = MySession::getToken();
$userinfo = Database::getInstance()->getUser($sessToken->getUserID());
$groups = Database::getInstance()->getGroups($sessToken->getUserID());

html_header('iiet.pl');
?>
<div class="w-100" style="max-width: 400px;">
<div class="card w-100 my-4">
	<div class="card-header">Nasze serwisy:</div>
	<ul class="list-group list-group-flush">
		<li class="list-group-item"><a href="https://enroll-me.iiet.pl/">Enroll Me!</a></li>
		<li class="list-group-item"><a href="https://wiki.iiet.pl/">EgzamWiki</a></li>
		<li class="list-group-item"><a href="https://forum.iiet.pl/">Forum</a></li>
		<li class="list-group-item"><a href="https://git.iiet.pl/">Gitlab</a></li>
		<li class="list-group-item"><a href="https://chat.iiet.pl/">RocketChat</a></li>
	</ul>
</div>
<div class="card w-100 my-4">
	<div class="card-header">Twoje dane:</div>
	<table class="table">
<?php
$data = array(
	'first_name' => 'Imię',
	'last_name' => 'Nazwisko',
	'email' => 'Email',
	'start_year' => 'Rocznik',
	'transcript_id' => 'Indeks',
);
foreach ($data as $k => $name) {
?>
		<tr>
			<th class="text-end"><?= htmlspecialchars($name) ?>:</th>
			<td><?= htmlspecialchars($userinfo[$k]) ?></td>
		</tr>
<?php } ?>
		<tr>
			<th class="text-end">Grupy:</th>
			<td>
<?php foreach ($groups as $group) {
	echo '<span class="badge bg-dark me-1">' . htmlspecialchars($group) . '</span>';
} ?>
			</td>
		</tr>
	</table>
	<div class="card-body pt-0">
		<a class="btn btn-outline-primary float-end" href="#">Zmień hasło</a>
	</div>
</div>
<div class="card w-100 my-4">
	<div class="card-header">Aktywne sesje:</div>
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
			<?= htmlspecialchars($ip) ?>
			<?php
			if ($sessToken->session != $session) {
				// $session is currently only an integer, but the urlencode
				// will make this safe even if the schema changes
				$url = '/logout.php?session=' . urlencode($session);
				echo '<a class="btn btn-danger btn-sm" href="' . $url . '">Wyloguj</a>';
			}
			?>
		</li>
<?php } ?>
	</ul>
</div>
</div>
<?php html_footer();
