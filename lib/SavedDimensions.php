<?php
require_once 'lib/SavedEntity.php';

class SavedDimensions extends SavedEntity {
	/**
	 * Constructor
	 */
	public function __construct($id, $offer_id, $seller_id, $db, $dbr)
	{	
		$this->offer_id = (int)$offer_id;
		$this->seller_id = (int)$seller_id;
		parent::__construct($id, $db, $dbr);
	}
	/**
	 * Loads all data
	 */ 
	protected function _load()
	{
		$q = "SELECT 0 forsa, IFNULL(sp.id, -ap.id) id, ap.`dimension_l`, ap.`dimension_h`, ap.`dimension_w`, ap.`weight_parcel`, IFNULL(sp.export,1) export
			FROM article_list al 
			JOIN offer_group og ON al.group_id = og.offer_group_id and not base_group_id
			join article_parcel ap on ap.article_id=al.article_id
			left join saved_parcel sp on sp.saved_id=" . $this->id . " and sp.parcel_id=ap.id
			WHERE og.offer_id =" . $this->offer_id . " and not al.inactive and not og.additional
			union 
			SELECT 1 forsa, sp.id, sp.`dimension_l`, sp.`dimension_h`, sp.`dimension_w`, sp.`weight_parcel`, sp.export
			FROM saved_parcel sp 
			where sp.saved_id=" . $this->id . " and sp.parcel_id is null
			";

		$parcels = $this->_dbr->getAll($q);

		$weight = 0;
	    $volume = 0;
		$dims = array();
		$min_weight = 0;
		foreach($parcels as $kp=>$parcel) {
			$parcels[$kp]->bandmass = (max($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h)
				+ 2*($parcel->dimension_l + $parcel->dimension_w + $parcel->dimension_h 
				- max($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h))) / 100;
			$parcels[$kp]->dimension = $parcel->dimension_l*$parcel->dimension_h*$parcel->dimension_w / 1000000;
			if ($parcel->export) {
				$weight += $parcel->weight_parcel;
				$volume += ($parcel->dimension_l*$parcel->dimension_w*$parcel->dimension_h)/1000000;
				$min_weight = min($min_weight, $parcel->weight_parcel);
				$dims[] = $parcel->dimension_l;
				$dims[] = $parcel->dimension_w;
				$dims[] = $parcel->dimension_h;
			}
		}
		
		$this->params['parcels']['weight_kg_carton'] = $weight;
		$this->params['parcels']['weight_g_carton'] =  $weight * 1000;
		$this->params['parcels']['volume_m3_carton'] = $volume;
		
		foreach ($parcels as $parcel)
		{
			$this->parcels[$parcel->id] = $parcel;
		}
		
		/* *********************** */
		$q = "SELECT 0 forsa, IFNULL(sp.id, -ap.id) id, ap.`dimension_l`, ap.`dimension_h`, ap.`dimension_w`, ap.`weight_parcel`, ap.`price`, IFNULL(sp.export,1) export
					FROM article_list al
					JOIN offer_group og ON al.group_id = og.offer_group_id and not base_group_id
					join article_real_parcel ap on ap.article_id=al.article_id
					left join saved_real_parcel sp on sp.saved_id=" . $this->id . " and sp.parcel_id=ap.id
					WHERE og.offer_id =" . $this->offer_id . " and not al.inactive and not og.additional
					union
					SELECT 1 forsa, sp.id, sp.`dimension_l`, sp.`dimension_h`, sp.`dimension_w`, sp.`weight_parcel`, sp.`price`, sp.export
					FROM saved_real_parcel sp
					where sp.saved_id=" . $this->id . " and sp.parcel_id is null
					";

		$real_parcels = $this->_dbr->getAll($q);
		$weight = 0;
		$volume = 0;
		$dims = array();
		$min_weight = 0;
		foreach ($real_parcels as $kp => $parcel) {
			$real_parcels[$kp]->bandmass = (max($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h)
					+ 2 * ($parcel->dimension_l + $parcel->dimension_w + $parcel->dimension_h
						- max($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h))) / 100;
			$real_parcels[$kp]->dimension = $parcel->dimension_l * $parcel->dimension_h * $parcel->dimension_w / 1000000;
			if ($parcel->export) {
				$weight += $parcel->weight_parcel;
				$volume += ($parcel->dimension_l * $parcel->dimension_w * $parcel->dimension_h) / 1000000;
				$min_weight = min($min_weight, $parcel->weight_parcel);
				$dims[] = $parcel->dimension_l;
				$dims[] = $parcel->dimension_w;
				$dims[] = $parcel->dimension_h;
			}
		}
		if (count($dims)) 
		{
			sort($dims);
			if ($min_weight < 30 && $dims[count($dims) - 1] + 2 * $dims[0] + $dims[1] < 360) 
			{
				$shipping_art = 'Paketversand';
			} 
			elseif ($min_weight >= 30 && $min_weight < 35 && $dims[0] <= 120 && $dims[count($dims) - 1] <= 320 && $dims[1] <= 225) 
			{
				$shipping_art = 'DE-1Man-2Options';
			} 
			else
			{
				$shipping_art = 'De-2Man-2Options';
			}
		}
	    
		$this->params['real_parcels']['shipping_art'] = $shipping_art;
		$this->params['real_parcels']['weight_kg_carton'] = $weight;
		$this->params['real_parcels']['weight_g_carton'] =  $weight * 1000;
		$this->params['real_parcels']['volume_m3_carton'] = $volume;
		
		foreach ($real_parcels as $parcel)
		{
			$this->real_parcels[$parcel->id] = $parcel;
		}		
		
		$this->shipping_arts = $this->_dbr->getAssoc("select id, shipping_art 
			from seller_shipping_art where seller_id=" . $this->seller_id);
			
		$this->export = $this->_dbr->getOne("SELECT export FROM saved_auctions WHERE id=" . $this->id);
	}
	/**
	 * Return all data
	 */
	public function get()
	{
		$result = new stdClass();
		
		$result->options['parcels'] = $this->params['parcels'];
		$result->options['real_parcels'] = $this->params['real_parcels'];
		$result->options['shipping_arts'] = $this->shipping_arts;
		
		$result->data['parcels'] = $this->parcels;
		$result->data['real_parcels'] = $this->real_parcels;
		$result->data['export'] = $this->export;
		
		return $result;
	}
	/**
	 * Set new data 
	 * @var array
	 */
	public function setData($in, $old = null)
	{
		$compare_fields = array('weight_parcel', 'dimension_h', 'dimension_l', 'dimension_w', 'export');

		foreach ($in['parcels'] as $id => $row)
		{
			$row = array_to_object($row);
			
			if (isset($this->parcels[$id]))
			{
				foreach ($compare_fields as $field)
				{
					if (isset($old['parcels'][$id][$field]) && $this->parcels[$id]->$field != $old['parcels'][$id][$field])
					{
						throw new Exception(Saved::MESSAGE_DATA_IS_CHANGED);		
					}				
				
					if ($row->$field != $this->parcels[$id]->$field)
					{
						$this->parcels[$id]->$field = mysql_escape_string($row->$field);
						
						if (!in_array($id, $this->_to_update))
							$this->_to_update['parcels'][] = $id;
					}
				}
			}
			elseif (is_int($id) || strpos($id, 'new_') !== false)
			{
				$this->parcels[] = $row;
				$this->_to_assign['parcels'][] = $row;
			}
		}

		foreach ($in['real_parcels'] as $id => $row)
		{
			$row = array_to_object($row);
			
			if (isset($this->real_parcels[$id]))
			{
				foreach ($compare_fields as $field)
				{
					if (isset($old['real_parcels'][$id][$field]) && $this->real_parcels[$id]->$field != $old['real_parcels'][$id][$field])
					{
						throw new Exception(Saved::MESSAGE_DATA_IS_CHANGED);		
					}				
				
					if ($row->$field != $this->real_parcels[$id]->$field)
					{
						$this->real_parcels[$id]->$field = mysql_escape_string($row->$field);
						
						if (!in_array($id, $this->_to_update))
							$this->_to_update['real_parcels'][] = $id;
					}
				}
			}
			elseif (is_int($id) || strpos($id, 'new_') !== false)
			{
				$this->real_parcels[] = $row;
				$this->_to_assign['real_parcels'][] = $row;
			}
		}
		

		if (isset($old['export']) && $this->export != $old['export'])
		{
			throw new Exception(Saved::MESSAGE_DATA_IS_CHANGED);		
		}				
		
		$export = (int)$in['export'];
		if ($export != $this->export)
		{
			$this->export = $export;
			$this->_export_changed = true;
		}
	}
	/**
	 * Save current data
	 */
	public function save()
	{
		foreach ($this->_to_update as $group_name => $parcel_ids)
		{
			foreach ($parcel_ids as $id)
			{
				if ($group_name == 'parcels')
				{
                    if ($id < 0 ) {
                        $row = new stdClass();
                        $row->dimension_l = $this->parcels[$id]->dimension_l;
                        $row->dimension_w = $this->parcels[$id]->dimension_w;
                        $row->dimension_h = $this->parcels[$id]->dimension_h;
                        $row->weight_parcel = $this->parcels[$id]->weight_parcel;
                        $row->parcel_id = abs($id);
                        $row->export = $this->parcels[$id]->export;

                        $this->_to_assign['parcels'][] = $row;
                    } else {
                        $this->_db->query("update saved_parcel set export=" . (int)$this->parcels[$id]->export . "
                            , dimension_l=" . $this->parcels[$id]->dimension_l . "
                            , dimension_w=" . $this->parcels[$id]->dimension_w . "
                            , dimension_h=" . $this->parcels[$id]->dimension_h . "
                            , weight_parcel=" . $this->parcels[$id]->weight_parcel . "
                            where id=" . $id);
                    }
				}
				elseif ($group_name == 'real_parcels') 
				{
                    if ($id < 0 ) {
                        $row = new stdClass();
                        $row->dimension_l = $this->real_parcels[$id]->dimension_l;
                        $row->dimension_w = $this->real_parcels[$id]->dimension_w;
                        $row->dimension_h = $this->real_parcels[$id]->dimension_h;
                        $row->weight_parcel = $this->real_parcels[$id]->weight_parcel;
                        $row->parcel_id = abs($id);
                        $row->export = $this->real_parcels[$id]->export;

                        $this->_to_assign['real_parcels'][] = $row;
                    } else {
                        $this->_db->query("update saved_real_parcel set export=" . (int)$this->real_parcels[$id]->export . "
                            , dimension_l=" . $this->real_parcels[$id]->dimension_l . "
                            , dimension_w=" . $this->real_parcels[$id]->dimension_w . "
                            , dimension_h=" . $this->real_parcels[$id]->dimension_h . "
                            , weight_parcel=" . $this->real_parcels[$id]->weight_parcel . "
                            where id=" . $id);
                    }
				}
			}
		}
		$this->_to_update = [];
        
		foreach ($this->_to_assign as $group_name => $parcels)
		{
			foreach ($parcels as $row)
			{
				if ($group_name == 'parcels')
				{
					$this->_db->query("insert into saved_parcel set
						saved_id=" . (int)$this->id . "
						, parcel_id=" . (isset($row->parcel_id) ? (int)$row->parcel_id : 'NULL') . "
						, export=" . (int)$row->export . "
						, dimension_l={$row->dimension_l}
						, dimension_w={$row->dimension_w}
						, dimension_h={$row->dimension_h}
						, weight_parcel={$row->weight_parcel}");
				} 
				elseif ($group_name == 'real_parcels') 
				{
					$this->_db->query("insert into saved_real_parcel set
						saved_id=" . (int)$this->id . "
						, parcel_id=" . (isset($row->parcel_id) ? (int)$row->parcel_id : 'NULL') . "
						, export=" . (int)$row->export . "
						, dimension_l={$row->dimension_l}
						, dimension_w={$row->dimension_w}
						, dimension_h={$row->dimension_h}
						, weight_parcel={$row->weight_parcel}");
				}
			}
		}
		$this->_to_assign = [];
		
		if ($this->_export_changed)
		{
			$this->_db->query("update saved_auctions set export={$this->export} where id={$this->id} limit 1");
			unset($this->_export_changed);
		}
	}
}