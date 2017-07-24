<?php
declare(strict_types=1);

namespace Mxm\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Mxm\Api;
use Mxm\Api\Exception;

/**
 * MXM JSON API Client
 *
 * @package    Mxm/Api
 * @copyright  2007-2017 Emailcenter UK Ltd. (https://www.emailcenteruk.com)
 * @license    LGPL-3.0
 */
class Helper
{
    use ConnectionTrait;
    use JsonTrait;

    /**
     * @var Api
     */
    private $api;

    /**
     * @var Client
     */
    private $httpClient;

    /**
     * @param Api $api
     * @param Client $httpClient
     */
    public function __construct(Api $api, Client $httpClient)
    {
        $this->api = $api;
        $this->httpClient = $httpClient;
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
        $basename = basename($path);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if ($mime === false) {
            throw new Exception\RuntimeException("MIME type could not be determined");
        }

        // Initialise
        /** @var string $fileKey */
        $fileKey = $this->api->file_upload->initialise()->key;

        // Build request
        $this->setConnectionConfig($this->api->getConfig());
        $headers = $this->getHeaders();

        $handleurl = ($this->useSsl ? 'https' : 'http') .
            '://' .
            $this->host .
            '/api/json/file_upload';

        $file = new \CURLFile($path, $mime, $basename);

        $params = [
            'method' => 'handle',
            'key'    => $fileKey,
            'file' => $file,
        ];

        // Write request
        $this->api->getLogger()->debug("Upload file {$fileKey}", [
            'fileKey' => $fileKey,
            'path'    => $path,
            'user'    => $this->username
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $handleurl);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC) ;
        curl_setopt($ch, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        curl_setopt($ch, CURLOPT_USERAGENT, $headers['User-Agent']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $response = curl_exec($ch);

        // Read response
        if ($response === false) {
            $errorMsg = curl_error($ch);
            curl_close($ch);
            throw new Exception\RuntimeException("Failed to upload file: {$errorMsg}");
        }

        $responseCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Handle any API exceptions
        // Valid response isn't needed, as 'handle' always returns true
        $this->processJsonResponse($response, $responseCode);

        curl_close($ch);

        $this->api->getLogger()->debug("Upload complete {$fileKey}", [
            'fileKey' => $fileKey,
            'path'    => $path,
            'user'    => $this->username
        ]);

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
            $error = error_get_last();
            throw new Exception\RuntimeException("Unable to open local file: {$error['message']}");
        }

        $logCtxt = [
            'type' => $type,
            'primaryId' => $primaryId,
            'path' => $filename
        ];
        $this->api->getLogger()->debug("Download file '{$type}': {$primaryId}", $logCtxt);

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
            unlink($local);
            throw $e;
        }

        // Write file to temp
        $responseBody = $response->getBody();
        while (!$responseBody->eof()) {
            $written = @fwrite($local, $responseBody->read(101400));
            if ($written === false) {
                $error = error_get_last();
                fclose($local);
                unlink($local);
                throw new Exception\RuntimeException("Unable to write to local file: {$error['message']}");
            }
        }
        fclose($local);
        $this->api->getLogger()->debug("Download complete '{$type}': {$primaryId}", $logCtxt);

        // Get MIME
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($filename);
        if ($mime === false) {
            unlink($local);
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
