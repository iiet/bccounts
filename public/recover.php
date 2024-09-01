<?php
require(__DIR__ . '/../src/common.php');

$success = null;
$error   = null;

function recover(): void {
	global $conf, $success, $error;
	$user = @$_POST['user'];
	if (!is_string($user)) {
		$error = 'Źle wypełniono formularz.';
		return;
	}

	// From this point on we pretend we were successful, to prevent people
	// from enumerating email addresses. However, this is still vulnerable
	// to a timing attack.
	// TODO run this in a forked process?
	// TODO is this even a concern? I remember this being a thing, but I checked a few services and they tell you outright if the email isn't in their database.

	$success =
		'Jeśli widniejesz w naszej bazie danych, ' .
		'wysłaliśmy ci maila z linkiem do zmiany hasła. ';

	// TODO maybe user email ratelimits should be per type of message?
	// Right now the user won't get any email if they try to recover their
	// password right after registering, which is a confusing failure mode.

	$res = Database::getInstance()->runStmt('
		SELECT id, email, last_email
		FROM users
		WHERE username = ? OR email = ?
	', [$user, $user])->fetch();
	if (!$res) return;
	[$uid, $rcpt, $last_email] = $res;

	if (time() < $last_email) return;

	// Generate a new recovery token - it will override any previous ones.
	$token = random_token();
	$stmt = Database::getInstance()->runStmt('
		INSERT INTO recovery_tokens (token, user, expires)
		VALUES (?, ?, ?)
		ON CONFLICT(user) DO UPDATE SET
			token=excluded.token,
			expires=excluded.expires
	', [ $token, $uid, time() + $conf['expires']['recovery'] ]);
	if ($stmt->rowCount() != 1) {
		mylog("recovery token insert failed");
		return;
	}

	// Send it to the user
	Database::getInstance()->runStmt('
		UPDATE users
		SET last_email = ?
		WHERE id = ?
	', [time(), $uid]);

	$url = $conf['mydomain'] . '/chpass.php?token=' . urlencode($token);
	$res = mymail($rcpt, 'Odzyskiwanie hasła iiet.pl',
		'Hej, ' .
		'by ustawić nowe hasło wejdź na ' .
		'<a href="'.hsc($url).'">' .  hsc($url) .  '</a>.'
	);
	if (!$res) {
		mylog("failed to send email");
	}
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	recover();
}

html_header("iiet.pl");
?>
<form class="w-100" style="max-width: 400px;" method="post">
	<h2>Odzyskiwanie hasła</h2>
	<?php if ($error !== null) { ?>
		<div class="alert alert-danger"> <?= hsc($error) ?> </div>
	<?php } ?>
	<?php if ($success !== null) { ?>
		<div class="alert alert-success"> <?= hsc($success) ?> </div>
	<?php } ?>
	<div class="my-3">
		<label for="user">Login (lub email):</label>
		<input
			type="text" name="user" class="form-control"
			aria-describedby="userinfo" required autofocus
			placeholder="123456<?=hsc($conf['studentsuffix'])?>"
		/>
		<div id="userinfo" class="form-text align-justify">
			Jeśli nie jesteś pewien co tu wpisać,
			spróbuj wpisać adres "z indeksem".
			<br>
			Możesz też poprosić starostów swojego roku o sprawdzenie twojej nazwy użytkownika,
			lub sprawdzić na który adres wysyłaliśmy ci wiadomości.
		</div>
	</div>
	<button class="btn btn-primary">Wyślij instrukcje zmiany hasła</button>
</form>
<?php
html_footer();
