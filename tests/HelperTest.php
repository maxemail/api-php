<?php

declare(strict_types=1);

namespace Maxemail\Api;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware as GuzzleMiddleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Maxemail API Client
 *
 * @package    Maxemail\Api
 * @copyright  2007-2019 Emailcenter UK Ltd. (https://maxemail.xtremepush.com)
 * @license    LGPL-3.0
 *
 * @runTestsInSeparateProcesses Used so global functions can be mocked after already accessed in other tests
 */
class HelperTest extends TestCase
{
    use PHPMock;

    /**
     * @var Client|MockObject
     */
    private $apiClientMock;

    /**
     * @var MockHandler
     */
    private $mockHandler;

    private $clientHistory = [];

    /**
     * @var Helper
     */
    private $helper;

    protected function setUp(): void
    {
        $this->apiClientMock = $this->createMock(Client::class);

        $this->mockHandler = new MockHandler();

        $stack = HandlerStack::create($this->mockHandler);
        $stack->push(
            GuzzleMiddleware::history($this->clientHistory),
        );

        $httpClient = new GuzzleClient([
            'base_uri' => 'https://example.com/api/json/',
            'handler' => $stack,
        ]);

        $this->helper = new Helper($this->apiClientMock, $httpClient);
    }

    public function testUpload()
    {
        $sampleFile = __DIR__ . '/__files/sample-file.csv';
        $key = 'abc123def456';

        /** @var Service|MockObject $fileUploadService */
        $fileUploadService = $this->createMock(Service::class);

        // The API client will be used to initialise the upload
        $this->apiClientMock->expects($this->once())
            ->method('__get')
            ->with('file_upload')
            ->willReturn($fileUploadService);

        $fileUploadService->expects($this->once())
            ->method('__call')
            ->with('initialise', [])
            ->willReturn((object)[
                'key' => $key,
            ]);

        // Use a Closure to save the request body at the point of handling the request
        // Using the Request instance saved by the history middleware isn't an option,
        // as the file handle will be closed by then
        $requestBody = '';
        $this->mockHandler->append(
            function (Request $request) use (&$requestBody) {
                $requestBody = (string)$request->getBody();
                return new Response(200, [], '');
            },
        );

        $actual = $this->helper->uploadFile($sampleFile);

        $this->assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        // Check request is multipart and body contains expected parameters
        $this->assertTrue($request->hasHeader('Content-Type'));
        $this->assertStringStartsWith('multipart/form-data', $request->getHeader('Content-Type')[0]);
        // @todo phpunit > v8, change to `assertMatchesRegularExpression()`
        $this->assertRegExp("/name=\"method\".*\r\n\r\nhandle\r\n/sU", $requestBody);
        $this->assertRegExp("/name=\"key\".*\r\n\r\n{$key}\r\n/sU", $requestBody);
        $fileContents = file_get_contents($sampleFile);
        $this->assertRegExp("/name=\"file\".*\r\n\r\n{$fileContents}\r\n/sU", $requestBody);

        $this->assertSame($key, $actual);
    }

    public function testUploadUnreadable()
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('File path is not readable');

        $sampleFile = __DIR__ . '/__files/does-not-exist';

        $this->helper->uploadFile($sampleFile);
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

        $this->helper->uploadFile($sampleFile);
    }

    public function testDownloadCsv()
    {
        $sampleFile = __DIR__ . '/__files/sample-file.csv';
        $key = 'abc123def456';

        $this->mockHandler->append(
            new Response(200, [], fopen($sampleFile, 'r')),
        );

        $downloadedFile = $this->helper->downloadFile('file', $key);

        $this->assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/download/file/key/' . $key, $request->getUri()->getPath());
        $this->assertSame(['*'], $request->getHeader('Accept'));

        $this->assertFileEquals($sampleFile, $downloadedFile);
    }

    public function testDownloadZip()
    {
        $responseFile = __DIR__ . '/__files/sample-file.csv.zip';
        $expectedFile = __DIR__ . '/__files/sample-file.csv';
        $key = 'abc123def456';

        $this->mockHandler->append(
            new Response(200, [], fopen($responseFile, 'r')),
        );

        $downloadedFile = $this->helper->downloadFile('file', $key);

        $this->assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/download/file/key/' . $key, $request->getUri()->getPath());
        $this->assertSame(['*'], $request->getHeader('Accept'));

        $this->assertFileEquals($expectedFile, $downloadedFile);
    }

    public function testDownloadZipNoExtract()
    {
        $sampleFile = __DIR__ . '/__files/sample-file.csv.zip';
        $key = 'abc123def456';

        $this->mockHandler->append(
            new Response(200, [], fopen($sampleFile, 'r')),
        );

        $downloadedFile = $this->helper->downloadFile('file', $key, [
            'extract' => false,
        ]);

        $this->assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/download/file/key/' . $key, $request->getUri()->getPath());
        $this->assertSame(['*'], $request->getHeader('Accept'));

        $this->assertFileEquals($sampleFile, $downloadedFile);
    }

    public function testDownloadPdf()
    {
        $sampleFile = __DIR__ . '/__files/sample-file.pdf';
        $key = 'abc123def456';

        $this->mockHandler->append(
            new Response(200, [], fopen($sampleFile, 'r')),
        );

        $downloadedFilename = $this->helper->downloadFile('file', $key);

        $this->assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/download/file/key/' . $key, $request->getUri()->getPath());
        $this->assertSame(['*'], $request->getHeader('Accept'));

        $this->assertFileEquals($sampleFile, $downloadedFilename);
    }

    public function testDownloadListExport()
    {
        $sampleFile = __DIR__ . '/__files/sample-file.csv';
        $key = 123;

        $this->mockHandler->append(
            new Response(200, [], fopen($sampleFile, 'r')),
        );

        $downloadedFile = $this->helper->downloadFile('listexport', $key);

        $this->assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/download/listexport/id/' . $key, $request->getUri()->getPath());
        $this->assertSame(['*'], $request->getHeader('Accept'));

        $this->assertFileEquals($sampleFile, $downloadedFile);
    }

    public function testDownloadDataExport()
    {
        $sampleFile = __DIR__ . '/__files/sample-file.csv';
        $key = 123;

        $this->mockHandler->append(
            new Response(200, [], fopen($sampleFile, 'r')),
        );

        $downloadedFile = $this->helper->downloadFile('dataexport', $key);

        $this->assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('/download/dataexport/id/' . $key, $request->getUri()->getPath());
        $this->assertSame(['*'], $request->getHeader('Accept'));

        $this->assertFileEquals($sampleFile, $downloadedFile);
    }

    public function testDownloadUnknownType()
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid download type specified');

        $this->helper->downloadFile('unknown', 123);
    }

    /**
     * Use exception to stop method early
     */
    public function testDownloadTmpFileLocation()
    {
        $this->expectException(\Exception::class);

        $fopenMock = $this->getFunctionMock(__NAMESPACE__, 'fopen');
        $fopenMock->expects($this->once())
            ->with($this->stringStartsWith(realpath(sys_get_temp_dir())), 'w')
            ->willReturn(false);

        $this->helper->downloadFile('file', 123);
    }

    /**
     * Use exception to stop method early
     */
    public function testDownloadTmpFileLocationCustom()
    {
        $this->expectException(\Exception::class);

        $directory = __DIR__ . '/__files';

        $fopenMock = $this->getFunctionMock(__NAMESPACE__, 'fopen');
        $fopenMock->expects($this->once())
            ->with($this->stringStartsWith($directory), 'w')
            ->willReturn(false);

        $this->helper->downloadFile('file', 123, [
            'dir' => $directory,
        ]);
    }

    public function testDownloadTmpFileUnableToOpen()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('Unable to open local file');

        $fopenMock = $this->getFunctionMock(__NAMESPACE__, 'fopen');
        $fopenMock->expects($this->once())
            ->willReturn(false);

        $this->helper->downloadFile('file', 123);
    }

    public function testDownloadTmpFileDeletedRequestError()
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('400 Bad Request');

        $this->mockHandler->append(
            new Response(400, [], 'Error'),
        );

        $directory = __DIR__ . '/__files';

        $tmpFile = '';
        $tmpResource = null;

        $fopenMock = $this->getFunctionMock(__NAMESPACE__, 'fopen');
        $fopenMock->expects($this->once())
            ->willReturnCallback(function ($filename, $mode) use (&$tmpFile, &$tmpResource) {
                $tmpFile = $filename;
                $tmpResource = \fopen($filename, $mode);
                return $tmpResource;
            });

        $fcloseMock = $this->getFunctionMock(__NAMESPACE__, 'fclose');
        $fcloseMock->expects($this->once())
            ->willReturnCallback(function ($resource) use (&$tmpResource) {
                $this->assertSame($tmpResource, $resource);
                return \fclose($tmpResource);
            });

        $unlinkMock = $this->getFunctionMock(__NAMESPACE__, 'unlink');
        $unlinkMock->expects($this->once())
            ->willReturnCallback(function ($filename) use (&$tmpFile) {
                $this->assertSame($tmpFile, $filename);
                return \unlink($tmpFile);
            });

        $this->helper->downloadFile('file', 'abc123def456', [
            'dir' => $directory,
        ]);
    }

    public function testDownloadTmpFileDeletedWriteError()
    {
        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('Unable to write to local file');

        $sampleFile = __DIR__ . '/__files/sample-file.csv';
        $this->mockHandler->append(
            new Response(200, [], fopen($sampleFile, 'r')),
        );

        $directory = __DIR__ . '/__files';

        $tmpFile = '';
        $tmpResource = null;

        $fopenMock = $this->getFunctionMock(__NAMESPACE__, 'fopen');
        $fopenMock->expects($this->once())
            ->willReturnCallback(function ($filename, $mode) use (&$tmpFile, &$tmpResource) {
                $tmpFile = $filename;
                $tmpResource = \fopen($filename, $mode);
                return $tmpResource;
            });

        $fwriteMock = $this->getFunctionMock(__NAMESPACE__, 'fwrite');
        $fwriteMock->expects($this->once())
            ->willReturn(false);

        $fcloseMock = $this->getFunctionMock(__NAMESPACE__, 'fclose');
        $fcloseMock->expects($this->once())
            ->willReturnCallback(function ($resource) use (&$tmpResource) {
                $this->assertSame($tmpResource, $resource);
                return \fclose($tmpResource);
            });

        $unlinkMock = $this->getFunctionMock(__NAMESPACE__, 'unlink');
        $unlinkMock->expects($this->once())
            ->willReturnCallback(function ($filename) use (&$tmpFile) {
                $this->assertSame($tmpFile, $filename);
                return \unlink($tmpFile);
            });

        $this->helper->downloadFile('file', 'abc123def456', [
            'dir' => $directory,
        ]);
    }
}
