<?php
/* Removes expired tokens and sessions from the database.
 * Should run pretty frequently, at least once per day.
 * TODO add example crontab file */

require(__DIR__ . '/../src/common.php');

Database::getInstance()->runStmt('
	DELETE FROM tokens
	WHERE expires < ?
', [time()]);

// Sessions don't have an explicit "expires" field (maybe they should?),
// so I'm subtracting the lifetime of the Session token, as it doesn't make
// sense for sessions to last longer than that anyways.
Database::getInstance()->runStmt('
	DELETE FROM sessions
	WHERE ctime < ?
', [time() - $conf['expires'][TokenType::Session->value]]);
