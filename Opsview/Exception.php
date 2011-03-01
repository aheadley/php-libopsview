<?php

class Opsview_Exception
  extends Exception {
  public function __construct( $message = null ) {
    parent::__construct( $message, null, null );
  }
}