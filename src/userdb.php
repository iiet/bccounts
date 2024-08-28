<?php

class UserDB
{
	protected static $instance = null;
	protected PDO $dbh;
	protected ?PDOStatement $lookup_stmt = null;
	protected ?PDOStatement $group_stmt = null;

	protected function __construct() {
		global $conf;
		$this->dbh = new PDO($conf['pdo_dsn'], $conf['pdo_user'], $conf['pdo_pass']);
		$this->dbh->exec('PRAGMA foreign_keys = ON;');

	}

	public static function getInstance() {
		if (self::$instance === null) {
			self::$instance = new UserDB();
		}
		return self::$instance;
	}

	public function getUser(string $user): ?array {
		if ($this->lookup_stmt === null) {
			$this->lookup_stmt = $this->dbh->prepare('
				SELECT
				id, email, password, fullname, start_year, transcript_id, legacy_id
				FROM users WHERE username = ?
			');
		}
		$this->lookup_stmt->execute([$user]);
		$res = $this->lookup_stmt->fetch(PDO::FETCH_ASSOC);
		if ($res === false) return null;

		if ($res['legacy_id'] === null) {
			$res['legacy_id'] = 'new_' . $res['id'];
		}

		// backwards compat. yes, this is stupid
		[$res['first_name'], $res['last_name']] = explode(' ', $res['fullname']);
		return $res;
	}

	public function getGroups(int $id): ?array {
		if ($this->group_stmt === null) {
			$this->group_stmt = $this->dbh->prepare('
				SELECT "group"
				FROM usergroups WHERE user = ?
			');
		}
		$this->group_stmt->execute([$id]);
		$groups = [];
		while (($res = $this->group_stmt->fetch(PDO::FETCH_NUM))) {
			$groups[] = $res[0];
		}
		return $groups;
	}
}
