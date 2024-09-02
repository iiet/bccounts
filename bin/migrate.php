<?php
/** Updates the DB schema to the newest version. */

function migrate(PDO $dbh, int $from, int $to, string $desc, string $query): void {
	if ($dbh->query('PRAGMA user_version')->fetch()['user_version'] != $from) {
		echo "Skipping $from -> $to: $desc\n";
		return;
	}
	echo "Executing $from -> $to: $desc\n";
	$dbh->exec($query);
	// It doesn't seem like you can use a prepared query here.
	// This is fine - $to is only an int, and even if it wasn't, it came from
	// a trusted source (this script).
	$dbh->exec('PRAGMA user_version = ' . $to);
}

if (!isset($argv[1])) {
	echo "Usage: php bin/migrate.php dsn [user] [pass]\n";
	echo "For SQLite: php bin/migrate.php sqlite:path/to/db.sql\n";
	die();
}
$dbh = new PDO($argv[1], @$argv[2], @$argv[3]);
$dbh->exec('PRAGMA foreign_keys = ON;');
$dbh->beginTransaction();

migrate($dbh, 0, 1, 'Initial schema', '
	CREATE TABLE IF NOT EXISTS "users" (
		"id" INTEGER PRIMARY KEY AUTOINCREMENT,
		"username" TEXT UNIQUE,
		"email" TEXT UNIQUE,
		"password" TEXT,
		"fullname" TEXT,
		"start_year" INTEGER,
		"transcript_id" INTEGER,
		"ctime" INTEGER,
		"mtime" INTEGER,
		"atime" INTEGER,
		"last_email" INTEGER,
		"regtoken" TEXT UNIQUE,
		"legacy_id" TEXT UNIQUE
	);

	CREATE INDEX IF NOT EXISTS "users_index_username"
	ON "users" ("username");
	CREATE INDEX IF NOT EXISTS "users_index_email"
	ON "users" ("email");
	CREATE INDEX IF NOT EXISTS "users_index_transcript_id"
	ON "users" ("transcript_id");
	CREATE INDEX IF NOT EXISTS "users_index_regtoken"
	ON "users" ("regtoken");

	CREATE TABLE IF NOT EXISTS "usergroups" (
		"user" INTEGER NOT NULL,
		"group" TEXT NOT NULL,
		"elder" INTEGER NOT NULL,
		PRIMARY KEY("user", "group"),
		FOREIGN KEY ("user") REFERENCES "users"("id")
		ON UPDATE RESTRICT ON DELETE CASCADE
	);

	CREATE INDEX IF NOT EXISTS "usergroups_index_user"
	ON "usergroups" ("user");
	CREATE INDEX IF NOT EXISTS "usergroups_index_group"
	ON "usergroups" ("group");

	CREATE TABLE IF NOT EXISTS "sessions" (
		"id" INTEGER PRIMARY KEY AUTOINCREMENT,
		"user" INTEGER NOT NULL,

		"ctime" INTEGER NOT NULL,
		"expires" INTEGER NOT NULL,

		"ip" TEXT,
		FOREIGN KEY ("user") REFERENCES "users"("id")
		ON UPDATE RESTRICT ON DELETE RESTRICT
	);
	CREATE INDEX IF NOT EXISTS "sessions_user"
	ON "sessions"("user");

	CREATE TABLE IF NOT EXISTS "tokens" (
		"token" TEXT NOT NULL UNIQUE,
		"session" INTEGER NOT NULL,
		"type" TEXT NOT NULL,
		"expires" INTEGER,
		"service" TEXT NOT NULL,
		PRIMARY KEY("token"),
		FOREIGN KEY ("session") REFERENCES "sessions"("id")
		ON UPDATE RESTRICT ON DELETE CASCADE
	);
	CREATE INDEX IF NOT EXISTS "tokens_session"
	ON "tokens"("session");

	CREATE TABLE IF NOT EXISTS "recovery_tokens" (
		"token" TEXT NOT NULL UNIQUE,
		"user" INTEGER NOT NULL UNIQUE,
		"expires" INTEGER,
		PRIMARY KEY("token"),
		FOREIGN KEY ("user") REFERENCES "users"("id")
		ON UPDATE RESTRICT ON DELETE CASCADE
	);
');

// redirect_uri is needed to support more than a single redirect_uri per
// service without violating the spec
migrate($dbh, 1, 2, 'Add a redirect_uri column to tokens', '
	ALTER TABLE "tokens" ADD COLUMN "redirect_uri" TEXT;
');

$dbh->commit();
