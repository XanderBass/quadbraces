<?php
  /**
   * QuadBracesParser
   * Парсер синтаксиса подобного MODX / Etomite
   *
   * Предназначен для того, чтобы не заколачивать экскаватором гвозди, то бишь
   * не устанавливать MODX для целевых страниц. Также предназначен в качестве
   * унифицированного стандарта шаблонизации.
   *
   * @version 3.0.0
   * @author  Xander Bass
   */

  /* INFO
    @product     : QuadBracesParser
    @component   : QuadBracesParser
    @type        : class
    @description : Главный класс
    @revision    : 2016-11-23 06:15:00
  */

  if (!defined("QUADBRACES_TPL_PATH")) {
    $_ = implode(DIRECTORY_SEPARATOR,array(
      realpath($_SERVER['DOCUMENT_ROOT']),
      'content','template'
    )).DIRECTORY_SEPARATOR;
    define("QUADBRACES_TPL_PATH",$_);
  }
  if (!defined("QUADBRACES_LOCALIZED")) define("QUADBRACES_LOCALIZED",false);

  foreach (array('lib','files','exts') as $k)
    if (!class_exists('QuadBraces'.ucfirst($k),false)) require "lib/{$k}.php";
  if (QUADBRACES_LOCALIZED && !class_exists('QuadBracesLang',false)) require 'lib/lang.php';

  /* CLASS ~BEGIN @string : Обработанные данные */
  /**
   * Class QuadBracesParser
   * @property      mysqli $db
   *
   * @property      object $owner
   *
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
   * @property      bool   $autoTemplate
   *
   * @property-read array $tags
   * @property-read array $tagsExts
   *
   * @property string $prefix
   * @property string $content
   *
   * @property float $startTime
   * @property int   $startRAM
   *
   * @property      array $resources
   * @property-read array $idx
   * @property      bool  $SEOStrict
   */
  class QuadBracesParser {
    const funcRex = '#^([[:alpha:]])(\w+)([[:alpha:]\d])$#si';

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
    protected $_tagsSort = null;
    protected $_tagsExts = null;

    protected $_prefix = 'quadbraces';

    protected $_resources = null;
    protected $_idx       = null;
    protected $_SEOStrict = false;

    /* CLASS:CONSTRUCT */
    function __construct($paths=null,$owner=null) {
      $this->startTime = 0;
      $this->startRAM  = 0;
      $this->_debug['points'] = array();
      $this->_owner    = $owner;
      // Поиск расширений
      $this->_tags     = array();
      $this->_tagsSort = array();
      $this->_tagsExts = array();
      foreach (array('data','tag') as $tt) {
        $_ = dirname(__FILE__).DIRECTORY_SEPARATOR.$tt.DIRECTORY_SEPARATOR;
        if ($f = glob($_."*.php")) foreach ($f as $e) {
          $key = basename($e,'.php');
          if ($key == 'prototype') continue;
          $cn = "QuadBraces".ucfirst($tt).ucfirst($key);
          if (!class_exists($cn,false))
            if (is_file($_."$key.php")) require $_."$key.php";
          if (class_exists($cn,false)) new $cn($this);
        }
      }
      $this->notice = 'common';
      if (!is_null($paths)) $this->paths = $paths;
      $this->invoke('init');
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
        case 'data':
          if ($this->invoke('beforeChangeData',$v)) {
            $this->_data = QuadBracesLib::megreData($this->_data,$v);
            $this->invoke('changeData',$v);
          }
          return $this->_data;
        case 'settings':
          if ($this->invoke('beforeChangeSettings',$v)) {
            $this->_settings = QuadBracesLib::megreData($this->_settings,$v);
            $this->invoke('changeSettings',$v);
          }
          return $this->_settings;
        case 'loadLanguage':
          $this->_loadLanguage = QuadBracesLib::bool($v);
          return $this->_loadLanguage;
        case 'autoTemplate':
          $this->_autoTemplate = QuadBracesLib::bool($v);
          return $this->_autoTemplate;
        case 'prefix':
          $this->_prefix = strval($v);
          return $this->_prefix;
        case 'maxLevel':
          $this->_maxLevel = intval($v);
          return $this->_maxLevel;
        case 'dictionary':
          if (!is_array($v)) return false;
          if ($this->invoke('beforeLoadDictionary',$v)) {
            $this->_dictionary = $v;
            $this->invoke('loadDictionary',$v);
          }
          return $this->_dictionary;
        case 'paths':
          $this->_paths = QuadBracesFiles::paths($v);
          return $this->_paths;
        case 'startTime': case 'startRAM':
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
        $e    = preg_replace('/^on(\w+)$/','\1',$n);
        $e[0] = strtolower($e[0]);
        return $this->_callEvent($e,$p);
      } elseif (isset($this->_methods[$n])) {
        return call_user_func_array($this->_methods[$n],$p);
      }
      if (!$this->invoke('methodNotFound')) return false;
      return (!$this->error("method not exists",$n));
    }

    /* CLASS:STRING */
    function __toString() { return $this->parse(); }

    /* **************** АКСЕССОРЫ **************** */
    /* Язык */
    protected function set_language($v) {
      if (!empty($v)) $this->_language = strval($v);
      if ($this->_loadLanguage && class_exists('QuadBracesLang',false))
        if ($this->invoke('beforeLoadLanguage',$v)) {
          $this->dictionary = QuadBracesLang::load($this->_language);
          $this->invoke('loadLanguage',$v);
        }
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
    protected function set_template($v) { return $this->setTemplate($v); }

    /* Контент */
    protected function set_content($v) {
      $_ = QuadBracesLib::extractData($v,null,$this->_prefix);
      if ($this->invoke('setContent',$_['body']))
        $this->variable('content',$_['body']);
      if ($this->_autoTemplate)
        $this->template = empty($_['data']['template']) ? '' : $_['data']['template'];
      $this->data = $_['data'];
      return $this->variable('content');
    }

    /* Ресурсы */
    protected function set_resources($v) {
      if (!is_array($v)) return false;
      if ($this->invoke('setResources',$v)) {
        $this->_resources = $v;
        $this->_idx       = array();
        foreach ($this->_resources as $id => $data) {
          $pid = isset($data['parent']) ? intval($data['parent']) : 0;
          if (!isset($this->_idx[$pid])) $this->_idx[$pid] = array();
          $this->_idx[$pid][] = $id;
        }
      }
      return $this->_resources;
    }

    /* **************** ВНУТРЕННИЕ МЕТОДЫ **************** */
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
        } else {
          if (!$this->invoke('invalidHandler',$e,$args)) break;
        }
        if (!$ret) break;
      }
      return $ret;
    }

    /* **************** КОМПОНЕНТНЫЕ МЕТОДЫ **************** */
    public function registerTag(QuadBracesTagPrototype $to) {
      if (!empty($to->order)) {
        if (!isset($this->_tagsSort[$to->order])) {
          $this->_tagsSort[$to->order] = $to->name;
        } else { $this->_tagsSort[] = $to->name; }
      } else { array_unshift($this->_tagsSort,$to->name); }
      $this->_tags[$to->name]     = $to->regexp;
      $this->_tagsExts[$to->name] = $to;
    }

    /* CLASS:METHOD
      @description : Итерация

      @param : $tpls | array  | value |        | Шаблоны
      @param : $O    | string | value | @EMPTY | Входные данные

      @return : string
    */
    public function iteration(array $tpls,$O='') {
      $tplI = "#\[\+([\w\-\.]+)\[([^\]]*)\]((:?".(QuadBracesLib::extRex).")*)\+\]#si";
      $H = false;
      foreach ($tpls as $tpl) if (preg_match($tpl[0],$O)) {
        $O = preg_replace($tpl[0],'[+\1['.$tpl[1].']\2+]',$O);
        $H = true;
      }
      if ($H) $O = preg_replace_callback($tplI,array($this,"parseInternal"),$O);
      return $O;
    }

    /* CLASS:METHOD
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
      @description : Начало обработки

      @param : $m | array | value | | Данные от регулярки

      @return : string | ключ элемента
    */
    public function parseStart(array $m) {
      $this->_arguments[$this->_level] = isset($m[8]) ? QuadBracesLib::arguments($m[8]) : array();
      return $m[1];
    }

    /* CLASS:METHOD
      @description : Конец обработки

      @param : $m | array | value | | Данные от регулярки

      @return : string
    */
    public function parseFinish(array $m,$etype,$k,$v='') {
      if (isset($m[2])) $v = $this->extensions($v,$m[2]);
      return ($v != '') ? $this->parse($v,null,$etype,$k) : '';
    }

    /* **************** МЕТОДЫ ДЛЯ ПОЛУЧЕНИЯ ДАННЫХ **************** */
    /* CLASS:METHOD
      @description : Чтение, запись "настройки" шаблонизатора

      @param : $key   | string | value |       | Ключ
      @param : $value |        | value | @NULL | Значение

      @return : ?
    */
    public function setting($key,$value=null) {
      if (!is_null($value)) {
        QuadBracesLib::setByKey($this->_settings,$key,$value);
        return $value;
      } else { return QuadBracesLib::getByKey($this->_settings,$key); }
    }

    /* CLASS:METHOD
      @description : Чтение, запись переменной шаблонизатора

      @param : $key   | string | value |       | Ключ
      @param : $value |        | value | @NULL | Значение

      @return : ?
    */
    public function variable($key,$value=null) {
      if (!is_null($value)) {
        if (is_array($this->_idata)) {
          QuadBracesLib::setByKey($this->_idata,$key,$value);
        } else { QuadBracesLib::setByKey($this->_data,$key,$value); }
        return $value;
      } else {
        if (is_array($this->_idata)) {
          return QuadBracesLib::getByKey($this->_idata,$key);
        } else { return QuadBracesLib::getByKey($this->_data,$key); }
      }
    }

    /* CLASS:METHOD
      @description : Поиск элемента

      @param : $type | string | value | | Тип элемента
      @param : $name | string | value | | Имя элемента

      @param : string
    */
    public function search($type,$name) {
      if ($fn = QuadBracesFiles::search(
        $type,$name,
        $this->paths,$this->language,$this->_templateExtension
      )) return $fn;
      return '';
    }

    public function setTemplate($v) {
      if (empty($v)) return $this->invoke('defaultTemplate',$this);
      $content = '[*content*]';
      $this->_templateName = '';
      if ($fn = $this->search('template',$v)) {
        if ($this->invoke('loadTemplate',$v)) {
          $content = @file_get_contents($fn);
          $this->_templateName = $v;
        }
      } else {
        if (!$this->invoke('templateNotFound',$v,$this))
          return $this->_template;
      }
      if ($_ = QuadBracesLib::extractFields($content))
        if ($this->invoke('templateFields',$_)) {
          $this->_fields = $_['fields'];
          foreach ($this->_fields as $alias => $data) {
            if ($alias == 'content') continue;
            $this->variable($alias,$data['default']);
          }
        }
      $content = $_['body'];
      if ($_ = QuadBracesLib::extractData($content,null,$this->_prefix))
        if ($this->invoke('templateData',$_)) $this->data = $_['data'];
      $this->_template = $_['body'];
      return $this->_template;
    }

    /* CLASS:METHOD
      @description : Получение чанка

      @param : $key | string | value | | Имя чанка

      @return : string
    */
    public function getChunk($key,$type='chunk') {
      switch ($type) {
        case 'string':
          list($K,$I) = QuadBracesLib::keys($key);
          if (!isset($this->_strings[$K])) {
            if ($fn = $this->search('chunk',$K)) {
              $this->_strings[$K] = @file($fn);
            } else { return false; }
          }
          return isset($this->_strings[$K][$I]) ? $this->_strings[$K][$I] : false;
        case 'lib':
          list($K,$I) = QuadBracesLib::keys($key);
          if (!isset($this->_chunks[$K])) {
            if ($fn = $this->search('chunk',$K)) {
              $fc = @file_get_contents($fn);
              $this->_chunks[$K] = preg_split('~\<\!\-\-(\s+)tags:splitter(\s+)\-\-\>~si',$fc);
            } else { return false; }
          }
          return isset($this->_chunks[$K][$I]) ? $this->_chunks[$K][$I] : false;
        default: if ($fn = $this->search('chunk',$key)) return @file_get_contents($fn);
      }
      return false;
    }

    /* CLASS:METHOD
      @description : Выполнение сниппета или расширения

      @param : $NAME   | string | value |        | Имя сниппета
      @param : $ARGS   | array  | value | @EMPTY | Аргументы
      @param : $INPUT  | string | value | @EMPTY | Входные данные
      @param : $CACHED | bool   | value | @FALSE | Признак кеширования

      @return : int/string
    */
    public function execute($NAME,$ARGS=array(),$INPUT='',$CACHED=false) {
      $result    = '';             /** @noinspection PhpUnusedLocalVariableInspection */
      $CMS       = $this->owner;   /** @noinspection PhpUnusedLocalVariableInspection */
      $parser    = $this;          /** @noinspection PhpUnusedLocalVariableInspection */
      $input     = strval($INPUT); /** @noinspection PhpUnusedLocalVariableInspection */
      $arguments = $ARGS;          /** @noinspection PhpUnusedLocalVariableInspection */
      $cached    = QuadBracesLib::bool($CACHED);
      if ($fn = $this->search('snippet',$NAME)) $result = include($fn);
      return strval($result);
    }

    /* CLASS:METHOD
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

    /* **************** МЕТОДЫ ПАРСЕРА **************** */
    /* CLASS:METHOD
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
        } else { $O = $this->sanitize($O); }
        $this->_level--;
      }
      if (is_array($data)) $this->_idata = null;
      // Финализация
      if ($this->_level == -1) {
        $O = $this->sanitize($O);
        // Бенчмарки
        $P = $this->_prefix;
        foreach ($this->_debug['points'] as $pk => $pd) {
          $C = "<!-- $P:$pk -->";
          $V = '';
          switch ($pd['key']) {
            case 'current': $V = $pd['value']; break;
            case 'parser' :
              switch ($pd['type']) {
                case 'time': $V = microtime(true) - $_stime; break;
                case 'ram' : $V = memory_get_usage() - $_smem; break;
              }
              break;
            case 'total':
              switch ($pd['type']) {
                case 'time': $V = microtime(true) - $this->startTime; break;
                case 'ram' : $V = memory_get_usage() - $this->startRAM; break;
              }
              break;
          }
          switch ($pd['type']) {
            case 'time': $V = QuadBracesLib::timeValue($V,$pd['unit']); break;
            case 'ram' : $V = QuadBracesLib::RAMValue($V,$pd['unit']); break;
          }
          $O = str_replace($C,$V,$O);
        }

        $_levels = null;
        $_stime  = null;
        $_smem   = null;
      }
      $O = trim($O);
      return $O;
    }

    /* CLASS:METHOD
      @description : Санитизация

      @param : $input | string | value | @EMPTY | Входные данные

      @param : string
    */
    public function sanitize($input) {
      if (empty($input)) return '';
      $O = $input;
      foreach ($this->_tags as $t)
        if (preg_match($t,$O)) $O = preg_replace($t,'',$O);
      return $O;
    }

    /* CLASS:METHOD
      @description : Обработка расширений

      @param : $value | string | value |        | Входные данные
      @param : $ext   | string | value | @EMPTY | Тип элемента

      @param : string
    */
    public function extensions($value,$ext='') {
      $RET = ''.trim($value);
      if (empty($ext)) return ''.$value;
      if ($_ = preg_match_all("#".(QuadBracesLib::extRex)."#si",$ext,$ms,PREG_SET_ORDER)) {
        for($c = 0; $c < count($ms); $c++) {
          $a = $ms[$c][1];
          $v = isset($ms[$c][4]) ? $ms[$c][4] : '';
          if (QuadBracesLib::isLogic($a)) {
            $cond  = QuadBracesLib::condition($a,$value,$v);
            $cthen = QuadBracesLib::isLogicFunction($a) ? $v : $RET;
            if (isset($ms[$c+1])) if ($ms[$c+1][1] == 'then') { $c++; $cthen = $ms[$c][4]; }
            $celse = $RET;
            if (isset($ms[$c+1])) if ($ms[$c+1][1] == 'else') { $c++; $celse = $ms[$c][4]; }
            $RET = $cond ? $cthen : $celse;
            if (preg_match($this->_tags['local'],$RET))
              $RET = preg_replace_callback($this->_tags['local'],array($this,"parse_local"),$RET);
          } elseif (QuadBracesLib::isMath($a))                         { $RET = QuadBracesLib::math($a,$RET,$v);
          } elseif (QuadBracesLib::isType($a))                         { $RET = QuadBracesLib::toType($a,$RET);
          } elseif (in_array($a,array('import','css-link','js-link'))) { $RET = QuadBracesExts::jscss($a,$RET);
          } elseif (in_array($a,array('link','link-external')))        { $RET = QuadBracesExts::link($a,$RET,$v);
          } else {
            switch ($a) {
              case 'links': $RET = QuadBracesExts::autoLinks($RET,$v); break;
              case 'include':
                if (!empty($v)) {
                  $_   = QuadBracesFiles::path("$RET/$v");
                  $RET = is_file($_) ? include($_) : '';
                }
                break;
              case 'ul':
              case 'ol': $RET = QuadBracesExts::autoList($RET,$v,$a); break;
              case 'for':
                $v = intval($v);
                $start = 1;
                if ($ms[$c+1][1] == 'start') { $c++; $start = intval($ms[$c][4]); }
                $splt  = '';
                if ($ms[$c+1][1] == 'splitter') { $c++; $splt = $ms[$c][4]; }
                $_R  = array();
                for ($pos = $start; $pos <= ($v - $start); $pos++) {
                  $tpls = array(
                    array("#\[\+(iterator\.index)((:?".(QuadBracesLib::extRex).")*)\+\]#si",$pos)
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
                    array("#\[\+(iterator\.index)((:?".(QuadBracesLib::extRex).")*)\+\]#si",$pos),
                    array("#\[\+(iterator|iterator\.key)((:?".(QuadBracesLib::extRex).")*)\+\]#si",$key)
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

    /* **************** МЕТОДЫ ДЛЯ РАБОТЫ С СОБЫТИЯМИ **************** */
    /* CLASS:METHOD
      @description : Регистрация события

      @param : $event | string   | value |       | Название функции
      @param : $func  | callable | value | @NULL | Функция

      @param : bool
    */
    public function registerEvent($n,$f=null) {
      if (!preg_match(self::funcRex,$n)) return (!$this->error("Invalid event name",$n));
      if (empty($this->_events[$n])) $this->_events[$n] = array();
      if (is_callable($f,true)) {
        $this->_events[$n][] = $f;
        return true;
      }
      return false;
    }

    /* CLASS:METHOD
      @description : Регистрация метода

      @param : $name | string   | value |       | Название функции
      @param : $func | callable | value | @NULL | Функция

      @param : bool
    */
    public function registerMethod($n,$f=null) {
      if (!preg_match(self::funcRex,$n)) return (!$this->error("Invalid method name",$n));
      if (is_callable($f,true)) {
        $this->_methods[$n] = $f;
        return true;
      }
      return false;
    }

    /* CLASS:METHOD
      @description : Вызов события

      @param : [1] | string | value | | Название события
      @params : аргументы функции

      @return : ?
    */
    public function invoke() {
      $fargs = func_get_args();
      if (!isset($fargs[0])) return true;
      $n = array_shift($fargs);
      return $this->_callEvent($n,$fargs);
    }

    /* CLASS:METHOD
      @description : Ошибка

      @return : ?
    */
    public function error() {
      $a = func_get_args();
      if (!$this->_callEvent('error',$a)) return false;
      throw new Exception(implode('|',$a));
    }
  }
  /* CLASS ~END */

  /* INFO @copyright: Xander Bass, 2016 */
?>