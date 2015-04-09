Maxemail API Client for PHP
===========================

Self-contained client in PHP for simplifying access to the Maxemail API

Installation
------------

Including this package in your application is made easy by using [Composer](https://getcomposer.org).
Simply include this requirement line in your `composer.json`.

```json
{
    "require": {
        "emailcenter/mxm-api-php": "~3.0"
    }
}
```

You can of course still include the files manually if you wish. You just need
the contents of the `src` directory.

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
