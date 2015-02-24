<?php

namespace Mxm\Api;

use Mxm\Api;

/**
 * MXM JSON API Client
 *
 * @category   Mxm
 * @package    Api
 * @copyright  Copyright (c) 2007-2015 Emailcenter UK. (http://www.emailcenteruk.com)
 * @license    Commercial
 */
trait ConnectionTrait
{
    /**
     * @var string
     */
    protected $host;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var bool
     */
    protected $useSsl;

    /**
     * @param array $config {
     *     @var string $host
     *     @var string $user
     *     @var string $pass
     *     @var bool   $useSsl
     * }
     */
    protected function setConnectionConfig($config)
    {
        $this->host     = $config['host'];
        $this->username = $config['user'];
        $this->password = $config['pass'];
        $this->useSsl   = (bool)$config['useSsl'];
    }

    /**
     * Get connection socket
     *
     * @return resource
     */
    protected function getConnection()
    {
        $port = $this->useSsl ? 443 : 80;
        $host = ($this->useSsl ? 'ssl://' : '') . $this->host;

        $socket = @fsockopen($host, $port);
        if ($socket === false) {
            $error = error_get_last();
            throw new \RuntimeException("Failed to connect to {$this->host} on port $port, {$error['message']}");
        }

        return $socket;
    }

    /**
     * Get request headers
     *
     * @param int|null $contentLength bytes, null if unknown
     * @return array
     */
    protected function getHeaders($contentLength)
    {
        $headers = array(
            'Host'           => $this->host,
            'Connection'     => 'close',
            'Content-type'   => 'application/x-www-form-urlencoded',
            'User-Agent'     => 'MxmJsonClient/' . Api::VERSION . ' PHP/' . phpversion()
        );

        if ($contentLength > 0) {
            $headers['Content-length'] = (int)$contentLength;
        }

        if (!is_null($this->username) && !is_null($this->password)) {
            $basicAuth                = base64_encode($this->username . ':' . $this->password);
            $headers['Authorization'] = "Basic $basicAuth";
        }

        return $headers;
    }

    /**
     * Build request string
     * Body is optional, headers will not be terminated if body not provided
     *
     * @param string $uri
     * @param array $headers
     * @param string $body
     * @return string
     */
    protected function buildPostRequest($uri, array $headers = [], $body = '')
    {
        $request = "POST {$uri} HTTP/1.0\r\n";

        foreach ($headers as $key => $value) {
            $request .= "$key: $value\r\n";
        }

        if (!empty($body)) {
            $request .= "\r\n$body";
        }

        return $request;
    }
}
