<?php
require_once 'lib/SavedEntity.php';

class SavedBonus extends SavedEntity {
	/**
	 * Constructor
	 */
	public function __construct($id, $username, $lang, $db, $dbr)
	{	
		$this->username = $username;
		$this->lang = $lang;
		parent::__construct($id, $db, $dbr);
	}
	/**
	 * Loads all icons data
	 */ 
	public function _load()
	{
		$this->bonus_groups = listBonusGroup($this->_db, $this->_dbr, $this->username, $this->lang);
		foreach ($this->bonus_groups as $k => $r) 
		{
			$this->bonus_groups[$k]->bonuses = listBonus($this->_db, $this->_dbr, $this->username, $this->lang, $r->id, 0);
		}
		
		$this->excluded = $this->_dbr->getCol('SELECT REPLACE(REPLACE(`par_key`, "]", ""), "bonus_exclude[", "") as id
			FROM `saved_params`
			WHERE `par_key` LIKE "bonus_exclude[%"
			AND `saved_id` = ' . $this->id);
	}
	/**
	 * Return all data
	 * @result object
	 */
	public function get()
	{
		$result = new stdClass();
		$result->data = $this->excluded;
		$result->options = $this->bonus_groups;
		return $result;
	}
	/**
	 * Set new data 
	 * @var array
	 */
	public function setData($in, $old = null)
	{
        if ($in['data'] == '') { 
            $in['data'] = []; 
        }
    
		if (isset($old['data']))
		{
            if ($old['data'] == '') {
                $old['data'] = [];
            }
            
			sort($this->excluded);
			sort($old['data']);
            
			if ($this->excluded != $old['data'])
				throw new Exception(Saved::MESSAGE_DATA_IS_CHANGED);
		}
	
		$this->to_assign = array_diff($in['data'], $this->excluded);
		$this->to_delete = array_diff($this->excluded, $in['data']);
        
		$this->excluded = $in['data'];
	}
	/**
	 * Save current data
	 */
	public function save()
	{
		foreach ($this->to_assign as $bonus)
		{
			$res = $this->_db->query("INSERT INTO `saved_params` (`saved_id`, `par_key`, `par_value`) 
				VALUES ({$this->id}, 'bonus_exclude[" . (int)$bonus . "]', 1)");
		}
		$this->to_assign = array();
		
		foreach ($this->to_delete as $bonus)
		{
			$res = $this->_db->query("DELETE FROM `saved_params` 
				WHERE `saved_id` = {$this->id} AND `par_key` = 'bonus_exclude[" . (int)$bonus . "]' LIMIT 1");
		}
		$this->to_delete = array();		
	}
}