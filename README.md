Maxemail JSON Client
====================

Self-contained client in PHP for simplifying access to the Maxemail API

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
