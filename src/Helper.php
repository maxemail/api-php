<?php
declare(strict_types=1);

namespace Maxemail\Api;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LogLevel;

/**
 * Maxemail API Client
 *
 * @package    Maxemail\Api
 * @copyright  2007-2017 Emailcenter UK Ltd. (https://maxemail.xtremepush.com)
 * @license    LGPL-3.0
 */
class Helper
{
    /**
     * @var Client
     */
    private $api;

    /**
     * @var GuzzleClient
     */
    private $httpClient;

    /**
     * @var string
     */
    private $logLevel = LogLevel::DEBUG;

    /**
     * @param Client $api
     * @param GuzzleClient $httpClient
     */
    public function __construct(Client $api, GuzzleClient $httpClient)
    {
        $this->api = $api;
        $this->httpClient = $httpClient;
    }

    /**
     * Set the level used for helper logging
     *
     * @param string $level
     * @return $this
     */
    public function setLogLevel(string $level): self
    {
        $this->logLevel = $level;

        return $this;
    }

    /**
     * Upload file
     *
     * Returns file key to use for list import, email content, etc.
     *
     * @param string $path
     * @return string file key
     */
    public function uploadFile(string $path): string
    {
        if (!is_readable($path)) {
            throw new Exception\InvalidArgumentException('File path is not readable: ' . $path);
        }
        $file = @fopen($path, 'r');
        if ($file === false) {
            $error = error_get_last();
            throw new Exception\RuntimeException("Unable to open local file: {$error['message']}");
        }

        // Initialise
        /** @var string $fileKey */
        $fileKey = $this->api->file_upload->initialise()->key;

        // Build request
        $multipart = [
            [
                'name' => 'method',
                'contents' => 'handle'
            ],
            [
                'name' => 'key',
                'contents' => $fileKey
            ],
            [
                'name' => 'file',
                'contents' => $file,
                'filename' => basename($path)
            ]
        ];
        $logCtxt = [
            'fileKey' => $fileKey,
            'path'    => $path
        ];

        $this->api->getLogger()->log($this->logLevel, "Upload file: {$fileKey}", $logCtxt);

        // File upload
        try {
            $this->httpClient->request('POST', 'file_upload', [
                'multipart' => $multipart
            ]);
        } finally {
            if (is_resource($file)) {
                fclose($file);
            }
        }

        $this->api->getLogger()->log($this->logLevel, "Upload complete: {$fileKey}", $logCtxt);

        return $fileKey;
    }

    /**
     * Download file by type
     *
     * @param string $type
     * @param string|int $primaryId
     * @param array $options {
     *     @var bool   $extract Whether to extract a compressed download, default true
     *     @var string $dir     Directory to use for downloaded file(s), default sys_temp_dir
     * }
     * @return string filename
     * @throws Exception\InvalidArgumentException
     * @throws Exception\RuntimeException
     */
    public function downloadFile(string $type, $primaryId, array $options = []): string
    {
        $typePrimary = [
            'file'       => 'key',
            'listexport' => 'id',
            'dataexport' => 'id',
        ];

        if (!isset($typePrimary[$type])) {
            throw new Exception\InvalidArgumentException('Invalid download type specified');
        }

        // Create target file
        $filename = tempnam(
            (isset($options['dir']) ? $options['dir'] : sys_get_temp_dir()),
            "mxm-{$type}-{$primaryId}-"
        );
        $local = @fopen($filename, 'w');
        if ($local === false) {
            unlink($filename);
            $error = error_get_last();
            throw new Exception\RuntimeException("Unable to open local file: {$error['message']}");
        }

        $logCtxt = [
            'type' => $type,
            'primaryId' => $primaryId,
            'path' => $filename
        ];
        $this->api->getLogger()->log($this->logLevel, "Download file '{$type}': {$primaryId}", $logCtxt);

        // Make request
        $uri = "/download/{$type}/" .
            "{$typePrimary[$type]}/{$primaryId}";
        try {
            $response = $this->httpClient->request('GET', $uri, [
                'headers' => [
                    'Accept' => '*' // Override API's default 'application/json'
                ],
                'stream' => true
            ]);
        } catch (RequestException $e) {
            fclose($local);
            unlink($filename);
            throw $e;
        }

        // Write file to temp
        $responseBody = $response->getBody();
        while (!$responseBody->eof()) {
            $written = @fwrite($local, $responseBody->read(101400));
            if ($written === false) {
                $error = error_get_last();
                fclose($local);
                unlink($filename);
                throw new Exception\RuntimeException("Unable to write to local file: {$error['message']}");
            }
        }
        fclose($local);
        $this->api->getLogger()->log($this->logLevel, "Download complete '{$type}': {$primaryId}", $logCtxt);

        // Get MIME
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($filename);
        if ($mime === false) {
            unlink($filename);
            throw new Exception\RuntimeException("MIME type could not be determined");
        }

        // Add extension to filename, optionally extract zip
        switch (true) {
            case (strpos($mime, 'zip')) :
                if (!isset($options['extract']) || $options['extract'] == true) {
                    // Maxemail only compresses CSV files, and only contains one file in a zip
                    $zip = new \ZipArchive();
                    $zip->open($filename);
                    $filenameExtract = $zip->getNameIndex(0);
                    $targetDir = rtrim(dirname($filename), '/');
                    $zip->extractTo($targetDir . '/');
                    $zip->close();
                    rename($targetDir . '/' . $filenameExtract, $filename . '.csv');
                    unlink($filename);
                    $filename = $filename . '.csv';
                } else {
                    rename($filename, $filename . '.zip');
                    $filename = $filename . '.zip';
                }

                break;

            case (strpos($mime, 'pdf')) :
                rename($filename, $filename . '.pdf');
                $filename = $filename . '.pdf';
                break;

            case (strpos($mime, 'csv')) :
                // no break
            case ($mime == 'text/plain') :
                rename($filename, $filename . '.csv');
                $filename = $filename . '.csv';
                break;
        }

        return $filename;
    }
}
