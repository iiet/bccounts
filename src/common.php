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

class TokenManager
{
	private string $secret;

	public function __construct(string $secret) {
		$this->secret = $secret;
	}

	public function create(TokenType $type, string $service, string $user) {
		if (str_contains($service, ":") || str_contains($user, ":")) {
			http_response_code(500);
			die("Tried to generate a bad token. Something is fucked.");
		}
		if (str_contains($type->value, ":")) {
			http_response_code(500);
			die("Something is really fucked.");
		}
		$tok = $type->value . ":" . $service . ":" . time() . ":" . $user;
		$mac = hash_hmac("sha256", $tok, $this->secret);
		return $mac . ":" . $tok;
	}

	/**
	 * Validates an untrusted token and checks if it's the expected type.
	 * If it is - returns the data stored in the token.
	 */
	public function accept(string $tok, TokenType $type) {
		global $conf;
		[$usermac, $usertype, $service, $time, $login] = explode(":", $tok, 5);
		if (str_contains($login, ":")) return false;

		// yeah, this is a bit stupid, but I want this to look as close
		// to the hmac gen as possible
		$rawtok = $usertype . ":" . $service . ":" . $time . ":" . $login;
		$mac = hash_hmac("sha256", $rawtok, $this->secret);
		if (!hash_equals($mac, $usermac)) {
			return false;
		}

		// now let's validate it
		$expiry = $conf["expires"][$type->value];
		if ($usertype !== $type->value) return false;
		if (intval($time) + $expiry < time()) return false;

		return [$service, $login];
	}
}

$conf = require(__DIR__ . "/../config.php");
$tokmgr = new TokenManager($conf["token_secret"]);
