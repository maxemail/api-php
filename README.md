# Emailcenter Maxemail API Client for PHP

[![Build Status](https://img.shields.io/travis/emailcenter/mxm-api-php/master.svg)](https://travis-ci.org/emailcenter/mxm-api-php)
[![Codecov](https://img.shields.io/codecov/c/github/emailcenter/mxm-api-php.svg)](https://codecov.io/gh/emailcenter/mxm-api-php)
[![Latest Stable Version](https://img.shields.io/packagist/v/emailcenter/mxm-api-php.svg)](https://packagist.org/packages/emailcenter/mxm-api-php)
[![Total Downloads](https://img.shields.io/packagist/dt/emailcenter/mxm-api-php.svg)](https://packagist.org/packages/emailcenter/mxm-api-php)
![Licence](https://img.shields.io/github/license/emailcenter/mxm-api-php.svg)

Self-contained client in PHP for simplifying access to the Maxemail API

## Requirements

![PHP](https://img.shields.io/badge/php-%5E7.0-brightgreen.svg)

This package requires PHP 7.x . Please see previous releases if you
require compatibility with an older version of PHP.

Composer will verify any other environment requirements on install/update.

When creating a new major version of this package, we MAY drop support for PHP
versions which are no longer
[actively supported](https://php.net/supported-versions.php) by the PHP project.


## Installation

Including this package in your application is made easy by using
[Composer](https://getcomposer.org).

```sh
$ composer require emailcenter/mxm-api-php
```

## Usage Example

```php
// Instantiate Client:
$config = [
    'username' => 'api@user.com',
    'password' => 'apipass'
];
$api = new \Mxm\Api($config);

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
