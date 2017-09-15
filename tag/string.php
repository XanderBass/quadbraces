<?php
  if (!class_exists("QuadBracesTagPrototype",false)) require 'prototype.php';

  class QuadBracesTagString extends QuadBracesTagChunk {
    protected $_name   = 'string';
    protected $_start  = '\{\[';
    protected $_rstart = '\-';
    protected $_finish = '\]\}';
    protected $_order  = 3;
  }
?>