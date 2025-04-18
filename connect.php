<?php
header("Content-Type: text/html; charset=utf-8");

$__debug_time = microtime(true);

error_reporting(E_ALL);

/**
 * Some xdebug settings
 */
ini_set('xdebug.var_display_max_children', 512);
ini_set('xdebug.var_display_max_data', 1024);
ini_set('xdebug.var_display_max_depth', 5);

error_reporting(E_ALL^E_NOTICE^E_WARNING^E_DEPRECATED^E_STRICT);

#use \label\DebugToolbar\DebugToolbar;

if (stripos($_SERVER['HTTP_HOST'], 'prologistics.info') !== false)
{
    session_start();
}

/**
 * Fast fix scanning of SQL injections
 */
if (isset($_COOKIE['ebas_passhash'])) {
    $needle = $_COOKIE['ebas_passhash'];
    if (preg_match('/^[a-z0-9]+$/', $needle) === 0) {
        throw new Exception('You have wrong password. Please contact administrator.');
    }
}
if (isset($_POST['cookie']['ebas_passhash'])) {
    $needle = $_POST['cookie']['ebas_passhash'];
    if (preg_match('/^[a-z0-9]+$/', $needle) === 0) {
        throw new Exception('You have wrong password. Please contact administrator.');
    }
}
if (isset($_COOKIE['ebas_username'])) {
    $needle = $_COOKIE['ebas_username'];
    if (
        (strpos($needle, '\'') !== false)
        || (strpos($needle, '"') !== false)
    )  {
        throw new Exception('You have wrong username. Please contact administrator.');
    }
}
if (isset($_POST['cookie']['ebas_username'])) {
    $needle = $_POST['cookie']['ebas_username'];
    if (
        (strpos($needle, '\'') !== false)
        || (strpos($needle, '"') !== false)
    )  {
        throw new Exception('You have wrong username. Please contact administrator.');
    }
}
if (isset($_POST['_username'])) {
    $needle = $_POST['_username'];
    if (
        (strpos($needle, '\'') !== false)
        || (strpos($needle, '"') !== false)
    )  {
        throw new Exception('You have wrong username. Please contact administrator.');
    }
}

$mem = exec("ps aux|grep " . getmypid() . "|grep -v grep|awk {'print $6'}");
$queryString = isset($_SERVER['QUERY_STRING']) ? (string)$_SERVER['QUERY_STRING'] : '';
$requestUri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
file_put_contents(__DIR__ . "/tmp/mem.log", getmypid() . ', ' . (string)$_SERVER['SCRIPT_NAME'] . "?" . $queryString . ', ' . $requestUri . ', ' . $mem . '
', FILE_APPEND);

error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
ini_set('safe_mode', 'off');
ini_set('max_file_uploads', '200');
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');

if (file_exists('tmp/' . $_SERVER['HTTP_HOST'] . '.block_message')) {
    die(file_get_contents('tmp/' . $_SERVER['HTTP_HOST'] . '.block_message'));
}
if (file_exists('tmp/block_message')) {
    die(file_get_contents('tmp/block_message'));
}

if (file_exists('tmp/block')) {
//		die('The database is blocked now');
    die("The access to the database is currently blocked, please try again later.<br>
Der Zugriff auf die Datenbank ist derzeit blockiert, bitte versuchen Sie es spater erneut.<br>
L'acces a la base de donnees est actuellement bloque, s'il vous plait essayer a nouveau plus tard.<br>
El acceso a la base de datos esta actualmente bloqueada, por favor, intentelo de nuevo mas tarde.");
}

require_once dirname(__FILE__) . '/patch.php';
require_once 'MDB2.php';
require_once 'Smarty.class.php';
require_once(__DIR__.'/App/bootstrap.php');
//  require_once 'DB/mysql.php';
//$db=DB::connect("mysql://widmer_ebay:dbpass1976a@unix+%2fvar%2flib%2fmysql%2fmysql.sock/widmero_ebay");

if (is_file(__DIR__ . '/connect_local.php')) {
    require_once __DIR__ . '/connect_local.php';
}

$devHosts = [
    'dev' => [
        'prolodev.prologistics.info',
        'dev.beliani.net',
        'devcache.beliani.net',
        'www.dev.beliani.net',
        'www.devcache.beliani.net',
        'beliani.net',
        'www.beliani.net',
    ],
    'heap' => [
        'proloheap.prologistics.info',
        'heap.beliani.net',
        'heapcache.beliani.net',
        'www.heap.beliani.net',
        'www.heapcache.beliani.net',
    ],
    'issue' => [
        'proloissue.prologistics.info',
        'issue.beliani.net',
        'issuecache.beliani.net',
        'www.issue.beliani.net',
        'www.issuecache.beliani.net',
    ],
];

if (getenv('APPLICATION_ENV') !== false) {
    define('APPLICATION_ENV', getenv('APPLICATION_ENV'));
} elseif (
    (in_array($_SERVER['HTTP_HOST'], $devHosts['dev']))
    || (//used for php cli
        ($_SERVER['HOSTNAME'] === 'dev2.prologistics.net')
        && (basename(__DIR__) === 'public_html_dev')
    )
) {
    define('APPLICATION_ENV', 'develop');
} elseif (
    (in_array($_SERVER['HTTP_HOST'], $devHosts['issue']))
    || (//used for php cli
        ($_SERVER['HOSTNAME'] === 'dev2.prologistics.net')
        && (basename(__DIR__) === 'public_html_issue')
    )
) {
    define('APPLICATION_ENV', 'issue');
} elseif (
    (in_array($_SERVER['HTTP_HOST'], $devHosts['heap']))
    || (//used for php cli
        ($_SERVER['HOSTNAME'] === 'dev2.prologistics.net')
        && (basename(__DIR__) === 'public_html_heap')
    )
) {
    define('APPLICATION_ENV', 'heap');
} else {
    define('APPLICATION_ENV', 'production');
}

if (php_sapi_name() == "cli") {
    if (stripos($_SERVER['PWD'], 'public_html_dev') !== false) {
        define('APPLICATION_CLI_ENV', 'cli_develop');
    } else if (stripos($_SERVER['PWD'], 'public_html_heap') !== false) {
        define('APPLICATION_CLI_ENV', 'cli_heap');
    } else {
        define('APPLICATION_CLI_ENV', 'cli_production');
    }
}

if (
    (APPLICATION_ENV === 'docker')
    || (APPLICATION_ENV === 'local')
    || (APPLICATION_ENV === 'develop')
    || (APPLICATION_ENV === 'heap')
    || (APPLICATION_ENV === 'issue')
    || (defined('APPLICATION_CLI_ENV') && (APPLICATION_CLI_ENV === 'cli_develop' || APPLICATION_CLI_ENV === 'cli_heap'))
) {
    if (in_array($_SERVER['HTTP_HOST'], $devHosts['dev'])) {
        if (is_file(__DIR__ . '/HEAPMERGEDINDICATOR')) {
            throw new Exception('SOMEBODY MERGED HEAP INTO THIS BRANCH!');
        }
    }

    if (APPLICATION_ENV === 'docker') {
        $read_db_host = '178.63.27.78:3306';
    } 
    else if (APPLICATION_ENV === 'local') {
        $read_db_host = '127.0.0.1:3307';
    } 
    else {
        $read_db_host = '127.0.0.1:3306'; //'78.46.89.181:3306';
    }
    
    $read_db_user = 'customer';
    $read_db_pass = 'Dr3enxGu^pg8.Qfrn4QW';
    $read_db_name = 'prologis2';
    if (APPLICATION_ENV === 'docker') {
        $db_host = '178.63.27.78:3306';
    } 
    else if (APPLICATION_ENV === 'local') {
        $db_host = '127.0.0.1:3307';
    } 
    else {
        $db_host = '127.0.0.1:3306';
    }

    /**
     * @deprecated please use const instead (create new one if not created yet)
     */
    $db_host_no_port = '127.0.0.1';
    $db_user = 'customer';
    $db_pass = 'Dr3enxGu^pg8.Qfrn4QW';
    $rdb_pass = 'Zif7mplr.Df4j';
    $db_name = 'prologis2';
    $db_name_log = 'prologis_log';
    
    if (APPLICATION_ENV === 'docker') {
        define('DB_PRODUCTION_READ_HOST', '127.0.0.1:3307');
        define('DB_PRODUCTION_READ_USER', 'readonly');
        define('DB_PRODUCTION_READ_PASSWORD', 'Jf83F^.p;Qrjf5kUi5p');
        define('DB_PRODUCTION_READ_NAME', 'prologis2');
    } else {
        define('DB_PRODUCTION_READ_HOST', '178.63.19.201');
        define('DB_PRODUCTION_READ_USER', 'readonly');
        define('DB_PRODUCTION_READ_PASSWORD', 'Jf83F^.p;Qrjf5kUi5p');
        define('DB_PRODUCTION_READ_NAME', 'prologis2');
    }
    
    define('FILES_PATH', '/home/dev/DATA/REPO_FILES/');

    $env = 'development';
    if (APPLICATION_ENV === 'docker') {
        define('REDIS_HOST', getenv('REDIS_PORT_6379_TCP_ADDR'));
        define('SPHINX_ENABLED', (bool)getenv('SPHINX_ENABLED'));
        define('SPHINX_HOST', getenv('SPHINX_HOST'));
        define('SPHINX_PORT_SQL', getenv('SPHINX_PORT_SQL'));
        define('DEBUG_TOOLBAR_ENABLED', getenv('DEBUG_TOOLBAR_ENABLED'));
    } else {
        define('REDIS_HOST', $db_host_no_port);
        define('SPHINX_ENABLED', true);
        define('SPHINX_HOST', '127.0.0.1');
        define('SPHINX_PORT_SQL', 9306);
        define('DEBUG_TOOLBAR_ENABLED', 1);
    }
    if (APPLICATION_ENV === 'issue') {
	    $read_db_name = 'prologis_issues';
	    $db_name = 'prologis_issues';
	}
} else {
    $read_db_host = '127.0.0.1:3306';
    $read_db_user = 'JustynaJ';
    $read_db_pass = '0-;-^56+0:+.cX|.0w;~^g%^93=.g+!~.:V';
    $read_db_name = 'test12';
    $db_host = '127.0.0.1:3306';
    $db_host_no_port = '127.0.0.1:3306';
    $db_user = 'JustynaJ';
    $db_pass = '0-;-^56+0:+.cX|.0w;~^g%^93=.g+!~.:V';
    $rdb_pass = 'E8sYKYQqJ';
    $db_name = 'test12';
    $db_name_log = 'test12';
    $env = 'production';
        define('REDIS_HOST', $db_host_no_port);
        define('SPHINX_ENABLED', true);
        define('SPHINX_HOST', '127.0.0.1');
        define('SPHINX_PORT_SQL', 9306);
        define('DEBUG_TOOLBAR_ENABLED', 1);

    define('FILES_PATH', '/DISK/DATA/REPO_FILES/');
}

define('SALT_EMAIL_ONLINE', '}WRNybH>Qt6rp<RYgcebQf{AxBfLd0IO');

define('DB_WRITE_USER', $db_user);
define('DB_WRITE_PASSWORD', $db_pass);
define('DB_NAME', $db_name);
define('DB_HOST', $db_host_no_port);

$db = dblogin($db_user, $db_pass, $db_host, $db_name);
$dbr = dblogin($read_db_user, $read_db_pass, $db_host, $read_db_name);
if (is_a($db, 'MDB2_Driver_mysql'))
{
    \label\DB::setInstance(\label\DB::USAGE_WRITE, $db);
}

if (filter_input(INPUT_GET, 'unload', FILTER_SANITIZE_NUMBER_INT)) {
    $uuid = filter_input(INPUT_GET, 'uuid', FILTER_SANITIZE_STRING);
    $pid = filter_input(INPUT_GET, 'pid', FILTER_SANITIZE_STRING);
    
    require_once 'lib/Limiter.php';
    
    $redis = false;
    if (class_exists('Redis')) {
        $redis = \label\RedisProvider::getInstance(\label\RedisProvider::USAGE_CACHE);
    }

    if ($redis) {
        $limiter = new Limiter(null, null, $redis);
        ignore_user_abort(true);
        $limiter->unload($uuid, $pid);
    }
    exit;
}

/**
 * @todo add this connection to \label\DB
 */
if (
    (APPLICATION_ENV === 'docker')
    || (APPLICATION_ENV === 'local')
    || (APPLICATION_ENV === 'develop')
    || (APPLICATION_ENV === 'heap')
    || (defined('APPLICATION_CLI_ENV') && (APPLICATION_CLI_ENV === 'cli_develop' || APPLICATION_CLI_ENV === 'cli_heap'))
) {
    $def_slave = $db_host;
} else {
    $def_slave = $dbr->getOne("select concat(ip,':',port) from slave where def");
}
$dbr_spec = dblogin($read_db_user, $read_db_pass, $def_slave, $read_db_name);

if (is_a($db, 'MDB2_Driver_mysql'))
{
    $page_slave = $dbr->getAssoc("select page, concat(ip,':',port) from page_slave join slave on slave.id=page_slave.slave_id where slave.inactive=0");
}
$SCRIPT_FILENAME = basename($_SERVER['SCRIPT_FILENAME']);

$QUERY_STRING1 = '';
if ($_SERVER['QUERY_STRING']) {
    list($QUERY_STRING1) = explode('&', $_SERVER['QUERY_STRING']);
}
parse_str(file_get_contents('php://input'), $payload);
if (isset($payload['fn'])) {
    $QUERY_STRING1 = 'fn=' . $payload['fn'];
}
//  print_r($_REQUEST); print_r($_COOKIE);
if (isset($page_slave[$SCRIPT_FILENAME . '?' . $QUERY_STRING1])) {
    if (APPLICATION_ENV === 'docker') {
        if (
            ($page_slave[$SCRIPT_FILENAME . '?' . $QUERY_STRING1] === '127.0.0.1:3306')
            || ($page_slave[$SCRIPT_FILENAME . '?' . $QUERY_STRING1] === '127.0.0.1:3307')
            || ($page_slave[$SCRIPT_FILENAME . '?' . $QUERY_STRING1] === '127.0.0.1:3308')
        ) {
            $page_slave[$SCRIPT_FILENAME . '?' . $QUERY_STRING1] = '178.63.27.78:3306';
        } else {
            throw new Exception('Unexpected slave server. Please check out configs.');
        }
    }
    else if (APPLICATION_ENV === 'local') {
        if ($page_slave[$SCRIPT_FILENAME] === '127.0.0.1:3306') {
            $page_slave[$SCRIPT_FILENAME] = '127.0.0.1:3307';
        }
    }
    $dbr = dblogin($read_db_user, $read_db_pass, $page_slave[$SCRIPT_FILENAME . '?' . $QUERY_STRING1], $read_db_name, 32);
    \label\DB::setInstance(\label\DB::USAGE_READ, $dbr);
} elseif (isset($page_slave[$SCRIPT_FILENAME])) {
    if (APPLICATION_ENV === 'docker') {
        if (
            ($page_slave[$SCRIPT_FILENAME] === '127.0.0.1:3306')
            || ($page_slave[$SCRIPT_FILENAME] === '127.0.0.1:3307')
            || ($page_slave[$SCRIPT_FILENAME] === '127.0.0.1:3308')
        ) {
            $page_slave[$SCRIPT_FILENAME] = '178.63.27.78:3306';
        } else {
            throw new Exception('Unexpected slave server. Please check out configs.');
        }
    }
    $dbr = dblogin($read_db_user, $read_db_pass, $page_slave[$SCRIPT_FILENAME], $read_db_name, 32);
} else {
    $dbr = dblogin($read_db_user, $read_db_pass, $def_slave, $read_db_name, 32);
}

if (is_a($dbr, 'MDB2_Driver_mysql'))
{
    \label\DB::setInstance(\label\DB::USAGE_READ, $dbr);
}

//  $db_log = dblogin($db_user_log,$db_pass_log,$db_host_log,$db_name_log);
$smarty = new Smarty;
$smarty->use_sub_dirs = false;

$opts = array(
    'socket' => array(
        'bindto' => $_SERVER['SERVER_ADDR'] . ':0',
    )
);
global $interface_context;

$interface_context = stream_context_create($opts);

function redblogin_justincase()
{
    static $db;
    if (!$db) {
        $db = DB::connect(DSN_RE_DB);
        $db->fetchmode = 3;
        $db->query('set names utf8');
    }
    return $db;
}

function dblogin($db_user, $db_pass, $db_host, $db_name, $client_flags = '')
{
    $options = [
        'portability' => MDB2_PORTABILITY_NONE,
    ];
    $dsn = array('phptype' => 'mysql',
        'hostspec' => $db_host,
        'username' => $db_user,
        'password' => $db_pass,
        'database' => $db_name
    );
/*    if (
        DebugToolbar::isEnabled(DebugToolbar::TRACE_MYSQL)
        && ($dsn['phptype'] === 'mysql')
    ) {
        $dsn['phptype'] = 'traceablemysql';
    }*/
    if ($client_flags) $dsn['client_flags'] = $client_flags;
    $dsn_str = 'mysql://' . $db_user . ':' . urlencode($db_pass) . '@' . $db_host . '/' . $db_name;
    $_db = MDB2::connect($dsn, $options);
    if (!is_a($_db, 'MDB2_Driver_mysql')) {
        echo 'try to connect DB ';
        echo ' fail!!!';
			print_r($_db);
        if (PEAR::isError($_db)) {
            $msg = $_db->getMessage();
            echo($msg);
//				print_r($_db);
        }
    }
    else 
    {
        $_db->loadModule('Extended');
        $_db->fetchmode = 3;
        $_db->query('set names utf8');
    }
    return $_db;
}

$r = array();
$r = explode('/', $_SERVER['PHP_SELF']);
$page = end($r);
$smarty->assign("_comments", $db->getAll("select * from _comments where page='$page'"));

function smarty_block_dynamic($param, $content, &$smarty)
{
    return $content;
}

$smarty->register_block('dynamic', 'smarty_block_dynamic', false);

$smarty->register_resource("string", array("string_get_template",
    "string_get_timestamp",
    "string_get_secure",
    "string_get_trusted"));

function string_get_template($tpl_name, &$tpl_source, &$smarty_obj)
{
    global $smartyStringTemplates;
    $tpl_source = $smartyStringTemplates[$tpl_name];
    return true;
}

function string_get_timestamp($tpl_name, &$tpl_timestamp, &$smarty_obj)
{
// do database call here to populate $tpl_timestamp.
    $tpl_timestamp = time;
    return true;
}

function string_get_secure($tpl_name, &$smarty_obj)
{
    return true;
}

function string_get_trusted($tpl_name, &$smarty_obj)
{
}

function aprint_r($var)
{
    global $loggedUser;
    if ($loggedUser->data->get_error_on_page) print_r($var->userinfo);
}

$smarty->assign("dayshift_logout_at", $db->getOne("select value_time from timestamp_setting where `code`='dayshift_logout_at'"));
$smarty->assign("now", date("H:i:s"));
$smarty->assign("today", date("Y-m-d"));
$smarty->assign("yesterday", $db->getOne("select date(date_sub(now(), interval 1 day))"));
$smarty->assign("tomorrow", $db->getOne("select date(date_add(now(), interval 1 day))"));
$smarty->assign("tomorrow5", $db->getOne("select date(date_add(now(), interval 5 day))"));
$smarty->assign("tomorrow30", $db->getOne("select date(date_add(now(), interval 30 day))"));
global $_SERVER_REMOTE_ADDR;
if ( ! isset($_SERVER['HTTP_X_FORWARDED_FOR']) || ! $_SERVER['HTTP_X_FORWARDED_FOR'] /* || strpos($_SERVER['HTTP_X_FORWARDED_FOR'],',')===false*/) {
    $_SERVER_REMOTE_ADDR = $_SERVER['REMOTE_ADDR'];
} else {
    list($_SERVER_REMOTE_ADDR) = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
}

