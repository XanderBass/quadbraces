<?php
  /* INFO
    @product     : QuadBracesParser
    @component   : QuadBracesTagTable
    @type        : class
    @description : Обработчик табличной информации
    @revision    : 2016-01-10 18:36:00
  */

  if (!class_exists('QuadBracesTag',false)) require 'prototype.php';

  /* CLASS ~BEGIN */
  class QuadBracesTagTable extends QuadBracesTag {
    public function main($key,$value,$args) {
      if (!is_array($value)) return strval($value);
      $v = '';
      // Параметры обработки
      $LK = false; $LP = ''; $FL = array();
      if (isset($args['langKeys']))   $LK = QuadBracesParser::bool($args['langKeys']);
      if (isset($args['langPrefix'])) $LP = $args['langPrefix'];

      if (!isset($args['fields'])) {
        foreach ($value as $dataRow)
          if (is_array($dataRow))
            foreach ($dataRow as $fKey => $fVal)
              if (!in_array($fKey,$FL)) $FL[] = $fKey;
      } else { $FL = explode(',',$args['fields']); }

      $tpls = $this->getTemplates($args,array(
        'table'    => '<table cellpadding="0" cellspacing="0"'.' border="0">[+content+]</table>',
        'heap'     => '<theap>[+rows+]</theap>',
        'heaprow'  => '<tr>[+cells+]</tr>',
        'heapcell' => '<th class="field-[+key+]">[+value+]</th>',
        'body'     => '<tbody>[+rows+]</tbody>',
        'bodyrow'  => '<tr data-id="[+id+]">[+cells+]</tr>',
        'bodycell' => '<td class="field-[+key+]">[+value+]</td>',
        'foot'     => '',
        'footrow'  => '<tr>[+cells+]</tr>',
        'footcell' => '<th class="field-[+key+]">[+value+]</th>'
      ));

      $tplsC = array(
        array("#\[\+(key)((:?\:([\w\-\.]+)((=`([^`]*)`))?)*)\+\]#si",''),
        array("#\[\+(value)((:?\:([\w\-\.]+)((=`([^`]*)`))?)*)\+\]#si",'')
      );

      $tplsR = array(
        array("#\[\+(id)((:?\:([\w\-\.]+)((=`([^`]*)`))?)*)\+\]#si",''),
      );
      // Шапка
      if (!empty($tpls['heap'])) {
        $row = array();
        foreach ($FL as $fKey) {
          $tplsC[0][1] = $fKey;
          $tplsC[1][1] = $LK ? "[%".(!empty($LP) ? $LP."." : "")."$fKey%]" : $fKey;;
          $row[]       = $this->owner->iteration($tplsC,$tpls['heapcell']);
        }
        $row = implode('',$row);
        $row = str_replace('[+cells+]',$row,$tpls['heaprow']);
        $v  .= str_replace('[+rows+]',$row,$tpls['heap']);
      }
      // Значения
      $rows = array();
      foreach ($value as $dataID => $dataRow) {
        if (is_array($dataRow)) {
          $tplsR[0][1] = $dataID;
          $row = array();
          foreach ($FL as $fKey) {
            $tplsC[0][1] = $fKey;
            $tplsC[1][1] = isset($dataRow[$fKey]) ? $dataRow[$fKey] : '';
            $row[]       = $this->owner->iteration($tplsC,$tpls['bodycell']);
          }
          $row = implode('',$row);
          $row = str_replace('[+cells+]',$row,$tpls['bodyrow']);
          $row = $this->owner->iteration($tplsR,$row);
          $rows[] = $row;
        }
      }
      $v.= str_replace('[+rows+]',implode('',$rows),$tpls['body']);
      // Подвал
      if (!empty($tpls['foot'])) {
        $row = array();
        foreach ($FL as $fKey) {
          $tplsC[0][1] = $fKey;
          $tplsC[1][1] = $LK ? "[%".(!empty($LP) ? $LP."." : "")."$fKey%]" : $fKey;;
          $row[]       = $this->owner->iteration($tplsC,$tpls['footcell']);
        }
        $row = implode('',$row);
        $row = str_replace('[+cells+]',$row,$tpls['footrow']);
        $v  .= str_replace('[+rows+]',$row,$tpls['foot']);
      }
      // Итог
      return str_replace('[+content+]',$v,$tpls['table']);
    }
  }
  /* CLASS ~END */

  /* INFO @copyright: Xander Bass, 2016 */
?>