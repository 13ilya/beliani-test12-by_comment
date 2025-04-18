<?php
require_once 'lib/SavedEntity.php';

class SavedShopParams extends SavedEntity {
	/**
	 * Data to save
	 */
	private $_to_assign = array();
	/**
	 * Data to delete
	 */
	private $_to_delete = array();
	/**
	 * Param options
	 */ 
	public $pars = array();
	/**
	 * Param options
	 */ 
	public $sa_names = array();
	/**
	 * Constructor
	 */
	public function __construct($id, $lang, $db, $dbr)
	{	
		$this->lang = $lang;
		parent::__construct($id, $db, $dbr);
	}	
	/**
	 * Loads all data
	 */ 
	protected function _load()
	{
		$q = "select REPLACE(REPLACE(par_key,'shop_catalogue_id[',''),']','') shop_id, par_value shop_cataloue_id
			from saved_params sp
			where saved_id=" . $this->id . " and par_key like 'shop_catalogue_id[%]'";
		$cats = $this->_dbr->getAll($q);
		
		$cats_conds = 0;
		foreach($cats as $cat) 
		{
			$cats_conds .= " or (shop_id={$cat->shop_id} and shop_catalogue_id={$cat->shop_cataloue_id}) ";
		}
		$q = "select distinct sn.* from Shop_Name_Cat snc
			join Shop_Names sn on sn.id=snc.NameID and IFNULL(sn.def_value,'')=''
			where $cats_conds";

		$this->pars = $this->_dbr->getAll($q);

		foreach($this->pars as $k=>$par) 
		{
			$this->sa_names[$par->id] = $par->Name;
			if ($par->translatable) 
			{
				switch ($par->ValueType) 
				{
					case 'text':
						$q = "select distinct sv.id, t.value as value 
							from Shop_Values sv
							join Shop_Value_Cat svc on svc.ValueID=sv.id
							left join translation t on t.table_name='Shop_Values' and t.field_name='ValueText'
								and t.language='{$this->lang}' and t.id=sv.id
							where NameID={$par->id} and ($cats_conds)
							and sv.inactive=0
							order by sv.ordering";

						$this->pars[$k]->values = $this->_dbr->getAssoc($q);
						$this->pars[$k]->values_cnt = count($this->pars[$k]->values);
						$q = "select spv.ValueID as iid, spv.*
							, IFNULL(t.value, spv.FreeValueText) as value
							from saved_parvalues spv
							left join Shop_Values sv on sv.id=spv.ValueID
							left join translation t on t.table_name='Shop_Values' and t.field_name='ValueText'
								and t.language='{$this->lang}' and t.id=sv.id
							where spv.saved_id=" . $this->id . " and spv.NameID={$par->id}
							and sv.inactive=0
							order by sv.ordering";
					break;
					case 'dec':
						$q = "select distinct sv.id, sv.ValueDec as value 
							from Shop_Values sv
							join Shop_Value_Cat svc on svc.ValueID=sv.id
							where NameID={$par->id} and ($cats_conds)
							and sv.inactive=0
							order by sv.ordering";
		
						$this->pars[$k]->values = $dbr->getAssoc($q);
						$this->pars[$k]->values_cnt = count($this->pars[$k]->values);
						$q = "select spv.ValueID as iid, spv.*
							, IFNULL(t.value, spv.FreeValueDec) as value
							from saved_parvalues spv
							left join Shop_Values sv on sv.id=spv.ValueID
							left join translation t on t.table_name='Shop_Values' and t.field_name='ValueDec'
								and t.language='{$this->lang}' and t.id=sv.id
							where spv.saved_id=".$this->id." and spv.NameID={$par->id}
							and sv.inactive=0
							order by sv.ordering";
					break;
					case 'img':
						$q = "select distinct sv.id, t.value as value 
							from Shop_Values sv
							join Shop_Value_Cat svc on svc.ValueID=sv.id
							left join translation t on t.table_name='Shop_Values' and t.field_name='ValueText'
								and t.language='{$this->lang}' and t.id=sv.id
							where NameID={$par->id} and ($cats_conds)
							and sv.inactive=0
							order by sv.ordering";

						$this->pars[$k]->values = $this->_dbr->getAssoc($q);
						
						$this->pars[$k]->pics = [];
						foreach ($this->pars[$k]->values as $id => $name)
						{
							$this->pars[$k]->pics[$id] = "/images/cache/{$this->lang}_src_shopparvalue_picid_{$id}_image.jpg";
						}
						
						$this->pars[$k]->values_cnt = count($this->pars[$k]->values);
						$q = "select spv.ValueID as iid, spv.*
							, t.value as value
							from saved_parvalues spv
							left join Shop_Values sv on sv.id=spv.ValueID
							left join translation t on t.table_name='Shop_Values' and t.field_name='ValueText'
								and t.language='{$this->lang}' and t.id=sv.id
							where spv.saved_id=" . $this->id . " and spv.NameID={$par->id}
							and sv.inactive=0
							order by spv.id";
					break;
				}
			} else {
				switch ($par->ValueType) {
					case 'text':
						$q = "select distinct sv.id, sv.ValueText 
							from Shop_Values sv
							join Shop_Value_Cat svc on svc.ValueID=sv.id
							where NameID={$par->id} and ($cats_conds)
							and sv.inactive=0
							order by sv.ordering";

						$this->pars[$k]->values = $this->_dbr->getAssoc($q);
						$this->pars[$k]->values_cnt = count($this->pars[$k]->values);
						if ($this->pars[$k]->values_cnt && $this->pars[$k]->ValueType!='img') $this->pars[$k]->values[''] = 'Free text';
						$qvalue = "IFNULL(sv.ValueText, spv.FreeValueText)";
					break;
					case 'dec':
						$q = "select distinct sv.id, sv.ValueDec as value 
							from Shop_Values sv
							join Shop_Value_Cat svc on svc.ValueID=sv.id
							where NameID={$par->id} and ($cats_conds)
							and sv.inactive=0
							order by sv.ordering";

						$this->pars[$k]->values = $this->_dbr->getAssoc($q);
						$this->pars[$k]->values_cnt = count($this->pars[$k]->values);
						$qvalue = "IFNULL(sv.ValueDec, spv.FreeValueDec)";
					break;
					case 'img':
						$qvalue = "sv.id";
					break;
				}
				$q = "select spv.ValueID as iid, spv.*
					, $qvalue as value
					from saved_parvalues spv
					left join Shop_Values sv on sv.id=spv.ValueID
					where saved_id=". $this->id . " and spv.NameID=$par->id
							and sv.inactive=0
							order by spv.id";
			}
			$this->data[$par->id] = $this->_dbr->getAssoc($q);
		}
	}
	/**
	 * Return all data
	 */
	public function get()
	{
		$result = new stdClass();
		$result->data = $this->data;
		$result->options['pars'] = $this->pars;
		$result->options['sa_names'] = $this->sa_names;
		return $result;
	}
	/**
	 * Save current data
	 */
	public function save()
	{
		foreach ($this->_to_assign as $name_id => $value_ids)
		{
			foreach ($value_ids as $value_id)
			{
                $this->_db->query("insert into saved_parvalues set saved_id=" . $this->id . "
                , `NameID`=" . $name_id  . "
                , `ValueID`= " . (int)$value_id);
			}
		}
		$this->_to_assign = array();
		
		foreach ($this->_to_delete as $name_id => $value_ids)
		{
			foreach ($value_ids as $value_id)
			{
                $this->_db->query("delete from saved_parvalues where saved_id=" . $this->id . "
                and `NameID`=" . $name_id  . "
                and `ValueID`= " . (int)$value_id . "
				limit 1");
			}
		}
		$this->_to_delete = array();
	}
	/**
	 * Set new data 
	 * @var array
	 */
	public function setData($in, $old = null)
	{
		foreach ($in as $name_id => $collection)
		{
			$assigned = array_keys($this->data[$name_id]);
			$new = array_keys($collection);
		
			if (isset($old[$name_id]) && !empty(array_diff($assigned, array_keys($old[$name_id]))))
			{
				throw new Exception(Saved::MESSAGE_DATA_IS_CHANGED);		
			}
			
			$this->_to_assign[$name_id] = array_diff($new, $assigned);
			$this->_to_delete[$name_id] = array_diff($assigned, $new);
			
			$this->data[$name_id] = $collection;
		}
	}
}