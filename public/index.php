<?php
require(__DIR__ . '/../src/common.php');

MySession::requireLogin();
MySession::tryRefresh();
$sessToken = MySession::getToken();
$userinfo = UserDB::getInstance()->lookup($sessToken->user);

html_header('iiet.pl');
?>
<p>
Zalogowano jako <code><?= htmlspecialchars($sessToken->user)?></code>
(<?= htmlspecialchars($userinfo['first_name'] . ' ' . $userinfo['last_name'])?>).
</p>
<h2>Nasze serwisy:</h2>
<ul>
<li><a href="https://enroll-me.iiet.pl/">Enroll Me!</a></li>
<li><a href="https://wiki.iiet.pl/">EgzamWiki</a></li>
</ul>
<h2>Inne takie:</h2>
<ul>
<li><a href="/logout.php">Wyloguj się</a></li>
</ul>
<?php html_footer();
