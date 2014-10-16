<?php

namespace Mxm;

use Mxm\Api\JsonClient;

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