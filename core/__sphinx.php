<?php
    //when you use this class, always use parent::__construct() to initialize sphinx
    class Sphinx extends Base{
        const ORDER_TYPE_CUSTOM = 'EXPRESSION';
        const ORDER_TYPE_EXPRESSION = 'EXPRESSION';
        
        private $instance = false;
        private $emptyResult = array(
            'info' => array(),
            'total' => 0
        );
        
        protected $sphinxIndexName = '';
        protected $maxPageNumber = 10000;
        
        public $sortExpression = '';
        
        public $updateInMySQL = true;

        public function __get($var){
            global $Core;
            if ($var == 'instance' || $var == 'sphinx' || $var == 'sp') {
                return $this->instance;
            } else if ($var == 'maxResults') {
                return  $this->maxPageNumber * $Core->itemsPerPage;
            } else if ($var == 'maxPage' || $var == 'maxPageNumber') {
                return $this->maxPageNumber;
            } else if ($var == 'sphinxIndexName') {
                return $this->sphinxIndexName;
            }
        }

        public function __construct($indexName){
            global $Core;

            if(empty($indexName)){
                throw new Exception("Index name is required");
            }

            parent::__construct();

            if($this->instance === false){
                $this->instance = new SphinxClient();
                $this->instance->setMaxQueryTime(3000);
                $this->instance->setLimits((($Core->rewrite->currentPage - 1) * $Core->itemsPerPage), $Core->itemsPerPage, ($Core->rewrite->currentPage * $Core->itemsPerPage));
                $this->sphinxIndexName = $indexName;

                $this->updateSpinxOrderType();
            }
        }

        function __destruct(){
            $this->instance->close();
            $this->instance = false;
        }

        public function updateSpinxOrderType(){
            if (stristr($this->orderType, self::ORDER_TYPE_ASC)) {
                $this->instance->setSortMode(SPH_SORT_ATTR_ASC, $this->orderByField);
            } else if (stristr($this->orderType, self::ORDER_TYPE_DESC)) {
                $this->instance->setSortMode(SPH_SORT_ATTR_DESC, $this->orderByField);
            } else if (stristr($this->orderType, self::ORDER_TYPE_EXPRESSION)) {
                $this->instance->setSortMode(SPH_SORT_EXPR, $this->sortExpression);
            }
        }
        
        public function setDefaultLimits(){
            global $Core;
            $this->instance->setLimits((($Core->rewrite->currentPage - 1) * $Core->itemsPerPage), $Core->itemsPerPage, ($Core->rewrite->currentPage * $Core->itemsPerPage));
        }

        public function sphinxQuery($params = '', $parse = true){
            global $Core;
            
            if(!empty($this->maxPageNumber) && ($this->instance->_offset + $Core->itemsPerPage) > ($this->maxPageNumber * $Core->itemsPerPage)){
                return $this->emptyResult;
            }
            
            if($parse){
                $res = $this->parseSpinxResult($this->instance->query($params,$this->sphinxIndexName));
            }
            else{
                $res = $this->instance->query($params,$this->sphinxIndexName);
            }
            
            if($res['total'] === NULL){
                if(!empty($this->instance->_error)){
                    throw new Error ("Sphinx Error: ".$this->instance->_error);
                }
                else {
                    throw new Error("Sphinx client offline! Contact support!");
                }
            }
            
            /**
            *   Reset to default settings after the query
            */
            $this->setDefaultLimits();
            $this->instance->ResetFilters();
            $this->instance->ResetGroupBy();
            return $res;
        }

        public function parseSpinxResult($result){
            $result = array(
                'info' => isset($result['matches']) ? $result['matches'] : array(),
                'total' => $result['total_found']
            );

            if(!empty($result['info'])){
                $temp = array();
                foreach($result['info'] as $k => $v){
                    $temp[$k] = $v['attrs'];
                    $temp[$k] = array_merge(array('id' => $k), $temp[$k]);
                }
                $result['info'] = $temp;
                unset($temp,$k,$v);
            }

            return $result;
        }

        public function getAll($language = false, $noTranslation = false, $limit = false, $parentId = false, $id = false, $additional = false){
            if($id){
                return $this->getById($id);
            }

            if($parentId){
                return $this->getByParentId($parentId,$language,$noTranslation,$limit);
            }

            if($limit){
                $this->sphinx->setLimits(0,$limit,$limit);
            }

            return $this->sphinxQuery();
        }

        public function getByParentId($parentId, $language = false, $noTranslation = false, $limit = false){
            global $Core;
            $parentId = intval($parentId);
            if(empty($parentId)){
                throw new Exception($Core->language->error_parent_id_cannot_be_empty);
            }

            if(empty($this->parentField)){
                throw new Exception($Core->language->error_this_class_does_not_have_a_parent_id.' - '.get_class($this));
            }

            $this->sphinx->setFilter($this->parentField,$parentId);

            if($limit === false){
                $this->sphinx->setLimits(0,1000000,1000000);
            }
            else if(is_numeric($limit)){
                $this->sphinx->setLimits(0,$limit,$limit);
            }

            return $this->sphinxQuery();
        }

        public function getById($id, $language = false, $noTranslation = false){
            $this->sphinx->setLimits(0,(is_array($id) ? count($id) : 1),(is_array($id) ? count($id) : 1));
            $this->sphinx->setFilter('id',is_array($id) ? $id : array($id));

            $res = $this->sphinxQuery();

            return is_array($id) ? $res : (!empty($res['info']) ? $res['info'][$id] : array());
        }

        public function search($phrase='', $limit = true, $additional = false){
            global $Core;

            if($limit === false){
                $this->sphinx->setLimits(0,1000000,1000000);
            }
            else if(is_numeric($limit)){
                $this->sphinx->setLimits(0,$limit,$limit);
            }
            else if($limit === true){
                $this->setDefaultLimits();
            }

            if(!empty($phrase)){
                $phrase = '*'.$this->sphinx->escapeString($phrase).'*';
            }
            if(!empty($additional)){
                $phrase .= $additional;
            }

            return $this->sphinxQuery($phrase);
        }
        
        public function searchInMysql($phrase='', $limit = true, $additional = false)
        {
            return parent::search($phrase, $limit, $additional);
        }
        
        //WARNING: use this function for attributes ONLY!!
        //2nd WARNING: it converts floats into doubles for some reason
        //it will update Sphinx index first, then the MySQL table;
        //remember to set $this->tableName to use the function!
        public function update($objectId, $input)
        {
            global $Core;

            if (!is_numeric($objectId) && !is_array($objectId)) {
                throw new Exception ($Core->language->error_object_id_must_be_numeric_or_array);
            }
                
            if (empty($input) || !is_array($input)) {
                throw new Exception ($Core->language->error_input_must_be_a_non_empty_array);
            }
            if (isset($input['id'])) {
                throw new Exception ($Core->language->error_field_id_is_not_allowed);
            }
            
            if (is_numeric($objectId)) {
                $objectId = intval($objectId);
            }
            
            if (empty($objectId)) {
                throw new Exception($Core->language->error_object_id_cannot_be_empty);
            }
            
            if (empty($this->tableName)) {
                throw new Exception($Core->language->error_set_a_table_name_first);
            }
            
            if (is_array($objectId) && count($objectId) > 4000) {
                throw new Exception($Core->language->error_sphinx_update_supports_max_4000_objects_in_a_single_query);
            }
            
            if (is_numeric($objectId)) {
                $this->sphinx->setLimits(0, 1, 1);
                $this->sphinx->setFilter('id', array($objectId));
    
                $object = $this->sphinxQuery('', false);
                
                if (empty($object['total'])){
                    throw new Exception ($Core->language->update_failed.' (class'.get_class($this).') - '.$Core->language->undefined.' '.substr($this->tableName,0,-1).'!');
                }
            } else {
                $this->sphinx->setLimits(0, count($objectId) + 1, count($objectId) + 1);
                $this->sphinx->setFilter('id', $objectId);
    
                $object = $this->sphinxQuery('', false);
                
                #if($Core->debugSphinx) {
                    foreach ($objectId as $key => $id) {
                        if (!isset($object['matches'][$id])) {
                            if ($Core->debugSphinx) {
                                printf(get_class($this)." $id was not found! Will not update it!".PHP_EOL);
                            }
                            unset($objectId[$key]);
                        }
                    }
                #}
            }
            
            $this->setDefaultLimits();
            $this->instance->ResetFilters();
            
            $keysPlain = array();
            $valsPlain = array();
            
            $keysString = array();
            $valsString = array();
            
            foreach ($input as $k => $v) {
                if (!isset($object['attrs'][$k])) {
                    throw new Exception($Core->language->error_the_field.' "'.$k.'" '.$Core->language->error_does_not_exist);
                }
                if ($object['attrs'][$k] == 1 || $object['attrs'][$k] == 5) {
                    $keysPlain[] = $k;
                    $valsPlain[] = $v;
                } else if($object['attrs'][$k] == 7) {
                    $keysString[] = $k;
                    $valsString[] = $v;
                }
            }
            
            if (!empty($keysPlain)) {
                if (is_numeric($objectId)) {
                    $valsToUpdate = array($objectId => $valsPlain);
                } else {
                    $valsToUpdate = array();
                    foreach($objectId as $id) {
                        $valsToUpdate[$id] = $valsPlain;
                    }
                }
                
                $res = $this->instance->UpdateAttributes($this->sphinxIndexName, $keysPlain, $valsToUpdate, SPH_UPDATE_PLAIN);
            
                if(empty($res)){
                    throw new Exception ($Core->language->update_failed.' (class'.get_class($this).') ');
                }
            }
            
            if (!empty($keysString)) {
                if (is_numeric($objectId)) {
                    $valsToUpdate = array($objectId => $valsString);
                } else {
                    $valsToUpdate = array();
                    foreach($objectId as $id) {
                        $valsToUpdate[$id] = $valsString;
                    }
                }
                
                $res = $this->instance->UpdateAttributes($this->sphinxIndexName, $keysString, $valsToUpdate, SPH_UPDATE_STRING);
            }
            
            if (isset($res) && !empty($res) && $this->updateInMySQL) {
                return parent::update($objectId,$input);
            } else if ($this->updateInMySQL) {
                throw new Exception ($Core->language->update_failed.' (class'.get_class($this).') ');
            }
            return false;
        }
        
        public function getEmptySphinxResult()
        {
            return $this->emptyResult;
        }
    }
?>