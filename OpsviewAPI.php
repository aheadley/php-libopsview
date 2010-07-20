<?php

class OpsviewAPI
{
    protected $config;
    protected $curl_handle;
    protected $states = array(
        'ok'        =>  'state=0',
        'warning'   =>  'state=1',
        'critical'  =>  'state=2',
        'unknown'   =>  'state=3',
        'unhandled' =>  'filter=unhandled',
    );
    protected $api_urls = array(
        'acknowledge'       =>  '/status/service',
        'status_service'    =>  '/api/status/service',
        'status_hostgroup'  =>  '/api/status/hostgroup',
        'login'             =>  '/login',
        'other'             =>  '/api',
    );
    
    public function __construct($config = 'opsview.ini')
    {
        if (is_array($config)) {
            $this->config = $config;
        } elseif (is_string($config) && is_readable($config)) {
            $this->config = parse_ini_file($config);
        } else {
            throw new Exception('no config');
        }

        $this->curl_handle = curl_init();
    }

    public function __destruct()
    {
        curl_close($this->curl_handle);
    }

    public function getStatusService($host, $service)
    {

    }

    public function getStatusHostgroup($hostgroup)
    {

    }

    public function acknowledgeService($host, $service, $comment)
    {

    }

    public function acknowledgeHost($host, $comment)
    {

    }

    public function deleteHost($host)
    {

    }

    public function createHost($newhostname)
    {

    }

    public function scheduleDowntimeHostgroup($hostgroup, $comment)
    {

    }

    public function disableNotificationsHostgroup($hostgroup)
    {

    }

    protected function login()
    {

    }
}
?>