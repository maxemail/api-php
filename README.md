Maxemail API Client for PHP
===========================

[![Build Status](https://img.shields.io/travis/emailcenter/mxm-api-php/master.svg?style=flat-square)](https://travis-ci.org/emailcenter/mxm-api-php)
[![Codecov](https://img.shields.io/codecov/c/github/emailcenter/mxm-api-php.svg?style=flat-square)](https://codecov.io/gh/emailcenter/mxm-api-php)
[![Latest Stable Version](https://img.shields.io/packagist/v/emailcenter/mxm-api-php.svg?style=flat-square)](https://packagist.org/packages/emailcenter/mxm-api-php)
[![Total Downloads](https://img.shields.io/packagist/dt/emailcenter/mxm-api-php.svg?style=flat-square)](https://packagist.org/packages/emailcenter/mxm-api-php)
![Licence](https://img.shields.io/github/license/emailcenter/mxm-api-php.svg?style=flat-square)

Self-contained client in PHP for simplifying access to the Maxemail API

Installation
------------

Including this package in your application is made easy by using
[Composer](https://getcomposer.org).

```sh
$ composer require emailcenter/mxm-api-php
```

Usage Example
-------------

```php
// Instantiate Client:
$config = array(
    'host' => 'maxemail.emailcenteruk.com',
    'user' => 'api@user.com',
    'pass' => 'apipass'
);
$api = new \Mxm\Api($config);

// General:
$result = $api->serviceName->method($arg1, $arg2);
var_dump($result);
```

Logging
-------

If you want more useful development-time debug info, throw the API a PSR-compatible logger:

```php
$logger = new Logger(); // Must implement \Psr\Log\LoggerInterface
$api->setLogger($logger);
```

For a quick-start to logging (plus advanced multi-destination logging!), see [Phlib/Logger](https://github.com/phlib/logger)

Helpers
-------

The client also includes a *Helper* class to take care of common scenarios that
are more complicated than the simple request-response model.

The helper is accessed from the client by the `getHelper()` method:

```php
$api->getHelper()->downloadFile(...);
```

See the in-line documentation for helper methods for the required and optional
parameters.
