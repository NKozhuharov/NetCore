<?php
    class Base{
        protected $tableName = false; //the name of the table of the parent class
        protected $parentField = false; //if the parent class has a table referencing it, this field is used for getByParentId()
        protected $autocompleteField = false; //if you use autocomplete table, this will be the field to put in it
        protected $autocompleteObjectId = false; //if you use autocomplete table, enter the object id here
        protected $autocompleteSeparator = ', '; //use this to build your autocomplete index fields if more than one fields is used in autocomplete
        protected $searchByField = false; //if there is no need for autocomplete table, the search function will search according to this field
        protected $returnPhraseOnly = true; //if this is set to true, search function will return only the id and the phrase of the result; otherwise it will return the whole row
        protected $showHiddenRows = false; //if set to true, search and getAll functios will not consider the 'hidden' columns in the db
        
        public $orderByField = 'name'; //default order by field is 'name'
        public $orderType = 'ASC'; //default order type is asc
        public $returnTimestamps = false; //use this if you need your GetWhatever function to return you a UNIX_TIMESTAMP of the timestamp fields as well

        protected $translationFields = array(); //fields in the table, which has translations in the {table}_lang
        protected $explodeFields = array(); //fields in the table which are comma separated
        protected $explodeDelimiter = '|'; //the separator for the separated fields in the table

        protected $tableFields = array(); //this is filled from get getTableInfo(); contains info for all the fields in the table
        protected $requiredFields = array(); //this is filled from get getTableInfo(); contains the required fieds in the table

        public function __construct(){}

        //this function fills the $tableFields and $requiredFields
        public function getTableInfo($noCache = false){
            global $Core;
            $Core->db->query("SELECT COLUMN_NAME AS 'id', DATA_TYPE AS 'type', IS_NULLABLE AS 'allow_null', COLUMN_DEFAULT AS 'default'
              FROM INFORMATION_SCHEMA.COLUMNS
              WHERE `table_schema` = '{$Core->dbName}' AND `table_name` = '{$this->tableName}' AND COLUMN_NAME != 'id'",$noCache ? 0 : $Core->cacheTime,false,'fillArray',$columnsInfo);

            if(empty($columnsInfo)){
                throw new exception("Table `{$this->tableName}` does not exist!");
            }

            foreach($columnsInfo as $k => $v){
                if($v["allow_null"] == "NO" && $v['type'] != 'timestamp' && $v['default'] != 'CURRENT_TIMESTAMP'){
                    $this->requiredFields[$v['id']] = $v['id'];
                    $v["allow_null"] = false;
                }
                else $v["allow_null"] = true;
                unset($v['id']);
                $this->tableFields[$k] = $v;
            }
            unset($columnsInfo);
            return true;
        }

        //get a list of the table fields
        public function getTableFields(){
            if(empty($this->tableFields)){
                $this->getTableInfo();
            }
            return $this->tableFields;
        }

        //get a list of the required fields
        public function getRequiredFields(){
            if(empty($this->tableFields)){
                $this->getTableInfo();
            }
            return $this->requiredFields;
        }

        //get a list of the translations fileds
        public function getTranslationFields(){
            return $this->translationFields;
        }

        //retuns the current tableName
        public function getTableName(){
            return $this->tableName;
        }

        //changes the current tableName. Use with caution!!!
        public function changeTableName($name){
            if(empty($name)){
                throw new exception("Invalid name!");
            }
            $this->tableName = $name;
            $this->tableFields = array();
            $this->requiredFields = array();
            $this->getTableInfo(true);
            return true;
        }
        
        //changes the current parentField; useful with changeTableName
        public function changeParentField($name){
            if(empty($name)){
                throw new exception("Invalid name!");
            }
            
            if(!isset($this->tableFields[$name])){
                throw new exception("This field does not exist in the table!");
            }
            $this->parentField = $name;
            return true;
        }

        //check if a numeric or string language is valid; returns the numeric value of the language
        public function checkLanguage($language){
            global $Core;
            if($language == false){
                return false;
            }

            if(empty($language)){
                throw new Exception ($Core->language->error_please_define_a_language);
            }

            if(is_array($language)){
                throw new Exception ($Core->language->error_language_must_a_string_or_numeric);
            }

            if(!is_numeric($language) && !isset($Core->language->langMap[$language])){
                throw new Exception ($Core->language->error_undefined_or_inactive_language);
            }
            if(!is_numeric($language)){
                $language = $Core->language->langMap[$language]['id'];
            }
            return $language;
        }

/*deprecated
        //converts timestamp into mysql date
        public function formatMysqlDate($date){
            if(!is_int($date) || $date < 0){
                throw new Exception ($Core->language->error_please_provide_a_timestamp);
            }
            return date('Y-m-d',$date);
        }
*/
        //OUTPUT FUNCTIONS
        //gets the parent id of the row in the table
        public function getParentId($objectId){
            global $Core;
            $objectId = intval($objectId);
            if(empty($objectId)){
                throw new Exception($Core->language->error_object_id_cannot_be_empty);
            }
            
            if(empty($this->parentField)){
                throw new Exception($Core->language->error_this_class_does_not_have_a_parent_id.' - '.get_class($this));
            }

            return $Core->db->result("SELECT `{$this->parentField}` FROM `{$Core->dbName}`.`{$this->tableName}` WHERE `id` = $objectId");
        }

        //returns the count of the results in the search function
        public function getCount($phrase = false, $additional = false){
            global $Core;
            if(!empty($phrase)){
                $res = $this->search($phrase,false,$additional);
                return is_array($res) ? count($res) : 0;
            }
            else{
                $Core->db->query("SELECT COUNT(*) AS 'ct' FROM `{$this->tableName}`".($additional ? " WHERE $additional" : ''),$Core->cacheTime,false,'fetch_assoc',$ct);
                return $ct['ct'];
            }
        }

        //if autocompletefields is defined - gets the search results from the autocomplete table if phrase is provided; if not gets the results from the getAll() function
        //limit parameter is accepted, default is from Core class; if you want specific limit, put it as second parameter
        //this function returns ONLY the ids of the rows
        //also checks for hidden rows, which should not be shown in the site. make sure to set the $noUser parameter to false!
        //additional parameter is added to the query usign AND
        public function search($phrase='', $limit = true, $additional = false){
            global $Core;

            if(empty($this->tableFields)){
                $this->getTableFields();
            }

            $phrase = trim($Core->db->real_escape_string($phrase));
            if(!empty($phrase)){
                if($this->autocompleteField && $this->autocompleteObjectId){
                    $q = "SELECT `autocomplete`.`object_id`, TRIM(`autocomplete`.`phrase`) AS 'phrase'";
                    $q .= " FROM `{$Core->dbName}`.`autocomplete`";
                    if(isset($this->tableFields['hidden']) && !$this->showHiddenRows){
                        $q .= " INNER JOIN `{$Core->dbName}`.`{$this->tableName}` ON `{$Core->dbName}`.`autocomplete`.`object_id` = `{$Core->dbName}`.`{$this->tableName}`.`id`";
                    }
                    $q .= " WHERE `autocomplete`.`type`={$this->autocompleteObjectId} AND `autocomplete`.`phrase` LIKE '%$phrase%'";
                    if(isset($this->tableFields['hidden']) && !$this->showHiddenRows){
                        $q .= " AND (`{$this->tableName}`.`hidden` IS NULL OR `{$this->tableName}`.`hidden` = 0)";
                    }
                    if($additional){
                        $q .= ' AND '.$additional;
                    }
                    $q .= ' GROUP BY `autocomplete`.`object_id` ORDER BY `autocomplete`.`phrase` ASC, `autocomplete`.`id` ASC';
                }
                else if($this->searchByField){
                    if($this->returnPhraseOnly){
                        $q  = "SELECT `id` AS 'object_id',`{$this->searchByField}` AS 'phrase'";
                    }
                    else{
                        $q = "SELECT *";
                    }
                    $q .= " FROM `{$Core->dbName}`.`{$this->tableName}`";
                    $q .= " WHERE `{$this->searchByField}` LIKE '%$phrase%'";
                    if(isset($this->tableFields['hidden']) && !$this->showHiddenRows){
                        $q .= " AND (`{$this->tableName}`.`hidden` IS NULL OR `{$this->tableName}`.`hidden` = 0)";
                    }
                    if($additional){
                        $q .= ' AND '.$additional;
                    }
                    
                    $q .= " ORDER BY `".((!empty($this->orderByField)) ? $this->orderByField : $this->searchByField)."` {$this->orderType}, `id` ASC";
                }
                else throw new Exception("In order to use the search function, plesae define autocompleteField and autocompleteObjectId or searchByField!");

                if($limit){
                    if(is_numeric($limit)){
                        $q.= " LIMIT ".(($Core->rewrite->currentPage - 1) * $limit).','.$limit;
                    }
                    else{
                        $q.= " LIMIT ".(($Core->rewrite->currentPage - 1) * $Core->itemsPerPage).','.$Core->itemsPerPage;
                    }
                }

                if($this->returnPhraseOnly){
                    if($Core->db->query($q,$Core->cacheTime,false,'fillArraySingleField',$result,'object_id','phrase')){
                        return $result;
                    }
                }
                else{
                    if($Core->db->query($q,$Core->cacheTime,false,'fillArray',$result,'id')){
                        return $result;
                    }
                }
                return array();
            }
            else{
                $all = $this->getAll(false,false,$limit,false,false,$additional);
                if(empty($all)){
                    return array();
                }
                $result = array();
                foreach($all as $k => $v){
                    if($this->autocompleteField && $this->autocompleteObjectId){
                        if($this->returnPhraseOnly){
                            if(is_array($this->autocompleteField)){
                                $result[$k] = array();
                                foreach($this->autocompleteField as $f){
                                    if(!empty($v[$f])){
                                        $result[$k][] = $v[$f];
                                    }
                                }
                                $result[$k] = implode($this->autocompleteSeparator,$result[$k]);
                            }
                            else{
                                $result[$k] = $v[$this->autocompleteField];
                            }
                        }
                        else return $all;
                    }
                    else if($this->searchByField){
                        if($this->returnPhraseOnly){
                            $result[$k] = $v[$this->searchByField];
                        }
                        else return $all;
                    }
                    else throw new Exception("In order to use the search function, plesae define autocompleteField and autocompleteObjectId or searchByField!");
                }
                unset($all);
                return $result;
            }
        }

        //this function returns all the elements from the table
        //TRANSLATION   - the results come out translated automatically in the language, which is chosen now;
        //TRANSLATION   - if you want specific language, put it in the first parameter ($language); if you don't want translation, set the second parameter ($noTranslation) to true
        //PAGINATION    - third parameter supports pagination, similar to the search function
        //HIDDEN FIELD  - also supports hidden field, like the search function
        //PARENT ID     - supports $parentId parameter; if this is set, it will return the elements with this parent id
        //ID            - supports $id parameter; if this is set, it will return the element from the table with the specific id
        //ADDITIONAL    - additional parameter is added to the query usign AND
        public function getAll($language = false, $noTranslation = false, $limit = false, $parentId = false, $id = false, $additional = false){
            global $Core;

            if(empty($this->tableFields)){
                $this->getTableFields();
            }

            $order = isset($this->tableFields[$this->orderByField]) ? "`{$this->orderByField}`" : '`id`';
            $ascDesc = ($this->orderType == 'ASC' || $this->orderType == 'DESC') ? $this->orderType : 'ASC';

            $q = "SELECT *";
            if($this->returnTimestamps){
                foreach($this->tableFields as $k => $v){
                    if($v['type'] == 'timestamp'){
                        $q .= ", UNIX_TIMESTAMP(`$k`) AS '{$k}_timestamp'";
                    }   
                }
                unset($k,$v);
            }
            $q.= " FROM `{$Core->dbName}`.`{$this->tableName}`";
            if(isset($this->tableFields['hidden']) && !$this->showHiddenRows){
                $q .= " WHERE (`hidden` IS NULL OR `hidden` = 0)";
            }

            if($parentId){
                if(empty($this->parentField)){
                    throw new Exception ($Core->language->error_this_object_does_not_have_a_parent);
                }

                $parentId = intval($parentId);
                if(empty($parentId)){
                    throw new Exception ($Core->language->error_parent_ID_cannot_be_empty);
                }
                $q .= " ".(stristr($q,'WHERE') ? "AND" : "WHERE")." `{$this->parentField}` = $parentId";
            }
            else if($id){
                if(is_array($id)){
                    $in = '';
                    foreach($id as $k => $v){
                        $v = intval($v);
                        if(empty($v)){
                            throw new Exception($Core->language->error_id_array_must_be_only_numerics);
                        }
                        $in .= "$v,";
                    }
                    $in = substr($in,0,-1);
                    $q .= " ".(stristr($q,'WHERE') ? "AND" : "WHERE")." `id` IN ($in)";
                    unset($k,$v,$in);
                }
                else{
                    $id = intval($id);
                    if(empty($id)){
                        throw new Exception ($Core->language->error_id_cannot_be_empty);
                    }
                    $q .= " ".(stristr($q,'WHERE') ? "AND" : "WHERE")." `id` = $id";
                }
            }

            if($additional){
                $q .= " ".(stristr($q,'WHERE') ? "AND" : "WHERE").' '.$additional;
            }

            if(empty($id)){
                $q .= " ORDER BY $order $ascDesc";
            }

            if($limit){
                if(is_numeric($limit)){
                    $q.= " LIMIT ".(($Core->rewrite->currentPage - 1) * $limit).','.$limit;
                }
                else{
                    $q.= " LIMIT ".(($Core->rewrite->currentPage - 1) * $Core->itemsPerPage).','.$Core->itemsPerPage;
                }
            }

            $Core->db->query($q,$Core->cacheTime,false,'fillArray',$result);

            if(empty($result)){
                return false;
            }
            unset($q);

            $language = $this->checkLanguage($language);
            if(!empty($this->translationFields) && ((!$noTranslation && $Core->language->useTranslation()) || (!empty($language) && $language != $Core->language->getDefaultLanguage('id')))){
                if(empty($language)){
                    $language = $Core->language->currentLanguageId;
                }
                $idList = array();
                foreach($result as $k => $v){
                    $idList = $v;
                }
                $idList = implode(',',$idList);

                $Core->db->query("SELECT * FROM `{$Core->dbName}`.`{$this->tableName}_lang` WHERE `object_id` IN ($idList) AND `lang_id`={$language}",$Core->cacheTime,false,'fillArray',$translations,'object_id');
                unset($k,$v,$idList);
                if(!empty($translations)){
                    foreach($translations as $k => $v){
                        if(isset($result[$k])){
                            foreach($this->translationFields as $field){
                                $result[$k][$field] = !empty($v[$field]) ? $v[$field] : $result[$k][$field];
                            }
                        }
                    }
                }
            }

            if(!empty($this->explodeFields)){
                $temp = $result;
                foreach($temp as $k => $v){
                    $result[$k] = $this->fixExplodeFields($v);
                }
                unset($temp);
            }

            return !empty($result) ? $result : false;
        }

        //gets a specific row from the table; support translation and notranslation like the getAll() function
        //now support an array of ints
        public function getById($id, $language = false, $noTranslation = false){
            $result = $this->getAll($language,$noTranslation,false,false,$id);
            if(is_array($result) && count($result) == 1){
                $result = current($result);
            }
            return $result;
        }

        //gets all rows with the provided parent id
        public function getByParentId($parentId, $language = false, $noTranslation = false, $limit = false){
            $result = $this->getAll($language,$noTranslation,$limit,$parentId);
            if(is_array($result) && count($result) == 1){
                $result = current($result);
            }
            return $result;
        }
        //END OUTPUT FUNCTIONS

        //INPUT FUNCTIONS
        //this validates the input array for the insert,update and translate functions
        private function prepareQueryArray($input){
            global $Core;
            if(empty($input) || !is_array($input)){
                throw new Exception ($Core->language->error_input_must_be_a_non_empty_array);
            }

            if(empty($this->tableFields)){
                $this->getTableInfo();
            }
            $allowedFields = $this->tableFields;
            $temp = array();

            $parentFunction = debug_backtrace()[1]['function'];
            if((stristr($parentFunction,'add') || $parentFunction == 'insert')){
                $requiredBuffer = $this->requiredFields;
            }
            else {
                $requiredBuffer = array();
                if(stristr($parentFunction,'translate')){
                    $allowedFields = $this->translationFields;
                }
            }

            foreach ($input as $k => $v){
                if($k == 'added'){
                    throw new Exception ($Core->language->field_added_is_not_allowed);
                }
                if($k == 'id'){
                    throw new Exception ($Core->language->field_id_is_not_allowed);
                }

                if(!isset($allowedFields[$k]) && !in_array($k,$allowedFields)){
                    throw new Exception (get_class($this).": The field $k does not exist in table {$this->tableName}!");
                }

                if(!is_array($v)){
                    $v = trim($v);
                }

                if(!empty($v) || (is_numeric($v) && $v == '0')){
                    if(!empty($requiredBuffer) && ($key = array_search($k, $requiredBuffer)) !== false) {
                        unset($requiredBuffer[$key]);
                    }
                    $fieldType = $this->tableFields[$k]['type'];
                    if(stristr($fieldType,'int') || stristr($fieldType,'double')){
                        if(!is_numeric($v)){
                            $k = str_ireplace('_id','',$k);
                            throw new Exception ($Core->language->error_field.' \"'.$Core->language->$k.'\" '.$Core->language->error_must_be_a_numeric_value);
                        }
                        $temp[$k] = $v;
                    }
                    else if($fieldType == 'date'){
                        $t = explode('-',$v);
                        if(count($t) < 3 || !checkdate($t[1],$t[2],$t[0])){
                            $k = str_ireplace('_id','',$k);
                            throw new Exception ($Core->language->error_field.' \"'.$Core->language->$k.'\" '.$Core->language->error_must_be_a_date_with_format);
                        }
                        $temp[$k] = $v;
                        unset($t);
                    }
                    else{
                        if(in_array($k,$this->explodeFields)){
                            if(is_object($v)){
                                $v = (array)$v;
                            }
                            else if(!is_array($v)){
                                $v = array($v);
                            }

                            if($k == 'languages'){
                                $tt = array();
                                foreach($v as $lang){
                                    if(empty($lang)){
                                        throw new Exception ($Core->language->error_language_cannot_be_empty);
                                    }
                                    $langMap = $Core->language->getLanguageMap(false);

                                    if(!isset($langMap[$lang])){
                                        throw new Exception ($Core->language->error_undefined_or_inactive_language);
                                    }
                                    if(!is_numeric($lang)){
                                        $lang = $langMap[$lang]['id'];
                                    }
                                    $tt[] = $lang;
                                }
                                $v = '|'.implode('|',$tt).'|';
                            }
                            else{
                                $tt = '';
                                foreach($v as $t){
                                    if(!empty($t)){
                                        $tt .= str_replace($this->explodeDelimiter,'_',$t).$this->explodeDelimiter;
                                    }
                                }
                                $v = substr($tt,0,-1);
                                unset($tt,$t);
                            }
                        }
                        else if(is_array($v)){
                            $k = str_ireplace('_id','',$k);
                            throw new Exception($Core->language->error_field.' \"'.$Core->language->$k.'\" '.$Core->language->error_must_be_numeric_string);
                        }

                        $temp[$k] = $Core->db->real_escape_string($v);
                    }
                }
                else{
                    if(stristr($parentFunction,'update') && isset($this->requiredFields[$k])){
                        $k = str_ireplace('_id','',$k);
                        throw new Exception($Core->language->error_field.' \"'.$Core->language->$k.'\" '.$Core->language->error_must_not_be_empty);
                    }
                    else $temp[$k] = '';
                }
            }

            if(!empty($requiredBuffer)){
                $temp = array();
                foreach($requiredBuffer as $r){
                    $r = str_ireplace('_id','',$r);
                    $temp[] = $Core->language->$r;
                }

                throw new Exception($Core->language->error_required_fields_missing.": ".implode(', ',$temp));
            }

            return $temp;
        }

        //general insert funciton, input is array key => value eq column_name => value
        //if $noAutocomplete is set to true, it will not insert anything into the autocomplete table
        public function add($input = false, $noAutocomplete = false){
            global $Core;

            $input = $this->prepareQueryArray($input);

            $q = "INSERT INTO `{$Core->dbName}`.`{$this->tableName}` (";
            foreach($input as $k => $v){
                $q .= "`$k`,";
            }

            $q = substr($q,0,-1).') VALUES (';
            foreach($input as $k => $v){
                $q .= ((empty($v) && !((is_numeric($v) && $v == '0'))) ? 'NULL' : (is_numeric($v) ? $v : "'$v'")).",";
            }
            $q = substr($q,0,-1).')';

            if($this->autocompleteObjectId > 0 && !$noAutocomplete){
                $acField = $this->formAutocompleteField($input);
                if(empty($acField)){
                    throw new Exception($Core->language->error_generating_index_text);
                }
            }

            try{
                $Core->db->query($q);
                $objectId = $Core->db->insert_id;
                if(isset($acField)){
                    $Core->db->query("INSERT INTO `{$Core->dbName}`.`autocomplete` (`type`,`object_id`,`phrase`) VALUES ({$this->autocompleteObjectId},$objectId,'{$acField}')");
                    unset($acField);
                }
            }
            catch(Exception $ex){
                $this->handleBuilderException($ex);
            }

            return $objectId;
        }

        //alias of the add function
        public function insert($input, $noAutocomplete = false){
            return $this->add($input,$noAutocomplete);
        }

        //deletes a row from the table and the row in the autocomplete table
        public function delete($id){
            global $Core;

            $id = intval($id);
            if(empty($id)){
                throw new Exception ($Core->language->error_id_cannot_be_empty);
            }

            try{
                $Core->db->query("DELETE FROM `{$Core->dbName}`.`{$this->tableName}` WHERE `id` = $id");
                if($this->autocompleteObjectId > 0){
                    $Core->db->query("DELETE FROM `{$Core->dbName}`.`autocomplete` WHERE `type` = {$this->autocompleteObjectId} AND `object_id` = $id");
                }
            }
            catch(Exception $ex){
                if(stristr($ex->getMessage(),'Mysql Error: Cannot delete or update a parent row: a foreign key constraint fails')){
                    $clName = get_class($this);
                    $clName = (substr($clName,-1) == 's' ? substr($clName,0,-1) : $clName);
                    $clName = strtolower($clName);

                    throw new Exception($Core->language->error_cannot_delete_this.' '.$Core->language->$clName.'! '.$Core->language->error_there_are_children_attached_to_id);
                }
                else{
                    throw new Exception($ex->getMessage());
                }
            }
            return true;
        }
        
        //deletes a set of rows from the table, according to their parent id
        public function deleteByParentId($id){
            global $Core;

            $id = intval($id);
            if(empty($id)){
                throw new Exception ($Core->language->error_id_cannot_be_empty);
            }
            
            if(empty($this->parentField)){
                throw new Exception($Core->language->error_this_class_does_not_have_a_parent_id.' - '.get_class($this));
            }
            
            try{
                if($this->autocompleteObjectId > 0){
                    $ids = $this->getByParentId($id);
                    $in = '';
                    if(isset($ids['id'])){
                        $in = $ids['id'];
                    }
                    else{
                        foreach($ids as $i){
                            $in .= $i['id'].',';    
                        }
                        unset($i);
                        $in = substr($in,0,-1);
                    }
                    $Core->db->query("DELETE FROM `{$Core->dbName}`.`autocomplete` WHERE `type` = {$this->autocompleteObjectId} AND `object_id` IN ({$in})");
                    unset($ids,$in);
                }
                $Core->db->query("DELETE FROM `{$Core->dbName}`.`{$this->tableName}` WHERE `{$this->parentField}` = $id");
            }
            catch(Exception $ex){
                if(stristr($ex->getMessage(),'Mysql Error: Cannot delete or update a parent row: a foreign key constraint fails')){
                    $clName = get_class($this);
                    $clName = (substr($clName,-1) == 's' ? substr($clName,0,-1) : $clName);
                    $clName = strtolower($clName);

                    throw new Exception($Core->language->error_cannot_delete_this.' '.$Core->language->$clName.'! '.$Core->language->error_there_are_children_attached_to_id);
                }
                else{
                    throw new Exception($ex->getMessage());
                }
            }
            return true;
        }

        //translate the object
        public function translate($objectId, $language, $input){
            global $Core;
            $language = $this->checkLanguage($language);

            $objectId = intval($objectId);
            $object = $this->getAll(false,true,false,false,$objectId);

            if(empty($object)){
                throw new Exception ($Core->language->update_failed.'(class'.get_class($this).') '.$Core->language->undefined.' '.substr($this->tableName,0,-1).'!');
            }
            unset($object);

            $input = $this->prepareQueryArray($input);

            $q = "INSERT INTO `{$Core->dbName}`.`{$this->tableName}_lang` (`object_id`,`lang_id`,";
            foreach ($input as $k => $v){
                $q .= "`$k`,";
            }

            $q = substr($q,0,-1);
            $q .= ") VALUES ($objectId,$language,";
            foreach ($input as $k => $v){
                $v = trim($Core->db->real_escape_string($v));
                $q .= (empty($v) ? 'NULL' : "'$v'").",";
                $flag = true;
            }
            $q = substr($q,0,-1);
            $q .= ') ON DUPLICATE KEY UPDATE ';
            foreach ($input as $k => $v){
                $v = trim($Core->db->real_escape_string($v));
                $q .= "`$k` = ".(empty($v) ? 'NULL' : "'$v'").",";
                $flag = true;
            }
            $q = substr($q,0,-1);

            if($this->autocompleteObjectId > 0){
                $acField = $this->formAutocompleteField($input);
                if(empty($acField)){
                    throw new Exception($Core->language->error_generating_index_text);
                }
            }

            try{
                $Core->db->query($q);

                if(isset($acField)){
                    $Core->db->query("INSERT INTO `{$Core->dbName}`.`autocomplete` (`type`,`object_id`,`phrase`,`language_id`)
                    VALUES ({$this->autocompleteObjectId},$objectId,'{$acField}',$language)
                    ON DUPLICATE KEY UPDATE `phrase` = '{$acField}'");
                    unset($acField);
                }
            }
            catch(Exception $ex){
                $this->handleBuilderException($ex);
            }
            return true;
        }
        
        //this functions updates the database row $objectId with the values from $input
        public function update($objectId,$input){
            global $Core;

            if(empty($input) || !is_array($input)){
                throw new Exception ($Core->language->error_input_must_be_a_non_empty_array);
            }
            if(isset($input['id'])){
                throw new Exception ($Core->language->error_field_id_is_not_allowed);
            }

            $objectId = intval($objectId);
            $object = $this->getAll(false,true,false,false,$objectId);

            if(empty($object)){
                throw new Exception ($Core->language->update_failed.'(class'.get_class($this).') '.$Core->language->undefined.' '.substr($this->tableName,0,-1).'!');
            }

            $input = $this->prepareQueryArray($input);
            $q = '';

            foreach ($input as $k => $v){
                $q .= "`$k` = ".((empty($v) && $v !== 0 && $v !== '0') ? 'NULL' : (is_numeric($v) ? $v : "'$v'")).",";
            }
            $q = "UPDATE `{$Core->dbName}`.`{$this->tableName}` SET ".substr($q,0,-1)." WHERE `id` = $objectId";
            
            if($this->autocompleteObjectId > 0){
                $acField = $this->formAutocompleteField($input);
                if(empty($acField)){
                    throw new Exception($Core->language->error_generating_index_text);
                }
            }

            try{
                $Core->db->query($q);
                if(isset($acField)){
                    $Core->db->query("UPDATE `{$Core->dbName}`.`autocomplete` SET `phrase` = '{$acField}'
                    WHERE `type` = {$this->autocompleteObjectId} AND `object_id` = $objectId AND `language_id`=".$Core->language->getDefaultLanguage('id'));
                    unset($acField);
                }
            }
            catch(Exception $ex){
                $this->handleBuilderException($ex);
            }
            return true;
        }
        //END INPUT FUNCTIONS

        //TEMPLATE FUNCTIONS
        public function drawTemplate($templateName,$params = false){
            if(empty($templateName)){
                throw new exception("Template name must not be empty!");
            }
            $temp = $this->getTemplate($params);
            if(in_array($templateName,get_class_methods(get_class($temp)))){
                $html = $temp->$templateName();
                unset($temp);
                return $html;
            }
            throw new exception("Template '$templateName' does not exist! It must be a public method of the ".get_class($this)."Templates class!");
        }

        public function getTemplate($params = false){
            global $Core;
            $clName = get_class($this);
            if(is_file($Core->siteDir.'templates/'.strtolower($clName).'.php')){
                require_once($Core->siteDir.'templates/'.strtolower($clName).'.php');
                $className = $clName.'Templates';
                if(!class_exists($className)){
                    throw new exception("Wrong class name in $clName.php! It must be '{$clName}Templates'!");
                }
                $temp = $params ? (new $className($params)) : new $className();
                unset($clName,$className);
                return $temp;
            }
            throw new exception("Template file $clName.php does not exist! Please create it in the 'templates' folder!");
        }
        
        //alias of getTemplate function
        public function template($params = false){
            return $this->getTemplate($params);
        }
        //END TEMPLATE FUNCUTIONS

        //SOME PRIVATE FUNCTIONS
        //this forms whats is going to be inserted into the autocomplete index table
        private function formAutocompleteField($input){
            if(is_array($this->autocompleteField)){
                $result = '';
                foreach($this->autocompleteField as $acf){
                    if(isset($input[$acf]) && !empty($input[$acf])){
                        $result.= $input[$acf].$this->autocompleteSeparator;
                    }
                }
                return substr($result,0,(0 - strlen($this->autocompleteSeparator)));
            }
            else{
                if(isset($input[$this->autocompleteField]) && !empty($input[$this->autocompleteField])){
                    return $input[$this->autocompleteField];
                }
            }

            return false;
        }

        //this is used to get the arrays from the explode fields in a result
        private function fixExplodeFields($input){
            foreach($this->explodeFields as $field){
                if(empty($input[$field]))
                    $input[$field] = array();
                else{
                    if(substr($input[$field],0,1) == $this->explodeDelimiter){
                        $input[$field] = substr($input[$field],1,-1);
                    }
                    $fld = explode($this->explodeDelimiter,$input[$field]);
                    $f = array();
                    foreach($fld as $k => $v){
                        $f[$k] = trim($v);
                    }
                    $input[$field] = $f;
                    unset($f,$fld,$k,$v);
                }
            }
            return $input;
        }

        //this is used for some automated exception handling, using the foreign key descriptions of the tables
        private function handleBuilderException($ex){
            global $Core;
            $m = $ex->getMessage();
            if(stristr($m,'duplicate')){
                $clName = get_class($this);
                $clName = (substr($clName,-1) == 's' ? substr($clName,0,-1) : $clName);
                $clName = strtolower($clName);
                if($clName == 'base'){
                    throw new Exception($Core->language->this_row_in_table.' '.$this->tableName.' '.$Core->language->error_already_exists);
                }
                throw new Exception($Core->language->this.' '.$Core->language->$clName.' '.$Core->language->error_already_exists);
            }
            else if(stristr($m,'FOREIGN KEY')){
                $m = substr($m,strpos($m,'CONSTRAINT `') + 12);
                if(stristr($m,'_')){
                    $m = substr($m,strpos($m,'_') + 1);
                }
                $m = substr($m,0,strpos($m,'`'));
                $m = str_replace('_fk','',$m);
                throw new Exception ($Core->language->error_invalid_or_undefined.' '.$Core->language->$m.'');
            }
            else if(stristr($m,'Unknown column')){
                $m = str_replace('Mysql Error: ','',$m);
                $m = str_replace(" in 'field list'",'',$m);
                throw new Exception ("$m!");
            }
            else{
                throw new Exception($m);
            }
        }
    }
?>