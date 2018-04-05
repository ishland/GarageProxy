<?php

class PluginClass{
    private $name;
    private $version;
    private $state;
    
    public $onPluginLoad = null;
    public $onPluginEnable = null;
    public $onPluginDisable = null;
    
    
    public function __construct($name, $version){
        $this->name = $name;
        $this->version = $version;
    }
    
    protected function PluginLoad(){
        //try to emit onPluginLoad
        if($this->state == "loaded") return false;
        if(is_callable($this->onPluginLoad)) call_user_func_array($this->onPluginLoad, array());
        $this->state = "loaded";
    }
}