<?php
declare(strict_types=1);

namespace Maxemail\Api;

use PHPUnit\Framework\TestCase;

/**
 * Maxemail API Client
 *
 * @package    Maxemail\Api
 * @copyright  2007-2019 Emailcenter UK Ltd. (https://maxemail.xtremepush.com)
 * @license    LGPL-3.0
 *
 * @group functional
 */
class FunctionalTest extends TestCase
{
    /**
     * @var Client
     */
    private $client;

    protected function setUp(): void
    {
        if (!getenv('FUNC_ENABLED')) {
            $this->markTestSkipped('Functional tests are disabled');
        }

        $config = [
            'uri' => getenv('FUNC_API_URI'),
            'username' => getenv('FUNC_API_USERNAME'),
            'password' => getenv('FUNC_API_PASSWORD')
        ];
        $this->client = new Client($config);
    }

    /**
     * The most basic of tests
     */
    public function testUserAuth()
    {
        $user = $this->client->user->isLoggedIn();
        $this->assertTrue($user);
    }

    /**
     * Test non-scalar result using Email tree which will always exist
     */
    public function testFetchTree()
    {
        $tree = $this->client->tree->fetchRoot('email', []);
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
        $this->expectException(Exception\ClientException::class);
        $this->expectExceptionMessage('Invalid Node Class');

        $this->client->tree->fetchRoot('notATree', []);
    }

    public function testDeprecatedMethod()
    {
        // @todo phpunit > v7, change to `expectDeprecation()` etc.
        $this->expectException(\PHPUnit\Framework\Error\Deprecated::class);
        $this->expectExceptionMessage('searchLists Deprecated');

        $this->client->recipient->searchLists('test@example.com');
    }

    /**
     * The file uploaded should be identical to the file then downloaded
     */
    public function testHelperUploadDownload()
    {
        $sampleFile = __DIR__ . '/__files/sample-file.csv';
        $key = $this->client->getHelper()->uploadFile($sampleFile);

        $downloadFile = $this->client->getHelper()->downloadFile('file', $key);

        $this->assertFileEquals($sampleFile, $downloadFile);
        unlink($downloadFile);
    }
}
