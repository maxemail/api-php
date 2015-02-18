<?php

namespace Mxm\Api;

/**
 * MXM JSON API Client
 *
 * @category   Mxm
 * @package    Api
 * @copyright  Copyright (c) 2007-2014 Emailcenter UK. (http://www.emailcenteruk.com)
 * @license    Commercial
 */
class JsonClient implements \Psr\Log\LoggerAwareInterface
{
    const VERSION = '2.0';

    /**
     * @var string
     */
    protected $service;

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
     * @var string
     */
    private $lastRequest;

    /**
     * @var string
     */
    private $lastResponse;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Construct
     *
     * @param string $service
     * @param array $config {
     *     @var string $host
     *     @var string $user
     *     @var string $pass
     *     @var bool   $useSsl
     * }
     */
    public function __construct($service, $config)
    {
        $this->service = $service;

        $this->host     = $config['host'];
        $this->username = $config['user'];
        $this->password = $config['pass'];
        $this->useSsl   = (bool)$config['useSsl'];
    }

    /**
     * Magic call for service method
     *
     * @param string $name
     * @param array $params
     * @return mixed
     */
    public function __call($name, array $params)
    {
        $data = array(
            'method' => $name
        );
        foreach ($params as $i => $param) {
            if (is_array($param)) {
                $param = json_encode($param);
            }
            $data['arg' . $i] = $param;
        }

        $json = $this->postRequest($data);

        return $this->decodeJson($json);
    }

    /**
     * Post request
     *
     * @param array $data
     * @return string
     * @throws \RuntimeException
     */
    protected function postRequest(array $data)
    {
        $port = $this->useSsl ? 443 : 80;
        $host = ($this->useSsl ? 'ssl://' : '') . $this->host;

        $socket = @fsockopen($host, $port);
        if ($socket === false) {
            $error = error_get_last();
            throw new \RuntimeException("Failed to connect to {$this->host} on port $port, {$error['message']}");
        }

        $body = http_build_query($data);
        $headers = array(
            'Host'           => $this->host,
            'Connection'     => 'close',
            'Content-type'   => 'application/x-www-form-urlencoded',
            'Content-length' => strlen($body),
            'User-Agent'     => 'MxmJsonClient/' . self::VERSION  . ' PHP/' . phpversion()
        );

        if (!is_null($this->username) && !is_null($this->password)) {
            $basicAuth                = base64_encode($this->username . ':' . $this->password);
            $headers['Authorization'] = "Basic $basicAuth";
        }

        $request = "POST /api/json/{$this->service} HTTP/1.0\r\n";
        foreach ($headers as $key => $value) {
            $request .= "$key: $value\r\n";
        }
        $request .= "\r\n$body";
        $this->lastRequest = $request;
        $this->getLogger()->debug("Request: {$this->service}.{$data['method']}", [
            'params'  => $data,
            'host'    => $this->host,
            'request' => $request
        ]);

        if (@fwrite($socket, $request) === false) {
            $error = error_get_last();
            throw new \RuntimeException("Failed to write to socket, {$error['message']}");
        }

        $response = '';
        while (!feof($socket)) {
            $response .= fread($socket, 8192);
        }
        @fclose($socket);

        $this->lastResponse = $response;
        $this->getLogger()->debug("Response: {$this->service}.{$data['method']}", [
            'params'   => $data,
            'host'     => $this->host,
            'response' => $response
        ]);

        preg_match("|^HTTP/[\d\.x]+ (\d+)|", $response, $matches);
        $code = (int)$matches[1];

        $parts   = preg_split('|(?:\r?\n){2}|m', $response, 2);
        $content = $parts[1];

        if ((int)$code != 200) {
            try {
                $message = $this->decodeJson($content);
                if ($message instanceof \stdClass && isset($message->msg)) {
                    $content = $message->msg;
                }
            } catch (\UnexpectedValueException $e) {
                // Void
                // Failed to decode, leave content as the raw response
            }
            throw new \RuntimeException($content, $code);
        }

        return $content;
    }

    /**
     * Last request
     *
     * @return string
     */
    public function getLastRequest()
    {
        return $this->lastRequest;
    }

    /**
     * Last response
     *
     * @return string
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * Decode JSON
     *
     * @param string $json
     * @return mixed
     * @throws \UnexpectedValueException
     */
    protected function decodeJson($json)
    {
        $result = json_decode($json, false);

        if ($result === null) {
            // Error checking
            switch (json_last_error()) {
                case JSON_ERROR_DEPTH:
                    $error = 'Maximum stack depth exceeded';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $error = 'Unexpected control character found';
                    break;
                case JSON_ERROR_SYNTAX:
                    $error = 'Syntax error, malformed JSON';
                    break;
                case JSON_ERROR_NONE:
                default:
                    // Value is really null
                    $error = '';
                    break;
            }

            if (!empty($error)) {
                throw new \UnexpectedValueException("Problem decoding json ($json), {$error}");
            }
        }

        return $result;
    }

    /**
     * Sets a logger instance on the object
     *
     * @param \Psr\Log\LoggerInterface $logger
     * @return $this
     */
    public function setLogger(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Gets the logger, creating a null logger if none defined
     *
     * @return \Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        if (!isset($this->logger)) {
            $this->logger = new \Psr\Log\NullLogger();
        }

        return $this->logger;
    }
}
