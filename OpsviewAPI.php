<?php

class OpsviewAPI
{
    protected $config;
    protected $curl_handle;
    protected $cookie_file;
    protected $content_type;
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
        //TODO: need to set defaults then use something like array_replace()
        //  to overwrite default settings
        if (is_array($config)) {
            $this->config = $config;
        } elseif (is_string($config) && is_readable($config)) {
            $this->config = parse_ini_file($config);
        } else {
            //TODO: change this to something meaningful
            throw new Exception('no config');
        }

        $this->curl_handle = curl_init();
        $this->cookie_file = 'cookie_file.txt';
        switch ($this->config['content_type']) {
            case 'xml':
                $this->content_type = 'text/xml';
                break;
            case 'json':
            default:
                $this->content_type = 'application/json';
                break;
        }
    }

    public function __destruct()
    {
        curl_close($this->curl_handle);
    }

    public function getStatusService($host_name, $service_name)
    {
        $host_status = null;
        $service_status = null;
        
        switch ($this->config['content_type'] ) {
            case 'xml':
                $host_status = simplexml_load_string($this->getStatusHost($host_name))
                    ->data->list->services;
                foreach ($host_status as $service) {
                    $attr = $service->attributes();
                    if ($attr['name'] == $service_name) {
                        $service_status = $service;
                        break;
                    }
                }
                break;
            case 'json':
            default:
                $host_status = json_decode($this->getStatusHost($host_name),true);
                foreach ($host_status['service']['list'][0]['services'] as $service) {
                    if ($service['name'] == $service_name) {
                        $service_status = json_encode($service);
                        break;
                    }
                }
                break;
        }

        return $service_status;
    }

    public function getStatusHost($host_name)
    {
        $host_status = null;
        //TODO: check cache here
        $this->login();
        curl_setopt_array($this->curl_handle, array(
            CURLOPT_URL             =>  $this->config['base_url'] .
                $this->api_urls['status_host'] . '?host=' . $host_name,
            CURLOPT_RETURNTRANSFER  =>  true,
            CURLOPT_COOKIEFILE      =>  $this->config['cache_dir'] .
                PATH_SEPARATOR . $this->cookie_file,
            CURLOPT_HTTPHEADER      =>  array(
                'Content-Type: ' . $this->content_type,
            ),
        ));
        $host_status = trim(curl_exec($this->curl_handle));

        return $host_status;
    }

    public function getStatusHostgroup($hostgroup_id)
    {
        $hostgroup_status = null;
        //TODO: check cache here
        $this->login();
        curl_setopt_array($this->curl_handle, array(
            CURLOPT_URL             =>  $this->config['base_url'] .
                $this->api_urls['status_hostgroup'] . '/' . $hostgroup_id,
            CURLOPT_RETURNTRANSFER  =>  true,
            CURLOPT_COOKIEFILE      =>  $this->config['cache_dir'] .
                PATH_SEPARATOR . $this->cookie_file,
            CURLOPT_HTTPHEADER      =>  array(
                'Content-Type: ' . $this->content_type,
                ),
        ));
        $hostgroup_status = trim(curl_exec($this->curl_handle));

        return $hostgroup_status;
    }

    public function acknowledgeService($host, $service, $comment,
        $notify = true, $autoremovecomment = true)
    {
        return $this->acknowledge(array($host => $service), $comment, $notify,
            $autoremovecomment);
    }

    public function acknowledgeHost($host, $comment, $notify = true,
        $autoremovecomment = true)
    {
        return $this->acknowledge(array($host), $comment, $notify, $autoremovecomment);
    }

    public function acknowledgeAll($comment, $notify = false, $autoremovecomment = true)
    {

    }

    public function createHost($new_host_name)
    {
        return false;
    }

    public function deleteHost($host_name)
    {
        return false;
    }

    public function reload()
    {
        return false;
    }

    public function scheduleDowntimeHostgroup($hostgroup, $comment)
    {

    }

    public function disableNotificationsHostgroup($hostgroup)
    {

    }

    protected function login()
    {
        $cookie_file = $this->config['cache_dir'] . PATH_SEPARATOR .
            $this->cookie_file;
        $post_data = array(
            'login_username'    =>  $this->config['username'],
            'login_password'    =>  $this->config['password'],
            'back'              =>  '',
            'login'             =>  'Log In',
        );

        curl_setopt_array($this->curl_handle, array(
            CURLOPT_URL             =>  $this->config['base_url'] .
                $this->api_urls['login'],
            CURLOPT_RETURNTRANSFER  =>  true,
            CURLOPT_POSTFIELDS      =>  $this->format_url_args($post_data),
            CURLOPT_COOKIEJAR       =>  $cookie_file,
        ));

        if (file_exists($cookie_file)
            && (time() - filemtime($cookie_file)) <
                $this->config['cookie_cache_time']) {
            //cookie file exists and is within cache window so we're still
            //  logged in (probably), do nothing
            return true;
        } else {
            //TODO: actually check the output of curl to see if login was (probably) successful
            return curl_exec($this->curl_handle);
        }
    }

    protected function format_url_args($args)
    {
        //TODO: make this handle arrays (like would be needed for acknowledgements
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

    protected function acknowledge($alerting, $comment, $notify = true, $autoremovecomment = true)
    {
        $hosts = array();
        $services= array();
        $post_args = '';
        $this->login();

        foreach ($alerting as $host => $service) {
            if ($service == '') {
                $hosts[] = $host;
            } else {
                $services[] = $host . ';' . $service;
            }
        }

        foreach ($hosts as $host) {
                $post_args .= '&' . 'host_selection=' . urlencode($host);
        }
        foreach ($services as $service) {
            $post_args .= '&' . 'service_selection=' . urlencode($service);
        }

        if ($post_args == '') {
            //stop here if we're not acknowledging anything
            return false;
        } else {
            curl_setopt_array($this->curl_handle, array(
                CURLOPT_URL             =>  $this->config['base_url'] .
                    $this->api_urls['acknowledge'],
                CURLOPT_POSTFIELDS      =>  $this->format_url_args(array(
                        'from'              =>  $this->config['base_url'],
                        'submit'            =>  'Submit',
                        'comment'           =>  $comment,
                        'notify'            =>  ($notify ? 'on' : 'off'),
                        'autoremovecomment' =>  ($autoremovecomment ? 'on' : 'off'),
                    )) . $post_args,
                CURLOPT_RETURNTRANSFER  =>  true,
                CURLOPT_COOKIEFILE      =>  $this->config['cache_dir'] .
                    PATH_SEPARATOR . $this->cookie_file,
            ));

            return curl_exec($this->curl_handle);
        }
    }
}
?>