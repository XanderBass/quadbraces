<?php
  if (!class_exists("QuadBracesTagPrototype",false)) require 'prototype.php';

  class QuadBracesTagSetting extends QuadBracesTagPrototype {
    protected $_name   = 'setting';
    protected $_start  = '\[\(';
    protected $_finish = '\)\]';
    protected $_order  = 6;

    public function main(array $m,$key='') { return $this->_owner->setting($key); }
  }
?>