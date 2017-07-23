<?php
declare(strict_types=1);

namespace Mxm\Api;

use Mxm\Api;
use Mxm\Api\Exception;

/**
 * MXM JSON API Client
 *
 * @package    Mxm/Api
 * @copyright  2007-2017 Emailcenter UK Ltd. (https://www.emailcenteruk.com)
 * @license    LGPL-3.0
 */
trait ConnectionTrait
{
    /**
     * @var string
     */
    private $host;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @var bool
     */
    private $useSsl;

    /**
     * @param array $config {
     *     @var string $host
     *     @var string $user
     *     @var string $pass
     *     @var bool   $useSsl
     * }
     */
    private function setConnectionConfig($config)
    {
        $this->host     = $config['host'];
        $this->username = $config['user'];
        $this->password = $config['pass'];
        $this->useSsl   = (bool)$config['useSsl'];
    }

    /**
     * Get request headers
     *
     * @return array
     */
    private function getHeaders(): array
    {
        $headers = [
            'Host'           => $this->host,
            'Connection'     => 'close',
            'Content-type'   => 'application/x-www-form-urlencoded',
            'User-Agent'     => 'MxmJsonClient/' . Api::VERSION . ' PHP/' . phpversion()
        ];

        if (!is_null($this->username) && !is_null($this->password)) {
            $basicAuth                = base64_encode($this->username . ':' . $this->password);
            $headers['Authorization'] = "Basic $basicAuth";
        }

        return $headers;
    }
}
