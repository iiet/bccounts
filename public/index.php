<?php
require(__DIR__ . '/../src/common.php');

MySession::requireLogin();
$sessToken = MySession::getToken();
$userinfo = UserDB::getInstance()->getUser($sessToken->user);
$groups = UserDB::getInstance()->getGroups($userinfo['id']);

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
		<li class="list-group-item"><a href="https://chat.iiet.pl/">Gitlab</a></li>
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
</div>
<?php html_footer();
