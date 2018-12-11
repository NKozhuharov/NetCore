<?php
    if(!class_exists("mc"))
        throw new Exception("No Memcache Class Defined");
    class db{
        public $select=false;
        public $insert=false;
        public $memcache=false;
        private static $Core=false;
        private $debug=false;
        private static $instance=false;

        public function kill(){
            if(isset($this->select) && is_object($this->select)){
                $thread1=$this->select->thread_id;
                if(isset($this->insert) && is_object($this->insert))
                    $thread2=$this->insert->thread_id;
                else
                    $this->insert = false;

                $this->select->kill($thread1);
                $this->select->close();
                if($this->insert!=false && $thread1!=$thread2){

                    $this->insert->kill($thread2);
                    $this->insert->close();
                }

                unset($this->select,$this->insert);
            }
        }

        private function __construct($Core){
            $this->memcache=mc::getInstance();
        }

        public function __destruct(){
            $this->kill();
        }

        public static function getInstance($Core=false){
            if(self::$instance==false && $Core==false && self::$Core==false)
                throw new Exception("Not connected");

            if($Core!=false)
                self::$Core=$Core;

            if(self::$instance==false)
                self::$instance=new db($Core!=false ? $Core : self::$Core);

            return self::$instance;
        }

        public static function restart($server){
            if(is_object(self::$instance->$server)){
                $thread=self::$instance->$server->thread_id;
                self::$instance->$server->kill($thread);
                self::$instance->$server->close();
            }
            $func=$server.'Connect';
            self::$instance->$server=false;
            self::$instance->$func();
        }

        function selectConnect(){
            if($this->select == false){
                $Core=self::$Core;
                @$this->select=new mysqli(
                     $Core['select']['host']
                    ,$Core['select']['user']
                    ,$Core['select']['pass']
                    ,$Core['select']['db']
                );

                if(@!is_object($this->select) || @!empty($this->select->error) || !empty($this->select->connect_error)){
                    throw new Exception('Mysql server is overloaded!',1001);
                }

                $this->select->set_charset($Core['select']['charset']);
            }
        }

        function insertConnect(){
            $Core=self::$Core;
            if(isset($Core['insert'])){
                if($this->insert==false){
                    if(!isset($Core['insert']) || $Core['insert']==false || isset($allowinsert) && $allowinsert==0 || is_file("../MainServerMysql.txt") && filemtime('../MainServerMysql.txt')> (time()-120) && file_get_contents("../MainServerMysql.txt")=='0'){
                        $this->insert=false;
                    }else{
                        $this->insert=mysqli_init();
                        $this->insert->options(MYSQLI_OPT_CONNECT_TIMEOUT,2);

                        @$this->insert->real_connect(
                             $Core['insert']['host']
                            ,$Core['insert']['user']
                            ,$Core['insert']['pass']
                            ,$Core['insert']['db']
                        );

                        if(!empty($this->insert->connect_error)){
                            $this->insert=false;
                        }
                        if($this->insert!=false){
                            if(!$this->insert && is_file("/var/www/settings/MainServerMysql.txt"))
                                file_put_contents("/var/www/settings/MainServerMysql.txt",'0');
                            else
                                $this->insert->set_charset($Core['insert']['charset']);
                        }
                    }
                }
            }else{
                $this->insert=& $this->select;
            }
        }

        public function error(){
            global $Core;

            $Core->dump(
                array(
                     'select' => $this->db->select->error
                    ,'insert' => $this->db->insert->error
                ), false
            );
        }

        public function query($sql,$cacheTime=60,$template=false,&$store=false,$keys="id",$field=false){
            global $Core;

            if($Core->cacheTime == -1){
                $cacheTime = -1;
            }
            $select=0;

            if(substr(preg_replace('{[^a-zA-Z0-9]}','',strtoupper($sql)),0,strlen("SELECT"))=="SELECT")
                $select=1;
            else if(substr(preg_replace('{[^a-zA-Z0-9]}','',strtoupper($sql)),0,strlen("CALL"))=="CALL")
                $select=1;

            if($this->select == false && $select == 1)
                $this->selectConnect();

            if($select==1 && func_num_args()==1 && !isset($this->config->insert)){
                return $this->select->query($sql);
            }
            if($select){
                $newQuery=0;
                $cacheName=md5("query_".$sql);
                if($this->memcache!=false && $cacheTime>=0)
                    $var=$this->memcache->get($cacheName);
                else{
                    $var=false;
                    $newQuery=1;
                }
                
                if(($var!=false && !empty($var) && (time()>=$var['cacheTime']+($cacheTime*60))) || $var==false){
                    $newQuery=1;
                }

                if($cacheTime==false || $cacheTime==-1)
                    $newQuery=true;

                if($newQuery){
                    $query=$this->select->query("$sql");
                    if(is_object($query)){
                        if(!$query->num_rows){
                            if($cacheTime==-1)
                                $this->memcache->set($cacheName,array('cacheTime' => time(),'query' => (false)));
                            return false;
                        }
                        $queryStart=microtime(true);
                        while($row=$query->fetch_assoc()){

                            if($store!==false){
                                GlobalTemplates::$template($row,$store,$keys,$field,$query->num_rows);

                            }else{
                                if(isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'],$Core->debugIps)){
                                    $Core->dump(array($template, $row), false);
                                }
                                GlobalTemplates::$template($row);
                            }

                            if($cacheTime!=0)
                                $results[]=$row;
                        }
                        $query->free_result();
                        if($cacheTime!=0 && (!empty($results))){
                            $this->memcache->set($cacheName,array('cacheTime' => time(),'query' => ($results)));
                        }
                    }else{
                        if(isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'],$Core->debugIps)){
                            $Core->dump('ERROR OCCURED ON THIS QUERY: '.PHP_EOL.$sql);
                        }
                        throw new Exception($Core->generalErrorText);
                    }
                }else{
                    if(!empty($var['query'])){
                        foreach($var['query'] as $row){
                            if($store!==false){
                                GlobalTemplates::$template($row,$store,$keys,$field);
                            }else{
                                GlobalTemplates::$template($row);
                            }
                        }
                    }
                }
                return true;
            }else{
                $this->insertConnect();
                if($this->insert!=false){
                    $this->insert->query($sql) or $error=true;
                    if(isset($error))
                        if(!defined("noExceptionOnDuplicate") || !stristr($this->insert->error,'Duplicate entry'))
                            throw new Exception("Mysql Error: ".$this->insert->error);
                    $this->insert_id=$this->insert->insert_id;
                    return true;
                }else{
                    throw new Exception("Insert is phorbitten at the current moment.");
                }
            }
        }

        public function result($sql,$cacheTime=0){
            global $Core;

            if($Core->cacheTime == -1){
                $cacheTime = -1;
            }

            if(strtolower(substr(preg_replace('{[^a-zA-Z0-9]}','',$sql),0,strlen("SELECT")))=="select"){
                if(preg_match("{SELECT (.*) FROM}i",$sql,$matches)){
                    $cacheName=md5("result_".$sql);
                    if($cacheTime<=0)
                        $var=false;
                    else
                        $var=$this->memcache->get($cacheName);
                    if($var!=false && (time()<=$var['cacheTime']+($cacheTime*60))){
                        return $var['query'];
                    }else{
                        if($this->select == false)
                            $this->selectConnect();
                        $queryStart=microtime(true);
                        $query=$this->select->query($sql);
                        if(is_object($query)){
                            $row=$query->fetch_assoc();
                            $query->free_result();
                            $field=$matches[1];
                            if(stristr($field,'from'))
                                $field=substr($field,0,stripos($field,' from'));

                            if(isset($row[$field])){
                                if($cacheTime!=0 && ($row[$field]!==false || $cacheTime==-1))
                                    $this->memcache->set($cacheName,array("cacheTime" => time(),'query' => $row[$field]));
                                return $row[$field];
                            }else{
                                $field=str_replace('`','',$field);
                                if(isset($row[$field]))
                                    return $row[$field];
                            }
                            return false;
                        }else{
                            if(isset($_SERVER['REMOTE_ADDR']) && in_array($_SERVER['REMOTE_ADDR'],$Core->debugIps)){
                                $Core->dump('ERROR OCCURED ON THIS QUERY: '.PHP_EOL.$sql);
                            }
                            throw new Exception($Core->generalErrorText);
                        }
                    }
                }
            }
            return false;
        }

        public function escape($var, $strip=true){
            if($this->select == false)
                $this->selectConnect();
            if(!is_array($var) && !is_object($var)){
                if($strip)
                {
                    $var=stripcslashes($var);
                }
                $var=$this->select->real_escape_string($var);
            }elseif(is_object($var)){
                throw new Exception($dev.' '.$var);
            }else{
                foreach($var as $k => $v){
                    $var[$k]=$this->escape($v);
                }
            }
            return $var;
        }

        public function real_escape_string($var){
            return $this->escape($var);
        }

        public function escape_string($var){
            return $this->escape($var);
        }

        public function close(){
            return false;
        }
    }
?>