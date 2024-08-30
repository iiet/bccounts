CREATE TABLE IF NOT EXISTS "users" (
	"id" INTEGER PRIMARY KEY AUTOINCREMENT,

	-- Set during onboarding.
	-- If NULL, the user wasn't yet onboarded - deny login!
	-- Note that it's used by some services as an unique identifier with the
	-- assumption it won't ever change.
	"username" TEXT UNIQUE,

	-- I wanted to create a separate "emails" table (it's the only sane way
	-- to allow changing emails, and allowing additional emails would be useful
	-- so as not to depend on the uni emails too much), but some services use
	-- the email as an identifier, so I need a single "primary" email.
	"email" TEXT UNIQUE,

	-- Stored in the password_hash / crypt(3) format.
	"password" TEXT,

	-- The previous implementation made the brillant decision to split up
	-- the name field (which was a single field in the phpBB days) into a
	-- separate first name and last name field.
	-- I'm bringing them back together.
	"fullname" TEXT,
	"start_year" INTEGER,
	-- NOT unique - in case someone wants to change their username, it's better
	-- to create an entirely new account for them.
	"transcript_id" INTEGER,

	-- Time when the user completed registration.
	"ctime" INTEGER,
	-- Time when the password was last changed.
	"mtime" INTEGER,
	-- Time when the user last logged in.
	"atime" INTEGER,

	-- The previous implementation assigned an unique random 20 character
	-- string to each user. I'm keeping this in for now for compatibility,
	-- but I'd love to get rid of this field in the future.
	"legacy_id" TEXT UNIQUE
);

CREATE INDEX IF NOT EXISTS "users_index_username"
ON "users" ("username");

CREATE INDEX IF NOT EXISTS "users_index_email"
ON "users" ("email");

CREATE TABLE IF NOT EXISTS "usergroups" (
	"user" INTEGER NOT NULL,
	"group" TEXT NOT NULL,
	PRIMARY KEY("user", "group"),
	FOREIGN KEY ("user") REFERENCES "users"("id")
	ON UPDATE RESTRICT ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS "usergroups_index_user"
ON "usergroups" ("user");

CREATE TABLE IF NOT EXISTS "sessions" (
	-- Two notes:
	-- 1. Without AUTOINCREMENT, session IDs could be reused, which could cause
	--    issues if e.g. a token assigned to a session doesn't get DELETEd
	--    and then gets reused for another session.
	--    https://www.sqlite.org/autoinc.html
	--    AUTOINCREMENT guarantees IDs won't get reused, but I still tried to
	--    write code that will be robust if IDs do get reused.
	--    (with the exception of bin/cleanup.php)
	-- 2. The IDs are visible to users in the session list, so they can
	--    figure out how active the service is. I don't see this as an issue,
	--    however it should be relatively easy to switch to randomly generated
	--    IDs instead.
	"id" INTEGER PRIMARY KEY AUTOINCREMENT,
	"user" INTEGER NOT NULL,

	"ctime" INTEGER NOT NULL,
	"expires" INTEGER NOT NULL,

	-- The IP that created the session.
	"ip" TEXT,
	FOREIGN KEY ("user") REFERENCES "users"("id")
	ON UPDATE RESTRICT ON DELETE RESTRICT
);
CREATE INDEX IF NOT EXISTS "sessions_user"
ON "sessions"("user");

/* Stores OAuth tokens and a session token (stored in a cookie).
 * If you're going to add support for apps not managed by us, add a scopes
 * column. A redirect_uri column could be helpful too. */
CREATE TABLE IF NOT EXISTS "tokens" (
	"token" TEXT NOT NULL UNIQUE,
	"session" INTEGER NOT NULL,
	"type" TEXT NOT NULL,
	"expires" INTEGER,
	-- References the keys in the services array in the config.
	"service" TEXT NOT NULL,
	PRIMARY KEY("token"),
	FOREIGN KEY ("session") REFERENCES "sessions"("id")
	ON UPDATE RESTRICT ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS "tokens_session"
ON "tokens"("session");
