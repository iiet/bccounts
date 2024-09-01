<?php
/* Used both for changing the password of logged in users (who have to input
 * their current password), and for password recoveries.
 * A tasty serving of spaghetti. */
require(__DIR__ . '/../src/common.php');

$error = null;
$success = null;
$minlen = $conf['passminlen'];

// Check which mode we're in, and if we have the proper credentials to proceed.
// A logged in user just needs to be, well, logged in.
// A password recovery token needs to actually be valid.

$rectoken = null;
$userid = null; // not null IFF the user is authorized to change their password
if (is_string(@$_GET['token'])) {
	// Password recovery mode.
	$rectoken = $_GET['token'];
	$res = Database::getInstance()->runStmt('
		SELECT user
		FROM recovery_tokens
		WHERE token = ? AND ? < expires
	', [$rectoken, time()])->fetch();
	if ($res === false) {
		$error = 'Ten link odzyskiwania hasła stracił ważność (lub nigdy nie był ważny).';
	} else {
		$userid = $res['user'];
	}
} else {
	// "Normal" mode.
	$sessToken = MySession::requireLogin();
	$userid = $sessToken->getUserID();
}

function chpass(): void {
	global $conf, $rectoken, $userid, $error, $success;
	assert($userid !== null);

	$new = @$_POST['new'];
	$confirm = @$_POST['confirm'];

	// First off, do some common form validation.
	if (!is_string($new) || !is_string($confirm)) {
		$error = 'Formularz nie został w pełni wypełniony';
		return;
	} else if (strlen($new) < $conf['passminlen']) {
		$error = 'Nowe hasło jest za krótkie.';
		return;
	} else if ($new !== $confirm) {
		$error = 'Podane nowe hasła nie są identyczne.';
		return;
	}

	// If the user isn't using a password recovery link, they also need
	// to present their current password.
	if ($rectoken === null) {
		$userinfo = Database::getInstance()->getUser($userid);
		assert($userinfo !== null);

		$curhash = $userinfo['password'];
		$current = @$_POST['current'];
		if (!is_string($current)) {
			$error = 'Formularz nie został w pełni wypełniony';
			return;
		}

		if ($curhash === null || !password_verify($current, $curhash)) {
			$error = 'Niepoprawne oryginalne hasło.';
			return;
		}
	}

	// Change the user's password.
	$hash = password_hash($new, $conf['passhash'][0], $conf['passhash'][1]);
	$stmt = Database::getInstance()->runStmt('
		UPDATE users
		SET password = ?, mtime = ?
		WHERE id = ?
	', [$hash, time(), $userid]);
	if ($stmt->rowCount() != 1) {
		$error = 'Coś się zepsuło.';
		return;
	} else {
		$success = 'Zmieniono hasło.';
	}

	if ($rectoken !== null) {
		$res = Database::getInstance()->runStmt('
			DELETE FROM recovery_tokens
			WHERE token = ?
			LIMIT 1
		', [$rectoken]);
		if ($res->rowCount() != 1) {
			mylog('Failed to delete recovery token for ' . $userid . '.');
		}

		// TODO log the user back in
	}
}

// Don't even bother to process the form if we don't have an user ID.
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $userid !== null) {
	chpass();
}

html_header('iiet.pl');
?>
<form class="w-100" style="max-width: 400px;" method="post">
	<?php if ($error !== null) { ?>
		<div class="alert alert-danger"> <?= hsc($error) ?> </div>
	<?php } ?>
	<?php if ($success !== null) { ?>
		<div class="alert alert-success"> <?= hsc($success) ?> </div>
	<?php } ?>
	<div class="mb-3">
		<label for="current">Aktualne hasło:</label>
		<input
			type="password" name="current" class="form-control"
			<?= $rectoken === null ? 'required autofocus' : 'disabled' ?>
		/>
	</div>
	<div class="mb-3">
		<label for="new">Nowe hasło:</label>
		<input
			type="password" name="new" class="form-control"
			aria-describedby="passinfo" minlength=<?=$minlen?> required
			<?= $rectoken === null ? '' : 'autofocus' ?>
		/>
		<div id="passinfo" class="form-text">
			Przynajmniej <?=$minlen?> znaków. <br>
			Mamy nadzieję, że korzystasz z menadżera haseł :)
		</div>
	</div>
	<div class="mb-3">
		<label for="confirm">Powtórz:</label>
		<input type="password" name="confirm" class="form-control" minlength=<?=$minlen?> required/>
	</div>
	<button class="btn btn-primary">Zmień hasło</button>
</form>
<?php html_footer();
