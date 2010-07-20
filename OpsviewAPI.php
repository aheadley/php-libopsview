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
        $cookie_file = $this->config['cache_dir'] . PATH_SEPARATOR . 'login_cookie.txt';
        $post_data = array(
            'login_username'    =>  $this->config['username'],
            'login_password'    =>  $this->config['password'],
            'back'              =>  '',
            'login'             =>  'Log In',
        );

        curl_setopt_array($this->curl_handle, array(
            CURLOPT_URL             =>  $this->config['base_url'] . $this->api_urls['login'],
            CURLOPT_RETURNTRANSFER  =>  true,
            CURLOPT_POSTFIELDS      =>  $this->format_url_args($post_data),
            CURLOPT_COOKIEJAR       =>  $cookie_file,
        ));

        if (file_exists($cookie_file)
            && (time() - filemtime($cookie_file)) < $this->config['cookie_cache_time']) {
            //cookie file exists and is within cache window so we're still logged in (probably), do nothing
            return true;
        } else {
            return curl_exec($this->curl_handle);
        }
    }

    protected function format_url_args($args)
    {
        if (is_array($args) and count($args) > 0) {
            $args_encoded = '';
            foreach ($args as $key => $value) {
                $args_encoded .= urlencode($key) . '=' . urlencode($value) . '&';
            }
            $args_encoded = substr($args_encoded, 0, strlen($args_encoded)-1);

            return $args_encoded;
        } else {
            return null;
        }
    }
}
?>