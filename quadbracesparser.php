<?php
  /**
   * QuadBracesParser
   * Парсер синтаксиса подобного MODX / Etomite
   *
   * Предназначен для того, чтобы не заколачивать экскаватором гвозди, то бишь
   * не устанавливать MODX для целевых страниц. Также предназначен в качестве
   * унифицированного стандарта шаблонизации.
   *
   * @version 1.1
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

   * @property-read object $owner  объект-владелец
   * @property      array  $paths  пути поиска
   *
   * @property      string $template     шаблон (принимает ключ шаблона, возвращает код оного)
   * @property-read string $templateName ключ шаблона
   * @property      string $templatePack шаблон-пак
   * @property      string $language     ключ языка
   * @property      bool   $loadLanguage признак автозагрузки языка
   *
   * @property      array $data       переменные парсера
   * @property      array $settings   "настройки"
   * @property      array $dictionary словарь
   * @property-read array $chunks     чанки
   * @property-read array $strings    однострочные чанки
   *
   * @property      float $startTime  стартовое время
   * @property      int   $startRAM   стартовый расход памяти
   */
  class QuadBracesParser {
    protected static $_maxLevel = 32;
    protected static $_tags     = null;
    protected static $_prefix   = 'QUADBRACES';

    protected $_owner = null;
    protected $_paths = array();

    protected $_template     = '';
    protected $_templateName = '';
    protected $_templatePack = '';
    protected $_language     = '';
    protected $_loadLanguage = true;

    protected $_data       = array(); // Переменные
    protected $_idata      = null;    // Переменные разовой обработки
    protected $_settings   = array(); // "Настройки"
    protected $_dictionary = array(); // Словарь
    protected $_chunks     = array(); // Чанки
    protected $_strings    = array(); // Чанки строки

    protected $_arguments  = array();
    protected $_level      = -1;

    protected $_startTime  = 0;
    protected $_startRAM   = 0;
    protected $_debug      = array();

    /******** ОБЩИЕ МЕТОДЫ КЛАССА ********/
    function __construct($owner=null) {
      self::initTags();
      $this->_owner = $owner;
      $this->_startTime = QBPARSER_STARTTIME;
      $this->_startRAM  = QBPARSER_STARTMEM;
      $this->_paths     = array(QBPARSER_TPL_PATH);
    }

    function __get($n) {
      if (method_exists($this,"get_$n"))       { $f = "get_$n"; return $this->$f();
      } elseif (property_exists($this,"_$n"))  { $f = "_$n";    return $this->$f; }
      return false;
    }

    function __set($n,$v) {
      if (method_exists($this,"set_$n")) { $f = "set_$n"; return $this->$f($v); }
      return false;
    }

    function __toString() { return $this->parse(); }

    /******** АКСЕССОРЫ КЛАССА ********/
    /* CLASS:PROPERTY
      @name        : paths
      @description : Пути шаблона
      @type        : array
      @mode        : rw
    */
    protected function get_paths() {
      $ret = $this->_paths;
      if (!empty($this->_templatePack))
        if (isset($ret[0])) $ret[0].= $this->_templatePack.DIRECTORY_SEPARATOR;
      return $ret;
    }
    protected function set_paths($v) {
      if (!is_array($v)) return false;
      $this->_paths = $v;
      return $this->_paths;
    }

    /* CLASS:PROPERTY
      @name        : template
      @description : Шаблон
      @type        : string
      @mode        : rw
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

    /* CLASS:PROPERTY
      @name        : data
      @description : Плейсхолдеры
      @type        : array
      @mode        : rw
    */
    protected function set_data($d) {
      $this->_data = self::megreData($this->_data,$d);
      return $this->_data;
    }

    /* CLASS:PROPERTY
      @name        : settings
      @description : Настройки
      @type        : array
      @mode        : rw
    */
    protected function set_settings($d) {
      $this->_settings = self::megreData($this->_settings,$d);
      return $this->_settings;
    }

    /* CLASS:PROPERTY
      @name        : dictionary
      @description : Словарь
      @type        : array
      @mode        : rw
    */
    protected function set_dictionary($d) {
      if (!is_array($d)) return false;
      $this->_dictionary = $d;
      return $this->_dictionary;
    }

    /* CLASS:PROPERTY
      @name        : templatePack
      @description : Шаблон-пак
      @type        : string
      @mode        : rw
    */
    protected function set_templatePack($v) {
      $this->_templatePack = $v;
      return $this->_templatePack;
    }

    /* CLASS:PROPERTY
      @name        : language
      @description : Сигнатура языка
      @type        : string
      @mode        : rw
    */
    protected function set_language($v) {
      if (!empty($v)) $this->_language = strval($v);
      if ($this->_loadLanguage)
        $this->_dictionary = self::loadLang($this->_language);
      return $this->_language;
    }

    /* CLASS:PROPERTY
      @name        : loadLanguage
      @description : Переключатель автозагрузки словарей
      @type        : string
      @mode        : rw
    */
    protected function set_loadLanguage($v) {
      $this->_loadLanguage = self::bool($v);
      return $this->_loadLanguage;
    }

    /* CLASS:PROPERTY
      @name        : startTime
      @description : Стартовое время отладки
      @type        : float
      @mode        : rw
    */
    protected function set_startTime($v) {
      $this->_startTime = floatval($v);
      return $this->_startTime;
    }

    /* CLASS:PROPERTY
      @name        : startRAM
      @description : Стартовое потребление памяти
      @type        : float
      @mode        : rw
    */
    protected function set_startRAM($v) {
      $this->_startRAM = floatval($v);
      return $this->_startRAM;
    }

    /******** ВНУТРЕННИЕ МЕТОДЫ КЛАССА ********/
    /* CLASS:INTERNAL
      @name        : _iteration
      @description : Итерация

      @param : $tpls | array  | value |        | Шаблоны
      @param : $O    | string | value | @EMPTY | Входные данные

      @return : string
    */
    protected function _iteration(array $tpls,$O='') {
      $tplI = "#\[\+([\w\-\.]+)\[([^\]]*)\]((:?\:([\w\-\.]+)((=`([^`]*)`))?)*)\+\]#si";
      $H = false;
      foreach ($tpls as $tpl) {
        if (preg_match($tpl[0],$O)) {
          $O = preg_replace($tpl[0],'[+\1['.$tpl[1].']\2+]',$O);
          $H = true;
        }
      }
      if ($H) $O = preg_replace_callback($tplI,array($this,"parse__internal"),$O);
      return $O;
    }

    /* CLASS:INTERNAL
      @name        : _tpls
      @description : Получение шаблонов

      @param : $arguments | array | value | | Аргументы

      @return : array
    */
    protected function _tpls($arguments,$def=array()) {
      $ret = $def;
      $K = array_keys($ret);

      $ret['type'] = 'chunk';
      if (isset($arguments['chunkType']))
        if (in_array($arguments['chunkType'],array('chunk','string','lib')))
          $ret['type'] = $arguments['chunkType'];

      foreach ($K as $Key) {
        if (isset($arguments[$Key]))
          if ($_ = $this->getChunk($arguments[$Key],$ret['type'])) $ret[$Key] = $_;
      }

      return $ret;
    }

    /* CLASS:INTERNAL
      @name        : _pkeys
      @description : Разделение ключей

      @param : $key | string | value | | Исходный ключ

      @return : array
    */
    protected function _pkeys($key) {
      $pkey = explode('.',$key);
      $item = intval($pkey[count($pkey)-1]); unset($pkey[count($pkey)-1]);
      $pkey = implode('.',$pkey);
      return array('key' => $pkey,'item' => $item);
    }

    /* CLASS:INTERNAL
      @name        : parse__internal
      @description : Внутренняя обработка

      @param : $m | array | value | | Данные от регулярки

      @return : string
    */
    protected function parse__internal(array $m) {
      $v = $m[2];
      if (isset($m[3])) $v = $this->extensions($v,$m[3],true);
      return $v;
    }

    /* CLASS:INTERNAL
      @name        : parse__start
      @description : Начало обработки

      @param : $m | array | value | | Данные от регулярки

      @return : string | ключ элемента
    */
    protected function parse__start(array $m) {
      $arguments = array();
      if (isset($m[8]) && !empty($m[8]))
        if ($_ = preg_match_all('|\&([\w\-\.]+)\=`([^`]*)`|si',$m[8],$ms,PREG_SET_ORDER))
          foreach ($ms as $pr) $arguments[$pr[1]] = $pr[2];
      $this->_arguments[$this->_level] = $arguments;
      return $m[1];
    }

    /* CLASS:INTERNAL
      @name        : parse__finish
      @description : Конец обработки

      @param : $m | array | value | | Данные от регулярки

      @return : string
    */
    protected function parse__finish(array $m,$etype,$k,$v='') {
      if (isset($m[2])) $v = $this->extensions($v,$m[2]);
      return ($v != '') ? $this->parse($v,null,$etype,$k) : '';
    }

    /******** ОБРАБОТЧИКИ ПАРСЕРА ********/
    // ****** DataE
    public function parse_datae($m) {
      $key       = $this->parse__start($m);
      $arguments = $this->_arguments[$this->_level];
      $value     = $this->variable($key);
      if ($value === false) return "<!-- not found: datae/$key -->";
      if (is_array($value)) {
        $tplt = 'chunk';
        if (isset($arguments['chunkType']))
          if (in_array($arguments['chunkType'],array('chunk','string','lib')))
            $tplt = $arguments['chunkType'];
        $tpli = '<li><span class="key">[+key+]</span><span class="value">[+value+]</span></li>';
        if (isset($arguments['chunk']))
          if ($fn = $this->search('chunk',$arguments['chunk']))
            switch ($tplt) {
              case 'string': $tpli = '{('.$arguments['chunk'].' [+arguments+])}'; break;
              case 'lib'   : $tpli = '{<'.$arguments['chunk'].' [+arguments+]>}'; break;
              default      : $tpli = '{{'.$arguments['chunk'].' [+arguments+]}}'; break;
            }
        $LK = false;
        if (isset($arguments['langKeys']))   $LK = self::bool($arguments['langKeys']);
        $LP = '';
        if (isset($arguments['langPrefix'])) $LP = $arguments['langPrefix'];
        $v = '';
        foreach ($value as $dataKey => $dataVal) {
          $DK = $LK ? "[%".(!empty($LP) ? $LP."." : "")."$dataKey%]" : $dataKey;
          if (is_array($dataVal)) {
            $_ = array();
            foreach ($dataVal as $dvKey => $dvVal) $_[] = "&".$dvKey."=`$dvVal`";
            $_[] = '&'.$key.".datakey=`$DK`";
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
      } else { $v = strval($value); }
      return $this->parse__finish($m,'datae',$key,$v);
    }

    // ****** Таблицы
    public function parse_table($m) {
      $key       = $this->parse__start($m);
      $arguments = $this->_arguments[$this->_level];
      $value     = $this->variable($key);
      if ($value === false) return "<!-- not found: table/$key -->";
      if (is_array($value)) {
        $v = '';
        // Параметры обработки
        $LK = false; $LP = ''; $FL = array();
        if (isset($arguments['langKeys']))   $LK = self::bool($arguments['langKeys']);
        if (isset($arguments['langPrefix'])) $LP = $arguments['langPrefix'];

        if (!isset($arguments['fields'])) {
          foreach ($value as $dataRow)
            if (is_array($dataRow))
              foreach ($dataRow as $fKey => $fVal)
                if (!in_array($fKey,$FL)) $FL[] = $fKey;
        } else { $FL = explode(',',$arguments['fields']); }

        $tpls = $this->_tpls($arguments,array(
          'table'    => '<table cellpadding="0" cellspacing="0"'.' border="0">[+content+]</table>',
          'heap'     => '<theap>[+rows+]</theap>',
          'heaprow'  => '<tr>[+cells+]</tr>',
          'heapcell' => '<th class="field-[+key+]">[+value+]</th>',
          'body'     => '<tbody>[+rows+]</tbody>',
          'row'      => '<tr data-id="[+id+]">[+cells+]</tr>',
          'cell'     => '<td class="field-[+key+]">[+value+]</td>',
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
            $row[]       = $this->_iteration($tplsC,$tpls['heapcell']);
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
              $row[]       = $this->_iteration($tplsC,$tpls['cell']);
            }
            $row = implode('',$row);
            $row = str_replace('[+cells+]',$row,$tpls['row']);
            $row = $this->_iteration($tplsR,$row);
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
            $row[]       = $this->_iteration($tplsC,$tpls['footcell']);
          }
          $row = implode('',$row);
          $row = str_replace('[+cells+]',$row,$tpls['footrow']);
          $v  .= str_replace('[+rows+]',$row,$tpls['foot']);
        }
        // Итог
        $v = str_replace('[+content+]',$v,$tpls['table']);
      } else { $v = strval($value); }
      return $this->parse__finish($m,'table',$key,$v);
    }

    // ****** Структура
    public function parse_structure($m) {
      $key       = $this->parse__start($m);
      $arguments = $this->_arguments[$this->_level];
      $value     = $this->variable($key);
      if ($value === false) return "<!-- not found: structure/$key -->";
      if (is_array($value)) {
        // Параметры обработки
        $FL = isset($arguments['fields']) ? explode(',',$arguments['fields']) : array();

        $tpls = $this->_tpls($arguments,array(
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
          $tplsC[1][1] = is_array($dataVal) ? "{~$key.$dataKey"."$args~}" : strval($dataVal);
          $rows[] = $this->_iteration($tplsC,$tpls['item']);
        }

        $v = str_replace('[+items+]',implode('',$rows),$tpls['outer']);
      } else { $v = strval($value); }
      return $this->parse__finish($m,'structure',$key,$v);
    }

    // ****** Чанки
    public function parse_chunk($m) {
      $key  = $this->parse__start($m);
      if ($v = $this->getChunk($key))
        return $this->parse__finish($m,'chunk',$key,$v);
      return "<!-- not found: chunk/$key -->";
    }

    // ****** Однострочные библиотеки
    public function parse_string($m) {
      $key  = $this->parse__start($m);
      if ($v = $this->getChunk($key,'string'))
        return $this->parse__finish($m,'chunk',$key,$v);
      return "<!-- not found: chunk/$key -->";
    }

    // ****** Многострочные библиотеки
    public function parse_lib($m) {
      $key  = $this->parse__start($m);
      if ($v = $this->getChunk($key,'lib'))
        return $this->parse__finish($m,'chunk',$key,$v);
      return "<!-- not found: chunk/$key -->";
    }

    // ****** Константы
    public function parse_constant($m) {
      $key = $this->parse__start($m);
      if (empty($key) || !defined($key)) return "<!-- not found: constant/$key -->";
      return $this->parse__finish($m,'constant',$key,constant($key));
    }

    // ****** "Настройки"
    public function parse_setting($m) {
      $key = $this->parse__start($m);
      $val = $this->setting($key);
      if ($val === false) return "<!-- not found: setting/$key -->";
      return $this->parse__finish($m,'setting',$key,$val);
    }

    // ****** Переменные
    public function parse_placeholder($m) {
      $key = $this->parse__start($m);
      $val = $this->variable($key);
      if ($val === false) return "<!-- not found: placeholder/$key -->";
      return $this->parse__finish($m,'placeholder',$key,$val);
    }

    // ****** Отладочные данные
    public function parse_debug($m) {
      $key = $this->parse__start($m);
      $dd = explode('.',$key);
      $dk = $dd[0];
      $KP = self::$_prefix;

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
          $v = microtime(true) - $this->_startTime;
          if (count($dd) > 1) if (array_key_exists($dd[1],$atm)) $v *= $atm[$dd[1]];
          $v = strval(round($v,2));
          break;
        case 'totalmem':
          $v = "<!-- $KP:TOTALMEM";
          if (count($dd) > 1) if (array_key_exists($dd[1],$asz)) $v.= ' '.$dd[1];
          $v.= " -->";
          break;
        case 'totaltime':
          $v = "<!-- $KP:TOTALTIME";
          if (count($dd) > 1) if (array_key_exists($dd[1],$atm)) $v.= ' '.$dd[1];
          $v.= " -->";
          break;
        case 'log'        : $v = "<!-- $KP:LOG -->"; break;
        case 'logstatus'  : $v = "<!-- $KP:LOGSTATUS -->"; break;
        case 'queries'    : $v = "<!-- $KP:QUERYCOUNT -->"; break;
        case 'timepoints' : $v = "<!-- $KP:TIMEPOINTS -->"; break;
        case 'querypoints': $v = "<!-- $KP:QUERYPOINTS -->"; break;
        default: $v = '';
      }

      if (empty($v)) {
        if (!isset($this->_debug[$key])) return "<!-- not found: debug/$key -->";
        $v = $this->_debug[$key];
      }

      return $this->parse__finish($m,'debug',$key,$v);
    }

    // ****** Сниппеты
    public function parse_snippet($m) {
      $key = $this->parse__start($m);
      $v   = '';
      if ($_ = $this->execute($key,$this->_arguments[$this->_level])) {
        $v  = strval($_);
      } else { if ($_ === false) return "<!-- not found: snippet/$key -->"; }
      return $this->parse__finish($m,'snippet',$key,$v);
    }

    // ****** Локальные плейсхолдеры
    public function parse_local($m) {
      $key = $this->parse__start($m);
      $v = "";
      if (isset($this->_arguments[$this->_level - 1][$key]))
        $v = $this->_arguments[$this->_level - 1][$key];
      return $this->parse__finish($m,'local',$key,$v);
    }

    // ****** Языковые плейсхолдеры
    public function parse_language($m) {
      $key = $this->parse__start($m);
      $P = 'caption';
      $K = self::langKey($key,$P);
      if (isset($this->_dictionary[$K][$P])) {
        $v = $this->_dictionary[$K][$P];
      } else { $v = ucfirst(str_replace(array('.','_','-'),' ',$key)); }
      return $this->parse__finish($m,'language',$key,$v);
    }

    /******** ПУБЛИЧНЫЕ МЕТОДЫ КЛАССА ********/
    /* CLASS:METHOD
      @name        : getChunk
      @description : Получение чанка

      @param : $key | string | value | | Имя чанка

      @return : string
    */
    public function getChunk($key,$type='chunk') {
      switch ($type) {
        case 'string':
          list($K,$I) = $this->_pkeys($key);
          if (!isset($this->_strings[$K])) {
            if ($fn = $this->search('chunk',$K)) {
              $this->_strings[$K] = @file($fn);
            } else { return false; }
          }
          return isset($this->_strings[$K][$I]) ? $this->_strings[$K][$I] : false;
        case 'lib':
          list($K,$I) = $this->_pkeys($key);
          if (!isset($this->_chunks[$K])) {
            if ($fn = $this->search('chunk',$K)) {
              $fc = @file_get_contents($fn);
              $this->_chunks[$K] = preg_split('~\<\!\-\- quadbraces:splitter \-\-\>~si',$fc);
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
      $result = '';
      /** @noinspection PhpUnusedLocalVariableInspection */
      $CMS    = $this->_owner;
      /** @noinspection PhpUnusedLocalVariableInspection */
      $parser = $this;
      /** @noinspection PhpUnusedLocalVariableInspection */
      $input  = strval($I);
      /** @noinspection PhpUnusedLocalVariableInspection */
      $arguments = $A;
      if ($fn = $this->search('snippet',$name)) $result = include($fn);
      return strval($result);
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
      @name        : parse
      @description : Обработка

      @param : $d   | string | value | @EMPTY | Входные данные
      @param : $elt | string | value | @EMPTY | Тип элемента
      @param : $key | string | value | @EMPTY | Ключ элемента

      @param : string
    */
    public function parse($d='',$data=null,$elt='',$key='') {
      static $_levels = null;

      $p1 = is_null($_levels) ? 2 : 1;
      $O  = (is_null($_levels) && empty($d)) ? $this->_template : strval($d);
      if (is_null($_levels)) $_levels = array();
      if (empty($O)) return $O;

      if (is_array($data)) $this->_idata = $data;
      for ($c1 = 0; $c1 < $p1; $c1++) {
        $this->_level++;
        if ($this->_level <= self::$_maxLevel) {
          $_levels[$this->_level] = array('element' => $elt,'key' => $key);
          $this->_debug['levels'] = $_levels;
          foreach (self::$_tags as $k => $t)
            if (method_exists($this,"parse_$k"))
              if (preg_match($t,$O))
                $O = preg_replace_callback($t,array($this,"parse_$k"),$O);
        } else { $O = self::sanitize($O); }
        $this->_level--;
      }
      if (is_array($data)) $this->_idata = null;

      if ($this->_level == -1) {
        $st = microtime(true)    - $this->_startTime;
        $tm = memory_get_usage() - $this->_startRAM;
        $ph = array(
          'TOTALTIME'    => round($st,2),
          'TOTALTIME ms' => round($st*1000,2),
          'TOTALTIME us' => round($st*1000000,2),
          'TOTALTIME ns' => round($st*1000000000,2),
          'TOTALMEM'     => round($tm,2),
          'TOTALMEM kb'  => round($tm/1000,2),
          'TOTALMEM mb'  => round($tm/1000000,2),
          'TOTALMEM gb'  => round($tm/1000000000,2),
          'LOG'          => '',
          'LOGSTATUS'    => 'not-empty',
          'QUERYCOUNT'   => 0,
          'TIMEPOINTS'   => '',
          'QUERYPOINTS'  => ''
        );

        if (is_object($this->_owner)) {
          foreach (array(
            'LOG'         => 'getLog',
            'TIMEPOINTS'  => 'getDebugPoints',
            'QUERYCOUNT'  => 'getQueriesCount',
            'QUERYPOINTS' => 'getQueryPoints'
          ) as $DK => $DM) if (method_exists($this->_owner,$DM)) $ph[$DK] = $this->_owner->$DM();
        }

        if (empty($ph['LOG'])) {
          $ph['LOGSTATUS'] = 'empty';
          $ph['LOG']       = '<span class="log-empty">Log is empty</span>';
        }

        $KP = self::$_prefix;
        foreach ($ph as $phk => $phv) $O = str_replace("<!-- $KP:$phk -->",$phv,$O);
        $O = self::sanitize($O);
        $_levels = null;
      }

      if (is_object($this->_owner))
        if (method_exists($this->_owner,'onParse')) $O = $this->_owner->onParse($O);

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
      $RET = trim($value);
      if (empty($ext)) return $value;
      if ($_ = preg_match_all('|\:([\w\-\.]+)((\=`([^`]*)`)?)|si',$ext,$ms,PREG_SET_ORDER)) {
        for($c = 0; $c < count($ms); $c++) {
          $a = $ms[$c][1];
          $v = isset($ms[$c][4]) ? $ms[$c][4] : '';
          if (in_array($a,array(
            'is','eq','isnot','neq','lt','lte','gt','gte',
            'even','odd',
            'notempty','empty'
          ))) {
            $cond  = false;
            $V     = intval($RET);
            $cthen = $RET;
            switch ($a) {
              case 'is'      :
              case 'eq'      : $cond = ($value == $v); break;
              case 'isnot'   :
              case 'neq'     : $cond = ($value != $v); break;
              case 'lt'      : $cond = ($value <  $v); break;
              case 'lte'     : $cond = ($value <= $v); break;
              case 'gt'      : $cond = ($value >  $v); break;
              case 'gte'     : $cond = ($value >= $v); break;
              case 'even'    : $cond = (($V % 2) == 0); $cthen = $v; break;
              case 'odd'     : $cond = (($V % 2) != 0); $cthen = $v; break;
              case 'empty'   : $cond =  empty($RET); $cthen = $v; break;
              case 'notempty': $cond = !empty($RET); $cthen = $v; break;
            }
            if (isset($ms[$c+1])) if ($ms[$c+1][1] == 'then') { $c++; $cthen = $ms[$c][4]; }
            $celse = $RET;
            if (isset($ms[$c+1])) if ($ms[$c+1][1] == 'else') { $c++; $celse = $ms[$c][4]; }
            $RET = $cond ? $cthen : $celse;
            if (preg_match(self::$_tags['local'],$RET))
              $RET = preg_replace_callback(self::$_tags['local'],array($this,"parse_local"),$RET);
          } elseif (in_array($a,array('import','css-link','js-link'))) {
            $tpls = array(
              'js-link' => '<script type="text/javascript" src="[+content]"></script>',
              'css-link' => '<link rel="stylesheet" type="text/css" href="[+content+]" />',
              'import'   => '@import url("[+content+]");'
            );
            if (isset($tpls[$a]) && !empty($v))
              $RET = str_replace('[+content+]',"$RET",$tpls[$a]);
          } elseif (in_array($a,array('link','link-external'))) {
            $tpls = array(
              'link'          => '<a href="[+content+]">[+value+]</a>',
              'link-external' => '<a href="[+content+]" target="_blank">[+value+]</a>'
            );
            if (isset($tpls[$a]) && !empty($v)) {
              $val = empty($v) ? $RET : $v;
              $RET = str_replace(array('[+content+]','[+value+]'),array($RET,$val),$tpls[$a]);
            }
          } else {
            switch ($a) {
              case 'links'   : $RET = self::autoLinks($RET,$v); break;
              case 'include' :
                if (!empty($v)) {
                  $_   = self::toPath("$RET/$v");
                  $RET = is_file($_) ? include($_) : '';
                }
                break;
              case 'ul': case 'ol':
              $tpl   = empty($v) ? '<li[+classes+]>[+item+]</li>' : $v;
              $items = preg_split('~\\r\\n?|\\n~',$RET);
              $RET   = "<$a>";
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
              $RET.= "</$a>";
              break;
              case 'for':
                $v = intval($v);
                $start = 1;
                if ($ms[$c+1][1] == 'start') { $c++; $start = intval($ms[$c][4]); }
                $splt  = '';
                if ($ms[$c+1][1] == 'splitter') { $c++; $splt = $ms[$c][4]; }
                $_R  = array();
                for ($pos = $start; $pos <= ($v - $start); $pos++) {
                  $tpls = array(
                    array("#\[\+(iterator\.index)((:?\:([\w\-\.]+)((=`([^`]*)`))?)*)\+\]#si",$pos)
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

    /* CLASS:METHOD
      @name        : search
      @description : Поиск элемента

      @param : $type | string | value | | Тип элемента
      @param : $name | string | value | | Имя элемента

      @param : string
    */
    public function search($type,$name) {
      $ext  = 'html';
      $DS   = DIRECTORY_SEPARATOR;

      switch ($type) {
        case 'template': $sdir = 'pages'; break;
        case 'chunk'   : $sdir = 'chunks'; break;
        case 'snippet' : $sdir = 'snippets'; $ext = 'php'; break;
        default: return false;
      }

      $dname = explode('.',$name);
      $ename = $dname[count($dname)-1];
      unset($dname[count($dname)-1]);
      $dname = count($dname) > 0 ? implode($DS,$dname).$DS : '';
      $found = '';

      $paths = $this->paths;

      foreach ($paths as $epath) {
        $D = $epath.$sdir.DIRECTORY_SEPARATOR.$dname;
        $fname = $D."$ename.$ext";
        if (is_file($fname)) $found = $fname;
        if ($this->_language != '') {
          $fname = $D.($this->_language).DIRECTORY_SEPARATOR."$ename.$ext";
          if (is_file($fname)) $found = $fname;
        }
      }

      return $found;
    }

    /******** БИБЛИОТЕЧНЫЕ МЕТОДЫ КЛАССА ********/
    /* CLASS:STATIC
      @name        : initTags
      @description : Инициализация тегов

      @return : array
    */
    public static function initTags() {
      if (is_null(self::$_tags)) {
        $map = array(
          'datae'       => array('\{\!','\!\}'),
          'table'       => array('\{\[','\]\}'),
          'structure'   => array('\{\~','\~\}'),
          'chunk'       => array('\{\{','\}\}'),
          'string'      => array('\{\(','\)\}'),
          'lib'         => array('\{\<','\>\}'),
          'constant'    => array('\{\*','\*\}'),
          'setting'     => array('\[\(','\)\]'),
          'placeholder' => array('\[\*','\*\]'),
          'debug'       => array('\[\^','\^\]'),
          'snippet'     => array('\[\!','\!\]'),
          'local'       => array('\[\+','\+\]'),
          'language'    => array('\[\%','\%\]')
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

    /* CLASS:STATIC
      @name        : sanitize
      @description : Очищение от тегов

      @param : $data | string | value | @EMPTY | Текстовые данные

      @return : string
    */
    public static function sanitize($data='') {
      self::initTags();
      if (empty($data)) return '';
      $O = $data;
      foreach (self::$_tags as $t) if (preg_match($t,$O)) $O = preg_replace($t,'',$O);
      return $O;
    }

    /* CLASS:STATIC
      @name        : getByKey
      @description : Получить значение по ключу

      @param : $input | array | value | | Входное значение
      @param : $key   | array | value | | Ключ

      @return : ?
    */
    public static function getByKey(array $input,$key) {
      $P = explode('.',$key);
      $V = $input;
      foreach ($P as $i) {
        if (!isset($V[$i])) return '';
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

    /* CLASS:STATIC
      @name        : langKey
      @description : Ключ языкового плейсхолдера

      @param : $key | string | value | | Ключ
      @param : $p   | string | link  | | Полученное свойство

      @return : string
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
      @name        : loadLang
      @description : Сканирование языковой папки и получения слооварных данных

      @param : $lang | string | value | | Язык

      @return : array
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

    /* CLASS:STATIC
      @name        : toPath
      @description : Корректировать путь

      @param : $path | string | value | | Путь

      @return : string
    */
    public static function toPath($path) {
      $fn  = explode('/',$path);
      return implode(DIRECTORY_SEPARATOR,$fn);
    }

    /* CLASS:STATIC
      @name        : bool
      @description : Булево значение

      @param : $v | | value | | входное значение

      @return : bool
    */
    public static function bool($v) {
      return ((strval($v) === 'true') || ($v === true) || (intval($v) > 0));
    }

    public static function placeholders($data,$value='') {
      $O = $value;
      foreach ($data as $key => $val) $O = str_replace("[+$key+]",$val,$O);
      return $O;
    }
  }
  /* CLASS ~END */

  /* INFO @copyright: xbLab 2015 */
?>