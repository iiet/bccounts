<?php
require(__DIR__ . '/../src/common.php');

$error = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	$userdata = UserDB::getInstance()->lookup(@$_POST['user']);
	if (password_verify(@$_POST['pass'], $userdata['password'])) {
		MySession::setToken(new Token(
			TokenType::Session, '', time(), $_POST['user'], $userdata['generation']
		));
	} else {
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
<form method="post">
	<?php if ($error) { ?>
		<div class="error"> <?= $error ?> </div>
	<?php } ?>
	<label for="user">Login (lub email):</label>
	<input type="text" name="user" required/>
	<br><br>
	<label for="pass">Hasło:</label>
	<input type="password" name="pass" required/>
	<a id="forgotpass" href="#">Zapomniałeś hasła?</a>
	<button>Zaloguj się</button>
</form>
<?php html_footer();
