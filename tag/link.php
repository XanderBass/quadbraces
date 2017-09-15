<?php
  if (!class_exists("QuadBracesTagPrototype",false)) require 'prototype.php';

  class QuadBracesTagLink extends QuadBracesTagPrototype {
    protected $_name   = 'link';
    protected $_start  = '\[\~';
    protected $_rstart = '\~';
    protected $_finish = '\~\]';
    protected $_order  = 9;

    public function main(array $m,$key='') {
      $R = $this->_owner->resources;
      $I = intval($key);
      if (empty($I)) return '';
      if (empty($R[$I])) {
        $this->owner->invoke('resourceNotFound',$I);
        return '';
      }
      $_ = array();
      $A = array();
      while (!empty($R[$I]) && ($I != 0)) {
        $A[] = $I;
        $_[] = empty($R[$I]['alias'])  ? $R[$I]['alias']          : $I;
        $I   = empty($R[$I]['parent']) ? intval($R[$I]['parent']) : 0;
        if (in_array($I,$A)) {
          $this->owner->invoke('resourceCycled',$I);
          return '';
        }
      }
      $v = implode('/',$_);
      if ($this->_owner->SEOStrict)
        $v.= isset($this->_owner->idx[$key]) ? '/' : '.html';
      return $v;
    }
  }
?>