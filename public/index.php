<?php
require(__DIR__ . '/../src/common.php');

MySession::requireLogin();
MySession::tryRefresh();
$sessToken = MySession::getToken();
$userinfo = UserDB::getInstance()->lookup($sessToken->user);

html_header('iiet.pl');
echo '<a href="/logout.php">wyloguj się</a>';
echo '<pre>';
var_dump($userinfo);
echo '</pre>';
