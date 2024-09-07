<?php
// If I don't load everything here, shit breaks.

global $conf, $IN_TEST;

// Used to check if I'm in a test.
// Probably not the cleanest way to handle this. People seem to suggest adding
// an entry in the PHPUnit config to tell it to set a global, but I don't
// really see the point. I end up with a global either way, might as well make
// it the standard way.
$IN_TEST = 1;

require_once(__DIR__ . '/../src/common.php');

if ($conf['pdo_dsn'] !== 'sqlite::memory:') {
	die("Incorrect dsn. Quitting to prevent possible db corruption.\n");
}

// Initialize the DB (in a pretty dirty way).
Database::getInstance()->dbh->exec(file_get_contents(__DIR__ . '/../schema.sql'));
