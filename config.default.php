<?php
/* Copy me to config.php and modify to taste. */

return array(
	/* The name of the cookie used to store the session token. */
	'cookie' => 'bccounts_token',

	'pdo_dsn' => 'sqlite:/home/dzwdz/src/bccounts/bccounts.db',
	'pdo_user' => null,
	'pdo_pass' => null,

	'passminlen' => 12,
	/* The second and third arguments to password_hash.
	 * You should tune this for your server - see bin/bcryptbench.php
	 */
	'passhash' => [PASSWORD_BCRYPT, ['cost' => 12]],

	/* Email is sent using PHP's builtin mail() function, which uses sendmail,
	 * so you need to configure that too.
	 * (Please don't actually use sendmail though,
	 *  use some compatible alternative).
	 * Note that, for development, Debian seems to already ship with Exim -
	 * you can send emails to localhost and read them with a client such as
	 * Claws Mail. */
	'email' => 'iiet.pl <bccounts@example.org>',

	/* The minimum delay between two emails sent to the same user, in seconds. */
	'email_ratelimit' => 0, // XXX only for development!

	/* Send all emails to this address instead.
	 * Meant to be used only for development. */
	'email_override' => 'dzwdz@localhost',

	/* Appened to the transcript_id in order to get an email. */
	'studentsuffix' => '@student.example.org',

	'mydomain' => 'http://localhost:8080',

	/* The token needed to use reqtoken.php. */
	'shared_token' => 'CHANGEME',

	'services' => array(
		// The key is used to identify the service internally and it might be
		// visible to the user.
		'test' => array(
			// I'm intentionally only allowing one redirect URI per service.
			// See: comment near the authorization_code handler in oauth.php
			'redirect_uri' => 'https://oauthdebugger.com/debug',
			'client_id' => 'testasdf',
			'client_secret' => '{clientSecret}',
		),
	),

	'expires' => array(
		/* I'm using ->value because PHP doesn't support using enum values as
		 * array keys. */

		/* The authorization code MUST expire shortly after it is issued to
		 * mitigate the risk of leaks.  A maximum authorization code lifetime
		 * of 10 minutes is RECOMMENDED.   -- RFC 6749 */
		TokenType::OAuthorization->value => 30,

		/* The lifetime of this token determines how long sessions in other
		 * services will linger after the user logs out from SSO.
		 *
		 * If it's too low, it'll make things slow.
		 * If it's too high, sessions will stay around longer than expected,
		 * which could cause issues on shared computers.
		 * 4 hours seem like a reasonable compromise? */
		TokenType::OAccess->value        => 4 * 60 * 60,

		TokenType::Session->value        => 6 * 30 * 24 * 60 * 60,
		/* There's no reason for the refresh token to last shorter than the
		 * session. */
		TokenType::ORefresh->value       => 6 * 30 * 24 * 60 * 60,

		/* Password recovery tokens. */
		'recovery'                       => 24 * 60 * 60,
	),

	// The services listed on the front page.
	'frontservices' => array(
		'Example' => 'https://example.com',
		'Another example' => 'https://example.com',
		'OAuthDebugger' => 'https://oauthdebugger.com',
	),

	'sourcelink' => 'https://git.iiet.pl/iiet/bccounts',
	'contactlink' => 'https://example.com',
);
