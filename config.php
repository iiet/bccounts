<?php

return array(
	"token_secret" => "penis",

	"services" => array(
		// the key is user-visible, used in e.g. tokens
		"enroll" => array(
			"url" => "/^https:\/\/enroll-me.iiet.pl\//",
			"cid" => "enroll",
			"secret" => "blahblah",
		),
		"test" => array(
			"url" => "/^https:\/\/oauthdebugger.com\//",
			"cid" => "testasdf",
			"secret" => "{clientSecret}",
		),
		/* user visible key => array(
		 *     "url" => regex that matches the allowed redirect urls,
		 *     "cid" => client id,
		 * )
		 */
	),

	"expires" => array(
		/* I'm using ->value because PHP doesn't support using enum values as
		 * array keys. */

		/* The authorization code MUST expire shortly after it is issued to mitigate
		 * the risk of leaks.  A maximum authorization code lifetime of 10 minutes
		 * is RECOMMENDED. */
		TokenType::OAuthorization->value => 30,

		TokenType::OAccess->value        => 24 * 60 * 60,
	),
);
