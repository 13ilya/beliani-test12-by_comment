<?php

require_once 'PEAR.php';

 /**
  * Limiter class
  *
  * Contains methods related to prepare get query, create template 
  * 
  * @version 0.1
  * 
  * @param MDB2_Driver_mysql $_db database write/read object identifier
  *
  * @param MDB2_Driver_mysql $_dbr database read (only) object identifier
  *
  * @return void
  */

class Limiter {
    private static $REDIS_TTL = 20;

    private $_db;
    private $_dbr;
    private $_redis;
    
    private $_username = '';
    
    private $_limiter_count = 0;
    private $_redis_key = false;
    
    private $_time;
    private $_uuid;
    
    public function __construct(MDB2_Driver_mysql $db = null, MDB2_Driver_mysql $dbr = null, \Redis $redis = null) {
        $this->_db = $db;
        $this->_dbr = $dbr;
        $this->_redis = $redis;
        
        $this->_time = time();
        $this->_uuid = $this->_generate_uuid();
    }
    
    /**
     * Check username/scriptname
     * 
     * @return boolean
     */
    public function check() {
        
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtolower($_SERVER['REQUEST_METHOD']) : false;
        if ($method != 'get') {
            return 1;
        }
        
        /**
         * Check enable/disable limiter for user
         */
        if ( ! $this->_dbr->getOne("SELECT `limiter` FROM `users` WHERE `username` = '" . mysql_real_escape_string($this->_username) . "'")) {
            return 2;
        }
        
        /**
         * Check enable/disable limiter for page
         */
        $_script = isset($_SERVER['SCRIPT_NAME']) ? preg_replace('#^/+#iu', '', $_SERVER['SCRIPT_NAME']) : false;
        $_script = mysql_real_escape_string($_script);
        $limiter = $this->_dbr->getAssoc("SELECT `a`.`limiter`, `a`.`limiter_count`
            FROM `acl_page` AS `a`
            LEFT JOIN `acl_php` AS `p` ON `p`.`acl_page` = `a`.`acl_page`
            WHERE `p`.`php_page` = '$_script'");
        
        if (isset($limiter[0]) || ! isset($limiter[1])) {
            return 3;
        }
        
        $this->_limiter_count = $limiter[1];
        $this->_limiter_count = 1;
        
        $redis_key = $this->_generate_redis_key();
        $redis_data = (array)$this->_redis->hGetAll($redis_key);
        $this->_redis->del($redis_key);
        
        foreach ($redis_data as $_uuid => $_time) {
            if ($_time > $this->_time - self::$REDIS_TTL) {
                $this->_redis->hSet($redis_key, $_uuid, $_time);
            }
        }
        $this->_redis->setTimeout($redis_key, self::$REDIS_TTL);
        
//        var_dump($this->pid);
        
        if ( ! $redis_data || count($redis_data) < $this->_limiter_count) {
            $this->_redis->hSet($redis_key, $this->_uuid, $this->_time);
            $this->_redis->setTimeout($redis_key, self::$REDIS_TTL);
            return true;
        }

        return false;
    }
    
    /**
     * Remove page from index? if page be unloaded
     */
    public function unload($uuid, $pid) {
        echo $out = "UUID: $uuid\nRedis key: $pid\n";

        if ($this->_redis->hGet($pid, $uuid)) {
            $this->_redis->hDel($pid, $uuid);
        }
        
//        file_put_contents('__unload.log', $out);
    }
    
    /**
     * Update settings
     */
    public function update() {
        /**
         * Update page settings
         */
        $ids = isset($_POST['ids']) ? (array)$_POST['ids'] : array();
        $enabled = isset($_POST['enabled']) ? (array)$_POST['enabled'] : array();
        $count = isset($_POST['count']) ? (array)$_POST['count'] : array();
  
        $queries = array();
        foreach ($ids as $iid) {
            $iid = (int)$iid;
            $_enabled = isset($enabled[$iid]) ? 1 : 0;
            $_count = isset($count[$iid]) && $_enabled ? (int)max(1, $count[$iid]) : 1;
            $this->_db->query("UPDATE `acl_page` SET `limiter` = $_enabled, `limiter_count` = $_count WHERE `iid` = $iid");
        }
        
        /**
         * Update users settings
         */
        $users = isset($_POST['users']) ? (array)$_POST['users'] : array();
        $users_names = array();
        
        foreach ($users as $user) {
            $users_names[] = "'" . mysql_real_escape_string($user) . "'";
        }
        
        $this->_db->query('UPDATE `users` SET `limiter` = 0');
        if ($users_names) {
            $this->_db->query('UPDATE `users` SET `limiter` = 1 WHERE `username` IN (' . implode(',', $users_names) . ')');
        }
    }
    
    /******************************************************************************************************************/
    
    private function _generate_redis_key($referer = false) {
        if ($this->_redis_key) {
            return $this->_redis_key;
        }

        return $this->_redis_key = md5($this->_username . 
                (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '') . 
                (isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : ''));

//        var_dump($referer, $this->_username, $_SERVER['SCRIPT_NAME'], $_SERVER['QUERY_STRING']);
//        
//        if ( ! $referer) {
//            return $this->_redis_key = md5($this->_username . 
//                    (isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '') . 
//                    (isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : ''));
//        }
//        else {
//            $url = isset($_SERVER['HTTP_REFERER']) ? (string)$_SERVER['HTTP_REFERER'] : '';
//            $url = parse_url($url);
//            
//            return $this->_redis_key = md5($this->_username . 
//                    (isset($url['path']) ? $url['path'] : '') . 
//                    (isset($url['query']) ? $url['query'] : ''));
//        }
    }
    
    private function _generate_uuid() {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),
            mt_rand( 0, 0xffff ),
            mt_rand( 0, 0x0fff ) | 0x4000,
            mt_rand( 0, 0x3fff ) | 0x8000,
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }
    
    public function __destruct() {
    }

    public function __set($name, $value) {
        switch ($name) {
            case 'username':
                $this->_username = $value;
                break;
        }
    }

    public function __get($name) {
        switch ($name) {
            case 'uuid':
                return $this->_uuid;
            case 'pid':
                return $this->_generate_redis_key();
        }
    }

}
