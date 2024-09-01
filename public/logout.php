<?php
require(__DIR__ . '/../src/common.php');

// Vulnerable to CSRF - sadly, the previous app thought that was a feature,
// so I'm leaving it as is for now.
// TODO fix CSRF and add a "bounce" logout page

$error = null;

$sessToken = MySession::getToken();
if ($sessToken) {
	if (isset($_GET['session'])) {
		// Log out a different session of the same user
		$other = $_GET['session'];

		if (is_numeric($other)) {
			$res = Database::getInstance()->runStmt('
				SELECT user
				FROM sessions
				WHERE id = ?
			', [$other])->fetch();
			if ($res && $res['user'] == $sessToken->getUserID()) {
				MySession::logout((int)$other);
				header('Location: /');
				die();
			}
		}
		$error = 'Nie udało się wylogować podanej sesji.';
	} else {
		// Log out of the current session
		MySession::logout($sessToken->session);
	}
}

html_header('iiet.pl');
if ($error !== null) { ?>
<div class="alert alert-danger"> <?= hsc($error) ?> </div>
<?php } else { ?>
<div class="w-100" style="max-width: 400px;">
<div class="alert alert-success"> Wylogowano cię. </div>
<p>
Ze względu na ograniczenia techniczne twoje sesje w innych serwisach mogą
być jeszcze aktywne przez kilka godzin.
Jeśli jesteś na współdzielonym komputerze,
najlepiej wyloguj się z nich ręcznie.
</p>
</div>
<?php }
html_footer();
