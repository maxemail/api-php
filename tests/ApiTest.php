<?php
declare(strict_types=1);

namespace Mxm;

use Mxm\Api\Exception\InvalidArgumentException;
use Mxm\Api\Helper;
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    private $testConfig = [
        'uri'      => 'https://maxemail.example.com/',
        'username' => 'api@user.com',
        'password' => 'apipass'
    ];

    public function testConfigValid()
    {
        $api = new Api($this->testConfig);

        $this->assertEquals($this->testConfig, $api->getConfig());
    }

    public function testConfigSupportDeprecatedUserPass()
    {
        $config = [
            'user' => 'api@user.com',
            'pass' => 'apipass'
        ];

        $api = new Api($config);

        $this->assertEquals($config['user'], $api->getConfig()['username']);
        $this->assertEquals($config['pass'], $api->getConfig()['password']);
    }

    public function testConfigDefaultHost()
    {
        $config = [
            'username' => 'api@user.com',
            'password' => 'apipass'
        ];

        $api = new Api($config);

        $this->assertEquals('https://maxemail.emailcenteruk.com/', $api->getConfig()['uri']);
    }

    public function testConfigStripsUriPath()
    {
        $config = [
            'uri' => 'http://maxemail.example.com/some/extra/path',
            'username' => 'api@user.com',
            'password' => 'apipass'
        ];

        $api = new Api($config);

        $this->assertEquals('http://maxemail.example.com/', $api->getConfig()['uri']);
    }

    public function testConfigInvalidUri()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('URI malformed');

        $config = [
            'uri' => '//',
            'username' => 'api@user.com',
            'password' => 'apipass'
        ];

        new Api($config);
    }

    public function testConfigMissingUriProtocol()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('URI must contain protocol scheme and host');

        $config = [
            'uri' => 'maxemail.example.com',
            'username' => 'api@user.com',
            'password' => 'apipass'
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
