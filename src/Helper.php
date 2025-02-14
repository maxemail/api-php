<?php

declare(strict_types=1);

namespace Maxemail\Api;

use GuzzleHttp\Client as GuzzleClient;
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
    private string $logLevel = LogLevel::DEBUG;

    public function __construct(
        private readonly Client $api,
        private readonly GuzzleClient $httpClient,
    ) {
    }

    public function setLogLevel(string $level): self
    {
        $this->logLevel = $level;

        return $this;
    }

    /**
     * @return string File key to use for list import, email content, etc.
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
                'contents' => 'handle',
            ],
            [
                'name' => 'key',
                'contents' => $fileKey,
            ],
            [
                'name' => 'file',
                'contents' => $file,
                'filename' => basename($path),
            ],
        ];
        $logCtxt = [
            'fileKey' => $fileKey,
            'path' => $path,
        ];

        $this->api->getLogger()->log($this->logLevel, "Upload file: {$fileKey}", $logCtxt);

        // File upload
        try {
            $this->httpClient->request('POST', 'file_upload', [
                'multipart' => $multipart,
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
     * @param array{
     *     extract: bool, // Whether to extract a compressed download, default true
     *     dir: string, // Directory to use for downloaded file(s), default sys_temp_dir
     * } $options
     * @return string filename
     */
    public function downloadFile(
        string $type,
        string|int $primaryId,
        array $options = [],
    ): string {
        $typePrimary = [
            'file' => 'key',
            'listexport' => 'id',
            'dataexport' => 'id',
        ];

        if (!isset($typePrimary[$type])) {
            throw new Exception\InvalidArgumentException('Invalid download type specified');
        }

        // Create target file
        $filename = tempnam(
            ($options['dir'] ?? sys_get_temp_dir()),
            "mxm-{$type}-{$primaryId}-",
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
            'path' => $filename,
        ];
        $this->api->getLogger()->log($this->logLevel, "Download file '{$type}': {$primaryId}", $logCtxt);

        // Make request
        $uri = "/download/{$type}/" .
            "{$typePrimary[$type]}/{$primaryId}";
        try {
            $response = $this->httpClient->request('GET', $uri, [
                'headers' => [
                    // Override API's default 'application/json'
                    'Accept' => '*',
                ],
                'stream' => true,
            ]);
        } catch (\Throwable $e) {
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

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($filename);
        if ($mime === false) {
            unlink($filename);
            throw new Exception\RuntimeException('MIME type could not be determined');
        }

        // Add extension to filename, optionally extract zip
        switch (true) {
            case str_contains($mime, 'zip'):
                if (!isset($options['extract']) || $options['extract'] === true) {
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

            case str_contains($mime, 'pdf'):
                rename($filename, $filename . '.pdf');
                $filename = $filename . '.pdf';
                break;

            case str_contains($mime, 'csv'):
            case $mime === 'text/plain':
                rename($filename, $filename . '.csv');
                $filename = $filename . '.csv';
                break;
        }

        return $filename;
    }
}
