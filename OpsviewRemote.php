<?php
require_once 'Zend/Http/Client.php';
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
    protected $base_url, $username, $password, $content_type;

    public function  __construct($base_url, $username, $password,
        $content_type=null) {

        $this->base_url = $base_url;
        $this->username = $username;
        $this->password = $password;
        $this->content_type = ($content_type == self::TYPE_JSON ?
            self::TYPE_JSON : self::TYPE_XML);

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
        $get_params = array('state' => array());
        for ($i=0; $i<4; $i++) {
            if ((pow(2,$i) & $status_mask) == pow(2,$i)) {
                $get_params['state'][] = $i;
            }
        }
        if ($unhandled) {
            $get_params['filter'] = 'unhandled';
        }
        $this->_connection->resetParameters()->
            setUri($this->base_url . self::$URL['status'] . '?' .
                self::_formatRequestParameters($get_params))->
            setHeaders('Content-Type', $this->content_type);
        $response = $this->_connection->request(Zend_Http_Client::GET);

        if ($response->getStatus() == 200) {
            return trim($response->getBody());
        } else {
            return null;
        }
    }

    public function getStatusHost($host_name, $status_mask=0, $unhandled=false) {
        $this->_login();
        $get_params = array('state' => array(), 'host' => $host_name);
        for ($i=0; $i<4; $i++) {
            if ((pow(2,$i) & $status_mask) == pow(2,$i)) {
                $get_params['state'][] = $i;
            }
        }
        if ($unhandled) {
            $get_params['filter'] = 'unhandled';
        }
        $this->_connection->resetParameters()->
            setUri($this->base_url . self::$URL['status'] . '?' .
                self::_formatRequestParameters($get_params))->
            setHeaders('Content-Type', $this->content_type);
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
        $get_params = array('state' => array(), 'hostgroupid' => $hostgroup_id);
        for ($i=0; $i<4; $i++) {
            if ((pow(2,$i) & $status_mask) == pow(2,$i)) {
                $get_params['state'][] = $i;
            }
        }
        if ($unhandled) {
            $get_params['filter'] = 'unhandled';
        }
        $this->_connection->resetParameters()->
            setUri($this->base_url . self::$URL['status'] . '?' .
                self::_formatRequestParameters($get_params))->
            setHeaders('Content-Type', $this->content_type);
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

    public function getStatusHostgroup($hostgroup_id='') {
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

        return $this->_acknowledge(array($host_name => array(null)), $comment, $notify,
            $auto_remove_comment);
    }

    public function acknowledgeService($service_name, $host_name, $comment,
        $notify=true, $auto_remove_comment=true) {

        return $this->_acknowledge(array($host_name => array($service_name)), $comment,
            $notify, $auto_remove_comment);
    }

    public function createHost($attributes) {
        $required_attributes = array('name', 'ip');
$xml_template = <<<'XML'
<opsview>
    <host action="create">
        %s
    </host>
</opsview>
XML;

        if (!self::_checkRequiredAttributes($required_attributes, $attributes)) {
            throw RuntimeException('Missing required host attribute');
        } else {
            return $this->_postXml(sprintf($xml_template,
                $this->_arrayToXml($attributes)));
        }
    }
    public function cloneHost($source_host, $attributes) {
        $required_attributes = array('name', 'ip');
$xml_template = <<<'XML'
<opsview>
    <host action="create">
        <clone>
            <name>%s</name>
        </clone>
        %s
    </host>
</opsview>
XML;
        if (!self::_checkRequiredAttributes($required_attributes, $attributes)) {
            throw RuntimeException('Missing required host attribute');
        } else {
            return $this->_postXml(sprintf($xml_template, $source_host,
                $this->_arrayToXml($attributes)));
        }
    }

    public function deleteHost($host) {
$xml_template = <<<'XML'
<opsview>
    <host action="delete" by_%s="%s"/>
</opsview>
XML;

        return $this->_postXml(sprintf($xml_template,
            (is_numeric($host) ? 'id' : 'name'), $host));
    }

    public function scheduleDowntime($hostgroup, $start_time, $end_time, $comment) {
$xml_template = <<<'XML'
<opsview>
    <hostgroup action="change" by_%s="%s">
        <downtime
            start="%s"
            end="%s"
            comment="%s">
            enable
        </downtime>
    </hostgroup>
</opsview>
XML;

        return $this->_postXml(sprintf($xml_template,
            (is_numeric($hostgroup) ? 'id' : 'name'), $hostgroup, $start_time,
            $end_time, $comment));
    }

    public function disableScheduledDowntime($hostgroup) {
$xml_template = <<<'XML'
<opsview>
    <hostgroup action="change" by_%s="%s">
        <downtime>disable</downtime>
    </hostgroup>
</opsview>
XML;

        return $this->_postXml(sprintf($xml_template,
            (is_numeric($hostgroup) ? 'id' : 'name'), $hostgroup));
    }

    public function enableNotifications($hostgroup) {
$xml_template = <<<'XML'
<opsview>
    <hostgroup action="change" by_%s="%s">
        <notifications>enable</notifications>
    </hostgroup>
</opsview>
XML;

        return $this->_postXml(sprintf($xml_template,
            (is_numeric($hostgroup) ? 'id' : 'name'), $hostgroup));
    }

    public function disableNotifications($hostgroup) {
$xml_template = <<<'XML'
<opsview>
    <hostgroup action="change" by_%s="%s">
        <notifications>disable</notifications>
    </hostgroup>
</opsview>
XML;

        return $this->_postXml(sprintf($xml_template,
            (is_numeric($hostgroup) ? 'id' : 'name'), $hostgroup));
    }

    public function reload() {
$xml_template = <<<'XML'
<opsview>
    <system action="reload"/>
</opsview>
XML;
        return $this->_postXml($xml_template);
    }

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
            setRawData(self::_formatAckPostParameters($targets, array(
                'from'  => $this->base_url,
                'submit'    => 'Submit',
                'comment'   => $comment,
                'notify'    => ($notify ? 'on' : 'off'),
                'autoremovecomment' => ($auto_remove_comment ? 'on' : 'off'),
            )));

        $response = $this->_connection->request(Zend_Http_Client::POST);
        return $response->getStatus() == 200;
    }

    protected static function _formatRequestParameters($parameters) {
        $params_parsed = array();
        foreach ($parameters as $parameter => $value) {
            if (is_array($value)) {
                foreach ($value as $subvalue) {
                    $params_parsed[] = urlencode($parameter) . '=' .
                        urlencode($subvalue);
                }
            } else {
                $params_parsed[] = urlencode($parameter) . '=' .
                    urlencode($value);
            }
        }

        return implode('&', $params_parsed);
    }

    protected static function _formatAckPostParameters($targets, $extra=null) {
        $params_prepped = (is_array($extra) ? $extra : array());
        $params_prepped['host_selection'] = array();
        $params_prepped['service_selection'] = array();

        foreach ($targets as $host => $services) {
            foreach ($services as $service) {
                if (!$service) {
                    $params_prepped['host_selection'][] = $host;
                } else {
                    $params_prepped['service_selection'][] = $host . ';' .
                        $service;
                }
            }
        }

        return self::_formatRequestParameters($params_prepped);
    }

    protected static function _arrayToXml($data) {
        $xml_string = '';
        foreach ($data as $tag => $content) {
            if (is_array($content)) {
                $xml_string .= "<${tag}>" . self::_arrayToXml($content) .
                    "</${tag}>";
            } else {
                $xml_string .= "<${tag}>${content}</${tag}>";
            }
        }

        return $xml_string;
    }

    protected static function _checkRequiredAttributes($required_attributes,
        $attributes) {

        foreach ($required_attributes as $attribute) {
            if (!array_key_exists($attribute, $attributes) ||
                is_null($attributes[$attribute])) {
                return false;
            }
        }

        return true;
    }

    protected function _postXml($xml_string) {
        $this->_login();
        $this->_connection->resetParameters()->
            setUri($this->base_url . self::$URL['api'])->
            setHeaders('Content-Type', self::TYPE_XML)->
            setRawData(trim($xml_string));

        $response = $this->_connection->request(Zend_Http_Client::POST);
        if ($response->getStatus() == 200) {
            return trim($response->getBody());
        } else {
            return null;
        }
    }
}