<?php
  if (!class_exists("QuadBracesTagPrototype",false)) require 'prototype.php';

  class QuadBracesTagConstant extends QuadBracesTagPrototype {
    protected $_name   = 'constant';
    protected $_start  = '\{\*';
    protected $_rstart = '\/';
    protected $_finish = '\*\}';
    protected $_order  = 5;

    public function main(array $m,$key='') {
      $v = '';
      if (empty($key) || !defined($key)) {
        $this->_error = 'not found';
      } else { $v = constant($key); }
      return $v;
    }
  }
?>