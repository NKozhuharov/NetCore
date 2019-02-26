<?php
    class PromoKeysUsage extends Base
    {
        public function __construct()
        {
            $this->tableName = 'promo_keys_usage';
            $this->parentField = 'key_id';
        }
        
        public function getKeyUsageById(int $keyId)
        {
            return $this->getCount(false, "`key_id` = $keyId");
        }
        
    }
?>