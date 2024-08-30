<?php
require(__DIR__ . '/../src/common.php');

$error = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	if (!MySession::login(@$_POST['user'], @$_POST['pass'])) {
		$error = 'Niepoprawna nazwa użytkownika lub hasło.';
	}
}

if (MySession::getToken() !== null) {
	/* did MySession::requireLogin bring us here? */
	$uri = @$_GET['redir'];
	if (!str_starts_with($uri, '/')) {
		/* hopefully enough to prevent this from being an open redirect? */
		$uri = '/index.php';
	}
	header('Location: ' . $uri);
	die();
}

html_header('iiet.pl');
?>
<form class="w-100" style="max-width: 400px;" method="post">
	<?php if ($error) { ?>
		<div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div>
	<?php } ?>
	<label for="user">Login (lub email):</label>
	<input type="text" name="user" class="form-control mb-2" required autofocus/>
	<label for="pass">Hasło:</label>
	<input type="password" name="pass" class="form-control mb-3" required/>
	<div class="d-flex flex-wrap justify-content-between align-items-center">
		<button class="btn btn-primary">Zaloguj się</button>
		<a id="forgotpass" href="#">Zapomniałeś hasła?</a>
	</div>
</form>
<?php html_footer();
