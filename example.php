<?php

use pnoeric\DiscourseAPI;

// ideally you have a composer auto-loader in place, but if not, you can just require the library:
// require_once 'lib/DiscourseAPI.php';

// note that you need to specify the URL of your Discourse install, and
// for API_KEY you'll need your key from Discourse
$api = new DiscourseAPI( DISCOURSE_URL, API_KEY, 'https' );

// create user
$r = $api->createUser( 'John Doe', 'johndoe', 'johndoe@discoursehosting.com', 'foobar!!' );
print_r( $r );

// in order to activate we need the id
$r = $api->getUserByUsername( 'johndoe' );
print_r( $r );

// activate the user
$r = $api->activateUser( $r->apiresult->user->id );
print_r( $r );

// create a category
$r = $api->createCategory( 'a new category', 'cc2222' );
print_r( $r );

$catId = $r->apiresult->category->id;

// create a topic in a category
$r = $api->createTopic(
	'This is the title of a brand new topic',
	"This is the body text of a brand new topic. What else is there to say? Enjoy the topic! Hurrah!",
	$catId,
	'johndoe'
);
print_r( $r );

$topicId = $r->apiresult->id;

// create a post in a topic
$now = new DateTime();

$r = $api->createPost(
	'This is the body of a new post in an existing topic',
	$topicId,
	'johndoe',
	$now
);

// change site setting (these are admin-only settings, NOT user preferences)
// use 'true' and 'false' between quotes
$r = $api->changeSiteSetting( 'invite_expiry_days', 29 );
print_r( $r );

// log off user
$api->logoutUserByUsername( 'johndoe' );

// and there are many, many more methods in the API... check 'em out!