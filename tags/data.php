<?php
  /* INFO
    @product     : QuadBracesParser
    @component   : QuadBracesTagData
    @type        : class
    @description : Обработчик данных
    @revision    : 2016-01-10 18:29:00
  */

  if (!class_exists('QuadBracesTag',false)) require 'prototype.php';

  /* CLASS ~BEGIN */
  class QuadBracesTagData extends QuadBracesTag {
    public function main($key,$value,$args) {
      if (!is_array($value)) return strval($value);
      $v = '';
      // Параметры обработки
      $LK = false; $LP = '';
      if (isset($args['langKeys']))   $LK = QuadBracesParser::bool($args['langKeys']);
      if (isset($args['langPrefix'])) $LP = $args['langPrefix'];

      $tplt = 'chunk';
      if (isset($args['chunkType']))
        if (in_array($args['chunkType'],array('chunk','string','lib'))) $tplt = $args['chunkType'];
      $tpli = '';
      if (isset($args['chunk'])) switch ($tplt) {
        case 'string': $tpli = '{['.$args['chunk'].' [+arguments+]]}'; break;
        case 'lib'   : $tpli = '{('.$args['chunk'].' [+arguments+])}'; break;
        default      : $tpli = '{{'.$args['chunk'].' [+arguments+]}}'; break;
      }
      if (!empty($tpli)) foreach ($value as $dataKey => $dataVal) {
        $DK = $LK ? "[%".(!empty($LP) ? $LP."." : "")."$dataKey%]" : $dataKey;
        if (is_array($dataVal)) {
          $_   = array();
          $_[] = "&_datakey=`$DK`";
          foreach ($dataVal as $dvKey => $dvVal) $_[] = "&".$dvKey."=`$dvVal`";
        } else {
          $_   = array();
          $_[] = "&_datakey=`$DK`";
          $_[] = "&_datavalue=`$dataVal`";
        }
        $v.= str_replace('[+arguments+]',implode(' ',$_),$tpli);
      }
      return $v;
    }
  }
  /* CLASS ~END */

  /* INFO @copyright: Xander Bass, 2016 */
?>