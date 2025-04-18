<?php
require_once 'lib/ShopCatalogue.php';
require_once 'lib/SavedEntity.php';

class SavedSims extends SavedEntity {
	/*
	 * Sims to add 
	 */
	public $to_assign = array();
	/*
	 * Sims to delete
	 */
	public $to_delete = array();
	/**
	 * Constructor
	 */
	public function __construct($id, $username, $available_sas, $db, $dbr)
	{	
		$this->username = $username;
		$this->available_sas = $available_sas;
		parent::__construct($id, $db, $dbr);
	}
	/**
	 * Loads all sims data
	 */ 
	protected function _load()
	{
        $q = "select distinct ss.id
            , ss.saved_id
            , ss.sim_saved_id
            , ss.inactive+sa.old+sa.inactive as inactive
            , ss.ordering
            , sp_auction_name.par_value as auction_name
            from saved_sim ss
            LEFT JOIN saved_params sp_auction_name ON sp_auction_name.saved_id = ss.sim_saved_id and sp_auction_name.par_key = 'auction_name'
            join saved_auctions sa on sa.id=ss.sim_saved_id and sa.old=0 and sa.inactive=0
            where ss.saved_id={$this->id} and ss.inactive=0 and ss.sim_saved_id
            order by ss.ordering";
        $sims = $this->_dbr->getAll($q);
        
		foreach ($sims as $sim)
		{
			$sim->last_changed = $this->_dbr->getOne("select CONCAT('Was changed by ',IFNULL(u.name, tl.username),' on ', tl.updated)
				from total_log tl
				left join users u on u.system_username=tl.username
				where table_name='saved_sim'
				and tableid = {$sim->id}
				order by tl.updated desc limit 1");
			$this->data[$sim->sim_saved_id] = $sim;
		}
	}
	/**
	 * Return all data
	 */
	public function get()
	{
		$result = new stdClass();
		$result->data = $this->data;
		$result->options = $this->available_sas;
		return $result;
	}
	/**
	 * Set new data 
	 * @var array
	 */
	public function setData($in, $old = null)
	{
		$assigned = array_keys($this->data);
		$new_sims = array_keys($in);

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
		
		$this->to_assign = array_diff($new_sims, $assigned);
		$this->to_delete = array_diff($assigned, $new_sims);
		
		foreach($in as $id => $row)
		{
			if (isset($this->data[$id]) && $row['ordering'] != $this->data[$id]->ordering)
			{
				$this->data[$id]->ordering = (int)$row['ordering'];
				$this->_changed_fields[] = $id;
			}
		}
	}
	/** 
	 * Save current data
	 */
	public function save()
	{
		foreach ($this->to_delete as $similar_sa)
		{
			$res = $this->_db->query("delete from saved_sim where saved_id = {$this->id} and sim_saved_id = $similar_sa limit 1");
			$res = $this->_db->query("delete from saved_sim where saved_id = $similar_sa and sim_saved_id = {$this->id} limit 1");
		}
		
		foreach ($this->_changed_fields as $similar_sa)
		{
			$res = $this->_db->query('update saved_sim set ordering = ' . $this->data[$similar_sa]->ordering . '
				where saved_id = ' . $this->id . ' and sim_saved_id = ' . $similar_sa . ' limit 1');
		}
		
		$ordering = [];
		foreach ($this->data as $sim_saved_id => $row)
		{
			$ordering[] = (int)$row->ordering;
		}
		$max_ordering = max($ordering);
		
		foreach ($this->to_assign as $similar_sa)
		{
			$max_ordering += 2;
			$res = $this->_db->query("insert ignore into saved_sim (saved_id, sim_saved_id, ordering) values ({$this->id}, $similar_sa, $max_ordering)");
			$res = $this->_db->query("insert ignore into saved_sim (saved_id, sim_saved_id) values ($similar_sa, {$this->id})");
		}

		if (!empty($this->to_assign) || !empty($this->to_delete) || !empty($this->_changed_fields))
			cacheClear("Shop_catalogue::sgetSimsParams({$this->id}%");
		
		$this->to_assign = array();		
		$this->to_delete = array();
		$this->_changed_fields = array();
	}
}