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
  private $_contentType = null;

  /**
   *
   * @param string $baseUrl url to base opsview location, i.e.
   *                          https://www.example.com/opsview/
   * @param string $username username to login to opsview
   * @param string $password password for that user
   * @param string $contentType content type to get back from opsview, only
   *                              applies to status requests, api requests are
   *                              always in xml
   */
  public function __construct( $baseUrl, $username, $password,
                                $contentType = null ) {

    $this->_baseUrl     = $baseUrl;
    $this->_username    = $username;
    $this->_password    = $password;
    $this->_contentType = $contentType == self::TYPE_JSON ?
                            self::TYPE_JSON : self::TYPE_XML;
    $this->_connection = new Zend_Http_Client(
        $this->_baseUrl,
        array(
          'strictredirects' => true,
          'timeout' => (int)ini_get( 'default_socket_timeout' ),
          'keepalive' => true ) );
    $this->_connection->setCookieJar();
  }

  public function getContentType() {
    return $this->_contentType;
  }

  /**
   *
   * @param int $statusMask mask results to certain status, multiple statuses
   *                          can be combined with bitwise OR ("|")
   * @param bool $unhandled limit results to unhandled problems only
   * @return bool see _acknowledge()
   */
  public function getStatusAll( $statusMask=0, $unhandled=false ) {
    $this->_login();
    $get_params = array( 'state' => array() );
    for( $i = 0; $i < 4; $i++ ) {
      if( (pow( 2, $i ) & $statusMask ) == pow( 2, $i ) ) {
        $get_params['state'][] = $i;
      }
    }
    if( $unhandled ) {
      $get_params['filter'] = 'unhandled';
    }
    $this->_connection->resetParameters()->
      setUri( $this->_baseUrl . self::URL_STATUS . '?' .
        self::_formatRequestParameters( $get_params ) )->
      setHeaders( 'Content-Type', $this->_contentType );
    $response = $this->_connection->request( Zend_Http_Client::GET );

    return $response->getStatus() == 200 ? trim( $response->getBody() ) : null;
  }

  public function getStatusHost( $host_name, $statusMask=0, $unhandled=false ) {
    $this->_login();
    $get_params = array( 'state' => array( ), 'host' => $host_name );
    for( $i = 0; $i < 4; $i++ ) {
      if( (pow( 2, $i ) & $statusMask) == pow( 2, $i ) ) {
        $get_params['state'][] = $i;
      }
    }
    if( $unhandled ) {
      $get_params['filter'] = 'unhandled';
    }
    $this->_connection->resetParameters()->
      setUri( $this->_baseUrl . self::URL_STATUS . '?' .
        self::_formatRequestParameters( $get_params ) )->
      setHeaders( 'Content-Type', $this->_contentType );
    $response = $this->_connection->request( Zend_Http_Client::GET );

    return ($response->getStatus() == 200 ? trim( $response->getBody() ) : null);
  }

  public function getStatusHosts( $hostgroup, $statusMask=0, $unhandled = false ) {

    $this->_login();
    $get_params = array( 'state' => array( ), 'hostgroupid' => $hostgroup );
    for( $i = 0; $i < 4; $i++ ) {
      if( (pow( 2, $i ) & $statusMask) == pow( 2, $i ) ) {
        $get_params['state'][] = $i;
      }
    }
    if( $unhandled ) {
      $get_params['filter'] = 'unhandled';
    }
    $this->_connection->resetParameters()->
      setUri( $this->_baseUrl . self::URL_STATUS . '?' .
        self::_formatRequestParameters( $get_params ) )->
      setHeaders( 'Content-Type', $this->_contentType );
    $response = $this->_connection->request( Zend_Http_Client::GET );

    return ($response->getStatus() == 200 ? trim( $response->getBody() ) : null);
  }

  public function getStatusService( $host, $service ) {
    $hostStatus = $this->getStatusHost( $host );
    switch( $this->_contentType ) {
      case self::TYPE_JSON:
        foreach( $hostStatus['data']['list']['services'] as $serviceNode ) {
          if( strtolower( $serviceNode['name'] ) == strtolower( $service ) ) {
            return Zend_Json::encode( $serviceNode );
          }
        }
        break;
      case self::TYPE_XML:
      default:
        foreach( simplexml_load_string( $hostStatus )->data->list->services as $serviceNode ) {
          if( strtolower( (string)$serviceNode->attributes()->name ) ==
            strtolower( $service ) ) {
            return $serviceNode->asXML();
          }
        }
    }
  }

  public function getStatusHostgroup( $hostgroup = null ) {
    $this->_login();
    $this->_connection->resetParameters()->
      setHeaders( 'Content-Type', $this->_contentType );
    if( is_numeric( $hostgroup ) || empty( $hostgroup ) ) {
      $this->_connection->setUri( $this->_baseUrl . self::URL_STATUS_HOSTGROUP . '/' . $hostgroup );
    } else {
      $this->_connection->setUri( $this->_baseUrl . self::URL_STATUS_HOSTGROUP )->
      setParameterGet( 'by_name', $hostgroup );
    }
    return $this->_doRequest( Zend_Http_Client::GET );
  }

  public function acknowledgeAll( $comment, $notify = true,
                                  $autoRemoveComment = true ) {

    $status = $this->getStatusAll(
        self::STATE_WARNING | self::STATE_CRITICAL, true );
    $targets = array( );
    switch( $this->_contentType ) {
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
    return $this->_acknowledge( $targets, $comment, $notify, $autoRemoveComment );
  }

  public function acknowledgeHost( $host, $comment, $notify = true,
                                    $autoRemoveComment = true ) {

    return $this->_acknowledge( array( $host => array( null ) ), $comment, $notify,
      $autoRemoveComment );
  }

  public function acknowledgeService( $service, $host, $comment,
                                        $notify = true, $autoRemoveComment = true ) {

    return $this->_acknowledge( array( $host => array( $service ) ), $comment,
      $notify, $autoRemoveComment );
  }

  public function createHost( $attributes ) {
    $requiredAttributes = array( 'name', 'ip' );
    $xmlTemplate = <<<'XML'
<opsview>
    <host action="create">
        %s
    </host>
</opsview>
XML;
    self::_checkRequiredAttributes( $requiredAttributes, $attributes );
    return $this->_postXml( sprintf( $xmlTemplate, self::_arrayToXml( $attributes ) ) );
  }

  public function cloneHost( $sourceHost, $attributes ) {
    $requiredAttributes = array( 'name', 'ip' );
    $xmlTemplate = <<<'XML'
<opsview>
    <host action="create">
        <clone>
            <name>%s</name>
        </clone>
        %s
    </host>
</opsview>
XML;
    self::_checkRequiredAttributes( $requiredAttributes, $attributes );
    return $this->_postXml( sprintf( $xmlTemplate, $sourceHost,
        self::_arrayToXml( $attributes ) ) );
  }

  public function deleteHost( $host ) {
    $xmlTemplate = <<<'XML'
<opsview>
    <host action="delete" by_%s="%s"/>
</opsview>
XML;
    return $this->_postXml( sprintf( $xmlTemplate,
        (is_numeric( $host ) ? 'id' : 'name' ), $host ) );
  }

  public function scheduleDowntime( $hostgroup, $start, $end, $comment ) {
    if( is_null( $start ) ) {
      $start = self::_getFormattedTime();
      if( is_null( $end ) ) {
        $end = self::_getFormattedTime( time() + 3600 );
      }
    }
    $xmlTemplate = <<<'XML'
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
    return $this->_postXml( sprintf( $xmlTemplate,
        is_numeric( $hostgroup ) ? 'id' : 'name', $hostgroup, $start,
        $end, $comment ) );
  }

  public function disableScheduledDowntime( $hostgroup ) {
    $xmlTemplate = <<<'XML'
<opsview>
    <hostgroup action="change" by_%s="%s">
        <downtime>disable</downtime>
    </hostgroup>
</opsview>
XML;
    return $this->_postXml( sprintf( $xmlTemplate,
        is_numeric( $hostgroup ) ? 'id' : 'name', $hostgroup ) );
  }

  public function enableNotifications( $hostgroup ) {
    $xmlTemplate = <<<'XML'
<opsview>
    <hostgroup action="change" by_%s="%s">
        <notifications>enable</notifications>
    </hostgroup>
</opsview>
XML;
    return $this->_postXml( sprintf( $xmlTemplate,
        is_numeric( $hostgroup ) ? 'id' : 'name', $hostgroup ) );
  }

  public function disableNotifications( $hostgroup ) {
    $xmlTemplate = <<<'XML'
<opsview>
    <hostgroup action="change" by_%s="%s">
        <notifications>disable</notifications>
    </hostgroup>
</opsview>
XML;
    return $this->_postXml( sprintf( $xmlTemplate,
        is_numeric( $hostgroup ) ? 'id' : 'name', $hostgroup ) );
  }

  public function reload() {
    $xmlTemplate = <<<'XML'
<opsview>
    <system action="reload"/>
</opsview>
XML;
    return $this->_postXml( $xmlTemplate );
  }

  /**
   * Logs into opsview if there isn't already an opsview auth cookie in the
   * cookiejar
   *
   * @return OpsviewRemote
   */
  private function _login() {
    if( !$this->_connection->getCookieJar()->getCookie( $this->_baseUrl,
        'auth_tkt', Zend_Http_CookieJar::COOKIE_OBJECT ) ) {
      $this->_connection->setUri( $this->_baseUrl . self::URL_LOGIN );
      $this->_connection->setParameterPost( array(
        'login_username' => $this->_username,
        'login_password' => $this->_password,
        'back' => $this->_baseUrl,
        'login' => 'Log In' ) );
      $this->_doRequest( Zend_Http_Client::POST );
      if( !$this->_connection->getCookieJar()->getCookie( $this->_baseUrl,
          'auth_tkt', Zend_Http_CookieJar::COOKIE_OBJECT ) ) {
        throw new Opsview_Remote_Exception( 'Login failed for unknown reason' );
      }
    }
  }

  /**
   *
   * @param array $targets hosts and services to ack, arranged as:
   *                        array(host1 => array( service1, service2, null))
   *                        null means to acknowledge the host itself
   * @param string $comment acknowledgement comment
   * @param bool $notify send out notification of acknowledgement
   * @param bool $autoRemoveComment remove the comment after service recovers
   * @return bool response status, true if status is 200 (OK), false otherwise
   */
  private function _acknowledge( array $targets, $comment, $notify,
                                   $autoRemoveComment ) {

    $this->_login();
    $this->_connection->resetParameters()->
      setUri( $this->_baseUrl . self::URL_ACK )->
      setRawData( self::_formatAckPostParameters( $targets, array(
          'from' => $this->_baseUrl,
          'submit' => 'Submit',
          'comment' => $comment,
          'notify' => ($notify ? 'on' : 'off'),
          'autoremovecomment' => $autoRemoveComment ? 'on' : 'off' ) ) );
    try {
      $this->_doRequest( Zend_Http_Client::POST );
    } catch( Opsview_Remote_HttpException $e ) {
      return false;
    }
    return true;
  }

  private function _doRequest( $method ) {
    $response = $this->_connection->request( $method );
    if( $response->getStatus() == 200 ) {
      return trim( $response->getBody() );
    } else {
      throw new Opsview_Remote_HttpException( $response->getMessage(), $reponse->getStatus() );
    }
  }

  /**
   *
   * @param string $xmlString xml to send to opsview's api
   * @return string opsview's xml response or null if there was an error
   */
  private function _postXml( $xmlString ) {
    $this->_login();
    $this->_connection->resetParameters()->
      setUri( $this->_baseUrl . self::URL_API )->
      setHeaders( 'Content-Type', self::TYPE_XML )->
      setRawData( trim( $xmlString ) );
    return $this->_doRequest( Zend_Http_Client::POST );
  }

  /**
   *
   * @param array $parameters request params as var name => value
   * @return string the formatted request parameters, suitable for get or post
   */
  private static function _formatRequestParameters( array $parameters ) {
    $paramsParsed = array();
    foreach( $parameters as $parameter => $value ) {
      if( is_array( $value ) ) {
        foreach( $value as $subvalue ) {
          $paramsParsed[] = urlencode( $parameter ) . '=' .
            urlencode( $subvalue );
        }
      } else {
        $paramsParsed[] = urlencode( $parameter ) . '=' .
          urlencode( $value );
      }
    }
    return implode( '&', $paramsParsed );
  }

  /**
   *
   * @param array $targets array of ack targets
   * @param array $params array of any additional post data
   * @return string the formatted post content
   */
  private static function _formatAckPostParameters( array $targets,
                                                       array $params = array() ) {
    $params['host_selection'] = array();
    $params['service_selection'] = array();
    foreach( $targets as $host => $services ) {
      foreach( $services as $service ) {
        if( !$service ) {
          $params['host_selection'][] = $host;
        } else {
          $params['service_selection'][] = $host . ';' . $service;
        }
      }
    }
    return self::_formatRequestParameters( $params );
  }

  /**
   *
   * @param array $data array of attribute tags => values, nesting allowed
   * @return string the resultant xml
   */
  private static function _arrayToXml( array $data ) {
    $xmlString = '';
    foreach( $data as $tag => $content ) {
      $xmlString .= sprintf( '<%s>%s</%s>', $tag,
        is_array( $content ) ? self::_arrayToXml( $content ) : $content, $tag );
    }
    return $xmlString;
  }

  /**
   * @param array $requiredAttributes list of required attributes
   * @param array $attributes          list of attributes passed
   * @return bool true if every attribute in $required_attributes is found
   *                  in $attributes
   */
  private static function _checkRequiredAttributes( array $requiredAttributes,
                                                      array $attributes ) {

    $missingAttributes = array();
    foreach( $requiredAttributes as $attribute ) {
      if( !array_key_exists( $attribute, $attributes ) ||
        is_null( $attributes[$attribute] ) ) {
        $missingAttributes[] = $attribute;
      }
    }
    if( empty( $missingAttributes ) ) {
      return true;
    } else {
      throw new Opsview_Remote_Exception( 'Missing required attributes: ' .
        implode( ', ', $missingAttributes ) );
    }
  }

  private static function _getFormattedTime( $time = null ) {
    if( is_null( $time ) ) {
      return date( self::TIME_FORMAT );
    } else {
      return date( self::TIME_FORMAT, $time );
    }
  }
}