<?php
require(__DIR__ . "/../src/common.php");

function redirect_back(string $uri, array $param) {
	$param["state"] = $_GET["state"];
	$uri = $_GET["redirect_uri"] . "?" . http_build_query($param);
	header("Location: " . $uri);
	die();
}

// RFC6749, 4.1.2.1.
function redirect_error(string $uri, string $error, string $desc) {
	redirect_back($uri, array(
		"error" => $error,
		"error_description" => $desc,
	));
}

function json_error(int $code, string $error, ?string $desc) {
	// 5.2.
	// I'm assuming Content-Type was already set
	http_response_code(400);
	$param = array("error" => $error);
	if ($desc) $param["error_description"] = $desc;
	echo json_encode($param);
	die();
}

function find_service(string $client_id) {
	global $conf;
	foreach ($conf["services"] as $k => $service) {
		if ($service["cid"] === $client_id) {
			return [$k, $service];
		}
	}
	return false;
}

header("Cache-Control: no-store");
header("Pragma: no-cache");

$endpoint = $_SERVER["PATH_INFO"];
if ($endpoint === "/authorize") {
	[$serviceName, $service] = find_service(@$_GET["client_id"]);
	if (!$serviceName) {
		http_response_code(403);
		die("Bad client_id.");
	}

	$uri = @$_GET["redirect_uri"];
	if (!preg_match($service["url"], $uri)) {
		http_response_code(403);
		die("Bad redirect_uri.");
	}

	if (@$_GET["response_type"] != "code") {
		redirect_error($uri, "unsupported_response_type", "i only support response_type=code");
	}

	$tok = new Token(TokenType::OAuthorization, $serviceName, time(), "testuser");
	redirect_back($uri, array(
		"code" => $tok->export(),
	));
} else if ($endpoint === "/token") { // RFC6749, 4.4.
	header("Content-Type: application/json;charset=UTF-8");

	// 4.1.3. authorization_code
	// the RFC says i MUST check the redirect_uri
	// i am instead going to be stupid and ignore that
	// TODO or just enforce a single redirect_uri. honestly it's simpler

	if (@$_POST["grant_type"] !== "authorization_code") {
		json_error(400, "unsupported_grant_type", "i only support grant_type=authorization_code");
	}

	// 2.3.
	// TODO support Basic auth
	$client_id = @$_POST["client_id"];
	$client_secret = @$_POST["client_secret"];

	[$serviceName, $service] = find_service($client_id);
	if (!$serviceName) {
		json_error(400, "invalid_client", "client_id not recognized");
	}
	if (!hash_equals($service["secret"], $client_secret)) {
		json_error(400, "invalid_client", "bad client_secret");
	}

	// TODO a separate error for the token expiring would be nice
	$tokAuth = Token::accept(@$_POST["code"], TokenType::OAuthorization);
	if (!$tokAuth || $tokAuth->service !== $serviceName) {
		json_error(400, "invalid_grant", null);
	}

	/* If an authorization code is used more than once, the authorization
	 * server MUST deny the request and SHOULD revoke (when possible) all
	 * tokens previously issued based on that authorization code.
	 * The authorization code is bound to the client identifier and
	 * redirection URI.
	 *
	 * I'm not doing that. This is not very good software. */

	$tokAcc = new Token(TokenType::OAccess, $tokAuth->service, time(), $tokAuth->user);
	echo json_encode(array(
		"access_token" => $tokAcc->export(),
		"access_token2" => $tokAcc,
		"token_type" => "Bearer",
		"expires_in" => $tokAcc->maxlifetime(),
		// TODO refresh tokens
		// "refresh_token" => $tokmgrcreate(TokenType:ORefresh, $tokService, $tokUser),
	));
} else if ($endpoint === "/userinfo") {
	// Bearer is described in RFC6750
	[$method, $rawtok] = explode(" ", @$_SERVER["HTTP_AUTHORIZATION"], 2);
	if ($method !== "Bearer") {
		http_response_code(401);
		header("WWW-Authenticate: Bearer");
		die();
	}
	$tok = Token::accept($rawtok, TokenType::OAccess);
	if (!$tok) {
		http_response_code(401);
		header('WWW-Authenticate: Bearer error="invalid_token"');
		die();
	}
	echo json_encode(array(
		// "user_id" => "???",
		"login" => $tok->user,
		// "first_name" => "",
		// "last_name" => "",
		// "email" => "",
		// "start_year" => 1970,
		"groups" => ["students"],
	));
} else {
	http_response_code(404);
	echo "Bad oauth endpoint.";
}
