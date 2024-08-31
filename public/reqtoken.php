<?php
require(__DIR__ . '/../src/common.php');

$error = null;
$success = null;

function send_token(string $transcript) {
	global $error, $success, $conf;
	$res = Database::getInstance()->runStmt('
		SELECT id, email, regtoken, last_email
		FROM users
		WHERE transcript_id = ?
	', [$transcript])->fetch();
	if ($res === false) {
		// Tak, to pozwala stwierdzić numery indeksów niezarejestrowanych
		// ludzi na roku. Jednak jeśli się ma token rocznika, to znaczy
		// że się jest studentem - więc i tak się prędzej czy później
		// dostanie listę indeksów przy okazji wyników kolokwium/egzaminu.
		//
		// Mógłbym tak samo odpowiadać bez znaczenia, czy indeks jest w bazie,
		// czy nie, ale wtedy nie mógłbym poinformować kogoś jeśli natknął
		// sie na ratelimit.
		$error = 'Nie mamy twojego numeru indeksu w bazie. Może już się zarejestrowałeś?';
		return;
	}

	[$uid, $email, $regtoken, $last_email] = $res;
	if (time() < $last_email + $conf['email_ratelimit']) {
		$error = 'Już wysłaliśmy ci niedawno email. Poczekaj z godzinę i spróbuj ponownie.';
		return;
	}

	Database::getInstance()->runStmt('
		UPDATE users
		SET last_email = ?
		WHERE id = ?
	', [time(), $uid]);

	$url = $conf['mydomain'] . '/register.php?token=' . urlencode($regtoken);
	$res = mymail($email, 'Rejestracja iiet.pl',
		'Hej, ' .
		'by dokończyć rejestrację wejdź na ' .
		'<a href="'.htmlspecialchars($url).'">' .
		htmlspecialchars($url) .
		'</a>.'
	);
	if (!$res) {
		$error = 'Nie udało się wysłać maila potwierdzającego.';
	} else {
		$success = 'Wysłaliśmy ci mail z dalszymi instrukcjami.';
	}
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	if ($conf['shared_token'] === null) {
		$error = 'Rejestracja jest aktualnie wyłączona.';
	} else if (!hash_equals($conf['shared_token'], @$_POST['token'])) {
		$error = 'Podany token rejestracji jest niepoprawny.';
	} else if (!isset($_POST['transcript'])) {
		$error = 'Nie podano numeru indeksu.';
	} else {
		send_token($_POST['transcript']);
	}
}

html_header('iiet.pl');
?>
<form class="w-100" style="max-width: 400px;" method="post">
	<?php if ($error) { ?>
		<div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div>
	<?php } ?>
	<?php if ($success) { ?>
		<div class="alert alert-success"> <?= htmlspecialchars($success) ?> </div>
	<?php } ?>
	<div class="my-3">
		<label for="token">Token rejestracji:</label>
		<input type="text" name="token" class="form-control" required value="<?=htmlspecialchars(@$_GET['token'])?>"/>
	</div>
	<div class="my-3">
		<label for="transcript">Numer indeksu:</label>
		<input type="number" name="transcript" class="form-control" min="100000" placeholder="123456" required />
	</div>
	<button class="btn btn-primary">Kontynuuj</button>
</form>
<?php html_footer();
