<?php

namespace Mxm\Test\Api;

use Mxm\Api\JsonClient;

class JsonClientTest extends \PHPUnit_Framework_TestCase
{
    private $testConfig = [
        'host'   => 'maxemail.emailcenteruk.com',
        'user'   => 'api@user.com',
        'pass'   => 'apipass',
        'useSsl' => true
    ];

    public function testSetGetLogger()
    {
        /** @var \Psr\Log\LoggerInterface|\PHPUnit_Framework_MockObject_MockObject $logger */
        $logger = $this->getMock('\Psr\Log\LoggerInterface');

        $api = new JsonClient('service', $this->testConfig);

        $api->setLogger($logger);

        $this->assertEquals($logger, $api->getLogger());
    }
}
