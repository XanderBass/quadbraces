<?php
  if (!class_exists("QuadBracesTagPrototype",false)) require 'prototype.php';

  class QuadBracesTagDebug extends QuadBracesTagPrototype {
    protected $_name   = 'debug';
    protected $_start  = '\[\^';
    protected $_rstart = '\^';
    protected $_finish = '\^\]';
    protected $_order  = 8;

    public function main(array $m,$key='') {
      $v = '';
      $at = array('ms','us','ns');
      $am = array('kb','mb','gb');
      $dd = explode('.',$key);
      $un = count($dd) > 1 ? $dd[count($dd) - 1] : '';
      if (!empty($un)) {
        if (in_array($un,$at) || in_array($un,$am)) {
          unset($dd[count($dd) - 1]);
        } else { $un = ''; }
      }
      $mt = '';
      if ((count($dd) > 1) && in_array($dd[count($dd)-1],array('time','ram'))) {
        $mt = $dd[count($dd)-1];
        unset($dd[count($dd)-1]);
      }
      $key = implode('.',$dd);
      $qbo = $this->_owner;
      $P = strtoupper($qbo->prefix);
      switch ($key) {
        case 'time':
          $K = $qbo->debugPoint(array(
            'type' => 'time',
            'key'  => 'current',
            'value' => microtime(true) - $qbo->startTime,
            'unit'  => (in_array($un,$at) ? $un : '')
          ));
          $v = "<!-- $P:$K -->";
          break;
        case 'ram':
          $K = $qbo->debugPoint(array(
            'type' => 'ram',
            'key'  => 'current',
            'value' => memory_get_usage() - $qbo->startRAM,
            'unit'  => (in_array($un,$am) ? $un : '')
          ));
          $v = "<!-- $P:$K -->";
          break;
        case 'parser':
        case 'total' :
          if (!empty($mt)) {
            $K = $qbo->debugPoint(array(
              'type'  => $mt,
              'key'   => $key,
              'unit'  => (in_array($un,($mt == 'ram' ? $am : $at)) ? $un : '')
            ));
            $v = "<!-- $P:$K -->";
          }
          break;
        case 'db.count':
          $v = "<!-- ".strtoupper($qbo->prefix.":db.count")." -->";
          break;
        default: $v = '';
      }
      if (empty($v)) {
        if (!isset($qbo->debug[$key])) {
          $this->_error = 'not found';
        } else { $v = $qbo->debug[$key]; }
      }
      return $v;
    }
  }
?>