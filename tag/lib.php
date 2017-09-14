<?php
  if (!class_exists("QuadBracesTagPrototype",false)) require 'prototype.php';

  class QuadBracesTagLib extends QuadBracesTagChunk {
    protected $_name   = 'lib';
    protected $_start  = '\{\(';
    protected $_finish = '\)\}';
    protected $_order  = 4;
  }
?>