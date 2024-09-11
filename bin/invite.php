<?php
/* Invites new users from a CSV file.
 *
 * php bin/invite.php -y 2020 -g "students" -g "2020-2025"    students.csv
 * php bin/invite.php -y 2020 -g "students" -g "2020-2022 DS" students.csv
 *   -y START_YEAR   - The year of enrollment. Optional.
 *   -g group        - The groups to add the new users to.
                       Please keep them consistent with the current format!
 * students.csv is a CSV file, without a header, in the following format:
 *   fullname,transcript_id
 * for example:
 *   Jan Kowalski,123456
 *   Anna Nowak,123457
 */

require(__DIR__ . '/../src/common.php');

$optind = null;
$opts = getopt('y:g:', [], $optind);

$start_year = @$opts['y']; // nullable
$groups = $opts['g'];

// Verify the year looks correct.
if ($start_year != null) {
	if (!is_string($start_year)) {
		die("\$opts['y'] is not a string. Did you pass -y multiple times?\n");
	}
	if (preg_match('/^20[0-9][0-9]$/', $start_year) !== 1) {
		die($start_year . " doesn't look like a valid year.\n");
	}
}

// Verify the groups look somewhat correct.
if (!isset($opts['g'])) {
	die("You need to choose the groups for the new users.\n");
}
if (!is_array($groups)) {
	// PHP sets ['g'] to a string if it was only specified once,
	// and to an array if it was specified multiple times.
	// What a braindead language.
	$groups = [$groups];
}
assert(is_array($groups));

// Read in the CSV file.
if ($optind + 1 != $argc) {
	die("I need exactly one argument after the options.\n");
}
$fp = fopen($argv[$optind], 'r');
if ($fp === false) {
	die("Can't open " . $argv[$optind] . "\n");
}
$data = [];
while (($row = fgetcsv($fp))) {
	if (count($row) != 2) {
		echo "Missized row, quitting:\n";
		var_dump($row);
		die();
	}
	[$fullname, $transcript] = $row;
	$fullname = trim($fullname);
	if (preg_match('/^[0-9]{6}$/', $transcript) !== 1) {
		die("$transcript doesn't look like a valid transcript number, quitting.\n");
	}
	$data[] = [$fullname, $transcript];
}
$fp = null;

echo "You're about to invite " . count($data) . " users:\n";
for ($i = 0; $i < 5 && isset($data[$i]); $i++) {
	// XXX in theory this could be used to inject escape sequences,
	//     which i think can be dangerous on some terminals.
	//     the data is coming from a trusted source so i don't think i care.
	echo "    " . $data[$i][1] . ": " . $data[$i][0] . "\n";
}
echo "    [...]\n";
if ($start_year != null) {
	echo "Their start_year will be set to $start_year.\n";
} else {
	echo "Their start_year will be NULL (are they faculty?).\n";
}
echo "They will be added to the following groups:\n";
// TODO check if the groups match the expected formats
foreach ($groups as $g) {
	echo "    $g\n";
}
if (!in_array("students", $groups)) {
	echo "In particular you did NOT specify the \"students\" group.\n";
}
echo "Are you sure you want to proceed? Type 'yes' if so.\n";

$times = 0;
while (trim(fgets(STDIN)) !== 'yes') {
	echo "That wasn't a 'yes'.\n";
	$times += 1;
	if ($times >= 5) die("Quitting.\n");
}

$db = Database::getInstance();
if ($db->beginTransaction() !== true) {
	die("Couldn't start a transaction?\n");
}

// Generate a 20char long base64url encoded string.
function random_legacy_id(): string {
	$s = base64_encode(random_bytes(15));
	assert(strlen($s) == 20);
	return str_replace(['+', '/', '='], ['-', '_', ''], $s);
}

$adds  = 0;
$warns = 0;
$skips = 0;
foreach ($data as $row) {
	[$fullname, $transcript] = $row;
	// transcript_id isn't marked as UNIQUE (to allow people to have multiple
	// accounts under special circumstances),
	// but the email is - so I'm using it to ensure no duplicates are entered.
	$email = $transcript . $conf['studentsuffix'];

	$regtoken = random_token();
	$stmt = Database::getInstance()->runStmt('
		INSERT OR IGNORE INTO users
		(email, fullname, start_year, transcript_id, regtoken)
		VALUES (?, ?, ?, ?, ?)
		RETURNING id
	', [$email, $fullname, $start_year, $transcript, $regtoken]);
	// Needs to be a fetchAll, otherwise the commit at the end fails.
	$res = $stmt->fetchAll();
	if (count($res) === 0) { // Didn't insert:
		echo "SKIP  $email\n";
		$skips += 1;
		continue;
	}
	assert(count($res) == 1);

	$uid = $res[0]['id'];
	assert($uid !== null);

	// Alright, this is a new user. Give them the correct groups.
	foreach ($groups as $g) {
		Database::getInstance()->runStmt('
			INSERT OR IGNORE INTO usergroups ("user", "group", "elder")
			VALUES (?, ?, 0)
		', [$uid, $g]);
	}

	// Let's also try to assign them a new legacy_id.
	// This can fail because of the UNIQUE constraint - I'm going to assume
	// the chances are low enough that I can deal with it, especially since
	// I have the fallback for missing legacy_ids.
	$stmt = Database::getInstance()->runStmt('
		UPDATE OR IGNORE users
		SET legacy_id = ?
		WHERE id = ? AND legacy_id IS NULL
		RETURNING legacy_id
	', [random_legacy_id(), $uid]);
	$res = $stmt->fetchAll(); // see comment above

	$legacy_id = "\033[31mNO LEGACY ID\033[0m";
	if (count($res) != 0) {
		$legacy_id = $res[0]['legacy_id'];
	} else {
		$warns += 1;
	}

	echo "ADDED $email $legacy_id\n";
	$adds += 1;
}

echo "Added $adds ($warns warnings), skipped $skips.\n";

echo "Last chance - type in 'commit' to commit the changes to the database.\n";
$times = 0;
while (trim(fgets(STDIN)) !== 'commit') {
	echo "That wasn't a 'commit'.\n";
	$times += 1;
	if ($times >= 5) die("Quitting.\n");
}

$db->commit();
