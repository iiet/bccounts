<?php

/**
 * Generates a random string that can be used as a secret token.
 * Used by the SessionToken class, bin/invite.php, public/recovery.php.
 */
function random_token(): string {
	// Generate 32*8=256 bits of entropy and encode it with base64url.
	// random_bytes is safe for crypto uses.
	$repr = str_replace(['+', '/', '='], ['-', '_', ''],  base64_encode(random_bytes(32)));
	// Prepend it with the time because I feel like it.
	return time() . '_' . $repr;
}

enum TokenType: string
{
	/* tokens used for OAuth: */
	case OAuthorization = 'a';
	case OAccess        = 'c';
	case ORefresh       = 'r';

	/* tokens used for the session: */
	case Session        = 's';
}

/**
 * Represents an authenticated, valid "token" bound to an user session.
 * Used for OAuth2 tokens and session tokens. */
class SessionToken
{
	protected TokenType $type;
	protected string    $service;
	protected int       $expires;
	protected int       $session;

	/** Not copied when deriving new tokens - only used for authorization
	 * tokens. */
	protected ?string   $redirect_uri = null;

	/** Cache for getUserID(). */
	protected ?int      $user = null;

	/** Only set for tokens that are (were) already in the database. */
	protected ?string   $repr = null;

	public function getType(): TokenType { return $this->type; }
	public function getService(): string { return $this->service; }
	public function getExpiryTime(): int { return $this->expires; }
	public function getSessionID():  int { return $this->session; }

	public function getRedirectURI(): ?string { return $this->redirect_uri; }
	public function setRedirectURI(?string $uri): void {
		$this->redirect_uri = $uri;
		$this->repr = null;
	}

	public function __construct(TokenType $type, string $service, ?int $expires, int $session) {
		global $conf;
		// If $expires is null, choose it based on the token type.
		if ($expires === null) {
			$expires = time() + $conf['expires'][$type->value];
		}

		$this->type    = $type;
		$this->service = $service;
		$this->expires = $expires;
		$this->session = $session;
	}

	public function export(): string {
		global $conf;
		if ($this->repr === null) {
			$this->repr = random_token();
			Database::getInstance()->runStmt('
				INSERT INTO tokens
				(token, session, type, expires, service, redirect_uri)
				VALUES (?, ?, ?, ?, ?, ?)
			', [
				$this->repr, $this->session, $this->type->value,
				$this->expires, $this->service, $this->redirect_uri,
			]);
		}
		return $this->repr;
	}

	public function getUserID(): int {
		if ($this->user === null) {
			$res = Database::getInstance()->runStmt('
				SELECT user
				FROM sessions
				WHERE id = ?
			', [$this->session])->fetch();
			// Pretty much impossible outside of a super rare race condition
			// with bin/cleanup.php.
			assert($res !== false);
			$this->user = $res['user'];
		}
		return $this->user;
	}

	/**
	 * Fetches a token from the database, and verifies that it's a valid
	 * token of the expected type.
	 */
	public static function accept(string $repr, TokenType $type): ?SessionToken {
		global $conf;

		$res = Database::getInstance()->runStmt('
			SELECT tokens.session, tokens.expires, sessions.expires,
			       tokens.service, tokens.redirect_uri
			FROM tokens
			JOIN sessions ON tokens.session = sessions.id
			WHERE token = ? AND type = ?
		', [$repr, $type->value])->fetch();
		if ($res === false) return null;
		[$session, $tokExpires, $sessExpires, $service, $uri] = $res;

		$time = time();
		if ($tokExpires < $time || $sessExpires < $time) {
			return null;
		}
		$tok = new SessionToken($type, $service, $tokExpires, $session);
		$tok->redirect_uri = $uri;
		/** @psalm-taint-escape html
		 *  @psalm-taint-escape cookie
		 *  @psalm-taint-escape has_quotes */
		$tok->repr = $repr;
		return $tok;
	}

	/**
	 * Fetches a token from the database, and verifies that it's a valid token
	 * of the expected type. It also removes it from the database to prevent
	 * further use. NOT ATOMIC - it's still possible to use the token twice
	 * if you get the timing just right.
	 *
	 * I only need this for the authorization keys. I don't think preventing
	 * their reuse is super important, so instead of trying to figure out how
	 * to properly do an atomic SELECT and DELETE I'm just not going to
	 * guarantee atomicity. (I don't think using a transaction is enough?)
	 */
	 public static function acceptOnce(string $repr, TokenType $type): ?SessionToken {
	 	$tok = self::accept($repr, $type);
	 	if ($tok !== null) {
	 		// As always, the LIMIT 1 is there out of paranoia.
	 		$stmt = Database::getInstance()->runStmt('
	 			DELETE FROM tokens
	 			WHERE token = ?
	 			LIMIT 1
	 		', [$repr]);
	 		if ($stmt->rowCount() == 0) {
	 			// Supposedly the SQLite wrapper is always returns a rowCount
	 			// of 0.  If OAuth stops working and you track it down to here,
	 			// just remove this if condition.
	 			mylog('Rejecting a token because we got raced... or because of a bug in PDO.');
	 			return null;
	 		}
	 	}
	 	return $tok;
	 }

	 /**
	  * Returns a copy of this token with a changed type and updated
	  * expiry time. Meant to make it easier to extend the token with new
	  * fields in the future.
	  */
	 // TODO rename - this name is kinda misleading, as it returns a copy
	 //      instead.
	 public function setTypeAndRefresh(TokenType $type): SessionToken {
	 	global $conf;
	 	$tok = clone $this;
	 	$tok->type = $type;
	 	$tok->expires = time() + $conf['expires'][$type->value];
	 	$tok->repr = null;
	 	return $tok;
	 }
}

abstract class MySession
{
	private static ?SessionToken $token = null;
	private static bool $tokenCached = false;

	public static function login(string $user, #[\SensitiveParameter] string $pass): bool {
		global $conf;

		// TODO log "weird" errors
		// I'm assuming we never get multiple results - usernames can't contain @.
		$res = Database::getInstance()->runStmt('
			SELECT id, password
			FROM users
			WHERE username = ? OR email = ?
		', [$user, $user])->fetch();
		if (!$res) return false;
		[$id, $hash] = $res;
		if ($hash === null) return false;
		if (!password_verify($pass, $hash)) return false;

		// Alright, the password is correct. Let's create a new session.
		// RETURNING is an SQLite extension (that I think came from PostgreSQL?)
		// It's much saner than lastInsertId, IMO.
		$time = time();
		$expires = $time + $conf['expires'][TokenType::Session->value];
		$res = Database::getInstance()->runStmt('
			INSERT INTO sessions (user, ctime, expires, ip)
			VALUES (?, ?, ?, ?)
			RETURNING id
		', [ $id, $time, $expires, $_SERVER['REMOTE_ADDR'] ])->fetch();
		if ($res == false) return false;

		// Create and save the token.
		$tok = new SessionToken(TokenType::Session, '', $expires, $res[0]);
		self::setToken($tok);
		return true;
	}

	public static function logout(int $session): void {
		$token = self::getToken();
		if ($token && $token->getSessionID() == $session) {
			self::setToken(null);
		}

		// If after deleting the session some tokens remain in the database,
		// the session ID they point to might be reused, which gives the
		// token holder access to another account!

		// The foreign key relation on tokens *should* automatically delete
		// all the tokens linked to the session, and AUTOINCREMENT on sessions
		// should prevent ID reuse, but, just to be sure, let's delete the
		// tokens anyways.
		Database::getInstance()->runStmt('
			DELETE FROM tokens
			WHERE session = ?
		', [$session]);
		Database::getInstance()->runStmt('
			DELETE FROM sessions
			WHERE id = ?
		', [$session]);
	}

	private static function setToken(?SessionToken $tok): void {
		global $conf;
		// To unset the cookie, we set its expiry time in the past.
		setcookie($conf['cookie'], $tok ? $tok->export() : '', array(
			'expires' => $tok ? $tok->getExpiryTime() : 1,
			'httponly' => true,
			'path' => '/',
			'secure' => true,
		));
		self::$token = $tok;
		self::$tokenCached = true;
	}

	/**
	 * Returns the session token of the currently logged in user or null
	 * if no session is active.
	 */
	public static function getToken(): ?SessionToken {
		global $conf;
		if (!self::$tokenCached) {
			self::$tokenCached = true;
			$cookie = @$_COOKIE[$conf['cookie']];
			if ($cookie) {
				self::$token = SessionToken::accept($cookie, TokenType::Session);
			}
		}
		return self::$token;
	}

	/**
	 * If the user isn't logged in, redirect them to the login page and
	 * kill the script. Otherwise, return the token.
	 */
	public static function requireLogin(): SessionToken {
		$token = self::getToken();
		if ($token === null) {
			$uri = '/login.php?redir=' . urlencode($_SERVER['REQUEST_URI']);
			header('Location: ' . $uri);
			die();
		} else {
			return $token;
		}
	}
}

class Database
{
	protected static ?Database $instance = null;
	public PDO $dbh;
	protected ?PDOStatement $lookup_stmt = null;
	protected ?PDOStatement $group_stmt = null;

	protected function __construct() {
		global $conf, $IN_TEST;
		$this->dbh = new PDO($conf['pdo_dsn'], $conf['pdo_user'], $conf['pdo_pass']);
		// SQLite doesn't enforce foreign key relations by default.
		$this->dbh->exec('PRAGMA foreign_keys = ON;');
		// Throw an exception on error.
		// This is already the default (since 8.0.0), but I want to be explicit.
		$this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

		if (isset($IN_TEST)) return; // tests/bootstrap.php will initialize the db.

		// Ensure the database version isn't mismatched.
		$version = $this->dbh->query('PRAGMA user_version')->fetch()['user_version'];
		if ($version !== 3) {
			throw new Exception('Incorrect user_version. Run bin/migrate.php.');
		}
	}

	public static function getInstance(): Database {
		if (self::$instance === null) {
			self::$instance = new Database();
		}
		return self::$instance;
	}

	/** @return array{id: int, email: string, username: ?string, password: ?string,
	 *                fullname: ?string, start_year: ?int, transcript_id: ?int,
	 *                legacy_id: string, first_name: ?string, last_name: ?string}
	 */
	public function getUser(int $uid): ?array {
		$res = $this->runStmt('
			SELECT
			id, email, username, password, fullname, start_year, transcript_id, legacy_id
			FROM users WHERE id = ?
		', [$uid])->fetch(PDO::FETCH_ASSOC);
		if ($res === false) return null;

		// Backwards compat stuff.
		if ($res['legacy_id'] === null) {
			// Pad to 20 characters - the previous IDs were 20 character long.
			$lid = "NOTLEGACY";
			$lid = $lid . str_pad($res['id'], 20 - strlen($lid), '0', STR_PAD_LEFT);
			$res['legacy_id'] = $lid;
		}
		// Yeah, this is stupid, but so is having a separate first name
		// and last name field in the first place.
		if ($res['fullname'] !== null) {
			[$res['first_name'], $res['last_name']] = explode(' ', $res['fullname']);
		}
		// This seems to fix some issues with the OAuth plugin on the forum.
		if (!isset($res['first_name'])) $res['first_name'] = '';
		if (!isset($res['last_name']))  $res['last_name'] = '';
		return $res;
	}

	public function getGroups(int $id): ?array {
		$stmt = $this->runStmt('
			SELECT "group"
			FROM usergroups WHERE user = ?
		', [$id]);
		$groups = [];
		while (($res = $stmt->fetch(PDO::FETCH_NUM))) {
			$groups[] = $res[0];
		}
		return $groups;
	}

	public function runStmt(string $tmpl, array $param): PDOStatement {
		// TODO cache statements?
		$stmt = $this->dbh->prepare($tmpl);
		if ($stmt && $stmt->execute($param)) {
			return $stmt;
		} else {
			// This branch shouldn't ever be taken, as PDO::ATTR_ERRMODE is set
			// to PDO::ERRMODE_EXCEPTION, but hey - better safe than sorry.
			mylog('runStmt failed');
			die();
		}
	}

	public function beginTransaction(): bool { return $this->dbh->beginTransaction(); }
	public function commit(): bool { return $this->dbh->commit(); }
}

/**
 * A wrapper around mail() that sends a HTML email with the correct headers.
 */
function mymail(string $to, string $subject, string $contents): bool {
	global $conf;
	// mail() is a... problematic function.
	// I should probably use something like PHPMailer instead - it'll probably
	// stand the test of time - but I'm stubborn about not using any libraries
	// for this project, so mail() it is.
	// After all, the documentation doesn't warn me not to use it...
	if ($conf['email_override'] !== null) {
		$to = $conf['email_override'];
	}
	return mail($to, $subject, $contents, [
		'From' => $conf['email'],
		'MIME-Version' => '1.0',
		'Content-Type' => 'text/html; charset=utf-8',
	]);
}

/**
 * A wrapper around error_log that logs the source file and line information.
 */
function mylog(string $to): void {
	$b = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
	error_log( pathinfo($b['file'])['basename'] . ':' . $b['line'] . ' ' . $to );
}

/**
 * A wrapper around htmlspecialchars, mostly inspired by the one Dokuwiki has.
 * This one is mostly meant to get static analyzers to shut the fuck up
 * about me passing a potentially non-string argument to htmlspecialchars.
 * @psalm-suppress ForbiddenCode
 */
function hsc(mixed $v): string {
	return htmlspecialchars(strval($v));
}

require(__DIR__ . '/../config/default.php');
if (!isset($IN_TEST)) {
	require(__DIR__ . '/../config/local.php'); // You need to create this yourself.
} else {
	require(__DIR__ . '/../config/test.php');
}

require(__DIR__ . '/template.php');
