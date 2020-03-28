<?php

use pnoeric\discourseAPI\DiscourseAPI;
use PHPUnit\Framework\TestCase;

class DiscourseApiTest extends TestCase {
	/**
	 * @var DiscourseAPI
	 */
	private $DiscourseAPI;

	protected function setUp() {
		// load environment vars from .env file
		// see https://github.com/vlucas/phpdotenv
		$dotEnv = Dotenv\Dotenv::createImmutable( __DIR__ );
		$dotEnv->load();

		// that's it! all the environment vars are loaded into $_ENV now, we don't need $dotEnv any longer.

		$this->DiscourseAPI = new DiscourseAPI( $_ENV['DISCOURSE_URL'], $_ENV['DISCOURSE_API_KEY'], $_ENV['DISCOURSE_PROTOCOL'] );
	}

	/**
	 * test the getCategories() call. assumes your Discourse installation has at least one category!
	 */
	public function testGetCategories() {
		$res = $this->DiscourseAPI->getCategories();

		// first let's be sure we got an object back!
		$this->assertIsObject( $res );
		$this->assertIsObject( $res->apiresult );

		// then let's be sure we got a valid result
		$this->assertEquals( '200', $res->http_code );

		// then let's be sure there is at least one category
		$this->assertGreaterThan( 0, sizeof( $res->apiresult->category_list->categories ), 'Expected there to be at least one category' );
	}

	// TODO: write lots more tests ;-)

}