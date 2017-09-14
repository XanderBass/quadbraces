<?php
  class QuadBracesExts {
    /* LIBRARY:FUNCTION
      @description : Ссылки

      @param : a     | string | value | | Тип
      @param : value | string | value | | Значение
      @param : add   | string | value | | Дополнительные данные

      @param : string
    */
    public static function link($a,$value,$add) {
      $tpls = array(
        'link'          => '<a href="[+content+]">[+value+]</a>',
        'link-external' => '<a href="[+content+]" target="_blank">[+value+]</a>'
      );
      if (isset($tpls[$a]) && !empty($add)) {
        $val = empty($v) ? $value : $add;
        return str_replace(array('[+content+]','[+value+]'),array($value,$val),$tpls[$a]);
      }
      return $value;
    }

    /* LIBRARY:FUNCTION
      @description : Ссылки JS, CSS

      @param : a     | string | value | | Тип
      @param : value | string | value | | Значение

      @param : string
    */
    public static function jscss($a,$value) {
      $tpls = array(
        'js-link'  => '<script type="text/javascript" src="[+content]"></script>',
        'css-link' => '<link rel="stylesheet" type="text/css" href="[+content+]" />',
        'import'   => '@import url("[+content+]");'
      );
      if (isset($tpls[$a])) return str_replace('[+content+]',"$value",$tpls[$a]);
      return $value;
    }

    /* LIBRARY:FUNCTION
      @description : Автоматическое преобразование ссылок

      @param : data  | string | value | | Исходное значение
      @param : value | string | value | | Атрибуты ссылок

      @return : string
    */
    public static function autoLinks($data,$value) {
      $rexURL    = '#^(http|https)\:\/\/([\w\-\.]+)\.([a-zA-Z0-9]{2,16})\/(\S*)$#si';
      $rexMailTo = '#^mailto\:([\w\.\-]+)\@([a-zA-Z0-9\.\-]+)\.([a-zA-Z0-9]{2,16})$#si';
      $RET       = $data;
      foreach (array(
        array($rexURL,'<a href="\1://\2.\3/\4"[+C+]>\1://\2.\3/\4</a>'),
        array($rexMailTo,'<a href="mailto:\1@\2.\3">\1@\2.\3</a>',)
      ) as $item) $RET = preg_replace($item[0],$item[1],$RET);
      // [+C+] - атрибуты ссылок
      return str_replace('[+C+]',(empty($value) ? '' : " $value"),$RET);
    }

    /* LIBRARY:FUNCTION
      @description : Списки

      @param : value | string | value |        | Атрибуты ссылок
      @param : row   | string | value | @NULL  | Шаблон ряда
      @param : ord   | bool   | value | @FALSE | Порядковый список

      @return : string
    */
    public static function autoList($value,$row=null,$ord=false) {
      $tpl   = empty($row) ? '<li[+classes+]>[+item+]</li>' : $row;
      $items = preg_split('~\\r\\n?|\\n~',$value);
      $type  = $ord ? 'ol' : 'ul';
      $RET   = "<$type>";
      for ($c = 0; $c < count($items); $c++) if (!empty($items[$c])) {
        $CL = '';
        if ($c == 0) $CL = ' classes="first"';
        if ($c == (count($items) - 1)) $CL = ' classes="last"';
        $IC  = explode('|',$items[$c]);
        $_ = str_replace(array('[+classes+]','[+item+]'),array($CL,$items[$c]),$tpl);
        for ($ic = 0; $ic < count($IC); $ic++)
          $_ = str_replace("[+item.$ic+]",$IC[$ic],$_);
        $RET.= $_;
      }
      return "$RET</$type>";
    }
  }
?>