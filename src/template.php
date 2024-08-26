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
<?php }
function html_footer() { ?>
</body>
</html>
<?php } ?>
