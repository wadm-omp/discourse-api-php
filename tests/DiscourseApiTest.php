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
	private $ssoSecret;


	protected function setUp() {
		// load environment vars from .env file
		// see https://github.com/vlucas/phpdotenv
		$dotEnv = Dotenv\Dotenv::createImmutable( __DIR__ );
		$dotEnv->load();

		// that's it! all the environment vars are loaded into $_ENV now, we don't need $dotEnv any longer.

		$this->DiscourseAPI = new DiscourseAPI( $_ENV['DISCOURSE_URL'], $_ENV['DISCOURSE_API_KEY'],
		                                        $_ENV['DISCOURSE_PROTOCOL'] );

		$this->testUserName = $_ENV['DISCOURSE_TEST_USERNAME'];

		$this->ssoSecret = $_ENV['DISCOURSE_SSO_SECRET'];

		// this dumps lots of stuff to the screen when set to true
		$this->DiscourseAPI->setDebugPutPostRequest( true );
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

		$res = $this->DiscourseAPI->createPost( $bodyText2, $topicResult->apiresult->topic_id, 'max001', $dt );

		var_dump( $res );

	}


	/**
	 * @throws Exception
	 * @group upload
	 */

	public function testUploadImage() {
		$fullPath = __DIR__ . '/judgingcat.jpg';

		$this->DiscourseAPI->setDebugPutPostRequest( true );

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
	 * test create user, and set user info
	 */
	public function testCreateUser() {
		$userName     = $realName = 'erictest' . mt_rand( 10, 999 );
		$emailAddress = 'eric+' . $userName . '@ericmueller.org';
		$password     = 'password' . mt_rand( 1000, 9999 );

		$res = $this->DiscourseAPI->createUser( $realName, $userName, $emailAddress, $password );

		// then let's be sure we got a valid result
		$this->assertEquals( 200, $res->http_code );
		$this->assertGreaterThan( 0, $res->apiresult->user_id, 'User ID is 0' );
		$this->assertEquals( true, $res->apiresult->success, 'createUser did not return success' );

		$r = $this->DiscourseAPI->setUserInfo( $userName, [ 'location' => 'At home right now' ] );

		// then let's be sure we got a valid result
		$this->assertEquals( 200, $res->http_code );

		// var_dump( $r );
	}


	/**
	 *
	 * @group sso
	 */
	public function testSyncSso() {
		$this->DiscourseAPI->setSsoSecret( $this->ssoSecret );

		$externalId = mt_rand( 1000, 9999 );

		$userName     = $realName = 'test-user-' . $externalId;
		$emailAddress = 'eric+' . $userName . '@ericmueller.org';

		$otherParams = [
			'require_activation' => 'false',
			'external_id'        => $externalId,
		];

		$res = $this->DiscourseAPI->syncSso( $emailAddress, $userName, $otherParams );

		var_dump( $res );

		// then let's be sure we got a valid result
		$this->assertEquals( 200, $res->http_code );

		//		if ( $res->apiresult->single_sign_on_record ) {
		//			//var_dump( $res->apiresult->single_sign_on_record );
		//		}

		$res = $this->DiscourseAPI->getDiscourseUserFromExternalId(
			$res->apiresult->single_sign_on_record->external_id,
			false );

		var_dump( $res->apiresult->user );
		/*
		 $res->apiresult->single_sign_on_record =
			object(stdClass)#16 (11) {
			  ["user_id"]=>
			  int(19)
			  ["external_id"]=>
			  string(4) "6754"
			  ["last_payload"]=>
			  string(103) "email=eric%2Berictest52%40ericmueller.org&external_id=6754&require_activation=false&username=erictest52"
			  ["created_at"]=>
			  string(24) "2020-04-02T14:37:55.759Z"
			  ["updated_at"]=>
			  string(24) "2020-04-02T14:37:55.759Z"
			  ["external_username"]=>
			  string(10) "erictest52"
			  ["external_email"]=>
			  string(31) "eric+erictest52@ericmueller.org"
			  ["external_name"]=>
			  NULL
			  ["external_avatar_url"]=>
			  NULL
			  ["external_profile_background_url"]=>
			  NULL
			  ["external_card_background_url"]=>
			  NULL
			}
    */
	}

	/**
	 * @throws Exception
	 */
	function testGetUserByUsername() {

		$this->DiscourseAPI->setDebugGetRequest( true );

		$res = $this->DiscourseAPI->getUserByDiscourseId( 3 );
		var_dump( $res );
	}

	/**
	 * @group notificationlevel
	 * @throws Exception
	 */
	function testchangeNotificationLevel() {

		// four months from now
		$until = new DateTime();
		$until->setTimestamp( time() + ( 60 * 60 * 24 * 4 * 30 ) );

		$sourceUsername   = 'erictest3';
		$usernameToIgnore = 'quinten';

		$this->DiscourseAPI->changeNotificationLevel( $sourceUsername, $usernameToIgnore, $until, false );
	}

	/**
	 * @group suspend
	 * @throws Exception
	 */
	function testSuspend() {

		$id = $this->DiscourseAPI->getDiscourseUserIdFromExternalId( 860838 );

		// one year from now
		$until = new DateTime();
		$until->setTimestamp( time() + ( 60 * 60 * 24 * 365 ) );
		$reason = 'This member has decided to suspend their own account temporarily.';

		$id = 4;

		$res = $this->DiscourseAPI->suspendUserById( $id, $until, $reason );
		die();
	}

	/**
	 * @group unsuspend
	 * @throws Exception
	 */
	function testUnsuspend() {
		$id = $this->DiscourseAPI->getDiscourseUserIdFromExternalId( 860838 );

		$id  = 4;
		$res = $this->DiscourseAPI->unsuspendUserById( $id );
		die();
	}


	/**
	 * @group customfield
	 * @throws Exception
	 */
	function testSetUserField() {
		$res = $this->DiscourseAPI->setUserField( 'erictest3', [ 'user_fields[2]' => 1 ] );

		// get user record
		$res = $this->DiscourseAPI->getUserByUsername( 'erictest3' );
		$this->assertEquals( $res->apiresult->user->user_fields->{'2'}, 1 );

		// set that field to 0
		$res = $this->DiscourseAPI->setUserField( 'erictest3', [ 'user_fields[2]' => 0 ] );

		// get user record and check again
		$res = $this->DiscourseAPI->getUserByUsername( 'erictest3' );
		$this->assertEquals( $res->apiresult->user->user_fields->{'2'}, 0 );
	}


	/**
	 * @throws Exception
	 * @group quicktest
	 */
	function testGetCategoriesAgain() {

		$this->DiscourseAPI->setDebugPutPostRequest( true );
		$this->DiscourseAPI->setDebugGetRequest( true );

		$res = $this->DiscourseAPI->getCategories();

		var_dump( $res );

		foreach ( $res->apiresult->category_list->categories as $k ) {
			echo $k->id . ' - ' . $k->name . "\n";
		}

	}


	/**
	 * @throws Exception
	 * @group trustlevel
	 */
	function testSetUserTrustLevel() {

		$res         = $this->DiscourseAPI->getUserByUsername( 'kickinitla' );
		$discourseId = $res->apiresult->user->id;

		$this->DiscourseAPI->setUserTrustLevel( $discourseId, 2 );

		// now check it
		$res = $this->DiscourseAPI->getUserByUsername( 'kickinitla' );

		$this->assertEquals( 2, $res->apiresult->user->trust_level, 'Expected trust level 2' );
	}


	/**
	 * @throws Exception
	 * @depends testSetUserTrustLevel
	 * @group   trustlevel
	 */
	function testGetUserTrustLevel() {
		$res         = $this->DiscourseAPI->getUserByUsername( 'kickinitla' );
		$discourseId = $res->apiresult->user->id;

		$this->assertEquals( 2, $this->DiscourseAPI->getUserTrustLevel( $discourseId ), 'Expected trust level 2' );
	}


	/**
	 * @throws Exception
	 * @group extid
	 */
	function testGetUserByExtId() {
		$extId = 1310598;

		$res = $this->DiscourseAPI->getDiscourseUserFromExternalId(
			$extId,
			false );

		var_dump( $res->user );
	}

	// TODO: write lots more tests ;-)
}