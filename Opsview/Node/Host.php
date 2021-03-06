<?php

class Opsview_Node_Host
  extends Opsview_Node {

  protected $_childType   = 'Opsview_Node_Service';
  protected $_allowParent = true;
  protected $_xmlTagName  = 'list';
  protected $_jsonTagName = 'list';

  public function acknowledge( $comment, $notify = true, $autoRemoveComment = true ) {
    $this->getRemote()->acknowledgeService( $this->getName(), $comment, $notify,
      $autoRemoveComment );
  }

  public function createClone( $newName, array $attributes ) {
    $this->getRemote()->cloneHost( $this->getName(), $attributes );
  }

  public function create( array $attributes ) {
    $attributes['name'] = $this->_attributes['name'];
    $attributes['name'] = $this->_attributes['ip'];
    $this->getRemote()->createHost( $attributes );
  }

  public function delete() {
    $this->getRemote()->deleteHost( $this->getName() );
  }

  public function getStatus( $filter = 0 ) {
    return $this->getRemote()->getStatusHost( $this->getName(), $filter );
  }

  public function toXml() {}
}