<?php
class Core{
    //PLATFORM VAIRABLES
    //system
    public $db,$mc,$dbName,$controller,$view  = false;
    public $siteDir, $controllersDir, $viewsDir = false;
    public $defaultTimezone      = 'Europe/Sofia'; //supported timezones - http://php.net/manual/en/timezones.php
    public $cacheTime            = 0; //query cache time; set to -1 for recache
    public $ajax                 = false; //file is ajax, dont show header and footer
    public $allowedModels        = false; //do not initialize models
    public $doNotStrip           = false; //do not strip theese parameters
    public $pageNotFoundLocation = '/not-found';//moust not be numeric so the rwrite can work
    public $siteClassesDir       = false; //current site classes
    public $isBot                = false; //current script is bot

    //domain
    public $siteDomain = 'https://example.com';
    public $siteName   = 'example';

    //core models
    #public $globalFunctions, $language, $rewrite;

    //rewrite override
    public $rewriteOverride  = array('' => 'index');

    //user model name
    public $userModel = 'user';

    //menu model name
    public $menuModel = 'menu';

    //limits
    public $folderLimit = 30000;

    //pagination limits
    public $itemsPerPage              = 25;
    public $numberOfPagesInPagination = 5;

    //images
    public $imagesStorage = 'images_org/';//original images not web accessible
    public $imagesDir     = 'images/';
    public $imagesWebDir  = '/images/';

    //files
    public $filesDir    = 'files/';
    public $filesWebDir = '/files/';

    //mail
    public $mailConfig = array('Username' => 'noreply@site.bg', 'Password' => 'pass', 'Host' => 'mail.site.bg', 'SMTPSecure' => 'ssl', 'Port' => '465');

    //metas
    public $meta = array(
        'title'          => '',
        'description'    => '',
        'keywords'       => '',
        'og_title'       => '',
        'og_description' => '',
        'og_type'        => '',
        'og_url'         => '',
        'og_image'       => ''
    );

    public $generalErrorText = 'Sorry There has been some kind of an error with your request. If this persists please Contact Us.';

    public $debugIps = array(); //for db errors

    //END OF PLATFORM VARIABLES

    public function __construct($info){
        //catch if request is ajax
        $this->ajax = (
               (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
            || (isset($_REQUEST['ajax']))
            || (isset($AJAX) && $AJAX == true)
        ) ? true : false;

        foreach(scandir(GLOBAL_PATH.'classes/core/') as $m){
            if(stristr($m,'.php')){
                require_once(GLOBAL_PATH.'classes/core/'.$m);
            }
        }

        $this->dbName = $info['db']['select']['db'];
        $this->mc     = mc::getInstance($info['mc']);
        $this->db     = db::getInstance($info['db']);

        $this->siteDir        = GLOBAL_PATH.site.'/';
        $this->controllersDir = $this->siteDir.'controllers/';
        $this->viewsDir       = $this->siteDir.'views/';

        //must rewrite nginx
        $this->imagesStorage = GLOBAL_PATH.$this->imagesStorage;
        $this->imagesDir     = GLOBAL_PATH.$this->imagesDir;
        $this->filesDir      = GLOBAL_PATH.$this->filesDir;
        $this->filesWebDir   = GLOBAL_PATH.$this->filesWebDir;

        return true;
    }

    public function __get($var){
        if(!isset($this->$var)){
            $var = strtolower($var);
            if(is_file($this->siteDir.'models/'.$var.'.php')){
                require_once($this->siteDir.'models/'.$var.'.php');
                $this->$var = new $var();
                return $this->$var;
            }
            if(is_file(GLOBAL_PATH.'classes/core/'.$var.'.php')){
                require_once(GLOBAL_PATH.'classes/core/'.$var.'.php');
                $this->$var = new $var();
                return $this->$var;
            }
            if(is_file(GLOBAL_PATH.'classes/'.$this->siteClassesDir.'/'.$var.'.php')){
                require_once(GLOBAL_PATH.'classes/'.$this->siteClassesDir.'/'.$var.'.php');
                $this->$var = new $var();
                return $this->$var;
            }
            return false;
        }
        else{
            return $this->var;
        }
        return false;
    }

    //IMPORTANT! CALL INIT FUNCTION RIGHT AFTER CORE CONSTRUCTOR!
    public function init(){
        date_default_timezone_set($this->defaultTimezone);
/*
        $this->rewrite         = new Rewrite();
        $this->globalFunctions = new GlobalFunctions();
        $this->language        = new Language();


        if($this->allowedModels && !is_array($this->allowedModels)){
            throw new Exception($this->language->error_allowed_models_must_be_an_array);
        }
*/
        $this->globalFunctions->stripAllFields($_POST, $this->doNotStrip);
        $this->globalFunctions->stripAllFields($_GET, $this->doNotStrip);
        $this->globalFunctions->stripAllFields($_REQUEST, $this->doNotStrip);
/*
        foreach(scandir($this->siteDir.'models/') as $m){
            if(stristr($m,'.php')){
                $m = str_replace('.php','',$m);
                if(!$this->allowedModels || (in_array($m,$this->allowedModels))){
                    require_once($this->siteDir.'models/'.$m.'.php');
                    $cName = ucfirst($m);
                    $this->$m = new $cName();
                    unset($cName);
                }
                unset($m);
            }
        }

        if($this->siteClassesDir){
            foreach(scandir(GLOBAL_PATH.'classes/'.$this->siteClassesDir) as $c){
                if(stristr($c,'.php')){
                    require_once(GLOBAL_PATH.'classes/'.$this->siteClassesDir.'/'.$c);
                    $c = str_replace('.php','',$c);
                    $cName = ucfirst($c);
                    $this->$c = new $cName();
                    unset($cName);
                }
            }
        }
*/
        return true;
    }

    public function redirect($url = '/'){
        if($this->ajax){
            die('<script>window.location.replace("'.$url.'")</script>');
        }else{
            header("Location: ".$url, 1, 302);
            exit();
        }
        return true;
    }

    public function doOrDie($check = false){
        if(!$check){
            header("Location: ".$this->pageNotFoundLocation, 1, 302);
            exit();
        }
        return true;
    }

    public function dump($var, $die = true){
        echo '<pre>';
        print_r($var);
        echo '</pre>';

        if($die){
            die;
        }
    }

    public function lockPage(){
        if(!in_array($_SERVER['REMOTE_ADDR'],$this->debugIps)){
            exit('This page is currently after development. We are sorry for the inconvenience. For questions please contact your developers');
        }
    }
}
?>