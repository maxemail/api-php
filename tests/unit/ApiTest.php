<?php

namespace Mxm\Test;

use Mxm\Api;

class ApiTest extends \PHPUnit_Framework_TestCase
{
    private $testConfig = [
        'host'   => 'maxemail.emailcenteruk.com',
        'user'   => 'api@user.com',
        'pass'   => 'apipass',
        'useSsl' => true
    ];

    public function testConfigValid()
    {
        $api = new Api($this->testConfig);

        $this->assertEquals($this->testConfig, $api->getConfig());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid hostname
     */
    public function testConfigInvalidHostProtocol()
    {
        $config = [
            'host' => 'https://maxemail.emailcenteruk.com'
        ];

        new Api($config);
    }

    public function testSetGetLogger()
    {
        /** @var \Psr\Log\LoggerInterface|\PHPUnit_Framework_MockObject_MockObject $logger */
        $logger = $this->getMock('\Psr\Log\LoggerInterface');

        $api = new Api($this->testConfig);

        $api->setLogger($logger);

        $this->assertEquals($logger, $api->getLogger());
    }
}
