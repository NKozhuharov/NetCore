<?php
    class GlobalTemplates{
        public static function multyQuery(array $input, $queryType, $TABLE_SCHEMA, $TABLE_NAME, $unset = false, $updateKey = false, $updateKeyVal = false){
            global $db;

            $db->query(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = '$TABLE_SCHEMA' AND TABLE_NAME = '$TABLE_NAME'"
                , 0, 'fillArraySingleField', $columns, 'COLUMN_NAME', 'COLUMN_NAME'
            );
            if(!$columns){
                throw new Exception("Table `$TABLE_SCHEMA`.`$TABLE_NAME` does'n exist.");
            }

            $queryType = strtoupper($queryType);
            if($queryType != 'INSERT' && $queryType != 'UPDATE'){
                throw new Exception("Query type must be INSERT or UPDATE.");
            }

            if($unset){
                 if(is_array($unset)){
                     foreach($unset as $u){
                         unset($input[$u]);
                     }
                 }else{
                     unset($input[$unset]);
                 }
            }
            $input = $db->escape($input);

            if($queryType == "INSERT"){
                $keys = array();
                $vals = array();

                foreach($input as $k => $v){
                    if(isset($columns[$k])){
                        $keys[] = "`".$k."`";
                        $vals[] = "'".$v."'";
                    }
                }

                if(empty($keys)){
                    throw new Exception("Nothing to insert.");
                }

                $sqlTpl = "$queryType INTO `$TABLE_SCHEMA`.`$TABLE_NAME`(%s) VALUES(%s)";
                $sqlStr = sprintf($sqlTpl, implode(', ' , $keys), implode(', ' , $vals));
            }else{
                if(!isset($columns[$updateKey])){
                    throw new Exception("Invalid update key '$updateKey'.");
                }elseif(!$updateKeyVal){
                    throw new Exception("Invalid update key value '$updateKeyVal'.");
                }

                $kv = '';
                foreach($input as $k => $v){
                    if(isset($columns[$k])){
                        $kv .= "`".$k."` = '".$v."', ";
                    }
                }

                $kv = substr($kv, 0, -2);
                if(!$kv){
                    throw new Exception("Nothing to update.");
                }

                $sqlStr = "$queryType `$TABLE_SCHEMA`.`$TABLE_NAME` SET $kv WHERE `$updateKey` = '$updateKeyVal'";
            }

            $db->query($sqlStr) ;
        }

        public function __invoke($func){
            return self::$func();
        }

        public static function fetch_assoc($row,&$store){
            $store=$row;
            return true;
        }

        public static function fillArray($row,&$store=false,$searchitemkeys=false){
            if($searchitemkeys!==false){
                if(!is_array($searchitemkeys))
                    $searchitemkey[0]=$searchitemkeys;
                else
                    $searchitemkey=$searchitemkeys;

                foreach($searchitemkey as $c => $k){
                    if($c==0){
                        $store[$row[$k]]=$row;
                    }else
                        $store[$row[$k]]=&$store[$row[$searchitemkey[0]]];
                }
            }else{
                $store[]=$row;
            }
        }

        public static function simpleArray($row,&$store=false){
            $store[]=$row;
        }

        public static function fillArraySingleField($row,&$store=false,$searchitemkeys=false,$field=false){
            if($field==false)
                throw new Exception("Array Single Field function requires the last parameter!");
            if($searchitemkeys!==false && $searchitemkeys!=='false'){
                if(!is_array($searchitemkeys))
                    $searchitemkey[0]=$searchitemkeys;
                else
                    $searchitemkey=$searchitemkeys;
                foreach($searchitemkey as $c => $k){
                    if($c==0)
                        $store[$row[$k]]=($field!=false ? $row[$field] : $row);
                    else
                        $store[$row[$k]]=&$store[$row[$searchitemkey[0]]];
                }
            }else
                $store[]=($field!=false ? $row[$field] : $row);
        }

        public static function fillArraySinglePriority($row,&$store=false,$searchitemkeys=false,$field=false){
            if(!is_array($field) || empty($field) || count($field)<2)
                throw new Exception("Array Single Priority field param is invalid!");
            if($searchitemkeys!==false){
                if(!is_array($searchitemkeys))
                    $searchitemkey[0]=$searchitemkeys;
                else
                    $searchitemkey=$searchitemkeys;
                foreach($searchitemkey as $c => $k){
                    foreach($field as $p){
                        if(!empty($row[$p])){
                            if($c==0)
                                $store[$row[$k]]=$row[$p];
                            else
                                $store[$row[$k]]=&$store[$row[$searchitemkey[0]]];

                            break;
                        }
                    }
                }
            }else
                $store[]=($field!=false ? $row[$field] : $row);
        }

        public static function singleFieldString($row,&$store=false){
            if(!is_string($store))
                $store='';
            foreach($row as $v){
                $store.=$v;
                break;
            }
        }

        public static function singleFieldSeparatedString($row,&$store=false,$separator){
            if(!is_string($store))
                $store='';
            foreach($row as $v){
                $store.=$v.$separator;
                break;
            }
        }

        public static function void(){
            //mi void nishto ne pravi za recache i takiva neshta se polzva
        }
    }
?>