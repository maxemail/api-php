<?php

declare(strict_types=1);

namespace Maxemail\Api;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\HandlerStack;
use Psr\Log\LoggerInterface;

/**
 * Maxemail API Client
 *
 * @package    Maxemail\Api
 * @copyright  2007-2019 Emailcenter UK Ltd. (https://maxemail.xtremepush.com)
 * @license    LGPL-3.0
 *
 * Services
 * @property mixed file_upload https://docs.maxemail.xtremepush.com/mxm-dev/api/services/file-upload
 * @property mixed file_transfer https://docs.maxemail.xtremepush.com/mxm-dev/api/services/file-transfer
 *
 * Navigation
 * @property mixed tree https://docs.maxemail.xtremepush.com/mxm-dev/api/services/tree
 * @property mixed folder https://docs.maxemail.xtremepush.com/mxm-dev/api/services/folder
 *
 * Emails
 * @property mixed campaign https://docs.maxemail.xtremepush.com/mxm-dev/api/services/campaign
 * @property mixed email_campaign https://docs.maxemail.xtremepush.com/mxm-dev/api/services/email-campaign
 * @property mixed email_send https://docs.maxemail.xtremepush.com/mxm-dev/api/services/email-send
 * @property mixed email_triggered https://docs.maxemail.xtremepush.com/mxm-dev/api/services/email-triggered
 * @property mixed folder_recurring https://docs.maxemail.xtremepush.com/mxm-dev/api/services/folder-recurring
 *
 * Content
 * @property mixed snippet https://docs.maxemail.xtremepush.com/mxm-dev/api/services/snippet
 *
 * Data
 * @property mixed recipient https://docs.maxemail.xtremepush.com/mxm-dev/api/services/recipient
 * @property mixed list https://docs.maxemail.xtremepush.com/mxm-dev/api/services/list
 * @property mixed list_import https://docs.maxemail.xtremepush.com/mxm-dev/api/services/list-import
 * @property mixed list_export https://docs.maxemail.xtremepush.com/mxm-dev/api/services/list-export
 * @property mixed profile https://docs.maxemail.xtremepush.com/mxm-dev/api/services/profile
 * @property mixed profile_field https://docs.maxemail.xtremepush.com/mxm-dev/api/services/profile-field
 * @property mixed datatable https://docs.maxemail.xtremepush.com/mxm-dev/api/services/datatable
 * @property mixed datatable_field https://docs.maxemail.xtremepush.com/mxm-dev/api/services/datatable-field
 * @property mixed datatable_import https://docs.maxemail.xtremepush.com/mxm-dev/api/services/datatable-import
 * @property mixed field_type https://docs.maxemail.xtremepush.com/mxm-dev/api/services/field-type
 *
 * Reporting
 * @property mixed comparison_report https://docs.maxemail.xtremepush.com/mxm-dev/api/services/comparison-report
 * @property mixed data_export https://docs.maxemail.xtremepush.com/mxm-dev/api/services/data-export
 * @property mixed data_export_report https://docs.maxemail.xtremepush.com/mxm-dev/api/services/data-export-report
 * @property mixed data_export_quick https://docs.maxemail.xtremepush.com/mxm-dev/api/services/data-export-quick
 * @property mixed data_export_quick_triggered https://docs.maxemail.xtremepush.com/mxm-dev/api/services/data-export-quick-triggered
 *
 * Features
 * @property mixed transactional https://docs.maxemail.xtremepush.com/mxm-dev/api/services/transactional
 * @property mixed data_export_quick_transactional https://docs.maxemail.xtremepush.com/mxm-dev/api/services/data-export-quick-transactional
 */
class Client implements \Psr\Log\LoggerAwareInterface
{
    public const VERSION = '5.1';

    /**
     * @var string
     */
    private $uri = 'https://mxm.xtremepush.com/';

    /**
     * @var string
     */
    private $token;

    /**
     * @var string
     */
    private $username;

    /**
     * @var string
     */
    private $password;

    /**
     * @var Service[]
     */
    private $services = [];

    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var GuzzleClientInterface
     */
    private $httpClient;

    /**
     * @var bool
     */
    private $debugLoggingEnabled = false;

    /**
     * @var \Closure(array):GuzzleClientInterface
     */
    private $httpClientFactory;

    /**
     * @param array $config {
     *     @var string $token        Required, or username & password
     *     @var string $username     Required, if no token
     *     @var string $password     Required, if no token
     *     @var string $uri          Optional. Default https://mxm.xtremepush.com/
     *     @var string $user         @deprecated See username
     *     @var string $pass         @deprecated See password
     *     @var bool   $debugLogging Optional. Enable logging of request/response. Default false
     * }
     */
    public function __construct(array $config)
    {
        // Must have API token
        if (!isset($config['token'])) {
            // Support deprecated key names from v3
            if (!isset($config['username']) && isset($config['user'])) {
                $config['username'] = $config['user'];
            }
            if (!isset($config['password']) && isset($config['pass'])) {
                $config['password'] = $config['pass'];
            }

            // Must have user/pass
            if (!isset($config['username']) || !isset($config['password'])) {
                throw new Exception\InvalidArgumentException('API config requires token OR username & password');
            }
            $this->username = $config['username'];
            $this->password = $config['password'];
        } else {
            $this->token = $config['token'];
        }

        if (isset($config['uri'])) {
            $parsed = parse_url($config['uri']);
            if ($parsed === false) {
                throw new Exception\InvalidArgumentException('URI malformed');
            }
            if (!isset($parsed['scheme']) || !isset($parsed['host'])) {
                throw new Exception\InvalidArgumentException('URI must contain protocol scheme and host');
            }
            $this->uri = "{$parsed['scheme']}://{$parsed['host']}/";
        }

        if (isset($config['debugLogging'])) {
            $this->debugLoggingEnabled = (bool)$config['debugLogging'];
        }
    }

    public function __get(string $name): Service
    {
        return $this->getInstance($name);
    }

    private function getInstance(string $serviceName): Service
    {
        if (!isset($this->services[$serviceName])) {
            $this->services[$serviceName] = new Service($serviceName, $this->getClient());
        }

        return $this->services[$serviceName];
    }

    private function getClient(): GuzzleClientInterface
    {
        if ($this->httpClient === null) {
            $stack = HandlerStack::create();
            Middleware::addMaxemailErrorParser($stack);
            Middleware::addWarningLogging($stack, $this->getLogger());
            if ($this->debugLoggingEnabled) {
                Middleware::addLogging($stack, $this->getLogger());
            }

            $clientConfig = [
                'base_uri' => $this->uri . 'api/json/',
                'headers' => [
                    'User-Agent' => 'MxmApiClient/' . self::VERSION . ' PHP/' . PHP_VERSION,
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept' => 'application/json',
                ],
                'handler' => $stack,
            ];

            if (isset($this->token)) {
                $clientConfig['headers']['Authorization'] = 'Bearer ' . $this->token;
            } else {
                $clientConfig['auth'] = [
                    $this->username,
                    $this->password,
                ];
            }

            if (!isset($this->httpClientFactory)) {
                $this->httpClient = new GuzzleClient($clientConfig);
            } else {
                $this->httpClient = ($this->httpClientFactory)($clientConfig);
            }
        }

        return $this->httpClient;
    }

    /**
     * Get API connection config
     *
     * @deprecated v5.2 No replacement; packages can maintain their own config; to be removed in v7.
     * @return array {
     *     @var string $uri
     *     @var string $username
     *     @var string $password
     * }
     */
    public function getConfig(): array
    {
        return [
            'uri' => $this->uri,
            'username' => $this->username,
            'password' => $this->password,
        ];
    }

    public function getHelper(): Helper
    {
        if (!isset($this->helper)) {
            $this->helper = new Helper($this, $this->getClient());
        }

        return $this->helper;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function getLogger(): LoggerInterface
    {
        if (!isset($this->logger)) {
            $this->logger = new \Psr\Log\NullLogger();
        }

        return $this->logger;
    }

    /**
     * @internal This method is not part of the BC promise. Used for DI for unit tests only.
     */
    public function setHttpClientFactory(\Closure $httpClientFactory): void
    {
        $this->httpClientFactory = $httpClientFactory;
    }
}
