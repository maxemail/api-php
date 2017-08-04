<?php
declare(strict_types=1);

namespace Emailcenter\MaxemailApi;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware as GuzzleMiddleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
    use PHPMock;

    /**
     * @var Client|\PHPUnit_Framework_MockObject_MockObject
     */
    private $apiClientMock;

    /**
     * @var GuzzleClient|\PHPUnit_Framework_MockObject_MockObject
     */
    private $httpClientMock;

    /**
     * @var MockHandler
     */
    private $mockHandler;

    private $clientHistory = [];

    protected function setUp()
    {
        $this->apiClientMock = $this->createMock(Client::class);
        $this->httpClientMock = $this->createMock(GuzzleClient::class);

        $this->mockHandler = new MockHandler();

        // This allows fopen() to be overloaded after it's first used normally in other tests
        $this->defineFunctionMock(__NAMESPACE__, 'fopen');
    }

    public function testUpload()
    {
        $sampleFile = __DIR__ . '/__files/sample-file.csv';
        $key = 'abc123def456';

        /** @var Service|\PHPUnit_Framework_MockObject_MockObject $fileUploadService */
        $fileUploadService = $this->createMock(Service::class);

        // The API client will be used to initialise the upload
        $this->apiClientMock->expects($this->once())
            ->method('__get')
            ->with('file_upload')
            ->willReturn($fileUploadService);

        $fileUploadService->expects($this->once())
            ->method('__call')
            ->with('initialise', [])
            ->willReturn((object)['key' => $key]);

        // HTTP Client is used to perform multipart upload
        // Can't use MockHandler and History middleware,
        // as once the request has been handled the file stream cannot be read again
        $checkMultipart = function($options) use ($key) {
            $this->assertCount(1, $options);
            $this->assertArrayHasKey('multipart', $options);

            $methodParam = $options['multipart'][0];
            $this->assertArrayHasKey('name', $methodParam);
            $this->assertEquals('method', $methodParam['name']);
            $this->assertArrayHasKey('contents', $methodParam);
            $this->assertEquals('handle', $methodParam['contents']);

            $keyParam = $options['multipart'][1];
            $this->assertArrayHasKey('name', $keyParam);
            $this->assertEquals('key', $keyParam['name']);
            $this->assertArrayHasKey('contents', $keyParam);
            $this->assertEquals($key, $keyParam['contents']);

            $fileParam = $options['multipart'][2];
            $this->assertArrayHasKey('name', $fileParam);
            $this->assertEquals('file', $fileParam['name']);
            $this->assertArrayHasKey('filename', $fileParam);
            $this->assertEquals('sample-file.csv', $fileParam['filename']);
            $this->assertArrayHasKey('contents', $fileParam);
            $this->assertInternalType('resource', $fileParam['contents']);

            return true;
        };
        $uploadResponse = new Response(200, [], '');
        $this->httpClientMock->expects($this->once())
            ->method('request')
            ->with('POST', 'file_upload', $this->callback($checkMultipart))
            ->willReturn($uploadResponse);

        $actual = $this->getHelperWithMockClient()->uploadFile($sampleFile);

        $this->assertEquals($key, $actual);
    }

    public function testUploadUnreadable()
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('File path is not readable');

        $sampleFile = __DIR__ . '/__files/does-not-exist';

        $this->getHelperWithMockClient()->uploadFile($sampleFile);
    }

    public function testUploadUnableToOpen()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('Unable to open local file');

        $sampleFile = __DIR__ . '/__files/sample-file.csv';

        $fopenMock = $this->getFunctionMock(__NAMESPACE__, 'fopen');
        $fopenMock->expects($this->once())
            ->with($sampleFile, 'r')
            ->willReturn(false);

        $this->getHelperWithMockClient()->uploadFile($sampleFile);
    }

    public function testDownloadCsv()
    {
        $sampleFile = __DIR__ . '/__files/sample-file.csv';
        $key = 'abc123def456';

        $this->mockHandler->append(
            new Response(200, [], fopen($sampleFile, 'r'))
        );

        $downloadedFile = $this->getHelperWithMockHandler()->downloadFile('file', $key);

        $this->assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('/download/file/key/' . $key, $request->getUri()->getPath());
        $this->assertEquals(['*'], $request->getHeader('Accept'));

        $this->assertFileEquals($sampleFile, $downloadedFile);
    }

    public function testDownloadZip()
    {
        $responseFile = __DIR__ . '/__files/sample-file.csv.zip';
        $expectedFile = __DIR__ . '/__files/sample-file.csv';
        $key = 'abc123def456';

        $this->mockHandler->append(
            new Response(200, [], fopen($responseFile, 'r'))
        );

        $downloadedFile = $this->getHelperWithMockHandler()->downloadFile('file', $key);

        $this->assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('/download/file/key/' . $key, $request->getUri()->getPath());
        $this->assertEquals(['*'], $request->getHeader('Accept'));

        $this->assertFileEquals($expectedFile, $downloadedFile);
    }

    public function testDownloadZipNoExtract()
    {
        $sampleFile = __DIR__ . '/__files/sample-file.csv.zip';
        $key = 'abc123def456';

        $this->mockHandler->append(
            new Response(200, [], fopen($sampleFile, 'r'))
        );

        $downloadedFile = $this->getHelperWithMockHandler()->downloadFile('file', $key, ['extract' => false]);

        $this->assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('/download/file/key/' . $key, $request->getUri()->getPath());
        $this->assertEquals(['*'], $request->getHeader('Accept'));

        $this->assertFileEquals($sampleFile, $downloadedFile);
    }

    public function testDownloadPdf()
    {
        $sampleFile = __DIR__ . '/__files/sample-file.pdf';
        $key = 'abc123def456';

        $this->mockHandler->append(
            new Response(200, [], fopen($sampleFile, 'r'))
        );

        $downloadedFilename = $this->getHelperWithMockHandler()->downloadFile('file', $key);

        $this->assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('/download/file/key/' . $key, $request->getUri()->getPath());
        $this->assertEquals(['*'], $request->getHeader('Accept'));

        $this->assertFileEquals($sampleFile, $downloadedFilename);
    }

    public function testDownloadListExport()
    {
        $sampleFile = __DIR__ . '/__files/sample-file.csv';
        $key = 123;

        $this->mockHandler->append(
            new Response(200, [], fopen($sampleFile, 'r'))
        );

        $downloadedFile = $this->getHelperWithMockHandler()->downloadFile('listexport', $key);

        $this->assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('/download/listexport/id/' . $key, $request->getUri()->getPath());
        $this->assertEquals(['*'], $request->getHeader('Accept'));

        $this->assertFileEquals($sampleFile, $downloadedFile);
    }

    public function testDownloadDataExport()
    {
        $sampleFile = __DIR__ . '/__files/sample-file.csv';
        $key = 123;

        $this->mockHandler->append(
            new Response(200, [], fopen($sampleFile, 'r'))
        );

        $downloadedFile = $this->getHelperWithMockHandler()->downloadFile('dataexport', $key);

        $this->assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        $this->assertEquals('GET', $request->getMethod());
        $this->assertEquals('/download/dataexport/id/' . $key, $request->getUri()->getPath());
        $this->assertEquals(['*'], $request->getHeader('Accept'));

        $this->assertFileEquals($sampleFile, $downloadedFile);
    }

    public function testDownloadUnknownType()
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid download type specified');

        $this->getHelperWithMockHandler()->downloadFile('unknown', 123);
    }

    /**
     * Get a Helper which uses a mock HTTP Client
     *
     * @return Helper
     */
    private function getHelperWithMockClient(): Helper
    {
        return new Helper($this->apiClientMock, $this->httpClientMock);
    }

    /**
     * Get a Helper which uses an HTTP Client using the MockHandler and storing history
     *
     * @return Helper
     */
    private function getHelperWithMockHandler(): Helper
    {
        $stack = HandlerStack::create($this->mockHandler);
        $stack->push(
            GuzzleMiddleware::history($this->clientHistory)
        );

        $httpClient = new GuzzleClient([
            'base_uri' => 'https://example.com/api/json/',
            'handler' => $stack
        ]);

        return new Helper($this->apiClientMock, $httpClient);
    }
}
