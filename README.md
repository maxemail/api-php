Maxemail API Client for PHP
===========================

Self-contained client in PHP for simplifying access to the Maxemail API

Installation
------------

Including this package in your application is made easy by using [Composer](https://getcomposer.org/doc/05-repositories.md#loading-a-package-from-a-vcs-repository).
Simply include this repository and the relevant requirement line in your `composer.json`.

```json
{
    "repositories": [
        {
            "type": "git",
            "url": "https://github.com/emailcenter/mxm-api-php.git"
        }
    ],
    "require": {
        "emailcenter/mxm-api-php": "~2.0"
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
    'url' => 'https://maxemail.emailcenteruk.com/',
    'user' => 'api@user.com',
    'pass' => 'apipass'
);
$api = new \Mxm\Api($config);
 
 
// General:
$result = $api->serviceName->method($arg1, $arg2);
var_dump($result);
```
