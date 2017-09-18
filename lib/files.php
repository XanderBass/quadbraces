<?php
  class QuadBracesFiles {
    /* CLASS:STATIC
      @description : Корректировать путь

      @param : $path | string | value | | Путь

      @return : string
    */
    public static function path($path) {
      return rtrim(implode(DIRECTORY_SEPARATOR,explode('/',$path)),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    }

    /* CLASS:STATIC
      @description : Корректировать пути

      @param : $path | string | value | | Путь

      @return : string
    */
    public static function paths($v) {
      $ret = array();
      $_   = is_array($v) ? $v : explode(',',''.$v);
      foreach ($_ as $path) {
        $vv = rtrim(implode(DIRECTORY_SEPARATOR,explode('/',$path)),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
        if (is_dir($vv)) if (!in_array($vv,$ret)) $ret[] = $vv;
      }
      return $ret;
    }

    /* CLASS:STATIC
      @description : Удаление папки

      @param : $d | string | value | | Директория

      @return : bool
    */
    public static function removeFolder($d) {
      if ($c = glob($d.DIRECTORY_SEPARATOR.'*'))
        foreach($c as $i) is_dir($i) ? self::removeFolder($i) : unlink($i);
      return rmdir($d);
    }

    /* CLASS:METHOD
      @description : Поиск элемента

      @param : type  | string | value |        | Тип элемента
      @param : name  | string | value |        | Имя элемента
      @param : paths | array  | value |        | Пути
      @param : lang  | string | value | @empty | Язык
      @param : ext   | string | value | html   | Расширение

      @param : string
    */
    public static function search($type,$name,$paths,$lang='',$ext='html') {
      $_x = $ext;
      $DS = DIRECTORY_SEPARATOR;

      if (!in_array($type,array('template','chunk','snippet'))) return false;
      $sdir = $type.'s';
      if ($type == 'snippet') $_x = 'php';

      $dname = explode('.',$name);
      $ename = $dname[count($dname)-1];
      unset($dname[count($dname)-1]);
      $dname = count($dname) > 0 ? implode($DS,$dname).$DS : '';
      $found = '';
      foreach ($paths as $epath) {
        $D = $epath.$sdir.DIRECTORY_SEPARATOR.$dname;
        $fname = $D."$ename.$_x";
        if (is_file($fname)) $found = $fname;
        if ($lang != '') {
          $fname = $D.$lang.DIRECTORY_SEPARATOR."$ename.$_x";
          if (is_file($fname)) $found = $fname;
        }
      }

      return $found;
    }

    /* CLASS:STATIC
      @description : Байты

      @param : $v | string | value | | Значение

      @return : int
    */
    public static function bytes($v) {
      $val  = trim($v);
      $last = strtolower($val[strlen($val)-1]);
      switch($last) {           /** @noinspection PhpMissingBreakStatementInspection */
        case 'g': $val *= 1024; /** @noinspection PhpMissingBreakStatementInspection */
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
      }
      return $val;
    }
  }
?>