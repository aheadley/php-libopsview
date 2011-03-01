<?php

class Opsview_Remote {
  //4-bit mask
  const STATE_OK              = 1;
  const STATE_WARNING         = 2;
  const STATE_CRITICAL        = 4;
  const STATE_UNKNOWN         = 8;
  const TIME_FORMAT           = 'Y/m/d H:i:s';
  const TYPE_XML              = 'text/xml';
  const TYPE_JSON             = 'application/json';
  const URL_ACK               = 'status/service/acknowledge';
  const URL_STATUS            = 'api/status/service';
  const URL_STATUS_HOSTGROUP  = 'api/status/hostgroup';
  const URL_LOGIN             = 'login';
  const URL_API               = 'api';

  private $_connection  = null;
  private $_baseUrl     = null;
  private $_username    = null;
  private $_password    = null;

  /**
   *
   * @param string $baseUrl url to base opsview location, i.e.
   *                          https://www.example.com/opsview/
   * @param string $username username to login to opsview
   * @param string $password password for that user
   * @param string $content_type content type to get back from opsview, only
   *                              applies to status requests, api requests are
   *                              always in xml
   */
  public function __construct( $baseUrl, $username, $password,
                               $content_type=null ) {

    $this->baseUrl = $baseUrl;
    $this->username = $username;
    $this->password = $password;
    $this->contentType = ($content_type == self::TYPE_JSON ?
        self::TYPE_JSON : self::TYPE_XML);

    $this->_connection = new Zend_Http_Client(
        $this->baseUrl,
        array(
          'strictredirects' => true,
          'timeout' => (int)ini_get( 'default_socket_timeout' ),
          'keepalive' => true,
      ) );
    $this->_connection->setCookieJar();
  }

  /**
   *
   * @param int $status_mask mask results to certain status, multiple statuses
   *                          can be combined with bitwise OR ("|")
   * @param bool $unhandled limit results to unhandled problems only
   * @return bool see _acknowledge()
   */
  public function getStatusAll( $status_mask=0, $unhandled=false ) {
    $this->_login();
    $get_params = array( 'state' => array( ) );
    for( $i = 0; $i < 4; $i++ ) {
      if( (pow( 2, $i ) & $status_mask) == pow( 2, $i ) ) {
        $get_params['state'][] = $i;
      }
    }
    if( $unhandled ) {
      $get_params['filter'] = 'unhandled';
    }
    $this->_connection->resetParameters()->
      setUri( $this->baseUrl . self::URL_STATUS . '?' .
        self::_formatRequestParameters( $get_params ) )->
      setHeaders( 'Content-Type', $this->contentType );
    $response = $this->_connection->request( Zend_Http_Client::GET );

    return ($response->getStatus() == 200 ? trim( $response->getBody() ) : null);
  }

  public function getStatusHost( $host_name, $status_mask=0, $unhandled=false ) {
    $this->_login();
    $get_params = array( 'state' => array( ), 'host' => $host_name );
    for( $i = 0; $i < 4; $i++ ) {
      if( (pow( 2, $i ) & $status_mask) == pow( 2, $i ) ) {
        $get_params['state'][] = $i;
      }
    }
    if( $unhandled ) {
      $get_params['filter'] = 'unhandled';
    }
    $this->_connection->resetParameters()->
      setUri( $this->baseUrl . self::URL_STATUS . '?' .
        self::_formatRequestParameters( $get_params ) )->
      setHeaders( 'Content-Type', $this->contentType );
    $response = $this->_connection->request( Zend_Http_Client::GET );

    return ($response->getStatus() == 200 ? trim( $response->getBody() ) : null);
  }

  public function getStatusHosts( $hostgroup_id, $status_mask=0,
                                  $unhandled=false ) {

    $this->_login();
    $get_params = array( 'state' => array( ), 'hostgroupid' => $hostgroup_id );
    for( $i = 0; $i < 4; $i++ ) {
      if( (pow( 2, $i ) & $status_mask) == pow( 2, $i ) ) {
        $get_params['state'][] = $i;
      }
    }
    if( $unhandled ) {
      $get_params['filter'] = 'unhandled';
    }
    $this->_connection->resetParameters()->
      setUri( $this->baseUrl . self::URL_STATUS . '?' .
        self::_formatRequestParameters( $get_params ) )->
      setHeaders( 'Content-Type', $this->contentType );
    $response = $this->_connection->request( Zend_Http_Client::GET );

    return ($response->getStatus() == 200 ? trim( $response->getBody() ) : null);
  }

  public function getStatusService( $host_name, $service_name ) {
    $host_status = $this->getStatusHost( $host_name );
    switch( $this->contentType ) {
      case self::TYPE_JSON:
        foreach( $host_status['data']['list']['services'] as $service ) {
          if( strtolower( $service['name'] ) == strtolower( $service_name ) ) {
            return json_encode( $service );
          }
        }
        break;
      case self::TYPE_XML:
      default:
        $host_status = simplexml_load_string( $host_status );
        foreach( $host_status->data->list->services as $service ) {
          if( strtolower( (string)$service->attributes()->name ) ==
            strtolower( $service_name ) ) {

            return $service->asXML();
          }
        }
    }
  }

  public function getStatusHostgroup( $hostgroup_id='' ) {
    $this->_login();
    $this->_connection->resetParameters()->
      setHeaders( 'Content-Type', $this->contentType );
    if( is_numeric( $hostgroup_id ) || empty( $hostgroup_id ) ) {
      $this->_connection->setUri( $this->baseUrl . self::URL_STATUS_HOSTGROUP . '/' . $hostgroup_id );
    } else {
      $this->_connection->setUri( $this->baseUrl . self::URL_STATUS_HOSTGROUP )->
      setParameterGet( 'by_name', $hostgroup_id );
    }
    $response = $this->_connection->request( Zend_Http_Client::GET );

    return ($response->getStatus() == 200 ? trim( $response->getBody() ) : null);
  }

  public function acknowledgeAll( $comment, $notify=true,
                                  $auto_remove_comment=true ) {

    $status = $this->getStatusAll(
        self::STATE_WARNING | self::STATE_CRITICAL, true );
    $targets = array( );
    switch( $this->contentType ) {
      case self::TYPE_JSON:
        $status = json_decode( $status, true );
        $hosts = $status_decoded['data']['list'];
        foreach( $hosts as $host ) {
          $targets[$host['name']] = array( );
          if( $host['current_check_attempt'] == $host['max_check_attempts'] ) {
            $targets[$host['name']][] = null;
          } else {
            foreach( $host['services'] as $service ) {
              if( $service['current_check_attempts'] ==
                $service['max_check_attempts'] ) {

                $targets[$host['name']][] = $service['name'];
              }
            }
          }
        }
        break;
      case self::TYPE_XML:
      default:
        $hosts = simplexml_load_string( $status )->data->list;
        foreach( $hosts as $host ) {
          $targets[(string)$host->attributes()->name] = array( );
          if( (string)$host->attributes()->state == 'down' ) {
            $targets[(string)$host->attributes()->name][] = null;
          }
          foreach( $host->services as $service ) {
            if( (int)$service->attributes()->current_check_attempt ==
              (int)$service->attributes()->max_check_attempts ) {

              $targets[(string)$host->attributes()->name][] =
                (string)$service->attributes()->name;
            }
          }
        }
    }

    return $this->_acknowledge( $targets, $comment, $notify, $auto_remove_comment );
  }

  public function acknowledgeHost( $host_name, $comment, $notify=true,
                                   $auto_remove_comment=true ) {

    return $this->_acknowledge( array( $host_name => array( null ) ), $comment, $notify,
      $auto_remove_comment );
  }

  public function acknowledgeService( $service_name, $host_name, $comment,
                                      $notify=true, $auto_remove_comment=true ) {

    return $this->_acknowledge( array( $host_name => array( $service_name ) ), $comment,
      $notify, $auto_remove_comment );
  }

  public function createHost( $attributes ) {
    $required_attributes = array( 'name', 'ip' );
    $xml_template = <<<'XML'
<opsview>
    <host action="create">
        %s
    </host>
</opsview>
XML;

    if( !self::_checkRequiredAttributes( $required_attributes, $attributes ) ) {
      throw RuntimeException( 'Missing required host attribute' );
    } else {
      return $this->_postXml( sprintf( $xml_template,
          $this->_arrayToXml( $attributes ) ) );
    }
  }

  public function cloneHost( $source_host, $attributes ) {
    $required_attributes = array( 'name', 'ip' );
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
    if( !self::_checkRequiredAttributes( $required_attributes, $attributes ) ) {
      throw RuntimeException( 'Missing required host attribute' );
    } else {
      return $this->_postXml( sprintf( $xml_template, $source_host,
          $this->_arrayToXml( $attributes ) ) );
    }
  }

  public function deleteHost( $host ) {
    $xml_template = <<<'XML'
<opsview>
    <host action="delete" by_%s="%s"/>
</opsview>
XML;

    return $this->_postXml( sprintf( $xml_template,
        (is_numeric( $host ) ? 'id' : 'name' ), $host ) );
  }

  public function scheduleDowntime( $hostgroup, $start_time, $end_time, $comment ) {
    if( is_null( $start_time ) ) {
      $start_time = self::_getFormattedTime();
      if( is_null( $end_time ) ) {
        $end_time = self::_getFormattedTime( time() + 3600 );
      }
    }
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

    return $this->_postXml( sprintf( $xml_template,
        (is_numeric( $hostgroup ) ? 'id' : 'name' ), $hostgroup, $start_time,
        $end_time, $comment ) );
  }

  public function disableScheduledDowntime( $hostgroup ) {
    $xml_template = <<<'XML'
<opsview>
    <hostgroup action="change" by_%s="%s">
        <downtime>disable</downtime>
    </hostgroup>
</opsview>
XML;

    return $this->_postXml( sprintf( $xml_template,
        (is_numeric( $hostgroup ) ? 'id' : 'name' ), $hostgroup ) );
  }

  public function enableNotifications( $hostgroup ) {
    $xml_template = <<<'XML'
<opsview>
    <hostgroup action="change" by_%s="%s">
        <notifications>enable</notifications>
    </hostgroup>
</opsview>
XML;

    return $this->_postXml( sprintf( $xml_template,
        (is_numeric( $hostgroup ) ? 'id' : 'name' ), $hostgroup ) );
  }

  public function disableNotifications( $hostgroup ) {
    $xml_template = <<<'XML'
<opsview>
    <hostgroup action="change" by_%s="%s">
        <notifications>disable</notifications>
    </hostgroup>
</opsview>
XML;

    return $this->_postXml( sprintf( $xml_template,
        (is_numeric( $hostgroup ) ? 'id' : 'name' ), $hostgroup ) );
  }

  public function reload() {
    $xml_template = <<<'XML'
<opsview>
    <system action="reload"/>
</opsview>
XML;
    return $this->_postXml( $xml_template );
  }

  /**
   * Logs into opsview if there isn't already an opsview auth cookie in the
   * cookiejar
   *
   * @return OpsviewRemote
   */
  protected function _login() {
    if( !$this->_connection->getCookieJar()->getCookie( $this->baseUrl,
        'auth_tkt', Zend_Http_CookieJar::COOKIE_OBJECT ) ) {

      $this->_connection->setUri( $this->baseUrl . self::URL_LOGIN );
      $this->_connection->setParameterPost( array(
        'login_username' => $this->username,
        'login_password' => $this->password,
        'back' => $this->baseUrl,
        'login' => 'Log In',
      ) );

      $this->_connection->request( Zend_Http_Client::POST );

      if( !$this->_connection->getCookieJar()->getCookie( $this->baseUrl,
          'auth_tkt', Zend_Http_CookieJar::COOKIE_OBJECT ) ) {
        throw new RuntimeException( 'Login failed' );
      }
    }

    return $this;
  }

  /**
   *
   * @param array $targets hosts and services to ack, arranged as:
   *                        array(host1 => array( service1, service2, null))
   *                        null means to acknowledge the host itself
   * @param string $comment acknowledgement comment
   * @param bool $notify send out notification of acknowledgement
   * @param bool $auto_remove_comment remove the comment after service recovers
   * @return bool response status, true if status is 200 (OK), false otherwise
   */
  protected function _acknowledge( $targets, $comment, $notify,
                                   $auto_remove_comment ) {

    $this->_login();
    $this->_connection->resetParameters()->
      setUri( $this->baseUrl . self::URL_ACK )->
      setRawData( self::_formatAckPostParameters( $targets, array(
          'from' => $this->baseUrl,
          'submit' => 'Submit',
          'comment' => $comment,
          'notify' => ($notify ? 'on' : 'off'),
          'autoremovecomment' => ($auto_remove_comment ? 'on' : 'off'),
        ) ) );

    $response = $this->_connection->request( Zend_Http_Client::POST );
    return $response->getStatus() == 200;
  }

  /**
   *
   * @param string $xml_string xml to send to opsview's api
   * @return string opsview's xml response or null if there was an error
   */
  protected function _postXml( $xml_string ) {
    $this->_login();
    $this->_connection->resetParameters()->
      setUri( $this->baseUrl . self::URL_API )->
      setHeaders( 'Content-Type', self::TYPE_XML )->
      setRawData( trim( $xml_string ) );
    $response = $this->_connection->request( Zend_Http_Client::POST );

    return ($response->getStatus() == 200 ? trim( $response->getBody() ) : null);
  }

  /**
   *
   * @param array $parameters request params as var name => value
   * @return string the formatted request parameters, suitable for get or post
   */
  protected static function _formatRequestParameters( $parameters ) {
    $params_parsed = array( );
    foreach( $parameters as $parameter => $value ) {
      if( is_array( $value ) ) {
        foreach( $value as $subvalue ) {
          $params_parsed[] = urlencode( $parameter ) . '=' .
            urlencode( $subvalue );
        }
      } else {
        $params_parsed[] = urlencode( $parameter ) . '=' .
          urlencode( $value );
      }
    }

    return implode( '&', $params_parsed );
  }

  /**
   *
   * @param array $targets array of ack targets
   * @param array $extra array of any additional post data
   * @return string the formatted post content
   */
  protected static function _formatAckPostParameters( $targets, $extra=null ) {
    $params_prepped = (is_array( $extra ) ? $extra : array( ));
    $params_prepped['host_selection'] = array( );
    $params_prepped['service_selection'] = array( );

    foreach( $targets as $host => $services ) {
      foreach( $services as $service ) {
        if( !$service ) {
          $params_prepped['host_selection'][] = $host;
        } else {
          $params_prepped['service_selection'][] = $host . ';' .
            $service;
        }
      }
    }

    return self::_formatRequestParameters( $params_prepped );
  }

  /**
   *
   * @param array $data array of attribute tags => values, nesting allowed
   * @return string the resultant xml
   */
  protected static function _arrayToXml( $data ) {
    $xml_string = '';
    foreach( $data as $tag => $content ) {
      if( is_array( $content ) ) {
        $xml_string .= "<${tag}>" . self::_arrayToXml( $content ) .
          "</${tag}>";
      } else {
        $xml_string .= "<${tag}>${content}</${tag}>";
      }
    }

    return $xml_string;
  }

  /**
   * @param array $required_attributes list of required attributes
   * @param array $attributes          list of attributes passed
   * @return bool true if every attribute in $required_attributes is found
   *                  in $attributes
   */
  protected static function _checkRequiredAttributes( $required_attributes,
                                                      $attributes ) {

    foreach( $required_attributes as $attribute ) {
      if( !array_key_exists( $attribute, $attributes ) ||
        is_null( $attributes[$attribute] ) ) {
        return false;
      }
    }

    return true;
  }

  private static function _getFormattedTime( $time = null ) {
    if( is_null( $time ) ) {
      return date( self::TIME_FORMAT );
    } else {
      return date( self::TIME_FORMAT, $time );
    }
  }
}