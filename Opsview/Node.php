<?php

abstract class Opsview_Node
  implements ArrayAccess {
  
  abstract private static $_childType;
  abstract private static $_allowParent;
  abstract protected static $_xmlTagName;
  abstract protected static $_jsonTagName;

  private $_parent      = null;
  private $_children    = array();
  private $_attributes  = array();
  private $_name        = null;

  public function __construct( $name, Opsview_Node $parent = null ) {
    $this->_name = $name;
    if( !is_null( $parent ) ) {
      $this->setParent( $parent );
    }
  }

  public function __toString() {
    return $this->getName();
  }

  public function getName() {
    return $this->_attributes['name'];
  }

  public function hasChild( Opsview_Node $child ) {
    try {
      $this->_childrenAllowed();
    } catch( Opsview_Node_Exception $e ) {
      return false;
    }
    return in_array( $child, $this->_children );
  }

  public function getChild( $offset ) {
    return $this->_children[$offset];
  }

  public function getChildren() {
    return new ArrayIterator( $this->_children );
  }

  public function addChild( Opsview_Node $child ) {
    $this->_childrenAllowed();
    if( !$this->hasChild( $child ) ) {
      $child->setParent( $this );
      $this->_children[] = $child;
    }
  }

  public function removeChild( Opsview_Node $child ) {
    $this->_childrenAllowed();
    if( $this->hasChild( $child ) ) {
      //search array and remove
    }
  }

  public function getParent() {
    $this->_parentAllowed();
    return $this->_parent;
  }

  public function setParent( Opsview_Node $parent ) {
    $this->_parentAllowed();
    $this->_parent = $parent;
  }

  public function getRemote() {
    return $this->getParent()->getRemote();
  }

  abstract public function getStatus( $filter );

  public function toJson() {
    $object = $this->_attributes;
    if( !is_null( self::$_childType ) ) {
      $childType = self::$_childType;
      $object[$childType::_jsonTagName] = array();
      foreach( $this->getChildren() as $child ) {
        //this won't work, it's going to give an array of strings of json objects
        $object[$childType::_jsonTagName][] = $child->toJson();
      }
    }
    return Zend_Json::encode( $object );
  }

  abstract public function toXml();

  public function parse( $data ) {
    $parsedData = null;
    $remote = $this->getRemote();
    if( !is_null( $remote ) ) {
      switch( $remote->getContentType() ) {
        case Opsview_Remote::TYPE_JSON:
          $parsedData = $this->parseJson( $data );
          break;
        case Opsview_Remote::TYPE_XML:
          $parsedData = $this->parseXml( $data );
          break;
        default:
          throw new Opsview_Node_Exception( 'Got unrecognized content type from remote' );
      }
    } else {
      try {
        $parsedData = $this->parseJson( $data );
        $type = Opsview_Remote::TYPE_JSON;
      } catch( Opsview_Node_Exception $e ) {
        try {
          $parsedData = $this->parseXml( $data );
          $type = Opsview_Remote::TYPE_XML;
        } catch( Opsview_Node_Exception $e ) {
          throw new Opsview_Node_Exception( 'Unable to determine data format' );
        }
      }
    }
    return $parsedData;
  }

  /* ArrayAccess methods */
  public function offsetGet( $offset ) {
    return isset( $this->_attributes[$offset] ) ? $this->_attributes[$offset] : null;
  }

  public function offsetSet( $offset, $value ) {
    if( is_null( $offset ) ) {
      $this->_attributes[] = $value;
    } else {
      $this->_attributes[$offset] = $value;
    }
  }

  public function offsetExists( $offset ) {
    return isset( $this->_attributes[$offset] );
  }

  public function offsetUnset( $offset ) {
    unset( $this->_attributes[$offset] );
  }
  /* end ArrayAccess methods */
  
  public function parseJson( $data ) {
    if( is_string( $data ) ) {
      $node = Zend_Json::decode( $data );
    } else {
      $node = $data;
    }
    if( isset( $node[self::$_jsonTagName] ) ) {
      /* if the tag name we're looking for isn't in the data we're given, we'll
       * just assume that the root node is the tag were looking for
       */
      $node = $node[self::$_jsonTagName];
    }
    $this->_attributes = array();
    foreach( array_filter( $node, 'is_string' ) as $attr => $value ) {
      $this->_attributes[$attr] = $value;
    }
    if( !is_null( self::$_childType ) ) {
      $this->_children = array();
      $childType = self::$_childType;
      foreach( $node[$childType::_jsonTagName] as $child ) {
        $newChild = new $childType( $child['name'], $this );
        $newChild->parseJson( $child );
        $this->addChild( $newChild );
      }
    }
  }
  
  public function parseXml( $data ) {
    if( is_string( $data ) ) {
      $node = current( simplexml_load_string( $data )->xpath( self::$_xmlTagName ) );
    } else {
      $node = $data;
    }
    $this->_attributes = array();
    foreach( $node->attributes() as $attr => $value ) {
      $this->_attributes[$attr] = $value;
    }
    if( !is_null( self::$_childType ) ) {
      $this->_children = array();
      $childType = self::$_childType;
      foreach( $node->children() as $child ) {
        $newChild = new $childType( $child->attributes()->name, $this );
        $newChild->parseXml( $child->asXML() );
        $this->addChild( $newChild );
      }
    }
  }
  
  public function update( $filter = 0 ) {
    $this->parse( $this->getStatus( $filter ) );
  }
  
  private function _childrenAllowed() {
    if( is_null( self::$_childType ) ) {
      throw new Opsview_Node_Exception( 'Node cannot have children' );
    }
  }

  private function _parentAllowed() {
    if( !self::$_allowParent ) {
      throw new Opsview_Node_Exception( 'Node cannot have a parent' );
    }
  }
}