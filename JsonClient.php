<?php

namespace Mxm;

/**
 * MXM JSON API Client
 *
 * @category   Mxm
 * @package    Mxm_Api
 * @copyright  Copyright (c) 2007-2014 Emailcenter UK. (http://www.emailcenteruk.com)
 * @license    Commercial
 *
 * Services
 * @property mixed file_upload http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:file_upload
 * @property mixed file_transfer http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:file_transfer
 *
 * Navigation
 * @property mixed tree http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:tree
 * @property mixed folder http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:folder
 *
 * Emails
 * @property mixed campaign http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:campaign
 * @property mixed email_campaign http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:email_campaign
 * @property mixed email_send http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:email_send
 * @property mixed email_triggered http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:email_triggered
 * @property mixed folder_recurring http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:folder_recurring
 *
 * Content
 * @property mixed snippet http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:snippet
 *
 * Data
 * @property mixed recipient http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:recipient
 * @property mixed list http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:list
 * @property mixed list_import http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:list_import
 * @property mixed list_export http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:list_export
 * @property mixed profile http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:profile
 * @property mixed profile_field http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:profile_field
 * @property mixed datatable http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:datatable
 * @property mixed datatable_field http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:datatable_field
 * @property mixed datatable_import http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:datatable_import
 * @property mixed field_type http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:field_type
 *
 * Reporting
 * @property mixed comparison_report http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:comparison_report
 * @property mixed data_export http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:data_export
 * @property mixed data_export_report http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:data_export_report
 * @property mixed data_export_quick http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:data_export_quick
 *
 * Features
 * @property mixed transactional http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:transactional
 * @property mixed data_export_quick_transactional http://maxemail.emailcenteruk.com/manual/doku.php?id=maxemail:v6:webservices:data_export_quick_transactional
 */
class Api
{
    /**
     * @var array
     */
    protected $services = array();

    /**
     * @var string
     */
    protected $url = null;

    /**
     * @var string
     */
    protected $username = null;

    /**
     * @var string
     */
    protected $password = null;

    /**
     * Construct
     *
     * @param array $config array containing url, user, pass
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
     * @return ApiJsonClient
     */
    public function getInstance($service)
    {
        if (!isset($this->services[$service])) {
            $url = $this->url . $service;
            $this->services[$service] = new ApiJsonClient($url, $this->username, $this->password);
        }
        return $this->services[$service];
    }

    /**
     * Magic get for service
     *
     * @param string $name
     * @return ApiJsonClient
     */
    public function __get($name)
    {
        return $this->getInstance($name);
    }
}

/**
 * MXM JSON API Client
 *
 * @category   Mxm
 * @package    Mxm_Api
 * @copyright  Copyright (c) 2007-2014 Emailcenter UK. (http://www.emailcenteruk.com)
 * @license    Commercial
 */
class ApiJsonClient
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
