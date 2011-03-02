<?php

class Opsview_Node_Hostgroup
  extends Opsview_Node {

  protected $_childType   = 'Opsview_Node_Host';
  protected $_allowParent = true;
  protected $_xmlTagName  = 'data';
  protected $_jsonTagName = 'service';

  public function cancelScheduledDowntime() {
    $this->getRemote()->disableScheduledDowntime( $this->getName() );
  }

  public function getStatus( $filter = 0 ) {
    $this->getRemote()->getStatusHosts( $this->_attributes['hostgroup_id'] );
  }

  public function setScheduledDowntime( $comment, $start = null, $end = null) {
    $this->getRemote()->scheduleDowntime( $this->getName(), $start, $end, $comment );
  }

  public function setNotifications( $enabled = true ) {
    if( $enabled ) {
      $this->getRemote()->enableNotifications( $this->getName() );
    } else {
      $this->getRemote()->disableNotifications( $this->getName() );
    }
  }

  public function toXml() {}

  public function update( $filter = 0 ) {
    $this->parse( $this->getRemote()->getStatusHosts( $this->getName() ) );
  }
}