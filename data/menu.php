<?php
  /* INFO
    @product     : QuadBracesParser
    @component   : QuadBracesTagMenu
    @type        : class
    @description : Обработчик меню
    @revision    : 2016-01-10 18:30:00
  */

  if (!class_exists('QuadBracesDataPrototype',false)) require 'prototype.php';

  /* CLASS ~BEGIN */
  class QuadBracesDataMenu extends QuadBracesDataPrototype {
    public function main($key,$value,$args) {
      if (!is_array($value)) return strval($value);
      // Параметры обработки
      $lk = false; $lp = ''; $ml = 0;
      if (isset($args['langKeys']))   $lk = QuadBracesLib::bool($args['langKeys']);
      if (isset($args['langPrefix'])) $lp = $args['langPrefix'];
      if (isset($args['maxLevel']))   $ml = intval($args['maxLevel']);
      // Chunk type
      $tplt = 'chunk';
      if (isset($args['chunkType']))
        if (in_array($args['chunkType'],array('chunk','string','lib'))) $tplt = $args['chunkType'];
      // Item
      $tpli = '';
      if (isset($args['chunk'])) {
        $a = '&key=`[+key+]` &value=`[+value+]` &children=`[+children+]`';
        switch ($tplt) {
          case 'string': $tpli = '{['.$args['chunk']." [+arguments+] $a]}"; break;
          case 'lib'   : $tpli = '{('.$args['chunk']." [+arguments+] $a)}"; break;
          default      : $tpli = '{{'.$args['chunk']." [+arguments+] $a}}"; break;
        }
      }
      if (empty($tpli)) return '';
      // Wrapper
      $tplw = '';
      if (isset($args['outer'])) {
        $a = '&items=`[+items+]` &level=`[+level+]`';
        switch ($tplt) {
          case 'string': $tplw = '{['.$args['outer']." $a]}"; break;
          case 'lib'   : $tplw = '{('.$args['outer']." $a)}"; break;
          default      : $tplw = '{{'.$args['outer']." $a}}"; break;
        }
      }
      return $this->_menu_($value,$tpli,$tplw,$lk,$lp,1,$ml);
    }

    protected function _menu_($value,$tpli,$tplw,$lk,$lp,$level=1,$ml=0) {
      $v = array();
      foreach ($value as $key => $val) {
        $dk = $lk ? "[%".(!empty($lp) ? $lp."." : "")."$key%]" : $key;
        $dv = ''; $ch = array('','',''); $ar = array();
        if (is_array($val)) {
          foreach ($val as $vKey => $vVal) {
            $ar[] = "&".$vKey."=`$vVal`";
            if (is_array($vVal))
              if (in_array($vKey,array('before','children','after')))
                if (($ml == 0) || ($level < $ml)) {
                  $lf = (!empty($lp) ? $lp."." : "").$key;
                  $ck      = $vKey == 'children' ? 1 : ($vKey == 'before' ? 0 : 2);
                  $ch[$ck] = $this->_menu_($vVal,$tpli,$tplw,$lk,$lf,$level+1,$ml);
                }
          }
        } else { $dv = $val; }
        $v[] = str_replace(array(
          '[+arguments+]',
          '[+key+]',
          '[+value+]',
          '[+children+]'
        ),array(implode(' ',$ar),$dk,$dv,implode('',$ch)),$tpli);
      }
      $v = implode('',$v);
      $v = $this->owner->parse(str_replace(array(
        '[+items+]','[+level+]'
      ),array(
        $this->owner->parse($v),
        $level
      ),empty($tplw) ? '<ul>[+items+]</ul>' : $tplw));
      return $v;
    }
  }
  /* CLASS ~END */

  /* INFO @copyright: Xander Bass, 2016 */
?>