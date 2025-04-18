<?php
require_once 'lib/SavedEntity.php';

class SavedRating extends SavedEntity
{
	/**
	 * Return all data
	 */
	public function get()
	{
		$result = new stdClass();
		$result->data = [
			'dont_show' => $this->dont_show,
			'inherited' => $this->inherited
		];
		$result->options = [
			'rows' => $this->rows,
			'avg_rating_code_published' => $this->avg_rating_code_published,
			'avg_rating_code_all' => $this->avg_rating_code_all,
			'avg_real_rating_code_all' => $this->avg_real_rating_code_all,
			'sa_array_shop' => $this->sa_array_shop,
		];
		return $result;
	}
	/**
	 * Loads all catalogue data
	 */ 
	protected function _load()
	{
		$this->dont_show = (int)$this->_dbr->getOne('SELECT `par_value`
			FROM `saved_params`
			WHERE `par_key` = "dont_show"
			AND `saved_id` = ' . $this->id);
			
		$this->inherited = $this->_dbr->getCol('SELECT `par_value`
			FROM `saved_params`
			WHERE `par_key` LIKE "ratings_inherited_from[%"
			AND `saved_id` = ' . $this->id);
		
		$this->rows = $this->_dbr->getAll("select * from (
			select
				af.auction_number
				, af.txnid
				, af.type
				, af.code
				, af.id
				, DATE(af.datetime) `datetime`
				, af.text
				, au_name.value name, au_firstname.value firstname, CONCAT(ROUND(100*af.code/5),'%') perc
				, IF (not au.hiderating and not af.hidden, 1, 0) published
				, 0 custom_id
			from auction_feedback af
			join auction au on af.auction_number=au.auction_number and af.txnid=au.txnid
			join auction_par_varchar au_name on au_name.auction_number=au.auction_number and au_name.txnid=au.txnid
				and au_name.key='name_invoice'
			join auction_par_varchar au_firstname on au_firstname.auction_number=au.auction_number and au_firstname.txnid=au.txnid
				and au_firstname.key='firstname_invoice'
			left join auction subau on subau.main_auction_number=au.auction_number and subau.main_txnid=au.txnid
			where subau.saved_id in ({$this->id})
					union all
			select distinct
			0
			, 0
			, 0
			, rating
			, 0
			, `date`
			, `text`
			, name, '' firstname
			, CONCAT(ROUND(100*rating/5),'%') perc
			, IF (hidden, 0, 1) published
			, scr.id custom_id
			from saved_custom_ratings scr
			where saved_id in ({$this->id}) ) t
			order by datetime desc limit 10");	

		$this->avg_rating_code_published = $this->_dbr->getRow("select AVG(t.code) avg_code, COUNT(*) cnt, CONCAT(ROUND(100*AVG(t.code)/5),'%') perc
			from (
			select af.code
						from auction_feedback af
						join auction au on af.auction_number=au.auction_number and af.txnid=au.txnid
						join customer c on au.customer_id=c.id
						left join auction subau on subau.main_auction_number=au.auction_number and subau.main_txnid=au.txnid
						where not au.hiderating and subau.saved_id in ({$this->id}) and not af.hidden
			union all
			select rating
						from saved_custom_ratings scr
						where saved_id in ({$this->id}) and not hidden) t");

		$this->avg_rating_code_all = $this->_dbr->getRow("select AVG(t.code) avg_code, COUNT(*) cnt, CONCAT(ROUND(100*AVG(t.code)/5),'%') perc
			from (
			select af.code
						from auction_feedback af
						join auction au on af.auction_number=au.auction_number and af.txnid=au.txnid
						join customer c on au.customer_id=c.id
						left join auction subau on subau.main_auction_number=au.auction_number and subau.main_txnid=au.txnid
						where subau.saved_id in ({$this->id})
			union all
			select rating
						from saved_custom_ratings scr
						where saved_id in ({$this->id})) t");
		
		$this->avg_real_rating_code_all = $this->_dbr->getRow("select AVG(t.code) avg_code, COUNT(*) cnt, CONCAT(ROUND(100*AVG(t.code)/5),'%') perc
			from (
			select af.code
						from auction_feedback af
						join auction au on af.auction_number=au.auction_number and af.txnid=au.txnid
						join customer c on au.customer_id=c.id
						left join auction subau on subau.main_auction_number=au.auction_number and subau.main_txnid=au.txnid
						where subau.saved_id in ({$this->id})
						) t");
						
		$this->sa_array_shop = $this->_dbr->getAssoc("SELECT saved_auctions.id, CONCAT(saved_auctions.id,': ',IFNULL(sp_auction_name.par_value,'')) name
				FROM saved_auctions
				left join saved_params sp_offer on saved_auctions.id=sp_offer.saved_id and sp_offer.par_key='offer_id'
				left join saved_params sp_username on saved_auctions.id=sp_username.saved_id and sp_username.par_key='username'
				LEFT JOIN saved_params sp_auction_name ON sp_auction_name.saved_id=saved_auctions.id and sp_auction_name.par_key = 'auction_name'
				left join seller_information si on si.username=sp_username.par_value
				left join seller_channel on seller_channel.id = si.seller_channel_id
				left join offer on offer_id=sp_offer.par_value
				where not IFNULL(saved_auctions.old,0) and si.seller_channel_id = 4
				order by saved_auctions.id");
	}
	/**
	 * Set data
	 */
	public function setData($in, $old = null)
	{
		if (isset($old['data']))
		{
			$assigned = sort($this->inherited);
			$_assigned = sort($old['data']['inherited']);
			$dont_show = (int)$old['data']['dont_show'];
			if ($assigned != $_assigned || $this->dont_show != $dont_show)
			{
				throw new Exception(Saved::MESSAGE_DATA_IS_CHANGED);
			}
		}
		$this->dont_show = $in['data']['dont_show'] == 'true' ? 1 : 0;
		$this->_to_assign = array_diff($in['data']['inherited'], $this->inherited);
		$this->_to_delete = array_diff($this->inherited, $in['data']['inherited']);
	}
	/**
	 * Save current data
	 */
	public function save()
	{
		$exist = $this->_db->getOne("SELECT `id` FROM `saved_params` 
			WHERE `saved_id` = " . $this->id . " AND `par_key` = 'dont_show'");
		if ($exist)
			$this->_db->query("UPDATE `saved_params` SET `par_value` = '" . (int)$this->dont_show . "'
				WHERE `par_key` = 'dont_show'
				AND `saved_id` = " . $this->id . "
				LIMIT 1");
		else 
			$this->_db->query("INSERT INTO `saved_params` SET `par_value` = '" . (int)$this->dont_show . "',
				`par_key` = 'dont_show',
				`saved_id` = " . $this->id);
			
		foreach ($this->_to_assign as $saved_id)
		{
			$this->_db->query("INSERT INTO `saved_params` SET `saved_id` = " . $this->id . "
                , `par_key` = 'ratings_inherited_from[0]'
                , `par_value` = " . (int)$saved_id);
		}
		$this->_to_assign = [];
		
		foreach ($this->_to_delete as $saved_id)
		{
			$this->_db->query("DELETE FROM `saved_params` WHERE `saved_id` = " . $this->id . "
                AND `par_key` = 'ratings_inherited_from[0]'
                AND `par_value` = " . (int)$saved_id ."
				LIMIT 1");
		}
		$this->_to_delete = [];
	}
}