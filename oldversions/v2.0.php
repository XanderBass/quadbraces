<?php
  /**
   * QuadBracesParser
   * Парсер синтаксиса подобного MODX / Etomite
   *
   * Предназначен для того, чтобы не заколачивать экскаватором гвозди, то бишь
   * не устанавливать MODX для целевых страниц. Также предназначен в качестве
   * унифицированного стандарта шаблонизации.
   *
   * @version 2.0.2
   * @author  Xander Bass
   */

  /* INFO
    @product     : QuadBracesParser
    @component   : QuadBracesParser
    @type        : class
    @description : Главный класс
    @revision    : 2016-01-10 19:23:00
  */

  if (!defined("QBPARSER_TPL_PATH")) {
    $_ = realpath($_SERVER['DOCUMENT_ROOT']).DIRECTORY_SEPARATOR;
    $_ = $_.implode(DIRECTORY_SEPARATOR,array('content','tpl')).DIRECTORY_SEPARATOR;
    define("QBPARSER_TPL_PATH",$_);
  }

  /* CLASS ~BEGIN @string : Обработанные данные */
  /**
   * Class QuadBracesParser_v2_0
   * @property      array $paths
   * @property      array $data
   * @property      array $idata
   * @property      array $fields
   * @property      array $settings
   * @property-read array $debug
   *
   * @property-read array $chunks
   * @property-read array $strings
   *
   * @property      string $language
   * @property      array  $dictionary
   *
   * @property-read int   $maxLevel
   * @property-read int   $level
   * @property-read array $arguments
   * @property-read array $notice
   *
   * @property      string $template
   * @property-read string $templateName
   * @property-read string $templateExtension
   *
   * @property-read array $tags
   * @property-read array $tagsExts
   *
   * @property string $prefix
   * @property string $content
   *
   * @property float $startTime
   * @property int   $startRAM
   */
  class QuadBracesParser_v2_0 {
    const argRex  = '(:?\s*)\&([\w\-\.]+)\=`([^`]*)';
    const extRex  = '\:([\w\-\.]+)((\=`([^`]*)`)?)';
    const funcRex = '#^([[:alpha:]])(\w+)([[:alpha:]\d])$#si';

    protected static $_all = null;

    protected $_owner   = null;
    protected $_events  = array();
    protected $_methods = array();

    protected $_paths    = array();
    protected $_fields   = array();
    protected $_data     = array();
    protected $_idata    = null;
    protected $_settings = array();
    protected $_debug    = array();

    protected $_chunks  = array(); // Чанки из библиотек
    protected $_strings = array(); // Чанки строки

    protected $_language     = null;
    protected $_loadLanguage = false;
    protected $_dictionary   = null;

    protected $_maxLevel  = 32;
    protected $_level     = -1;
    protected $_arguments = array();
    protected $_notice    = array();

    protected $_template          = '';
    protected $_templateName      = '';
    protected $_templateExtension = 'html';
    protected $_autoTemplate      = false;

    protected $_tags     = null;
    protected $_tagsExts = null;

    protected $_prefix = 'quadbraces';

    /* CLASS:CONSTRUCT */
    function __construct($owner=null) {
      $this->startTime = 0;
      $this->startRAM  = 0;
      $this->_debug['points'] = array();
      $this->_owner    = $owner;
      // Поиск расширений
      $this->_tags = array();
      $tags = array();
      $_ = dirname(__FILE__).DIRECTORY_SEPARATOR.'tags'.DIRECTORY_SEPARATOR;
      if ($f = glob($_."*.php")) foreach ($f as $e) {
        $key = basename($e,'.php');
        if ($key == 'prototype') continue;
        $CN = "QuadBracesTag".ucfirst($key);
        $tags[$CN] = $key;
      }
      // Инициализация расширений
      foreach ($tags as $cn => $key) {
        if (!class_exists($cn,false)) if (is_file($_."$key.php")) require $_."$key.php";
        if (class_exists($cn,false)) { /** @var QuadBracesTagPrototype $to */
          $to = new $cn($this);
          $this->_tags[$key]     = $to->regexp;
          $this->_tagsExts[$key] = $to;
        }
      }
      // Установка тегов
      $tags = self::tags();
      foreach ($tags as $cn => $key) $this->_tags[$cn] = $key;
      // Опции
      $this->notice = 'common';
    }

    /* CLASS:GET */
    function __get($n) {
      switch ($n) {
        case 'startTime': return $this->_debug['start']['time'];
        case 'startRAM' : return $this->_debug['start']['ram'];
      }
      if (in_array($n,array('content'))) return $this->variable($n);
      if (method_exists($this,"get_$n"))      { $f = "get_$n"; return $this->$f();
      } elseif (property_exists($this,"_$n")) { $f = "_$n";    return $this->$f; }
      return false;
    }

    /* CLASS:SET */
    function __set($n,$v) {
      switch ($n) {
        case 'data'    : $this->_data     = self::megreData($this->_data,$v);     return $this->_data;
        case 'settings': $this->_settings = self::megreData($this->_settings,$v); return $this->_settings;
        case 'loadLanguage': $this->_loadLanguage = self::bool($v); return $this->_loadLanguage;
        case 'autoTemplate': $this->_autoTemplate = self::bool($v); return $this->_autoTemplate;
        case 'prefix'  : if (!empty($v)) $this->_prefix = strval($v);   return $this->_prefix;
        case 'maxLevel': $this->_maxLevel = intval($v); return $this->_maxLevel;
        case 'dictionary':
          if (!is_array($v)) return false;
          $this->_dictionary = $v;
          return $this->_dictionary;
        case 'paths':
          if (!is_array($v)) return false;
          $this->_paths = self::paths($v);
          return $this->_paths;
        case 'startTime': case 'startRAM' :
          $k = $n == 'startTime' ? 'time' : 'ram';
          $this->_debug['start'][$k] = $k == 'time' ? floatval($v) : intval($v);
          return $this->_debug['start'][$k];
      }
      if (method_exists($this,"set_$n")) { $f = "set_$n"; return $this->$f($v); }
      return false;
    }

    /* CLASS:CALL */
    function __call($n,$p) {
      if (preg_match('/^on(\w+)$/',$n)) {
        $e = lcfirst(preg_replace('/^on(\w+)$/','\1',$n));
        return $this->_callEvent($e,$p);
      } elseif (isset($this->_methods[$n])) {
        return call_user_func_array($this->_methods[$n],$p);
      }
      return (!$this->error("method not exists",$n));
    }

    /* CLASS:STRING */
    function __toString() { return $this->parse(); }

    /******** АКСЕССОРЫ ********/
    /* Язык */
    protected function set_language($v) {
      if (!empty($v)) $this->_language = strval($v);
      if ($this->_loadLanguage) $this->_dictionary = self::loadLanguage($this->_language);
      return $this->_language;
    }

    /* Оповещения */
    protected function set_notice($v) {
      static $_tagKeys = null;
      if (is_null($_tagKeys)) {
        $_tagKeys   = array_keys($this->_tags);
        $_tagKeys[] = 'custom';
      }
      if (!is_int($v) && !is_numeric($v)) {
        switch ($v) {
          case 'strict': $this->_notice = $_tagKeys; break;
          case 'common': $this->_notice = array('chunk','string','lib','snippet','custom'); break;
          default:
            $this->_notice = array();
            $TMP = is_array($v) ? $v : explode(',',$v);
            foreach ($TMP as $Ti) if (in_array($Ti,$_tagKeys)) $this->_notice[] = $Ti;
        }
      }
      return $this->_notice;
    }

    /* Шаблон */
    protected function set_template($v) {
      $content = '[*content*]';
      $this->_templateName = '';
      if (!empty($v))
        if ($fn = $this->search('template',$v)) {
          $content = @file_get_contents($fn);
          $this->_templateName = $v;
        }
      $_ = self::extractFields($content);
      $this->_fields   = $_['fields'];
      foreach ($this->_fields as $alias => $data) $this->variable($alias,$data['default']);
      $content = $_['body'];
      $_ = self::extractData($content,null,$this->_prefix);
      $this->_template = $_['body'];
      $this->data      = $_['data'];
      return $this->_template;
    }

    /* Контент */
    protected function set_content($v) {
      $_ = self::extractData($v,null,$this->_prefix);
      $this->variable('content',$_['body']);
      if ($this->_autoTemplate)
        $this->template = isset($_['data']['template']) ? $_['data']['template'] : '';
      $this->data = $_['data'];
      return $this->variable('content');
    }

    /******** ВНУТРЕННИЕ МЕТОДЫ ********/
    /* Вызов события */
    protected function _callEvent($e,$args) {
      if (!isset($this->_events[$e]))    return true;
      if (!is_array($this->_events[$e])) return true;
      $ret = true;
      foreach ($this->_events[$e] as $func) {
        // Превентивная проверка возможности вызова хука
        $EX = false;
        if (is_array($func)) {
          $obj = $func[0];
          $met = $func[1];
          if (is_object($func[0])) $EX = method_exists($obj,$met);
        } else { $EX = function_exists($func); }
        // Вызов хука
        if ($EX) {
          $res  = call_user_func_array($func,$args);
          $ret &= ((strval($res)=='true')||($res===true)||(intval($res)>0));
        }
        if (!$ret) break;
      }
      return $ret;
    }

    /* Обработка чанка */
    protected function _parse_chunk($m,$t='chunk') {
      $key = $this->parseStart($m);
      $v   = $this->getChunk($key,$t);
      if ($v === false) {
        if (in_array($t,$this->_notice)) return "<!-- not found: $t/$key -->";
        $v = '';
      }
      return $this->parseFinish($m,$t,$key,$v);
    }

    /* Обработка переменной */
    protected function _parse_data($m,$type='variable') {
      $key = $this->parseStart($m);
      $val = $this->$type($key);
      if ($val === false) {
        if (in_array($type,$this->_notice)) return "<!-- not found: $type/$key -->";
        $val = '';
      }
      if (is_array($val)) $val = var_export($val,true);
      return $this->parseFinish($m,$type,$key,$val);
    }

    /******** КОМПОНЕНТНЫЕ МЕТОДЫ ********/
    /* CLASS:METHOD
      @name        : iteration
      @description : Итерация

      @param : $tpls | array  | value |        | Шаблоны
      @param : $O    | string | value | @EMPTY | Входные данные

      @return : string
    */
    public function iteration(array $tpls,$O='') {
      $tplI = "#\[\+([\w\-\.]+)\[([^\]]*)\]((:?".(self::extRex).")*)\+\]#si";
      $H = false;
      foreach ($tpls as $tpl) {
        if (preg_match($tpl[0],$O)) {
          $O = preg_replace($tpl[0],'[+\1['.$tpl[1].']\2+]',$O);
          $H = true;
        }
      }
      if ($H) $O = preg_replace_callback($tplI,array($this,"parseInternal"),$O);
      return $O;
    }

    /* CLASS:METHOD
      @name        : parseInternal
      @description : Внутренняя обработка

      @param : $m | array | value | | Данные от регулярки

      @return : string
    */
    public function parseInternal(array $m) {
      $v = $m[2];
      if (isset($m[3])) $v = $this->extensions($v,$m[3],true);
      return $v;
    }

    /* CLASS:METHOD
      @name        : parseStart
      @description : Начало обработки

      @param : $m | array | value | | Данные от регулярки

      @return : string | ключ элемента
    */
    public function parseStart(array $m) {
      $this->_arguments[$this->_level] = isset($m[8]) ? self::arguments($m[8]) : array();
      return $m[1];
    }

    /* CLASS:METHOD
      @name        : parseFinish
      @description : Конец обработки

      @param : $m | array | value | | Данные от регулярки

      @return : string
    */
    public function parseFinish(array $m,$etype,$k,$v='') {
      if (isset($m[2])) $v = $this->extensions($v,$m[2]);
      return ($v != '') ? $this->parse($v,null,$etype,$k) : '';
    }

    /******** ОБРАБОТЧИКИ ПАРСЕРА ********/
    /* Чанки */
    public function parse_chunk($m)  { return $this->_parse_chunk($m); }
    public function parse_string($m) { return $this->_parse_chunk($m,'string'); }
    public function parse_lib($m)    { return $this->_parse_chunk($m,'lib'); }

    /* Константы */
    public function parse_constant($m) {
      $key = $this->parseStart($m);
      if (empty($key) || !defined($key)) {
        if (in_array('constant',$this->_notice)) return "<!-- not found: constant/$key -->";
        $v = '';
      } else { $v = constant($key); }
      return $this->parseFinish($m,'constant',$key,$v);
    }

    /* Настройки, переменные, локальные плейсхолдеры */
    public function parse_setting($m)  { return $this->_parse_data($m,'setting'); }
    public function parse_variable($m) { return $this->_parse_data($m,'variable'); }
    public function parse_local($m) {
      $key = $this->parseStart($m);
      $v = isset($this->_arguments[$this->_level-1][$key]) ? $this->_arguments[$this->_level-1][$key] : '';
      if (is_array($v)) $v = var_export($v,true);
      return $this->parseFinish($m,'local',$key,$v);
    }

    /* Язык */
    public function parse_language($m) {
      $key = $this->parseStart($m);
      $v   = $this->word($key);
      return $this->parseFinish($m,'language',$key,$v);
    }

    /* Сниппеты */
    public function parse_snippet($m) {
      $key = $this->parseStart($m);
      $v   = '';
      if ($_ = $this->execute($key,$this->_arguments[$this->_level])) {
        $v = strval($_);
      } else {
        if (($_ === false) && in_array('snippet',$this->_notice))
          return "<!-- not found: snippet/$key -->";
      }
      return $this->parseFinish($m,'snippet',$key,$v);
    }

    /* Отладка */
    public function parse_debug($m) {
      $key = $this->parseStart($m);
      $at = array('ms','us','ns');
      $am = array('kb','mb','gb');
      $dd = explode('.',$key);
      $un = count($dd) > 1 ? $dd[count($dd) - 1] : '';
      if (!empty($un)) {
        if (in_array($un,$at) || in_array($un,$am)) {
          unset($dd[count($dd) - 1]);
        } else { $un = ''; }
      }
      $mt = '';
      if ((count($dd) > 1) && in_array($dd[count($dd)-1],array('time','ram'))) {
        $mt = $dd[count($dd)-1];
        unset($dd[count($dd)-1]);
      }
      $key = implode('.',$dd);
      $P = strtoupper($this->prefix);
      switch ($key) {
        case 'time':
          $K = $this->debugPoint(array(
            'type' => 'time',
            'key'  => 'current',
            'value' => microtime(true) - $this->startTime,
            'unit'  => (in_array($un,$at) ? $un : '')
          ));
          $v = "<!-- $P:$K -->";
          break;
        case 'ram':
          $K = $this->debugPoint(array(
            'type' => 'ram',
            'key'  => 'current',
            'value' => memory_get_usage() - $this->startRAM,
            'unit'  => (in_array($un,$am) ? $un : '')
          ));
          $v = "<!-- $P:$K -->";
          break;
        case 'parser':
        case 'total' :
          if (!empty($mt)) {
            $K = $this->debugPoint(array(
              'type'  => $mt,
              'key'   => $key,
              'unit'  => (in_array($un,($mt == 'ram' ? $am : $at)) ? $un : '')
            ));
            $v = "<!-- $P:$K -->";
          }
          break;
        case 'db.count': $v = "<!-- ".strtoupper($this->prefix.":db.count")." -->"; break;
        default: $v = '';
      }
      if (empty($v)) {
        if (!isset($this->debug[$key])) {
          if (in_array('debug',$this->notice)) return "<!-- not found: debug/$key -->";
          $v = '';
        } else { $v = $this->debug[$key]; }
      }
      return $this->parseFinish($m,'debug',$key,$v);
    }

    /******** МЕТОДЫ ДЛЯ ПОЛУЧЕНИЯ ДАННЫХ ********/
    /* CLASS:METHOD
      @name        : setting
      @description : Чтение, запись "настройки" шаблонизатора

      @param : $key   | string | value |       | Ключ
      @param : $value |        | value | @NULL | Значение

      @return : ?
    */
    public function setting($key,$value=null) {
      if (!is_null($value)) {
        self::setByKey($this->_settings,$key,$value);
        return $value;
      } else { return self::getByKey($this->_settings,$key); }
    }

    /* CLASS:METHOD
      @name        : variable
      @description : Чтение, запись переменной шаблонизатора

      @param : $key   | string | value |       | Ключ
      @param : $value |        | value | @NULL | Значение

      @return : ?
    */
    public function variable($key,$value=null) {
      if (!is_null($value)) {
        if (is_array($this->_idata)) {
          self::setByKey($this->_idata,$key,$value);
        } else { self::setByKey($this->_data,$key,$value); }
        return $value;
      } else {
        if (is_array($this->_idata)) {
          return self::getByKey($this->_idata,$key);
        } else { return self::getByKey($this->_data,$key); }
      }
    }

    /* CLASS:METHOD
      @name        : word
      @description : Слово из словаря

      @param : $key | string | value | | Ключ

      @return : ?
    */
    public function word($key) {
      $P = 'caption';
      $K = self::languageKey($key,$P);
      if (isset($this->_dictionary[$K][$P])) return $this->_dictionary[$K][$P];
      return ucfirst(str_replace(array('.','_','-'),' ',$key));
    }

    /* CLASS:METHOD
      @name        : search
      @description : Поиск элемента

      @param : $type | string | value | | Тип элемента
      @param : $name | string | value | | Имя элемента

      @param : string
    */
    public function search($type,$name) {
      $ext = $this->_templateExtension;
      $DS  = DIRECTORY_SEPARATOR;

      if (!in_array($type,array('template','chunk','snippet'))) return false;
      $sdir = $type.'s';
      if ($type == 'snippet') $ext = 'php';

      $dname = explode('.',$name);
      $ename = $dname[count($dname)-1];
      unset($dname[count($dname)-1]);
      $dname = count($dname) > 0 ? implode($DS,$dname).$DS : '';
      $found = '';

      $paths = $this->paths;
      $lang  = $this->language;

      foreach ($paths as $epath) {
        $D = $epath.$sdir.DIRECTORY_SEPARATOR.$dname;
        $fname = $D."$ename.$ext";
        if (is_file($fname)) $found = $fname;
        if ($lang != '') {
          $fname = $D.$lang.DIRECTORY_SEPARATOR."$ename.$ext";
          if (is_file($fname)) $found = $fname;
        }
      }

      return $found;
    }

    /* CLASS:METHOD
      @name        : getChunk
      @description : Получение чанка

      @param : $key | string | value | | Имя чанка

      @return : string
    */
    public function getChunk($key,$type='chunk') {
      switch ($type) {
        case 'string':
          list($K,$I) = self::keys($key);
          if (!isset($this->_strings[$K])) {
            if ($fn = $this->search('chunk',$K)) {
              $this->_strings[$K] = @file($fn);
            } else { return false; }
          }
          return isset($this->_strings[$K][$I]) ? $this->_strings[$K][$I] : false;
        case 'lib':
          list($K,$I) = self::keys($key);
          if (!isset($this->_chunks[$K])) {
            if ($fn = $this->search('chunk',$K)) {
              $fc = @file_get_contents($fn);
              $this->_chunks[$K] = preg_split('~\<\!\-\- tags:splitter \-\-\>~si',$fc);
            } else { return false; }
          }
          return isset($this->_chunks[$K][$I]) ? $this->_chunks[$K][$I] : false;
        default: if ($fn = $this->search('chunk',$key)) return @file_get_contents($fn);
      }
      return false;
    }

    /* CLASS:METHOD
      @name        : execute
      @description : Выполнение сниппета или расширения

      @param : $name | string | value |        | Имя сниппета
      @param : $A    | array  | value | @EMPTY | Аргументы
      @param : $I    | string | value | @EMPTY | Входные данные

      @return : int/string
    */
    public function execute($name,$A=array(),$I='') {
      $result = '';            /** @noinspection PhpUnusedLocalVariableInspection */
      $CMS    = $this->_owner; /** @noinspection PhpUnusedLocalVariableInspection */
      $parser = $this;         /** @noinspection PhpUnusedLocalVariableInspection */
      $input  = strval($I);    /** @noinspection PhpUnusedLocalVariableInspection */
      $arguments = $A;
      if ($fn = $this->search('snippet',$name)) $result = include($fn);
      return strval($result);
    }

    /* CLASS:METHOD
      @name        : debugPoint
      @description : Установка точки диагностики

      @param : $data  | array  | value |       | Данные
      @param : $value | string | value | @NULL | Ключ

      @return : string | Ключ
    */
    public function debugPoint($data,$key=null) {
      $KEY = md5((is_null($key) ? microtime(true) : $key).rand(0,255));
      $this->_debug['points'][$KEY] = $data;
      return $KEY;
    }

    /******** МЕТОДЫ ПАРСЕРА ********/
    /* CLASS:METHOD
      @name        : parse
      @description : Обработка

      @param : $d   | string | value | @EMPTY | Входные данные
      @param : $elt | string | value | @EMPTY | Тип элемента
      @param : $key | string | value | @EMPTY | Ключ элемента

      @param : string
    */
    public function parse($d='',$data=null,$elt='',$key='') {
      static $_levels = null;
      static $_stime  = null;
      static $_smem   = null;
      // Инициализация
      $p1 =  is_null($_levels) ? 2 : 1;
      $O  = (is_null($_levels) && empty($d)) ? $this->template : strval($d);
      if (empty($O)) return $O;
      if (is_null($_levels)) $_levels = array();
      if (is_null($_stime))  $_stime  = microtime(true);
      if (is_null($_smem))   $_smem   = memory_get_usage();
      $this->_debug['time'] = 0;
      // Каркас обработки
      if (is_array($data)) $this->_idata = $data;
      for ($c1 = 0; $c1 < $p1; $c1++) {
        $this->_level++;
        if ($this->_level <= $this->_maxLevel) {
          $_levels[$this->_level] = array('element' => $elt,'key' => $key);
          $this->_debug['levels'] = $_levels;
          foreach ($this->_tags as $k => $t) {
            $m = null;
            if (isset($this->_tagsExts[$k]))           { $m = array($this->_tagsExts[$k],"parse");
            } elseif (method_exists($this,"parse_$k")) { $m = array($this,"parse_$k"); }
            if (!is_null($m)) if (preg_match($t,$O)) $O = preg_replace_callback($t,$m,$O);
          }
        } else { $O = self::sanitize($O); }
        $this->_level--;
      }
      if (is_array($data)) $this->_idata = null;
      // Финализация
      if ($this->_level == -1) {
        $O = self::sanitize($O);
        $O = $this->parseBenchmark($O,$_stime,$_smem);
        $_levels = null;
        $_stime  = null;
        $_smem   = null;
      }
      return $O;
    }

    /* CLASS:METHOD
      @name        : extensions
      @description : Обработка расширений

      @param : $value | string | value |        | Входные данные
      @param : $ext   | string | value | @EMPTY | Тип элемента

      @param : string
    */
    public function extensions($value,$ext='') {
      $RET = ''.trim($value);
      if (empty($ext)) return ''.$value;
      if ($_ = preg_match_all("#".(self::extRex)."#si",$ext,$ms,PREG_SET_ORDER)) {
        for($c = 0; $c < count($ms); $c++) {
          $a = $ms[$c][1];
          $v = isset($ms[$c][4]) ? $ms[$c][4] : '';
          if (self::isLogic($a)) {
            $cond  = self::condition($a,$value,$v);
            $cthen = self::isLogicFunction($a) ? $v : $RET;
            if (isset($ms[$c+1])) if ($ms[$c+1][1] == 'then') { $c++; $cthen = $ms[$c][4]; }
            $celse = '';
            if (isset($ms[$c+1])) if ($ms[$c+1][1] == 'else') { $c++; $celse = $ms[$c][4]; }
            $RET = $cond ? $cthen : $celse;
            if (preg_match($this->_tags['local'],$RET))
              $RET = preg_replace_callback($this->_tags['local'],array($this,"parse_local"),$RET);
          } elseif (in_array($a,array('import','css-link','js-link'))) {
            $RET = self::jscss($a,$RET);
          } elseif (in_array($a,array('link','link-external'))) {
            $RET = self::link($a,$RET,$v);
          } else {
            switch ($a) {
              case 'links': $RET = self::autoLinks($RET,$v); break;
              case 'include':
                if (!empty($v)) {
                  $_   = self::path("$RET/$v");
                  $RET = is_file($_) ? include($_) : '';
                }
                break;
              case 'ul':
              case 'ol': $RET = self::autoList($RET,$v,$a); break;
              case 'for':
                $v = intval($v);
                $start = 1;
                if ($ms[$c+1][1] == 'start') { $c++; $start = intval($ms[$c][4]); }
                $splt  = '';
                if ($ms[$c+1][1] == 'splitter') { $c++; $splt = $ms[$c][4]; }
                $_R  = array();
                for ($pos = $start; $pos <= ($v - $start); $pos++) {
                  $tpls = array(
                    array("#\[\+(iterator\.index)((:?".(self::extRex).")*)\+\]#si",$pos)
                  );
                  $_R[] = $this->iteration($tpls,$RET);
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
                    array("#\[\+(iterator\.index)((:?".(self::extRex).")*)\+\]#si",$pos),
                    array("#\[\+(iterator|iterator\.key)((:?".(self::extRex).")*)\+\]#si",$key)
                  );
                  $_R[] = $this->iteration($tpls,$RET);
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

    /******** МЕТОДЫ ДЛЯ РАБОТЫ С ОТЛАДОЧНЫМИ ДАННЫМИ ********/
    /* CLASS:METHOD
      @name        : timeValue
      @description : Значение времени

      @param : $value | float  | value |        | Значение
      @param : $unit  | string | value | @EMPTY | Единица

      @return : string
    */
    public function timeValue($value,$unit='') {
      $ret = $value;
      switch ($unit) {
        case 'ms': $ret *= 1000; break;
        case 'us': $ret *= 1000000; break;
        case 'ns': $ret *= 1000000000; break;
      }
      return strval(round($ret,2));
    }

    /* CLASS:METHOD
      @name        : timeValue
      @description : Значение времени

      @param : $value | float  | value |        | Значение
      @param : $unit  | string | value | @EMPTY | Единица

      @return : string
    */
    public function RAMValue($value,$unit='') {
      $ret = $value;
      switch ($unit) {
        case 'kb': $ret /= 1024; break;
        case 'mb': $ret /= 1048576; break;
        case 'gb': $ret /= 1073741824; break;
      }
      return strval(round($ret,2));
    }

    /* CLASS:METHOD
      @name        : parseBenchmark
      @description : Бенчмарки

      @param : $v  | string | value |   | Исходник
      @param : $pt | string | value | 0 | Стартовое время парсера
      @param : $pr | string | value | 0 | Стартовый объём ОЗУ парсера

      @return : string
    */
    public function parseBenchmark($v,$pt=0,$pr=0) {
      $O = $v;
      $P = $this->_prefix;
      foreach ($this->_debug['points'] as $pk => $pd) {
        $C = "<!-- $P:$pk -->";
        $V = '';
        switch ($pd['key']) {
          case 'current':
            switch ($pd['type']) {
              case 'time': $V = $this->timeValue($pd['value'],$pd['unit']); break;
              case 'ram' : $V = $this->RAMValue($pd['value'],$pd['unit']); break;
            }
            break;
          case 'parser':
            switch ($pd['type']) {
              case 'time': $V = $this->timeValue(microtime(true) - $pt,$pd['unit']); break;
              case 'ram' : $V = $this->RAMValue(memory_get_usage() - $pr,$pd['unit']); break;
            }
            break;
          case 'total':
            switch ($pd['type']) {
              case 'time': $V = $this->timeValue(microtime(true)   - $this->startTime,$pd['unit']); break;
              case 'ram' : $V = $this->RAMValue(memory_get_usage() - $this->startRAM,$pd['unit']); break;
            }
            break;
        }
        $O = str_replace($C,$V,$O);
      }
      return $O;
    }

    /******** МЕТОДЫ ДЛЯ РАБОТЫ С СОБЫТИЯМИ ********/
    /* CLASS:METHOD
      @name        : registerEvent
      @description : Регистрация события

      @param : $event | string   | value |       | Название функции
      @param : $func  | callable | value | @NULL | Функция

      @param : bool
    */
    public function registerEvent($n,$f=null) {
      if (preg_match(self::funcRex,$n)) {
        if (!isset($this->_events[$n])) $this->_events[$n] = array();
        if (is_callable($f,true)) {
          $this->_events[$n][] = $f;
          return true;
        }
      } else { return (!$this->error("Invalid event name",$n)); }
      return true;
    }

    /* CLASS:METHOD
      @name        : registerMethod
      @description : Регистрация метода

      @param : $name | string   | value |       | Название функции
      @param : $func | callable | value | @NULL | Функция

      @param : bool
    */
    public function registerMethod($n,$f=null) {
      if (preg_match(self::funcRex,$n)) {
        if (is_callable($f,true)) {
          $this->_methods[$n] = $f;
          return true;
        }
      } else { return (!$this->error("Invalid method name",$n)); }
      return false;
    }

    /* CLASS:METHOD
      @name        : invoke
      @description : Вызов события

      @param : [1] | string | value | | Название события
      @params : аргументы функции

      @return : ?
    */
    public function event() {
      $fargs = func_get_args();
      if (!isset($fargs[0])) return true;
      $n = array_shift($fargs);
      return $this->_callEvent($n,$fargs);
    }

    /******** ПРОЧИЕ МЕТОДЫ ********/
    /* CLASS:METHOD
      @name        : error
      @description : Ошибка

      @return : ?
    */
    public function error() {
      $a = func_get_args();
      if (!$this->_callEvent('error',$a)) return false;
      throw new Exception(implode('|',$a));
    }

    /******** ОБЩИЕ БИБЛИОТЕЧНЫЕ МЕТОДЫ ********/
    /* CLASS:STATIC
      @name        : regexp
      @description : Регулярное выражение

      @param : $start  | string | value | | Начало
      @param : $finish | string | value | | Конец

      @return : string
    */
    public static function regexp($S,$F) {
      return "#$S([\w\.\-]+)((:?".(self::extRex).")*)((".(self::argRex).")*)$F#si";
    }

    /* CLASS:STATIC
      @name        : tags
      @description : Инициализация тегов

      @return : array
    */
    public static function tags() {
      static $_tags = null;
      if (is_null($_tags)) {
        $map = array(
          'local'    => array('\[\+','\+\]'),
          'chunk'    => array('\{\{','\}\}'),
          'string'   => array('\{\[','\]\}'),
          'lib'      => array('\{\(','\)\}'),
          'constant' => array('\{\*','\*\}'),
          'setting'  => array('\[\(','\)\]'),
          'variable' => array('\[\*','\*\]'),
          'debug'    => array('\[\^','\^\]'),
          'language' => array('\[\%','\%\]'),
          'snippet'  => array('\[\!','\!\]')
        );
        $_tags = array();
        foreach ($map as $k => $d) $_tags[$k] = self::regexp($d[0],$d[1]);
      }
      return $_tags;
    }

    /* CLASS:STATIC
      @name        : arguments
      @description : Аргументы

      @param : $v | string | value | | Исходная строка

      @return : array
    */
    public static function arguments($v) {
      $arguments = array();
      if (!empty($v))
        if ($_ = preg_match_all('#'.(self::argRex).'#si',$v,$ms,PREG_SET_ORDER))
          foreach ($ms as $pr) $arguments[$pr[1]] = $pr[2];
      return $arguments;
    }

    /* CLASS:STATIC
      @name        : sanitize
      @description : Очищение от тегов

      @param : $data | string | value | @EMPTY | Текстовые данные

      @return : string
    */
    public static function sanitize($data='') {
      $tags = self::tags();
      if (empty($data)) return '';
      $O = $data;
      foreach ($tags as $t) if (preg_match($t,$O)) $O = preg_replace($t,'',$O);
      return $O;
    }

    /******** ПРОЧИЕ ФУНКЦИИ ********/
    /* CLASS:STATIC
      @name        : bool
      @description : Булево значение

      @param : $v | | value | | входное значение

      @return : bool
    */
    public static function bool($v) { return ((strval($v)==='true')||($v===true)||(intval($v)>0)); }

    /******** ФУНКЦИИ ДЛЯ РАБОТЫ С МЕТАДАННЫМИ ********/
    /* CLASS:STATIC
      @name        : extractFields
      @description : Извлечь метаполя

      @param : $tpl | string | value |       | Код шаблона

      @return : array | массив:
                        элемент body содержит очищенный шаблон,
                        элемент data содержит извлечённые данные
    */
    public static function extractFields($tpl) {
      $out = array('fields' => array(),'body' => '');
      $rex = '#\<\!--(?:\s+)FIELD\:([\w\.\-]+)(('.(self::argRex).')*)(?:\s+)--\>#si';
      $out['rex'] = $rex;
      if (preg_match_all($rex,$tpl,$am,PREG_SET_ORDER)) foreach ($am as $d) {
        $key  = $d[1];
        $data = empty($d[2]) ? array() : self::arguments($d[2]);
        $out['fields'][$key] = array(
          'default' => isset($data['default']) ? $data['default']      : '',
          'type'    => isset($data['type'])    ? intval($data['type']) : 0,
          'caption' => isset($data['caption']) ? $data['caption']      : ''
        );
      }
      $out['body'] = trim(preg_replace($rex,'',$tpl));
      return $out;
    }

    /* CLASS:STATIC
      @name        : extractData
      @description : Извлечь метаданные

      @param : $tpl | string | value |       | Код шаблона
      @param : $def | array  | value | @NULL | Данные по умолчанию
      @param : $cln | string | value | @NULL | Имя класса

      @return : array | массив:
                        элемент body содержит очищенный шаблон,
                        элемент data содержит извлечённые данные
    */
    public static function extractData($tpl,$def=null,$cln='') {
      $TPL = $tpl;
      $out = array('data' => array(),'body' => '');
      $rex = '#\<\!--(?:\s+)DATA'
           . (empty($cln) ? '' : '(?:\s+)'.$cln)
           . '\:\[+key+\](?:\s+)`([^\`]*)`(?:\s+)--\>#si';
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
        $rex = str_replace('[+key+]','([\w\-\.]+)',$rex);
        $out['rex'] = $rex;
        if (preg_match_all($rex,$TPL,$am,PREG_SET_ORDER))
          foreach ($am as $d) $out['data'][$d[1]] = $d[2];
        $TPL = preg_replace($rex,'',$TPL);
      }
      $out['body'] = trim($TPL);
      return $out;
    }

    /******** ФУНКЦИИ ДЛЯ РАБОТЫ С СОСТАВНЫМИ КЛЮЧАМИ ********/
    /* CLASS:STATIC
      @name        : getByKey
      @description : Получить значение по ключу

      @param : $input | array | value | | Входное значение
      @param : $key   | array | value | | Ключ

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

    /* CLASS:STATIC
      @name        : setByKey
      @description : Установить значение по ключу

      @param : $input | array | value | | Входное значение
      @param : $key   | array | value | | Ключ
      @param : $value |       | value | | Значение

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

    /* CLASS:STATIC
    @name        : keys
    @description : Разделение ключей

    @param : $key | string | value | | Исходный ключ

    @return : array
  */
    public static function keys($key) {
      $pkey = explode('.',$key);
      $item = intval($pkey[count($pkey)-1]);
      unset($pkey[count($pkey)-1]);
      $pkey = implode('.',$pkey);
      return array($pkey,$item);
    }

    /* CLASS:STATIC
      @name        : mergeData
      @description : Слить данные

      @param : $input | array | value | | Входной массив
      @param : $value | array | value | | Значение

      @return : array
    */
    public static function megreData($input,$value) {
      if (!is_array($value) || !is_array($input)) return $input;
      if (empty($input)) return $value;
      $ret = $input;
      foreach ($value as $k => $v) $ret[$k] = $v;
      return $ret;
    }

    /******** ФУНКЦИИ ДЛЯ РАБОТЫ С ФАЙЛОВОЙ СИСТЕМОЙ ********/
    /* CLASS:STATIC
      @name        : path
      @description : Корректировать путь

      @param : $path | string | value | | Путь

      @return : string
    */
    public static function path($path) {
      $_ = DIRECTORY_SEPARATOR;
      $T = ($_ == '/' ? '/' : '').$_;
      $T = rtrim($path,$T);
      return implode($_,explode('/',$T)).$_;
    }

    /* CLASS:STATIC
      @name        : paths
      @description : Корректировать пути

      @param : $path | string | value | | Путь

      @return : string
    */
    public static function paths($v) {
      $ret = array();
      $_   = is_array($v) ? $v : explode(',',$v);
      foreach ($_ as $path) {
        $vv = self::path($path);
        if (is_dir($vv)) if (!in_array($vv,$ret)) $ret[] = $vv;
      }
      return $ret;
    }

    /* CLASS:STATIC
      @name        : removeFolder
      @description : Удаление папки

      @param : $d | string | value | | Директория

      @return : bool
    */
    public static function removeFolder($d) {
      if ($c = glob($d.DIRECTORY_SEPARATOR.'*'))
        foreach($c as $i) is_dir($i) ? self::removeFolder($i) : unlink($i);
      return rmdir($d);
    }

    /* CLASS:STATIC
      @name        : bytes
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

    /******** РАСШИРЕНИЯ ********/
    /* CLASS:STATIC
      @name        : link
      @description : Ссылки

      @param : $a     | string | value | | Тип
      @param : $value | string | value | | Значение
      @param : $add   | string | value | | Дополнительные данные

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

    /* CLASS:STATIC
      @name        : jscss
      @description : Ссылки JS, CSS

      @param : $a     | string | value | | Тип
      @param : $value | string | value | | Значение

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

    /******** ЛОГИЧЕСКИЕ РАСШИРЕНИЯ ********/
    /* CLASS:STATIC
      @name        : condition
      @description : Условие

      @param : $cond | string | value | | Условие
      @param : $v1   |        | value | | Первая переменная
      @param : $v2   |        | value | | Вторая переменная

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

    /* CLASS:STATIC
      @name        : isLogic
      @description : Признак условия

      @param : $cond | string | value | | Условие

      @return : bool
    */
    public static function isLogic($cond) {
      return in_array($cond,array(
        'is','eq','isnot','neq','lt','lte','gt','gte',
        'even','odd','notempty','empty','null','isnull','notnull','isarray'
      ));
    }

    /* CLASS:STATIC
      @name        : isLogicFunction
      @description : Признак условия

      @param : $cond | string | value | | Условие

      @return : bool
    */
    public static function isLogicFunction($cond) {
      return in_array($cond,array(
        'even','odd','notempty','empty','null','isnull','notnull','isarray'
      ));
    }

    /******** ФУНКЦИИ ДЛЯ РАБОТЫ С ТЕКСТОМ ********/
    /* CLASS:STATIC
      @name        : placeholders
      @description : Простая замена

      @param : $data  | array  | value |        | данные
      @param : $value | string | value | @EMPTY | Шаблон

      @return : string
    */
    public static function placeholders($data,$value='') {
      $O = $value;
      foreach ($data as $key => $val) $O = str_replace("[+$key+]",$val,$O);
      return $O;
    }

    /* CLASS:STATIC
      @name        : autoLinks
      @description : Автоматическое преобразование ссылок

      @param : $data  | string | value | | Исходное значение
      @param : $value | string | value | | Атрибуты ссылок

      @return : string
    */
    public static function autoLinks($data,$value) {
      $rexURL    = '#^(http|https)\:\/\/([\w\-\.]+)\.([a-zA-Z0-9]{2,16})\/(\S*)$#si';
      $rexMailTo = '#^mailto\:([\w\.\-]+)\@([a-zA-Z0-9\.\-]+)\.([a-zA-Z0-9]{2,16})$#si';
      $RET = $data;
      foreach (array(
                 array($rexURL,'<a href="\1://\2.\3/\4"[+C+]>\1://\2.\3/\4</a>'),
                 array($rexMailTo,'<a href="mailto:\1@\2.\3">\1@\2.\3</a>',)
               ) as $item)
        $RET = preg_replace($item[0],$item[1],$RET);
      // [+C+] - атрибуты ссылок
      return str_replace('[+C+]',(empty($value) ? '' : " $value"),$RET);
    }

    /* CLASS:STATIC
      @name        : autoList
      @description : Списки

      @param : $value | string | value |        | Атрибуты ссылок
      @param : $row   | string | value | @NULL  | Шаблон ряда
      @param : $ord   | bool   | value | @FALSE | Порядковый список

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

    /******** ФУНКЦИИ ДЛЯ РАБОТЫ С ЛОКАЛИЗАЦИЕЙ ********/
    /* CLASS:STATIC
      @name        : languageKey
      @description : Ключ языкового плейсхолдера

      @param : $key | string | value | | Ключ
      @param : $p   | string | link  | | Полученное свойство

      @return : string
    */
    public static function languageKey($key,&$p) {
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

    /* CLASS:STATIC
      @name        : loadLanguage
      @description : Сканирование языковой папки и получения слооварных данных

      @param : $lang | string | value | | Язык

      @return : array
    */
    public static function loadLanguage($lang=null,$paths=null) {
      static $_sup    = null;
      static $_pcache = null;
      if (is_null($_sup))   $_sup = array('caption','hint');
      if (!is_null($paths)) $_pcache = self::paths($paths);
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

    /* CLASS:STATIC
      @name        : accepted
      @description : Допустимые языки

      @return : array
    */
    public static function accepted($renew=false,$def='en') {
      static $ret = null;
      if (is_array($ret) && !$renew) return $ret;
      $ret = array();
      if ($s = $_SERVER['HTTP_ACCEPT_LANGUAGE']) {
        if ($l = explode(',',$s)) {
          foreach ($l as $li) {
            if ($_ = explode(';',$li)) {
              $key = explode('-',$_[0]);
              $key = strtolower($key[0]);
              if (ctype_alnum($key)) {
                $val = floatval(isset($_[1]) ? $_[1] : 1);
                $ret[$key] = $val;
              }
            }
          }
          arsort($ret,SORT_NUMERIC);
          $ret = array_keys($ret);
        }
      }
      if (empty($ret)) $ret[] = $def;
      if (!empty(self::$_all)) {
        $tmp = $ret;
        foreach ($ret as $k => $l) if (!in_array($l,self::$_all)) unset($tmp[$k]);
        $tmp = array_values($tmp);
        $ret = $tmp;
      }
      return $ret;
    }
  }
  /* CLASS ~END */

  /* INFO @copyright: Xander Bass, 2016 */
?>