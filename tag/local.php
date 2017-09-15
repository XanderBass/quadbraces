<?php
  if (!class_exists("QuadBracesTagPrototype",false)) require 'prototype.php';

  class QuadBracesTagLocal extends QuadBracesTagPrototype {
    protected $_name   = 'local';
    protected $_start  = '\[\+';
    protected $_rstart = '\+';
    protected $_finish = '\+\]';
    protected $_order  = 1;

    public function main(array $m,$key='') {
      $v = '';
      $qbo = $this->_owner;
      if (isset($qbo->arguments[$qbo->level-1][$key])) {
        $v = $qbo->arguments[$qbo->level-1][$key];
      } else { $this->_error = 'not found'; }
      return $v;
    }
  }
?>