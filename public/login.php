<?php
require(__DIR__ . '/../src/common.php');

$error = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	$user = @$_POST['user'];
	$pass = @$_POST['pass'];
	if (!is_string($user) || !is_string($pass)) {
		$error = 'Niepoprawnie wypełniony formularz.';
	} else if (!MySession::login($user, $pass)) {
		$error = 'Niepoprawna nazwa użytkownika lub hasło.';
	}
}

if (MySession::getToken() !== null) {
	// Did MySession::requireLogin bring us here?
	$uri = @$_GET['redir'];
	if (is_string($uri) && str_starts_with($uri, '/')) {
		// Hopefully that was enough not to be an open redirect?

		/** @psalm-taint-escape header */
		$uri = $uri; // Yeah, I don't think there's another way to tell Psalm
		             // that this is safe to use now. I hate this.

		header('Location: ' . $uri);
	} else {
		header('Location: /index.php');
	}
	die();
}

html_header('iiet.pl');
?>
<form class="w-100" style="max-width: 400px;" method="post">
	<?php if ($error) { ?>
		<div class="alert alert-danger"> <?= hsc($error) ?> </div>
	<?php } ?>
	<label for="user">Login (lub email):</label>
	<input type="text" name="user" class="form-control mb-2" required autofocus/>
	<label for="pass">Hasło:</label>
	<input type="password" name="pass" class="form-control mb-3" required/>
	<div class="d-flex flex-wrap justify-content-between align-items-center">
		<button class="btn btn-primary">Zaloguj się</button>
		<a id="forgotpass" href="/recover.php">Zapomniałeś hasła?</a>
	</div>
</form>
<?php html_footer();
