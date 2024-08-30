<?php

return array(
	/* The name of the cookie used to store the session token. */
	'cookie' => 'bccounts_token',

	'pdo_dsn' => 'sqlite:/home/dzwdz/src/bccounts/bccounts.db',
	'pdo_user' => null,
	'pdo_pass' => null,

	'services' => array(
		// the key is user-visible, used in e.g. tokens
		'enroll' => array(
			'url' => '/^https:\/\/enroll-me.iiet.pl\//',
			'cid' => 'enroll',
			'secret' => 'blahblah',
		),
		'test' => array(
			'url' => '/^https:\/\/oauthdebugger.com\//',
			'cid' => 'testasdf',
			'secret' => '{clientSecret}',
		),
		/* user visible key => array(
		 *     'url' => regex that matches the allowed redirect urls,
		 *     'cid' => client id,
		 * )
		 */
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
	),
);
