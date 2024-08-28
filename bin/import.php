<?php
/* Imports users from a CSV file on stdin.
 * username,email,password,fullname,start_year,transcript_id,legacy_id,groups */

require(__DIR__ . '/../src/common.php');

$stdin = fopen('php://stdin', 'r');
if ($stdin === false) die("can't open stdin apparently");

$dbh = new PDO($conf['pdo_dsn'], $conf['pdo_user'], $conf['pdo_pass']);
$dbh->exec('PRAGMA foreign_keys = ON;');

$user_stmt = $dbh->prepare('
	INSERT OR IGNORE INTO users
	(username, email, password, fullname, start_year, transcript_id, ctime, legacy_id)
	VALUES
	(:username, :email, :password, :fullname, :start_year, :transcript_id, :ctime, :legacy_id)
');
$group_stmt = $dbh->prepare('
	INSERT OR IGNORE INTO usergroups ("user", "group")
	VALUES ((SELECT id from USERS where username = ?), ?)
');

$starttime = time();
while (($data = fgetcsv($stdin))) {
	// Treat empty fields as null.
	foreach ($data as &$v) { if ($v === '') $v = null; }

	$map = [
		'username'      => $data[0],
		'email'         => $data[1],
		'password'      => $data[2],
		'fullname'      => $data[3],
		'start_year'    => $data[4],
		'transcript_id' => $data[5],
		'legacy_id'     => $data[6],
		//'groups'        => $data[7],
		'ctime'         => $starttime,
	];
	$user_stmt->execute($map);

	foreach (explode(',', $data[7]) as $g) {
		$group_stmt->execute([$data[0], $g]);
	}
}
