<?php
require(__DIR__ . '/../src/common.php');

MySession::requireLogin();
MySession::tryRefresh();
$sessToken = MySession::getToken();
$userinfo = UserDB::getInstance()->lookup($sessToken->user);

html_header('iiet.pl');
?>
<div class="w-100" style="max-width: 400px;">
<div class="card w-100 my-4">
	<div class="card-header">Nasze serwisy:</div>
	<ul class="list-group list-group-flush">
		<li class="list-group-item"><a href="https://enroll-me.iiet.pl/">Enroll Me!</a></li>
		<li class="list-group-item"><a href="https://wiki.iiet.pl/">EgzamWiki</a></li>
	</ul>
</div>
<div class="card w-100 my-4">
	<div class="card-header">Twoje dane:</div>
	<table class="table">
<?php
$data = array(
	"first_name" => "Imię",
	"last_name" => "Nazwisko",
	"email" => "Email",
	"start_year" => "Rocznik",
	"transcript_id" => "Indeks",
);
foreach ($data as $k => $name) {
?>
		<tr>
			<th class="text-end"><?= htmlspecialchars($name) ?>:</th>
			<td><?= htmlspecialchars($userinfo[$k]) ?></td>
		</tr>
<?php } ?>
	</table>
	<div class="card-body pt-0">
		<a class="btn btn-outline-primary float-end" href="#">Zmień hasło</a>
	</div>
</div>
</div>
<?php html_footer();
