<?php
require_once 'lib/SavedEntity.php';
require_once 'lib/ShopCatalogue.php';

class SavedCatalogue extends SavedEntity
{
    private $username;
    private $siteid;
    
	/**
	 * Constructor
	 */
	public function __construct($id, $username, $siteid, $db, $dbr)
	{	
		$this->username = mysql_escape_string($username);
		$this->siteid = (int)$siteid;
		parent::__construct($id, $db, $dbr);
	}
	/**
	 * Return all data
	 */
	public function get()
	{
		$result = new stdClass();
		$result->data = $this->data;
		$result->options = $this->options;
		return $result;
	}
	/**
	 * Loads all catalogue data
	 */ 
	protected function _load()
	{
		$shops = $this->_dbr->getAssoc("select id as iid, id, name, url, `ssl` 
			from shop where username='" . $this->username . "' 
			and siteid=" . $this->siteid . " order by id");
			
		$this->data['catalogue'] = [];
		$this->data['limited'] = [];
	
		foreach ($shops as $id => $shop) 
		{
			$this->data['catalogue'][$id] = [];
			$this->data['limited'][$id] = [];
			
			$shopCatalogue = new Shop_Catalogue($this->_db, $this->_dbr, $id, '', 1);
			global $getall;
			$getall = 1;
			$shops[$id]['shop_catalogues'] = $shopCatalogue->listAll(0, 0, '(0)');

			$this->__limited = [];
			foreach($shops[$id]['shop_catalogues'] as $key => $value)
			{
				$shops[$id]['shop_catalogues'][$key] = $this->_clearCatalogue($value);
			}

			$where_in .= isset($where_in) ? ", 'shop_catalogue_id[{$id}]'" : "'shop_catalogue_id[{$id}]'";
			
			if (!empty($this->__limited))
				foreach($this->__limited as $limited)
					$where_in .= ", 'cal_cat[{$id}][$limited]'";
		}

		$all = $this->_dbr->getAll('SELECT `par_key`, `par_value`
			FROM `saved_params`
			WHERE `par_key` IN (' . $where_in . ')
			AND `saved_id` = ' . $this->id);
		
		foreach($all as $row)
		{
			if (preg_match("/shop_catalogue_id\[(\d+)\]/ui", $row->par_key, $matches))
				$this->data['catalogue'][(int)$matches[1]][] = (int)$row->par_value;
			
			if (preg_match("/cal_cat\[(\d+)\]\[(\d+)\]/ui", $row->par_key, $matches))
				$this->data['limited'][(int)$matches[1]][(int)$matches[2]] = $row->par_value;
		}
		
		$this->options['shops'] = $shops;
		
		$this->options['lastchange'] = $this->_dbr->getOne("select CONCAT('Was changed by ',IFNULL(u.name, tl.username),' on ', tl.updated)
			from total_log tl
			left join users u on u.system_username=tl.username
			where table_name='saved_params'
			and tableid = (select id from saved_params where saved_id=" . $this->id . " and par_key = 'shop_catalogue_changed')
			and field_name = 'par_value'
			order by tl.updated desc limit 1");
	}
	/**
	 * remove all unnecessary information to minimize size of transfering data
	 */
	private function _clearCatalogue($catalogue)
	{
		$result = new stdClass();
		$result->name = $catalogue->name;
		$result->id = $catalogue->id;
		
		$result->date_limited = $catalogue->date_limited;
		if ($catalogue->date_limited)
			$this->__limited[] = (int)$catalogue->id;
		
		$result->hidden = $catalogue->hidden;
		foreach ($catalogue->children as $key => $value)
		{
			$result->children[$key] = $this->_clearCatalogue($value);
		}
		return $result;
	}	
	/**
	 * Set new data 
	 * @var array
	 */
	public function setData($in, $old = null)
	{
		foreach($in['data']['catalogue'] as $shop_id => $new_catalogue)
		{
			$new_catalogue = empty($new_catalogue) ? [] : $new_catalogue;
			
			if (isset($old['data']['catalogue'][$shop_id]))
			{
				$old['data']['catalogue'][$shop_id] = empty($old['data']['catalogue'][$shop_id]) ? [] : $old['data']['catalogue'][$shop_id];
				$assigned = sort($this->data['catalogue'][$shop_id]);
				$_assigned = sort($old['data']['catalogue'][$shop_id]);
				if ($assigned != $_assigned)
					throw new Exception(Saved::MESSAGE_DATA_IS_CHANGED);
			}
			
			$new = array_diff($new_catalogue, $this->data['catalogue'][$shop_id]);
			if (!empty($new))
				$this->to_assign['catalogue'][$shop_id] = $new;
				
			$deleted = array_diff($this->data['catalogue'][$shop_id], $new_catalogue);
			if (!empty($deleted))
				$this->to_delete['catalogue'][$shop_id] = $deleted;
			
			if (!empty($new) || !empty($deleted))
				$this->data['catalogue'][$shop_id] = $new_catalogue;	
		}

		foreach($in['data']['limited'] as $shop_id => $new_catalogue)
		{
			$new_catalogue = empty($new_catalogue) ? [] : $new_catalogue;
			
			if (isset($old['data']['limited'][$shop_id]))
			{
				$old['data']['limited'][$shop_id] = empty($old['data']['limited'][$shop_id]) ? [] : $old['data']['limited'][$shop_id];
				$assigned = sort($this->data['limited'][$shop_id]);
				$_assigned = sort($old['data']['limited'][$shop_id]);
				if ($assigned != $_assigned)
					throw new Exception(Saved::MESSAGE_DATA_IS_CHANGED);
			}
			
			$deleted = array_diff(array_keys($this->data['limited'][$shop_id]), array_keys($new_catalogue));
			
			if (!empty($deleted))
				$this->to_delete['limited'][$shop_id] = $deleted;
			foreach ($new_catalogue as $category => $date)
			{
				if (isset($old['data']['limited'][$shop_id][$category]) 
						&& $old['data']['limited'][$shop_id][$category] != $this->data['limited'][$shop_id][$category])
				{
					throw new Exception(Saved::MESSAGE_DATA_IS_CHANGED);
				}
			
				if (isset($this->data['limited'][$shop_id][$category]) && $this->data['limited'][$shop_id][$category] != $date)
				{
					$this->to_update['limited'][$shop_id][$category] = $date;
				}
				elseif (!isset($this->data['limited'][$shop_id][$category]))
				{
					$this->to_assign['limited'][$shop_id][$category] = $date;
				}
			}
			$this->data['limited'][$shop_id] = $new_catalogue;	
		}
	}
	/**
	 * Save current data
	 */
	public function save()
	{
        $processed = [];
		/* catalog */
		foreach ($this->to_assign['catalogue'] as $shop_id => $catalogue)
		{
			foreach ($catalogue as $category) {
				$this->_db->query("INSERT INTO `saved_params` SET
					`par_key` = 'shop_catalogue_id[$shop_id]',
					`par_value` = $category,
					`saved_id` = {$this->id}");
                
                /**
                 * New in Catalog moving to top
                 */
                $min_cat_order = (int)$this->_dbr->getOne("SELECT min(CAST(`par_value` AS SIGNED)) 
                    FROM `saved_params` WHERE `par_key` = 'shopspos_cat[$shop_id][$category]'");
                $min_cat_order--;

                $shopspos_cat_id = $this->_dbr->getOne("SELECT `id` FROM `saved_params` 
                    WHERE `par_key` = 'shopspos_cat[$shop_id][$category]' AND `saved_id` = {$this->id}");

                if ($shopspos_cat_id) {
                    $this->_db->query("UPDATE `saved_params` SET
                        `par_value` = '$min_cat_order' WHERE `id` = $shopspos_cat_id");
                } else {
                    $this->_db->query("INSERT INTO `saved_params` SET
                        `par_key` = 'shopspos_cat[$shop_id][$category]',
                        `par_value` = '$min_cat_order',
                        `saved_id` = {$this->id}");
                }
                
                $processed[] = $category;
            }
		}
		$this->to_assign['catalogue'] = array();
		
		foreach ($this->to_delete['catalogue'] as $shop_id => $catalogue)
		{
			foreach ($catalogue as $category) {
				$this->_db->query("DELETE FROM `saved_params` WHERE
					`par_key` = 'shop_catalogue_id[$shop_id]' 
					AND `par_value` = $category
					AND `saved_id` = {$this->id}
					LIMIT 1");
                    
                $processed[] = $category;
            }
		}
		$this->to_delete['catalogue'] = array();

		/* limited */
		foreach ($this->to_assign['limited'] as $shop_id => $catalogue)
		{
			foreach ($catalogue as $category_id => $date) {
				$this->_db->query("INSERT INTO `saved_params` SET
					`par_key` = 'cal_cat[$shop_id][$category_id]',
					`par_value` = '" . mysql_real_escape_string($date) . "',
					`saved_id` = {$this->id}");
                
                $processed[] = $category_id;
            }
		}
		$this->to_assign['limited'] = array();
		
		foreach ($this->to_delete['limited'] as $shop_id => $catalogue)
		{
			foreach ($catalogue as $category) {
				$this->_db->query("DELETE FROM `saved_params` WHERE
					`par_key` = 'cal_cat[$shop_id][$category]' 
					AND `saved_id` = {$this->id}
					LIMIT 1");
                
                $processed[] = $category;
            }
		}
		$this->to_delete['limited'] = array();
		
		foreach ($this->to_update['limited'] as $shop_id => $catalogue)
		{
			foreach ($catalogue as $category_id => $date) {
				$this->_db->query("UPDATE `saved_params` 
					SET `par_value` = '" . mysql_real_escape_string($date) . "'
					WHERE `par_key` = 'cal_cat[$shop_id][$category_id]' 
					AND `saved_id` = {$this->id}
					LIMIT 1");
                
                $processed[] = $category_id;
            }
		}
		$this->to_update['limited'] = array();
        
        $processed = array_unique($processed);
        foreach ($processed as $shop_catalogue_id)
            cacheClear("getOffers($shop_catalogue_id%");
	}
}