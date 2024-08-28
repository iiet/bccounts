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
 * something as the user. Used for OAuth2 tokens, and for the session tokens (TODO). */
class Token
{
	public TokenType $type;
	public string    $service;
	public int       $time; /* creation time */
	public string    $user;
	public string    $generation;

	public function __construct(
		TokenType $type, string $service, int $time,
		string $user, string $generation
	) {
		$this->type       = $type;
		$this->service    = $service;
		$this->time       = $time;
		$this->user       = $user;
		$this->generation = $generation;
	}

	protected function has_illegal_chars() {
		return str_contains($this->type->value, ':')
			|| str_contains($this->service, ':')
			|| str_contains($this->user, ':')
			|| str_contains($this->generation, ':');
	}

	/**
	 * Serializes and signs the token.
	 */
	public function export(): string {
		global $conf;
		if ($this->has_illegal_chars()) {
			// TODO exceptions?
			die('Tried to create a bad token, something is fucked.');
		}
		$tok = $this->type->value . ':'
		     . $this->service . ':'
		     . $this->time . ':'
		     . $this->user . ':'
		     . $this->generation;
		$mac = hash_hmac('sha256', $tok, $conf['token_secret']);
		return $mac . ':' . $tok;
	}

	/**
	 * Validates an exported token and checks if it's the expected type.
	 * If it is - returns the parsed token.
	 */
	public static function accept(string $fulltok, TokenType $type): ?Token {
		global $conf;
		[$usermac, $tok] = explode(':', $fulltok, 2);
		$mac = hash_hmac('sha256', $tok, $conf['token_secret']);
		if (!hash_equals($mac, $usermac)) {
			return null;
		}

		[$usertype, $service, $time, $user, $gen] = explode(':', $tok, 5);
		if ($gen === null) {
			return null;
		}
		$obj = new Token(TokenType::from($usertype), $service, $time, $user, $gen);

		// Let's validate it.
		if ($obj->has_illegal_chars()) {
			die('Successfully validated an illegal token?');
		}
		if ($obj->type !== $type) {
			return null;
		}
		if ($obj->time + $obj->maxlifetime() < time()) {
			return null;
		}
		if (!UserDB::getInstance()->check_generation($user, $gen)) {
			return null; // This token was born in the wrong generation
		}

		return $obj;
	}

	public function maxlifetime(): int {
		global $conf;
		return $conf['expires'][$this->type->value];
	}
}

abstract class MySession
{
	private static ?Token $token = null;
	private static bool $tokenCached = false;

	public static function setToken(Token $tok): void {
		global $conf;
		assert($tok->type === TokenType::Session);
		assert($tok->service === '');
		setcookie($conf['cookie'], $tok->export(), array(
			'expires' => $tok->time + $tok->maxlifetime(),
			'httponly' => true,
			'path' => '/',
			'secure' => true,
		));
		self::$token = $tok;
		self::$tokenCached = true;
	}

	public static function unsetToken(): void {
		global $conf;
		setcookie($conf['cookie'], '', array(
			'expires' => 1,
			'httponly' => true,
			'path' => '/',
			'secure' => true,
		));
		self::$token = null;
		self::$tokenCached = true;
	}

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

	public static function tryRefresh(): void {
		$token = self::getToken();
		if (!$token) return;
		// TODO maybe I should only refresh tokens of a certain age?
		self::setToken(new Token(
			TokenType::Session, '', time(), $token->user, $token->generation
		));
	}

	// Not directly related to managing sessions, but useful enough.
	public static function requireLogin(): void {
		if (self::getToken() !== null) return;
		echo '<pre>';
		$uri = '/login.php?redir=' . urlencode($_SERVER['REQUEST_URI']);
		header('Location: ' . $uri);
		die();
	}
}

$conf = require(__DIR__ . '/../config.php');
require(__DIR__ . '/template.php');
require(__DIR__ . '/userdb.php');
