<?php
class Language{
    protected $defaultLanguage = 'en';
    protected $defaultLanguageId = 41;

    public $currentLanguage = false;
    public $currentLanguageId = false;

    public $phrases = array();
    public $phrasesText = array();

    public $langMap = array();
    public $allowedLanguages = array();

    public function __construct(){
        global $Core;
        $Core->db->query("SELECT `id`,LOWER(`short`) AS 'sh' FROM `{$Core->dbName}`.`languages` WHERE `active`=1",
        0,'fillArraySingleField', $this->allowedLanguages, 'id', 'sh');

        $this->getLanguage();
        $this->getPhrases();
        $this->getLanguageMap();
    }

    public function __get($phrase){
        if(isset($this->phrases[$phrase])){
            return $this->phrases[$phrase];
        }
        else if(isset($this->phrasesText[$phrase])){
            return $this->phrasesText[$phrase];
        }
        else return $phrase;
    }

    private function getLanguage(){
        if(isset($_POST['language']) && in_array($_POST['language'], $this->allowedLanguages)){
            $this->currentLanguage = $_POST['language'];
            setcookie('language', $_POST['language'], time()+86400, '/');

            if(isset($_SERVER['HTTP_REFERER'])){
                $location = $_SERVER['HTTP_REFERER'];
            }else{
                $location = '';
            }

            $location = str_replace('&language='.$_POST['language'], '', $location);
            $location = str_replace('?language='.$_POST['language'], '', $location);

            header("Location: ".$location);
        }
        elseif(isset($_COOKIE['language']) && in_array($_COOKIE['language'], $this->allowedLanguages)){
            $this->currentLanguage = $_COOKIE['language'];
        }
        else{
            $this->currentLanguage = $this->defaultLanguage;
            $this->currentLanguageId = $this->defaultLanguageId;
        }
        return true;
    }

    private function getPhrases(){
        global $Core;
        $Core->db->query(
            "SELECT `phrase`,
            IF(`".$this->currentLanguage."` IS NULL OR `".$this->currentLanguage."` = '', `".$this->defaultLanguage."`, `".$this->currentLanguage."`) AS `translation`
            FROM `{$Core->dbName}`.`phrases`"
            ,0 , 'fillArraySingleField', $this->phrases, 'phrase', 'translation'
        );
        $Core->db->query(
            "SELECT `phrase`,
            IF(`".$this->currentLanguage."` IS NULL OR `".$this->currentLanguage."` = '', `".$this->defaultLanguage."`, `".$this->currentLanguage."`) AS `translation`
            FROM `{$Core->dbName}`.`phrases_text`"
            ,0 , 'fillArraySingleField', $this->phrasesText, 'phrase', 'translation'
        );
        return true;
    }

    public function changeLanguage($language){
        if(in_array(strtolower($language), $this->allowedLanguages)){
            $this->currentLanguage = $language;
            $this->currentLanguageId = $this->getLanguageMap()[$this->currentLanguage]['id'];
            setcookie('language', $language, time()+86400, '/');
            $this->getPhrases();
        }

        return true;
    }

    public function getLanguageMap($active = true){
        if(!empty($this->langMap) && $active){
            return $this->langMap;
        }
        global $Core;
        $Core->db->query("SELECT `id`,`name`,`native_name`,`short`,LOWER(`short`) AS 'lower' FROM `{$Core->dbName}`.`languages`".(($active) ? "WHERE `active`=1" : ''),0,'fillArray',$langMap);
        $result = array();
        foreach($langMap as $m){
            $result[$m['id']] = $m;
            $result[$m['short']] = $m;
            $result[$m['lower']] = $m;
            if(empty($this->currentLanguageId) && $m['lower'] == $this->currentLanguage){
                $this->currentLanguageId = $m['id'];
            }
        }
        unset($langMap,$m);
        if($active){
            $this->langMap = $result;
        }
        return $result;
    }

    public function getActiveLanguages($lower = false){
        if(empty($this->langMap)){
            $this->getLanguageMap();
        }

        $result = array();
        foreach($this->langMap as $k => $m){
            if(is_numeric($k)){
                $result[$k] = ($lower) ? mb_strtolower($m['short']) : $m['short'];
            }
        }
        return $result;
    }

    public function getDefaultLanguage($what){
        if($what == 'id'){
            return $this->defaultLanguageId;
        }
        if($what == 'short'){
            return $this->defaultLanguage;
        }
        return array('id' => $this->defaultLanguageId, 'short' => $this->defaultLanguage);
    }

    public function useTranslation(){
        return ($this->defaultLanguageId == $this->currentLanguageId) ? false : true;
    }

    public function getAll($translate = true){
        global $Core;

        $Core->db->query("SELECT `id`,`name` FROM `{$Core->dbName}`.`languages` ORDER BY `name` ASC",0,'fillArraySingleField',$temp,'id','name');
        if($translate){
            $langs = array();
            foreach($temp as $k => $v){
                $langs[$k] = $this->phrases[$v];
            }
            unset($temp);
            asort($langs);
            return $langs;
        }

        return $temp;
    }
}
?>