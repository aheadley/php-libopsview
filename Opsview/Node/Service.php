<?php

class Opsview_Node_Service
  extends Opsview_Node {

  private static $_childType      = null;
  private static $_allowParent    = true;
  private static $_xmlTagName     = 'services';
  protected static $_jsonTagName  = 'services';

  public function acknowledge( $comment, $notify = true, $autoRemoveComment = true ) {
    $this->getRemote()->acknowledgeService( $this->getName(), $this->getParent()->getName(),
      $comment, $notify, $autoRemoveComment );
  }

  public function getStatus( $filter = 0 ) {
    return $this->getRemote()->getStatusService( $this->getName(),
                                                 $this->getParent()->getName() );
  }

  public function toXml() {}
}