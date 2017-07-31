<?php
declare(strict_types=1);

namespace Mxm\Api;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware as GuzzleMiddleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class ServiceTest extends TestCase
{
    /**
     * @var GuzzleClient
     */
    private $httpClient;

    /**
     * @var MockHandler
     */
    private $mockHandler;

    private $clientHistory = [];

    protected function setUp()
    {
        $this->mockHandler = new MockHandler();

        $stack = HandlerStack::create($this->mockHandler);
        Middleware::addMaxemailErrorParser($stack);
        $stack->push(
            GuzzleMiddleware::history($this->clientHistory)
        );

        $this->httpClient = new GuzzleClient([
            'base_uri' => 'https://example.com/api/json/',
            'handler' => $stack
        ]);
    }

    public function testMagicCallSendsRequest()
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode('OK'))
        );

        $service = new Service('dummy_service', $this->httpClient);
        $service->dummyMethod(
            'var1', // check variables properly passed
            'var2', // another string to check values are in correct order
            123, // int to check typing
            ['foo' => ['bar' => 'bob']] // array to check encoding
        );

        $expectedParams = [
            'method' => 'dummyMethod',
            'arg0' => 'var1',
            'arg1' => 'var2',
            'arg2' => 123,
            'arg3' => json_encode(['foo' => ['bar' => 'bob']])
        ];

        $this->assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/api/json/dummy_service', $request->getUri()->getPath());
        $this->assertEquals(http_build_query($expectedParams), (string)$request->getBody());
    }
}
