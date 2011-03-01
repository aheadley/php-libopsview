<?php

class Opsview_Node_Server
  extends Opsview_Node {

  private static $_allowParent    = false;
  private static $_childType      = 'Opsview_Node_Host';
  private static $_xmlTagName     = 'data';
  private static $_jsonTagName    = 'service';

  private $_remote    = null;
  private $_url       = null;
  private $_username  = null;
  private $_password  = null;

  public function __construct( $url, $username, $password ) {
    parent::__construct( null );
    $this->_url       = $url;
    $this->_username  = $username;
    $this->_password  = $password;
    $this->_remote    = new Opsview_Remote( $this->_url, $this->_username, $this->_password );
  }

  public function acknowledge( $comment, $notify = true, $autoRemoveComment = true ) {
    $this->getRemote()->acknowledgeAll( $comment, $notify, $autoRemoveComment );
  }

  public function getName() {
    return $this->_url;
  }

  public function getRemote() {
    return $this->_remote;
  }

  public function getStatus( $filter = 0 ) {
    return $this->getRemote()->getStatusAll( $filter );
  }

  public function reload() {
    $this->getRemote()->reload();
  }

  public function toXml() {}
}