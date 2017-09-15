<?php
  /**
   * Class QuadBracesTagPrototype
   * @property-read QuadBracesParser $owner
   * @property-read string           $error
   * @property-read string           $start
   * @property-read string           $finish
   * @property-read string           $name
   * @property-read int              $order
   * @property-read array            $args
   * @property-read string           $regexp
   */
  class QuadBracesTagPrototype {
    protected $_owner  = null;
    protected $_error  = false;
    protected $_start  = '';
    protected $_finish = '';
    protected $_name   = '';
    protected $_order  = 0;

    function __construct(QuadBracesParser $owner) {
      $this->_owner = $owner;
      if ($this->_owner->MODXRevoMode) {
        $this->_start  = '\['.$this->_start;
        $this->_finish = '\]\]';
      }
      $this->_owner->registerTag($this);
    }

    function __get($n) {
      switch ($n) {
        case 'args':
          return $this->_owner->arguments[$this->_owner->level];
        case 'regexp':
          return "#{$this->_start}([\w\.\-]+)(?:\??)"
               . "((:?\:([\w\-\.]+)((\=`([^`]*)`)?))*)"
               . "(((:?\s*)\&([\w\-\.]+)\=`([^`]*)`)*)"
               . "{$this->_finish}#si";
      }
      $N = "_$n";
      if (property_exists($this,$N)) return $this->$N;
      return false;
    }

    public function parse($m) {
      $key = $this->_owner->parseStart($m);
      $val = $this->main($m,$key);
      if ($this->_error) {
        $e = $this->_error;
        $this->_error = false;
        if (in_array($this->_name,$this->_owner->notice))
          return "<!-- {$e}: {$this->_name}/$key -->";
        $val = '';
      }
      return $this->_owner->parseFinish($m,$this->_name,$key,$val);
    }

    public function main(/** @noinspection PhpUnusedParameterInspection */ array $m,$key='') {
      $this->_error = 'abstract tag';
      return '';
    }
  }
?>