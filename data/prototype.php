<?php
  /* INFO
    @product     : QuadBraces
    @component   : QuadBracesDataPrototype
    @type        : class
    @description : Прототип кастомного обработчика тегов
    @revision    : 2016-01-10 19:12:00
  */

  /* CLASS ~BEGIN */
  /**
   * Class QuadBracesDataPrototype
   * @property QuadBracesParser $owner
   */
  class QuadBracesDataPrototype {
    protected $owner  = null;
    protected $ready  = false;
    protected $name   = '';

    /* CLASS:CONSTRUCT */
    function __construct($owner) {
      if ($owner instanceof QuadBracesParser) {
        $this->owner = $owner;
        $this->ready = true;
        $this->name  = preg_replace('/^QuadBracesTag(\w+)$/si','\1',get_class($this));
        $this->name  = strtolower($this->name);
      }
    }

    /* CLASS:METHOD
      @name        : parse
      @description : Обработка данных

      @param : $m | array | value | | Данные от регулярки

      @return : string
    */
    public function parse($m) {
      $key   = $this->owner->parseStart($m);
      $args  = $this->owner->arguments[$this->owner->level];
      $value = $this->owner->variable($key);
      if ($value === false)
        if (in_array('custom',$this->owner->notice)) return "<!-- not found: variable/$key -->";
      $v = $this->main($key,$value,$args);
      return $this->owner->parseFinish($m,'data',$key,$v);
    }

    /* CLASS:METHOD
      @name        : getTemplates
      @description : Получение шаблонов

      @param : $arguments | array | value | | Аргументы

      @return : array
    */
    public function getTemplates($args,$def=array()) {
      $ret = $def;
      $K = array_keys($ret);
      $ret['type'] = 'chunk';
      if (isset($args['chunkType']))
        if (in_array($args['chunkType'],array('chunk','string','lib'))) $ret['type'] = $args['chunkType'];
      foreach ($K as $Key) if (isset($args[$Key]))
        if ($_ = $this->owner->getChunk($args[$Key],$ret['type'])) $ret[$Key] = $_;
      return $ret;
    }

    /* CLASS:METHOD
      @name        : regexp
      @description : Регулярное выражение

      @return : string
    */
    public function regexp() {
      $T = "#\[\:{$this->name}\@([\w\.\-]+)"
         . "((:?\:([\w\-\.]+)((\=`([^`]*)`)?))*)"
         . "(((:?\s*)\&([\w\-\.]+)\=`([^`]*))*)"
         . "\:\]#si";
      return $T;
    }

    /* CLASS:VIRTUAL
      @name        : main
      @description : Обработка данных

      @param : $key   | string | value | | Ключ данных
      @param : $value | string |       | | Значение
      @param : $args  | array  | value | | Аргументы

      @return : string
    */
    public function main(/** @noinspection PhpUnusedParameterInspection */ $key,$value,$args) {
      return $value;
    }
  }
  /* CLASS ~END */

  /* INFO @copyright: Xander Bass, 2016 */
?>