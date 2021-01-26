<?php
// This file is part of Stateful
//
// Stateful is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Stateful is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Stateful.  If not, see <http://www.gnu.org/licenses/>.
declare(strict_types = 1);

/*
 * Class defintions for the PHP version of the PEGJS parser.
 * toString functions are mainly to document what the objects parts mean. But
 * you can do some debugging with them.
 * end of the file contains functions the parser uses...
 */

 class CTP_Node {
  public $position = null;
  public $mathmode = false;
  public function __construct(){
   $this->position = null;
  }
  public function getChildren() {
   return array();
  }
  public function toString($params=array()) {
   return "[NO TOSTRING FOR ".get_class($this)."]";
  }
  // Calls a function for all this nodes children.
  // Callback needs to take a node and return true if it changes nothing or does no structural changes
  // if it does structural changes it must return false so that the recursion may be repeated on
  // the changed structure
  public function callbackRecurse($function) {
    $children = $this->getChildren();
   for ($i = 0; $i < count($children); $i++) {
    // Not a foreach as the list may change.
    if ($function($children[$i]) !== true) {
     return false;
    }
    if ($children[$i]->callbackRecurse($function) !== true) {
     return false;
    }
   }
   return true;
  }
 }

 class CTP_Root extends CTP_Node {
  public $items = null;
  public function __construct($items) {
   parent::__construct();
   $this->items = $items;
  }
  public function getChildren() {
   return $this->items;
  }
  public function toString($params=array()) {
   $r = '';
   foreach ($this->items as $item)
    $r .= $item->toString($params);
   return $r;
 }
}


class CTP_IOBlock extends CTP_Node {
 public $channel = null;
 public $variable = null;
 public function __construct($channel, $variable) {
  parent::__construct();
  $this->channel = $channel;
  $this->variable = $variable;
 }
 public function toString($params=array()) {
  return "[[".$this->channel->toString($params).":".$this->variable->toString($params)."]]";
 }
}

class CTP_String extends CTP_Node {
 public $value = null;
 public $single = null;
 public function __construct($value, $single) {
  parent::__construct();
  $this->value = $value;
  $this->single = $single;
 }
 public function toString($params=array()) {
   if ($this->single) {
      return "'".str_replace("'","\\'",str_replace('\\','\\\\',$this->value))."'";
   }
   return '"'.str_replace('"','\\"',str_replace('\\','\\\\',$this->value)).'"';
 }
}

class CTP_Raw extends CTP_Node {
 public $value = null;
 public function __construct($value) {
  parent::__construct();
  $this->value = $value;
 }
 public function toString($params=array()) {
  return $this->value;
 }
}

class CTP_Block extends CTP_Node {
 public $name = null;
 public $parameters = null;
 public $contents = array();
 public function __construct($name, $parameters, $contents) {
  parent::__construct();
  $this->name = $name;
  $this->parameters = $parameters;
  $this->contents = $contents;
 }
 public function getChildren() {
    switch ($this->name) {
      case 'latex':
      case 'comment':
      case 'raw':
        return [];
      default:
        return $this->contents;
    }
 }
 public function toString($params=array()) {
   if ($this->name === 'comment' && array_key_exists('no comments', $params) && $params['no comments'] === true) {
    return '';
   }

   if ($this->name === 'latex') {
    return '{@' . $this->contents[0]->toString($params) . '@}';
   }
   if ($this->name === 'raw') {
    return '{#' . $this->contents[0]->toString($params) . '#}';
   }

   if ($this->name === 'if' && array_key_exists(' branch lengths', $this->parameters)) {
    // if-blocks use the parameters for more complex things for their branches. 
    // Note the space in front of the parameter name...
    $i = 0; // Total iterator
    $j = 0; // In block iterator
    $b = 0; // Branch iterator
    $r = '[[if test=' . $this->parameters['test'][$b]->toString($params) . ']]';
    while ($j < $this->parameters[' branch lengths'][$b]) {
     $r .= $this->contents[$i]->toString($params);
     $i = $i + 1;
     $j = $j + 1;
    }
    $j = 0;
    $b = $b + 1;

    while ($b < count($this->parameters['test'])) {
     $r .= '[[elif test=' . $this->parameters['test'][$b]->toString($params) . ']]';
     while ($j < $this->parameters[' branch lengths'][$b]) {
      $r .= $this->contents[$i]->toString($params);
      $i = $i + 1;
      $j = $j + 1;
     }
     $j = 0;
     $b = $b + 1;
    }

    if ($b < count($this->parameters[' branch lengths'])) {
     $r .= '[[else]]';
     while ($j < $this->parameters[' branch lengths'][$b]) {
      $r .= $this->contents[$i]->toString($params);
      $i = $i + 1;
      $j = $j + 1;
     }
    }

    $r .= '[[/if]]';
    return $r;
   }

   if ($this->name === 'define') {
    $r = '[[' . $this->name;
    foreach ($this->parameters as $param) {
     $r .= ' ' . $param['key'] . '=' . $param['value']->toString($params);
    }
    $r .= '/]]';
    return $r;
   }


   $r = '[[' . $this->name;
   foreach ($this->parameters as $key => $value) {
    $r .= ' ' . $key . '=' . $value->toString($params);
   }
   if (count($this->contents) === 0) {
    $r .= '/]]';
   } else {
    $r .= ']]';
    foreach ($this->contents as $value) {
     $r .= $value->toString($params);
    }
    $r .= '[[/' . $this->name . ']]';
   }

   return $r;
 }
}
