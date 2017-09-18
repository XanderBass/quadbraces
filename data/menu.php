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
      $rm = $this->owner->MODXRevoMode;
      $ml = isset($args['maxLevel']) ? intval($args['maxLevel']) : 0;
      // Chunk type
      $TT = 'chunk';
      if (isset($args['chunkType']))
        if (in_array($args['chunkType'],array('string','lib'))) $TT = $args['chunkType'];
      switch ($TT) {
        case 'string': $bs = $rm ? '[[-' : '{['; $be = $rm ? ']]' : ']}'; break;
        case 'lib'   : $bs = $rm ? '[[=' : '{('; $be = $rm ? ']]' : ')}'; break;
        default      : $bs = $rm ? '[[$' : '{{'; $be = $rm ? ']]' : '}}'; break;
      }
      $pb = $rm ? '[[+' : '[+';
      $pe = $rm ? ']]'  : '+]';
      // Item
      $TI = <<<html
<li><a href="{$pb}url{$pe}">{$pb}title{$pe}</a>{$pb}children{$pe}</li>
html;
      if (isset($args['item'])) $TI = "{$bs}{$args['item']} [+args+]{$be}";
      // Wrapper
      $TW = <<<html
<ul class="level-[+level+]">
[+items+]
</ul>
html;
      if (isset($args['outer']))
        $TW = "{$bs}{$args['outer']}"
            . " &items=`[+items+]` &level=`[+level+]`"
            . "{$be}";
      return $this->_menu_($value,$TI,$TW,1,$ml);
    }

    protected function _menu_($data,$TI,$TW,$level=1,$ml=0) {
      static $rm = null;
      if (is_null($rm)) $rm = $this->owner->MODXRevoMode;
      $pb = $rm ? '[[+' : '[+';
      $pe = $rm ? ']]'  : '+]';
      $v = array();
      foreach ($data as $id => $item) {
        if (!is_array($item)) continue;
        $ch = array('','','');
        $ar = array("&id=`{$id}`");
        $t_ = $TI;
        foreach ($item as $key => $val) {
          if (is_array($val)) {
            if (in_array($key,array('before','children','after'))) {
              if (($level >= $ml) && ($ml != 0)) continue;
              $ck      = $key == 'children' ? 1 : ($key == 'before' ? 0 : 2);
              $ch[$ck] = $this->_menu_($val,$TI,$TW,$level+1,$ml);
            }
          } else {
            $ar[] = "&{$key}=`{$val}`";
            $t_ = str_replace($pb.$key.$pe,$val,$t_);
          }
        }
        $v[] = str_replace(array(
          '[+args+]','[+id+]','[+children+]'
        ),array(
          implode(' ',$ar),$id,implode('',$ch)
        ),$t_);
      }
      $v = implode('',$v);
      $v = $this->owner->parse(str_replace(array(
        '[+items+]','[+level+]'
      ),array(
        $this->owner->parse($v),$level
      ),$TW));
      return $v;
    }
  }
  /* CLASS ~END */

  /* INFO @copyright: Xander Bass, 2016 */
?>