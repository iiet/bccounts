<?php

enum TokenType: string
{
	/* tokens used for OAuth: */
	case OAuthorization = "a";
	case OAccess        = "c";
	case ORefresh       = "r";

	/* tokens used for the session: */
	case Session        = "s";
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

	public function __construct(TokenType $type, string $service, int $time, string $user) {
		$this->type    = $type;
		$this->service = $service;
		$this->time    = $time;
		$this->user    = $user;
	}

	protected function has_illegal_chars() {
		return str_contains($type->value, ":")
			|| str_contains($this->service, ":")
			|| str_contains($this->user, ":");
	}

	/**
	 * Serializes and signs the token.
	 */
	public function export(): string {
		global $conf;
		if ($this->has_illegal_chars()) {
			// TODO exceptions?
			die("Tried to create a bad token, something is fucked.");
		}
		$tok = $this->type->value . ":"
		     . $this->service . ":"
		     . $this->time . ":"
		     . $this->user;
		$mac = hash_hmac("sha256", $tok, $conf["token_secret"]);
		return $mac . ":" . $tok;
	}

	/**
	 * Validates an exported token and checks if it's the expected type.
	 * If it is - returns the parsed token.
	 */
	public static function accept(string $fulltok, TokenType $type): ?Token {
		global $conf;
		[$usermac, $tok] = explode(":", $fulltok, 2);
		$mac = hash_hmac("sha256", $tok, $conf["token_secret"]);
		if (!hash_equals($mac, $usermac)) {
			return null;
		}

		[$usertype, $service, $time, $user] = explode(":", $tok, 4);
		$obj = new Token(TokenType::from($usertype), $service, $time, $user);

		// Let's validate it.
		if ($obj->has_illegal_chars()) {
			die("Successfully validated an illegal token?");
		}
		if ($obj->type !== $type) {
			return null;
		}
		if ($obj->time + $obj->maxlifetime() < time()) {
			return null;
		}

		return $obj;
	}

	public function maxlifetime(): int {
		global $conf;
		return $conf["expires"][$this->type->value];
	}
}

$conf = require(__DIR__ . "/../config.php");
