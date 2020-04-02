<?php

use pnoeric\discourseAPI\DiscourseAPI;
use PHPUnit\Framework\TestCase;

class DiscourseApiTest extends TestCase {
	/**
	 * @var DiscourseAPI
	 */
	private $DiscourseAPI;

	private $testUserName;

	private $testCategory;
	private $testTopic;


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
	 * @group topics
	 */
	public function testGetCategories() {
		$res = $this->DiscourseAPI->getCategories();

		// first let's be sure we got an object back!
		$this->assertIsObject( $res );
		$this->assertIsObject( $res->apiresult );

		// then let's be sure we got a valid result
		$this->assertEquals( 200, $res->http_code );

		// then let's be sure there is at least one category
		$this->assertGreaterThan( 0, sizeof( $res->apiresult->category_list->categories ),
		                          'Expected there to be at least one category' );

	}

	/**
	 * test the getCategory() call. assumes your Discourse installation has at least one category!
	 * this call is to get full info on a single category
	 *
	 * @depends testGetCategories
	 *
	 * @group   topics
	 */
	public function testGetCategory() {
		// first get all categories
		$res = $this->DiscourseAPI->getCategories();

		$this->assertEquals( 200, $res->http_code );

		// pull out the first category
		$firstCategory = $res->apiresult->category_list->categories[0];

		// use ID to get full info (this is the method we're testing)
		$catInfo = $this->DiscourseAPI->getCategory( $firstCategory->id );

		$this->assertEquals( 200, $catInfo->http_code );

		//var_dump($catInfo->apiresult );die();
		$this->assertIsObject( $catInfo->apiresult );
		$this->assertIsArray( $catInfo->apiresult->users, "Can't retrieve array of users from getCategory() " . $firstCategory->id );
		$this->assertIsArray( $catInfo->apiresult->topic_list->topics, "Can't retrieve array of topics from getCategory() " . $firstCategory->id );

		// use SLUG to get full info (this is the method we're testing)
		$catInfo = $this->DiscourseAPI->getCategory( $firstCategory->slug );

		$this->assertEquals( 200, $catInfo->http_code );

		$this->assertIsObject( $catInfo->apiresult );
		$this->assertIsArray( $catInfo->apiresult->users, "Can't retrieve array of users from getCategory()" );
		$this->assertIsArray( $catInfo->apiresult->topic_list->topics, "Can't retrieve array of topics from getCategory()" );

	}

	/**
	 * test the getTopic() call. assumes your Discourse installation has at least one category!
	 * this call is to get full info on a single category
	 *
	 * @group   topics
	 *
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

		$this->assertEquals( 200, $topicInfo->http_code );

		$this->assertEquals( $topicInfo->apiresult->id, $firstTopic->id );
		$this->assertEquals( $topicInfo->apiresult->name, $firstTopic->name );
	}

	/**
	 * @group   topics
	 *
	 * @depends testGetCategories
	 * @depends testGetCategory
	 * @depends testGetTopic
	 */

	public function testCreateTopic() {
		// first get all categories
		$res = $this->DiscourseAPI->getCategories();

		// pull out the first category
		$firstCategory = $res->apiresult->category_list->categories[0];

		$topicName = "Here is my test topic that has a long title and random number, " . mt_rand( 1, 999 );

		$bodyText = $this->getDummyPargraphs( 3 );
		$res      = $this->DiscourseAPI->createTopic( $topicName, $bodyText, $firstCategory->id, 'system' );

		$this->assertEquals( 200, $res->http_code );
		$this->assertEquals( 'system', $res->apiresult->username );
		$this->assertEquals( 1, $res->apiresult->post_number );
		$this->assertEquals( 0, $res->apiresult->reply_count );
	}

	/**
	 * @group   topics
	 *
	 * @depends testGetCategories
	 * @depends testGetCategory
	 * @depends testGetTopic
	 * @depends testCreateTopic
	 */
	public function testCreatePost() {
		// first get all categories
		$res = $this->DiscourseAPI->getCategories();

		// pull out the first category
		$firstCategory = $res->apiresult->category_list->categories[0];

		$topicName = "Here's a test topic (with a long title!) with a random number: " . mt_rand( 10, 9999 );

		$bodyText  = $this->getDummyPargraphs( 3 );
		$bodyText2 = $this->getDummyPargraphs( 3 );

		$dt = new \DateTime();
		$dt->setTimestamp( 1571020500 );        // 14 oct 2019, 4:35:00 am local time [zurich]

		$topicResult = $this->DiscourseAPI->createTopic( $topicName, $bodyText,
		                                                 $firstCategory->id, 'system',
		                                                 0, $dt );

		$this->assertIsInt( $topicResult->apiresult->topic_id );
		$this->assertNotEquals( 0, $topicResult->apiresult->topic_id, 'Topic ID of new topic is 0' );

		$dt->setTimestamp( 1571307742 );        // 17 oct 2019, 12:22:22 pm local time [zurich]

		$res = $this->DiscourseAPI->createPost( $bodyText2, $topicResult->apiresult->topic_id, 'system', $dt );

		var_dump( $res );

	}


	/**
	 * @throws Exception
	 * @group upload
	 */

	public function testUploadImage() {
		$fullPath = __DIR__ . '/judgingcat.jpg';

		$res = $this->DiscourseAPI->uploadImage( $fullPath, 'judging cat', 'image/jpeg' );
		var_dump( $res );

		// first let's be sure we got an object back!
		$this->assertIsObject( $res );
		$this->assertIsObject( $res->apiresult );

		// then let's be sure we got a valid result
		$this->assertEquals( 200, $res->http_code );
		$this->assertIsString( $res->apiresult->url );

	}


	/**
	 * get dummy text from randomtext.me service
	 *
	 * @param $grafCount
	 *
	 * @return string
	 */
	private function getDummyPargraphs( $grafCount ) {
		$url = 'https://www.randomtext.me/api/gibberish/p-' . $grafCount . '/2-10';

		do {
			$jsonData = @file_get_contents( $url );

			if ( ! $jsonData ) {
				// if we didn't get anything, sleep for 1 sec & try again
				sleep( 1 );
			}
		} while ( ! $jsonData );

		$obj = json_decode( $jsonData );

		$dummyText = (string) $obj->text_out;

		// change into plaintext
		$dummyText = str_replace( [ '<p>', "\r" ], "", $dummyText );
		$dummyText = str_replace( '</p>', "\n\n", $dummyText );

		return trim( $dummyText );
	}


	/**
	 *
	 * @group users
	 */
	public function testCreateUser() {
		$userName     = $realName = 'erictest' . mt_rand( 10, 999 );
		$emailAddress = 'eric+' . $userName . '@ericmueller.org';
		$password     = 'password' . mt_rand( 1000, 9999 );

		$res = $this->DiscourseAPI->createUser( $realName, $userName, $emailAddress, $password );

		$this->assertGreaterThan( 0, $res->apiresult->user_id, 'User ID is 0' );
		$this->assertEquals( true, $res->apiresult->success, 'createUser did not return success' );

		$r = $this->DiscourseAPI->setUserInfo( $userName, [ 'location' => 'At home right now' ] );
		var_dump( $r );
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
