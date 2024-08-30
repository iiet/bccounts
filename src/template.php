<?php function html_header(string $title) { ?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars($title) ?></title>
	<!-- TODO vendor -->
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>
<body>
<div class="container min-vh-100 d-flex flex-column align-items-center">
	<header class="d-flex flex-wrap justify-content-between py-3 mb-4 border-bottom align-items-center w-100">
		<a href="/" class="link-body-emphasis text-decoration-none fs-4 flex-grow-1">iiet.pl</a>
<?php
$sessToken = MySession::getToken();
if ($sessToken) {
	$userinfo = Database::getInstance()->getUser($sessToken->getUserID());
?>
		<span class="mx-2">Zalogowano jako <?= htmlspecialchars($userinfo['username']) ?></span>
		<a href="/logout.php" class="btn btn-outline-primary">Wyloguj się</a>
<?php } ?>
	</header>
<?php }

function html_footer() { ?>
	<footer class="py-3 mt-4 border-top mt-auto w-100 text-body-secondary">
		bccounts, stworzone przez BIT.
		<a href="#">kod</a>,
		<a href="#">polityka prywatności</a>
	</footer>
</div>
</body>
</html>
<?php } ?>
