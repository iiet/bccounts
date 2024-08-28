CREATE TABLE IF NOT EXISTS "users" (
	"id" INTEGER NOT NULL UNIQUE,

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
	"legacy_id" TEXT UNIQUE,
	PRIMARY KEY("id")
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
	"id" INTEGER NOT NULL UNIQUE,
	"user" INTEGER NOT NULL,

	"ctime" INTEGER NOT NULL,
	-- The IP the session was created on.
	"ip" TEXT,
	-- The user agent used to create the session.
	"useragent" TEXT,
	PRIMARY KEY("id"),
	FOREIGN KEY ("user") REFERENCES "users"("id")
	ON UPDATE RESTRICT ON DELETE RESTRICT
);

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
