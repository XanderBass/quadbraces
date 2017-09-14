<?php
  if (!class_exists("QuadBracesTagPrototype",false)) require 'prototype.php';

  class QuadBracesTagSnippet extends QuadBracesTagPrototype {
    protected $_name   = 'snippet';
    protected $_start  = '\[\!';
    protected $_finish = '\!\]';
    protected $cached = false;
    protected $_order  = 10;

    public function main(array $m,$key='') {
      $v = $this->_owner->execute($key,$this->args,'',$this->cached);
      $v = strval($v);
      return $v;
    }
  }
?>