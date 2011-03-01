<?php
/**
 * Nexcess.net Toolkit
 *
 * <pre>
 * +----------------------------------------------------------------------+
 * | Nexcess.net Toolkit                                                  |
 * +----------------------------------------------------------------------+
 * | Copyright (c) 2006-2010 Nexcess.net L.L.C., All Rights Reserved.     |
 * +----------------------------------------------------------------------+
 * | Redistribution and use in source form, with or without modification  |
 * | is NOT permitted without consent from the copyright holder.          |
 * |                                                                      |
 * | THIS SOFTWARE IS PROVIDED BY THE AUTHOR AND CONTRIBUTORS "AS IS" AND |
 * | ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO,    |
 * | THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A          |
 * | PARTICULAR PURPOSE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL,    |
 * | EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO,  |
 * | PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR   |
 * | PROFITS; OF BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY  |
 * | OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT         |
 * | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE    |
 * | USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH     |
 * | DAMAGE.                                                              |
 * +----------------------------------------------------------------------+
 * </pre>
 */

class Opsview_Node_Host
  extends Opsview_Node {

  private static $_allowParent    = true;
  private static $_childType      = 'Opsview_Node_Service';
  private static $_xmlTagName     = 'list';
  private static $_jsonTagName    = 'list';

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