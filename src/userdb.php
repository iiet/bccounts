<?php
/* Mock implementation */

class UserDB
{
	protected static $instance = null;

	protected function __construct() {}

	public static function getInstance() {
		if (self::$instance === null) {
			self::$instance = new UserDB();
		}
		return self::$instance;
	}

	public function lookup(string $user) {
		return array(
			'legacyid' => 'iamalegacyid',
			/* 'password' */
			'password' => '$2y$10$ItF35aNuzrTnADVCpUhMduOr02bloDeEmGeDzwJ8JLW.EaRYnz5SW',
			'generation' => '0',
			'first_name' => 'John',
			'last_name' => 'Smith',
			'email' => 'johnsmith@example.org',
			'start_year' => 1970,
			'transcript_id' => 696969,
			'groups' => array('red', 'blue'),
		);
	}

	public function check_generation(string $user, string $generation): bool {
		$data = $this->lookup($user);
		return $data['generation'] === $generation;
	}
}
