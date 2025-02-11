<?php

declare(strict_types=1);

namespace Maxemail\Api;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Maxemail API Client
 *
 * @package    Maxemail\Api
 * @copyright  2007-2019 Emailcenter UK Ltd. (https://maxemail.xtremepush.com)
 * @license    LGPL-3.0
 */
class ApiTest extends TestCase
{
    private array $testConfig = [
        'uri' => 'https://maxemail.example.com/',
        'username' => 'api@user.com',
        'password' => 'apipass',
    ];

    public function testConfigValid(): void
    {
        $api = new Client($this->testConfig);

        $this->assertSame($this->testConfig, $api->getConfig());
    }

    public function testConfigSupportDeprecatedUserPass(): void
    {
        $config = [
            'user' => 'api@user.com',
            'pass' => 'apipass',
        ];

        $api = new Client($config);

        $this->assertSame($config['user'], $api->getConfig()['username']);
        $this->assertSame($config['pass'], $api->getConfig()['password']);
    }

    public function testConfigDefaultHost(): void
    {
        $config = [
            'username' => 'api@user.com',
            'password' => 'apipass',
        ];

        $api = new Client($config);

        $this->assertSame('https://mxm.xtremepush.com/', $api->getConfig()['uri']);
    }

    public function testConfigStripsUriPath(): void
    {
        $config = [
            'uri' => 'http://maxemail.example.com/some/extra/path',
            'username' => 'api@user.com',
            'password' => 'apipass',
        ];

        $api = new Client($config);

        $this->assertSame('http://maxemail.example.com/', $api->getConfig()['uri']);
    }

    public function testConfigInvalidUri(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('URI malformed');

        $config = [
            'uri' => '//',
            'username' => 'api@user.com',
            'password' => 'apipass',
        ];

        new Client($config);
    }

    public function testConfigMissingUriProtocol(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('URI must contain protocol scheme and host');

        $config = [
            'uri' => 'maxemail.example.com',
            'username' => 'api@user.com',
            'password' => 'apipass',
        ];

        new Client($config);
    }

    public function testSetGetLogger(): void
    {
        /** @var \Psr\Log\LoggerInterface|MockObject $logger */
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        $api = new Client($this->testConfig);

        $api->setLogger($logger);

        $this->assertSame($logger, $api->getLogger());
    }

    public function testGetHelper(): void
    {
        $api = new Client($this->testConfig);

        $helper = $api->getHelper();

        $this->assertInstanceOf(Helper::class, $helper);
    }

    /**
     * Test getInstance() returns same Service instance for same name, different for different name
     */
    public function testGetInstance(): void
    {
        $api = new Client($this->testConfig);

        $originalService = $api->service;
        $sameService = $api->service;
        $differentService = $api->different;

        $this->assertSame($originalService, $sameService);
        $this->assertNotSame($originalService, $differentService);
    }
}
