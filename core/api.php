<?php
    class API
    {
        const MAX_ITEMS_PER_PAGE = 100;
        
        const DEFAULT_ITEMS_PER_PAGE = 12;
        
        const DEFAULT_PAGE = 1;
        
        const FORBIDDEN_FILTER_NAMES = array(
            'action',
            'ajax',
            'noAppend',
            'template',
            'items_per_page',
            'limit',
            'page'
        );
        
        const QUERY_MC_REGISTER_KEY_PREFIX = 'API_MC_REGISTER_';
        
        const LOGGED_IN_QUERY_LIMIT = 3600;
        
        const NOT_LOGGED_IN_QUERY_LIMIT = 1200;
        
        protected $excludedQueryLimitIps = array();

        private $filters = array();

        private $action = false;

        private $method = false;

        private $result = array();

        private $total = 0;
        
        /**
         * Main function of the class, parses a user query.
         * To use the class (and it's children) one should call this function ONLY, the rest is done by the parameters in the $_REQUEST array
         * Child clases should contain the 'actions' functions, used to populate the results
         * Handles any errors that occured when trying to populate the response for the user
         * Adds the header 'Content-Type: application/json'
         * Ouputs a JSON encoded string, conating:
         * 'error' - the text of the error message
         * 
         * Throws Error if the requested 'action' function is not defined
         * @throw Error
         */
        public function query()
        {
            try {
                $remainingQueries = $this->getRemainingQueries();
                
                $this->getFiltersFromRequest();

                $this->getActionFromRequest();

                $this->getMethodFromAction();

                if($this->action === false) {
                    throw new Error("Define action!");
                }

                if (method_exists($this,$this->method)) {
                    $this->setDefaultPagination();

                    $this->{$this->method}();

                    $this->returnResponse($remainingQueries);
                }
                throw new Error("Invalid action!");
            }
            catch (Error $error) {
                header('Content-Type: application/json');

                exit(
                    json(
                        array(
                            'error'  => $error->getMessage()
                        )
                    )
                );
            }
        }

        /**
         * Sets the currentPage variable of the Rewrite class using the 'page' key in the $_REQUEST array
         * If it's not set, uses the DEFAULT_PAGE constan of the class
         * Sets the Core itemsPerPage variable, according to the 'items_per_page' or 'limit' keys in the $_REQUEST array
         * If both keys are present, the 'limit' key is considered
         * If none are present it uses the DEFAULT_ITEMS_PER_PAGE constan of the class
         * Throws Error if the Core items per page variable is bigger than MAX_ITEMS_PER_PAGE constant
         * @throws Error
         */
        private function setDefaultPagination()
        {
            global $Core;

            $Core->Rewrite->currentPage = isset($_REQUEST['page']) && !empty($_REQUEST['page']) ? $_REQUEST['page'] : self::DEFAULT_PAGE;

            $Core->itemsPerPage = isset($_REQUEST['items_per_page']) && !empty($_REQUEST['items_per_page']) ? $_REQUEST['items_per_page'] : self::DEFAULT_ITEMS_PER_PAGE;
            
            $Core->itemsPerPage = isset($_REQUEST['limit']) && !empty($_REQUEST['limit']) ? $_REQUEST['limit'] : self::DEFAULT_ITEMS_PER_PAGE;
            
            if (!is_numeric($Core->Rewrite->currentPage)) {
                throw new Error("Invalid page!"); 
            }
            
            if (!is_numeric($Core->itemsPerPage)) {
                throw new Error("Invalid limits!"); 
            }
            
            if ($Core->itemsPerPage > self::MAX_ITEMS_PER_PAGE) {
                throw new Error("Maximum ".self::MAX_ITEMS_PER_PAGE." items per page!");
            }
        }
        
        /**
         * The ouput function of the API class
         * Adds the header 'Content-Type: application/json'
         * Outputs json formatted result, containing:
         * 'info' => an array of objects, requested from the API, limited by the Core varaible itemsPerPage 
         * 'total' => the number of total results for the request
         * 'page' => the current page number of the pagination; uses the currentPage variable of the Rewrite class
         * 'limit' => the limit of the objects in the 'info' key; uses the Core varaible itemsPerPage 
         * 'remaining_queries' => the number of remainign queries for the user's IP address
         */
        private function returnResponse(int $remainingQueries)
        {
            global $Core;

            header('Content-Type: application/json');

            exit(
                json(
                    array(
                        'info'  => $this->result,
                        'total' => $this->total,
                        'page'  => intval($Core->Rewrite->currentPage),
                        'limit' => intval($Core->itemsPerPage),
                        'remaining_queries' => $remainingQueries
                    )
                )
            );
        }
        
        /**
         * Set the mathod propery of the class, using the action property
         */
        protected function getMethodFromAction()
        {
            $actionParts = explode('_',$this->action);
            $this->method = $actionParts[0];
            for($i = 1; $i < count($actionParts); $i++){
                $this->method .= ucfirst($actionParts[$i]);
            }
        }
        
        /**
         * Manually overwrite the mathod propery of the class, using the $method parameter
         * Throws Error if $method parameter is empty
         * @param string $method the new method name
         * @throws Error
         */
        public function setMethod(string $method)
        {
            if (!empty($method)) {
                $this->method = $method;
            }
            else {
                throw new Error("Method name cannot be empty!");
            }
        }

        /**
         * Looks for the key 'action' in the $_REQUEST array and sets the action property of the API class to it
         * Throws Error if the there are spaces in the key 'action' in the $_REQUEST array
         * Throws Error if the key 'action' in the $_REQUEST array is empty
         * Throws Error if the key 'action' in the $_REQUEST array is not defined
         * @throws Error
         */
        protected function getActionFromRequest()
        {
            if (isset($_REQUEST['action'])) {
                if (!empty($_REQUEST['action'])) {
                    $this->action = $_REQUEST['action'];
                    if (stristr($this->action,' ')) {
                        throw new Error("No spaces are allowed in the action parameter!");
                    }

                }
                else {
                    throw new Error("Action is empty!");
                }
            }
            else {
                throw new Error("Define action!");
            }
        }

        /**
         * Sets the Rewrite currentPage variable to the new value from the $page parameter
         * Throws Error if the $page is less than or 0
         * @param int $page the new page number
         * @throws Error
         */
        protected function setCurrentPage(int $page)
        {
            global $Core;

            if ($page <= 0) {
                throw new Error("Current page must be larger than 0!");
            }

            $Core->rewrite->currentPage = $page;
        }
        
        /**
         * Sets the Core itemsPerPage variable to the new count from the $itemsPerPage parameter
         * Throws Error if the $itemsPerPage is 0
         * Throws Error if the Core items per page variable is bigger than MAX_ITEMS_PER_PAGE constant
         * @param int $itemsPerPage the new items per page count
         * @throws Error
         */
        protected function setItemsPerPage(int $itemsPerPage)
        {
            global $Core;

            if ($itemsPerPage <= 0) {
                throw new Error("The items per page nubmer must be larger than 0!");
            }

            $Core->itemsPerPage = $itemsPerPage;

            if ($Core->itemsPerPage > self::MAX_ITEMS_PER_PAGE) {
                throw new Error("Maximum ".self::MAX_ITEMS_PER_PAGE." items per page!");
            }
        }

        /**
         * Populates the $filters property of the class using the $_REQUEST array
         * Ignores the names in the FORBIDDEN_FILTER_NAMES constant
         */
        protected function getFiltersFromRequest()
        {
            foreach ($_REQUEST as $filterName => $filterValue) {
                if (!in_array($filterName, self::FORBIDDEN_FILTER_NAMES)){
                    $this->addFilter($filterName,$filterValue);
                }
            }
        }
        
        /**
         * Sets a filters with name, provided by $filterName parameter and with value - $value parameter
         * Throws Error if the $filterName variable is empty
         * @param string $filterName the name of the filter
         * @param $value
         * @throws Error
         */
        protected function addFilter(string $filterName, $value)
        {
            if(empty($filterName)) {
                throw new Error("You cannot add an empty filter!");
            }

            $this->filters[$filterName] = $value;
        }
        
        /**
         * Unsets the filters with name provided in $filterName variable
         * Throws Error if the $filterName variable is empty
         * @param string $filterName the name of the filter
         * @throws Error
         */
        protected function removeFilter(string $filterName)
        {
            if(empty($filterName)) {
                throw new Error("You cannot remove an empty filter!");
            }

            unset($this->filters[$filterName]);
        }

        /**
         * Gets the value of the filter, specified by $filterName parameter
         * A filter could be false or empty string, so we cannot return these
         * Throws Error if the $filterName variable is empty
         * Throws Error if the $filterName filter is not set
         * @param string $filterName the name of the filter
         * @throws Error
         * @return string
         */
        protected function getFilterValue(string $filterName)
        {
            if (empty($filterName)) {
                throw new Error("Filter name cannot be empty!");
            }

            if (isset($this->filters[$filterName])) {
                return $this->filters[$filterName];
            }
            
            throw new Error("Filter '$filterName' not set!");
        }
        
        /**
         * Checks if a specific filter is set
         * Throws Error if the $filterName variable is empty
         * @param string $filterName the name of the filter
         * @throws Error
         * @return bool
         */
        protected function filterIsset(string $filterName)
        {
            if (empty($filterName)) {
                throw new Error("Filter name cannot be empty!");
            }

            if (isset($this->filters[$filterName])) {
                return true;
            }

            return false;
        }
        
        /**
         * Gets the list of all filters in the request
         * @return array
         */
        protected function getFilters()
        {
            return $this->filters;
        }
        
        /**
         * Check if all of the filters in the $requiredFilters vairable are present
         * @param array $requiredFilters the names of the filters to check
         * @throws Error
         */
        protected function checkRequredFilters(array $requiredFilters)
        {
            if (!empty($requiredFilters)) {
                foreach ($requiredFilters as $filter) {
                    if (!isset($this->filters[$filter])) {
                        throw new Error ("Filter \'$filter\' is requried!");
                    }

                    if (empty($this->filters[$filter])) {
                        throw new Error ("Filter \'$filter\' cannot be empty");
                    }
                }
            }
        }
        
        /**
         * Sets a result row for the API response
         * @param $value the new value for the result
         * @param bool|string $key the key for the result
         */
        protected function addToResult($value, $key = false)
        {
            if ($key === false) {
                $this->result[] = $value;
            }
            else {
                $this->result[$key] = $value;
            }
        }
        
        /**
         * Sets the result for the API response
         * @param array $value the new results value
         */
        protected function setResult(array $value)
        {
            $this->result = $value;
        }
        
        /**
         * Gets the API results
         * @return array
         */
        protected function getResult(){
            return $this->result;
        }
        
        /**
         * Sets the total results for the API response
         * @param int $total the new total value
         */
        protected function setTotal(int $total)
        {
            $this->total = $total;
        }
        
        /**
         * Gets the total results
         * @return int
         */
        protected function getTotal()
        {
            return $this->total;
        }
        
        /**
         * Checks if user is loged in
         * @param bool $throwError if this is set to true, it will throw Error when the user is not logged in
         * @throws Error
         * @return bool
         */
        protected function checkLogin($throwError = true)
        {
            global $Core;

            if (!isset($Core->{$Core->userModel}->user->id) || $Core->{$Core->userModel}->user->id <= 0) {
                if ($throwError) {
                    throw new Error("You have to be logged in to do that!");
                }
                return false;
            }
            return true;
        }
        
        /**
         * Reads the memcache entry, which indicates the remainig query limit for a speicif IP address
         * @return string
         */
        private function getQueryLimitFromQueryRegister()
        {
            global $Core;
            
            $entry = $Core->mc->get(self::QUERY_MC_REGISTER_KEY_PREFIX.md5($_SERVER['REMOTE_ADDR']));
            
            if (empty($entry)) {
                return '';
            }
            
            return json_decode($entry,true);
        }
        
        /**
         * Gets the JSON ecnoded object to put in memcache
         * @param int $count the number of queries remaining
         * @param int $timestamp the timestampt when the limit expires
         * @return string
         */
        private function getQueryRegistryObject(int $count, int $timestamp)
        {
            return json_encode(
                array(
                    'count' => $count,
                    'time'  => $timestamp
                )
            );
        }
        
        /**
         * Check how many query attemps the user has.
         * Throws Error if no attemps left
         * Increments the counter in the MC or creates it
         * @throw Error
         * @return int
         */
        private function getRemainingQueries()
        {
            global $Core;
            
            $entry = $this->getQueryLimitFromQueryRegister(); 
            
            if (!empty($entry)) {
                 $remaining = ($this->checkLogin(false)) ? 
                    (self::LOGGED_IN_QUERY_LIMIT - $entry['count']) : 
                    (self::NOT_LOGGED_IN_QUERY_LIMIT - $entry['count']);
                
                 if ($remaining <= 0 && !in_array($_SERVER['REMOTE_ADDR'], $this->excludedQueryLimitIps)) {
                    throw new Error("You are exceding the API query limit! Try again in 1 minute!");
                 }
                 
                 $Core->mc->set(
                    self::QUERY_MC_REGISTER_KEY_PREFIX.md5($_SERVER['REMOTE_ADDR']),
                    $this->getQueryRegistryObject($entry['count'] + 1, $entry['time']),
                    time() - $entry['time']
                 );
                
                return $remaining;
            }
            else {
                $Core->mc->set(
                    self::QUERY_MC_REGISTER_KEY_PREFIX.md5($_SERVER['REMOTE_ADDR']),
                    $this->getQueryRegistryObject(1, time()),
                    60
                );
                
                return ($this->checkLogin(false)) ? (self::LOGGED_IN_QUERY_LIMIT - 1) : (self::NOT_LOGGED_IN_QUERY_LIMIT - 1);
            }
        }
    }
?>