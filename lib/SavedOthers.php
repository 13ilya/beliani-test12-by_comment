<?php
require_once 'lib/SavedEntity.php';

class SavedOthers extends SavedEntity {
	/*
	 * IDs for update
	 */
	private $_to_update;
	/**
	 * Constructor
	 */
	public function __construct($id, $sa_names, $available_sas, $db, $dbr)
	{	
		$this->sa_names = $sa_names;
		$this->available_sas = $available_sas;
		parent::__construct($id, $db, $dbr);
	}
	/**
	 * Loads all data
	 */ 
	protected function _load()
	{
		$others = $this->_dbr->getAll("select saved_other.* 
			, (select CONCAT('Was changed by ',IFNULL(u.name, tl.username),' on ', tl.updated)
				from total_log tl
				left join users u on u.system_username=tl.username
				where table_name='saved_other' 
				and tableid = saved_other.id
				order by tl.updated desc limit 1) last_changed
			from saved_other 
			where saved_id=" . $this->id . " 
			order by ordering");
			
		foreach ($others as $other)
			$this->data[$other->other_saved_id] = $other;
	}
	/**
	 * Return all data
	 */
	public function get()
	{
		$result = new stdClass();
		$result->data = $this->data;
		$result->options['available_sas'] = $this->available_sas;
		$result->options['sa_names'] = $this->sa_names;
		return $result;
	}
	/**
	 * Set new data 
	 * @var array
	 */
	public function setData($in, $old = null)
	{
		$assigned = array_keys($this->data);
		$new_others = array_keys($in);
		
		if (isset($old))
		{
			$_assigned = array_keys($old);
			sort($assigned); 
			sort($_assigned); 

			if ($assigned != $_assigned)
			{
				throw new Exception(Saved::MESSAGE_DATA_IS_CHANGED);	
			}
		}
		
		$this->to_assign = array_diff($new_others, $assigned);
		$this->to_delete = array_diff($assigned, $new_others);

		foreach ($in as $id => $row)
		{
			if (isset($this->data[$id]) && ($this->data[$id]->NameID != $row['NameID'] || $this->data[$id]->ordering != $row['ordering']))
			{
				$this->_to_update[] = $id;
				$this->data[$id]->NameID = mysql_escape_string($row['NameID']);
				$this->data[$id]->ordering = mysql_escape_string($row['ordering']);
			}
			else
			{
				$other = new stdClass();
				$other->ordering = $row['ordering'];
				$other->NameID = $row['NameID'];
				$this->data[$id] = $other;
			}
		}
	}
	/**
	 * Save current data
	 */
	public function save()
	{
        foreach ($this->to_assign as $other_saved_id)
		{
			$ordering = isset($this->data[$other_saved_id]->ordering) ? $this->data[$other_saved_id]->ordering : '';
			$name_id = isset($this->data[$other_saved_id]->NameID) ? $this->data[$other_saved_id]->NameID : 0;
		
			$res = $this->_db->query("insert ignore into saved_other (saved_id, other_saved_id, NameID, ordering) 
				values ({$this->id}, $other_saved_id, $name_id, $ordering)");
		}
		$this->to_assign = array();
		
        foreach ($this->to_delete as $other_saved_id)
		{
			$res = $this->_db->query("delete from saved_other where saved_id = {$this->id} and other_saved_id = $other_saved_id");
		}
		$this->to_delete = array();
		
        foreach ($this->_to_update as $other_id)
		{
			$res = $this->_db->query("update saved_other set 
				NameID = " . (int)$this->data[$other_id]->NameID . "
				, ordering = " . (int)$this->data[$other_id]->ordering . "
				where saved_id=" . $this->id . " and other_saved_id = " . (int)$other_id . " limit 1");
		}
		$this->to_update = array();
	}
}