<?php
require_once 'lib/SavedEntity.php';

class SavedIcons extends SavedEntity {
	/**
	 * Shop id
	 */
	public $shop_id;
	/**
	 * Constructor
	 */
	public function __construct($id, $shop_id, $db, $dbr)
	{	
		$this->shop_id = (int)$shop_id;
		parent::__construct($id, $db, $dbr);
	}
	/**
	 * Loads all icons data
	 */ 
	public function _load()
	{
		$selected = $this->_dbr->getCol("SELECT `par_key` FROM `saved_params` 
			WHERE `saved_id` = " . $this->id . "
			AND `par_key` LIKE 'icons%'");
		
		$values = array();
		foreach ($selected as $par_key)
		{
			$values[] = (int)str_replace(array('icons[', ']'), array('', ''),  $par_key);
		}
			
		$this->data['selected'] = $values;

		$this->options = $this->_dbr->getAll("SELECT i.* FROM shop_icons si
			LEFT JOIN icons i ON i.id = si.icon_id
			WHERE si.shop_id = " . $this->shop_id . " and i.active=1");
	}
	/**
	 * Return all data
	 * @result object
	 */
	public function get()
	{
		$result = new stdClass();
		$result->data = $this->data;
		$result->options = $this->options;
		return $result;
	}
	/**
	 * Set new data 
	 * @var array
	 */
	public function setData($in, $old = null)
	{
		foreach ($in['data'] as $field => $value)
		{
			if ($this->data[$field] != $value)
			{
				if ($field == 'selected')
				{
                    $value = empty($value) ? [] : $value;
                    
					if (isset($old['data']))
					{
                        $old['data'][$field] = empty($old['data'][$field]) ? [] : $old['data'][$field];
                        
						$assigned = sort($this->data[$field]);
						$_assigned = sort($old['data'][$field]);
						
						if ($assigned != $_assigned)
						{
							throw new Exception(Saved::MESSAGE_DATA_IS_CHANGED);		
						}
					}

					$this->to_assign = array_diff($value, $this->data[$field]);
					$this->to_delete = array_diff($this->data[$field], $value);
				}
				
				if (isset($old['data']) && $old['data'][$field] != $this->data[$field])
				{
					throw new Exception(Saved::MESSAGE_DATA_IS_CHANGED);
				}
				$this->data[$field] = $value;
				$this->_changed_fields[] = $field;
			}
		}
	}
	/**
	 * Save current data
	 */
	public function save()
	{
		foreach ($this->_changed_fields as $field)
		{
            foreach($this->to_assign as $icon_id) 
            {
                $this->_db->query("INSERT INTO `saved_params` (`saved_id`, `par_key`, `par_value`) 
                    VALUES ({$this->id}, 'icons[$icon_id]', 1)");
            }
            
            foreach($this->to_delete as $icon_id) 
            {
                $this->_db->query("DELETE FROM `saved_params` 
                    WHERE `saved_id` = {$this->id}
                    AND `par_key` = 'icons[$icon_id]'
                    LIMIT 1");
            }
		}
		
		$this->_changed_fields = array();
	}
}