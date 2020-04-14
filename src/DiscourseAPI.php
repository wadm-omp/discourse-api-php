<?php

/**
 * Discourse API client for PHP
 *
 * Expanded on original by DiscourseHosting
 *
 * @category     DiscourseAPI
 * @package      DiscourseAPI
 * @author       Original author DiscourseHosting <richard@discoursehosting.com>
 * Additional work, timolaine, richp10, pnoeric and others..
 * @copyright    2013, DiscourseHosting.com
 * @license      http://www.gnu.org/licenses/gpl-2.0.html GPLv2
 * @link         https://github.com/richp10/discourse-api-php
 *
 * @noinspection MoreThanThreeArgumentsInspection
 **/

namespace pnoeric\discourseAPI;

use Cassandra\Date;
use DateTime;
use Exception;
use stdClass;
use function is_int;

class DiscourseAPI {
	/**
	 * @var string
	 */
	private $_protocol;

	/**
	 * @var string
	 */
	private $_apiKey;

	/**
	 * @var string
	 */
	private $_discourseHostname;

	/**
	 * @var string secret key for SSO
	 */
	protected $sso_secret;

	private $debugGetRequest = false;
	private $debugPutPostRequest = false;

	////////////////  Groups

	/**
	 * getGroups
	 *
	 * @return mixed HTTP return code and API return object
	 */
	public function getGroups() {
		return $this->_getRequest( '/groups.json' );
	}

	/**
	 * getGroup
	 *
	 * @param string $group name of group
	 *
	 * @return mixed HTTP return code and API return object
	 * @throws Exception
	 */

	public function getGroup( $groupname ) {
		return $this->_getRequest( '/groups/' . $groupname . '.json' );
	}

	/**
	 * @deprecated please use joinGroup() instead
	 */
	public function addUserToGroup( $g, $u ) {
		return $this->joinGroup( $g, $u );
	}

	/**
	 * joinGroup
	 *
	 * @param string $groupName name of group
	 * @param string $username  user to add to the group
	 *
	 * @return mixed HTTP return code and API return object
	 * @throws Exception
	 */
	public function joinGroup( $groupName, $username ) {
		$groupId = $this->getGroupIdByGroupName( $groupName );
		if ( ! $groupId ) {
			return false;
		}

		$params = [
			'usernames' => $username,
		];

		return $this->_putRequest( '/groups/' . $groupId . '/members.json', [ $params ] );
	}

	/*
	 * getGroupIdByGroupName
	 *
	 * @param string $groupname    name of group
	 *
	 * @return mixed id of the group, or false if nonexistent
	 */
	public function getGroupIdByGroupName( $groupname ) {
		$obj = $this->getGroup( $groupname );
		if ( $obj->http_code !== 200 ) {
			return false;
		}

		return $obj->apiresult->group->id;
	}

	/**
	 * @param $groupName
	 * @param $username
	 *
	 * @return bool|stdClass
	 * @throws Exception
	 */
	public function leaveGroup( $groupName, $username ) {
		$userid  = $this->getUserByUsername( $username )->apiresult->user->id;
		$groupId = $this->getGroupIdByGroupName( $groupName );
		if ( ! $groupId ) {
			return false;
		}
		$params = [
			'user_id' => $userid,
		];

		return $this->_deleteRequest( '/groups/' . $groupId . '/members.json', [ $params ] );
	}

	/**
	 * getGroupMembers
	 *
	 * @param string $group name of group
	 *
	 * @return mixed HTTP return code and API return object
	 * @throws Exception
	 */
	public function getGroupMembers( $group ) {
		return $this->_getRequest( "/groups/{$group}/members.json" );
	}

	/**
	 * create a group and add users
	 *
	 * @param string $groupname name of group to be created
	 * @param array  $usernames users in the group
	 *
	 * @param int    $aliaslevel
	 * @param string $visible
	 * @param string $automemdomain
	 * @param string $automemretro
	 * @param string $title
	 * @param string $primegroup
	 * @param string $trustlevel
	 *
	 * @return mixed HTTP return code and API return object
	 * @noinspection MoreThanThreeArgumentsInspection
	 *
	 * @throws Exception
	 */
	public function addGroup(
		$groupname,
		array $usernames = [],
		$aliaslevel = 3,
		$visible = 'true',
		$automemdomain = '',
		$automemretro = 'false',
		$title = '',
		$primegroup = 'false',
		$trustlevel = '0'
	) {
		$groupId = $this->getGroupIdByGroupName( $groupname );

		// if group already exists, get outta here
		if ( $groupId ) {
			return false;
		}

		$params = [
			'group' => [
				'name'                               => $groupname,
				'usernames'                          => implode( ',', $usernames ),
				'alias_level'                        => $aliaslevel,
				'visible'                            => $visible,
				'automatic_membership_email_domains' => $automemdomain,
				'automatic_membership_retroactive'   => $automemretro,
				'title'                              => $title,
				'primary_group'                      => $primegroup,
				'grant_trust_level'                  => $trustlevel,
			],
		];

		return $this->_postRequest( '/admin/groups', $params );
	}

	/**
	 * @param string $groupname
	 *
	 * @return bool|stdClass
	 * @throws Exception
	 */
	public function removeGroup( string $groupname ) {
		$groupId = $this->getGroupIdByGroupName( $groupname );
		if ( ! $groupId ) {
			return false;
		}

		return $this->_deleteRequest( '/admin/groups/' . (string) $groupId, [] );
	}

	///////////////   Categories

	/** @noinspection MoreThanThreeArgumentsInspection * */
	/**
	 * createCategory
	 *
	 * @param string $categoryName name of new category
	 * @param string $color        color code of new category (six hex chars, no #)
	 * @param string $textColor    optional color code of text for new category
	 * @param string $userName     optional user to create category as
	 *
	 * @return mixed HTTP return code and API return object
	 *
	 * @throws Exception
	 */
	public function createCategory(
		string $categoryName,
		string $color,
		string $textColor = '000000',
		string $userName = 'system'
	) {
		$params = [
			'name'       => $categoryName,
			'color'      => $color,
			'text_color' => $textColor,
		];

		return $this->_postRequest( '/categories', [ $params ], $userName );
	}

	/**
	 * ignore a user (or unignore a user)
	 *
	 * @param          $sourceUsername
	 * @param          $usernameToIgnore
	 * @param DateTime $timespan
	 * @param bool     $ignore
	 *
	 * @return stdClass
	 * @throws Exception
	 */
	public function changeNotificationLevel( $sourceUsername, $usernameToIgnore, DateTime $timespan, $ignore = true ) {
		$params = [
			'notification_level' => ( $ignore ) ? 'ignore' : 'normal',
		];

		// if we are ignoring the user, add the time span (up to 4 months)
		if ( $ignore ) {
			$params['expiring_at'] = substr( $timespan->format( 'c' ), 0, 10 );
		}

		return $this->_putRequest( '/u/' . $usernameToIgnore . '/notification_level.json', [ $params ],
		                           $sourceUsername );
	}

	/**
	 * get info on a single category - by category ID only
	 *
	 * @param $categoryId
	 *
	 * @return stdClass
	 * @throws Exception
	 */
	public function getCategory( $categoryId ): stdClass {
		return $this->_getRequest( "/c/{$categoryId}.json" );
	}

	/** @noinspection MoreThanThreeArgumentsInspection * */
	/**
	 * Edit Category
	 *
	 * @param integer    $catid
	 * @param string     $allow_badges
	 * @param string     $auto_close_based_on_last_post
	 * @param string     $auto_close_hours
	 * @param string     $background_url
	 * @param string     $color
	 * @param string     $contains_messages
	 * @param string     $email_in
	 * @param string     $email_in_allow_strangers
	 * @param string     $logo_url
	 * @param string     $name
	 * @param int|string $parent_category_id
	 * @param            $groupname
	 * @param int|string $position
	 * @param string     $slug
	 * @param string     $suppress_from_homepage
	 * @param string     $text_color
	 * @param string     $topic_template
	 * @param array      $permissions
	 *
	 * @return mixed HTTP return code and API return object
	 * @throws Exception
	 */
	public function updatecat(
		$catid,
		$allow_badges = 'true',
		$auto_close_based_on_last_post = 'false',
		$auto_close_hours = '',
		$background_url,
		$color = '0E76BD',
		$contains_messages = 'false',
		$email_in = '',
		$email_in_allow_strangers = 'false',
		$logo_url = '',
		$name = '',
		$parent_category_id = '',
		$groupname,
		$position = '',
		$slug = '',
		$suppress_from_homepage = 'false',
		$text_color = 'FFFFFF',
		$topic_template = '',
		$permissions
	) {
		$params = [
			'allow_badges'                  => $allow_badges,
			'auto_close_based_on_last_post' => $auto_close_based_on_last_post,
			'auto_close_hours'              => $auto_close_hours,
			'background_url'                => $background_url,
			'color'                         => $color,
			'contains_messages'             => $contains_messages,
			'email_in'                      => $email_in,
			'email_in_allow_strangers'      => $email_in_allow_strangers,
			'logo_url'                      => $logo_url,
			'name'                          => $name,
			'parent_category_id'            => $parent_category_id,
			'position'                      => $position,
			'slug'                          => $slug,
			'suppress_from_homepage'        => $suppress_from_homepage,
			'text_color'                    => $text_color,
			'topic_template'                => $topic_template,
		];

		# Add the permissions - this is an array of group names and integer permission values.
		if ( count( $permissions ) > 0 ) {
			foreach ( $permissions as $key => $value ) {
				$params[ 'permissions[' . $key . ']' ] = $permissions[ $key ];
			}
		}

		# This must PUT
		return $this->_putRequest( '/categories/' . $catid, [ $params ] );
	}

	/**
	 * getCategories
	 *
	 * @return mixed HTTP return code and API return object
	 * @throws Exception
	 */
	public function getCategories() {
		return $this->_getRequest( '/categories.json' );
	}

	//////////////   USERS

	/**
	 * log out user - by username
	 *
	 * @param string $userName username of new user
	 *
	 * @return mixed HTTP return code and API return object
	 *
	 * @throws Exception
	 * @deprecated please use logoutUserByUsername() or logoutUserById() instead
	 *
	 */
	public function logoutUser( string $userName ) {
		return $this->logoutUserByUsername( $userName );
	}


	/**
	 * set user's info
	 * see https://github.com/discourse/discourse_api/blob/master/lib/discourse_api/api/users.rb#L32
	 *
	 * :name, :title, :bio_raw, :location, :website, :profile_background, :card_background,
	 * :email_messages_level, :mailing_list_mode, :homepage_id, :theme_ids, :user_fields
	 *
	 * @param string $userName note this can not be a discourse user ID
	 * @param array  $params   params to set
	 *
	 * @return stdClass HTTP return code and API return object
	 *
	 * @throws Exception
	 */
	public function setUserInfo( $userName, array $params ): stdClass {
		return $this->_putRequest( '/u/' . $userName . '.json', [ $params ], $userName );
	}


	/**
	 * log out user - by username
	 *
	 * @param string $userName username of new user
	 *
	 * @return mixed HTTP return code and API return object
	 * @throws Exception
	 */
	public function logoutUserByUsername( string $userName ) {
		$discourseUserId = $this->getUserByUsername( $userName )->apiresult->user->id;

		return $this->logoutUserById( $discourseUserId );
	}

	/**
	 * log out user - by user ID
	 *
	 * @param string $discourseUserId
	 *
	 * @return mixed HTTP return code and API return object
	 * @throws Exception
	 */
	public function logoutUserById( int $discourseUserId ) {

		return $this->_postRequest( '/admin/users/' . $discourseUserId . '/log_out', [] );
	}

	/**
	 * unsuspend user - by user ID
	 *
	 * @param string $discourseUserId
	 *
	 * @return mixed HTTP return code and API return object
	 * @throws Exception
	 */
	public function unsuspendUserById( int $discourseUserId ) {
		return $this->_putRequest( '/admin/users/' . $discourseUserId . '/unsuspend', [] );
	}


	/**
	 * suspend user - by user ID
	 *
	 * @param int      $discourseUserId
	 *
	 * @param DateTime $until
	 * @param          $reason
	 *
	 * @return mixed HTTP return code and API return object
	 * @throws Exception
	 */
	public function suspendUserById( int $discourseUserId, \DateTime $until, $reason ) {

		// format 'c' = 2004-02-12T15:19:21+00:00
		$date = substr( $until->format( 'c' ), 0, 10 );

		$params = [
			'suspend_until' => $date,
			'reason'        => $reason,
			'message'       => '',
			'post_action'   => 'delete',
		];

		return $this->_putRequest( '/admin/users/' . $discourseUserId . '/suspend', [ $params ] );
	}


	/**
	 * createUser
	 *
	 * @param string $name         name of new user
	 * @param string $userName     username of new user
	 * @param string $emailAddress email address of new user
	 * @param string $password     password of new user
	 * @param bool   $activate     activate user immediately (no confirmation email)?
	 *
	 * @return mixed HTTP return code and API return object
	 * @throws Exception
	 * @noinspection MoreThanThreeArgumentsInspection
	 */
	public function createUser(
		string $name,
		string $userName,
		string $emailAddress,
		string $password,
		bool $activate = true,
		int $external_id = 0
	) {

		// apparently we need to call hp.json to get a challenge string, not sure why, can't find in Discourse docs
		$obj = $this->_getRequest( '/users/hp.json' );
		if ( $obj->http_code !== 200 ) {
			return false;
		}

		$params = [
			'name'                  => $name,
			'username'              => $userName,
			'email'                 => $emailAddress,
			'password'              => $password,
			'challenge'             => strrev( $obj->apiresult->challenge ),
			'password_confirmation' => $obj->apiresult->value,
			'active'                => $activate ? 'true' : 'false',
		];

		return $this->_postRequest( '/users', [ $params ] );
	}

	/**
	 * activateUser
	 *
	 * @param integer $discourseUserId id of user to activate
	 *
	 * @return mixed HTTP return code
	 * @throws Exception
	 */
	public function activateUser( $discourseUserId ) {
		return $this->_putRequest( "/admin/users/{$discourseUserId}/activate", [] );
	}

	/**
	 * getUsernameByEmail
	 *
	 * @param string $email email of user
	 *
	 * @return mixed HTTP return code and API return object
	 * @throws Exception
	 */
	public function getUsernameByEmail( $email ) {
		$users = $this->_getRequest( '/admin/users/list/active.json?filter=' . urlencode( $email ) );
		foreach ( $users->apiresult as $user ) {
			if ( strtolower( $user->email ) === strtolower( $email ) ) {
				return $user->username;
			}
		}

		return false;
	}

	/**
	 * getUserByUsername
	 *
	 * @param string $userName username of user
	 *
	 * @return mixed HTTP return code and API return object
	 * @throws Exception
	 */
	public function getUserByUsername( $userName ) {
		return $this->_getRequest( "/users/{$userName}.json" );
	}

	/**
	 * get discourse user by their internal ID -
	 * note that this returns FULL record, including single_sign_on_record
	 *
	 * @param int $id discourse (non-external) ID
	 *
	 * @return mixed HTTP return code and API return object
	 * @throws Exception
	 */
	public function getUserByDiscourseId( $id ) {
		return $this->_getRequest( "/admin/users/{$id}.json" );
	}

	/**
	 * getUserByExternalID
	 *
	 * @param string $externalID external id of sso user
	 *
	 * @return mixed HTTP return code and API return object
	 * @throws Exception
	 */
	function getUserByExternalID( $externalID ) {
		return $this->_getRequest( "/users/by-external/{$externalID}.json" );
	}

	/**
	 * getUserIdByExternalID
	 *
	 * @param string $externalID external id of sso user
	 *
	 * @return mixed HTTP return code and API return object
	 * @throws Exception
	 */
	public function getDiscourseUserIdFromExternalId( $externalID ) {
		$res = $this->getDiscourseUserFromExternalId( $externalID );

		if ( $res ) {
			return $res->id;
		}

		return false;
	}

	/**
	 * get a discourse user reocrd from their external ID - returns the full user record
	 *
	 * @param int $externalID external id of sso user
	 *
	 * @return mixed HTTP return code and API return object
	 * @throws Exception
	 */
	public function getDiscourseUserFromExternalId( int $externalID ) {
		$res = $this->_getRequest( "/users/by-external/{$externalID}.json" );

		if ( $res->http_code == 404 ) {
			return false;
		}

		if ( is_object( $res ) && $res->apiresult->user->id ) {

			// now call this to get the FULL record, with single_sign_on_record if there
			$fullUserRecord = $this->getUserByDiscourseId( $res->apiresult->user->id );

			$r = $fullUserRecord->apiresult;

			return $r;
		}

		return false;
	}

	/**
	 * invite a user to a topic
	 *
	 * @param        $email
	 * @param        $topicId
	 * @param string $userName
	 *
	 * @return stdClass
	 * @throws Exception
	 */
	public function inviteUser( $email, $topicId, $userName = 'system' ): stdClass {
		$params = [
			'email'    => $email,
			'topic_id' => $topicId,
		];

		return $this->_postRequest( '/t/' . (int) $topicId . '/invite.json', [ $params ], $userName );
	}

	/**
	 * getUserByEmail
	 *
	 * @param string $email email of user
	 *
	 * @return mixed user object
	 * @throws Exception
	 */
	public function getUserByEmail( $email ) {
		$users = $this->_getRequest( '/admin/users/list/active.json', [
			'filter' => $email,
		] );
		foreach ( $users->apiresult as $user ) {
			if ( strtolower( $user->email ) === strtolower( $email ) ) {
				return $user;
			}
		}

		return false;
	}

	/**
	 * getUserBadgesByUsername
	 *
	 * @param string $userName username of user
	 *
	 * @return mixed HTTP return code and list of badges for given user
	 * @throws Exception
	 */
	public function getUserBadgesByUsername( $userName ) {
		return $this->_getRequest( "/user-badges/{$userName}.json" );
	}

	///////////////  POSTS

	/**
	 * createPost
	 *
	 * @param string   $bodyText
	 * @param int      $topicId
	 * @param string   $username
	 * @param DateTime $createDateTime create date/time for the post
	 *
	 * @return stdClass
	 * @throws Exception
	 */
	public function createPost( string $bodyText, int $topicId, string $username, \DateTime $createDateTime ): stdClass {
		$params = [
			'raw'       => $bodyText,
			'archetype' => 'regular',
			'topic_id'  => $topicId,
		];

		if ( $createDateTime ) {
			// Discourse likes ISO 8601 date/time format
			$params ['created_at'] = $createDateTime->format( 'c' );
		}

		return $this->_postRequest( '/posts', [ $params ], $username );
	}

	/**
	 * getPostsByNumber
	 *
	 * @param $topic_id
	 * @param $post_number
	 *
	 * @return mixed HTTP return code and API return object
	 * @throws Exception
	 */
	public function getPostsByNumber( $topic_id, $post_number ) {
		return $this->_getRequest( '/posts/by_number/' . $topic_id . '/' . $post_number . '.json' );
	}

	/**
	 * UpdatePost
	 *
	 * @param        $bodyhtml
	 * @param        $post_id
	 * @param string $userName
	 *
	 * @return stdClass
	 * @throws Exception
	 */
	public function updatePost( $bodyhtml, $post_id, $userName = 'system' ): stdClass {
		$bodyraw = htmlspecialchars_decode( $bodyhtml );
		$params  = [
			'post[cooked]'      => $bodyhtml,
			'post[edit_reason]' => '',
			'post[raw]'         => $bodyraw,
		];

		return $this->_putRequest( '/posts/' . $post_id, [ $params ], $userName );
	}

	//////////////  TOPICS

	/**
	 * createTopic
	 *
	 * this creates a new topic, then makes the first post in the topic, from the $userName with the $bodyText
	 *
	 * @param string        $topicTitle     title of topic
	 * @param string        $bodyText       body text of topic post
	 * @param string        $categoryId     must be Discourse category ID, can't be slug!
	 * @param string        $userName       user to create topic as
	 * @param int           $replyToId      optional: post id to reply as
	 * @param DateTime|null $createDateTime create datetime for topic
	 *
	 * @return mixed HTTP return code and API return object
	 *
	 * @throws Exception
	 * @noinspection MoreThanThreeArgumentsInspection
	 */
	public function createTopic(
		string $topicTitle,
		string $bodyText,
		string $categoryId,
		string $userName,
		int $replyToId = 0,
		DateTime $createDateTime = null
	) {

		if ( ! $categoryId ) {
			return false;
		}

		$params = [
			'title'                => $topicTitle,
			'raw'                  => $bodyText,
			'category'             => $categoryId,
			'archetype'            => 'regular',        // not a private_message
			'reply_to_post_number' => $replyToId,
		];

		if ( $createDateTime ) {
			// Discourse likes ISO 8601 date/time format
			$params ['created_at'] = $createDateTime->format( 'c' );
		}

		// https://docs.discourse.org/#tag/Topics/paths/~1posts.json/post
		return $this->_postRequest( '/posts', [ $params ], $userName );
	}

	/**
	 * get info on a topic - by topic ID or slug
	 *
	 * @param $topicIdOrSlug
	 *
	 * @return stdClass
	 * @throws Exception
	 */
	public function getTopic( $topicIdOrSlug ): stdClass {
		return $this->_getRequest( "/t/{$topicIdOrSlug}.json" );
	}

	/**
	 * topTopics
	 *
	 * @param string $category slug of category
	 * @param string $period   daily, weekly, monthly, yearly
	 *
	 * @return mixed HTTP return code and API return object
	 * @throws Exception
	 */
	public function topTopics( $category, $period = 'daily' ) {
		return $this->_getRequest( '/c/' . $category . '/l/top/' . $period . '.json' );
	}

	/**
	 * latestTopics
	 *
	 * @param string $category slug of category
	 *
	 * @return mixed HTTP return code and API return object
	 * @throws Exception
	 */
	public function latestTopics( $category ) {
		return $this->_getRequest( '/c/' . $category . '/l/latest.json' );
	}

	////////////// MISC

	/**
	 * change site setting
	 *
	 * @param $siteSetting
	 * @param $value
	 *
	 * @return stdClass
	 * @throws Exception
	 */
	public function changeSiteSetting( $siteSetting, $value ): stdClass {
		$params = [
			$siteSetting => $value,
		];

		return $this->_putRequest( '/admin/site_settings/' . $siteSetting, [ $params ] );
	}


	//////////////// Private Functions

	/** @noinspection MoreThanThreeArgumentsInspection */
	/**
	 * @param string $requestString
	 * @param array  $paramArray
	 * @param string $apiUser
	 * @param string $httpMethod
	 *
	 * @return stdClass
	 *
	 * @throws Exception
	 */
	private function _getRequest(
		string $requestString,
		array $paramArray = [],
		string $apiUser = 'system',
		string $httpMethod = 'GET'
	): stdClass {
		$paramArray['show_emails'] = 'true';

		// set up headers for HTTP request we're about to make
		$headers = [
			'Api-Key: ' . $this->_apiKey,
			'Api-Username: ' . $apiUser,
		];

		if ( $this->debugGetRequest ) {
			echo "\nDiscourse-API DEBUG: user '" . $apiUser . "' making $httpMethod request: $requestString, parameters: " . json_encode( $paramArray ) . " \n";
		}

		$ch  = curl_init();
		$url = sprintf( '%s://%s%s?%s', $this->_protocol, $this->_discourseHostname, $requestString,
		                http_build_query( $paramArray ) );

		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $httpMethod );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );

		$body = curl_exec( $ch );
		$rc   = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		$resObj            = new stdClass();
		$resObj->http_code = $rc;

		// Only return valid json
		$json              = json_decode( $body );
		$resObj->apiresult = $body;
		if ( json_last_error() === JSON_ERROR_NONE ) {
			$resObj->apiresult = $json;
		}

		if ( $resObj->http_code == 429 ) {
			throw new Exception( 'Rate limit', 429 );
		}

		return $resObj;
	}

	/** @noinspection MoreThanThreeArgumentsInspection * */
	/**
	 * @param string $reqString
	 * @param array  $paramArray
	 * @param string $apiUser
	 * @param string $httpMethod
	 *
	 * @return stdClass
	 *
	 * @throws Exception
	 */
	private function _putpostRequest(
		string $reqString,
		array $paramArray,
		string $apiUser = 'system',
		$httpMethod = 'POST'
	): stdClass {

		$type = 'multipart/form-data';

		// prepare query body in x-www-form-url-encoded format
		// see https://stackoverflow.com/questions/4007969/application-x-www-form-urlencoded-or-multipart-form-data

		// special flag for upload file!
		if ( isset( $paramArray['uploadFile'] ) ) {
			// we are trying to upload a file, so we prepare the curl POSTFIELDS a little different
			// see http://code.iamkate.com/php/sending-files-using-curl/
			$query = [];
			foreach ( $paramArray as $k => $v ) {
				$query[ $k ] = $v;
			}
		} else {
			// just build normal query here
			$query = '';
			if ( isset( $paramArray['group'] ) && is_array( $paramArray['group'] ) ) {
				$query = http_build_query( $paramArray );
			} else {
				if ( is_array( $paramArray[0] ) ) {
					foreach ( $paramArray[0] as $param => $value ) {
						$query .= $param . '=' . urlencode( $value ) . '&';
					}
				}
			}
			$query = trim( $query, '&' );
		}

		if ( $this->debugPutPostRequest ) {
			$queryDebug = is_array( $query ) ? json_encode( $query ) : $query;
			echo "\nDiscourse-API DEBUG: user '" . $apiUser . "' making $httpMethod request: $reqString, parameters: " . json_encode( $paramArray ) . " - " . $queryDebug . "\n\n";
		}

		// set up headers for HTTP request we're about to make
		$headers = [
			// see https://stackoverflow.com/questions/4007969/application-x-www-form-urlencoded-or-multipart-form-data
			'Content-Type: ' . $type,
			'Api-Key: ' . $this->_apiKey,
			'Api-Username: ' . $apiUser,
		];

		// fire up curl and send request
		$ch  = curl_init();
		$url = sprintf( '%s://%s%s', $this->_protocol, $this->_discourseHostname,
		                $reqString ); //, $this->_apiKey, $apiUser );

		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $query );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $httpMethod );

		// curl_setopt( $ch, CURLOPT_VERBOSE, 1 );

		// make the call and get the results
		$body = curl_exec( $ch );
		$rc   = curl_getinfo( $ch, CURLINFO_HTTP_CODE );

		curl_close( $ch );

		$resObj            = new stdClass();
		$json              = json_decode( $body );
		$resObj->apiresult = $body;
		if ( json_last_error() === JSON_ERROR_NONE ) {
			$resObj->apiresult = $json;
		}

		$resObj->http_code = $rc;

		if ( $resObj->http_code == 429 ) {
			throw new Exception( 'Rate limit', 429 );
		}

		return $resObj;
	}

	/**
	 * @param string $reqString
	 * @param array  $paramArray
	 * @param string $apiUser
	 *
	 * @return stdClass
	 * @throws Exception
	 */
	private function _deleteRequest( string $reqString, array $paramArray, string $apiUser = 'system' ): stdClass {
		return $this->_putpostRequest( $reqString, $paramArray, $apiUser, 'DELETE' );
	}

	/**
	 * @param string $reqString
	 * @param array  $paramArray
	 * @param string $apiUser
	 *
	 * @return stdClass
	 * @throws Exception
	 */
	private function _putRequest( string $reqString, array $paramArray, string $apiUser = 'system' ): stdClass {
		return $this->_putpostRequest( $reqString, $paramArray, $apiUser, 'PUT' );
	}

	/**
	 * @param string $reqString
	 * @param array  $paramArray
	 * @param string $apiUser
	 *
	 * @return stdClass
	 * @throws Exception
	 */
	private function _postRequest( string $reqString, array $paramArray, string $apiUser = 'system' ): stdClass {
		/** @noinspection ArgumentEqualsDefaultValueInspection * */
		return $this->_putpostRequest( $reqString, $paramArray, $apiUser, 'POST' );
	}

	/**
	 * DiscourseAPI constructor.
	 *
	 * @param string $discourseHostname
	 * @param null   $apiKey
	 * @param string $protocol
	 */
	public function __construct( $discourseHostname, $apiKey = null, $protocol = 'https' ) {
		$this->_discourseHostname = $discourseHostname;
		$this->_apiKey            = $apiKey;
		$this->_protocol          = $protocol;
	}

	/**
	 * upload image to Discourse
	 * you have to do this before you can insert it into a message, or whatever
	 *
	 * @return object(stdClass)#13 (2) {
	 * ["apiresult"]=>
	 * object(stdClass)#16 (12) {
	 * ["id"]=>
	 * int(24)
	 * ["url"]=>
	 * string(74) "/uploads/default/original/1X/7831a3ef6e0ee3c584037a2f0bc3d476db2650eb.jpeg"
	 * ["original_filename"]=>
	 * string(12) "cat face.jpg"
	 * ["filesize"]=>
	 * int(84958)
	 * ["width"]=>
	 * int(768)
	 * ["height"]=>
	 * int(960)
	 * ["thumbnail_width"]=>
	 * int(400)
	 * ["thumbnail_height"]=>
	 * int(500)
	 * ["extension"]=>
	 * string(4) "jpeg"
	 * ["short_url"]=>
	 * string(41) "upload://h9hEkn0sHg4AGsq4EbOfNNx41T5.jpeg"
	 * ["retain_hours"]=>
	 * NULL
	 * ["human_filesize"]=>
	 * string(5) "83 KB"
	 * }
	 * ["http_code"]=>
	 * int(200)
	 * }
	 *
	 * @param string $fullPath
	 *
	 * @return stdClass
	 * @throws Exception
	 */
	public function uploadImage( string $fullPath, string $filename, string $mimeFiletype ) {
		// see https://www.php.net/manual/en/class.curlfile.php
		$cfile = new \CURLFile( $fullPath, $mimeFiletype, $filename ); // try adding

		$cfile->type = 'upload';

		$params = [
			'file'       => $cfile,
			'type'       => 'upload',
			'uploadFile' => true,
		];

		return $this->_postRequest( '/uploads.json', $params );
	}

	/**
	 * @param       $email
	 * @param       $username
	 * @param array $otherParameters external_id, add_groups, require_activation
	 *
	 * @return stdClass
	 * @throws Exception
	 */
	public function syncSso( $email, $username, array $otherParameters = [] ) {
		// Create an array of SSO parameters.
		$sso_params = [
			'email'    => $email,
			'username' => $username,
		];

		if ( $otherParameters ) {
			$sso_params = array_merge( $sso_params, $otherParameters );
		}

		// Convert the SSO parameters into the SSO payload and generate the SSO signature.
		$sso_payload = base64_encode( http_build_query( $sso_params ) );
		$sig         = hash_hmac( 'sha256', $sso_payload, $this->sso_secret );

		$url         = 'https://forum.example.com/admin/users/sync_sso';
		$post_fields = [
			'sso' => $sso_payload,
			'sig' => $sig,
		];

		return $this->_postRequest( '/admin/users/sync_sso', [ $post_fields ] );
	}

	/**
	 * @return string
	 */
	public function getSsoSecret(): string {
		return $this->sso_secret;
	}

	/**
	 * @param string $sso_secret
	 */
	public function setSsoSecret( string $sso_secret ): void {
		$this->sso_secret = $sso_secret;
	}

	/**
	 * @return bool
	 */
	public function isDebugPutPostRequest(): bool {
		return $this->debugPutPostRequest;
	}

	/**
	 * @param bool $debugPutPostRequest
	 */
	public function setDebugPutPostRequest( bool $debugPutPostRequest ): void {
		$this->debugPutPostRequest = $debugPutPostRequest;
	}

	/**
	 * @return bool
	 */
	public function isDebugGetRequest(): bool {
		return $this->debugGetRequest;
	}

	/**
	 * @param bool $debugGetRequest
	 */
	public function setDebugGetRequest( bool $debugGetRequest ): void {
		$this->debugGetRequest = $debugGetRequest;
	}

}
