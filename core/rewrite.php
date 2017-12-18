<?php
class Rewrite{
    private $controller = false;
    private $view       = false;
    private $PATH       = false;
    public $GET         = false;
    public $URL         = false;
    public $currentPage = 1;

    public function __construct($override = false){
        global $Core;

        //file is local bot
        if(!isset($_SERVER['REQUEST_URI'])){
            return false;
        }

        $q = urldecode($_SERVER['REQUEST_URI']);

        if(mb_stristr($q, '?')){
            $qs = substr($q, 0, strpos($q, '?'));
            preg_match_all("{/([^/]*)}", $qs, $matches);
        }else{
            preg_match_all("{/([^/]*)}", $q, $matches);
        }

        $matches   = $matches[1];
        $this->GET = $matches;

        if(isset($_REQUEST['page']) && is_numeric($_REQUEST['page']) && intval($_REQUEST['page']) !== 1){
            $this->currentPage = $_REQUEST['page'];
        }elseif(isset($matches[count($matches)-1]) && is_numeric($matches[count($matches)-1]) && intval($matches[count($matches)-1]) !== 1){
            $this->currentPage = $matches[count($matches)-1];
            unset($matches[count($matches)-1]);
        }

        $this->PATH = implode('/', $matches);
        $this->URL  = '/'.$this->PATH;

        if(isset($Core->rewriteOverride[$this->PATH])){
            $this->PATH = $Core->rewriteOverride[$this->PATH];
        }elseif(in_array($this->PATH, $Core->rewriteOverride)){
            $Core->doOrDie();
        }

        $this->controller = $this->PATH;
        $this->view       = $this->PATH;
        return true;
    }

    public function getFiles(){
        global $Core;

        if(is_file($Core->controllersDir.$this->controller.'.php')){
            require_once($Core->controllersDir.$this->controller.'.php');
        }else{
            $Core->doOrDie();
        }

        if(is_file($Core->viewsDir.$this->view.'.html')){
            require_once($Core->viewsDir.$this->view.'.html');
        }elseif(is_file($Core->viewsDir.$this->view.'.php')){
            require_once($Core->viewsDir.$this->view.'.php');
        }
        return true;
    }

    public function setController($controller){
        $this->controller = $controller;
        return true;
    }

    public function setView($view){
        $this->view = $view;
        return true;
    }
}
?>