<?php
/**
 * Main controller for API calls
 */
class apiController {
	/**
	 * @type MDB2_Driver_mysql DB read and write object
	 */
	protected $_db;
	
	/**
	 * @type MDB2_Driver_mysql DB only read object
	 */
	protected $_dbr;

    /**
     * @type User logged user object
     */
    protected $_loggedUser;

	/**
	 * @type array Input data array
	 */
	protected $_input = array();

	/**
	 * @type array Array to collect result data
	 */
	protected $_result = array();

    /**
     * @type boolean User logged in flag
     */
    protected $_loggedIn;

    /**
     * @type string Site url
     */
    protected $_siteURL;

	/**
	 * Constructor
	 */
	function __construct()
	{
        $timer = microtime(true);
        
		$this->timer = microtime(true);
		
		require_once 'connect.php';
        $GLOBALS['db'] = $db;
        $GLOBALS['dbr'] = $dbr;
        $GLOBALS['dbr_spec'] = $dbr_spec;
        $GLOBALS['configs'] = \Config::getAll($db, $dbr);


        $checkpoint = round(microtime(true) - $timer, 3);
        $this->_result['debug'][] = "- CONSTRUCT 1: $checkpoint -";
        $timer = microtime(true);
        
        require_once 'util.php';
		#require_once 'login.php';
        require_once 'config.php';

        $checkpoint = round(microtime(true) - $timer, 3);
        $this->_result['debug'][] = "- CONSTRUCT 2: $checkpoint -";
        $timer = microtime(true);
        
        $GLOBALS['db'] = $db;
        $GLOBALS['dbr'] = $dbr;
        $GLOBALS['dbr_spec'] = $dbr_spec;
        $GLOBALS['siteURL'] = $siteURL;
		
		$this->_db = $GLOBALS['db'];
		$this->_dbr = $GLOBALS['dbr'];
        $this->_siteURL = $GLOBALS['siteURL'];
		
		if (!isset($GLOBALS['loggedUser']))
			$GLOBALS['loggedUser'] = $loggedUser;

        if (!isset($GLOBALS['loggedIn']))
            $GLOBALS['loggedIn'] = $loggedIn;

        $this->_loggedUser = $GLOBALS['loggedUser'];
        $this->_loggedIn = $GLOBALS['loggedIn'];

		$GLOBALS['smarty'] = $smarty;
		$GLOBALS['redis'] = $redis;

		foreach ($_REQUEST as $key => $value)
		{
			$this->_input[$key] = $value;
		}

        $checkpoint = round(microtime(true) - $timer, 3);
        $this->_result['debug'][] = "- CONSTRUCT 3: $checkpoint -";
        $timer = microtime(true);
        
        versions();
        
        $checkpoint = round(microtime(true) - $timer, 3);
        $this->_result['debug'][] = "- CONSTRUCT 4: $checkpoint -";
        $timer = microtime(true);
	}
	
	/*
	 * Output json result and die
	 */
	public function output($no_time = false)
	{
        if(!$no_time) {
            $this->_result['_exec_time'] = round(microtime(true) - $this->timer, 2);
        }
		
		header('Cache-Control: no-cache, must-revalidate');
		header('Expires: ' . date('D, d M Y H:i:s', strtotime('+1 day')) . ' GMT');
		header('Content-Type: application/json');
		
		echo json_encode($this->_result);
		die;
	}
        
	/**
	 * Put error message in json response
	 * @param string
	 */
	protected function responseError($message)
	{
		$this->_result['error'] = $message;
		$this->output();
	}
}