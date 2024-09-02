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
	// Handle URIs that already contain query parameters.
	// TODO: what about uri fragments?
	if (str_contains($uri, '?')) {
		$uri = $uri . '&';
	} else {
		$uri = $uri . '?';
	}
	$uri = $uri . http_build_query($param);
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

/** @return ?array{string, array{redirect_uri: non-empty-string,
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

	/**
	 * We're checking if the URI matches the configured.
	 * All regexes *should* begin with a ^ (there's a note about it),
	 * so an open redirect shouldn't be possible if the server isn't
	 * misconfigured.
	 *
	 * Some regexes might not end with a $, so the redirect_uri could include
	 * newlines in an attempt to inject headers, but header() checks for that.
	 * This is undocumented behaviour (what the FUCK, php?), but you can verify
	 * that the check is there in sapi_header_op.
	 * @psalm-taint-escape header
	 */
	$uri = @$_GET['redirect_uri'];
	if (!is_string($uri) || preg_match($service['redirect_uri'], $uri) !== 1) {
		http_response_code(403);
		die('Bad redirect_uri.');
	}

	if (@$_GET['response_type'] != 'code') {
		// RFC6749, 4.1.2.1. Error Response
		// We've verified that the redirect URI is valid, so we can use it now.
		redirect_back($uri, array(
			'error' => 'unsupported_response_type',
			'error_description' => 'i only support response_type=code',
		));
	}

	// TODO ->setService
	$tok = new SessionToken(
		TokenType::OAuthorization, $serviceName,
		null, $sessToken->getSessionID()
	);
	$tok->setRedirectURI($uri);
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
		// RFC6749, 4.1.3. Access SessionToken Request

		// The RFC says I MUST check the redirect_uri.
		// I don't need to do that, as there's only one valid redirect_uri per
		// service anyways.  If you want to change that, you'd need to add a
		// redirect_uri field to the tokens table.

		$code = @$_POST['code'];
		if (!is_string($code)) {
			json_error(400, 'invalid_client', 'invalid code parameter');
		}

		// Don't allow reuse of authorization codes per the RFC.
		$tokAuth = SessionToken::acceptOnce($code, TokenType::OAuthorization);
		if (!$tokAuth || $tokAuth->getService() !== $serviceName) {
			json_error(400, 'invalid_grant', null);
		}

		// If we were provided a redirect URI, verify that it looks correct.
		if (isset($_POST['redirect_uri'])) {
			$uri = $_POST['redirect_uri'];
			// The is_string check is redundant, I just want to be verbose.
			if (!is_string($uri) || $uri !== $tokAuth->getRedirectURI()) {
				json_error(400, 'invalid_grant', 'mismatched redirect_uri');
			}
		}

		$tokAcc     = $tokAuth->setTypeAndRefresh(TokenType::OAccess);
		$tokRefresh = $tokAuth->setTypeAndRefresh(TokenType::ORefresh);

		echo json_encode(array(
			'access_token' => $tokAcc->export(),
			'refresh_token' => $tokRefresh->export(),
			'token_type' => 'Bearer',
			'expires_in' => $conf['expires'][TokenType::OAccess->value],
		)) . "\n";
	} else if ($grant_type == 'refresh_token') {
		$code = @$_POST['refresh_token'];
		if (!is_string($code)) {
			json_error(400, 'invalid_client', 'invalid refresh_token parameter');
		}

		$tokRefresh = SessionToken::accept(@$code, TokenType::ORefresh);
		if (!$tokRefresh || $tokRefresh->getService() !== $serviceName) {
			json_error(400, 'invalid_grant', null);
		}

		$tokAcc = $tokRefresh->setTypeAndRefresh(TokenType::OAccess);
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
	header('Content-Type: application/json;charset=UTF-8');

	// Bearer is described in RFC6750
	$rawtok = null;
	$http_auth = @$_SERVER['HTTP_AUTHORIZATION'];
	if (is_string($http_auth) && str_starts_with($http_auth, 'Bearer ')) {
		// RFC6750, 2.1. Authorization Request Header Field
		[, $rawtok] = explode(' ', $http_auth, 2);
	} else if (isset($_GET['access_token'])) {
		// RFC6750, 2.3. URI Query Parameter
		$rawtok = $_GET['access_token'];
	} else {
		http_response_code(401);
		header('WWW-Authenticate: Bearer');
		die();
	}

	$tok = SessionToken::accept($rawtok, TokenType::OAccess);
	if (!$tok) {
		http_response_code(401);
		header('WWW-Authenticate: Bearer error="invalid_token"');
		die();
	}
	$data   = Database::getInstance()->getUser($tok->getUserID());
	$groups = Database::getInstance()->getGroups($tok->getUserID());
	assert($data !== null);
	if (@$_GET['compat'] === 'public') {
		// Output compatible with the old /public endpoint.
		// I need it, because enroll-me crashes when it receives an
		// unrecognized key in the response... Fucking brillant.
		echo json_encode(array(
			'user_id'    => $data['legacy_id'],
			'login'      => $data['username'],
		)) . "\n";
	} else {
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
	}
} else {
	http_response_code(404);
	echo 'Bad oauth endpoint.';
}
