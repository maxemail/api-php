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
    use ConnectionTrait;
    use JsonTrait;

    /**
     * @var string
     */
    protected $service;

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

        $this->setConnectionConfig($config);
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
        $socket = $this->getConnection();

        $body = http_build_query($data);
        $headers = $this->getHeaders();
        $headers['Content-length'] = strlen($body);

        $request = $this->buildPostRequest("/api/json/{$this->service}", $headers, $body);

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

        return $this->processJsonResponse($content, $code);
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
