<?php
/* Removes expired tokens and sessions from the database.
 * Should run pretty frequently, at least once per day.
 * TODO add example crontab file */

require(__DIR__ . '/../src/common.php');

Database::getInstance()->runStmt('
	DELETE FROM tokens
	WHERE expires IS NOT NULL AND expires < ?
', [time()]);

// I'm relying on the foreign key relation to automatically delete tokens
// linked to the expired sessions.
Database::getInstance()->runStmt('
	DELETE FROM sessions
	WHERE expires < ?
', [time()]);
