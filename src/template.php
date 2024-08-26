<?php function html_header(string $title) { ?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars($title) ?></title>
	<link rel="stylesheet" href="style.css">
</head>
<body>
<main>
<h1><a href="/">iiet.pl</a></h1>
<?php }
function html_footer() { ?>
</main>
</body>
</html>
<?php } ?>
