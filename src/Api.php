<?php

namespace Mxm;

use Mxm\Api\JsonClient;
use Mxm\Api\Helper;

/**
 * MXM JSON API Client
 *
 * @category   Mxm
 * @package    Api
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
class Api implements \Psr\Log\LoggerAwareInterface
{
    const VERSION = '2.0';

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
    protected $useSsl = true;

    /**
     * @var JsonClient[]
     */
    protected $services = array();

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

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
            throw new \InvalidArgumentException('Invalid hostname provided');
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
    public function __get($name)
    {
        return $this->getInstance($name);
    }

    /**
     * Get JsonClient for selected service
     *
     * @param string $service
     * @return JsonClient
     */
    protected function getInstance($service)
    {
        if (!isset($this->services[$service])) {
            $this->services[$service] = new JsonClient($service, $this->getConfig());
            $this->services[$service]->setLogger($this->getLogger());
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
    public function getConfig()
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
    public function getHelper()
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

        foreach ($this->services as $service) {
            $service->setLogger($logger);
        }

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
