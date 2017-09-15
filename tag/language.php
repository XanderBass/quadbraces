<?php
  if (!class_exists("QuadBracesTagPrototype",false)) require 'prototype.php';

  class QuadBracesTagLanguage extends QuadBracesTagPrototype {
    protected $_name   = 'language';
    protected $_start  = '\[\%';
    protected $_rstart = '\%';
    protected $_finish = '\%\]';
    protected $_order  = 9;

    public function main(array $m,$key='') {
      $P = 'caption';
      $K = explode('.',$key);
      if (count($K) > 1) if (in_array($K[count($K) - 1],array(
        'caption','description','placeholder'
      ))) {
        $P = $K[count($K) - 1];
        unset($K[count($K) - 1]);
      }
      $K = implode('.',$K);
      if (isset($this->_owner->dictionary[$K][$P]))
        return $this->_owner->dictionary[$K][$P];
      return ucfirst(str_replace(array('.','_','-'),' ',$K));
    }
  }
?>