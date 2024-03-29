<?php

declare(strict_types=1);

namespace Maxemail\Api;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware as GuzzleMiddleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * Maxemail API Client
 *
 * @package    Maxemail\Api
 * @copyright  2007-2019 Emailcenter UK Ltd. (https://maxemail.xtremepush.com)
 * @license    LGPL-3.0
 */
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

    protected function setUp(): void
    {
        $this->mockHandler = new MockHandler();

        $stack = HandlerStack::create($this->mockHandler);
        Middleware::addMaxemailErrorParser($stack);
        $stack->push(
            GuzzleMiddleware::history($this->clientHistory)
        );

        $this->httpClient = new GuzzleClient([
            'base_uri' => 'https://example.com/api/json/',
            'handler' => $stack,
        ]);
    }

    public function testMagicCallSendsRequest()
    {
        $this->mockHandler->append(
            new Response(200, [], json_encode('OK'))
        );

        $service = new Service('dummy_service', $this->httpClient);
        $service->dummyMethod(
            // check variables are properly passed
            'var1',
            // another string to check values are in correct order
            'var2',
            // int to check typing
            123,
            // array to check encoding
            [
                'foo' => [
                    'bar' => 'bob',
                ],
            ]
        );

        $expectedParams = [
            'method' => 'dummyMethod',
            'arg0' => 'var1',
            'arg1' => 'var2',
            'arg2' => 123,
            'arg3' => json_encode([
                'foo' => [
                    'bar' => 'bob',
                ],
            ]),
        ];

        $this->assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/api/json/dummy_service', $request->getUri()->getPath());
        $this->assertSame(http_build_query($expectedParams), (string)$request->getBody());
    }
}
