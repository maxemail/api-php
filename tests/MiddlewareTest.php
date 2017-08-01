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

    protected function setUp()
    {
        $this->mockHandler = new MockHandler();

        $stack = HandlerStack::create($this->mockHandler);
        Middleware::addMaxemailErrorParser($stack);

        $this->httpClient = new GuzzleClient([
            'handler' => $stack
        ]);
    }

    public function testErrorHandlerSkips200()
    {
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

        $this->mockHandler->append(
            new Response(500, [], 'Internal server error')
        );

        $service = new Service('dummy_service', $this->httpClient);
        $service->dummyMethod();
    }

    public function testErrorHandlerHandles400Standard()
    {
        $this->expectException(ClientException::class);

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
