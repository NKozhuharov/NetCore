<?php
    class mc{
        private static $instance=false;
        private $Core=false;
        private $localhostIndex=false;
        public $servers=false;

        public static function getInstance($Core=false){
            if(self::$instance==false && $Core==false)
                throw new Exception("No Connection");
            if(self::$instance==false)
                self::$instance=new mc($Core);
            return self::$instance;
        }
        private function __construct($Core){
           if(!defined('MC_PREFIX'))
                define('MC_PREFIX', '');

           $this->config = $Core;
        }
        public function connect(){
            if($this->servers == false){
                foreach($this->config as $key => $server){
                    if($server['host'] == 'localhost'){
                        $this->localhostIndex = $key;
                    }
                    $this->servers[] = new Memcached();
                    $this->servers[count($this->servers)-1]->addServer($server['host'],$server['port'],2);
                }
            }
        }
        public function get($key){
            $key = md5(MC_PREFIX.$key);
            if($this->servers == false){
                $this->connect();
            }
            $val=$this->servers[$this->localhostIndex]->get($key);
            if(!empty($val) && $val!=false){
                $val=gzuncompress($val);
                $val=unserialize($val);
            }
            return $val;
        }
        public function set($key,$val,$time=false){
            $key = md5(MC_PREFIX.$key);
            if($this->servers == false){
                $this->connect();
            }
            $val=serialize($val);
            $val=gzencode($val,1,FORCE_DEFLATE);
            foreach($this->servers as $k => $v){
                if($time!=false)
                    $this->servers[$k]->set($key,$val,$time);
                else
                    $this->servers[$k]->set($key,$val);
            }
            unset($val,$key,$time);
            return true;
        }
    }
?>