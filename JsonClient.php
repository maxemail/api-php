<?php

 *
 * v1.4, config array version
 *
 * @category   Mxm
 * @package    Mxm_Api
 * @copyright  Copyright (c) 2007-2012 Emailcenter UK. (http://www.emailcenteruk.com)
 * @license    Commercial
 */
class Mxm_Api_JsonClient
{
 
    protected $services = array();
    protected $url      = null;
    protected $username = null;
    protected $password = null;
 
    /**
     * Construct
     *
     * @param array $config object containing url, user, pass
     */
    public function __construct(array $config)
    {
        $this->url      = rtrim($config['url'], '/') . '/api/json/';
        $this->username = $config['user'];
        $this->password = $config['pass'];
    }
 
    /**
     * Get JsonClient for selected service
     *
     * @param string $service
     * @return JsonClient
     */
    public function getInstance($service)
    {
        if (!isset($this->services[$service])) {
            $url = $this->url . $service;
            $this->services[$service] = new JsonClient($url, $this->username, $this->password);
        }
        return $this->services[$service];
    }
 
    /**
     * Magic get for service
     *
     * @param string $name
     * @return JsonClient
     */
    public function __get($name)
    {
        return $this->getInstance($name);
    }
 
}
 
/**
 * MXM JSON API Client
 *
 * v1.4, config array version
 *
 * @category   Mxm
 * @package    Mxm_Api
 * @copyright  Copyright (c) 2007-2012 Emailcenter UK. (http://www.emailcenteruk.com)
 * @license    Commercial
 */
class JsonClient
{
 
    /**
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
     */
    protected function _postRequest(array $data)
    {
        $port = strtolower($this->scheme) == 'https' ? 443 : 80;
        $host = strtolower($this->scheme) == 'https' ? 'ssl://' : '';
        $host.= $this->host;
 
        $socket = @fsockopen($host, $port);
        if ($socket === false) {
            $error = error_get_last();
            throw new Exception("Failed to connect to {$this->host} on port $port, {$error['message']}");
        }
 
        $basicAuth = base64_encode($this->username . ':' . $this->password);
        $body = http_build_query($data);
 
        $headers = array(
            'Host'              => $this->host,
            'Connection'        => 'close',
            'Authorization'     => "Basic $basicAuth",
            'Content-type'      => 'application/x-www-form-urlencoded',
            'Content-length'    => strlen($body),
            'User-Agent'        => 'MxmJsonClient/1.4a PHP/' . phpversion()
        );
        $request = "POST {$this->path} HTTP/1.0\r\n";
        foreach ($headers as $key => $value) {
            $request .= "$key: $value\r\n";
        }
        $request .= "\r\n$body";
        $this->lastRequest = $request;
 
        if (@fwrite($socket, $request) === false) {
            $error = error_get_last();
            throw new Exception("Failed to write to socket, {$error['message']}");
        }
 
        $response = '';
        while (!feof($socket)) {
            $response .= fread($socket, 8192);
        }
        @fclose($socket);
 
        $this->lastResponse = $response;
 
        preg_match("|^HTTP/[\d\.x]+ (\d+)|", $response, $matches);
        $code = (int)$matches[1];
 
        $parts = preg_split('|(?:\r?\n){2}|m', $response, 2);
        $content = $parts[1];
 
        if ((int)$code != 200) {
            try {
                $message = (array)$this->_decodeJson($content);
                if (array_key_exists('msg', $message)) {
                    $content = $message['msg'];
                }
            } catch (Exception $e) {
 
            }
            throw new Exception($content, $code);
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
     * @return array
     */
    private function _decodeJson($json)
    {
        $result = json_decode($json, false);
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
            throw new Exception("Problem decoding json ($json), {$error}");
        }
        return $result;
    }
 
    public function __call($name, $params)
    {
        $data = array(
            'method' => $name
        );
        for ($i = 0; $i < count($params); $i++) {
            if (is_array($params[$i])) {
                $params[$i] = json_encode($params[$i]);
            }
            $data['arg' . $i] = $params[$i];
        }
        $json = $this->_postRequest($data);
        if ($json == 'null') {
            return null;
        }
        return $this->_decodeJson($json);
    }
 
}

