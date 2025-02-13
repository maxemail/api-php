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
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Maxemail API Client
 *
 * @package    Maxemail\Api
 * @copyright  2007-2019 Emailcenter UK Ltd. (https://maxemail.xtremepush.com)
 * @license    LGPL-3.0
 *
 * RunTestsInSeparateProcesses: Allow global functions to be mocked after already accessed in other tests
 */
#[RunTestsInSeparateProcesses]
class HelperTest extends TestCase
{
    use PHPMock;

    private Client&MockObject $apiClientMock;

    private MockHandler $mockHandler;

    private array $clientHistory = [];

    private Helper $helper;

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

    public function testUpload(): void
    {
        $sampleFile = __DIR__ . '/__files/sample-file.csv';
        $key = 'abc123def456';

        /** @var Service|MockObject $fileUploadService */
        $fileUploadService = $this->createMock(Service::class);

        // The API client will be used to initialise the upload
        $this->apiClientMock->expects(static::once())
            ->method('__get')
            ->with('file_upload')
            ->willReturn($fileUploadService);

        $fileUploadService->expects(static::once())
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
            function (Request $request) use (&$requestBody): Response {
                $requestBody = (string)$request->getBody();
                return new Response(200, [], '');
            },
        );

        $actual = $this->helper->uploadFile($sampleFile);

        static::assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        // Check request is multipart and body contains expected parameters
        static::assertTrue($request->hasHeader('Content-Type'));
        static::assertStringStartsWith('multipart/form-data', $request->getHeader('Content-Type')[0]);
        static::assertMatchesRegularExpression("/name=\"method\".*\r\n\r\nhandle\r\n/sU", $requestBody);
        static::assertMatchesRegularExpression("/name=\"key\".*\r\n\r\n{$key}\r\n/sU", $requestBody);
        $fileContents = file_get_contents($sampleFile);
        static::assertMatchesRegularExpression("/name=\"file\".*\r\n\r\n{$fileContents}\r\n/sU", $requestBody);

        static::assertSame($key, $actual);
    }

    public function testUploadUnreadable(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('File path is not readable');

        $sampleFile = __DIR__ . '/__files/does-not-exist';

        $this->helper->uploadFile($sampleFile);
    }

    public function testUploadUnableToOpen(): void
    {
        $reasonMessage = sha1(uniqid('error'));

        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('Unable to open local file: ' . $reasonMessage);

        $sampleFile = __DIR__ . '/__files/sample-file.csv';

        $fopenMock = $this->getFunctionMock(__NAMESPACE__, 'fopen');
        $fopenMock->expects(static::once())
            ->with($sampleFile, 'r')
            ->willReturn(false);

        $errorMock = $this->getFunctionMock(__NAMESPACE__, 'error_get_last');
        $errorMock->expects(static::once())
            ->willReturn([
                'message' => $reasonMessage,
            ]);

        $this->helper->uploadFile($sampleFile);
    }

    public function testDownloadCsv(): void
    {
        $sampleFile = __DIR__ . '/__files/sample-file.csv';
        $key = 'abc123def456';

        $this->mockHandler->append(
            new Response(200, [], fopen($sampleFile, 'r')),
        );

        $downloadedFile = $this->helper->downloadFile('file', $key);

        static::assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        static::assertSame('GET', $request->getMethod());
        static::assertSame('/download/file/key/' . $key, $request->getUri()->getPath());
        static::assertSame(['*'], $request->getHeader('Accept'));

        static::assertFileEquals($sampleFile, $downloadedFile);
    }

    public function testDownloadZip(): void
    {
        $responseFile = __DIR__ . '/__files/sample-file.csv.zip';
        $expectedFile = __DIR__ . '/__files/sample-file.csv';
        $key = 'abc123def456';

        $this->mockHandler->append(
            new Response(200, [], fopen($responseFile, 'r')),
        );

        $downloadedFile = $this->helper->downloadFile('file', $key);

        static::assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        static::assertSame('GET', $request->getMethod());
        static::assertSame('/download/file/key/' . $key, $request->getUri()->getPath());
        static::assertSame(['*'], $request->getHeader('Accept'));

        static::assertFileEquals($expectedFile, $downloadedFile);
    }

    public function testDownloadZipNoExtract(): void
    {
        $sampleFile = __DIR__ . '/__files/sample-file.csv.zip';
        $key = 'abc123def456';

        $this->mockHandler->append(
            new Response(200, [], fopen($sampleFile, 'r')),
        );

        $downloadedFile = $this->helper->downloadFile('file', $key, [
            'extract' => false,
        ]);

        static::assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        static::assertSame('GET', $request->getMethod());
        static::assertSame('/download/file/key/' . $key, $request->getUri()->getPath());
        static::assertSame(['*'], $request->getHeader('Accept'));

        static::assertFileEquals($sampleFile, $downloadedFile);
    }

    public function testDownloadPdf(): void
    {
        $sampleFile = __DIR__ . '/__files/sample-file.pdf';
        $key = 'abc123def456';

        $this->mockHandler->append(
            new Response(200, [], fopen($sampleFile, 'r')),
        );

        $downloadedFilename = $this->helper->downloadFile('file', $key);

        static::assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        static::assertSame('GET', $request->getMethod());
        static::assertSame('/download/file/key/' . $key, $request->getUri()->getPath());
        static::assertSame(['*'], $request->getHeader('Accept'));

        static::assertFileEquals($sampleFile, $downloadedFilename);
    }

    public function testDownloadListExport(): void
    {
        $sampleFile = __DIR__ . '/__files/sample-file.csv';
        $key = 123;

        $this->mockHandler->append(
            new Response(200, [], fopen($sampleFile, 'r')),
        );

        $downloadedFile = $this->helper->downloadFile('listexport', $key);

        static::assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        static::assertSame('GET', $request->getMethod());
        static::assertSame('/download/listexport/id/' . $key, $request->getUri()->getPath());
        static::assertSame(['*'], $request->getHeader('Accept'));

        static::assertFileEquals($sampleFile, $downloadedFile);
    }

    public function testDownloadDataExport(): void
    {
        $sampleFile = __DIR__ . '/__files/sample-file.csv';
        $key = 123;

        $this->mockHandler->append(
            new Response(200, [], fopen($sampleFile, 'r')),
        );

        $downloadedFile = $this->helper->downloadFile('dataexport', $key);

        static::assertCount(1, $this->clientHistory);

        /** @var Request $request */
        $request = $this->clientHistory[0]['request'];

        static::assertSame('GET', $request->getMethod());
        static::assertSame('/download/dataexport/id/' . $key, $request->getUri()->getPath());
        static::assertSame(['*'], $request->getHeader('Accept'));

        static::assertFileEquals($sampleFile, $downloadedFile);
    }

    public function testDownloadUnknownType(): void
    {
        $this->expectException(Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid download type specified');

        $this->helper->downloadFile('unknown', 123);
    }

    public function testDownloadTmpFileLocation(): void
    {
        $fopenMock = $this->getFunctionMock(__NAMESPACE__, 'fopen');
        $fopenMock->expects(static::once())
            ->with(static::stringStartsWith(realpath(sys_get_temp_dir())), 'w')
            // Return false to exit early
            ->willReturn(false);

        $errorMock = $this->getFunctionMock(__NAMESPACE__, 'error_get_last');
        $errorMock->expects(static::once())
            ->willReturn([
                'message' => 'exit early',
            ]);

        try {
            $this->helper->downloadFile('file', 123);
        } catch (Exception\RuntimeException) {
            // Ignore exception from exiting early
        }
    }

    public function testDownloadTmpFileLocationCustom(): void
    {
        $directory = __DIR__ . '/__files';

        $fopenMock = $this->getFunctionMock(__NAMESPACE__, 'fopen');
        $fopenMock->expects(static::once())
            ->with(static::stringStartsWith($directory), 'w')
            // Return false to exit early
            ->willReturn(false);

        $errorMock = $this->getFunctionMock(__NAMESPACE__, 'error_get_last');
        $errorMock->expects(static::once())
            ->willReturn([
                'message' => 'exit early',
            ]);

        try {
            $this->helper->downloadFile('file', 123, [
                'dir' => $directory,
            ]);
        } catch (Exception\RuntimeException) {
            // Ignore exception from exiting early
        }
    }

    public function testDownloadTmpFileUnableToOpen(): void
    {
        $reasonMessage = sha1(uniqid('error'));

        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('Unable to open local file: ' . $reasonMessage);

        $fopenMock = $this->getFunctionMock(__NAMESPACE__, 'fopen');
        $fopenMock->expects(static::once())
            ->willReturn(false);

        $errorMock = $this->getFunctionMock(__NAMESPACE__, 'error_get_last');
        $errorMock->expects(static::once())
            ->willReturn([
                'message' => $reasonMessage,
            ]);

        $this->helper->downloadFile('file', 123);
    }

    public function testDownloadTmpFileDeletedRequestError(): void
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
        $fopenMock->expects(static::once())
            ->willReturnCallback(function ($filename, $mode) use (&$tmpFile, &$tmpResource) {
                $tmpFile = $filename;
                $tmpResource = \fopen($filename, $mode);
                return $tmpResource;
            });

        $fcloseMock = $this->getFunctionMock(__NAMESPACE__, 'fclose');
        $fcloseMock->expects(static::once())
            ->willReturnCallback(function ($resource) use (&$tmpResource): bool {
                static::assertSame($tmpResource, $resource);
                return \fclose($tmpResource);
            });

        $unlinkMock = $this->getFunctionMock(__NAMESPACE__, 'unlink');
        $unlinkMock->expects(static::once())
            ->willReturnCallback(function ($filename) use (&$tmpFile): bool {
                static::assertSame($tmpFile, $filename);
                return \unlink($tmpFile);
            });

        $this->helper->downloadFile('file', 'abc123def456', [
            'dir' => $directory,
        ]);
    }

    public function testDownloadTmpFileDeletedWriteError(): void
    {
        $reasonMessage = sha1(uniqid('error'));

        $this->expectException(Exception\RuntimeException::class);
        $this->expectExceptionMessage('Unable to write to local file: ' . $reasonMessage);

        $sampleFile = __DIR__ . '/__files/sample-file.csv';
        $this->mockHandler->append(
            new Response(200, [], fopen($sampleFile, 'r')),
        );

        $directory = __DIR__ . '/__files';

        $tmpFile = '';
        $tmpResource = null;

        $fopenMock = $this->getFunctionMock(__NAMESPACE__, 'fopen');
        $fopenMock->expects(static::once())
            ->willReturnCallback(function ($filename, $mode) use (&$tmpFile, &$tmpResource) {
                $tmpFile = $filename;
                $tmpResource = \fopen($filename, $mode);
                return $tmpResource;
            });

        $fwriteMock = $this->getFunctionMock(__NAMESPACE__, 'fwrite');
        $fwriteMock->expects(static::once())
            ->willReturn(false);

        $errorMock = $this->getFunctionMock(__NAMESPACE__, 'error_get_last');
        $errorMock->expects(static::once())
            ->willReturn([
                'message' => $reasonMessage,
            ]);

        $fcloseMock = $this->getFunctionMock(__NAMESPACE__, 'fclose');
        $fcloseMock->expects(static::once())
            ->willReturnCallback(function ($resource) use (&$tmpResource): bool {
                static::assertSame($tmpResource, $resource);
                return \fclose($tmpResource);
            });

        $unlinkMock = $this->getFunctionMock(__NAMESPACE__, 'unlink');
        $unlinkMock->expects(static::once())
            ->willReturnCallback(function ($filename) use (&$tmpFile): bool {
                static::assertSame($tmpFile, $filename);
                return \unlink($tmpFile);
            });

        $this->helper->downloadFile('file', 'abc123def456', [
            'dir' => $directory,
        ]);
    }
}
