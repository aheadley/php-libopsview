<?php

class OpsviewAPI
{
    protected $config;
    protected $curl_handle_get, $curl_handle_post;
    protected $cookie_file, $content_type, $curl_user_agent;
    protected $cache_file_suffix = 'cache';
    protected $states = array(
        'ok'        =>  'state=0',
        'warning'   =>  'state=1',
        'critical'  =>  'state=2',
        'unknown'   =>  'state=3',
        'unhandled' =>  'filter=unhandled',
    );
    protected $api_urls = array(
        'acknowledge'       =>  '/status/service/acknowledge',
        'status_all'        =>  '/api/status/service',
        'status_service'    =>  '/api/status/service',
        'status_host'       =>  '/api/status/service',
        'status_hostgroup'  =>  '/api/status/hostgroup',
        'login'             =>  '/login',
        'api'               =>  '/api',
    );
    
    public function __construct($config = 'opsview.ini')
    {
        $this->config = array(
            'status_cache_time' =>  10,
            'cookie_cache_time' =>  60*60*4, // 4 hours
            'content_type'      =>  'json',
            'cache_dir'         =>  getcwd() . DIRECTORY_SEPARATOR . 'cache',
            'use_cache'         =>  (is_writable($this->config['cache_dir']) ?
                true : false),
        );

        if (is_string($config) && is_readable($config)) {
            $config = parse_ini_file($config);
        }
        if (is_array($config)) {
            foreach ($config as $key => $value) {
                $this->config[$key] = $value;
            }
        }
        if (!isset($this->config['base_url']) || !isset($this->config['username']) ||
            !isset($this->config['password'])) {
            throw new RuntimeException('Invalid configuration');
        }

        $this->curl_user_agent = curl_version();
        $this->curl_user_agent = 'PHP ' . phpversion() . '/cURL ' . $this->curl_user_agent['version'];
        $this->cookie_file = 'cookie.' . $this->cache_file_suffix;
        switch ($this->config['content_type']) {
            case 'xml':
                $this->content_type = 'text/xml';
                break;
            case 'json':
            default:
                $this->content_type = 'application/json';
                break;
        }
        $this->curl_handle_post = curl_init();
        $this->curl_handle_get = curl_init();
        curl_setopt_array($this->curl_handle_post, array(
            CURLOPT_RETURNTRANSFER  =>  true,
            CURLOPT_POST            =>  true,
            CURLOPT_COOKIEFILE      =>  $this->config['cache_dir'] . DIRECTORY_SEPARATOR .
                $this->cookie_file,
            CURLOPT_COOKIEJAR       =>  $this->config['cache_dir'] . DIRECTORY_SEPARATOR .
                $this->cookie_file,
            CURLOPT_USERAGENT       =>  $this->curl_user_agent,
        ));
        curl_setopt_array($this->curl_handle_get, array(
            CURLOPT_RETURNTRANSFER  =>  true,
            CURLOPT_COOKIEFILE      =>  $this->config['cache_dir'] . DIRECTORY_SEPARATOR .
                $this->cookie_file,
            CURLOPT_COOKIEJAR       =>  $this->config['cache_dir'] . DIRECTORY_SEPARATOR .
                $this->cookie_file,
            CURLOPT_USERAGENT       =>  $this->curl_user_agent,
        ));
    }

    public function __destruct()
    {
        curl_close($this->curl_handle_get);
        curl_close($this->curl_handle_post);
    }

    public function getStatusAll($filters)
    {
        $status = null;
        $filter_arg = '';
        $cache_key = 'status-all';

        if ($this->checkCache($cache_key)) {
            return $this->getCache($cache_key);
        } else {
            $this->login();

            if (is_array($filters) && count($filters) > 0) {
                foreach ($filters as $filter) {
                    $filter_arg .= $this->states[$filter] . '&';
                }
                $filter_arg = substr($filter_arg, 0, strlen($filter_arg)-1);
            }

            curl_setopt_array($this->curl_handle_get, array(
                CURLOPT_URL             =>  $this->config['base_url'] . $this->api_urls['status_all'] .
                    '?' . $filter_arg,
                CURLOPT_HTTPHEADER      =>  array(
                    'Content-Type: ' . $this->content_type,
                ),
            ));

            $response = curl_exec($this->curl_handle_get);
            $this->cache($cache_key, $response);
            return $response;
        }
    }

    public function getStatusService($host_name, $service_name)
    {
        $host_status = null;
        $service_status = null;
        $cache_key = 'status-service-' . $host_name . '-' . $service_name;

        if ($this->checkCache($cache_key)) {
            return $this->getCache($cache_key);
        } else {
            switch ($this->config['content_type'] ) {
                case 'xml':
                    $host_status = simplexml_load_string($this->getStatusHost($host_name))
                        ->opsview->data->list->services;
                    foreach ($host_status as $service) {
                        if ($service->attributes()->name == $service_name) {
                            $service_status = $service->asXML();
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

            $this->cache($cache_key, $service_status);
            return $service_status;
        }
    }

    public function getStatusHost($host_name)
    {
        $host_status = null;
        $cache_key = 'status-host-' . $host_name;

        if ($this->checkCache($cache_key)) {
            return $this->getCache($cache_key);
        } else {
            $this->login();
            curl_setopt_array($this->curl_handle_get, array(
                CURLOPT_URL             =>  $this->config['base_url'] .
                    $this->api_urls['status_host'] . '?host=' . $host_name,
                CURLOPT_HTTPHEADER      =>  array(
                    'Content-Type: ' . $this->content_type,
                ),
            ));

            $host_status = trim(curl_exec($this->curl_handle_get));
            $this->cache($cache_key, $host_status);
            return $host_status;
        }
    }

    public function getStatusHostgroup($hostgroup_id)
    {
        $hostgroup_status = null;
        $cache_key = 'status-hostgroup-' . $hostgroup_id;

        if ($this->checkCache($cache_key)) {
            return $this->getCache($cache_key);
        } else {
            $this->login();
            curl_setopt_array($this->curl_handle_get, array(
                CURLOPT_URL             =>  $this->config['base_url'] .
                    $this->api_urls['status_hostgroup'] . '/' . $hostgroup_id,
                CURLOPT_HTTPHEADER      =>  array(
                    'Content-Type: ' . $this->content_type,
                    ),
            ));

            $hostgroup_status = trim(curl_exec($this->curl_handle_post));
            $this->cache($cache_key, $hostgroup_status);
            return $hostgroup_status;
        }
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
        return $this->acknowledge(array(
            $host => null,
            ), $comment, $notify, $autoremovecomment);
    }

    public function acknowledgeAll($comment, $notify = false, $autoremovecomment = true)
    {
        $alerting = array();
        //TODO: this is wrong, need to change either here or filters in getStatusAll()
        $alerting_raw = $this->getStatusAll(array(
            $this->states['critical'],
            $this->states['warning'],
            $this->states['unhandled'],
        ));

        switch ($this->config['content_type']) {
            /* lol whoops, wasn't thinking ahead on this. since the hostname is
             *  is used as the array key if there is more than one service alerting
             *  but unacknowledged only the last service will actually be acknowledged
             *  since the others get overwritten
             * TODO: fix this, use arrays for services maybe?
             */
            case 'xml':
                $alerting_raw = simplexml_load_string($alerting)->opsview->data->list;
                foreach ($alerting_raw as $host_raw) {
                    if ($host_raw->attributes()->current_check_attempt ==
                        $host_raw->attributes()->max_check_attempts) {
                        $alerting[$host_raw->attributes()->name] = null;
                    } else {
                        foreach ($alerting_raw->services as $service_raw) {
                            if ($service_raw->attributes()->current_check_attempt ==
                                $service_raw->attributes()->max_check_attempts) {
                                $alerting[$host_raw->attributes()->name] =
                                    $service_raw->attributes()->name;
                            }
                        }
                    }
                }
            case 'json':
            default:
                $alerting_raw = json_decode($alerting_raw, true);
                $alerting_raw = $alerting_raw['data']['list'];
                foreach ($alerting_raw as $host_raw) {
                    if ($host_raw['current_check_attempt'] == $host_raw['max_check_attempts']) {
                        $alerting[$host_raw['name']] = null;
                    } else {
                        foreach ($alerting_raw['services'] as $service_raw) {
                            if ($service_raw['current_check_attempt'] ==
                                $service_raw['max_check_attempts']) {
                                $alerting[$host_raw['name']] = $service_raw['name'];
                            }
                        }
                    }
                }
        }   //end switch

        return $this->acknowledge($alerting, $comment, $notify, $autoremovecomment);
    }

    public function createHost($new_host_name)
    {
        return false;
    }

    public function cloneHost($new_host_name, $old_host_name)
    {
        $xml = '<opsview><host action="create"><clone><name>%s</name></clone>' .
            '<name>%s</name></host></opsview>';
        return $this->sendXmlToApi(sprintf($xml, $old_host_name, $new_host_name));
    }

    public function deleteHostById($host_id)
    {
        $xml = '<opsview><host action="delete" by_id="%s"/></opsview>';
        return $this->sendXmlToApi(sprintf($xml, $host_id));
    }

    public function deleteHostByName($host_name)
    {
        $xml = '<opsview><host action="delete" by_name="%s"/></opsview>';
        return $this->sendXmlToApi(sprintf($xml, $host_name));
    }

    public function reload()
    {
        return $this->sendXmlToApi('<opsview><system action="reload"/></opsview>');
    }

    public function scheduleDowntimeHostgroup($hostgroup, $comment)
    {
        return false;
    }

    public function disableNotificationsHostgroup($hostgroup)
    {
        return false;
    }

    protected function login()
    {
        $cookie_file = $this->config['cache_dir'] . DIRECTORY_SEPARATOR .
            $this->cookie_file;
        $post_data = array(
            'login_username'    =>  $this->config['username'],
            'login_password'    =>  $this->config['password'],
            'back'              =>  '',
            'login'             =>  'Log In',
        );

        curl_setopt_array($this->curl_handle_post, array(
            CURLOPT_URL             =>  $this->config['base_url'] .
                $this->api_urls['login'],
            CURLOPT_POSTFIELDS      =>  http_build_query($post_data, '', '&'),
        ));

        if (is_readable($cookie_file)
            && (time() - filemtime($cookie_file)) <
                $this->config['cookie_cache_time']) {
            //cookie file exists and is within cache window so we're still
            //  logged in (probably), do nothing
            return true;
        } else {
            //TODO: actually check the output of curl to see if login was (probably) successful
            return curl_exec($this->curl_handle_post);
        }
    }

    protected function acknowledge($alerting, $comment, $notify = true, $autoremovecomment = true)
    {
        $post_args = '';
        $this->login();

        foreach ($alerting as $host => $service) {
            if ($service == null) {
                $post_args .= '&host_selection=' . urlencode($host);
            } else {
                $post_args .= '&service_selection=' . urlencode($host . ';' . $service);
            }
        }

        if ($post_args == '') {
            //stop here if we're not acknowledging anything
            return false;
        } else {
            curl_setopt_array($this->curl_handle_post, array(
                CURLOPT_URL             =>  $this->config['base_url'] .
                    $this->api_urls['acknowledge'],
                CURLOPT_POSTFIELDS      =>  http_build_query(array(
                        'from'              =>  $this->config['base_url'],
                        'submit'            =>  'Submit',
                        'comment'           =>  $comment,
                        'notify'            =>  ($notify ? 'on' : 'off'),
                        'autoremovecomment' =>  ($autoremovecomment ? 'on' : 'off'),
                    ), '', '&') . $post_args,
            ));

            return curl_exec($this->curl_handle_post);
        }
    }

    protected function sendXmlToApi($xml_string)
    {
        if (!simplexml_load_string($xml_string)) {
            return false;
        } else {
            $this->login();

            curl_setopt_array($this->curl_handle_post, array(
                CURLOPT_URL             =>  $this->config['base_url'] . $this->api_urls['api'],
                CURLOPT_POST            =>  true,
                CURLOPT_POSTFIELDS      =>  $this->escapeXml($xml_string),
            ));

            return curl_exec($this->curl_handle_post);
        }
    }

    protected function escapeXml($xml)
    {
        return preg_replace('/(\r?\n)+/', '', addslashes($xml));
    }

    protected function cache($key, $string)
    {
        $cache_file = $this->config['cache_dir'] . DIRECTORY_SEPARATOR .
            basename($key) . '.' . $this->config['content_type'] . '.' .
            $this->cache_file_suffix;

        return ($this->config['use_cache'] &&
            @file_put_contents($cache_file, $string, LOCK_EX) && true);
    }

    protected function getCache($key)
    {
        $cache_file = $this->config['cache_dir'] . DIRECTORY_SEPARATOR .
            basename($key) . '.' . $this->config['content_type'] . '.' .
            $this->cache_file_suffix;

        return @file_get_contents($cache_file, false);
    }

    protected function checkCache($key)
    {
        $cache_file = $this->config['cache_dir'] . DIRECTORY_SEPARATOR .
            basename($key) . '.' . $this->config['content_type'] . '.' .
            $this->cache_file_suffix;

        return ($this->config['use_cache'] && is_readable($cache_file) &&
            (abs(time() - filemtime($cache_file)) <= $this->config['status_cache_time']));
    }
}
?>