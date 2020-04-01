<?php

use pnoeric\discourseAPI\DiscourseAPI;
use PHPUnit\Framework\TestCase;

class DiscourseApiTest extends TestCase {
	/**
	 * @var DiscourseAPI
	 */
	private $DiscourseAPI;

	private $testUserName;

	protected function setUp() {
		// load environment vars from .env file
		// see https://github.com/vlucas/phpdotenv
		$dotEnv = Dotenv\Dotenv::createImmutable( __DIR__ );
		$dotEnv->load();

		// that's it! all the environment vars are loaded into $_ENV now, we don't need $dotEnv any longer.

		$this->DiscourseAPI = new DiscourseAPI( $_ENV['DISCOURSE_URL'], $_ENV['DISCOURSE_API_KEY'],
		                                        $_ENV['DISCOURSE_PROTOCOL'] );

		$this->testUserName = $_ENV['DISCOURSE_TEST_USERNAME'];
	}

	/**
	 * test the getCategories() call. assumes your Discourse installation has at least one category!
	 *
	 * @group common
	 */
	public function testGetCategories() {
		$res = $this->DiscourseAPI->getCategories();

		// first let's be sure we got an object back!
		$this->assertIsObject( $res );
		$this->assertIsObject( $res->apiresult );

		// then let's be sure we got a valid result
		$this->assertEquals( '200', $res->http_code );

		// then let's be sure there is at least one category
		$this->assertGreaterThan( 0, sizeof( $res->apiresult->category_list->categories ),
		                          'Expected there to be at least one category' );

	}


	/**
	 * test the getCategory() call. assumes your Discourse installation has at least one category!
	 * this call is to get full info on a single category
	 *
	 * @depends testGetCategories
	 * @group common
	 */
	public function testGetCategory() {
		// first get all categories
		$res = $this->DiscourseAPI->getCategories();

		// pull out the first category
		$firstCategory = $res->apiresult->category_list->categories[0];

		// use ID to get full info (this is the method we're testing)
		$catInfo = $this->DiscourseAPI->getCategory( $firstCategory->id );

		$this->assertIsObject( $catInfo->apiresult );
		$this->assertIsArray( $catInfo->apiresult->users, "Can't retrieve array of users from getCategory()" );
		$this->assertIsArray( $catInfo->apiresult->topic_list->topics, "Can't retrieve array of topics from getCategory()" );

		// use SLUG to get full info (this is the method we're testing)
		$catInfo = $this->DiscourseAPI->getCategory( $firstCategory->slug );

		$this->assertIsObject( $catInfo->apiresult );
		$this->assertIsArray( $catInfo->apiresult->users, "Can't retrieve array of users from getCategory()" );
		$this->assertIsArray( $catInfo->apiresult->topic_list->topics, "Can't retrieve array of topics from getCategory()" );

	}

	/**
	 * test the getTopic() call. assumes your Discourse installation has at least one category!
	 * this call is to get full info on a single category
	 *
	 * @group common
	 * @depends testGetCategories
	 * @depends testGetCategory
	 */
	public function testGetTopic() {
		// first get all categories
		$res = $this->DiscourseAPI->getCategories();

		// pull out the first category
		$firstCategory = $res->apiresult->category_list->categories[0];

		// get the category
		$catInfo = $this->DiscourseAPI->getCategory( $firstCategory->id );

		// get the first topic
		$firstTopic = $catInfo->apiresult->topic_list->topics[0];

		// here is the method we're testing
		$topicInfo = $this->DiscourseAPI->getTopic( $firstTopic->id );

		$this->assertEquals( $topicInfo->apiresult->id, $firstTopic->id );
		$this->assertEquals( $topicInfo->apiresult->name, $firstTopic->name );
	}

	/**
	 */
	public function testCreateUser() {

		$testUserName = 'dummyaccount' . mt_rand( 0, 999 );

		$params = [
			'location' => 'Home!',
		];

		$realName     = 'erictest5';
		$userName     = 'erictest5';
		$emailAddress = 'eric+erictest5@ericmueller.org';
		$password     = 'password';

		$this->DiscourseAPI->createUser( $realName, $userName, $emailAddress, $password );


		//			$res = $this->DiscourseAPI->setUserInfo( $testUserName, $params );

		//		var_dump( $res );
	}

	/**
	 */
	public function testSetUserInfo() {

		$testUserName = 'dummyaccount' . mt_rand( 0, 999 );

		$params = [
			'location' => 'Home!',
		];


		$res = $this->DiscourseAPI->setUserInfo( $testUserName, $params );

		var_dump( $res );
	}

	// TODO: write lots more tests ;-)

}
