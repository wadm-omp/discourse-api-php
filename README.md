# discourse-api-php

This is a composer packaged version of the PHP library for accessing the Discourse API.

# Features

* Supports header-based authentication (required by Discourse as of April 2020)
* Package includes test suite for developers
 
### History

Originally as published by DiscourseHosting at https://github.com/discoursehosting/discourse-api-php

With contributions from:

* https://github.com/richp10
* https://github.com/timolaine
* https://github.com/vinkashq
* https://github.com/kazad/discourse-api-php
* https://github.com/timolaine/discourse-api-php

Many methods added and major refactor by Eric Mueller March/April 2020, now lives at https://github.com/pnoeric/discourse-api-php

## Testing

### Setup

1. Run `composer install` to install PHPUnit and other helper libraries
2. Copy the `.env.example` file in the tests folder, change the name to `.env` and fill in the blanks

### Running tests

In the terminal, from the root directory of this project, enter: `./vendor/bin/phpunit tests/DiscourseApiTest.php`