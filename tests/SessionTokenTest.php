<?php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Testcase;

final class SessionTokenTest extends Testcase
{
	private static int $uid;
	private static int $sid;

	public static function setUpBeforeClass(): void {
		// We need to create an user and session for the tokens to reference.
		$stmt = Database::getInstance()->runStmt('
			INSERT INTO users (username)
			VALUES (NULL)
			RETURNING id
		', []);
		self::$uid = $stmt->fetchAll()[0]['id'];
		assert(self::$uid !== null);

		$stmt = Database::getInstance()->runStmt('
			INSERT INTO sessions (user, ctime, expires)
			VALUES (?, ?, ?)
			RETURNING id
		', [self::$uid, time(), time() + 3600]);
		self::$sid = $stmt->fetchAll()[0]['id'];
		assert(self::$sid !== null);
	}


	public static function tokenTypeProvider(): array {
		return [
			'OAuthorization' => [TokenType::OAuthorization],
			'OAccess'        => [TokenType::OAccess],
			'ORefresh'       => [TokenType::ORefresh],
			'Session'        => [TokenType::Session],
		];
	}

	#[DataProvider('tokenTypeProvider')]
	public function testFullRoundtrip(TokenType $type): void {
		$tok1 = new SessionToken($type, '', null, self::$sid);
		if ($type == TokenType::OAuthorization) {
			$tok1->setRedirectURI('https://example.com/');
		}

		$repr = $tok1->export();
		$this->assertIsString($repr, "Didn't export properly.");
		$this->assertSame($repr, $tok1->export(), "Returned two different reprs.");

		$tok2 = SessionToken::accept($repr, $type);
		$this->assertNotNull($tok2, "Didn't accept exported token.");

		$this->assertSame($tok1->getSessionID(), $tok2->getSessionID());
		$this->assertSame($tok1->getExpiryTime(), $tok2->getExpiryTime());
		$this->assertSame($repr, $tok2->export());
		$this->assertSame($tok1->getUserID(), $tok2->getUserID());
		if ($type == TokenType::OAuthorization) {
			$this->assertSame($tok1->getRedirectURI(), $tok2->getRedirectURI());
		}
	}


	public static function mismatchedTypeProvider(): array {
		// I don't need so many tests for this but I'm lazy.
		$res = [];
		foreach (TokenType::cases() as $type1) {
			foreach (TokenType::cases() as $type2) {
				if ($type1 == $type2) continue;
				$res[$type1->name . ' as ' . $type2->name] = [$type1, $type2];
			}
		}
		return $res;
	}

	#[DataProvider('mismatchedTypeProvider')]
	public function testRejectMismatchedTypes(TokenType $type1, TokenType $type2): void {
		$tok1 = new SessionToken($type1, '', null, self::$sid);
		$repr = $tok1->export();
		$this->assertIsString($repr, "didn't export properly");

		$tok2 = SessionToken::accept($repr, $type2);
		$this->assertNull($tok2, "accepted mismatched token");
	}


	public function testAcceptOnce(): void {
		// Only relevant for authorization tokens.
		$tok = new SessionToken(TokenType::OAuthorization, '', null, self::$sid);
		$repr = $tok->export();
		$this->assertIsString($repr, "didn't export properly");

		$tok1 = SessionToken::acceptOnce($repr, TokenType::OAuthorization);
		$this->assertNotNull($tok1, "didn't accept freshly exported token");

		$tok2 = SessionToken::acceptOnce($repr, TokenType::OAuthorization);
		$this->assertNull($tok2, "accepted token twice");
	}

	public function testRefreshIntoType(): void {
		$tok1 = new SessionToken(TokenType::OAuthorization, '', null, self::$sid);
		$tok1->export(); // Just to cache the repr.

		$tok2 = $tok1->setTypeAndRefresh(TokenType::OAccess);
		$this->assertNotNull($tok2, "Didn't create a new token.");
		$this->assertSame($tok1->getType(), TokenType::OAuthorization);
		$this->assertSame($tok2->getType(), TokenType::OAccess);

		$this->assertSame($tok1->getSessionID(), $tok2->getSessionID());
		$this->assertSame($tok1->getUserID(), $tok2->getUserID());

		$this->assertNotSame($tok1->export(), $tok2->export());

		$this->markTestIncomplete("Haven't mocked the time yet.");
	}

	public function testExpiry(): void { // TODO
		$this->markTestIncomplete("Haven't mocked the time yet.");
	}
}
