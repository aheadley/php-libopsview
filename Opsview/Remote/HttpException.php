<?php

class Opsview_Remote_HttpException
  extends Opsview_Remote_Exception {

  public function __construct( $message = null, $code = null ) {
    parent::__construct( $message, $code );
  }
}