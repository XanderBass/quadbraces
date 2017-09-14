<?php
  if (!class_exists('QuadBracesFiles',false)) require 'files.php';

  class QuadBracesLang {
    protected static $_all = null;

    /* LIBRARY:FUNCTION
      @description : Допустимые языки

      @param : renew | bool   | value | @false | Признак обновления
      @param : renew | string | value | en     | Сигнатура по умолчанию

      @return : array
    */
    public static function accepted($renew=false,$def='en') {
      static $ret = null;
      if (is_array($ret) && !$renew) return $ret;
      $ret = array();
      if (($s = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'])))
        if (preg_match_all('/([a-z]{1,8}(?:-[a-z]{1,8})?)(?:;q=([0-9.]+))?/',$s,$l)) {
          $ret = array_combine($l[1],$l[2]);
          $tmp = $ret;
          foreach ($tmp as $n => $v) $ret[$n] = $v ? $v : 1;
          arsort($ret,SORT_NUMERIC);
          $ret = array_keys($ret);
        }
      if  (empty($ret)) $ret = $def;
      if (!empty(self::$_all)) {
        $tmp = $ret;
        foreach ($ret as $k => $l) if (!in_array($l,self::$_all)) unset($tmp[$k]);
        $tmp = array_values($tmp);
        $ret = $tmp;
      }
      return $ret;
    }

    /* CLASS:STATIC
      @description : Сканирование языковой папки и получения слооварных данных

      @param : $lang  | string | value | @null | Язык
      @param : $paths |        | value | @null | Пути

      @return : array
    */
    public static function load($lang=null,$paths=null) {
      static $_sup    = null;
      static $_pcache = null;
      if (is_null($_sup))   $_sup = array('caption','hint');
      if (!is_null($paths)) $_pcache = QuadBracesFiles::paths($paths);
      if (empty($_pcache))  $_pcache = null;
      if (is_null($_pcache)) return false;
      if (is_null($lang))    return false;

      $retval = array();
      $files  = array();

      foreach ($_pcache as $path)
        if ($_ = glob($path.$lang.DIRECTORY_SEPARATOR.'*.lng'))
          foreach ($_ as $_i) $files[] = $_i;
      foreach ($files as $f) {
        $data = file($f);
        foreach ($data as $s) {
          $str = trim($s);
          if (!empty($str)) {
            $a = explode('|',$str);
            $k = trim(array_shift($a));
            foreach ($_sup as $p => $e) {
              $d = isset($a[$p]) ? trim($a[$p]) : '';
              if (($d == '') && isset($retval[$k][$e])) $d = $retval[$k][$e];
              $retval[$k][$e] = $d;
            }
          }
        }
      }
      return $retval;
    }
  }
?>