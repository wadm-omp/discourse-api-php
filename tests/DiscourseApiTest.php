<?php
/**
 * Test suite for DiscourseAPI.php
 *
 * These tests are not great - would love for someone to clean them up and make them better.
 * For now, they're a quick way to test some methods, and to dump output to the screen.
 *
 * @author Eric Mueller, https://github.com/pnoeric
 */

use PHPUnit\Framework\TestCase;
use pnoeric\DiscourseAPI;

class DiscourseApiTest extends TestCase {
	/**
	 * @var DiscourseAPI
	 */
	private $DiscourseAPI;

	/*
	 * all following vars come directly from .env file
	 */
	private $testAdminUsername;
	private $testAdminUserId;
	private $testRegularUsername;
	private $testRegularUserId;
	private $testRegularExternalId;
	private $ssoSecret;

	/**
	 * @var string path to test JPG file
	 */
	private $testJpegPath;

	public function setUp(): void {
		// load environment vars from .env file
		// see https://github.com/vlucas/phpdotenv
		$dotEnv = Dotenv\Dotenv::createImmutable( __DIR__ );
		$dotEnv->load();

		// that's it! all the environment vars are loaded into $_ENV now, we don't need $dotEnv any longer.

		$this->DiscourseAPI = new \pnoeric\DiscourseAPI( $_ENV['DISCOURSE_URL'], $_ENV['DISCOURSE_API_KEY'],
		                                                 $_ENV['DISCOURSE_PROTOCOL'] );

		$propertiesFromEnv = [
			'testAdminUsername'         => 'DISCOURSE_ADMIN_USER_NAME',
			'testAdminUserId'           => 'DISCOURSE_ADMIN_USER_ID',
			'testRegularUsername'       => 'DISCOURSE_REGULAR_USER_NAME',
			'testRegularUserId'         => 'DISCOURSE_REGULAR_USER_ID',
			'testRegularUserExternalId' => 'DISCOURSE_REGULAR_USER_EXTERNAL_ID',
			'ssoSecret'                 => 'DISCOURSE_SSO_SECRET',
		];

		// pull variables from $_ENV and load them as properties here
		foreach ( $propertiesFromEnv as $k => $v ) {
			$this->$k = $_ENV[ $v ];
		}

		// set up the path to our test JPEG file
		$this->testJpegPath = __DIR__ . '/assets/judgingcat.jpg';

		// this dumps lots of stuff to the screen when set to true
		//		$this->DiscourseAPI->setDebugPutPostRequest( true );
		//		$this->DiscourseAPI->setDebugGetRequest( true );
	}

	/**
	 * test the getCategories() call. assumes your Discourse installation has at least one category!
	 *
	 * @group topics
	 * @throws Exception
	 */
	public function testGetCategories() {
		$res = $this->DiscourseAPI->getCategories();

		// first let's be sure we got an object back!
		$this->assertIsObject( $res->apiresult );
		$this->assertEquals( 200, $res->http_code );

		// then let's be sure there is at least one category
		$this->assertGreaterThan( 0,
		                          sizeof( $res->apiresult->category_list->categories ),
		                          'Expected there to be at least one category' );

	}

	/**
	 * test the getCategory() call. assumes your Discourse installation has at least one category!
	 * this call is to get full info on a single category
	 *
	 * @depends testGetCategories
	 *
	 * @group   topics
	 * @throws Exception
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
	 * @throws Exception
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
	 * @throws Exception
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

		//var_dump( $res );
		// add tests here to confirm the results are okay
	}

	/**
	 * test uploading of image
	 *
	 * @throws Exception
	 * @group upload
	 */
	public function testUploadImage() {
		// first let's just be sure we set up the test correctly ;-)
		$this->assertFileExists( $this->testJpegPath );

		// now upload the test image
		$res = $this->DiscourseAPI->uploadImage( $this->testJpegPath, 'judging cat', 'image/jpeg' );

		// first let's be sure we got an object back!
		$this->assertIsObject( $res );
		$this->assertIsObject( $res->apiresult );

		// then let's be sure we got a valid result
		$this->assertEquals( 200, $res->http_code );

		$this->assertIsString( $res->apiresult->url );
	}

	/**
	 * get dummy text from randomtext.me service, used internally only
	 *
	 * @param $paragraphCount
	 *
	 * @return string
	 */
	private function getDummyPargraphs( $paragraphCount ) {
		$url = 'https://www.randomtext.me/api/gibberish/p-' . $paragraphCount . '/2-10';

		do {
			$jsonData = @file_get_contents( $url );

			if ( ! $jsonData ) {
				// if we didn't get anything, sleep for 1 sec & try again
				sleep( 1 );
			}
		} while ( ! $jsonData );

		$obj = json_decode( $jsonData );

		$dummyText = (string) $obj->text_out;

		// strip HTML <p> tags and change to CRs
		$dummyText = str_replace( [ '<p>', "\r" ], "", $dummyText );
		$dummyText = str_replace( '</p>', "\n\n", $dummyText );

		return trim( $dummyText );
	}

	/**
	 * test create user, and set user info
	 */
	public function testCreateUser() {
		$userName     = $realName = 'test-user-' . mt_rand( 10, 999 );
		$emailAddress = 'test+' . $userName . '@example.com';
		$password     = 'password' . mt_rand( 1000, 9999 );

		$res = $this->DiscourseAPI->createUser( $realName, $userName, $emailAddress, $password );

		// then let's be sure we got a valid result
		$this->assertEquals( 200, $res->http_code );
		$this->assertGreaterThan( 0, $res->apiresult->user_id, 'User ID is 0' );
		$this->assertEquals( true, $res->apiresult->success, 'createUser did not return success' );

		$r = $this->DiscourseAPI->setUserInfo( $userName, [ 'location' => 'At home right now' ] );

		// then let's be sure we got a valid result
		$this->assertEquals( 200, $res->http_code );

		// write more tests here to confirm output
		// var_dump( $r );
	}

	/**
	 * @group sso
	 */
	public function testSyncSso() {
		$this->DiscourseAPI->setSsoSecret( $this->ssoSecret );

		$externalId = mt_rand( 1000, 9999 );

		$userName     = $realName = 'test-user-' . $externalId;
		$emailAddress = 'test+' . $userName . '@example.com';

		$otherParams = [
			'require_activation' => 'false',
			'external_id'        => $externalId,
		];

		$res = $this->DiscourseAPI->syncSso( $emailAddress, $userName, $otherParams );

		// var_dump( $res );

		// then let's be sure we got a valid result
		$this->assertEquals( 200, $res->http_code );

		//		if ( $res->apiresult->single_sign_on_record ) {
		//			//var_dump( $res->apiresult->single_sign_on_record );
		//		}

		$res = $this->DiscourseAPI->getDiscourseUserFromExternalId(
			$res->apiresult->single_sign_on_record->external_id,
			false );

		// spot check a few fields
		$this->assertIsInt( $res->apiresult->user->user_id );
		$this->assertIsString( $res->apiresult->user->external_email );

		// var_dump( $res->apiresult->user );
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
	 * @group lacksAssertions
	 */
	function testGetUserByUsername() {
		// $this->DiscourseAPI->setDebugGetRequest( true );

		$res = $this->DiscourseAPI->getUserByDiscourseId( $this->testAdminUserId );

		$this->assertIsObject( $res->apiresult );
		$this->assertEquals( 200, $res->http_code );

		$this->assertEquals( $this->testAdminUsername, $res->apiresult->user->username );

		//var_dump( $res );
	}

	/**
	 * @group lacksAssertions
	 * @throws Exception
	 */
	function testUserIgnore() {
		$sourceUsername   = $this->testAdminUsername;
		$usernameToIgnore = $this->testRegularUsername;

		// four months from now
		$until = new DateTime();
		$until->setTimestamp( time() + ( 60 * 60 * 24 * 4 * 30 ) );

		$this->DiscourseAPI->ignoreUnignoreUser(
			$sourceUsername,
			$usernameToIgnore,
			$until,
			false );
	}

	/**
	 * test getting disocurse user ID from external ID
	 *
	 * @group sso
	 * @throws Exception
	 */
	public function testGetDiscourseUserIdFromExternalId() {
		if ( ! $this->testRegularExternalId ) {
			$this->markTestSkipped( "Missing DISCOURSE_REGULAR_USER_EXTERNAL_ID in .env file" );
		}

		$getId = $this->DiscourseAPI->getDiscourseUserIdFromExternalId( $this->testRegularExternalId );

		// be sure the ID we got is the discourse ID for this external ID
		$this->assertEquals( $this->testAdminUserId, $getId );
	}

	/**
	 * @group suspend
	 * @throws Exception
	 */
	function testSuspend() {
		// one year from now
		$until = new DateTime();
		$until->setTimestamp( time() + ( 60 * 60 * 24 * 365 ) );
		$reason = 'This member account is suspended.';

		$res = $this->DiscourseAPI->suspendUserById( $this->testRegularUserId, $until, $reason );

		$this->assertIsObject( $res->apiresult );
		$this->assertEquals( 200, $res->http_code );

		// needs better assertions!
	}

	/**
	 * test unsuspendUserById()
	 *
	 * @group unsuspend
	 * @throws Exception
	 */
	function testUnsuspend() {
		$res = $this->DiscourseAPI->unsuspendUserById( $this->testRegularUserId );

		$this->assertIsObject( $res->apiresult );
		$this->assertEquals( 200, $res->http_code );

		// needs better assertions!
	}

	/**
	 * @group customfield
	 * @throws Exception
	 */
	function testSetUserField() {
		// which user field can we mess with? needs to be set up in discourse first
		// $testFieldId = 2;

		if ( ! $testFieldId ) {
			$this->markTestSkipped( 'Missing user field ID, skipping test' );
		}

		$res = $this->DiscourseAPI->setUserField( $this->testAdminUsername, [ 'user_fields[' . $testFieldId . ']' => 1234 ] );

		// get user record
		$res = $this->DiscourseAPI->getUserByUsername( $this->testAdminUsername );
		$this->assertEquals( 1234, $res->apiresult->user->user_fields->{$testFieldId} );

		// set that user field to 0
		$res = $this->DiscourseAPI->setUserField( $this->testAdminUsername, [ 'user_fields[' . $testFieldId . ']' => 0 ] );

		// get user record and check again
		$res = $this->DiscourseAPI->getUserByUsername( $this->testAdminUsername );
		$this->assertEquals( 0, $res->apiresult->user->user_fields->{' . $testFieldId . '} );
	}

	/**
	 * @throws Exception
	 * @group trustlevel
	 */
	function testSetUserTrustLevel() {
		$res         = $this->DiscourseAPI->getUserByUsername( $this->testRegularUsername );
		$discourseId = (int) $res->apiresult->user->id;

		$this->DiscourseAPI->setUserTrustLevel( $discourseId, 2 );

		// now check it
		$res = $this->DiscourseAPI->getUserByUsername( $this->testRegularUsername );

		$this->assertEquals( 2,
		                     $res->apiresult->user->trust_level,
		                     'Expected trust level 2' );
	}

	/**
	 * @throws Exception
	 * @depends testSetUserTrustLevel
	 * @group   trustlevel
	 */
	function testGetUserTrustLevel() {
		$res         = $this->DiscourseAPI->getUserByUsername( $this->testAdminUsername );
		$discourseId = $res->apiresult->user->id;

		$this->assertEquals( 2,
		                     $this->DiscourseAPI->getUserTrustLevel( $discourseId ),
		                     'Expected trust level 2' );
	}

	/**
	 * @throws Exception
	 * @group sso
	 */
	function testGetUserByExtId() {
		$res = $this->DiscourseAPI->getDiscourseUserFromExternalId(
			$this->testRegularUserExternalId,
			false );

		$this->assertIsObject( $res->apiresult );
		$this->assertEquals( 200, $res->http_code );
		$this->assertEquals( $this->testRegularUserId, $res->apiresult->user->id );

		// needs better assertions!
	}

	/**
	 * @group avatar
	 * @group lacksAssertions
	 * @throws Exception
	 */
	function testSetAvatar() {
		$mime     = 'image/jpeg';
		$filename = 'Judging Cat.jpg';

		$r = $this->DiscourseAPI->setAvatar(
			$this->testAdminUsername,
			$this->testAdminUserId,
			$this->testJpegPath,
			$mime,
			$filename
		);

		// var_dump( $r );
	}

	/**
	 * @group deleteuser
	 * @throws Exception
	 */
	function testDeleteUser() {
		$this->markTestSkipped( 'This test deletes a Discourse user account; please manually enable it in the test suite' );

		// this test is commented out because it WILL erase an account permanently!
		// to test, I recommend setting up the user account to erase, then uncomment the test
		// and run JUST this test (or just this group)

		// $discourseUserIdToDelete = 4;
		// $res = $this->DiscourseAPI->anonymizeAccount( $discourseUserIdToDelete );
		// var_dump( $res );
	}

	/**
	 * test getting the "top topics" from Discourse API
	 *
	 * @group toptopics
	 * @throws Exception
	 */
	function testGettingTopTopics() {
		$res = $this->DiscourseAPI->getTopTopics();
		$this->assertIsArray( $res );
		$res = $this->DiscourseAPI->getTopTopics( 'all' );
		$this->assertIsArray( $res );
		$res = $this->DiscourseAPI->getTopTopics( 'weekly' );
		$this->assertIsArray( $res );

		// now do the same test but get them as a specific user
		$res = $this->DiscourseAPI->getTopTopics( 'weekly', $this->testAdminUsername );
		$this->assertIsArray( $res );

		// var_dump( $res );
	}

	/**
	 *
	 * @group topics
	 */
	function testGettingLatestTopics() {
		$res = $this->DiscourseAPI->getLatestTopics();
		$this->assertIsArray( $res );

		$res = $this->DiscourseAPI->getLatestTopics( $this->testAdminUsername );
		$this->assertIsArray( $res );

		// var_dump( $res );
	}


	/**
	 * @throws Exception
	 * @group emailsettings
	 */
	function testChangeEmailDigestSettings() {
		$res = $this->DiscourseAPI->getUserByUsername( $this->testAdminUsername );

		$this->assertIsObject( $res->apiresult );
		$this->assertEquals( 200, $res->http_code );

		// set email digests to true
		$this->DiscourseAPI->setEmailDigestUserSetting( $this->testAdminUsername, true );

		// and confirm the setting "took"
		$res = $this->DiscourseAPI->getUserByUsername( $this->testAdminUsername );
		$this->assertEquals( true, $res->apiresult->user->user_option->email_digests );

		// now set it to false
		$this->DiscourseAPI->setEmailDigestUserSetting( $this->testAdminUsername, false );

		$res = $this->DiscourseAPI->getUserByUsername( $this->testAdminUsername );
		$this->assertEquals( false, $res->apiresult->user->user_option->email_digests );
	}
}
