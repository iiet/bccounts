<?php

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
 * Represents an authenticated, valid "token" verifying permission to do
 * something as the user. Used for OAuth2 tokens and session tokens. */
class Token
{
	public TokenType $type;
	public string    $service;
	public int       $expires;
	public int       $session;
	private ?int     $user = null;

	protected ?string $repr = null;

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
		if ($this->repr !== null) {
			return $this->repr;
		}
		// Generate 32*8=256 bits of entropy and encode it with base64url.
		// random_bytes is safe for crypto uses.
		$repr = str_replace(['+', '/', '='], ['-', '_', ''],  base64_encode(random_bytes(32)));
		// Prepend it with the time because I feel like it.
		$repr = time() . '_' . $repr;
		Database::getInstance()->runStmt('
			INSERT INTO tokens (token, session, type, expires, service)
			VALUES (?, ?, ?, ?, ?)
		', [$repr, $this->session, $this->type->value, $this->expires, $this->service]);
		return $repr;
	}

	public function getUserID(): int {
		$stmt = Database::getInstance()->runStmt('
			SELECT user
			FROM sessions
			WHERE id = ?
		', [$this->session]);
		$res = $stmt->fetch();
		return $res['user'];
	}

	/**
	 * Fetches a token from the database, and verifies that it's a valid
	 * token of the expected type.
	 */
	public static function accept(string $repr, TokenType $type): ?Token {
		global $conf;

		$stmt = Database::getInstance()->runStmt('
			SELECT tokens.session, tokens.expires, sessions.expires, tokens.service
			FROM tokens
			JOIN sessions ON tokens.session = sessions.id
			WHERE token = ? AND type = ?
		', [$repr, $type->value]);
		$res = $stmt->fetch();
		if ($res === false) return null;
		[$session, $tokExpires, $sessExpires, $service] = $res;

		$time = time();
		if ($tokExpires < $time || $sessExpires < $time) {
			return null;
		}
		return new Token($type, $service, $tokExpires, $session);
	}
}

abstract class MySession
{
	private static ?Token $token = null;
	private static bool $tokenCached = false;

	public static function login(string $user, #[\SensitiveParameter] string $pass): bool {
		global $conf;

		// TODO log "weird" errors
		$stmt = Database::getInstance()->runStmt('
			SELECT id, password
			FROM USERS
			WHERE username = ? OR email = ?
		', [$user, $user]);
		if (!$stmt) return false;
		// I'm assuming we never get multiple results - usernames can't contain @.
		$res = $stmt->fetch();
		if (!$res) return false;
		[$id, $hash] = $res;
		if (!password_verify($pass, $hash)) return false;

		// Alright, the password is correct. Let's create a new session.
		// RETURNING is an SQLite extension (that I think came from PostgreSQL?)
		// It's much saner than lastInsertId, IMO.
		$time = time();
		$expires = $time + $conf['expires'][TokenType::Session->value];
		$stmt = Database::getInstance()->runStmt('
			INSERT INTO sessions (user, ctime, expires, ip)
			VALUES (?, ?, ?, ?)
			RETURNING id
		', [ $id, $time, $expires, $_SERVER['REMOTE_ADDR'] ]);
		if (!$stmt) return false;
		$res = $stmt->fetch();
		if ($res == false) return false;

		// Create and save the token.
		$tok = new Token(TokenType::Session, '', $expires, $res[0]);
		self::setToken($tok);
		return true;
	}

	public static function logout(int $session): void {
		$token = self::getToken();
		if ($token && $token->session == $session) {
			self::setToken(null);
		}

		// If after deleting the session some tokens remain in the database,
		// the session ID they point to might be reused, which gives the
		// token holder access to another account!

		// The foreign key relation on tokens *should* automatically delete
		// all the tokens linked to the session, and AUTOINCREMENT on sessions
		// should prevent ID reuse, but, just to be sure, let's delete the
		// tokens anyways.
		$stmt = Database::getInstance()->runStmt('
			DELETE FROM tokens
			WHERE session = ?
		', [$session]);
		if (!$stmt) die(); // Not taking any chances.

		$stmt = Database::getInstance()->runStmt('
			DELETE FROM sessions
			WHERE id = ?
		', [$session]);
		if (!$stmt) die();
	}

	private static function setToken(?Token $tok): void {
		global $conf;
		// To unset the cookie, we set its expiry time in the past.
		setcookie($conf['cookie'], $tok ? $tok->export() : '', array(
			'expires' => $tok ? $tok->expires : 1,
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
	public static function getToken(): ?Token {
		global $conf;
		if (!self::$tokenCached) {
			self::$tokenCached = true;
			$cookie = @$_COOKIE[$conf['cookie']];
			if ($cookie) {
				self::$token = Token::accept($cookie, TokenType::Session);
			}
		}
		return self::$token;
	}

	/**
	 * If the user isn't logged in, redirect them to the login page and
	 * kill the script.
	 */
	public static function requireLogin(): void {
		if (self::getToken() !== null) return;
		$uri = '/login.php?redir=' . urlencode($_SERVER['REQUEST_URI']);
		header('Location: ' . $uri);
		die();
	}
}

class Database
{
	protected static $instance = null;
	protected PDO $dbh;
	protected ?PDOStatement $lookup_stmt = null;
	protected ?PDOStatement $group_stmt = null;

	protected function __construct() {
		global $conf;
		$this->dbh = new PDO($conf['pdo_dsn'], $conf['pdo_user'], $conf['pdo_pass']);
		// SQLite doesn't enforce foreign key relations by default.
		$this->dbh->exec('PRAGMA foreign_keys = ON;');

	}

	public static function getInstance() {
		if (self::$instance === null) {
			self::$instance = new Database();
		}
		return self::$instance;
	}

	public function getUser(int $uid): ?array {
		$stmt = $this->runStmt('
			SELECT
			id, email, username, fullname, start_year, transcript_id, legacy_id
			FROM users WHERE id = ?
		', [$uid]);
		$res = $stmt->fetch(PDO::FETCH_ASSOC);
		if ($res === false) return null;

		// Backwards compat stuff.
		if ($res['legacy_id'] === null) {
			$res['legacy_id'] = 'new_' . $res['id'];
		}
		// Yeah, this is stupid, but so is having a separate first name
		// and last name field in the first place.
		[$res['first_name'], $res['last_name']] = explode(' ', $res['fullname']);
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

	public function runStmt(string $tmpl, array $param): ?PDOStatement {
		$stmt = $this->dbh->prepare($tmpl);
		if ($stmt && $stmt->execute($param)) {
			return $stmt;
		} else {
			return null;
		}
	}
}

$conf = require(__DIR__ . '/../config.php');
require(__DIR__ . '/template.php');
