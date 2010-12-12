<?php

class OpsviewRemote {
    //4-bit mask
    const STATE_OK = 1;
    const STATE_WARNING = 2;
    const STATE_CRITICAL = 4;
    const STATE_UNKNOWN = 8;

    const TYPE_XML = 'text/xml';
    const TYPE_JSON = 'application/json';

    protected static $URL = array(
        'acknowledge'       =>  'status/service/acknowledge',
        'status'            =>  'api/status/service',
        'status_hostgroup'  =>  'api/status/hostgroup',
        'login'             =>  'login',
        'api'               =>  'api',
    );

    protected $_connection;
    protected $url, $username, $password;

    public function  __construct($base_url, $username, $password,
        $content_type=null) {

        $this->base_url = $base_url;
        $this->username = $username;
        $this->password = $password;
        if ($content_type == self::TYPE_XML or $content_type == self::TYPE_JSON) {
            $this->content_type = $content_type;
        } else {
            $this->content_type = self::TYPE_XML;
        }

        $this->_connection = new Zend_Http_Client(
            $this->base_url,
            array(
                'strictredirects'   => true,
                'timeout'           => 60,
                'keepalive'         => true,
            ));
        $this->_connection->setCookieJar();
    }

    public function getStatusAll($status_mask=0, $unhandled=false) {
        $this->_login();
        $this->_connection->resetParameters()->
            setUri($this->base_url . self::$URL['status'])->
            setHeaders('Content-Type', $this->content_type);
        for ($i=0;$i<4;$i++) {
            if ((pow(2,$i) & $status_mask) == pow(2,$i)) {
                $this->_connection->setParameterGet('state', $i);
            }
        }
        if ($unhandled) {
            $this->_connection->setParameterGet('filter', 'unhandled');
        }
        $response = $this->_connection->request(Zend_Http_Client::GET);

        if ($response->getStatus() == 200) {
            return trim($response->getBody());
        } else {
            return null;
        }
    }

    public function getStatusHost($host_name, $status_mask=0, $unhandled=false) {
        $this->_login();
        $this->_connection->resetParameters()->
            setUri($this->base_url . self::$URL['status'])->
            setHeaders('Content-Type', $this->content_type)->
            setParameterGet('host', $host_name);
        for ($i=0;$i<4;$i++) {
            if ((pow(2,$i) & $status_mask) == pow(2,$i)) {
                $this->_connection->setParameterGet('state', $i);
            }
        }
        if ($unhandled) {
            $this->_connection->setParameterGet('filter', 'unhandled');
        }
        $response = $this->_connection->request(Zend_Http_Client::GET);

        if ($response->getStatus() == 200) {
            return trim($response->getBody());
        } else {
            return null;
        }
    }

    public function getStatusHosts($hostgroup_id, $status_mask=0,
        $unhandled=false) {

        $this->_login();
        $this->_connection->resetParameters()->
            setUri($this->base_url . self::$URL['status'])->
            setHeaders('Content-Type', $this->content_type)->
            setParameterGet('hostgroupid', $hostgroup_id);
        for ($i=0;$i<4;$i++) {
            if ((pow(2,$i) & $status_mask) == pow(2,$i)) {
                $this->_connection->setParameterGet('state', $i);
            }
        }
        if ($unhandled) {
            $this->_connection->setParameterGet('filter', 'unhandled');
        }
        $response = $this->_connection->request(Zend_Http_Client::GET);

        if ($response->getStatus() == 200) {
            return trim($response->getBody());
        } else {
            return null;
        }
    }

    public function getStatusService($host_name, $service_name) {
        $host_status = $this->getStatusHost($host_name);
        switch ($this->content_type) {
            case self::TYPE_JSON:
                foreach ($host_status['data']['list']['services'] as $service) {
                    if (strtolower($service['name']) == strtolower($service_name)) {
                        return json_encode($service);
                    }
                }
                break;
            case self::TYPE_XML:
            default:
                $host_status = simplexml_load_string($host_status);
                foreach ($host_status->data->list->services as $service) {
                    if (strtolower((string)$service->attributes()->name) ==
                        strtolower($service_name)) {

                        return $service->asXML();
                    }
                }
        }
    }

    public function getStatusHostgroup($hostgroup_id) {
        $this->_login();
        $this->_connection->resetParameters()->
            setUri($this->base_url . self::$URL['status_hostgroup'] .
                '/' . $hostgroup_id)->
            setHeaders('Content-Type', $this->content_type);
        $response = $this->_connection->request(Zend_Http_Client::GET);

        if ($response->getStatus() == 200) {
            return trim($response->getBody());
        } else {
            return null;
        }
    }

    public function acknowledgeAll($comment, $notify=true,
        $auto_remove_comment=true) {

        $status = $this->getStatusAll(
            self::STATE_WARNING | self::STATE_CRITICAL, true);
        $targets = array();
        switch ($this->content_type) {
            case self::TYPE_JSON:
                $status = json_decode($status, true);
                $hosts = $status_decoded['data']['list'];
                foreach ($hosts as $host) {
                    $targets[$host['name']] = array();
                    if ($host['current_check_attempt'] == $host['max_check_attempts']) {
                        $targets[$host['name']][] = null;
                    } else {
                        foreach ($host['services'] as $service) {
                            if ($service['current_check_attempts'] ==
                                $service['max_check_attempts']) {

                                $targets[$host['name']][] = $service['name'];
                            }
                        }
                    }
                }
                break;
            case self::TYPE_XML:
            default:
                $hosts = simplexml_load_string($status)->data->list;
                foreach ($hosts as $host) {
                    $targets[(string)$host->attributes()->name] = array();
                    if ((string)$host->attributes()->state == 'down') {
                        $targets[(string)$host->attributes()->name][] = null;
                    }
                    foreach ($host->services as $service) {
                        if ((int)$service->attributes()->current_check_attempt ==
                            (int)$service->attributes()->max_check_attempts) {

                            $targets[(string)$host->attributes()->name][] =
                                (string)$service->attributes()->name;
                        }
                    }
                }
        }

        return $this->_acknowledge($targets, $comment, $notify, $auto_remove_comment);
    }

    public function acknowledgeHost($host_name, $comment, $notify=true,
        $auto_remove_comment=true) {

        $this->_acknowledge(array($host_name => array(null)), $comment, $notify,
            $auto_remove_comment);
    }

    public function acknowledgeService($service_name, $host_name, $comment,
        $notify=true, $auto_remove_comment=true) {

        $this->_acknowledge(array($host_name => array($service_name)), $comment,
            $notify, $auto_remove_comment);
    }

    public function createHost($attributes);
    public function cloneHost($source_host, $dest_host, $attributes);
    public function deleteHost($host);
    public function scheduleDowntime($hostgroup, $start_time, $end_time, $comment);
    public function disableScheduledDowntime($hostgroup);
    public function enableNotifications($hostgroup);
    public function disableNotifications($hostgroup);
    public function reload();

    protected function _login() {
        if (!$this->_connection->getCookieJar()->getCookie($this->base_url,
            'auth_tkt', Zend_Http_CookieJar::COOKIE_OBJECT)) {

            $this->_connection->setUri($this->base_url . self::$URL['login']);
            $this->_connection->setParameterPost(array(
                'login_username'    => $this->username,
                'login_password'    => $this->password,
                'back'              => $this->base_url,
                'login'             => 'Log In',
            ));

            $this->_connection->request(Zend_Http_Client::POST);
        }

        return $this;
    }

    protected function _acknowledge($targets, $comment, $notify,
        $auto_remove_comment) {

        $this->_login();
        $this->_connection->resetParameters()->
            setUri($this->base_url . self::$URL['acknowledge'])->
            setRawData($this->_format_post_data($targets, array(
                'from'  => $this->base_url,
                'submit'    => 'Submit',
                'comment'   => $comment,
                'notify'    => ($notify ? 'on' : 'off'),
                'autoremovecomment' => ($auto_remove_comment ? 'on' : 'off'),
            )));

        $response = $this->_connection->request(Zend_Http_Client::POST);
        return $response->getStatus() == 200;
    }

    protected function _format_post_data($targets, $extra=null) {
        $host_selection = array();
        $service_selection = array();
        $extra_data = array();
        $post_data = '';
        foreach ($targets as $host => $services) {
            foreach ($services as $service) {
                if (!$service) {
                    $host_selection[] = 'host_selection=' . urlencode($host);
                } else {
                    $service_selection[] = 'service_selection=' .
                        urlencode($host . ';' . $service);
                }
            }
        }
        $host_selection = implode('&', $host_selection);
        $service_selection = implode('&', $service_selection);

        if (is_array($extra)) {
            foreach ($extra as $key => $value) {
                $extra_data[] = urlencode($key) . '=' . urlencode($value);
            }
            $extra_data = implode('&', $extra_data);
        }

        return trim(trim(implode('&', array(
            $host_selection,
            $service_selection,
            $extra_data,
        )), '&'));

    }
}