Maxemail JSON Client
====================

Self contained JSON client in PHP for simplifying access to the Maxemail API

Usage Example
-------------
```php
// Instantiate Client:
$config = array(
    'url' => 'https://maxemail.emailcenteruk.com/',
    'user' => 'api@user.com',
    'pass' => 'apipass'
);
$client = new Mxm_Api_JsonClient($config);
 
 
// General:
$result = $client->serviceName->method($arg1, $arg2);
var_dump($result);
```