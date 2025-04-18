<?php
require_once 'PEAR.php';
    require_once 'config.php';

class User
{
    /**
     * Count of acceptable attempts for input token
     * @type int
     */
    const TOKEN_LOGIN_ATTEMPTS = 3;

    var $data;
    var $_db;
    var $_dbr;
    var $_error;

    private $_null_fields = [];

    function User($db, $dbr, $username, $exactly=0, $complete=0)
    {
        $this->_db = $db;$this->_dbr = $dbr;
        $r = $this->_dbr->getAll("EXPLAIN users");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
        foreach($r as $field) {
	            if ($field->Null == 'YES') {
	                $this->_null_fields[] = $field->Field;
	            }
		}
        if (!$username) {
            $this->data = new stdClass;
            foreach($r as $field) {
                $field = $field->Field;
                $this->data->$field = '';
            }
            $this->_isNew = true;
        } else {
            $r = $this->_dbr->getRow("SELECT * FROM users WHERE username='$username'");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r;
            if (!$exactly) {
                if (!$this->data && !$exactly) {
                   $this->data = $this->_dbr->getRow("SELECT * FROM users WHERE def");
                   if (!$this->data) {
                    $this->_error = PEAR::raiseError("User::User : record $username does not exist 0");
                    return;
                   } 
                }
            } else {
#                $this->_error = PEAR::raiseError("User::User : record $username does not exist 1");
#                return;
            }
            
            foreach ($this->data as &$item) {
                if ($item === null) {
                    $item = '';
                }
            }
            
            $this->_isNew = false;
            $this->data->timezone = 0; #(int)Config::get($db, $dbr, 'server_to_GMT') + (int)Config::get($db, $dbr, 'GMT_to_local');
#            echo $this->data->timezone.'<br>';
            $this->username = $username;
            if ($complete) $this->protocol = $this->getProtocol($db, $dbr, $this->username);
            $this->sellers = $this->getSellers($db, $dbr, $this->username);
            $this->suppliers = $this->getSuppliers($db, $dbr, $this->username);
        }
    }
    //get current user
    public static function getCurrentUser()
    {
        global $db, $dbr, $loggedUser;
        if($loggedUser){
            return $loggedUser;
        }
        $user = isset($_POST['_username']) ? $_POST['_username'] : (isset($_COOKIE["ebas_username"]) ? $_COOKIE["ebas_username"] : null);
        if(!$user){
            return null;
        }
        return new User($db, $dbr, $user, 1);
    }

    function accessLevel($page) {
        return  (int)$this->_dbr->getOne("SELECT max( LEVEL ) FROM acl JOIN acl_php ON acl.page = acl_php.acl_page
            left join users u on acl.username=u.role_id 
            WHERE u.role_id = ".$this->data->role_id."    AND acl_php.php_page = '$page'");
    }
    
    function accessLevelacl($page) {
        return  (int)$this->_dbr->getOne("SELECT LEVEL FROM acl WHERE username = '{$this->data->username}' AND page = '$page'");
    }
    
    function accessLevelsacl() {
//        echo "SELECT page, LEVEL FROM acl WHERE username = '".$this->data->username."'";
        $r = $this->_dbr->getAssoc("SELECT page, LEVEL FROM acl 
            left join users u on acl.username=u.role_id 
            WHERE u.role_id = ".$this->data->role_id." ");
        return  $r;
    }
    
    function setAccessLevel($page, $level) {
        $r = $this->_db->query("REPLACE INTO acl SET username='{$this->data->username}'
            , page='$page', level='$level'");
            if (PEAR::isError($r)) {
                aprint_r($r);
                return;
            }
    }
    
    function setSellerAccount($seller) {
        $this->_db->query("REPLACE INTO acl_sellers SET username='{$this->data->username}', seller_name='$seller'");
    }
    
    function clearSellerAccount() {
        $this->_db->query("DELETE FROM acl_sellers WHERE username='{$this->data->username}'");
    }
    
    function authenticate($passhash)
    {
        return isset($this->data->username) &&
               isset($this->data->passhash) &&
               $this->data->passhash == $passhash && (strlen($this->data->passhash)==40);
    }

    function set ($field, $value)
    {
        if (isset($this->data->$field)) {
            $this->data->$field = $value;
        }
    }

    function get ($field)
    {
        if ($field=='timezone') return (int)Config::get($this->_db, $this->_dbr, 'server_to_GMT') + (int)Config::get($this->_db, $this->_dbr, 'GMT_to_local');
        if (isset($this->data->$field)) {
            return $this->data->$field;
        } else {
            return null;
        }
    }

    function get_last_login_time()
    {
        $q = "SELECT min(t.time) FROM 
            (SELECT (time) FROM prologis_log.user_log WHERE username='{$this->data->username}' and login ORDER BY time desc LIMIT 0, 2) t";
        return  $this->_dbr->getOne($q);
    }

    function get_last_pwchange_time()
    {
        return  $this->_dbr->getOne("select max(Updated) from total_log where Table_name='users' and Field_name='passhash' and TableID={$this->data->id}");
    }

    function update ()
    {
        global $db_pass;
        global $db_host;
        global $db_name;
        global $rdb_pass;
        $query = '';
        if (!is_object($this->data)) {
            $this->_error = PEAR::raiseError('User::update : no data');
        }
        foreach ($this->data as $field => $value) {
            if ($field=='has_monitored_users') continue;
            if ($query) {
                $query .= ', ';
            }
			// set NULL for foreign key fields
            if (in_array($field, $this->_null_fields) && $value == '') {
                $value = 'NULL';
            } else {
				$value = "'" . mysql_escape_string($value) . "'";
			}
			
            $query .= "`$field`= $value";
        }
        if ($this->_isNew) {
            $un = str_replace(' ', '', $this->get('username'));
              $db = dblogin('admin',$rdb_pass,$db_host,$db_name);    
            $r = $db->query("create user '$un' IDENTIFIED BY '$db_pass'");
             if (PEAR::isError($r)) { aprint_r($r); }
            $r = $db->query("grant all on prologis2.* to '$un'@'%'");
             if (PEAR::isError($r)) { aprint_r($r); }
            $r = $db->query("grant all on prologis_log.* to '$un'@'%'");
             if (PEAR::isError($r)) { aprint_r($r); }
            $r = $this->_db->query("INSERT INTO users SET $query ");
             if (PEAR::isError($r)) { aprint_r($r); }
        } else {
            $r = $this->_db->query("UPDATE users SET $query WHERE username = '" .mysql_escape_string($this->username) . "'");
        }
        if (PEAR::isError($r)) {
			print_r($this->_null_fields);
            $this->_error = $r; print_r($r); die();
        }
        $r = $this->_db->query("update users set system_username=CONCAT(REPLACE(username,' ',''),'@localhost')");
        if (PEAR::isError($r)) {
            $this->_error = $r; print_r($r); die();
        }
        return $r;
    }

    static function listAll($db, $dbr, $deleted='0')
    {
        global $debug;
        $q = "SELECT users.*
        , role.name access_plan
        , 0  lastlogin
        , max(concat(tl.updated,'|','by ',u.name,' on ',updated,'|',u.username,'|',date(updated))) changelog
        FROM users 
        LEFT JOIN role ON role.id=users.role_id 
        left join total_log tl on tl.table_name='users' and tl.field_name='passhash' and tl.tableid=users.id
        left join users u on u.system_username=tl.username
        where IFNULL(users.deleted, 0) in ($deleted)
        group by users.id
        order by users.name";
//        if ($debug) echo $q;
        $list = $dbr->getAll($q);
        foreach($list as $k=>$r) {
            list($dummy, $list[$k]->changelog, $list[$k]->changelog_username, $list[$k]->changelog_updated) = explode('|', $r->changelog);
        }
        if (PEAR::isError($list)) {
            aprint_r($list);
        }
        return $list;
    }

    static function listCustom($db, $dbr, $deleted=0, $custom_parameters=false){
        global $debug;
        
        if ($custom_parameters){
            foreach ($custom_parameters as $column_name => $column_value){
                $sql_restr .= ' AND users.'.$column_name."='".$column_value."'";
            }
        }
        
        $q = "SELECT users.*
        , role.name access_plan
        , 0  lastlogin
        , max(concat(tl.updated,'|','by ',u.name,' on ',updated,'|',u.username,'|',date(updated))) changelog
        FROM users 
        LEFT JOIN role ON role.id=users.role_id 
        left join total_log tl on tl.table_name='users' and tl.field_name='passhash' and tl.tableid=users.id
        left join users u on u.system_username=tl.username
        where ".($deleted ? '' : 'NOT')." IFNULL(users.deleted, 0)".$sql_restr."
        group by users.id
        order by users.name";
//        if ($debug) echo $q;
        
        $list = $dbr->getAll($q);
        foreach($list as $k=>$r) {
            list($dummy, $list[$k]->changelog, $list[$k]->changelog_username, $list[$k]->changelog_updated) = explode('|', $r->changelog);
        }
        if (PEAR::isError($list)) {
            aprint_r($list);
        }
        return $list;
    }
    
    /**
     * @description get associative array
     * @param object $db
     * @param object $dbr
     * @param integer $deleted
     * @param array $custom_parameters
     * @return associative array
     */
    static function listAssoc($db, $dbr, $deleted = 0, $custom_parameters = false) 
    {
        global $debug;
        
        if ($custom_parameters){
            foreach ($custom_parameters as $column_name => $column_value){
                $sql_restr .= ' AND users.'.$column_name."='".$column_value."'";
            }
        }
        
        $q = "
            SELECT users.username, users.`name`
            FROM users 
            WHERE " . ($deleted ? '' : 'NOT') . " IFNULL(users.deleted, 0)" . $sql_restr . "
                GROUP BY users.id
                ORDER BY users.name";
        
        $list = $dbr->getAssoc($q);
        if (PEAR::isError($list)) {
            aprint_r($list);
        }
        return $list; 
    }
    
    /***/
    static function listArray($db, $dbr, $deleted=0)
    {
        $ret = array();
        $list = User::listAll($db, $dbr, $deleted);
        foreach ($list as $user) {
            $ret[$user->username] = $user->name;
        }
        return $ret;
    }
    
    static function getProtocol($db, $dbr, $username='', $ip='', $from=0, $to=9999999) {
        $r = $db->query("SELECT SQL_CALC_FOUND_ROWS user_log.* 
/*            , (SELECT c.country 
                FROM 
                ip2nationCountries c,
                ip2nation i 
            WHERE 
                i.ip < INET_ATON(user_log.ip) 
                AND c.code = i.country ORDER BY i.ip DESC LIMIT 1) host*/
            from prologis_log.user_log 
            where username='".$username."' OR '".$username."'='' 
            ".(strlen($ip)?" and ip='".mysql_escape_string($ip)."'":'')."
            ORDER BY time desc LIMIT $from, $to");
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        $list = array();
        require_once("class-ipgeo.php" );
        while ($article = $r->fetchRow()) {
            if (strlen($article->ip)) {
#                $ipList = new IPGeo($article->ip);
#                $article->host = $ipList->ip($article->ip);
#                $ips = explode('.', $article->ip);
#                $ip_number = $ips[0]*256*256*256+$ips[1]*256*256+$ips[2]*256+$ips[3];
                if ((int)$article->locId) $article->loc = $dbr->getRow("select * from IPLocs where locId=".(int)$article->locId);
            }
            $list[] = $article;
        }
        return $list;
    }

    static function getSellers($db, $dbr, $username='') {
        $r = $db->query("SELECT si.username AS seller_name, acls.username
            FROM (select username from seller_information si
                UNION ALL select 'All_sellers'
                ) si
            LEFT JOIN acl_sellers acls ON si.username = acls.seller_name
            AND (acls.username='".$username."' OR '".$username."'='') order by si.username");
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    static function getMethods($db, $dbr, $username='') {
        $r = $db->query("SELECT sm.company_name, acl.username, sm.shipping_method_id
            FROM (select CONCAT(country, ': ', company_name) company_name, shipping_method_id from shipping_method where not deleted
                UNION ALL select ' All methods', 0
                ) sm
            LEFT JOIN acl_method acl ON sm.shipping_method_id = acl.shipping_method_id
            AND (acl.username='".$username."' OR '".$username."'='') order by sm.company_name");
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    function setMethod($shipping_method_id) {
        $this->_db->query("REPLACE INTO acl_method SET username='{$this->data->username}', shipping_method_id='$shipping_method_id'");
    }
    
    static function getWarehouses($db, $dbr, $username='') {
        $r = $dbr->getAll("SELECT distinct sm.name, acl.username, sm.warehouse_id
            FROM (select name, warehouse_id from warehouse where not inactive
                UNION ALL select ' All warehouses', 0
                ) sm
            LEFT JOIN acl_warehouse acl ON sm.warehouse_id = acl.warehouse_id
            AND (acl.username='".$username."' OR '".$username."'='') order by sm.name");
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        return $r;
    }

    function setWarehouse($warehouse_id) {
        $this->_db->query("REPLACE INTO acl_warehouse SET username='{$this->data->username}', warehouse_id=$warehouse_id");
    }
    
    static function getWarehouses2ship($db, $dbr, $username='') {
        $r = $dbr->getAll("SELECT distinct sm.name, acl.username, sm.warehouse_id
            FROM (select name, warehouse_id from warehouse where not inactive
                UNION ALL select ' All warehouses', 0
                ) sm
            LEFT JOIN acl_warehouse2ship acl ON sm.warehouse_id = acl.warehouse_id
            AND (acl.username='".$username."' OR '".$username."'='') order by sm.name");
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        return $r;
    }

    function setWarehouse2ship($warehouse_id) {
        $this->_db->query("REPLACE INTO acl_warehouse4article SET username='{$this->data->username}', warehouse_id=$warehouse_id");
    }

    static function getWarehouses4article($db, $dbr, $username='') {
        $r = $dbr->getAll("SELECT distinct sm.name, acl.username, sm.warehouse_id
            FROM (select name, warehouse_id from warehouse where not inactive
                UNION ALL select ' All warehouses', 0
                ) sm
            LEFT JOIN acl_warehouse4article acl ON sm.warehouse_id = acl.warehouse_id
            AND (acl.username='".$username."' OR '".$username."'='') order by sm.name");
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        return $r;
    }

    function setWarehouse4article($warehouse_id) {
        $this->_db->query("REPLACE INTO acl_warehouse4article SET username='{$this->data->username}', warehouse_id=$warehouse_id");
    }
    
    static function getSupervisors($db, $dbr, $username='') {
        $r = $db->query("SELECT u.name sv_name, us.username, u.username sv_username 
            FROM users u
            LEFT JOIN user_supervisor us ON u.username = us.sv_username
            AND (us.username='".$username."') where NOT IFNULL(deleted, 0) order by u.name");
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    function setSupervisor($sv_username) {
        $this->_db->query("REPLACE INTO user_supervisor SET username='{$this->data->username}', sv_username='$sv_username'");
    }
    
/*    function getSellers($db, $dbr, $username='') {
        $r = $db->query("SELECT u.name sv_name, us.username, u.username sv_username 
            FROM users u
            LEFT JOIN user_supervisor us ON u.username = us.sv_username
            AND (us.username='".$username."' OR '".$username."'='') order by u.name");
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }*/

    function setSellers($sellername) {
        $this->_db->query("REPLACE INTO user_seller SET username='{$this->data->username}', sellername='$sellername'");
    }
    
    static function getRMA_problems($db, $dbr, $username='') {
        $r = $db->query("SELECT rp.name, up.username, rp.problem_id rma_problem_id
            FROM rma_problem rp
            LEFT JOIN user_problem up ON rp.problem_id = up.rma_problem_id
            AND (up.username='".$username."') order by rp.name");
/*            echo "SELECT rp.name, up.username, rp.problem_id rma_problem_id
            FROM rma_problem rp
            LEFT JOIN user_problem up ON rp.problem_id = up.rma_problem_id
            AND (up.username='".$username."' OR '".$username."'='') order by rp.name";*/
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    function setRMA_problem($rma_problem_id) {
        $this->_db->query("REPLACE INTO user_problem SET username='{$this->data->username}', rma_problem_id=$rma_problem_id");
    }
    
    static function getRMA_solutions($db, $dbr, $username='') {
        $r = $db->query("SELECT rp.name, up.username, rp.solution_id rma_solution_id
            FROM rma_solution rp
            LEFT JOIN user_solution up ON rp.solution_id = up.rma_solution_id
            AND (up.username='".$username."') 
            where rp.alt=0
            order by rp.name");
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    function setRMA_solution($rma_solution_id) {
        $this->_db->query("REPLACE INTO user_solution SET username='{$this->data->username}', rma_solution_id=$rma_solution_id");
    }
    
    function clearMethods() {
        $this->_db->query("DELETE FROM acl_method WHERE username='{$this->data->username}'");
    }

    function clearWarehouses() {
        $this->_db->query("DELETE FROM acl_warehouse WHERE not inactive and username='{$this->data->username}'");
    }

    function clearWarehouses2ship() {
        $this->_db->query("DELETE FROM acl_warehouse2ship WHERE not inactive and username='{$this->data->username}'");
    }

    function clearRMA_problems() {
        $this->_db->query("DELETE FROM user_problem WHERE username='{$this->data->username}'");
    }

    function clearRMA_solutions() {
        $this->_db->query("DELETE FROM user_solution WHERE username='{$this->data->username}'");
    }

    function clearSupervisors() {
        $this->_db->query("DELETE FROM user_supervisor WHERE username='{$this->data->username}'");
    }

    static function singleton($db, $dbr, $username)
    {
        if (!isset($GLOBALS['USER_SINGLETON'][$username])) {
            $GLOBALS['USER_SINGLETON'][$username] = new User($db, $dbr, $username);
        }
        return $GLOBALS['USER_SINGLETON'][$username];
    }

    static function getRSSFeed($db, $dbr, $username)
    {
        return array();
        require_once 'XML/Serializer.php';
        $feedXML = '';
        $user = new User($db, $dbr, $username);
        $file = fopen ($user->get('rss'), "r");
        if ($file) {
            while (!feof ($file)) {
                $feedXML .= fgets ($file, 1024);
            }
            fclose($file);
        }    
        $opts = array(
                         'indent'             => '  ',
                         'linebreak'          => "\n",
                         'typeHints'          => false,
                         'addDecl'            => true,
                         'scalarAsAttributes' => false,
                         'encoding' => 'utf-8',
                        'attributesArray' => '__attrs__',
                         'rootName'           => 'channel',
                         'mode' => 'simplexml',
//                         'rootAttributes'     => array( 'xmlns' => 'urn:ebay:apis:eBLBaseComponents' )
                    );
        $feed_us = new XML_Unserializer( $opts );
        $r = $feed_us->unserialize( $feedXML );
        if (!PEAR::isError($r)) {
            $result = $feed_us->getUnserializedData();
        }
        if (isset($result['channel']['item']['title'])) 
            $feed = array($result['channel']['item']);
        else $feed = $result['channel']['item'];
        return $feed;
    }

    function setSupplier($company_id) {
        $this->_db->query("REPLACE INTO acl_suppliers SET username='{$this->data->username}', company_id=$company_id");
    }
    
    function clearSupplier() {
        $this->_db->query("DELETE FROM acl_suppliers WHERE username='{$this->data->username}'");
    }
    
    static function getSuppliers($db, $dbr, $username='') {
        $r = $db->query("SELECT opc.name, opc.id, opc.type, acls.company_id
            FROM (select name, id, type from op_company 
                UNION ALL select ' All suppliers', -1, NULL
                UNION ALL select ' All shipping companies', -2, 'shipping'
                ) opc
            LEFT JOIN acl_suppliers acls ON opc.id = acls.company_id
            AND (acls.username='".$username."' OR '".$username."'='') order by opc.name");
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    static function getETACountries($db, $dbr, $username='') {
        $q = "select distinct w.country_code, u.username, c.name country_name, u.days
            from warehouse w
            LEFT JOIN country c ON w.country_code = c.code
            LEFT JOIN users_eta_country u ON u.country_code = c.code
                and u.username='$username'
            where w.country_code<>''
            and not w.inactive
        ";
        $list = $dbr->getAll($q);
        if (PEAR::isError($list)) {
            aprint_r($list);
            return;
        }
        return $list;
    }

    function setETACountry($country_code, $days=0) {
        $this->_db->query("REPLACE INTO users_eta_country SET username='{$this->data->username}', country_code='$country_code', days = $days");
    }
    
    function clearETACountries() {
        $this->_db->query("DELETE FROM users_eta_country WHERE username='{$this->data->username}'");
    }

    static function getbrokenSellers($db, $dbr, $username='') {
        $q = "select distinct u.username, si.username seller_username
            from seller_information si
            LEFT JOIN users_broken_seller u ON u.seller_username = si.username
                and u.username='$username'
            where 1 and si.isActive
        ";
        $list = $dbr->getAll($q);
        if (PEAR::isError($list)) {
            aprint_r($list);
            return;
        }
        return $list;
    }

    function setbrokenSellers($seller_username) {
        $this->_db->query("REPLACE INTO users_broken_seller 
            SET username='{$this->data->username}', seller_username='$seller_username'");
    }
    
    function clearbrokenSellers() {
        $this->_db->query("DELETE FROM users_broken_seller WHERE username='{$this->data->username}'");
    }

    static function getwareMinstock($username='') {
        $db = \label\DB::getInstance(\label\DB::USAGE_READ);
        $q = "select distinct u.username, w.warehouse_id, CONCAT(w.country_code,': ',w.name) warehouse_name
            from warehouse w
            LEFT JOIN user_ware_minstock u ON u.warehouse_id = w.warehouse_id
                and u.username='$username'
            where 1 and w.inactive=0
            order by warehouse_name
        ";
        $list = $db->getAll($q);
        if (PEAR::isError($list)) {
            aprint_r($list);
            return;
        }
        return $list;
    }

    function setwareMinstock($warehouse_id) {
        $this->_db->query("REPLACE INTO user_ware_minstock 
            SET username='{$this->data->username}', warehouse_id='$warehouse_id'");
    }
    
    function clearwareMinstock() {
        $this->_db->query("DELETE FROM user_ware_minstock WHERE username='{$this->data->username}'");
    }
    
    /**
     * List all countries + username if for user set option "PP change alert"
     * @param string $username
     * @return array
     */
    static function getPPAlert($username='') {
        $db = \label\DB::getInstance(\label\DB::USAGE_READ);
        $q = "select distinct u.username, c.value `country_id`, `c`.`description` country_name
            from config_api_values c
            LEFT JOIN user_pp_change_alert u ON u.country = c.value
                and u.username='$username'
            where 1 and c.par_id=5 and not c.inactive
            order by value
        ";
        $list = $db->getAll($q);
        if (PEAR::isError($list)) {
            aprint_r($list);
            return;
        }
        return $list;
    }

    /**
     * Add country in table `user_ware_no_barcode_alert`
     * @param string $country_id
     */
    function setPPAlert($country_id) {
        $this->_db->query("REPLACE INTO user_pp_change_alert 
            SET username='{$this->data->username}', country='$country_id'");
    }
    
    /**
     * Cleare table `user_pp_change_alert` for user
     */
    function clearPPAlert() {
        $this->_db->query("DELETE FROM user_pp_change_alert WHERE username='{$this->data->username}'");
    }
    
    /**
     * List all not 'C' warehouses + username if for user set option "no_barcode_alert"
     * @param string $username
     * @return array
     */
    static function getwareNoBarcodeAlert($username='') {
        $db = \label\DB::getInstance(\label\DB::USAGE_READ);
        $q = "select distinct u.username, w.warehouse_id, CONCAT(w.country_code,': ',w.name) warehouse_name
            from warehouse w
            LEFT JOIN user_ware_no_barcode_alert u ON u.warehouse_id = w.warehouse_id
                and u.username='$username'
            where 1 and w.inactive=0 and w.barcode_type != 'C'
            order by warehouse_name
        ";
        $list = $db->getAll($q);
        if (PEAR::isError($list)) {
            aprint_r($list);
            return;
        }
        return $list;
    }

    /**
     * Add warehouse in table `user_ware_no_barcode_alert`
     * @param int $warehouse_id
     */
    function setwareNoBarcodeAlert($warehouse_id) {
        $this->_db->query("REPLACE INTO user_ware_no_barcode_alert 
            SET username='{$this->data->username}', warehouse_id='$warehouse_id'");
    }
    
    /**
     * Cleare table `user_ware_no_barcode_alert` for user
     */
    function clearwareNoBarcodeAlert() {
        $this->_db->query("DELETE FROM user_ware_no_barcode_alert WHERE username='{$this->data->username}'");
    }
    
    function getMonitoredUsers() 
    {
        $q = "SELECT `employee`.`id`
            FROM `emp_user_monitor`
            LEFT JOIN `employee` ON `employee`.`id` = `emp_user_monitor`.`emp_id`
            WHERE `emp_user_monitor`.`username` = '{$this->data->username}'";
        return $this->_db->getCol($q);
    }
    /**
     * Put message about login/logout to supervisors monitoring file
     *
     * @param bool $login
     * @param int $company_id
     */
    function notifySupervisors($login = true, $company_id)
    {
        $supervisor_ids = $this->_db->getCol("SELECT `supervisor`.`id`
            FROM `emp_user_monitor`
            LEFT JOIN `employee` `me` ON `me`.`id` = `emp_user_monitor`.`emp_id`
            LEFT JOIN `users` `supervisor` ON `supervisor`.`username` = `emp_user_monitor`.`username`
            WHERE `me`.`username` = '{$this->data->username}'");
        
        $company = $this->_db->getOne("SELECT `address`.`company` FROM `address`
            LEFT JOIN `address_obj` ON `address_obj`.`address_id` = `address`.`id` AND `address_obj`.`obj` = 'company'
            LEFT JOIN `company` ON `company`.`id` = `address_obj`.`obj_id`
            WHERE `company`.`id` = ".(int)$company_id);
            
        $employee = $this->getEmployeeInfo();

        foreach($supervisor_ids as $supervisor_id)
        {
            $filepath = $_SERVER['DOCUMENT_ROOT'] . 'monitor' . DIRECTORY_SEPARATOR . $supervisor_id;
            $timediff = $this->get('timezone');
            $time = ServerTolocal(date("Y-m-d H:i:s"), $timediff);
            $message = $employee->name . ' ' . $employee->name2 . ($login ? ' Logged in to ' : ' Logged out from ') . $company . ' on ' . $time;
            $handle = fopen($filepath, "a");
            if ($handle !== false)
            {
                fwrite($handle, $message . "\n");
                fclose($handle);
            }
        }
    }

    /**
     * Check if two-factor authorization enabled for current user
     * @return bool
     */
    public function isEnabled2FA()
    {
        
        
        $result = $this->_dbr->getOne('
            SELECT `sms_login`
            FROM `role`
            WHERE `id` = ?
        ', null, [$this->data->role_id]);
        return (bool)$result;
    }

    /**
     * Check if token exists for current user, and if it was created in the recent past
     * @param $token
     * @return bool
     */
    public function checkToken($token)
    {
        $token = strtoupper(trim($token));
        if (empty($token)) {
            return false;
        }
        $token_validity = (int)Config::get($this->_db, $this->_dbr, 'aTokenValidity');
        $tokenLifeTime = ( $token_validity ? $token_validity : 60 )*60;
        $result = $this->_dbr->getRow(
            '
                SELECT `token_code`, `token_datetime`
                FROM `users`
                WHERE `id` = ?
            ',
            null,
            [$this->data->id]
        );
        return (bool)
            ($result->token_code === $token)
            && (time() - strtotime($result->token_datetime) < $tokenLifeTime);
    }

    /**
     * Generated new auth token
     * @param int $length Number digits of token
     * @return string
     */
    public function generateToken($length = 6)
    {
        $allowable_characters = "1234567890";
        $ps_len = strlen($allowable_characters);
        mt_srand((double)microtime()*1000000);
        $token = "";
        for($i = 0; $i < $length; $i++) {
            $token .= $allowable_characters[mt_rand(0,$ps_len-1)];
        }
        return $token;
    }
    /**
     * Create token for this user and send it to his phone.
     * If phone doesn't set or employee assotiated with user not found - returns false. Otherwise true.
     * @param bool $emailAlso should send via email also (in additional to sms)
     * @return bool
     */
    public function generateAndSendToken($emailAlso = false)
    {
        $token = $this->generateToken();
        $this->_db->execParam(
            '
                UPDATE `users`
                SET
                    `token_code` = ?,
                    `token_datetime` = ?,
                    `token_attempt` = 0
                WHERE `id` = ?
            ',
            [
                $token,
                date('Y-m-d H:i:s'),
                $this->data->id,
            ]
        );

        $employee = $this->getEmployeeInfo();
        if (!empty($employee->tel_mob1)) {
            $numberToSendSms = $employee->tel_mob1;
        } elseif (!empty($employee->tel_mob2)) {
            $numberToSendSms = $employee->tel_mob2;
        }

        $message = "Your token is: $token";

        $auctionCover = new stdClass();
        $auctionCover->smsNumber = $numberToSendSms;
        $auctionCover->shipping_order_datetime_sms_to = 'smsNumber';
        $auctionCover->message = $message;
        $auctionCover->auction_number = 0;
        $auctionCover->txnid = 0;
        
        if ($emailAlso) {
            $auctionCover->email = $employee->email;
        }
        
        //check by what method to send
        switch(\Config::get(0, 0, 'aTokenUser')){
            case 'skype':
                if ($emailAlso) {
                    $auctionCover->smsNumber = false;
                    standardEmail($this->_db, $this->_dbr, $auctionCover, 'oneoff_token');
                }
                
                $skype_login = $employee->skype_login ? $employee->skype_login : $this->data->skype_id;
                send_skype_message($skype_login, $message);
                break;
            case 'sms':
                standardEmail($this->_db, $this->_dbr, $auctionCover, 'oneoff_token');
                break;
        }
        return true;
    }

    /**
     * Increment token attempt for this user, also check if attempt remained.
     * @return bool is attempt remained
     */
    public function processAndCheckTokenAttempt()
    {
        $this->_db->execParam(
            '
                UPDATE `users`
                SET
                    `token_attempt` = `token_attempt` + 1
                WHERE `id` = ?
            ',
            [
                $this->data->id,
            ]
        );

        $attempt = $this->_dbr->getOne(
            '
                SELECT `token_attempt`
                FROM `users`
                WHERE `id` = ?
            ',
            null,
            [
                $this->data->id,
            ]
        );

        if (self::TOKEN_LOGIN_ATTEMPTS <= $attempt) {
            $this->deleteToken();
            return false;
        }

        return true;
    }

    /**
     * Remove one-off user token
     */
    public function deleteToken()
    {
        $this->_db->execParam(
            '
                UPDATE `users`
                SET
                    `token_code` = NULL,
                    `token_datetime` = NULL,
                    `token_attempt` = 0
                WHERE `id` = ?
            ',
            [$this->data->id]
        );
    }

    /**
     * Return assotiated employee info with user
     * @return mixed
     */
    private function getEmployeeInfo()
    {
        return $this->_dbr->getRow(
            '
                SELECT *
                FROM `employee`
                WHERE `username` = ?
            ',
            null,
            [$this->data->username]
        );
    }

    /**
     * get user by id
     * @param $id Id user
     * @return null|User
     */
    public static function getById($id)
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $user = $dbr->getOne('select username,id from users where id='.( (int) $id) );
        if (PEAR::isError($user)) { print_r($user); die();}
        if(!$user){
            return null;
        }
        return new self($db, $dbr, $user);
    }
}
