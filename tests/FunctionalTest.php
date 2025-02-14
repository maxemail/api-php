<?php

declare(strict_types=1);

namespace Maxemail\Api;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Maxemail API Client
 *
 * @package    Maxemail\Api
 * @copyright  2007-2019 Emailcenter UK Ltd. (https://maxemail.xtremepush.com)
 * @license    LGPL-3.0
 */
#[Group('functional')]
class FunctionalTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        if (!getenv('FUNC_ENABLED')) {
            static::markTestSkipped('Functional tests are disabled');
        }

        $config = [
            'uri' => getenv('FUNC_API_URI'),
            'username' => getenv('FUNC_API_USERNAME'),
            'password' => getenv('FUNC_API_PASSWORD'),
        ];
        $this->client = new Client($config);
    }

    /**
     * The most basic of tests
     */
    public function testUserAuth(): void
    {
        $user = $this->client->user->isLoggedIn();
        static::assertTrue($user);
    }

    /**
     * Test non-scalar result using Email tree which will always exist
     */
    public function testFetchTree(): void
    {
        $tree = $this->client->tree->fetchRoot('email', []);
        $root = $tree[0];

        static::assertSame('email', $root->text);
        static::assertTrue($root->rootNode);
    }

    /**
     * This test isn't about the message per-se,
     * but it checks that we're getting the properly decoded Maxemail error
     */
    public function testFetchTreeError(): void
    {
        $this->expectException(Exception\ClientException::class);
        $this->expectExceptionMessage('Invalid Node Class');

        $this->client->tree->fetchRoot('notATree', []);
    }

    public function testDeprecatedMethod(): void
    {
        // Capture deprecation error
        $originalErrorLevel = error_reporting();
        error_reporting(E_ALL);
        set_error_handler(static function (int $errno, string $errstr): never {
            throw new \Exception($errstr, $errno);
        }, E_USER_DEPRECATED);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('searchLists Deprecated');

        try {
            $this->client->recipient->searchLists('test@example.com');
        } finally {
            error_reporting($originalErrorLevel);
            restore_error_handler();
        }
    }

    /**
     * The file uploaded should be identical to the file then downloaded
     */
    public function testHelperUploadDownload(): void
    {
        $sampleFile = __DIR__ . '/__files/sample-file.csv';
        $key = $this->client->getHelper()->uploadFile($sampleFile);

        $downloadFile = $this->client->getHelper()->downloadFile('file', $key);

        static::assertFileEquals($sampleFile, $downloadFile);
        unlink($downloadFile);
    }
}
