<?php
// The default configuration file.
// Ideally you'd make a config/local.php from scratch and only change the
// settings you care about.

// Technically speaking I can omit initializizing the arrays, and I think
// DokuWiki does that, but PHP's manual discourages it.
$conf = array();

/* The name of the cookie used to store the session token. */
$conf['cookie'] = 'bccounts_token';

$conf['pdo_dsn']  = 'sqlite:/path/to/bccounts.db';
$conf['pdo_user'] = null;
$conf['pdo_pass'] = null;

$conf['passminlen'] = 12;

/* The second and third arguments to password_hash.
 * You should tune this for your server - see bin/bcryptbench.php
 */
$conf['passhash'] = [PASSWORD_BCRYPT, ['cost' => 12]];

/* Email is sent using PHP's builtin mail() function, which uses sendmail,
 * so you need to configure that too. (Please don't actually use sendmail
 * though, use some compatible alternative).
 * Note that, for development, Debian seems to already ship with Exim -
 * you can send emails to localhost and read them with a client such as
 * Claws Mail. */
$conf['email'] = 'iiet.pl <bccounts@example.org>';

/* The minimum delay between two emails sent to the same user, in seconds. */
$conf['email_ratelimit'] = 3600;

/* Send all emails to this address instead.
 * Meant to be used only for development. */
$conf['email_override'] = null;

/* Appened to the transcript_id in order to get an email. */
$conf['studentsuffix'] = '@student.example.org';

$conf['mydomain'] = 'http://localhost:8080';

/* The token needed to use reqtoken.php. */
$conf['shared_token'] = null;

$conf['services'] = array();

if (false) {
	// The key is used to identify the service internally and it might be
	// visible to the user.
	$conf['services']['test'] = [
		// A regex specifying the allowed redirect URIs.
		// Note that you can use a custom delimiter:
		// https://www.php.net/manual/en/regexp.reference.delimiters.php
		// Don't forget the ^ and be careful about dots.
		'redirect_uri' => '~^https://oauthdebugger\.com/debug~',
		'client_id' => 'testasdf',
		'client_secret' => '{clientSecret}',
	];
}

$conf['expires'] = array();

/* The authorization code MUST expire shortly after it is issued to
 * mitigate the risk of leaks.  A maximum authorization code lifetime
 * of 10 minutes is RECOMMENDED.   -- RFC 6749
 *
 * I'm using ->value because PHP doesn't support using enum values as array
 * keys. */
$conf['expires'][TokenType::OAuthorization->value] = 30;

/* The lifetime of access tokens determines how long sessions in other services
 * will linger after the user logs out from SSO.
 *
 * If it's too low, it'll make things slow.
 * If it's too high, sessions will stay around longer than expected, which
 * could cause issues on shared computers.
 * 4 hours seem like a reasonable compromise? */
$conf['expires'][TokenType::OAccess->value] = 4 * 60 * 60;

/* The session token is used for the session cookie. It doesn't get refreshed,
 * so the user has to login every time it expires. This is intentional (but I
 * already forgot why...).
 * There's no reason for the refresh token to last shorter than the session,
 * and it can't outlive it, so I'm just setting them both to the same value. */
$conf['expires'][TokenType::Session->value]  = 6 * 30 * 24 * 60 * 60;
$conf['expires'][TokenType::ORefresh->value] = 6 * 30 * 24 * 60 * 60;

/* Password recovery tokens. */
$conf['expires']['recovery'] = 24 * 60 * 60;

// The services listed on the front page.
$conf['frontservices'] = [
	'Example' => 'https://example.com',
	'Another example' => 'https://example.com',
	'OAuthDebugger' => 'https://oauthdebugger.com',
];

$conf['sourcelink'] = 'https://git.iiet.pl/iiet/bccounts';

/* Used in the footer and register form. */
$conf['contactlink'] = 'https://example.com';
$conf['contactname'] = 'Example Organization';

/* Used by appapi.php. */
$conf['appapi_users'] = [
	// 'aid' => 'auth_token',
];
