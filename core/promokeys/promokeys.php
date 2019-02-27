<?php
    class PromoKeys extends Base
    {
        const MIN_KEY_LENGTH = 3;
        const MAX_KEY_LENGTH = 40;
        
        /**
         * All types of keys
         * @var array
         */
        private $typeMap = array(
            1 => 'private',
            2 => 'period',
            3 => 'limited',
            4 => 'automated',
        );
        
        /**
         * A color for every key type (for the admin panel)
         * @var array
         */
        private $typeColorMap = array(
            1 => 'blue',
            2 => 'orange',
            3 => 'green',
            4 => 'gray'
        );
        
        /**
         * All discount types
         * @var array
         */
        private $discountTypeMap = array(
            1 => 'percentage',
            2 => 'amount',
        );
        
        public function __construct()
        {
            $this->tableName = 'promo_keys';
            $this->orderByField = 'id';
            $this->orderType = 'DESC';
            $this->parentField = 'type';
            $this->searchByField = 'key';
        }
        
        /**
         * Gets the promo key types
         * @return array
         */
        public function getTypeMap()
        {
            return $this->typeMap;
        }
        
        /**
         * Gets the promo key type colors
         * @return array
         */
        public function getTypeColorMap()
        {
            return $this->typeColorMap;
        }
        
        /**
         * Gets the discount types
         * @return array
         */
        public function getDiscountTypeMap()
        {
            return $this->discountTypeMap;
        }
        
        /**
         * Adds a new key, type 'private' in the db
         * Throws Error if invalid parameters are provided
         * Returns the id of the new key
         * @param string $key - the name/text of the key
         * @param int $platformId - the key is going to be available for this plarform
         * @param string $privateUsername - the key is going to be active for this username
         * @param int $discountType - the discount type for the key
         * @param float $discount - the discount for the key
         * @param string $note - optional note for the key
         * @throws Error
         * @return int
         */
        public function addPrivate(string $key, int $platformId, string $privateUsername, int $discountType, float $discount, string $note = NULL)
        {
            $this->validatePlatformId($platformId);
             
            $this->validateKeyName($key, $platformId);
            
            $this->validateDiscount($discountType, $discount);
            
            if (empty($privateUsername)) {
                throw new Error("Provide username!");
            }
            
            return $this->add(
                array(
                    'key'              => $key,
                    'platform_id'      => $platformId,
                    'type'             => 1,
                    'discount_type'    => $discountType,
                    'discount'         => $discount,
                    'private_username' => $privateUsername,
                    'note'             => $note,
                )
            );
        }
        
        /**
         * Adds a new key, type 'period' in the db
         * Throws Error if invalid parameters are provided
         * Returns the id of the new key
         * @param string $key - the name/text of the key
         * @param int $platformId - the key is going to be available for this plarform
         * @param DateTime $date - the key is going to be active until this date
         * @param int $discountType - the discount type for the key
         * @param float $discount - the discount for the key
         * @param string $note - optional note for the key
         * @throws Error
         * @return int
         */
        public function addPeriod(string $key, int $platformId, DateTime $date, int $discountType, float $discount, string $note = NULL)
        {
            $this->validatePlatformId($platformId);
            
            $this->validateKeyName($key, $platformId);
            
            $this->validateDiscount($discountType, $discount);
            
            return $this->add(
                array(
                    'key'              => $key,
                    'platform_id'      => $platformId,
                    'type'             => 2,
                    'discount_type'    => $discountType,
                    'discount'         => $discount,
                    'expire_date'      => $date->format('Y-m-d'),
                    'note'             => $note,
                )
            );
        }
        
        /**
         * Adds a new key, type 'limited' in the db
         * Throws Error if invalid parameters are provided
         * Returns the id of the new key
         * @param string $key - the name/text of the key
         * @param int $platformId - the key is going to be available for this plarform
         * @param int $limit - the key is going to be active until this number of usages
         * @param int $discountType - the discount type for the key
         * @param float $discount - the discount for the key
         * @param string $note - optional note for the key
         * @throws Error
         * @return int
         */
        public function addLimited(string $key, int $platformId, int $limit, int $discountType, float $discount, string $note = NULL)
        {
            $this->validatePlatformId($platformId);
            
            $this->validateKeyName($key, $platformId);
            
            $this->validateDiscount($discountType, $discount);
            
            if (empty($limit)) {
                throw new Error("Provide expire limit!");
            }
            
            return $this->add(
                array(
                    'key'              => $key,
                    'platform_id'      => $platformId,
                    'type'             => 3,
                    'discount_type'    => $discountType,
                    'discount'         => $discount,
                    'expire_limit'     => $limit,
                    'note'             => $note,
                )
            );
        }
        
        /**
         * Checks if the following key exists, by it's name and platform id
         * @param string $key - the name of the key
         * @param int $platformId - the id of the plarform
         * @return bool
         */
        public function keyExists(string $key, int $platformId)
        {
            $existingKey = $this->getAll(false, true, 1, false, false, "`key` = '$key' AND `platform_id` = $platformId");
            
            return !empty($existingKey) ? true : false;
        }
        
        /**
         * Checks if the following key exists, by it's id
         * Throws Error if the id is empty
         * @param id $keyId - the id of the key
         * @throws Error
         * @return bool
         */
        public function keyIdExists(int $keyId)
        {
            if (empty($keyId)) {
                throw new Error("Provide key id!");
            }
            
            $existingKey = $this->getById($keyId);
            
            return empty($existingKey) ? false : true;
        }
        
        /**
         * Checks if the following key is expired, by it's name and platform id
         * @param string $key - the name of the key
         * @param int $platformId - the id of the plarform
         * @return bool
         */
        public function isKeyExpired(string $key, int $platformId)
        {
            $existingKey = $this->getAll(
                false, 
                true, 
                1, 
                false, 
                false, 
                "`key` = '$key' AND `expired` = 0 AND `platform_id` = $platformId"
            );
            
            return !empty($existingKey) ? false : true;
        }
        
        /**
         * Gets a key from the databse, according to it's name and platform id
         * Throws Error if the parameters are invalid, key does not exist, or it is expired
         * @param string $key - the name of the key
         * @param int $platformId - the id of the plarform
         * @throws Error
         * @return array
         */
        public function getKey(string $key, int $platformId)
        {
            $this->validatePlatformId($platformId);
            
            if (empty($key)) {
                throw new Error("Provide key!");
            }
            
            $existingKey = $this->getAll(
                false, 
                true, 
                1, 
                false, 
                false, 
                "`key` = '$key' AND `platform_id` = $platformId"
            );
            
            if (empty($existingKey)) {
                throw new Error("This key does not exist!");
            }
            
            $existingKey = current($existingKey);
            
            if ($existingKey['expired'] == 1) {
                throw new Error("This key is expired!");
            }
            
            return $existingKey;
        }
        
        /**
         * Gets a 10 symbol random string, to be used as a key name
         * @param string $seed - optional parameter for further randomization
         * @return string
         */
        public function getRandomKeyName(string $seed = NULL)
        {
            return strtoupper(substr(sha1(time() . $seed), rand(0, 20), 10));
        }
        
        /**
         * Markes the key, but it's id, as expired
         * Throws Error if the id is empty
         * @param id $keyId - the id of the key
         * @throws Error
         */
        public function deActivateKey(int $keyId)
        {
            if (!$this->keyIdExists($keyId)) {
                throw new Error("This key does not exist!");
            }
            
            $this->update($keyId, array('expired' => 1));
        }
        
        /**
         * Markes the key, but it's id, as active
         * Throws Error if the id is empty
         * @param id $keyId - the id of the key
         * @throws Error
         */
        public function activateKey(int $keyId)
        {
            if (!$this->keyIdExists($keyId)) {
                throw new Error("This key does not exist!");
            }
            
            $this->update($keyId, array('expired' => 0));
        }
        
        /**
         * Updates the expiration criteria for a key, by it's id
         * It could also update it's note
         * Checks if the key is still active/inactive after the update, according to the different criteria
         * Can only update note for 'automatic' keys
         * Throws Error if some of the parameters are invalid
         * @param id $keyId - the id of the key
         * @param string $newValue - the new value for the key criteria
         * @param string $newNote - the new value for the note
         * @throws Error
         */
        public function updateKey(int $keyId, string $newValue, string $newNote = NULL)
        {
            global $Core;
            
            if (empty($keyId)) {
                throw new Error("Provide key id!");
            }
            
            $existingKey = $this->getById($keyId);
            
            if (empty($existingKey)) {
                throw new Error("This key does not exist!");
            }
            
            if (empty($newValue) && $existingKey['type'] != 4) {
                throw new Error("Key criteria cannot be empty!");
            }
            
            if ($existingKey['type'] == 1) {
                $this->update(
                    $keyId,
                    array(
                        'private_username' => $newValue,
                        'note'             => $newNote,  
                    )
                );
            } else if ($existingKey['type'] == 2) {
                $newDate = new DateTime($newValue);
                $this->update(
                    $keyId,
                    array(
                        'expire_date' => $newDate->format('Y-m-d'),
                        'note'        => $newNote,  
                    )
                );
                
                if ($newDate->getTimestamp() < time()) {
                    $this->deActivateKey($keyId);
                } else {
                    $this->activateKey($keyId);
                }
                
                unset($newDate);
            } else if ($existingKey['type'] == 3) {
                $this->update(
                    $keyId,
                    array(
                        'expire_limit' => intval($newValue),
                        'note'         => $newNote,  
                    )
                );
                
                $keyUsage = $Core->PromoKeysUsage->getKeyUsageById($keyId);
                
                if ($keyUsage < $newValue) {
                    $this->activateKey($keyId);
                } else {
                    $this->deActivateKey($keyId);
                }
            } else if ($existingKey['type'] == 4) {
                $this->update(
                    $keyId,
                    array(
                        'note' => $newNote,  
                    )
                );
            }
        }
        
        /**
         * Records a usage for the key by it's id
         * Throws Error if parameters are invalid or the key cannot be used
         * @param string $key - the name of the key
         * @param string $userIp - the IP of the user, which is using the key
         * @param string $username - the name of the user; only considered when a 'private' key is used
         * @throws Error
         */
        public function useKey(int $keyId, string $userIp, string $username = null)
        {
            global $Core;
            
            if (empty($keyId)) {
                throw new Error("Provide key id!");
            }
            
            if (!filter_var($userIp, FILTER_VALIDATE_IP)) {
                throw new Error("Provide a valid user ip!");
            }
            
            $existingKey = $this->getById($keyId);
            
            if (empty($existingKey)) {
                throw new Error("This key does not exist!");
            }
            
            if ($existingKey['expired']) {
                throw new Error ("This key is expired!");
            }
            
            if ($existingKey['type'] == 1) {
                if (empty($username)) {
                    throw new Error("Provide username for the key!");
                }
                
                if ($username != $existingKey['private_username']) {
                    throw new Error("This key is for another user!");
                }
                
                $Core->PromoKeysUsage->add(
                    array(
                        'key_id'  => $existingKey['id'],
                        'user_ip' => $userIp
                    )
                );
                
                $this->deActivateKey($key['id']);
            } else if ($existingKey['type'] == 2) {
                $Core->PromoKeysUsage->add(
                    array(
                        'key_id'  => $existingKey['id'],
                        'user_ip' => $userIp
                    )
                );
            } else if ($existingKey['type'] == 3) {
                $Core->PromoKeysUsage->add(
                    array(
                        'key_id'  => $existingKey['id'],
                        'user_ip' => $userIp
                    )
                );
                
                $keyUsageCount = $Core->PromoKeysUsage->getCount(false, "`key_id` = {$existingKey['id']}");
                
                if ($keyUsageCount == $existingKey['expire_limit']) {
                    $this->deActivateKey($existingKey['id']);
                }
            } else if ($existingKey['type'] == 4) {
                $Core->PromoKeysUsage->add(
                    array(
                        'key_id'  => $existingKey['id'],
                        'user_ip' => $userIp
                    )
                );
                
                $this->deActivateKey($existingKey['id']);
            }
        }
        
        /**
         * Records a usage for the key by it's name
         * Throws Error if parameters are invalid or the key cannot be used
         * Returns an array, containing the id of the applied key (key_id) and the discounted sum for the order (discounted_sum)
         * @param string $key - the name of the key
         * @param int $platformId - the id of the platfrom, from where the key is used
         * @param float $totalSum - the sum before the discount
         * @param string $username - the name of the user; only considered when a 'private' key is used
         * @throws Error
         * @return array
         */
        public function applyKey(string $key, int $platformId, float $totalSum, string $username = null)
        {
            $existingKey = $this->getKey($key, $platformId);
            
            if ($existingKey['type'] == 1) {
                if (empty($username)) {
                    throw new Error("Provide username for the key!");
                }
                
                if ($username != $existingKey['private_username']) {
                    throw new Error("This key is for another user!");
                }
            }
            
            $discountedSum = 0;
            if ($existingKey['discount_type'] == 1) {
                $discountedSum = $totalSum - ($totalSum * $existingKey['discount'] / 100);
            } else if ($existingKey['discount_type'] == 2) {
                $discountedSum = $totalSum - $existingKey['discount'];
            } 
            
            if ($discountedSum < 0) {
                $discountedSum = 0;
            }
            
            return array(
                'discounted_sum' => number_format($discountedSum, 2),
                'key_id'         => $existingKey['id']
            );
        }
        
        /**
         * Checks if the provided platform id exists
         * Throws Error if the id is empty, or it doesn't exist
         * @param int $platformId - the id of the platform
         * @throws Error
         */
        private function validatePlatformId(int $platformId)
        {
            global $Core;
            
            if (empty($platformId)) {
                throw new Error("Platform id cannot be empty!");
            }
            
            $allPlatforms = $Core->PromoKeysPlatforms->getAll(false, true);
            
            if (!isset($allPlatforms[$platformId])) {
                throw new Error("This platform does not exist!");
            }
        }
        
        /**
         * Validates the provided key name
         * Throws Error if something is wrong
         * @param string $key - the name of the key
         * @param int $platformId - the id of the platform
         * @throws Error
         */
        private function validateKeyName(string $key, int $platformId)
        {
            if (empty($key)) {
                throw new Error("Key name cannot be empty!");
            }
            
            if (strlen($key) < self::MIN_KEY_LENGTH) {
                throw new Error("Key length must be more or exactly ".self::MIN_KEY_LENGTH.' symbols!');
            }
            
            if (strlen($key) > self::MAX_KEY_LENGTH) {
                throw new Error("Key length must be less or exactly ".self::MAX_KEY_LENGTH.' symbols!');
            }
            
            if (!$this->isKeyExpired($key, $platformId)) {
                throw new Error("This key is already in use!");
            }
        }
        
        /**
         * Validate the discount type and discount 
         * Throws Error if something is wrong
         * @param int $discountType - the discount type for the key
         * @param float $discount - the discount for the key
         * @throws Error
         */
        private function validateDiscount(int $discountType, float $discount)
        {
            if (empty($discount) || empty($discountType)) {
                throw new Error("Provide discount and discount type");
            }
            
            if (!isset($this->discountTypeMap[$discountType])) {
                throw new Error("Invalid discount type!");
            }
            
            if ($discount < 0) {
                throw new Error("Discout must be more than 0!");
            }
            
            if ($discountType == 1 && $discount > 100) {
                throw new Error("Percentage discount must be less or exactly 100%!");
            }
        }
    }
