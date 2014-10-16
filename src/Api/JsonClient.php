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
class JsonClient
{
    const VERSION = '1.6';

    /**
     * @var string
     */
    private $lastRequest;

    /**
     * @var string
     */
    private $lastResponse;

    /**
     * Construct
     *
     * @param string $url
     * @param string $username
     * @param string $password
     */
    public function __construct($url, $username, $password)
    {
        $this->url = $url;

        $parts = parse_url($url);
        $this->scheme   = $parts['scheme'];
        $this->host     = $parts['host'];
        $this->path     = $parts['path'];
        $this->username = $username;
        $this->password = $password;
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
        $port = strtolower($this->scheme) == 'https' ? 443 : 80;
        $host = strtolower($this->scheme) == 'https' ? 'ssl://' : '';
        $host.= $this->host;

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

        $request = "POST {$this->path} HTTP/1.0\r\n";
        foreach ($headers as $key => $value) {
            $request .= "$key: $value\r\n";
        }
        $request .= "\r\n$body";
        $this->lastRequest = $request;

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

        preg_match("|^HTTP/[\d\.x]+ (\d+)|", $response, $matches);
        $code = (int)$matches[1];

        $parts   = preg_split('|(?:\r?\n){2}|m', $response, 2);
        $content = $parts[1];

        if ((int)$code != 200) {
            try {
                $message = (array)$this->decodeJson($content);
                if (array_key_exists('msg', $message)) {
                    $content = $message['msg'];
                }
            } catch (\UnexpectedValueException $e) {
                // Void
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
     * @return \stdClass
     * @throws \UnexpectedValueException
     */
    protected function decodeJson($json)
    {
        $result = json_decode($json, false);

        // Error checking
        $ver = version_compare(PHP_VERSION, '5.3');
        if ($ver == '-1') {
            // Less than php5.3
            $error = '';
            if ($result === null) {
                $error = 'JSON was not able to be decoded';
            }
        } else {
            // At least php5.3
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
                    $error = '';
                    break;
            }
        }

        if (!empty($error)) {
            throw new \UnexpectedValueException("Problem decoding json ($json), {$error}");
        }

        return $result;
    }

    /**
     * Magic call for service method
     *
     * @param string $name
     * @param array $params
     * @return \stdClass
     */
    public function __call($name, $params)
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
        if ($json == 'null') {
            return null;
        }

        return $this->decodeJson($json);
    }
}
