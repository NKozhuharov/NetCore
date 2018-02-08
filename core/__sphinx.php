<?php
    class Sphinx extends Base{
        private $instance = false;
        
        protected $sphinxIndexName = '';
        
        public function __get($var){
            if($var == 'instance' || $var == 'sphinx' || $var == 'sp'){
                return $this->instance;
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
                return $this->getByParentId($parentId);
            }
            
            if($limit){
                $this->sphinx->setLimits(0,$limit,$limit);
            }
            
            return $this->sphinxQuery();
        }
        
        public function getParentId($objectId){
            global $Core;
            $objectId = intval($objectId);
            if(empty($objectId)){
                throw new Exception($Core->language->error_object_id_cannot_be_empty);
            }
            
            if(empty($this->parentField)){
                throw new Exception($Core->language->error_this_class_does_not_have_a_parent_id.' - '.get_class($this));
            }
            
            $this->sphinx->setFilter($this->parentField,$parentId);
            $this->sphinx->setLimits(0,1000000,1000000);
            
            return $this->sphinxQuery();
        }
        
        public function getById($id, $language = false, $noTranslation = false){
            $this->sphinx->setLimits(0,(is_array($id) ? count($id) : 1),(is_array($id) ? count($id) : 1));
            $this->sphinx->setFilter('id',is_array($id) ? $id : array($id));
            
            return $this->sphinxQuery();
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
    }
?>