<<<<<<< HEAD
<?php
class User extends Base{
    private $sessionKey    = false;
    private $beforePass    = 'WERe]r#$%{}^JH[~ghGHJ45 #$';
    private $afterPass     = '9 Y{]}innv89789#$%^&';
    private $cookieName    = '';
    private $pageLevel     = false;
    public  $pageId        = false;
    public  $emailRequired = true;

    protected $usersLevelTableName         = false;
    protected $pagesTableName              = 'pages';
    protected $lastLoginField              = false; //set this to record last login time
    protected $usersRecoveryTableName      = false; //set this to enable password recovery functions, must contain user_id(int) and token(varchar 50)
    protected $usersRecoveryControllerName = 'recover'; //this is the controller, which handles the tokens

    protected $sessionTime = 3600; //session time in seconds

    public $user = false;

    public function __construct(){
        global $Core;

        if(!isset($_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_URI'])){
            throw new Exception($Core->language->error_remote_addr_or_request_uri_is_not_set);
        }

        $this->cookieName = 'user_'.sha1($this->tableName);

        $this->setSessionKey();
        $this->setUser();

        if($this->usersLevelTableName){
            $this->checkAccess();
        }
    }

    private function setSessionKey(){
        if(isset($_COOKIE[$this->cookieName])){
            $uniqid = $_COOKIE[$this->cookieName];
        }else{
            $uniqid = uniqid();
        }

        setcookie($this->cookieName, $uniqid, time() + $this->sessionTime, "/");
        $this->sessionKey = $this->tableName.$_SERVER['REMOTE_ADDR'].$_SERVER['HTTP_USER_AGENT'].$uniqid;
        $this->loggedIn = true;
        return true;
    }

    public function setProperty($property, $value){
        $this->user->$property = $value;
        $this->setSession($this->user);
    }

    private function setSession($user){
        global $Core;

        $Core->db->memcache->set($this->sessionKey, array("expire" => time() + $this->sessionTime,'user' => $user));
        return true;
    }

    public function resetSession(){
        global $Core;
        #var_dump($this->sessionKey);
        #exit;
        $this->user = (object) array('id' => 0, 'level' => 0, 'level_id' => 0, 'loggedIn' => false, 'pages' => false);
        $Core->db->memcache->set($this->sessionKey,array("expire" => time() + $this->sessionTime, 'user' => $this->user));
        #var_dump($this->sessionKey);
        #exit;
        return true;
    }

    private function setUser(){
        global $Core;

        $key = $Core->db->memcache->get($this->sessionKey);

        if($this->user = isset($key['expire'], $key['user'], $key['user']->loggedIn) && $key['expire'] >= time() ? $key['user'] : false){
            $this->user->id       = intval($this->user->id);
            $this->user->level    = intval($this->user->level);
            $this->user->level_id = intval($this->user->level_id);
            $this->user->loggedIn = $key['user']->loggedIn;
        }else{
            $this->user = (object) array('id' => 0, 'level' => 0, 'level_id' => 0, 'loggedIn' => false, 'pages' => false);
        }

        $this->setSession($this->user);
        return true;
    }

    public function hashPassword($pass){
        global $Core;

        return md5($this->beforePass.sha1($Core->db->escape($pass)).$this->afterPass);
    }

    public function login($un, $pw){
        global $Core;

        if(empty($un)){
            throw new Error($Core->language->error_please_enter_your_username);
        }

        if(empty($pw)){
            throw new Error($Core->language->error_please_enter_your_password);
        }

        $un = $Core->db->escape(trim($un));
        $pw = $this->hashPassword($pw);

        if($userId = $Core->db->result("SELECT `id` FROM `{$Core->dbName}`.`{$this->tableName}` WHERE (`username`='$un' OR `email` = '$un') AND `password` = '$pw'")){
            $this->setSession((object) $this->getUserInfo($userId));
            $this->setUser();
            if($this->lastLoginField){
                $Core->db->query("UPDATE `{$Core->dbName}`.`{$this->tableName}`
                SET {$this->lastLoginField} = '".$Core->globalFunctions->formatMysqlTime(time(),true)."' WHERE `id` = {$this->user->id}");
            }
        }else{
            throw new Error($Core->language->error_your_username_or_password_is_invalid);
        }
        return true;
    }

    public function loginById($id){
        global $Core;

        if(empty($id) || !is_numeric($id)){
            throw new Error($Core->language->error_invalid_id);
        }

        $userInfo = $this->getUserInfo($id);
        
        if($userInfo){
            $this->setSession((object)$userInfo);
            $this->setUser();
            if($this->lastLoginField){
                $Core->db->query("UPDATE `{$Core->dbName}`.`{$this->tableName}`
                SET {$this->lastLoginField} = '".$Core->globalFunctions->formatMysqlTime(time(),true)."' WHERE `id` = {$this->user->id}");
            }
        }
        else{
            throw new Error($Core->language->error_this_id_does_not_exist);
        }

        return true;
    }

    public function logout($url = '/login', $redirect = true){
        global $Core;

        $this->resetSession();
        
        if($redirect && (!isset($_SERVER['REQUEST_URI']) || $_SERVER['REQUEST_URI'] != $url)){
            $Core->redirect($url);
        }
        return true;
    }

    public function checkPass($newPass, $repeatPass){
        global $Core;
        if(empty($newPass)){
            throw new Error($Core->language->error_please_enter_your_password);
        }

        if(empty($repeatPass)){
            throw new Error($Core->language->error_please_repeat_your_password);
        }

        if($newPass !== $repeatPass){
            throw new Error($Core->language->error_passwords_do_not_match);
        }
        return true;
    }

    public function validatePass($pass,$min=5,$max=20,$number=false,$caps=false,$symbol=false){
        global $Core;

        if(empty($pass)){
            throw new Error($Core->language->error_please_provide_your_password);
        }
        if(stristr($pass,' ')){
            throw new Error($Core->language->error_no_spaces_are_allowed_in_the_password);
        }
        if(!empty($min) && strlen($pass) < $min){
            throw new Error($Core->language->error_password_must_be_minimum.' '.$min.' '.$Core->language->symbols);
        }
        if(!empty($max) && strlen($pass) > $max){
            throw new Error($Core->language->error_password_must_be_maximum.' '.$max.' '.$Core->language->symbols);
        }
        if($number && !preg_match("#[0-9]+#", $pass)){
            throw new Error($Core->language->error_password_must_contain_a_number);
        }
        if($caps && !preg_match("#[A-Z]+#", $pass)){
            throw new Error($Core->language->error_password_must_contain_a_capital_letter);
        }
        if($symbol && !preg_match("#\W+#", $pass)){
            throw new Error($Core->language->error_password_must_contain_a_symbol);
        }
        return $pass;
    }

    public function validateUsername($username, $min=5, $max=20){
        global $Core;
        $username = trim($username);
        if(empty($username)){
            throw new Error($Core->language->error_please_provide_your_username);
        }
        if(stristr($username,' ')){
            throw new Error($Core->language->error_no_spaces_are_allowed_in_the_username);
        }
        if(!empty($min) && strlen($username) < $min){
            throw new Error($Core->language->error_username_must_be_minimum.' '.$min.' '.$Core->language->symbols);
        }
        if(!empty($max) && strlen($username) > $max){
            throw new Error($Core->language->error_username_must_be_maximum.' '.$max.' '.$Core->language->symbols);
        }
        return $username;
    }

    public function register($un, $pw, $rpw, $email = false,  $levelId = false){
        global $Core;

        $un    = $Core->db->escape(trim($un));
        $email = $Core->db->escape(trim($email));

        if($this->usersLevelTableName && (!$levelId || !is_numeric($levelId)) || !isset($this->getUserLevels()[$levelId])){
            throw new Error($Core->language->error_user_level_id_is_not_valid);
        }elseif(empty($un)){
            throw new Error($Core->language->error_username_is_not_valid);
        }elseif(empty($pw)){
            throw new Error($Core->language->error_password_is_not_valid);
        }elseif(empty($rpw)){
            throw new Error($Core->language->error_repeat_password_is_not_valid);
        }elseif($Core->db->result("SELECT `id` FROM `{$Core->dbName}`.`{$this->tableName}` WHERE `username`='$un'")){
            throw new Error($Core->language->error_this_username_is_already_taken);
        }elseif($this->emailRequired && !filter_var($email, FILTER_VALIDATE_EMAIL)){
            throw new Error($Core->language->error_email_is_not_valid);
        }elseif($this->emailRequired && $Core->db->result("SELECT `id` FROM `{$Core->dbName}`.`{$this->tableName}` WHERE `email` = '$un'")){
            throw new Error($Core->language->error_this_email_is_already_taken);
        }

        $this->checkPass($pw, $rpw);

        $pw = $this->hashPassword($pw);

        $q = "INSERT INTO `{$Core->dbName}`.`{$this->tableName}` (`username`, `password` ";
        if($email){
            $q .= ", `email`";
        }
        if($levelId){
            $q .= ", `level_id`";
        }
        $q .= ") VALUES('$un', '$pw'";
        if($email){
            $q .= ", '$email'";
        }
        if($levelId){
            $q .= ", '$levelId'";
        }
        $q .= ")";

        $Core->db->query($q);
        return $Core->db->insert_id;
    }

    //set third parameter to check for existing password!
    /**
    *  Removing the extra params in favor of extending the validatePass function since its far less params you have to throw constantly when you need a pass validated
    */
    public function changePass($userId, $newPass, $repeatPass, $currentPass = false){//, $min = 5, $max = 20, $numbers = false, $caps = false, $symbols = false
        global $Core;

        if($currentPass !== false && empty($currentPass)){
            throw new Error($Core->language->error_please_enter_your_current_password);
        }

        $this->checkPass($newPass, $repeatPass);

        $this->validatePass($newPass);//,$min,$max,$numbers,$caps,$symbols

        $chPass = $Core->db->result("SELECT `password` FROM `{$Core->dbName}`.`{$this->tableName}` WHERE `id` = $userId");
        if(empty($chPass)){
            throw new Error($Core->language->error_this_user_does_not_exist);
        }
        if($currentPass !== false && $this->hashPassword($currentPass) != $chPass){
            throw new Error($Core->language->error_your_current_password_is_incorrect);
        }
        unset($chPass);

        $Core->db->query("UPDATE `{$Core->dbName}`.`{$this->tableName}`
        SET `password` = '".$this->hashPassword($newPass)."'
        WHERE`id` = '$userId'");

        throw new Success($Core->language->password_changed_successfully);
    }

    public function recoverUsername($email, $body = false, $subjet = false){
        global $Core;

        if(empty($email)){
            throw new Error($Core->language->error_please_enter_your_email_address);
        }

        $Core->db->query("SELECT `username` FROM `{$Core->dbName}`.`{$this->tableName}` WHERE `email` = '$email'",0,'fetch_assoc',$user);
        if(empty($user)){
            throw new Error($Core->language->error_this_user_does_not_exist);
        }

        if($body === false){
            $body = $Core->language->you_requested_a_username_recovery_.' .
            <br />'.$Core->language->your_username_is.' <b>'.$user['username'].'</b>';
        }
        else{
            $body = str_replace(array('%USERNAME%','%EMAIL%'),
                                array($user['username'],$email),$body);
        }

        if($subjet === false){
            $subjet = $Core->language->recover_username_request;
        }

        $Core->GlobalFunctions->sendEmail($Core->siteName,$Core->siteName,$subjet,$email,$body);

        throw new Success($Core->language->password_email_with_username_sent_successfully);
    }

    public function requestPasswordToken($email, $body = false, $subjet = false){
        global $Core;

        if(empty($email)){
            throw new Error($Core->language->error_please_enter_your_email_address);
        }

        $Core->db->query("SELECT `id`,`username` FROM `{$Core->dbName}`.`{$this->tableName}` WHERE `email` = '$email'",0,'fetch_assoc',$user);
        if(empty($user)){
            throw new Error($Core->language->error_this_user_does_not_exist);
        }

        //check for existing token
        $token = $Core->db->result("SELECT `token` FROM `{$Core->dbName}`.`{$this->usersRecoveryTableName}` WHERE `user_id` = '{$user['id']}'");

        if(empty($token)){
            $token = md5(uniqid());
            $Core->db->query("INSERT INTO `{$Core->dbName}`.`{$this->usersRecoveryTableName}`
                (`id`,`user_id`,`token`) VALUES (NULL,{$user['id']},'$token')
            ");
        }

        if($body === false){
            $body = $Core->language->you_requested_a_password_chanage_for.' '.$user['username'].' '.
            $Core->language->please_click_on_the_following_link_to_change_your_password.'<br />'.
            '<a href="'.$Core->siteDomain.'/'.$this->usersRecoveryControllerName.'?token='.$token.'">'.
            $Core->siteDomain.'/'.$this->usersRecoveryControllerName.'?token='.$token.'</a>.';
        }
        else{
            $body = str_replace(array('%TOKEN%','%USERNAME%','%EMAIL%','%URL%'),
                                array($token,$user['username'],$email,$Core->siteDomain.'/'.$this->usersRecoveryControllerName.'?token='.$token),$body);
        }

        if($subjet === false){
            $subjet = $Core->language->recover_password_request;
        }

        $Core->GlobalFunctions->sendEmail($Core->siteName,$Core->siteName,$subjet,$email,$body);

        throw new Success($Core->language->password_recover_token_sent_successfully);
    }

    public function recoverPassword($token, $body = false, $subjet = false){
        global $Core;

        if(empty($token)){
            throw new Error($Core->language->error_invalid_token);
        }

        $token = $Core->db->real_escape_string($token);

        $userId = $Core->db->result("SELECT `user_id` FROM `{$Core->dbName}`.`{$this->usersRecoveryTableName}` WHERE `token` = '$token'");
        if(empty($userId)){
            throw new Error($Core->language->error_invalid_token);
        }
        $Core->db->query("SELECT * FROM `{$Core->dbName}`.`{$this->tableName}` WHERE `id` = $userId",0,'fetch_assoc',$user);

        $newPass = strtoupper(substr(md5(time()),5,10));

        $Core->db->query("UPDATE `{$Core->dbName}`.`{$this->tableName}`
        SET `password` = '".$this->hashPassword($newPass)."'
        WHERE `id` = '{$user['id']}'");

        $Core->db->query("DELETE FROM `{$Core->dbName}`.`{$this->usersRecoveryTableName}` WHERE `token` = '$token'");

        if($body === false){
            $body = $Core->language->your_new_password_for.' '.$user['username'].' :<b>'.
            $newPass.'</b>';
        }
        else{
            $body = str_replace(array('%USERNAME%','%EMAIL%','%PASSWORD%'),
                                array($user['username'],$email,$newPass),$body);
        }

        if($subjet === false){
            $subjet = $Core->language->your_new_password;
        }

        $Core->GlobalFunctions->sendEmail($Core->siteName,$Core->siteName,$subjet,$user['email'],$body);

        throw new Success($Core->language->new_password_sent_successfully);
    }

    public function getUserInfo($id){
        global $Core;

        $id = intval($id);
        if(empty($id)){
            throw new Error($Core->language->error_invalid_id);
        }

        if($this->usersLevelTableName){
            $q = "
            SELECT
                 `{$Core->dbName}`.`{$this->tableName}`.*
                ,`{$Core->dbName}`.`{$this->usersLevelTableName}`.`role` AS 'user_role'
                ,`{$Core->dbName}`.`{$this->usersLevelTableName}`.`level` AS 'level'
                ,'true' AS 'loggedIn'
            FROM
                `{$Core->dbName}`.`{$this->tableName}`
            LEFT JOIN
                `{$Core->dbName}`.`{$this->usersLevelTableName}`
            ON
                `{$Core->dbName}`.`{$this->tableName}`.`level_id` = `{$Core->dbName}`.`{$this->usersLevelTableName}`.`id`
            WHERE
                `{$Core->dbName}`.`{$this->tableName}`.`id`= '$id'";
        }
        else{
            $q = "SELECT *, 'true' AS 'loggedIn', '0' AS 'level' FROM `{$Core->dbName}`.`{$this->tableName}` WHERE `{$Core->dbName}`.`{$this->tableName}`.`id`= '$id'";
        }
        if($Core->db->query($q, 0, 'fetch_assoc',$user)){
            $user['pages'] = $this->getUserPages($user['level']);
            return $user;
        }
        return false;
    }

    public function checkAccess(){
        global $Core;

        if($Core->db->query("
            SELECT
                `{$Core->dbName}`.`{$this->pagesTableName}`.`id`
                ,`{$Core->dbName}`.`{$this->usersLevelTableName}`.`level` AS 'level'
            FROM
                `{$Core->dbName}`.`{$this->pagesTableName}`
            LEFT JOIN
                `{$Core->dbName}`.`{$this->usersLevelTableName}`
            ON
                `{$Core->dbName}`.`{$this->pagesTableName}`.`level_id` = `{$Core->dbName}`.`{$this->usersLevelTableName}`.`id`
            WHERE
                `{$Core->dbName}`.`{$this->pagesTableName}`.`url` = '".$Core->db->escape($Core->rewrite->URL)."'"
            , 0,'fetch_assoc', $mainPage))
        {
            $this->pageLevel = intval($mainPage['level']);
            $this->pageId    = intval($mainPage['id']);
        }else{
            $this->pageLevel=0;
            $this->pageId=0;
            #$Core->doOrDie();
        }

        if($this->pageLevel !== 0 && ($this->user->level === 0 || $this->pageLevel < $this->user->level)){
            $Core->doOrDie();
        }
        return true;
    }

    public function getUserPages($level = false){
        global $Core;

        if(!$level){
            $level = $this->user->level;
        }

        if($this->usersLevelTableName){
            $q  = " SELECT ";
            $q .= " `{$Core->dbName}`.`{$this->pagesTableName}`.*, ";
            $q .= " `{$Core->dbName}`.`{$this->usersLevelTableName}`.`level` AS 'level'";
            $q .= " FROM `{$Core->dbName}`.`{$this->pagesTableName}` ";
            $q .= " LEFT JOIN  `{$Core->dbName}`.`{$this->usersLevelTableName}` ";
            $q .= " ON `{$Core->dbName}`.`{$this->usersLevelTableName}`.`id` = `{$Core->dbName}`.`{$this->pagesTableName}`.`level_id` ";
            $q .= " WHERE `level_id`".($level === 0 ? " = " : " >= ");
            $q .= " (SELECT `id` FROM `{$Core->dbName}`.`{$this->usersLevelTableName}` WHERE `level` = $level) ";
            $q .= " AND `name` IS NOT NULL AND `name` != '' ";
            $q .= " ORDER BY `{$Core->dbName}`.`{$this->pagesTableName}`.`order` ASC, `{$Core->dbName}`.`{$this->pagesTableName}`.`name` ASC";
        }else{
            $q = "SELECT * FROM `{$Core->dbName}`.`{$this->pagesTableName}` WHERE `name` IS NOT NULL AND `name` != '' ORDER BY `order` ASC, `name` ASC";
        }

        if($Core->db->query($q, 0, 'simpleArray', $pages)){
            return $pages;
        }
        return false;
    }

    public function getUserLevels(){
        global $Core;

        $Core->db->query("SELECT * FROM `{$Core->dbName}`.`{$this->usersLevelTableName}` WHERE `level` != '0'", 0, 'fillArray', $levels, 'id');
        return $levels;
    }
}
=======
<?php
class User extends Base{
    private $sessionKey    = false;
    private $beforePass    = 'WERe]r#$%{}^JH[~ghGHJ45 #$';
    private $afterPass     = '9 Y{]}innv89789#$%^&';
    private $cookieName    = '';
    private $pageLevel     = false;
    public  $pageId        = false;
    public  $emailRequired = true;

    protected $usersLevelTableName         = false;
    protected $pagesTableName              = 'pages';
    protected $lastLoginField              = false; //set this to record last login time
    protected $usersRecoveryTableName      = false; //set this to enable password recovery functions, must contain user_id(int) and token(varchar 50)
    protected $usersRecoveryControllerName = 'recover'; //this is the controller, which handles the tokens

    protected $sessionTime = 3600; //session time in seconds

    public $user = false;

    public function __construct(){
        global $Core;

        if(!isset($_SERVER['REMOTE_ADDR'], $_SERVER['REQUEST_URI'])){
            throw new Exception($Core->language->error_remote_addr_or_request_uri_is_not_set);
        }

        $this->cookieName = 'user_'.sha1($this->tableName);

        $this->setSessionKey();
        $this->setUser();

        if($this->usersLevelTableName){
            $this->checkAccess();
        }
    }

    private function setSessionKey(){
        if(isset($_COOKIE[$this->cookieName])){
            $uniqid = $_COOKIE[$this->cookieName];
        }else{
            $uniqid = uniqid();
        }

        setcookie($this->cookieName, $uniqid, time() + $this->sessionTime, "/");
        $this->sessionKey = $this->tableName.$_SERVER['REMOTE_ADDR'].$_SERVER['HTTP_USER_AGENT'].$uniqid;
        $this->loggedIn = true;
        return true;
    }

    public function setProperty($property, $value){
        $this->user->$property = $value;
        $this->setSession($this->user);
    }

    private function setSession($user){
        global $Core;

        $Core->db->memcache->set($this->sessionKey, array("expire" => time() + $this->sessionTime,'user' => $user));
        return true;
    }

    public function resetSession(){
        global $Core;

        $this->user = (object) array('id' => 0, 'level' => 0, 'loggedIn' => false);
        $Core->db->memcache->set($this->sessionKey,array("expire" => time() + $this->sessionTime, 'user' => $this->user));
        return true;
    }

    private function setUser(){
        global $Core;

        $key = $Core->db->memcache->get($this->sessionKey);

        if($this->user = isset($key['expire'], $key['user'], $key['user']->loggedIn) && $key['expire'] >= time() ? $key['user'] : false){
            $this->user->id       = intval($this->user->id);
            $this->user->level    = intval($this->user->level);
            $this->user->loggedIn = $key['user']->loggedIn;
        }else{
            $this->user = (object) array('id' => 0, 'level' => 0, 'loggedIn' => false);
        }

        $this->setSession($this->user);
        return true;
    }

    public function hashPassword($pass){
        global $Core;

        return md5($this->beforePass.sha1($Core->db->escape($pass)).$this->afterPass);
    }

    public function login($un, $pw){
        global $Core;

        if(empty($un)){
            throw new Error($Core->language->error_please_enter_your_username);
        }

        if(empty($pw)){
            throw new Error($Core->language->error_please_enter_your_password);
        }

        $un = $Core->db->escape(trim($un));
        $pw = $this->hashPassword($pw);

        if($userId = $Core->db->result("SELECT `id` FROM `{$Core->dbName}`.`{$this->tableName}` WHERE (`username`='$un' OR `email` = '$un') AND `password` = '$pw'")){
            $this->setSession((object) $this->getUserInfo($userId));
            $this->setUser();
            if($this->lastLoginField){
                $Core->db->query("UPDATE `{$Core->dbName}`.`{$this->tableName}`
                SET {$this->lastLoginField} = '".$Core->globalFunctions->formatMysqlTime(time(),true)."' WHERE `id` = {$this->user->id}");
            }
        }else{
            throw new Error($Core->language->error_your_username_or_password_is_invalid);
        }
        return true;
    }

    public function loginById($id){
        global $Core;

        if(empty($id) || !is_numeric($id)){
            throw new Error($Core->language->error_invalid_id);
        }

        $userInfo = $this->getUserInfo($id);
        if($userInfo){
            $this->setSession((object)$userInfo);
            $this->setUser();
            if($this->lastLoginField){
                $Core->db->query("UPDATE `{$Core->dbName}`.`{$this->tableName}`
                SET {$this->lastLoginField} = '".$Core->globalFunctions->formatMysqlTime(time(),true)."' WHERE `id` = {$this->user->id}");
            }
        }
        else{
            throw new Error($Core->language->error_this_id_does_not_exist);
        }

        return true;
    }

    public function logout($url = '/login', $redirect = true){
        global $Core;

        $this->resetSession();

        if($redirect && (!isset($_SERVER['REQUEST_URI']) || $_SERVER['REQUEST_URI'] != $url)){
            $Core->redirect($url);
        }
        return true;
    }

    public function checkPass($newPass, $repeatPass){
        global $Core;
        if(empty($newPass)){
            throw new Error($Core->language->error_please_enter_your_password);
        }

        if(empty($repeatPass)){
            throw new Error($Core->language->error_please_repeat_your_password);
        }

        if($newPass !== $repeatPass){
            throw new Error($Core->language->error_passwords_do_not_match);
        }
        return true;
    }

    public function validatePass($pass,$min=5,$max=20,$number=false,$caps=false,$symbol=false){
        global $Core;

        if(empty($pass)){
            throw new Error($Core->language->error_please_provide_your_password);
        }
        if(stristr($pass,' ')){
            throw new Error($Core->language->error_no_spaces_are_allowed_in_the_password);
        }
        if(!empty($min) && strlen($pass) < $min){
            throw new Error($Core->language->error_password_must_be_minimum.' '.$min.' '.$Core->language->symbols);
        }
        if(!empty($max) && strlen($pass) > $max){
            throw new Error($Core->language->error_password_must_be_maximum.' '.$max.' '.$Core->language->symbols);
        }
        if($number && !preg_match("#[0-9]+#", $pass)){
            throw new Error($Core->language->error_password_must_contain_a_number);
        }
        if($caps && !preg_match("#[A-Z]+#", $pass)){
            throw new Error($Core->language->error_password_must_contain_a_capital_letter);
        }
        if($symbol && !preg_match("#\W+#", $pass)){
            throw new Error($Core->language->error_password_must_contain_a_symbol);
        }
        return $pass;
    }

    public function validateUsername($username,$min=5,$max=20){
        global $Core;
        $username = trim($username);
        if(empty($username)){
            throw new Error($Core->language->error_please_provide_your_username);
        }
        if(stristr($username,' ')){
            throw new Error($Core->language->error_no_spaces_are_allowed_in_the_username);
        }
        if(!empty($min) && strlen($username) < $min){
            throw new Error($Core->language->error_username_must_be_minimum.' '.$min.' '.$Core->language->symbols);
        }
        if(!empty($max) && strlen($username) > $max){
            throw new Error($Core->language->error_username_must_be_maximum.' '.$max.' '.$Core->language->symbols);
        }
        return $username;
    }

    public function register($un, $pw, $rpw, $email = false,  $levelId = false){
        global $Core;

        $un    = $Core->db->escape(trim($un));
        $email = $Core->db->escape(trim($email));

        if($this->usersLevelTableName && (!$levelId || !is_numeric($levelId)) || !isset($this->getUserLevels()[$levelId])){
            throw new Error($Core->language->error_user_level_id_is_not_valid);
        }elseif(empty($un)){
            throw new Error($Core->language->error_username_is_not_valid);
        }elseif(empty($pw)){
            throw new Error($Core->language->error_password_is_not_valid);
        }elseif(empty($rpw)){
            throw new Error($Core->language->error_repeat_password_is_not_valid);
        }elseif($Core->db->result("SELECT `id` FROM `{$Core->dbName}`.`{$this->tableName}` WHERE `username`='$un'")){
            throw new Error($Core->language->error_this_username_is_already_taken);
        }elseif($this->emailRequired && !filter_var($email, FILTER_VALIDATE_EMAIL)){
            throw new Error($Core->language->error_email_is_not_valid);
        }elseif($this->emailRequired && $Core->db->result("SELECT `id` FROM `{$Core->dbName}`.`{$this->tableName}` WHERE `email` = '$un'")){
            throw new Error($Core->language->error_this_email_is_already_taken);
        }

        $this->checkPass($pw, $rpw);

        $pw = $this->hashPassword($pw);

        $q = "INSERT INTO `{$Core->dbName}`.`{$this->tableName}` (`username`, `password` ";
        if($email){
            $q .= ", `email`";
        }
        if($levelId){
            $q .= ", `level_id`";
        }
        $q .= ") VALUES('$un', '$pw'";
        if($email){
            $q .= ", '$email'";
        }
        if($levelId){
            $q .= ", '$levelId'";
        }
        $q .= ")";

        $Core->db->query($q);
        return $Core->db->insert_id;
    }

    //set third parameter to check for existing password!
    public function changePass($userId, $newPass, $repeatPass, $currentPass = false, $min = 5, $max = 20, $numbers = false, $caps = false, $symbols = false){
        global $Core;

        if($currentPass !== false && empty($currentPass)){
            throw new Error($Core->language->error_please_enter_your_current_password);
        }

        $this->checkPass($newPass, $repeatPass);

        $this->validatePass($newPass,$min,$max,$numbers,$caps,$symbols);

        $chPass = $Core->db->result("SELECT `password` FROM `{$Core->dbName}`.`{$this->tableName}` WHERE `id` = $userId");
        if(empty($chPass)){
            throw new Error($Core->language->error_this_user_does_not_exist);
        }
        if($currentPass !== false && $this->hashPassword($currentPass) != $chPass){
            throw new Error($Core->language->error_your_current_password_is_incorrect);
        }
        unset($chPass);

        $Core->db->query("UPDATE `{$Core->dbName}`.`{$this->tableName}`
        SET `password` = '".$this->hashPassword($newPass)."'
        WHERE`id` = '$userId'");

        throw new Success($Core->language->password_changed_successfully);
    }

    public function recoverUsername($email, $body = false, $subjet = false){
        global $Core;

        if(empty($email)){
            throw new Error($Core->language->error_please_enter_your_email_address);
        }

        $Core->db->query("SELECT `username` FROM `{$Core->dbName}`.`{$this->tableName}` WHERE `email` = '$email'",0,'fetch_assoc',$user);
        if(empty($user)){
            throw new Error($Core->language->error_this_user_does_not_exist);
        }
        
        if($body === false){
            $body = $Core->language->you_requested_a_username_recovery_.' .
            <br />'.$Core->language->your_username_is.' <b>'.$user['username'].'</b>';
        }
        else{
            $body = str_replace(array('%USERNAME%','%EMAIL%'),
                                array($user['username'],$email),$body);
        }
        
        if($subjet === false){
            $subjet = $Core->language->recover_username_request;
        }
        
        $Core->GlobalFunctions->sendEmail($Core->siteName,$Core->siteName,$subjet,$email,$body);

        throw new Success($Core->language->password_email_with_username_sent_successfully);
    }

    public function requestPasswordToken($email, $body = false, $subjet = false){
        global $Core;

        if(empty($email)){
            throw new Error($Core->language->error_please_enter_your_email_address);
        }

        $Core->db->query("SELECT `id`,`username` FROM `{$Core->dbName}`.`{$this->tableName}` WHERE `email` = '$email'",0,'fetch_assoc',$user);
        if(empty($user)){
            throw new Error($Core->language->error_this_user_does_not_exist);
        }

        //check for existing token
        $token = $Core->db->result("SELECT `token` FROM `{$Core->dbName}`.`{$this->usersRecoveryTableName}` WHERE `user_id` = '{$user['id']}'");

        if(empty($token)){
            $token = md5(uniqid());
            $Core->db->query("INSERT INTO `{$Core->dbName}`.`{$this->usersRecoveryTableName}`
                (`id`,`user_id`,`token`) VALUES (NULL,{$user['id']},'$token')
            ");
        }
        
        if($body === false){
            $body = $Core->language->you_requested_a_password_chanage_for.' '.$user['username'].' '.
            $Core->language->please_click_on_the_following_link_to_change_your_password.'<br />'.
            '<a href="'.$Core->siteDomain.'/'.$this->usersRecoveryControllerName.'?token='.$token.'">'.
            $Core->siteDomain.'/'.$this->usersRecoveryControllerName.'?token='.$token.'</a>.';
        }
        else{
            $body = str_replace(array('%TOKEN%','%USERNAME%','%EMAIL%','%URL%'),
                                array($token,$user['username'],$email,$Core->siteDomain.'/'.$this->usersRecoveryControllerName.'?token='.$token),$body);            
        }
        
        if($subjet === false){
            $subjet = $Core->language->recover_password_request;
        }
        
        $Core->GlobalFunctions->sendEmail($Core->siteName,$Core->siteName,$subjet,$email,$body);

        throw new Success($Core->language->password_recover_token_sent_successfully);
    }

    public function recoverPassword($token, $body = false, $subjet = false){
        global $Core;

        if(empty($token)){
            throw new Error($Core->language->error_invalid_token);
        }

        $token = $Core->db->real_escape_string($token);

        $userId = $Core->db->result("SELECT `user_id` FROM `{$Core->dbName}`.`{$this->usersRecoveryTableName}` WHERE `token` = '$token'");
        if(empty($userId)){
            throw new Error($Core->language->error_invalid_token);
        }
        $Core->db->query("SELECT * FROM `{$Core->dbName}`.`{$this->tableName}` WHERE `id` = $userId",0,'fetch_assoc',$user);

        $newPass = strtoupper(substr(md5(time()),5,10));

        $Core->db->query("UPDATE `{$Core->dbName}`.`{$this->tableName}`
        SET `password` = '".$this->hashPassword($newPass)."'
        WHERE `id` = '{$user['id']}'");

        $Core->db->query("DELETE FROM `{$Core->dbName}`.`{$this->usersRecoveryTableName}` WHERE `token` = '$token'");
        
        if($body === false){
            $body = $Core->language->your_new_password_for.' '.$user['username'].' :<b>'.
            $newPass.'</b>';
        }
        else{
            $body = str_replace(array('%USERNAME%','%EMAIL%','%PASSWORD%'),
                                array($user['username'],$email,$newPass),$body);
        }
        
        if($subjet === false){
            $subjet = $Core->language->your_new_password;
        }
        
        $Core->GlobalFunctions->sendEmail($Core->siteName,$Core->siteName,$subjet,$user['email'],$body);

        throw new Success($Core->language->new_password_sent_successfully);
    }

    public function getUserInfo($id){
        global $Core;

        $id = intval($id);
        if(empty($id)){
            throw new exception($Core->languge->error_invalid_id);
        }

        if($this->usersLevelTableName){
            $q = "
            SELECT
                 `{$Core->dbName}`.`{$this->tableName}`.*
                ,`{$Core->dbName}`.`{$this->usersLevelTableName}`.`role` AS 'user_role'
                ,`{$Core->dbName}`.`{$this->usersLevelTableName}`.`level` AS 'level'
                ,'true' AS 'loggedIn'
            FROM
                `{$Core->dbName}`.`{$this->tableName}`
            LEFT JOIN
                `{$Core->dbName}`.`{$this->usersLevelTableName}`
            ON
                `{$Core->dbName}`.`{$this->tableName}`.`level_id` = `{$Core->dbName}`.`{$this->usersLevelTableName}`.`id`
            WHERE
                `{$Core->dbName}`.`{$this->tableName}`.`id`= '$id'";
        }
        else{
            $q = "SELECT *, 'true' AS 'loggedIn', '0' AS 'level' FROM `{$Core->dbName}`.`{$this->tableName}` WHERE `{$Core->dbName}`.`{$this->tableName}`.`id`= '$id'";
        }
        if($Core->db->query($q, 0, 'fetch_assoc',$user)){
            return $user;
        }
        return false;
    }

    public function checkAccess(){
        global $Core;

        if($Core->db->query("
            SELECT
                `{$Core->dbName}`.`{$this->pagesTableName}`.`id`
                ,`{$Core->dbName}`.`{$this->usersLevelTableName}`.`level` AS 'level'
            FROM
                `{$Core->dbName}`.`{$this->pagesTableName}`
            LEFT JOIN
                `{$Core->dbName}`.`{$this->usersLevelTableName}`
            ON
                `{$Core->dbName}`.`{$this->pagesTableName}`.`level_id` = `{$Core->dbName}`.`{$this->usersLevelTableName}`.`id`
            WHERE
                `{$Core->dbName}`.`{$this->pagesTableName}`.`url` = '".$Core->db->escape($Core->rewrite->URL)."'"
            , 0,'fetch_assoc', $mainPage))
        {
            $this->pageLevel = intval($mainPage['level']);
            $this->pageId    = intval($mainPage['id']);
        }else{
            $Core->doOrDie();
        }

        if($this->pageLevel !== 0 && ($this->user->level === 0 || $this->pageLevel < $this->user->level)){
            $Core->doOrDie();
        }
        return true;
    }

    public function getUserPages($level = false){
        global $Core;

        if(!$level){
            $level = $this->user->level;
        }

        if($this->usersLevelTableName){
            $q  = " SELECT ";
            $q .= " `{$Core->dbName}`.`{$this->pagesTableName}`.*, ";
            $q .= " `{$Core->dbName}`.`{$this->usersLevelTableName}`.`level` AS 'level', ";
            $q .= " IF(`{$Core->dbName}`.`{$this->pagesTableName}`.`id` = '{$this->pageId}', true, false) AS 'is_current'";
            $q .= " FROM `{$Core->dbName}`.`{$this->pagesTableName}` ";
            $q .= " LEFT JOIN  `{$Core->dbName}`.`{$this->usersLevelTableName}` ";
            $q .= " ON `{$Core->dbName}`.`{$this->usersLevelTableName}`.`id` = `{$Core->dbName}`.`{$this->pagesTableName}`.`level_id` ";
            $q .= " WHERE `level_id`".($level === 0 ? " = " : " >= ");
            $q .= " (SELECT `id` FROM `{$Core->dbName}`.`{$this->usersLevelTableName}` WHERE `level` = $level) ";
            $q .= " AND `name` IS NOT NULL AND `name` != '' ";
            $q .= " ORDER BY `{$Core->dbName}`.`{$this->pagesTableName}`.`order` ASC, `{$Core->dbName}`.`{$this->pagesTableName}`.`name` ASC";
        }else{
            $q = "SELECT * FROM `{$Core->dbName}`.`{$this->pagesTableName}` WHERE `name` IS NOT NULL AND `name` != '' ORDER BY `order` ASC, `name` ASC";
        }

        if($Core->db->query($q, 0, 'simpleArray', $pages)){
            return $pages;
        }
        return false;
    }

    public function getUserLevels(){
        global $Core;

        $Core->db->query("SELECT * FROM `{$Core->dbName}`.`{$this->usersLevelTableName}` WHERE `id` != '1'", 0, 'fillArray', $levels, 'id');
        return $levels;
    }
}
>>>>>>> 278113fbed0c131102d4501761faa596e85542bf
?>