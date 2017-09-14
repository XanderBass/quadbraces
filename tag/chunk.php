<?php
  if (!class_exists("QuadBracesTagPrototype",false)) require 'prototype.php';

  class QuadBracesTagChunk extends QuadBracesTagPrototype {
    protected $_name   = 'chunk';
    protected $_start  = '\{\{';
    protected $_finish = '\}\}';
    protected $_order  = 2;

    public function main(array $m,$key='') {
      $v = $this->_owner->getChunk($key,$this->_name);
      if ($v === false) {
        $this->_error = 'not found';
        return '';
      }
      return $v;
    }
  }
?>