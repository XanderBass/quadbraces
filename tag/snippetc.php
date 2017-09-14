<?php
  if (!class_exists("QuadBracesTagPrototype",false)) require 'prototype.php';

  class QuadBracesTagSnippetc extends QuadBracesTagPrototype {
    protected $_name   = 'snippetc';
    protected $_start  = '\[\[';
    protected $_finish = '\]\]';
    protected $cached  = true;
    protected $_order  = 11;
  }
?>