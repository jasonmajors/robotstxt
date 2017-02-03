<?php

use PHPUnit\Framework\TestCase;
use Robots\RobotsTxt;
use Robots\Exceptions\MissingRobotsTxtException;

/**
 * @covers RobotsTxt
 */
class RobotsTxtTest extends TestCase
{
	protected $robotsTxt;

	protected function setUp()
	{
		$this->robotsTxt = new RobotsTxt();
	}

	/**
	 * @dataProvider userAgentProvider
	 */
	public function testSetUserAgent($userAgent)
	{
		$this->robotsTxt->setUserAgent($userAgent);
		$this->assertEquals('RobotsTxtBot\1.0', ini_get('user_agent'));
		$this->assertInstanceOf(Robots\Robotstxt::class, $this->robotsTxt);
	}

	/**
	 * @dataProvider  getDisallowedProvider
	 * @depends 	  testSetUserAgent
	 */
	public function testGetDisallowed($url, $expectedDisallowed)
	{
		$disallowed = $this->robotsTxt->getDisallowed($url);
		$this->assertEquals(true, ($disallowed === $expectedDisallowed));
	}

	/**
	 * @depends 	 testGetDisallowed
	 * @dataProvider isAllowedProvider
	 */
	public function testIsAllowed($url, $expected)
	{
		$isAllowed = $this->robotsTxt->isAllowed($url);
		$this->assertEquals($expected, $isAllowed);
	}

	public function testMissingRobotsTxtExceptionWhenNoRobotsTxtFile()
	{
		$this->expectException(MissingRobotsTxtException::class);
		$this->robotsTxt->isAllowed('https://www.example.com');
	}

	public function userAgentProvider()
	{
		return [
			['RobotsTxtBot\1.0'],
		];
	}

	public function getDisallowedProvider()
	{
		$phpnetExpectedDisallowed = [
			'backend',
			'distributions',
			'stats',
			'server-status',
			'source.php',
			'search.php',
			'mod.php',
			'manual/add-note.php',
			'manual/vote-note.php',
			'harming/humans',
			'ignoring/human/orders',
			'harm/to/self',
		];

		return [
			['https://secure.php.net', $phpnetExpectedDisallowed],
		];
	}

	public function isAllowedProvider()
	{
		return [
			['https://secure.php.net/backend/', false],
			['https://secure.php.net/distributions', false],
			['https://secure.php.net/stats', false],
			['https://secure.php.net/source.php', false],
			['https://secure.php.net/manual/vote-note.php', false],
			['https://secure.php.net/support.php', true],
			['https://secure.php.net/sources.php', true]
		];
	}
}
