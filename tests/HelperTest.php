<?php
declare(strict_types=1);

namespace Emailcenter\MaxemailApi;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response;
use phpmock\phpunit\PHPMock;
use PHPUnit\Framework\TestCase;

class HelperTest extends TestCase
{
    use PHPMock;

    /**
     * @var Client|\PHPUnit_Framework_MockObject_MockObject
     */
    private $client;

    /**
     * @var GuzzleClient|\PHPUnit_Framework_MockObject_MockObject
     */
    private $httpClient;

    /**
     * @var Helper
     */
    private $helper;

    protected function setUp()
    {
        $this->client = $this->createMock(Client::class);
        $this->httpClient = $this->createMock(GuzzleClient::class);
        $this->helper = new Helper($this->client, $this->httpClient);

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
        $this->client->expects($this->once())
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
        $this->httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'file_upload', $this->callback($checkMultipart))
            ->willReturn($uploadResponse);

        $actual = $this->helper->uploadFile($sampleFile);

        $this->assertEquals($key, $actual);
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
}
