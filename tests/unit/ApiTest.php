<?php
declare(strict_types=1);

namespace Mxm;

use Mxm\Api\Exception\InvalidArgumentException;
use Mxm\Api\Helper;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
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

    public function testConfigInvalidHostProtocol()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid hostname');

        $config = [
            'host' => 'https://maxemail.emailcenteruk.com'
        ];

        new Api($config);
    }

    public function testSetGetLogger()
    {
        /** @var \Psr\Log\LoggerInterface|\PHPUnit_Framework_MockObject_MockObject $logger */
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        $api = new Api($this->testConfig);

        $api->setLogger($logger);

        $this->assertEquals($logger, $api->getLogger());
    }

    public function testGetHelper()
    {
        $api = new Api($this->testConfig);

        $helper = $api->getHelper();

        $this->assertInstanceOf(Helper::class, $helper);
    }

    /**
     * Test getInstance() returns same JsonClient for same service, different for different service
     */
    public function testGetInstance()
    {
        $api = new Api($this->testConfig);

        $originalService = $api->service;
        $sameService = $api->service;
        $differentService = $api->different;

        $this->assertSame($originalService, $sameService);
        $this->assertNotSame($originalService, $differentService);
    }
}
