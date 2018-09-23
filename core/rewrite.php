<?php
class Rewrite{
    private $controller = false;
    private $view       = false;
    private $PATH       = false;
    public $GET         = false;
    public $URL         = false;
    public $FULLURL     = false;
    public $URLGETPART  = '';
    public $currentPage = 1;

    public function __construct($override = false){
        global $Core;

        //file is local bot
        if(!isset($_SERVER['REQUEST_URI'])){
            return false;
        }
        $this->FULLURL = urldecode($_SERVER['REQUEST_URI']);
        
        $qs=$this->FULLURL;
        if(mb_stristr($this->FULLURL, '?')){
            $qs = substr($this->FULLURL, 0, strpos($this->FULLURL, '?'));   
        }
        preg_match_all("~/([^/]*)~", $qs, $matches);
        
        $matches   = $matches[1];
        $this->GET = $matches;
        
        if(stristr($this->FULLURL,'?')){
            $this->URLGETPART = substr($this->FULLURL,strpos($this->FULLURL,'?'));
        }

        if(isset($_REQUEST['page']) && is_numeric($_REQUEST['page']) && intval($_REQUEST['page']) !== 1){
            $this->currentPage = $_REQUEST['page'];
        }elseif(isset($matches[count($matches)-1]) && is_numeric($matches[count($matches)-1]) && intval($matches[count($matches)-1]) !== 1){
            $this->currentPage = $matches[count($matches)-1];
            unset($matches[count($matches)-1]);
        }elseif($Core->allowFirstPage && isset($matches[count($matches)-1]) && is_numeric($matches[count($matches)-1]) && intval($matches[count($matches)-1]) === 1){
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
        /**
        * changed to support the first directory as controller if a controller with the full path does not exists
        *   eg: /streamers/all will try to find /controllers/streamers/all.php and if it does not exist try /controllers/streamers.php
        * 
        * reason: being less dinamic and an actual downgrade to nginx rewrites otherwise...
        * 
        * Edit: since the users no longer require a db entrance and asume that the page is free for everywhere if there isn't one
        * $this->URL is not overwritten anymore cuz it fucks with pages and shit
        */
        $this->firstDir=substr($this->PATH,0,1)=='/' ? substr($this->PATH,1) : $this->PATH;
        if(stristr($this->firstDir,'/')){
            $this->firstDir=substr($this->firstDir,0,strpos($this->firstDir,'/'));
        }
        
        if(!is_file($Core->controllersDir.$this->controller.'.php') && is_file($Core->controllersDir.$this->firstDir.'.php')){
            $this->controller=$this->firstDir;
            $this->view=$this->firstDir;
            #$this->URL='/'.$this->firstDir;
        }
        
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
    
    public function getControllerName(){
        return $this->controller;
    }
    
    private function _404(){
        header("404 Not Found",1,404);
        exit;
    }
}
?>