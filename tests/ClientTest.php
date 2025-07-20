<?php

declare(strict_types=1);

namespace Maxemail\Api;

use GuzzleHttp\ClientInterface as GuzzleClient;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Maxemail API Client
 *
 * @package    Maxemail\Api
 * @copyright  2007-2019 Emailcenter UK Ltd. (https://maxemail.xtremepush.com)
 * @license    LGPL-3.0
 */
class ClientTest extends TestCase
{
    private $testConfig = [
        'uri' => 'https://maxemail.example.com/',
        'username' => 'clientId',
        'password' => 'clientSecret',
    ];

    public function testConfigValid()
    {
        $api = new Client($this->testConfig);

        $factory = function (array $actual): GuzzleClient {
            $expectedUri = $this->testConfig['uri'] . 'api/json/';
            static::assertSame($expectedUri, $actual['base_uri']);

            $expectedAuth = [
                $this->testConfig['username'],
                $this->testConfig['password'],
            ];
            static::assertSame($expectedAuth, $actual['auth']);

            $expectedHeaders = [
                'User-Agent' => 'MxmApiClient/' . Client::VERSION . ' PHP/' . PHP_VERSION,
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
            ];
            static::assertSame($expectedHeaders, $actual['headers']);

            return $this->createMock(GuzzleClient::class);
        };

        $api->setHttpClientFactory($factory);

        // Get a service, to trigger the HTTP Client factory
        $api->folder;
    }

    public function testConfigSupportDeprecatedUserPass()
    {
        $config = [
            'user' => 'api@user.com',
            'pass' => 'apipass',
        ];

        $api = new Client($config);

        $factory = function (array $actual) use ($config): GuzzleClient {
            $expectedAuth = [
                $config['user'],
                $config['pass'],
            ];
            static::assertSame($expectedAuth, $actual['auth']);

            return $this->createMock(GuzzleClient::class);
        };

        $api->setHttpClientFactory($factory);

        // Get a service, to trigger the HTTP Client factory
        $api->folder;
    }

    public function testConfigDefaultHost()
    {
        $config = [
            'username' => 'clientId',
            'password' => 'clientSecret',
        ];

        $api = new Client($config);

        $factory = function (array $actual) use ($config): GuzzleClient {
            $expectedUri = 'https://mxm.xtremepush.com/api/json/';
            static::assertSame($expectedUri, $actual['base_uri']);

            return $this->createMock(GuzzleClient::class);
        };

        $api->setHttpClientFactory($factory);

        // Get a service, to trigger the HTTP Client factory
        $api->folder;
    }

    public function testConfigStripsUriPath()
    {
        $config = [
            'uri' => 'https://maxemail.example.com/some/extra/path',
            'username' => 'clientId',
            'password' => 'clientSecret',
        ];

        $api = new Client($config);

        $factory = function (array $actual) use ($config): GuzzleClient {
            $expectedUri = 'https://maxemail.example.com/api/json/';
            static::assertSame($expectedUri, $actual['base_uri']);

            return $this->createMock(GuzzleClient::class);
        };

        $api->setHttpClientFactory($factory);

        // Get a service, to trigger the HTTP Client factory
        $api->folder;
    }

    public function testConfigInvalidUri()
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('URI malformed');

        $config = [
            'uri' => '//',
            'username' => 'clientId',
            'password' => 'clientSecret',
        ];

        new Client($config);
    }

    public function testConfigMissingUriProtocol()
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('URI must contain protocol scheme and host');

        $config = [
            'uri' => 'maxemail.example.com',
            'username' => 'clientId',
            'password' => 'clientSecret',
        ];

        new Client($config);
    }

    public function testConfigMissingUsername(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('API config requires username & password');

        $config = [
            'password' => 'clientSecret',
        ];

        new Client($config);
    }

    public function testConfigMissingPassword(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('API config requires username & password');

        $config = [
            'username' => 'clientId',
        ];

        new Client($config);
    }

    public function testGetConfig(): void
    {
        $api = new Client($this->testConfig);

        static::assertSame($this->testConfig, $api->getConfig());
    }

    public function testSetGetLogger()
    {
        /** @var \Psr\Log\LoggerInterface|MockObject $logger */
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);

        $api = new Client($this->testConfig);

        $api->setLogger($logger);

        $this->assertSame($logger, $api->getLogger());
    }

    public function testGetHelper()
    {
        $api = new Client($this->testConfig);

        $helper = $api->getHelper();

        $this->assertInstanceOf(Helper::class, $helper);
    }

    /**
     * Test getInstance() returns same Service instance for same name, different for different name
     */
    public function testGetInstance()
    {
        $api = new Client($this->testConfig);

        $originalService = $api->service;
        $sameService = $api->service;
        $differentService = $api->different;

        $this->assertSame($originalService, $sameService);
        $this->assertNotSame($originalService, $differentService);
    }
}
