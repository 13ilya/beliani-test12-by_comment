<?php
class Visitor
{
	const COOKIE = '_vi';
	
	private static $_instance = null;
	
	public $data;
	private $_db;
	private $_dbr;
	private $_loggedCustomer;
	
	private function __construct($db, $dbr, $loggedCustomer) 
	{
		$this->_db = $db;
		$this->_dbr = $dbr;
		$this->_loggedCustomer = $loggedCustomer;
		
		if (isset($_COOKIE[self::COOKIE]) && !empty($_COOKIE[self::COOKIE])) 
		{
			$session_id = $_COOKIE[self::COOKIE];
		}
		else
		{
			$session_id = md5($_SERVER['REMOTE_ADDR'] . date('Y-m-d H:i:s'));
			setcookie(self::COOKIE, $session_id, strtotime('+1 year'), '/');
		}
		
		$result = $this->_dbr->query("SELECT * FROM `visitor` WHERE `phpsessid` = '$session_id' ORDER BY `id` DESC LIMIT 1");
		$this->data = $result->fetchRow();
		
		if (!$this->data) 
		{
			$this->data = new stdClass();
			$this->data->phpsessid = $session_id;
			$this->data->ip = $_SERVER['REMOTE_ADDR'];
			$this->data->created = date('Y-m-d H:i:s');
			$this->data->visited = date('Y-m-d H:i:s');
		}

		$this->save();
	}
	
	static public function getInstance($db, $dbr, $loggedCustomer) 
	{
		if(is_null(self::$_instance))
		{
			self::$_instance = new self($db, $dbr, $loggedCustomer);
		}
		return self::$_instance;
	}
	
	public function save()
	{
		if ($this->data)
		{
			$customer_query = $this->_loggedCustomer ? $this->_loggedCustomer->id : 'NULL';
			
			if (isset($this->data->id) && $this->data->id)
			{
				$this->data->visited = date('Y-m-d H:i:s');
				$this->_db->query("UPDATE `visitor` SET 
						`phpsessid` = '{$this->data->phpsessid}',
						`ip` = '{$this->data->ip}',
						`customer_id` = {$customer_query},
						`visited` = '{$this->data->visited}'
					WHERE `id` = {$this->data->id} LIMIT 1");
			}
			else
			{
				$result = $this->_db->query("INSERT INTO `visitor` (`phpsessid`, `ip`, `customer_id`, `created`, `visited`) 
					VALUES (
						'{$this->data->phpsessid}', 
						'{$this->data->ip}', 
						{$customer_query}, 
						'{$this->data->created}', 
						'{$this->data->visited}')");

				$this->data->id = $this->_db->lastInsertID();
			}
		}
		else
		{
			throw Exception('Error saving visitor: data must be set!');
		}
	}
}
?>