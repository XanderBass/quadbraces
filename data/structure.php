<?php
  /* INFO
    @product     : QuadBracesParser
    @component   : QuadBracesTagStructure
    @type        : class
    @description : Обработчик структурной информации
    @revision    : 2016-01-10 18:36:00
  */

  if (!class_exists('QuadBracesDataPrototype',false)) require 'prototype.php';

  /* CLASS ~BEGIN */
  class QuadBracesDataStructure extends QuadBracesDataPrototype {
    public function main($key,$value,$args) {
      if (!is_array($value)) return strval($value);
      // Параметры обработки
      $FL = isset($arguments['fields']) ? explode(',',$arguments['fields']) : array();

      $tpls = $this->getTemplates($arguments,array(
        'outer' => '<ul>[+items+]</ul>',
        'item'  => '<li><span class="key">[+key+]</span><span>[+value+]</span></li>'
      ));

      $args = array();
      foreach ($arguments as $aKey => $aVal) $args[] = "&$aKey=`$aVal`";
      $args = implode(' ',$args);
      if (!empty($args)) $args = " $args";

      $tplsC = array(
        array("#\[\+(key)((:?\:([\w\-\.]+)((=`([^`]*)`))?)*)\+\]#si",''),
        array("#\[\+(value)((:?\:([\w\-\.]+)((=`([^`]*)`))?)*)\+\]#si",'')
      );

      // Значения
      $rows = array();
      foreach ($value as $dataKey => $dataVal) if (empty($FL) || in_array($dataKey,$FL)) {
        $tplsC[0][1] = $dataKey;
        $tplsC[1][1] = is_array($dataVal) ? "[:structure@$key.$dataKey"."$args:]" : strval($dataVal);
        $rows[] = $this->owner->iteration($tplsC,$tpls['item']);
      }

      return str_replace('[+items+]',implode('',$rows),$tpls['outer']);
    }
  }
  /* CLASS ~END */

  /* INFO @copyright: Xander Bass, 2016 */
?>