<?php

declare(strict_types=1);

namespace Maxemail\Api;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Maxemail\Api\Exception\ClientException as MxmClientException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Maxemail API Client
 *
 * @package    Maxemail\Api
 * @copyright  2007-2019 Emailcenter UK Ltd. (https://maxemail.xtremepush.com)
 * @license    LGPL-3.0
 */
class MiddlewareTest extends TestCase
{
    private GuzzleClient $httpClient;

    private MockHandler $mockHandler;

    private HandlerStack $handlerStack;

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();

        $this->handlerStack = HandlerStack::create($this->mockHandler);

        $this->httpClient = new GuzzleClient([
            'handler' => $this->handlerStack,
        ]);
    }

    public function testWarningLoggerCreatesLog(): void
    {
        $warningMsg = 'dummyMethod Deprecated: some example description';
        $warning = "299 MxmApi/v100 \"{$warningMsg}\"";

        // @todo phpunit > v7, change to `expectDeprecation()` etc.
        // Requires convertDeprecationsToExceptions='true' in PHPUnit config
        if (version_compare(\PHPUnit\Runner\Version::id(), '8.0.0') < 0) {
            // PHPUnit v7
            $this->expectException(\PHPUnit\Framework\Error\Deprecated::class);
        } else {
            // PHPUnit v8+
            $this->expectDeprecation();
        }
        $this->expectExceptionMessage($warningMsg);

        /** @var LoggerInterface|MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())
            ->method('warning')
            ->with($warningMsg);

        Middleware::addWarningLogging($this->handlerStack, $logger);
        $this->mockHandler->append(
            new Response(
                200,
                [
                    'Warning' => $warning,
                ],
                json_encode('OK'),
            ),
        );

        $service = new Service('dummy_service', $this->httpClient);
        $service->dummyMethod();
    }

    public function testWarningLoggerSkipsNoAgent(): void
    {
        $warningMsg = 'some warning with no agent';
        $warning = "299 - \"{$warningMsg}\"";

        /** @var LoggerInterface|MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::never())
            ->method(static::anything());

        Middleware::addWarningLogging($this->handlerStack, $logger);
        $this->mockHandler->append(
            new Response(
                200,
                [
                    'Warning' => $warning,
                ],
                json_encode('OK'),
            ),
        );

        $service = new Service('dummy_service', $this->httpClient);
        $service->dummyMethod();
    }

    public function testWarningLoggerSkipsWrongAgent(): void
    {
        $warningMsg = 'some other system';
        $warning = "299 other/1.2.3 \"{$warningMsg}\"";

        /** @var LoggerInterface|MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::never())
            ->method(static::anything());

        Middleware::addWarningLogging($this->handlerStack, $logger);
        $this->mockHandler->append(
            new Response(
                200,
                [
                    'Warning' => $warning,
                ],
                json_encode('OK'),
            ),
        );

        $service = new Service('dummy_service', $this->httpClient);
        $service->dummyMethod();
    }

    public function testWarningLoggerSkipsWrongCode(): void
    {
        $warningMsg = 'something which looks like Maxemail';
        $warning = "199 MxmApi/v100 \"{$warningMsg}\"";

        /** @var LoggerInterface|MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::never())
            ->method(static::anything());

        Middleware::addWarningLogging($this->handlerStack, $logger);
        $this->mockHandler->append(
            new Response(
                200,
                [
                    'Warning' => $warning,
                ],
                json_encode('OK'),
            ),
        );

        $service = new Service('dummy_service', $this->httpClient);
        $service->dummyMethod();
    }

    public function testWarningLoggerSkipsNoWarning(): void
    {
        /** @var LoggerInterface|MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::never())
            ->method(static::anything());

        Middleware::addWarningLogging($this->handlerStack, $logger);
        $this->mockHandler->append(
            new Response(200, [], json_encode('OK')),
        );

        $service = new Service('dummy_service', $this->httpClient);
        $service->dummyMethod();
    }

    public function testErrorHandlerSkips200(): void
    {
        Middleware::addMaxemailErrorParser($this->handlerStack);
        $this->mockHandler->append(
            new Response(200, [], json_encode('OK')),
        );

        $service = new Service('dummy_service', $this->httpClient);
        $result = $service->dummyMethod();

        static::assertSame('OK', $result);
    }

    public function testErrorHandlerSkips500(): void
    {
        $this->expectException(ServerException::class);

        Middleware::addMaxemailErrorParser($this->handlerStack);
        $this->mockHandler->append(
            new Response(500, [], 'Internal server error'),
        );

        $service = new Service('dummy_service', $this->httpClient);
        $service->dummyMethod();
    }

    public function testErrorHandlerHandles400Standard(): void
    {
        $this->expectException(ClientException::class);

        Middleware::addMaxemailErrorParser($this->handlerStack);
        $this->mockHandler->append(
            new Response(400, [], 'Error'),
        );

        $service = new Service('dummy_service', $this->httpClient);
        $service->dummyMethod();
    }

    public function testErrorHandlerHandles400Maxemail(): void
    {
        $errorMsg = 'Maxemail API error message';

        $this->expectException(MxmClientException::class);
        $this->expectExceptionMessage($errorMsg);

        Middleware::addMaxemailErrorParser($this->handlerStack);
        $mxmError = [
            'success' => 'false',
            'msg' => $errorMsg,
        ];
        $this->mockHandler->append(
            new Response(400, [], json_encode($mxmError)),
        );

        $service = new Service('dummy_service', $this->httpClient);
        $service->dummyMethod();
    }
}
