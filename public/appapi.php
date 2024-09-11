<?php
// Exports a list of all the students.
// This shouldn't ever be needed, but our phpBB OAuth plugin is garbage and
// requires this.
// I'm exporting less data than the previous account system (because I don't
// keep that much data on the users) and I'm only exporting people who have
// never used the old account system, so as not to risk messing up old accounts.

require(__DIR__ . '/../src/common.php');

// Here's an example response from the original implementation:
/*
dzwdz@fleshwing:~/src/bccounts$ jq 'map(select(.login == "gologotest"))' appapi-students.json
[{
    "id": 4326,
    "email": "REDACTED",
    "user_id": "the 20 char legacy id",
    "sign_in_count": 4,
    "current_sign_in_at": "REDACTED",
    "last_sign_in_at": "REDACTED",
    "current_sign_in_ip": "REDACTED",
    "last_sign_in_ip": "REDACTED",
    "created_at": "REDACTED",
    "updated_at": "REDACTED",
    "transcript_number": null,
    "login": "gologotest",
    "active": true,
    "start_year": 2023,
    "first_name": "REDACTED",
    "last_name": "REDACTED",
    "migrated": true,
    "group_names": [ "students", "infra", "2023-2028" ],
    "group_ids": [ 5, 24, 27 ],
    "name": "REDACTED"
}]

The fields used by sync.php are:
user_id, login, name, group_ids (fuck.), active, start_year, email
*/

function die403(): never {
	http_response_code(403);
	die();
}

$aid = @$_GET['aid'];
$auth_token = @$_GET['authentication_token'];
if (!is_string($aid) || !is_string($auth_token)) die403();
$expect = @$conf['appapi_users'][$aid];
if (!is_string($expect)) die403();
if (!hash_equals($expect, $auth_token)) die403();

$res = [];

// Yes, I could just do everything in a single query, but I want to explicitly
// go through ->getUser() to use its fallbacks for e.g. a missing legacy_id.
// Also note how I'm skipping over unregistered users because the previous
// app didn't support that, and it would probably break stuff that depends on
// this endpoint.
$usersStmt = Database::getInstance()->runStmt('
	SELECT id FROM users
	WHERE start_year >= 2025 AND username IS NOT NULL
', []);
while (([$uid] = $usersStmt->fetch())) {
	$data = Database::getInstance()->getUser($uid);
	if ($data === null) continue; // shouldn't happen
	$res[] = [
		'user_id'    => $data['legacy_id'],
		'login'      => $data['username'],
		'name'       => $data['fullname'],
		'group_ids'  => [], // shrug emoji
		'active'     => true,
		'start_year' => $data['start_year'],
		'email'      => $data['email'],
	];
}
echo json_encode($res) . "\n";
