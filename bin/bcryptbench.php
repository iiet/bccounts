<?php
/**
 * Benchmarks bcrypt's performance on the server, so you can choose a reasonable
 * cost. You probably want to aim at about 100ms.
 */

for ($cost = 10; $cost < 20; $cost++) {
	$start = microtime(true);
	password_hash('agh', PASSWORD_BCRYPT, ['cost' => $cost]);
	$end = microtime(true);
	echo $cost . ': ' . ($end - $start) . "\n";
}
