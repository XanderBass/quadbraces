<?php
  class QuadBracesLib {
    const extRex  = '\:([\w\-\.]+)((\=`([^`]*)`)?)';
    const extArgs = '#(:?\s*)\&([\w\-\.]+)\=`([^`]*)`#si';

    /******** ТЕГИ ********/
    /* LIBRARY:FUNCTION
      @description : Аргументы

      @param : v | string | value | | Исходная строка

      @return : array
    */
    public static function arguments($v) {
      if (empty($v)) return array();
      $A = array();
      if ($_ = preg_match_all(self::extArgs,$v,$ms,PREG_SET_ORDER))
        foreach ($ms as $pr) $A[$pr[2]] = $pr[3];
      return $A;
    }

    /******** КЛЮЧИ ********/
    /* LIBRARY:FUNCTION
      @description : Получить значение по ключу

      @param : input | array | value | | Входное значение
      @param : key   | array | value | | Ключ

      @return : ?
    */
    public static function getByKey(array $input,$key,$def='') {
      $P = explode('.',$key);
      $V = $input;
      foreach ($P as $i) {
        if (!isset($V[$i])) return $def;
        $V = $V[$i];
      }
      return $V;
    }

    /* LIBRARY:FUNCTION
      @description : Установить значение по ключу

      @param : input | array | value | | Входное значение
      @param : key   | array | value | | Ключ
      @param : value |       | value | | Значение

      @return : ?
    */
    public static function setByKey(array &$input,$key,$value) {
      $P = explode('.',$key);
      if (count($P) < 1) return false;
      $tmp = &$input;
      foreach ($P as $K) {
        if (!isset($tmp[$K]) || !is_array($tmp[$K])) $tmp[$K] = array();
        $tmp = &$tmp[$K];
      }
      $tmp = $value;
      unset($tmp);
      return $input;
    }

    /* LIBRARY:FUNCTION
    @description : Разделение ключей

    @param : key | string | value | | Исходный ключ

    @return : array
  */
    public static function keys($key) {
      $pkey = explode('.',$key);
      $item = intval($pkey[count($pkey)-1]);
      unset($pkey[count($pkey)-1]);
      $pkey = implode('.',$pkey);
      return array($pkey,$item);
    }

    /******** ЛОГИКА И МАТЕМАТИКА ********/
    /* LIBRARY:FUNCTION
      @description : Условие

      @param : cond | string | value | | Условие
      @param : v1   |        | value | | Первая переменная
      @param : v2   |        | value | | Вторая переменная

      @return : bool
    */
    public static function condition($cond,$v1,$v2=null) {
      $vt = ''.trim($v1);
      $vi = intval($vt);
      switch ($cond) {
        case 'is'      :
        case 'eq'      : return ($v1 == $v2);
        case 'isnot'   :
        case 'neq'     : return ($v1 != $v2);
        case 'lt'      : return ($v1 <  $v2);
        case 'lte'     : return ($v1 <= $v2);
        case 'gt'      : return ($v1 >  $v2);
        case 'gte'     : return ($v1 >= $v2);
        case 'even'    : return (($vi % 2) == 0);
        case 'odd'     : return (($vi % 2) != 0);
        case 'empty'   : return  empty($vt);
        case 'notempty': return !empty($vt);
        case 'null'    :
        case 'isnull'  : return  is_null($vt);
        case 'notnull' : return !is_null($vt);
        case 'isarray' : return is_array($v1);
      }
      return false;
    }

    /* LIBRARY:FUNCTION
      @description : Математическая операция

      @param : o  | string | value | | Операция
      @param : v1 |        | value | | Первая переменная
      @param : v2 |        | value | | Вторая переменная

      @return : float
    */
    public static function math($o,$v1,$v2) {
      switch ($o) {
        case 'plus' : return floatval($v1) + floatval($v2);
        case 'minus': return floatval($v1) - floatval($v2);
        case 'mul'  : return floatval($v1) * floatval($v2);
        case 'div'  : return floatval($v1) / floatval($v2);
        case 'rest' : return floatval($v1) % floatval($v2);
        case 'idiv' : return round(floatval($v1) / floatval($v2),0,PHP_ROUND_HALF_DOWN);
      }
      return $v1;
    }

    /* LIBRARY:FUNCTION
      @description : Преобразование типа

      @param : o | string | value | | Операция
      @param : v |        | value | | Переменная

      @return : ?
    */
    public static function toType($o,$v) {
      switch ($o) {
        case 'int'  : return intval($v);
        case 'float': return floatval($v);
        case 'bool' : return self::bool($v) ? 'true' : 'false';
      }
      return $v;
    }

    /* LIBRARY:FUNCTION
      @description : Признак условия

      @param : cond | string | value | | Условие

      @return : bool
    */
    public static function isLogic($cond) {
      return in_array($cond,array(
        'is','eq','isnot','neq','lt','lte','gt','gte',
        'even','odd','notempty','empty','null','isnull','notnull','isarray'
      ));
    }

    /* LIBRARY:FUNCTION
      @description : Признак условия

      @param : cond | string | value | | Условие

      @return : bool
    */
    public static function isLogicFunction($cond) {
      return in_array($cond,array(
        'even','odd','notempty','empty','null','isnull','notnull','isarray'
      ));
    }

    /* LIBRARY:FUNCTION
      @description : Признак математической операции

      @param : cond | string | value | | Условие

      @return : bool
    */
    public static function isMath($cond) {
      return in_array($cond,array('plus','minus','mul','div','rest','idiv'));
    }

    /* LIBRARY:FUNCTION
      @description : Признак преобразования типов

      @param : cond | string | value | | Условие

      @return : bool
    */
    public static function isType($cond) { return in_array($cond,array('int','float','bool')); }

    /******** МЕТАДАННЫЕ ********/
    /* LIBRARY:FUNCTION
      @description : Извлечь метаполя

      @param : tpl | string | value | | Код шаблона

      @return : array | массив:
                        элемент body содержит очищенный шаблон,
                        элемент data содержит извлечённые данные
    */
    public static function extractFields($tpl) {
      $out = array('fields' => array(),'body' => '');
      $rex = '#\<\!--(?:\s+)FIELD\:([\w\.\-]+)(?:\??)(((:?\s*)\&([\w\-\.]+)\=`([^`]*)`)*)(?:\s*)--\>#si';
      $out['rex'] = $rex;
      if (preg_match_all($rex,$tpl,$am,PREG_SET_ORDER)) foreach ($am as $d) {
        $key  = $d[1];
        $data = empty($d[2]) ? array() : QuadBracesLib::arguments($d[2]);
        $out['fields'][$key] = array(
          'default' => isset($data['default']) ? $data['default']      : '',
          'type'    => isset($data['type'])    ? intval($data['type']) : 0,
          'caption' => isset($data['caption']) ? $data['caption']      : ''
        );
      }
      $out['body'] = trim(preg_replace($rex,'',$tpl));
      return $out;
    }

    /* LIBRARY:FUNCTION
      @description : Извлечь метаданные

      @param : tpl | string | value |       | Код шаблона
      @param : def | array  | value | @NULL | Данные по умолчанию
      @param : cln | string | value | @NULL | Имя класса

      @return : array | массив:
                        элемент body содержит очищенный шаблон,
                        элемент data содержит извлечённые данные
    */
    public static function extractData($tpl,$def=null) {
      $TPL = $tpl;
      $out = array('data' => array(),'body' => '');
      $rex = '#\<\!--(?:\s*)DATA\:\[\+key\+\](?:\s+)`([^`]*)`(?:\s*)--\>#si';
      if (is_array($def)) {
        foreach ($def as $key => $val) {
          $t = str_replace('[+key+]',strtolower($key),$rex);
          $out['data'][$key] = $val;
          if ($r = preg_match_all($t,$TPL,$_,PREG_PATTERN_ORDER)) {
            $out['data'][$key] = $_[1][0];
            if (is_int($val))   $out['data'][$key] = intval($_[1][0]);
            if (is_float($val)) $out['data'][$key] = floatval($_[1][0]);
            $TPL = preg_replace($t,'',$TPL);
          }
        }
      } else {
        $rex = str_replace('\[\+key\+\]','([\w\-\.]+)',$rex);
        $out['rex'] = $rex;
        if (preg_match_all($rex,$TPL,$am,PREG_SET_ORDER)) foreach ($am as $d) $out['data'][$d[1]] = $d[2];
        $TPL = preg_replace($rex,'',$TPL);
      }
      $out['body'] = trim($TPL);
      return $out;
    }

    /******** ПРОЧЕЕ ********/
    /* LIBRARY:FUNCTION
      @description : Простая замена

      @param : data  | array  | value |        | данные
      @param : value | string | value | @EMPTY | Шаблон

      @return : string
    */
    public static function placeholders($data,$value='') {
      $O = $value;
      foreach ($data as $key => $val) $O = str_replace("[+$key+]",$val,$O);
      return $O;
    }

    /* LIBRARY:FUNCTION
      @description : Булево значение

      @param : v | | value | | входное значение

      @return : bool
    */
    public static function bool($v) {
      return ((strval($v) === 'true') || ($v === true) || (intval($v) > 0));
    }

    /* LIBRARY:FUNCTION
      @description : Слить данные

      @param : input | array | value | | Входной массив
      @param : value | array | value | | Значение

      @return : array
    */
    public static function megreData($input,$value) {
      if (!is_array($value) || !is_array($input)) return $input;
      if (empty($input)) return $value;
      $ret = $input;
      foreach ($value as $k => $v) $ret[$k] = $v;
      return $ret;
    }

    /* LIBRARY:FUNCTION
      @description : Значение времени

      @param : $value | float  | value |        | Значение
      @param : $unit  | string | value | @EMPTY | Единица

      @return : string
    */
    public static function timeValue($value,$unit='') {
      $ret = $value;
      switch ($unit) {
        case 'ms': $ret *= 1000; break;
        case 'us': $ret *= 1000000; break;
        case 'ns': $ret *= 1000000000; break;
      }
      return strval(round($ret,2));
    }

    /* LIBRARY:FUNCTION
      @description : Значение времени

      @param : $value | float  | value |        | Значение
      @param : $unit  | string | value | @EMPTY | Единица

      @return : string
    */
    public static function RAMValue($value,$unit='') {
      $ret = $value;
      switch ($unit) {
        case 'kb': $ret /= 1024; break;
        case 'mb': $ret /= 1048576; break;
        case 'gb': $ret /= 1073741824; break;
      }
      return strval(round($ret,2));
    }
  }
?>