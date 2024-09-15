<?php
require(__DIR__ . '/../src/common.php');

$error = null;
$success = null;

function send_token(string $transcript): void {
	global $error, $success, $conf;
	$res = Database::getInstance()->runStmt('
		SELECT id, email, regtoken, last_email
		FROM users
		WHERE transcript_id = ? AND regtoken IS NOT NULL
	', [$transcript])->fetch();
	if ($res === false) {
		// This allows enumerating the transcript numbers of unregistered people
		// if one posseses the shared registration token.
		// I don't think this is an issue for two reasons:
		// 1. If you have the registration token, you're probably a student.
		//    You'll probably receive a complete list of transcript numbers
		//    together with the results of one of the exams anyways.
		// 2. I could try responding with the same message no matter what,
		//    but this sucks UX-wise - in particular, I won't be able to tell
		//    people when they hit the ratelimit, which would be a pretty
		//    confusing experience.
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
		'<a href="'.hsc($url).'">' .  hsc($url) .  '</a>.'
	);
	if (!$res) {
		$error = 'Nie udało się wysłać maila potwierdzającego.';
	} else {
		$success = 'Wysłaliśmy ci mail z dalszymi instrukcjami.';
	}
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	$tok = @$_POST['token'];
	$transcript = @$_POST['transcript'];
	if ($conf['shared_token'] === null) {
		$error = 'Rejestracja jest aktualnie wyłączona.';
	} else if (!is_string($tok) || !hash_equals($conf['shared_token'], $tok)) {
		$error = 'Podany token rejestracji jest niepoprawny.';
	} else if (!is_string($transcript)) {
		$error = 'Nie podano numeru indeksu.';
	} else {
		send_token($transcript);
	}
}

html_header('iiet.pl');
?>
<form class="w-100" style="max-width: 400px;" method="post">
	<div class="alert alert-light">
	Żeby założyć u nas konto, musisz najpierw
	<a href="https://panel.agh.edu.pl/newuser/newuser.php">
	założyć uczelnianego emaila</a>.
	Wyślemy ci tam linka do dokończenia rejestracji.
	</div>
	<?php if ($error !== null) { ?>
		<div class="alert alert-danger"> <?= hsc($error) ?> </div>
	<?php } ?>
	<?php if ($success !== null) { ?>
		<div class="alert alert-success"> <?= hsc($success) ?> </div>
	<?php } ?>
	<div class="my-3">
		<label for="token">Token rejestracji:</label>
		<input
			type="text" name="token" class="form-control" required
			<?php if (isset($_GET['token'])) { ?>
			value="<?=hsc($_GET['token'])?>" readonly
			<?php } ?>
		/>
	</div>
	<div class="my-3">
		<label for="transcript">Numer indeksu:</label>
		<input
			type="number" name="transcript" class="form-control"
			min="100000" placeholder="123456" required autofocus
		/>
	</div>
	<button class="btn btn-primary">Kontynuuj</button>
</form>
<?php html_footer();
