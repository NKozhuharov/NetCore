<?php
    class Sphinx extends Base{
        private $instance = false;
        private $emptyResult = array(
            'info' => array(),
            'total' => 0
        );
        
        protected $sphinxIndexName = '';
        protected $maxPageNumber = 10000;

        public function __get($var){
            global $Core;
            if($var == 'instance' || $var == 'sphinx' || $var == 'sp'){
                return $this->instance;
            }
            else if($var == 'maxResults'){
                return $this->maxPageNumber * $Core->itemsPerPage;
            }
            else if($var == 'maxPage' || $var == 'maxPageNumber'){
                return $this->maxPageNumber;
            }
        }

        //when you use this function, always use parent::__construct() to initialize sphinx
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
            if(stristr($this->orderType,'asc')){
                $this->instance->setSortMode(SPH_SORT_ATTR_ASC,$this->orderByField);
            }
            else if(stristr($this->orderType,'desc')){
                $this->instance->setSortMode(SPH_SORT_ATTR_DESC,$this->orderByField);
            }
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

            $this->instance->setLimits((($Core->rewrite->currentPage - 1) * $Core->itemsPerPage), $Core->itemsPerPage, ($Core->rewrite->currentPage * $Core->itemsPerPage));
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

            if(!empty($phrase)){
                $phrase = '*'.$this->sphinx->escapeString($phrase).'*';
            }
            if(!empty($additional)){
                $phrase .= $additional;
            }

            return $this->sphinxQuery($phrase);
        }
        
        //WARNING: use this function for attributes ONLY!!
        //2nd WARNING: it converts floats into doubles for some reason
        public function update($objectId,$input){
            global $Core;

            if(!is_numeric($objectId)){
                throw new Exception ($Core->language->error_object_id_must_be_numeric);
            }
            if(empty($input) || !is_array($input)){
                throw new Exception ($Core->language->error_input_must_be_a_non_empty_array);
            }
            if(isset($input['id'])){
                throw new Exception ($Core->language->error_field_id_is_not_allowed);
            }

            $objectId = intval($objectId);
            if(empty($objectId)){
                throw new Exception($Core->language->error_object_id_cannot_be_empty);
            }
            
            $this->sphinx->setLimits(0,1,1);
            $this->sphinx->setFilter('id', array($objectId));

            $object = $this->sphinxQuery('',false);
            
            if(empty($object['total'])){
                throw new Exception ($Core->language->update_failed.' (class'.get_class($this).') '.$Core->language->undefined.' '.substr($this->tableName,0,-1).'!');
            }
            
            $keysPlain = array();
            $valsPlain = array();
            
            $keysString = array();
            $valsString = array();
            
            foreach($input as $k => $v){
                if(!isset($object['attrs'][$k])){
                    throw new Exception($Core->language->error_the_field.' "'.$k.'" '.$Core->language->error_does_not_exist);
                }
                if($object['attrs'][$k] == 1 || $object['attrs'][$k] == 5){
                    $keysPlain[] = $k;
                    $valsPlain[] = $v;
                }
                else if($object['attrs'][$k] == 7){
                    $keysString[] = $k;
                    $valsString[] = $v;
                }
            }
            
            if(!empty($keysPlain)){
                $res = $this->instance->UpdateAttributes($this->sphinxIndexName,$keysPlain,array($objectId => $valsPlain),SPH_UPDATE_PLAIN);
            
                if(empty($res)){
                    throw new Exception ($Core->language->update_failed.' (class'.get_class($this).') ');
                }
            }
            
            if(!empty($keysString)){
                $res = $this->instance->UpdateAttributes($this->sphinxIndexName,$keysString,array($objectId => $valsString),SPH_UPDATE_STRING);
            }
            
            if(isset($res) && !empty($res)){
                return parent::update($objectId,$input);
            }
            else{
                throw new Exception ($Core->language->update_failed.' (class'.get_class($this).') ');
            }
            return false;
        }
    }
?>