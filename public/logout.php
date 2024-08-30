<?php
require(__DIR__ . '/../src/common.php');

$sessToken = MySession::getToken();
if ($sessToken) {
	if (isset($_GET['session'])) {
		// Log out a different session of the same user
		$other = $_GET['session'];

		$res = Database::getInstance()->runStmt('
			SELECT user
			FROM sessions
			WHERE id = ?
		', [$other])->fetch();
		if ($res && $res['user'] == $sessToken->getUserID()) {
			MySession::logout($other);
			header('Location: /');
			die();
		}
	} else {
		// Log out of the current session
		MySession::logout($sessToken->session);
	}
}

html_header('iiet.pl');
?>
<div class="w-100" style="max-width: 400px;">
<p>
Wylogowano cię.
Możesz wciąż być zalogowany w pozostałych serwisach,
bo nie do końca przemyślałem jak to wszystko będzie działać
i nie mam możliwości cię wylogować.
Ups.
</p>
</div>
<?php html_footer();
