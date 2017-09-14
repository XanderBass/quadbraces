<?php
  if (!class_exists("QuadBracesTagPrototype",false)) require 'prototype.php';

  class QuadBracesTagVariable extends QuadBracesTagPrototype {
    protected $_name   = 'variable';
    protected $_start  = '\[\*';
    protected $_finish = '\*\]';
    protected $_order  = 7;

    public function main(array $m,$key='') { return $this->_owner->variable($key); }
  }
?>