# Maxemail API Client for PHP

[![Code Checks](https://img.shields.io/github/actions/workflow/status/maxemail/api-php/code-checks.yml?logo=github)](https://github.com/maxemail/api-php/actions/workflows/code-checks.yml)
[![Codecov](https://img.shields.io/codecov/c/github/maxemail/api-php.svg?logo=codecov)](https://codecov.io/gh/maxemail/api-php)
[![Latest Stable Version](https://img.shields.io/packagist/v/maxemail/api-php.svg?logo=packagist)](https://packagist.org/packages/maxemail/api-php)
[![Total Downloads](https://img.shields.io/packagist/dt/maxemail/api-php.svg?logo=packagist)](https://packagist.org/packages/maxemail/api-php)
![Licence](https://img.shields.io/github/license/maxemail/api-php.svg)

Self-contained client in PHP for simplifying access to the Maxemail API

## Requirements

![PHP](https://img.shields.io/badge/php-%5E7.1-brightgreen.svg)

This package requires at least PHP 7.1 . Please see previous releases if you
require compatibility with an older version of PHP.

Composer will verify any other environment requirements on install/update.

When creating a new major version of this package, we MAY drop support for PHP
versions which are no longer
[actively supported](https://php.net/supported-versions.php) by the PHP project.


## Installation

Including this package in your application is made easy by using
[Composer](https://getcomposer.org).

```sh
$ composer require maxemail/api-php
```

## Usage Example

```php
// Instantiate Client:
$config = [
    'username' => 'api@user.com',
    'password' => 'apipass'
];
$api = new \Maxemail\Api\Client($config);

// General:
$result = $api->serviceName->method($arg1, $arg2);
var_dump($result);
```

## Logging

If you want more useful development-time debug info, throw the API a PSR-compatible logger:

```php
$logger = new Logger(); // Must implement \Psr\Log\LoggerInterface
$api->setLogger($logger);
```

For a quick-start to logging (plus advanced multi-destination logging!), see [Phlib/Logger](https://github.com/phlib/logger)

## Helpers

The client also includes a *Helper* class to take care of common scenarios that
are more complicated than the simple request-response model.

The helper is accessed from the client by the `getHelper()` method:

```php
$api->getHelper()->downloadFile(...);
```

See the in-line documentation for helper methods for the required and optional
parameters.

## License

This package is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
