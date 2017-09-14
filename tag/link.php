<?php
  if (!class_exists("QuadBracesTagPrototype",false)) require 'prototype.php';

  class QuadBracesTagLink extends QuadBracesTagPrototype {
    protected $_name   = 'link';
    protected $_start  = '\[\~';
    protected $_finish = '\~\]';
    protected $_order  = 9;

    public function main(array $m,$key='') {
      $v = '';
      $R = $this->_owner->resources;
      if (!is_null($R)) {
        $_ = array();
        $I = intval($key);
        $A = 0;
        while (isset($R[$I])) {
          $A[] = $I;
          $_[] = isset($R[$I]['alias'])  ? $R[$I]['alias']          : $I;
          $I   = isset($R[$I]['parent']) ? intval($R[$I]['parent']) : 0;
          if (($I == 0) || (in_array($I,$A))) break;
        }
        $v = implode('/',$_);
        if ($this->_owner->SEOStrict)
          $v.= isset($this->_owner->idx[$key]) ? '/' : '.html';
      }
      return $v;
    }
  }
?>