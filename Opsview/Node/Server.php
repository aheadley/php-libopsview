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

class Opsview_Node_Server
  extends Opsview_Node {

  private static $_allowParent    = false;
  private static $_childType      = 'Opsview_Node_Hostgroup';
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