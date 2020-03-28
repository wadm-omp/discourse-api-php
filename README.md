# discourse-api-php

This is a composer packaged version of the PHP library for accessing the Discourse API as published by DiscourseHosting at https://github.com/discoursehosting/discourse-api-php

### Includes contributions from:

* pnoeric (https://github.com/pnoeric/)
* https://github.com/kazad/discourse-api-php
* https://github.com/timolaine/discourse-api-php

## Testing

#### Setup

1. Run `composer install` to install PHPUnit and other helper libraries
2. Copy the `.env.example` file in the tests folder, change the name to `.env` and add your Discourse URL and API key

#### Running tests

In the terminal, from the root directory of this project, enter: `./vendor/bin/phpunit tests/DiscourseApiTest.php`