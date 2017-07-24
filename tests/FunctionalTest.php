<?php
declare(strict_types=1);

namespace Mxm;

use PHPUnit\Framework\TestCase;
use Mxm\Api\Exception\ClientException;

class FunctionalTest extends TestCase
{
    /**
     * @var \Mxm\Api
     */
    private $api;

    protected function setUp()
    {
        if (!$GLOBALS['FUNC_ENABLED']) {
            $this->markTestSkipped('Functional tests are disabled');
        }

        $config = [
            'host' => $GLOBALS['FUNC_API_HOST'],
            'user' => $GLOBALS['FUNC_API_USER'],
            'pass' => $GLOBALS['FUNC_API_PASS'],
            'useSsl' => $GLOBALS['FUNC_API_SSL']
        ];
        $this->api = new \Mxm\Api($config);
    }

    /**
     * The most basic of tests
     */
    public function testUserAuth()
    {
        $user = $this->api->user->isLoggedIn();
        $this->assertTrue($user);
    }

    /**
     * Test non-scalar result using Email tree which will always exist
     */
    public function testFetchTree()
    {
        $tree = $this->api->tree->fetchRoot('email', []);
        $root = $tree[0];

        $this->assertEquals('email', $root->text);
        $this->assertTrue($root->rootNode);
    }

    /**
     * This test isn't about the message per-se,
     * but it checks that we're getting the properly decoded Maxemail error
     */
    public function testFetchTreeError()
    {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('Invalid Node Class');

        $this->api->tree->fetchRoot('notATree', []);
    }

    /**
     * The file uploaded should be identical to the file then downloaded
     */
    public function testHelperUploadDownload()
    {
        $sampleFile = __DIR__ . '/__files/sample-file.csv';
        $key = $this->api->getHelper()->uploadFile($sampleFile);

        $downloadFile = $this->api->getHelper()->downloadFile('file', $key);

        $this->assertFileEquals($sampleFile, $downloadFile);
        unlink($downloadFile);
    }
}
