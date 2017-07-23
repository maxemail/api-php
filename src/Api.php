<?php
declare(strict_types=1);

namespace Mxm;

use Mxm\Api\Exception;
use Mxm\Api\Helper;
use Mxm\Api\JsonClient;
use Mxm\Api\Middleware;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

/**
 * MXM JSON API Client
 *
 * @package    Mxm/Api
 * @copyright  2007-2017 Emailcenter UK Ltd. (https://www.emailcenteruk.com)
 * @license    LGPL-3.0
 *
 * Services
 * @property mixed file_upload https://docs.emailcenteruk.com/mxm-dev/api/services/file-upload
 * @property mixed file_transfer https://docs.emailcenteruk.com/mxm-dev/api/services/file-transfer
 *
 * Navigation
 * @property mixed tree https://docs.emailcenteruk.com/mxm-dev/api/services/tree
 * @property mixed folder https://docs.emailcenteruk.com/mxm-dev/api/services/folder
 *
 * Emails
 * @property mixed campaign https://docs.emailcenteruk.com/mxm-dev/api/services/campaign
 * @property mixed email_campaign https://docs.emailcenteruk.com/mxm-dev/api/services/email-campaign
 * @property mixed email_send https://docs.emailcenteruk.com/mxm-dev/api/services/email-send
 * @property mixed email_triggered https://docs.emailcenteruk.com/mxm-dev/api/services/email-triggered
 * @property mixed folder_recurring https://docs.emailcenteruk.com/mxm-dev/api/services/folder-recurring
 *
 * Content
 * @property mixed snippet https://docs.emailcenteruk.com/mxm-dev/api/services/snippet
 *
 * Data
 * @property mixed recipient https://docs.emailcenteruk.com/mxm-dev/api/services/recipient
 * @property mixed list https://docs.emailcenteruk.com/mxm-dev/api/services/list
 * @property mixed list_import https://docs.emailcenteruk.com/mxm-dev/api/services/list-import
 * @property mixed list_export https://docs.emailcenteruk.com/mxm-dev/api/services/list-export
 * @property mixed profile https://docs.emailcenteruk.com/mxm-dev/api/services/profile
 * @property mixed profile_field https://docs.emailcenteruk.com/mxm-dev/api/services/profile-field
 * @property mixed datatable https://docs.emailcenteruk.com/mxm-dev/api/services/datatable
 * @property mixed datatable_field https://docs.emailcenteruk.com/mxm-dev/api/services/datatable-field
 * @property mixed datatable_import https://docs.emailcenteruk.com/mxm-dev/api/services/datatable-import
 * @property mixed field_type https://docs.emailcenteruk.com/mxm-dev/api/services/field-type
 *
 * Reporting
 * @property mixed comparison_report https://docs.emailcenteruk.com/mxm-dev/api/services/comparison-report
 * @property mixed data_export https://docs.emailcenteruk.com/mxm-dev/api/services/data-export
 * @property mixed data_export_report https://docs.emailcenteruk.com/mxm-dev/api/services/data-export-report
 * @property mixed data_export_quick https://docs.emailcenteruk.com/mxm-dev/api/services/data-export-quick
 * @property mixed data_export_quick_triggered https://docs.emailcenteruk.com/mxm-dev/api/services/data-export-quick-triggered
 *
 * Features
 * @property mixed transactional https://docs.emailcenteruk.com/mxm-dev/api/services/transactional
 * @property mixed data_export_quick_transactional https://docs.emailcenteruk.com/mxm-dev/api/services/data-export-quick-transactional
 */
class Api implements \Psr\Log\LoggerAwareInterface
{
    const VERSION = '4.0';

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
    private $useSsl = true;

    /**
     * @var JsonClient[]
     */
    private $services = [];

    /**
     * @var Helper
     */
    private $helper;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var Client
     */
    private $httpClient;

    /**
     * Construct
     *
     * @param array $config {
     *     @var string $host   Hostname, required
     *     @var string $user   Username, required
     *     @var string $pass   Password, required
     *     @var bool   $useSsl Use secure connection, optional, default true
     * }
     */
    public function __construct(array $config)
    {
        // Validate hostname
        // RFC 952 regex from http://stackoverflow.com/questions/106179/regular-expression-to-match-dns-hostname-or-ip-address
        // Maxemail instances won't require RFC 1123 support
        $valid952HostnameRegex = "/^(([a-zA-Z]|[a-zA-Z][a-zA-Z0-9\-]*[a-zA-Z0-9])\.)*([A-Za-z]|[A-Za-z][A-Za-z0-9\-]*[A-Za-z0-9])$/";

        $this->host = filter_var($config['host'], FILTER_VALIDATE_REGEXP, [
            'options' => [
                'regexp' => $valid952HostnameRegex
            ]
        ]);
        if ($this->host === false) {
            throw new Exception\InvalidArgumentException('Invalid hostname provided');
        }

        $this->username = $config['user'];
        $this->password = $config['pass'];

        if (isset($config['useSsl'])) {
            $this->useSsl = (bool)$config['useSsl'];
        }
    }

    /**
     * Magic get for service
     *
     * @param string $name
     * @return JsonClient
     */
    public function __get(string $name): JsonClient
    {
        return $this->getInstance($name);
    }

    /**
     * Get JsonClient for selected service
     *
     * @param string $service
     * @return JsonClient
     */
    private function getInstance(string $service): JsonClient
    {
        if ($this->httpClient === null) {
            $stack = HandlerStack::create();
            Middleware::addMaxemailErrorParser($stack);
            Middleware::addLogging($stack, $this->getLogger());
            $this->httpClient = new Client([
                'base_uri' => ($this->useSsl ? 'https' : 'http') . "://{$this->host}/api/json/",
                'auth' => [
                    $this->username,
                    $this->password
                ],
                'headers' => [
                    'User-Agent'   => 'MxmJsonClient/' . self::VERSION . ' PHP/' . phpversion(),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept'       => 'application/json'
                ],
                'handler' => $stack
            ]);
        }

        if (!isset($this->services[$service])) {
            $this->services[$service] = new JsonClient($service, $this->httpClient);
        }

        return $this->services[$service];
    }

    /**
     * Get API config
     *
     * @return array {
     *     @var string $host   Hostname
     *     @var string $user   Username
     *     @var string $pass   Password
     *     @var bool   $useSsl Use secure connection
     * }
     */
    public function getConfig(): array
    {
        return [
            'host' => $this->host,
            'user' => $this->username,
            'pass' => $this->password,
            'useSsl' => $this->useSsl
        ];
    }

    /**
     * Get API Helper
     *
     * @return Helper
     */
    public function getHelper(): Helper
    {
        if (!isset($this->helper)) {
            $this->helper = new Helper($this);
        }

        return $this->helper;
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
    public function getLogger(): \Psr\Log\LoggerInterface
    {
        if (!isset($this->logger)) {
            $this->logger = new \Psr\Log\NullLogger();
        }

        return $this->logger;
    }
}
