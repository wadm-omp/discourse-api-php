# discourse-api-php

This is a composer packaged version of the PHP library for accessing the Discourse API.

# Features

* Supports header-based authentication (required by Discourse as of April 2020)
* Includes test suite for developers
 
### History

Originally as published by DiscourseHosting at https://github.com/discoursehosting/discourse-api-php.

With contributions from:

* https://github.com/richp10
* https://github.com/timolaine
* https://github.com/vinkashq
* https://github.com/kazad/discourse-api-php
* https://github.com/timolaine/discourse-api-php

Many methods added and major refactor by Eric Mueller March/April 2020, now lives at https://github.com/pnoeric/discourse-api-php.

# How to Use

Just include it with Composer, and then:

```PHP
$hostname = 'forums.example.com';

// set this up in Discourse first
$key = 'my-secret-discourse-api-key';

$api = new \pnoeric\DiscourseAPI($hostname, $key);

// and you're off and running!
$api->getTopTopics();

// if you aren't sure what the API results, just look at it:
$results = $api->getUserByDiscourseId( 1 );
var_dump($results);
```

For more examples, check out `tests/DiscourseApiTest.php`.

# For Developers

I'd love to see your changes and improvements to this library! Please feel free to submit a pull request, and please include a new test if you are adding/refactoring methods.

## Testing

### Setup

1. Run `composer install` to install PHPUnit and other helper libraries
2. Copy the `.env.example` file in the tests folder, change the name to `.env` and fill in the blanks

### Running tests

In the terminal, from the root directory of this project, enter: `./vendor/bin/phpunit tests/DiscourseApiTest.php`