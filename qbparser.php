<?php
  /**
   * QuadBracesParser
   * Парсер синтаксиса MODX / Etomite
   *
   * Предназначен для того, чтобы не заколачивать экскаватором гвозди, то бишь
   * не устанавливать MODX для целевых страниц
   *
   * @version 1.0
   * @author  Xander Bass
   */

  // Инициализация констант
  if (!defined("QBPARSER_TPL_PATH")) {
    $_ = realpath($_SERVER['DOCUMENT_ROOT']).DIRECTORY_SEPARATOR;
    $_ = $_.implode(DIRECTORY_SEPARATOR,array('content','tpl')).DIRECTORY_SEPARATOR;
    define("QBPARSER_TPL_PATH",$_);
  }
  if (!defined("QBPARSER_LANG_PATH")) define("QBPARSER_LANG_PATH",QBPARSER_TPL_PATH.'lang'.DIRECTORY_SEPARATOR);
  if (!defined("QBPARSER_STARTTIME")) define("QBPARSER_STARTTIME",microtime(true));
  if (!defined("QBPARSER_STARTMEM"))  define("QBPARSER_STARTMEM",memory_get_usage());

  /**
   * Class QuadBracesParser

   * @property      string $template     шаблон (принимает ключ шаблона, возвращает код оного)
   * @property-read string $templateName ключ шаблона
   * @property      string $templatePack шаблон-пак
   *
   * @property      array  $data         данные для плейсхолдеров
   * @property      array  $settings     данные для "настроек"
   * @property-read array  $dictionary   данные для словаря
   *
   * @property      string $language     подгружаемый язык
   */
  class QuadBracesParser {
    protected static $_maxLevel = 32;
    protected static $_tags     = null;

    protected $_debugTrace   = array();
    protected $_template     = '';
    protected $_templateName = '';
    protected $_templatePack = '';
    protected $_data         = array();
    protected $_settings     = array();
    protected $_arguments    = array();
    protected $_language     = '';
    protected $_dictionary   = array();
    protected $_level        = -1;

    function __construct() {
      self::initTags();
      $this->_debugTrace['starttime'] = microtime(true);
    }

    function __get($n) {
      if (method_exists($this,"get_$n"))       { $f = "get_$n"; return $this->$f();
      } elseif (property_exists($this,"_$n"))  { $f = "_$n"; return $this->$f;
      } elseif (method_exists($this,"set_$n")) { $e = 'is write only';
      } else { $e = property_exists($this,$n) ? 'is protected' : 'does not exists'; }
      throw new Exception("property $n $e");
    }

    function __set($n,$v) {
      if (method_exists($this,"set_$n"))       { $f = "set_$n"; return $this->$f($v);
      } elseif (method_exists($this,"get_$n")) { $e = 'is read only';
      } else { $e = property_exists($this,$n) ? 'is protected' : 'does not exists'; }
      throw new Exception("property $n $e");
    }

    function __toString() { return $this->parse(); }

    /**
     * @description Сеттер для template
     * @param  string $name
     * @return string
     */
    protected function set_template($name) {
      $content = '[*content*]';
      if (!empty($name)) {
        $this->_templateName = '';
        if ($fn = $this->search('template',$name)) {
          $content = @file_get_contents($fn);
          $this->_templateName = $name;
        }
      }
      $this->_template = $content;
      return $this->_template;
    }

    /**
     * @description Сеттер для templatePack
     * @param  string $v
     * @return string
     */
    protected function set_templatePack($v) {
      if (is_dir(QBPARSER_TPL_PATH.$v)) $this->_templatePack = $v;
      return $this->_templatePack;
    }

    /**
     * @description Сеттер для data
     * @param  array $d
     * @return array
     */
    protected function set_data(array $d) {
      $this->_data = self::megreData($this->_data,$d);
      return $this->_data;
    }

    /**
     * @description Сеттер для settings
     * @param  array $d
     * @return array
     */
    protected function set_settings(array $d) {
      $this->_settings = self::megreData($this->_settings,$d);
      return $this->_settings;
    }

    /**
     * @description Сеттер для языка
     * @param  string $v
     * @return string
     */
    protected function set_language($v) {
      if (!empty($v)) $this->_language = strval($v);
      $this->_dictionary = self::loadLang($this->_language);
      return $this->_language;
    }

    /**
     * @description Выполнение сниппета или расширения
     * @param  string $name имя сниппета
     * @param  array  $A    входные аргументы
     * @param  string $I    входные данные для расширения
     * @return string
     */
    public function execute($name,$A=array(),$I='') {
      $result = '';
      /** @noinspection PhpUnusedLocalVariableInspection */
      $arguments = $A;
      /** @noinspection PhpUnusedLocalVariableInspection */
      $input     = strval($I);
      if ($fn = $this->search('snippets',$name)) $result = include($fn);
      return strval($result);
    }

    /**
     * @comment алиасы колбека
     */
    public function parse_chunk($m)       { return $this->parse_element($m,'chunk'); }
    public function parse_constant($m)    { return $this->parse_element($m,'constant'); }
    public function parse_setting($m)     { return $this->parse_element($m,'setting'); }
    public function parse_placeholder($m) { return $this->parse_element($m,'placeholder'); }
    public function parse_language($m)    { return $this->parse_element($m,'language'); }
    public function parse_debug($m)       { return $this->parse_element($m,'debug'); }
    public function parse_snippet($m)     { return $this->parse_element($m,'snippet'); }
    public function parse_local($m)       { return $this->parse_element($m,'local'); }
    public function parse_datae($m)       { return $this->parse_element($m,'datae'); }

    /**
     * @description Колбек для preg_replace_callback в обработчике
     * @param  array  $m     данные от preg_replace_callback
     * @param  string $etype тип элемента
     * @return string
     */
    private function parse_element($m,$etype='') {
      $arguments = array();
      if (isset($m[8]) && !empty($m[8]))
        if ($_ = preg_match_all('|\&([\w\-\.]+)\=`([^`]*)`|si',$m[8],$ms,PREG_SET_ORDER))
          foreach ($ms as $pr) $arguments[$pr[1]] = $pr[2];

      $k = $m[1]; $v = '';

      if (empty($etype)) return '';
      switch($etype) {
        case 'chunk':
          if ($fn = $this->search('chunk',$k)) {
            $v  = @file_get_contents($fn);
          } else { return "<!-- EMPTY chunk/$k -->"; }
          break;
        case 'constant':
          if (empty($k) || !defined($k)) return "<!-- EMPTY $etype/$k -->";
          $v = strval(constant($k));
          break;
        case 'setting': case 'local': case 'placeholder':
          $PA = array('setting' => '_settings','local' => '','placeholder' => '_data');
          $PN = strval($PA[$etype]);
          $AR = empty($PN) ? $this->_arguments[$this->_level - 1] : $this->$PN;
          if (!isset($AR[$k])) return $etype == 'local' ? "" : "<!-- EMPTY $etype/$k -->";
          $v = strval($AR[$k]);
          break;
        case 'language':
          $P = 'caption';
          $K = self::langKey($k,$P);
          if (isset($this->_dictionary[$K][$P])) {
            $v = $this->_dictionary[$K][$P];
          } else { $v = ucfirst(str_replace(array('.','_','-'),' ',$k)); }
          break;
        case 'debug':
          $dd = explode('.',$k);
          $dk = $dd[0];

          $asz = array('kb' => 1000,'mb' => 1000000,'gb' => 1000000000);
          $atm = array('ms' => 1000,'us' => 1000000,'ns' => 1000000000);

          switch ($dk) {
            case 'memory':
            case 'mem':
              $v = memory_get_usage();
              if (count($dd) > 1) if (array_key_exists($dd[1],$asz)) $v /= $asz[$dd[1]];
              $v = strval(round($v,2));
              break;
            case 'time':
              $v = microtime(true) - QBPARSER_STARTTIME;
              if (count($dd) > 1) if (array_key_exists($dd[1],$atm)) $v *= $atm[$dd[1]];
              $v = strval(round($v,2));
              break;
            case 'totalmem':
              $v = '<!-- PARSER:TOTALMEM';
              if (count($dd) > 1) if (array_key_exists($dd[1],$asz)) $v.= ' '.$dd[1];
              $v.= " -->";
              break;
            case 'totaltime':
              $v = '<!-- PARSER:TOTALTIME';
              if (count($dd) > 1) if (array_key_exists($dd[1],$atm)) $v.= ' '.$dd[1];
              $v.= " -->";
              break;
            default: $v = '';
          }

          if (empty($v)) {
            if (!isset($this->_debugTrace[$k])) return "<!-- EMPTY debugtrace/$k -->";
            $v  = $this->_debugTrace[$k];
          }
          break;
        case 'datae':
          if (!isset($this->_data[$k])) return "<!-- EMPTY data/$k -->";
          if (is_array($this->_data[$k])) {
            $tpli = '<li><span class="key">[+key+]</span><span class="value">[+value+]</span></li>';
            if (isset($arguments['chunk']))
              if ($fn = $this->search('chunk',$arguments['chunk'])) {
                $tpli = '{{'.$arguments['chunk'].' [+arguments+]}}';
              }
            $LK = false;
            if (isset($arguments['langKeys']))   $LK = self::bool($arguments['langKeys']);
            $LP = '';
            if (isset($arguments['langPrefix'])) $LP = $arguments['langPrefix'];
            $v = '';
            foreach ($this->_data[$k] as $dataKey => $dataVal) {
              $DK = $LK ? "[%".(!empty($LP) ? $LP."." : "")."$dataKey%]" : $dataKey;
              if (is_array($dataVal)) {
                $_ = array();
                foreach ($dataVal as $dvKey => $dvVal) $_[] = "&".$dvKey."=`$dvVal`";
                $_[] = '&'.$k.".datakey=`$DK`";
                $v.= str_replace(array(
                  '[+key+]','[+value+]','[+arguments+]'
                ),array(
                  $DK,strval($dataVal),implode(' ',$_)
                ),$tpli);
              } else {
                $_ = strval($dataVal);
                $v.= str_replace(array(
                  '[+key+]','[+value+]','[+arguments+]'
                ),array(
                  $DK,$_,"&key=`$DK` &value=`$_`"
                ),$tpli);
              }
            }
          } else { $v = strval($this->_data[$k]); }
          break;
        case 'snippet':
          if ($_ = $this->execute($k,$arguments)) {
            $v  = strval($_);
          } else { if ($_ === false) return "<!-- EMPTY snippet/$k -->"; }
          break;
      }

      if (isset($m[2])) $v = $this->extensions($v,$m[2]);
      $this->_arguments[$this->_level] = $arguments;
      return ($v != '') ? $this->parse($v,$etype,$k) : '';
    }

    protected function parse_internal($m) {
//      $k = $m[1];
      $v = $m[2];
      if (isset($m[3])) $v = $this->extensions($v,$m[3],true);
      return $v;
    }

    /**
     * @description Обработка
     * @param  string $d   данные для рекурсивного алгоритма
     * @param  string $elt тип обрабатываемого элемента
     * @param  string $key ключ обрабатываемого элемента
     * @return string
     * @throws Exception
     */
    public function parse($d='',$elt='',$key='') {
      static $_levels = null;

      $P = is_null($_levels) ? 2 : 1;
      $O = is_null($_levels) ? $this->_template : strval($d);
      if (is_null($_levels)) $_levels = array();
      if (empty($O)) return $O;

      for ($c = 0; $c < $P; $c++) {
        $this->_level++;
        if ($this->_level <= self::$_maxLevel) {
          $_levels[$this->_level] = array('element' => $elt,'key' => $key);
          $this->_debugTrace['levels'] = $_levels;
          foreach (self::$_tags as $k => $t) {
            if (method_exists($this,"parse_$k")) {
              if (preg_match($t,$O))
                $O = preg_replace_callback($t,array($this,"parse_$k"),$O);
            } else { throw new Exception("parser not implemented: $k"); }
          }
        } else { $O = self::sanitize($O); }
        $this->_level--;
      }

      if ($this->_level == -1) {
        $st = microtime(true) - QBPARSER_STARTTIME;
        $tm = memory_get_usage() - QBPARSER_STARTMEM;
        $ph = array(
          'TOTALTIME'    => round($st,2),
          'TOTALTIME ms' => round($st*1000,2),
          'TOTALTIME us' => round($st*1000000,2),
          'TOTALTIME ns' => round($st*1000000000,2),
          'TOTALMEM'     => round($tm,2),
          'TOTALMEM kb'  => round($tm/1000,2),
          'TOTALMEM mb'  => round($tm/1000000,2),
          'TOTALMEM gb'  => round($tm/1000000000,2)
        );

        foreach ($ph as $phk => $phv) $O = str_replace("<!-- DEBUG:$phk -->",$phv,$O);

        $O = self::sanitize($O);
        $_levels = null;
      }

      return $O;
    }

    /**
     * @description Обработка расширений
     * @param  string $value    исходные данные
     * @param  string $ext      название расширения
     * @param  bool   $internal обработка внутреннего плейсхолдера (например, плейсхолдер итератора)
     * @return string
     */
    public function extensions($value,$ext='',$internal=false) {
      $RET  = $value;
      $TRET = trim($RET);
      if (empty($ext)) return $value;
      if ($_ = preg_match_all('|\:([\w\-\.]+)((\=`([^`]*)`)?)|si',$ext,$ms,PREG_SET_ORDER)) {
        for($c = 0; $c < count($ms); $c++) {
          $a = $ms[$c][1];
          $v = isset($ms[$c][4]) ? $ms[$c][4] : '';
          if (in_array($a,array('is','eq','isnot','neq','lt','lte','gt','gte','even','odd'))) {
            $cond = false;
            $V    = intval($TRET);
            switch ($a) {
              case 'is'   :
              case 'eq'   : $cond = ($value == $v); break;
              case 'isnot':
              case 'neq'  : $cond = ($value != $v); break;
              case 'lt'   : $cond = ($value <  $v); break;
              case 'lte'  : $cond = ($value <= $v); break;
              case 'gt'   : $cond = ($value >  $v); break;
              case 'gte'  : $cond = ($value >= $v); break;
              case 'even' : $cond = (($V % 2) == 0); break;
              case 'odd'  : $cond = (($V % 2) != 0); break;
            }
            $cthen = $RET;
            if ($ms[$c+1][1] == 'then') { $c++; $cthen = $ms[$c][4]; }
            $celse = $RET;
            if ($ms[$c+1][1] == 'else') { $c++; $celse = $ms[$c][4]; }
            $RET = $cond ? $cthen : $celse;
          } elseif (!$internal) {
            $EMP  = (empty($TRET) && ($TRET !== '0'));
            switch ($a) {
              case 'empty'   : $RET = $EMP ? $v : $TRET; break;
              case 'notempty': $RET = $EMP ? '' : str_replace('[+value+]',"$TRET",$v); break;
              case 'for':
                $v = intval($v);
                $start = 1;
                if ($ms[$c+1][1] == 'start') { $c++; $start = intval($ms[$c][4]); }
                $splt  = '';
                if ($ms[$c+1][1] == 'splitter') { $c++; $splt = $ms[$c][4]; }
                $_R  = array();
                for ($pos = $start; $pos <= ($v - $start); $pos++) {
                  $tpls = array(
                    array("#\[\+(iterator)((:?\:([\w\-\.]+)((=`([^`]*)`))?)*)\+\]#si",$pos)
                  );
                  $_R[] = $this->_iteration($tpls,$RET);
                }
                $RET = implode($splt,$_R);
                break;
              case 'foreach':
                $v = explode(',',$v);
                $splt  = '';
                if ($ms[$c+1][1] == 'splitter') { $c++; $splt = $ms[$c][4]; }
                $_R  = array();
                foreach ($v as $pos => $key) {
                  $tpls = array(
                    array("#\[\+(iterator\.index)((:?\:([\w\-\.]+)((=`([^`]*)`))?)*)\+\]#si",$pos),
                    array("#\[\+(iterator|iterator\.key)((:?\:([\w\-\.]+)((=`([^`]*)`))?)*)\+\]#si",$key)
                  );
                  $_R[] = $this->_iteration($tpls,$RET);
                }
                $RET = implode($splt,$_R);
                break;
              default: if ($_ = $this->execute($a,$v,$RET)) $RET = $_;
            }
          }
        }
      }
      return $RET;
    }

    /**
     * @description итерация
     * @param  array  $tpls массив шаблонов
     * @param  string $O    входные данные
     * @return string
     */
    private function _iteration(array $tpls,$O='') {
      $tplI = "#\[\+([\w\-\.]+)\[([^\]]*)\]((:?\:([\w\-\.]+)((=`([^`]*)`))?)*)\+\]#si";
      $H = false;
      foreach ($tpls as $tpl) {
        if (preg_match($tpl[0],$O)) {
          $O = preg_replace($tpl[0],'[+\1['.$tpl[1].']\2+]',$O);
          $H = true;
        }
      }
      if ($H) $O = preg_replace_callback($tplI,array($this,"parse_internal"),$O);
      return $O;
    }

    /**
     * @description Поиск файловых элементов
     * @param  string $type тип элемента
     * @param  string $name ключ элемента
     * @return string
     * @throws Exception
     */
    public function search($type,$name) {
      $sdir = 'chunks';
      $ext  = 'html';
      $DS   = DIRECTORY_SEPARATOR;

      switch ($type) {
        case 'template': $sdir = 'pages'; break;
        case 'chunk'   : break;
        case 'snippet' : $sdir = 'snippets'; $ext = 'php'; break;
        default: throw new Exception('No parser type'); break;
      }

      $dname = explode('.',$name);
      $ename = $dname[count($dname)-1];
      unset($dname[count($dname)-1]);
      $dname = count($dname) > 0 ? implode($DS,$dname).$DS : '';
      $found = '';

      $_ = array();
      if (!empty($this->_templatePack)) $_[] = $this->_templatePack;
      $_[] = $sdir;
      $path = QBPARSER_TPL_PATH.implode($DS,$_).$DS.$dname;
      // Ищем общий файл
      $fname = $path."$ename.$ext";
      if (is_file($fname)) $found = $fname;
      // Ищем файл для конкретного языка
      if (!empty($this->_language)) {
        $fname = $path.$this->_language.DIRECTORY_SEPARATOR."$ename.$ext";
        if (is_file($fname)) $found = $fname;
      }

      return $found;
    }

    /**
     * @description Инициализация тегов
     */
    public static function initTags() {
      if (is_null(self::$_tags)) {
        $map = array(
          'chunk'       => array('\{\{','\}\}'),
          'constant'    => array('\{\*','\*\}'),
          'setting'     => array('\[\(','\)\]'),
          'placeholder' => array('\[\*','\*\]'),
          'debug'       => array('\[\^','\^\]'),
          'snippet'     => array('\[\!','\!\]'),
          'local'       => array('\[\+','\+\]'),
          'language'    => array('\[\%','\%\]'),
          'datae'       => array('\{\!','\!\}')
        );
        self::$_tags = array();
        foreach ($map as $k => $d) {
          self::$_tags[$k] = "#".$d[0]
            . '([\w\.\-]+)'                         // Alias
            . '((:?\:([\w\-\.]+)((=`([^`]*)`))?)*)' // Extensions
            . '((:?\s*\&([\w\-\.]+)=`([^`]*)`)*)'   // Parameters
            . $d[1].'#si';
        }
      }
    }

    /**
     * @description Очистка от тегов
     * @param  string $data данные для очистки
     * @return string
     */
    public static function sanitize($data='') {
      self::initTags();
      if (empty($data)) return '';
      $O = $data;
      foreach (self::$_tags as $t) if (preg_match($t,$O)) $O = preg_replace($t,'',$O);
      return $O;
    }

    /**
     * @description Слияние двух массивов данных с перекрытие
     * @param  array $input входной массив
     * @param  array $value данные для слияния
     * @return array
     */
    public static function megreData(array $input,array $value) {
      if (!is_array($value) || !is_array($input)) return $input;
      if (empty($input)) return $value;
      $ret = $input;
      foreach ($value as $k => $v) $ret[$k] = $v;
      return $ret;
    }

    /**
     * @description Обработка языкового ключа
     * @param  string $key Исходный ключ
     * @param  string $p   Возвращённое свойство
     * @return string
     */
    public static function langKey($key,&$p) {
      static $_sup = null;
      if (is_null($_sup)) $_sup = array('caption','hint');
      $_ = explode('.',$key);
      $l = count($_) - 1;
      $p = 'caption';
      if (($l > 0)) if (in_array($_[$l],$_sup)) {
        $p = $_[$l];
        unset($_[$l]);
      }
      $k = implode('.',$_);
      return $k;
    }

    /**
     * @description Сканирование языковой папки и получения слооварных данных
     * @param  string $lang
     * @return array
     */
    public static function loadLang($lang) {
      static $_sup = null;
      if (is_null($_sup)) $_sup = array('caption','hint');
      $retval = array();
      // Сканируем директорию
      $files = array();
      if ($_ = glob(QBPARSER_LANG_PATH.$lang.DIRECTORY_SEPARATOR.'*.lng'))
        foreach ($_ as $_i) $files[] = $_i;
      // Загружаем данные из файлов
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

    /**
     * @description Булево значение
     * @param  mixed $v Значение
     * @return bool
     */
    public static function bool($v) {
      return ((strval($v) === 'true') || ($v === true) || (intval($v) > 0));
    }
  }

  /**
   * @copyleft Xander Bass. 2015
   */
?>