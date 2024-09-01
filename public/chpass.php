<?php
require(__DIR__ . '/../src/common.php');

$error = null;
$success = null;
$minlen = $conf['passminlen'];

MySession::requireLogin();
$sessToken = MySession::getToken();
$userinfo = Database::getInstance()->getUser($sessToken->getUserID());

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	$current = @$_POST['current'];
	$new     = @$_POST['new'];
	$confirm = @$_POST['confirm'];
	if (strlen($new) < $minlen) {
		$error = 'Nowe hasło jest za krótkie.';
	} else if ($new !== $confirm) {
		$error = 'Podane nowe hasła nie są identyczne.';
	} else if (!password_verify($current, $userinfo['password'])) {
		$error = 'Niepoprawne oryginalne hasło.';
	} else {
		$hash = password_hash($new, $conf['passhash'][0], $conf['passhash'][1]);
		$stmt = Database::getInstance()->runStmt('
			UPDATE users
			SET password = ?, mtime = ?
			WHERE id = ?
		', [$hash, time(), $sessToken->getUserID()]);
		if ($stmt->rowCount() != 1) {
			$error = 'Coś się zjebało.';
		} else {
			$success = 'Zmieniono hasło.';
		}
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
	<div class="mb-3">
		<label for="current">Aktualne hasło:</label>
		<input type="password" name="current" class="form-control" required autofocus/>
	</div>
	<div class="mb-3">
		<label for="new">Nowe hasło:</label>
		<input type="password" name="new" class="form-control" aria-describedby="passinfo" minlength=<?=$minlen?> required/>
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
