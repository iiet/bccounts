<?php
require(__DIR__ . '/../src/common.php');

html_header('iiet.pl');

function die_with_dialog(string $err) {
	echo '<div class="alert alert-danger">' . htmlspecialchars($err) . '</div>';
	html_footer();
	die();
}

$error = null;
$passminlen = $conf['passminlen'];

if (!isset($_GET['token'])) {
	die_with_dialog('Brak dostępu.');
}

$regData = Database::getInstance()->runStmt('
	SELECT fullname, transcript_id, email
	FROM users
	WHERE regtoken = ?
', [$_GET['token']])->fetch();
if (!$regData) {
	die_with_dialog('Ten link stracił ważność (lub nigdy nie był ważny).');
}

function register() {
	global $conf, $error, $passminlen;

	// Input validation
	foreach (['user', 'new', 'confirm'] as $k) {
		if (!isset($_POST[$k])) {
			$error = 'Formularz nie został w pełni wypełniony';
			return;
		}
	}
	$username  = $_POST['user'];
	$password1 = $_POST['new'];
	$password2 = $_POST['confirm'];
	$userlen   = strlen($username);
	if (preg_match('/^[A-Za-z][A-Za-z0-9_]{2,19}$/', $username) !== 1) {
		$error = 'Wybrana nazwa użytkownika nie spełnia wymagań.';
		return;
	}
	if (strlen($password1) < $passminlen) {
		$error = 'Zbyt krótkie hasło.';
		return;
	}
	if ($password1 !== $password2) {
		$error = 'Podane hasła nie są identyczne.';
		return;
	}

	// Set up the account
	$hash = password_hash($password1, $conf['passhash'][0], $conf['passhash'][1]);
	$token = $_GET['token'];
	assert($token !== null);

	// The LIMIT 1 is there just out of paranoia.
	$stmt = Database::getInstance()->runStmt('
		UPDATE users
		SET username = ?, password = ?, ctime = ?, regtoken = NULL
		WHERE regtoken = ?
		LIMIT 1
	', [$username, $hash, time(), $token]);
	if ($stmt->rowCount() != 1) {
		// We've verified that this link is still valid before calling this
		// function, so this UPDATE should always succeed.
		$error = 'Coś poszło nie tak. Skontaktuj się z administracją.';
		mylog('regtoken UPDATE failed');
		return;
	}

	// Let's login the user for convenience's sake.
	// This computes the password hash twice, wasting a bunch of time,
	// but it's also the simplest way to do this - so, whatever.
	if (MySession::login($username, $password1)) {
		header('Location: index.php');
		die();
	} else {
		mylog('failed to login after successful registration');
		$error = 'Coś poszło bardzo nie tak. Skontaktuj się z administracją.';
		return;
	}
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	register();
}

[$fullname, $transcript, $email] = $regData;

?>
<form class="w-100" style="max-width: 600px;" method="post">
	<?php if ($error) { ?>
		<div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div>
	<?php } ?>
	<p>
	Twoje konto na platformie studenckiej jest już prawie gotowe! <br>
	Potrzebujemy już tylko paru detali.
	</p>
	<div class="mb-2">
		<label for="fullname">Imię i nazwisko:</label>
		<input
			type="text" name="fullname" class="form-control" disabled
			value="<?=htmlspecialchars($fullname)?>"
		/>
	</div>
	<div class="mb-2">
		<label for="transcript">Numer indeksu:</label>
		<input
			type="text" name="transcript" class="form-control" disabled
			value="<?=htmlspecialchars($transcript)?>"
		/>
	</div>
	<div class="mb-2">
		<label for="transcript">Email:</label>
		<input
			type="text" name="transcript" class="form-control" disabled
			value="<?=htmlspecialchars($email)?>"
		/>
	</div>
	<div class="mb-2">
		<label for="user">Login:</label>
		<input
			type="text" name="user" class="form-control"
			aria-describedby="userinfo" required minlength=3 maxlength=20
			pattern="[A-Za-z][A-Za-z0-9_]+"
			value="<?=htmlspecialchars(@$_POST['user'])?>" autofocus
		/>
		<div id="userinfo" class="form-text">
			Od 3 do 20 znaków alfanumerycznych, bez "ogonków". Musi się zaczynać od litery.
			<br>
			<code>/[A-Za-z][A-Za-z0-9_]+/</code>, jeśli znasz regex.
		</div>
	</div>
	<div class="mb-2">
		<label for="new">Hasło:</label>
		<input type="password" name="new" class="form-control" aria-describedby="passinfo" minlength=<?=$passminlen?> required/>
		<div id="passinfo" class="form-text">
			Przynajmniej <?=$passminlen?> znaków. <br>
			Swoją drogą, korzystasz z menadżera haseł, prawda? :)
		</div>
	</div>
	<div class="mb-2">
		<label for="confirm">Powtórz hasło:</label>
		<input type="password" name="confirm" class="form-control" minlength=<?=$passminlen?> required/>
	</div>
	<div class="my-3 alert alert-warning">
		Kontynuując wyrażasz zgodę na przetwarzanie swoich danych osobowych przez
		<a href="https://knbit.agh.edu.pl">Koło Naukowe BIT</a>.
		Detale opisane są w <a href="/privacy.php">polityce prywatności</a>.
	</div>
	<button class="btn btn-primary">Zarejestruj się</button>
</form>
<?php html_footer();
