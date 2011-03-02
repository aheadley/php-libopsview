<?php

abstract class Opsview_Node
  implements ArrayAccess {

  protected $_childType      = null;
  protected $_allowParent    = null;
  protected $_xmlTagName   = null;
  protected $_jsonTagName  = null;
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
    return in_array( $child, $this->_children, true );
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
      //TODO: search array and remove
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
    if( !is_null( $this->_childType ) ) {
      $childType = $this->_childType;
      $object[${$this->_childType}->_jsonTagName] = array();
      foreach( $this->getChildren() as $child ) {
        //TODO: this won't work, it's going to give an array of strings of json objects
        $object[${$this->_childType}->_jsonTagName][] = $child->toJson();
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

  public function parseJson( $data ) {
    if( is_string( $data ) ) {
      $node = Zend_Json::decode( $data );
    } else {
      $node = $data;
    }
    if( isset( $node[$this->_jsonTagName] ) ) {
      /* if the tag name we're looking for isn't in the data we're given, we'll
       * just assume that the root node is the tag were looking for
       */
      $node = $node[$this->_jsonTagName];
    }
    $this->_attributes = array();
    foreach( array_filter( $node, 'is_string' ) as $attr => $value ) {
      $this->_attributes[$attr] = $value;
    }
    if( !is_null( $this->_childType ) ) {
      $this->_children = array();
      //TODO: this won't work, can't get the child class's json tag name
      foreach( $node[$this->_childType->_jsonTagName] as $child ) {
        $newChild = new $this->_childType( $child['name'], $this );
        $newChild->parseJson( $child );
        $this->addChild( $newChild );
      }
    }
  }

  public function parseXml( $data ) {
    if( is_string( $data ) ) {
      $node = current( simplexml_load_string( $data )->xpath( $this->_xmlTagName ) );
    } else {
      $node = $data;
    }
    $this->_attributes = array();
    foreach( $node->attributes() as $attr => $value ) {
      $this->_attributes[$attr] = $value;
    }
    if( !is_null( $this->_childType ) ) {
      $this->_children = array();
      //TODO: same problem as parseJson, should also maybe use xpath query to get children
      foreach( $node->children() as $child ) {
        $newChild = new $this->_childType( $child->attributes()->name, $this );
        $newChild->parseXml( $child );
        $this->addChild( $newChild );
      }
    }
  }

  public function update( $filter = 0 ) {
    var_dump( $this->_xmlTagName );
    $this->parse( $this->getStatus( $filter ) );
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

  private function _childrenAllowed() {
    if( is_null( $this->_childType ) ) {
      throw new Opsview_Node_Exception( 'Node cannot have children' );
    }
  }

  private function _parentAllowed() {
    if( !$this->_allowParent ) {
      throw new Opsview_Node_Exception( 'Node cannot have a parent' );
    }
  }
}