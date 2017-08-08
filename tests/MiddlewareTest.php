<?php
declare(strict_types=1);

namespace Emailcenter\MaxemailApi;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Emailcenter\MaxemailApi\Exception\ClientException as MxmClientException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MiddlwareTest extends TestCase
{
    /**
     * @var GuzzleClient
     */
    private $httpClient;

    /**
     * @var MockHandler
     */
    private $mockHandler;

    /**
     * @var HandlerStack
     */
    private $handlerStack;

    protected function setUp()
    {
        $this->mockHandler = new MockHandler();

        $this->handlerStack = HandlerStack::create($this->mockHandler);

        $this->httpClient = new GuzzleClient([
            'handler' => $this->handlerStack
        ]);
    }

    public function testWarningLoggerCreatesLog()
    {
        $warningMsg = 'dummyMethod Deprecated: some example description';
        $warning = "299 MxmApi/v100 \"{$warningMsg}\"";

        $this->expectException(\PHPUnit\Framework\Error\Deprecated::class);
        $this->expectExceptionMessage($warningMsg);

        /** @var LoggerInterface|\PHPUnit_Framework_MockObject_MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with($warningMsg);

        Middleware::addWarningLogging($this->handlerStack, $logger);
        $this->mockHandler->append(
            new Response(200, ['Warning' => $warning], json_encode('OK'))
        );

        $service = new Service('dummy_service', $this->httpClient);
        $service->dummyMethod();
    }

    public function testWarningLoggerSkipsNoAgent()
    {
        $warningMsg = 'some warning with no agent';
        $warning = "299 - \"{$warningMsg}\"";

        /** @var LoggerInterface|\PHPUnit_Framework_MockObject_MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())
            ->method($this->anything());

        Middleware::addWarningLogging($this->handlerStack, $logger);
        $this->mockHandler->append(
            new Response(200, ['Warning' => $warning], json_encode('OK'))
        );

        $service = new Service('dummy_service', $this->httpClient);
        $service->dummyMethod();
    }

    public function testWarningLoggerSkipsWrongAgent()
    {
        $warningMsg = 'some other system';
        $warning = "299 other/1.2.3 \"{$warningMsg}\"";

        /** @var LoggerInterface|\PHPUnit_Framework_MockObject_MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())
            ->method($this->anything());

        Middleware::addWarningLogging($this->handlerStack, $logger);
        $this->mockHandler->append(
            new Response(200, ['Warning' => $warning], json_encode('OK'))
        );

        $service = new Service('dummy_service', $this->httpClient);
        $service->dummyMethod();
    }

    public function testWarningLoggerSkipsWrongCode()
    {
        $warningMsg = 'something which looks like Maxemail';
        $warning = "199 MxmApi/v100 \"{$warningMsg}\"";

        /** @var LoggerInterface|\PHPUnit_Framework_MockObject_MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())
            ->method($this->anything());

        Middleware::addWarningLogging($this->handlerStack, $logger);
        $this->mockHandler->append(
            new Response(200, ['Warning' => $warning], json_encode('OK'))
        );

        $service = new Service('dummy_service', $this->httpClient);
        $service->dummyMethod();
    }

    public function testWarningLoggerSkipsNoWarning()
    {
        /** @var LoggerInterface|\PHPUnit_Framework_MockObject_MockObject $logger */
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())
            ->method($this->anything());

        Middleware::addWarningLogging($this->handlerStack, $logger);
        $this->mockHandler->append(
            new Response(200, [], json_encode('OK'))
        );

        $service = new Service('dummy_service', $this->httpClient);
        $service->dummyMethod();
    }

    public function testErrorHandlerSkips200()
    {
        Middleware::addMaxemailErrorParser($this->handlerStack);
        $this->mockHandler->append(
            new Response(200, [], json_encode('OK'))
        );

        $service = new Service('dummy_service', $this->httpClient);
        $result = $service->dummyMethod();

        $this->assertEquals('OK', $result);
    }

    public function testErrorHandlerSkips500()
    {
        $this->expectException(ServerException::class);

        Middleware::addMaxemailErrorParser($this->handlerStack);
        $this->mockHandler->append(
            new Response(500, [], 'Internal server error')
        );

        $service = new Service('dummy_service', $this->httpClient);
        $service->dummyMethod();
    }

    public function testErrorHandlerHandles400Standard()
    {
        $this->expectException(ClientException::class);

        Middleware::addMaxemailErrorParser($this->handlerStack);
        $this->mockHandler->append(
            new Response(400, [], 'Error')
        );

        $service = new Service('dummy_service', $this->httpClient);
        $service->dummyMethod();
    }

    public function testErrorHandlerHandles400Maxemail()
    {
        $errorMsg = 'Maxemail API error message';

        $this->expectException(MxmClientException::class);
        $this->expectExceptionMessage($errorMsg);

        Middleware::addMaxemailErrorParser($this->handlerStack);
        $mxmError = [
            'success' => 'false',
            'msg' => $errorMsg
        ];
        $this->mockHandler->append(
            new Response(400, [], json_encode($mxmError))
        );

        $service = new Service('dummy_service', $this->httpClient);
        $service->dummyMethod();
    }
}
