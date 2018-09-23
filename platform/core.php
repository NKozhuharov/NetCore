<?php
class Core{
    //PLATFORM VAIRABLES
    //system
    public $db,$mc,$dbName,$controller,$view    = false;
    public $siteDir, $controllersDir, $viewsDir = false;
    public $defaultTimezone      = 'Europe/Sofia'; //supported timezones - http://php.net/manual/en/timezones.php
    public $cacheTime            = 0; //query cache time; set to -1 for recache
    public $ajax                 = false; //file is ajax, dont show header and footer
    public $doNotStrip           = false; //do not strip theese parameters
    public $pageNotFoundLocation = '/not-found';//moust not be numeric so the rwrite can work
    public $allowFirstPage       = false; //if allowed url like "/1" won't redirect to $pageNotFoundLocation
    public $siteClassesDir       = false; //current site classes
    public $isBot                = false; //current script is bot
    public $isPublic             = false; //current server is front (public)

    //domain
    public $siteDomain = 'https://example.com';
    public $siteName   = 'example';

    //rewrite override
    public $rewriteOverride  = array('' => 'index');

    //user model name
    public $userModel = false;

    //menu model name
    public $menuModel = false;

    //notifications(messages) model name
    public $notifyModel = false;

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
    public $debug = false; //for general debug purposes
    public $debugMysql = false; //for MySQL queries debug 

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

        $this->siteDir        = GLOBAL_PATH.(isset($info['sitepath']) ? $info['sitepath'] : site).'/';
        $this->controllersDir = $this->siteDir.'controllers/';
        $this->viewsDir       = $this->siteDir.'views/';
        return true;
    }

    public function __get($var){
        $var = strtolower($var);
        
        if(!isset($this->$var)){
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
            return $this->$var;
        }
        return false;
    }

    //IMPORTANT! CALL INIT FUNCTION RIGHT AFTER CORE CONSTRUCTOR!
    public function init(){
        date_default_timezone_set($this->defaultTimezone);

        $this->globalFunctions->stripAllFields($_POST, $this->doNotStrip);
        $this->globalFunctions->stripAllFields($_GET, $this->doNotStrip);
        $this->globalFunctions->stripAllFields($_REQUEST, $this->doNotStrip);

        //WARNING: REWRITE NGNIX IN ORDER FOR IMAGES AND FILES TO WORK
        $this->imagesStorage = GLOBAL_PATH.$this->imagesStorage;
        $this->imagesDir     = GLOBAL_PATH.$this->imagesDir;
        $this->filesDir      = GLOBAL_PATH.$this->filesDir;

        return true;
    }

    public function redirect($url = '/'){
        if($this->ajax){
            throw new Success('<script>window.location.replace("'.$url.'")</script>');
        }else{
            header("Location: ".$url, 1, 302);
            exit();
        }
        return true;
    }

    public function doOrDie($redirect = false){
        if($redirect){
            if($this->ajax){
                throw new Error('<script>window.location.replace("'.$this->pageNotFoundLocation.'")</script>');
            }else{
                header("Location: ".$this->pageNotFoundLocation, 1, 302);
                exit();
            }
        }
        else{
            header("404 Not Found",1,404);
            $this->Rewrite->setView('not-found');
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
    
    public function performanceTest(){
        $var=debug_backtrace();
        
        $time=microtime(true)-START;
        
        $lastSegment=microtime(true)-$this->lastSegment;
        $this->lastSegment=microtime(true);
        echo '<div style="color: white; background: black; position: relative;"><pre>';
        var_dump([
             'file'          => $var[0]['file']
            ,'line'          => $var[0]['line']
            ,'timeFromStart' => $time
            ,'lastSegment'   => $lastSegment
        ]);
        echo '</pre></div>';
    }
    
    public function depricated(){
        $var=debug_backtrace();
        
        ECHO (" the CALLEE function is DEPRICATED please remove it from usage");
        echo '<br><br>';
        echo 'Called in: ';
        echo '<pre>';
        var_dump($var);
        exit;
    }
    
    public function optimiseImage($url,$width='',$height=''){
        if(empty($width) || empty($height)){
            throw new Exception("Please specify width and height.");
        }
        if(substr($url,0,1)=='/')
            return $url;
        
        return '/i/'.intval($width).'-'.intval($height).'/'.base64_encode($url).'.jpeg';
    }
}
?>