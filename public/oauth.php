<?php
/* A mostly self contained implementation of an OAuth2 server.
 * https://oauth.net/specs/
 * RFC 6749 is the important one. It might look scary, but this is actually
 * a pretty simple protocol to implement. Give it a read.
 */

require(__DIR__ . '/../src/common.php');

/**
 * Redirect the user to the specified redirect URI with the given query
 * parameters. Only relevant for /authorize.
 */
function redirect_back(string $uri, array $param): never {
	$param['state'] = $_GET['state'];
	$uri = $uri . '?' . http_build_query($param);
	header('Location: ' . $uri);
	die();
}

/**
 * Returns a JSON-formatted error from the token endpoint,
 * as specified by RFC 6749, 5.2. Error Response
 */
function json_error(int $code, string $error, ?string $desc): never {
	// I'm assuming Content-Type was already set
	http_response_code(400);
	$param = array('error' => $error);
	if ($desc !== null) {
		$param['error_description'] = $desc;
	}
	echo json_encode($param) . "\n";
	die();
}

/** @return ?array{string, array{redirect_uri: string,
  *                              client_id: string,
  *                              client_secret: string}} */
function find_service(string $client_id): ?array {
	global $conf;
	foreach ($conf['services'] as $k => $service) {
		if ($service['client_id'] === $client_id) {
			return [$k, $service];
		}
	}
	return null;
}

// RFC 6749, 5.1. Successful Response
// To keep stuff simple I'm just setting those headers up front.
header('Cache-Control: no-store');
header('Pragma: no-cache');

$endpoint = $_SERVER['PATH_INFO'];
if ($endpoint === '/authorize') {
	MySession::requireLogin();
	$sessToken = MySession::getToken();
	$client_id = @$_GET['client_id'];
	assert($sessToken !== null);

	if (!is_string($client_id)) { // Satisfy Psalm.
		http_response_code(403);
		die('Bad client_id.');
	}

	[$serviceName, $service] = find_service($client_id);
	if ($serviceName === null) {
		http_response_code(403);
		die('Bad client_id.');
	}
	assert($service !== null); // Get Psalm to shut up.

	$uri = @$_GET['redirect_uri'];
	if ($uri !== $service['redirect_uri']) {
		http_response_code(403);
		die('Bad redirect_uri.');
	}
	$uri = $service['redirect_uri']; // This fixes 3 "errors" in Psalm. Yeah.

	if (@$_GET['response_type'] != 'code') {
		// RFC6749, 4.1.2.1. Error Response
		// We've verified that the redirect URI is valid, so we can use it now.
		redirect_back($uri, array(
			'error' => 'unsupported_response_type',
			'error_description' => 'i only support response_type=code',
		));
	}

	$tok = new Token(TokenType::OAuthorization, $serviceName, null, $sessToken->session);
	redirect_back($uri, array(
		'code' => $tok->export(),
	));
} else if ($endpoint === '/token') { // RFC6749, 4.4.
	header('Content-Type: application/json;charset=UTF-8');

	// RFC6749, 2.3. Client Authentication
	if (isset($_SERVER['PHP_AUTH_USER']) && isset($_SERVER['PHP_AUTH_PW'])) {
		// HTTP Basic authentication
		$client_id     = $_SERVER['PHP_AUTH_USER'];
		$client_secret = $_SERVER['PHP_AUTH_PW'];
	} else if (isset($_POST['client_id']) && isset($_POST['client_secret'])) {
		// "Inline" credentials.
		// Used by OAuthDebugger
		$client_id     = $_POST['client_id'];
		$client_secret = $_POST['client_secret'];
	} else {
		json_error(400, 'invalid_client', 'no authorization method used');
	}

	if (!is_string($client_id) || !is_string($client_secret)) {
		json_error(400, 'invalid_client', 'bad authorization params');
	}

	[$serviceName, $service] = find_service($client_id);
	if ($serviceName === null) {
		json_error(400, 'invalid_client', 'client_id not recognized');
	}
	assert($service !== null);
	if (!hash_equals($service['client_secret'], $client_secret)) {
		json_error(400, 'invalid_client', 'bad client_secret');
	}

	$grant_type = @$_POST['grant_type'];
	if ($grant_type === 'authorization_code') {
		// RFC6749, 4.1.3. Access Token Request

		// The RFC says I MUST check the redirect_uri.
		// I don't need to do that, as there's only one valid redirect_uri per
		// service anyways.  If you want to change that, you'd need to add a
		// redirect_uri field to the tokens table.

		$code = @$_POST['code'];
		if (!is_string($code)) {
			json_error(400, 'invalid_client', 'invalid code parameter');
		}

		// Don't allow reuse of authorization codes per the RFC.
		$tokAuth = Token::acceptOnce($code, TokenType::OAuthorization);
		if (!$tokAuth || $tokAuth->service !== $serviceName) {
			json_error(400, 'invalid_grant', null);
		}

		$tokAcc = new Token(TokenType::OAccess, $tokAuth->service, null, $tokAuth->session);
		$tokRefresh = new Token(TokenType::ORefresh, $tokAuth->service, null, $tokAuth->session);

		echo json_encode(array(
			'access_token' => $tokAcc->export(),
			'refresh_token' => $tokRefresh->export(),
			'token_type' => 'Bearer',
			'expires_in' => $conf['expires'][TokenType::OAccess->value],
		)) . "\n";
	} else if ($grant_type == 'refresh_token') {
		// TODO checks if the current implementation checks the client secret on refresh

		$code = @$_POST['refresh_token'];
		if (!is_string($code)) {
			json_error(400, 'invalid_client', 'invalid refresh_token parameter');
		}

		$tokRefresh = Token::accept(@$code, TokenType::ORefresh);
		if (!$tokRefresh || $tokRefresh->service !== $serviceName) {
			json_error(400, 'invalid_grant', null);
		}

		$tokAcc = new Token(TokenType::OAccess, $tokRefresh->service, null, $tokRefresh->session);
		echo json_encode(array(
			'access_token' => $tokAcc->export(),
			'token_type' => 'Bearer',
			'expires_in' => $conf['expires'][TokenType::OAccess->value],
		)) . "\n";
	} else {
		$err = 'the only supported grant_types are authorization_code and refresh_token';
		json_error(400, 'unsupported_grant_type', $err);
	}
} else if ($endpoint === '/userinfo') {
	// Bearer is described in RFC6750
	[$method, $rawtok] = explode(' ', @$_SERVER['HTTP_AUTHORIZATION'], 2);
	if ($method !== 'Bearer') {
		http_response_code(401);
		header('WWW-Authenticate: Bearer');
		die();
	}
	$tok = Token::accept($rawtok, TokenType::OAccess);
	if (!$tok) {
		http_response_code(401);
		header('WWW-Authenticate: Bearer error="invalid_token"');
		die();
	}
	$data   = Database::getInstance()->getUser($tok->getUserID());
	$groups = Database::getInstance()->getGroups($tok->getUserID());
	assert($data !== null);
	echo json_encode(array(
		'user_id'    => $data['legacy_id'],
		'login'      => $data['username'],

		'full_name'  => $data['fullname'],
		'first_name' => $data['first_name'],
		'last_name'  => $data['last_name'],
		'email'      => $data['email'],
		'start_year' => $data['start_year'],
		'groups'     => $groups,
	)) . "\n";
} else {
	http_response_code(404);
	echo 'Bad oauth endpoint.';
}
