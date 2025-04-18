<?php
require_once 'PEAR.php';

class ShippingPlan
{
    var $data;
    var $_db;
var $_dbr;
    var $_error;
    var $_isNew;

    function ShippingPlan($db, $dbr, $id = 0, $lang='german')
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('ShippingPlan::ShippingPlan expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
        $id = (int)$id;
        if (!$id) {
            $r = $this->_db->query("EXPLAIN shipping_plan");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->countries = array();
            $this->data = new stdClass;
            while ($field = $r->fetchRow()) {
                $field = $field->Field;
                $this->data->$field = '';
            }
            $this->_isNew = true;
        } else {
            $r = $this->_db->query("SELECT * FROM shipping_plan WHERE shipping_plan_id='$id'");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("ShippingPlan::ShippingPlan : record $id does not exist");
                return;
            }
			$this->data->rules_to_confirm = $dbr->getOne("SELECT value
				FROM translation
				WHERE table_name = 'shipping_plan'
				AND field_name = 'rules_to_confirm'
				AND language = '$lang'
				AND id = $id");
			$q = "SELECT
			spc.shipping_plan_country_id f1,
			spc.shipping_plan_id f2, 
			spc.country_code f3,
			spc.shipping_cost f4,
			spc.COD_cost f5,
			scc.real_shipping_cost  f6,
			scc.real_COD_cost  f7,
			spc.island_cost f8,
			scc.real_island_cost f9,
			spc.additional_cost f10,
			scc.real_additional_cost f11,
			spc.diff_shipping_plan_id f12,
			spc.cod_diff_shipping_plan_id f13,
			spc.estimate f14, 
			spc.shipping_plan_country_id f15,
			NULL f16, 
			NULL f17, 
			NULL f18, 
			NULL f19, 
			NULL f20, 
			NULL f21, 
			spc.bonus f22,
			spc.add_percnt f23, 
			spc.real_shipping_cost f24,
			spc.real_COD_cost f25,
			spc.real_island_cost f26,
			spc.real_additional_cost f27,
			sc.curr_code as f28,
			spc.car_free_zone f29
			FROM shipping_plan_country spc
			JOIN shipping_plan sp ON spc.shipping_plan_id=sp.shipping_plan_id 
			LEFT JOIN shipping_cost sc ON sp.shipping_cost_id=sc.id
			LEFT JOIN shipping_cost_country scc ON scc.shipping_cost_id=sc.id
			AND spc.country_code=scc.country_code  
			WHERE spc.shipping_plan_id='$id'";
//			echo $q;
			$this->countries = $this->_dbr->getAssoc($q, false, null, DB_FETCHMODE_ORDERED);
			foreach($this->countries as $k=>$r) {
				$i = 0;
				foreach($r as $fld_name=>$fld_value) {
					$this->countries[$k][$i++] = $fld_value;
				}
			}
//			print_r($this->countries);
            $this->_isNew = false;
        }
    }

    function set($field, $value)
    {
        if (isset($this->data->$field)) {
            $this->data->$field = $value;
        }
    }

    function get($field)
    {
        if (isset($this->data->$field)) {
            return $this->data->$field;
        } else {
            return null;
        }
    }

    function update()
    {
        $query = '';
        if (!is_object($this->data)) {
            $this->_error = PEAR::raiseError('ShippingPlan::update : no data');
        }
        foreach ($this->data as $field => $value) {
            if ($query) {
                $query .= ', ';
            }
            $query .= "`$field`='" . mysql_escape_string($value) . "'";
        }
        if ($this->_isNew) {
            $command = "INSERT INTO";
            $where = '';
        } else {
            $command = "UPDATE";
            $where = "WHERE shipping_plan_id='" . mysql_escape_string($this->data->shipping_plan_id) . "'";
        }
        $r = $this->_db->query("$command shipping_plan SET $query $where");
        if (PEAR::isError($r)) {
            $this->_error = $r;
        }
        if ($this->_isNew) {
            $this->data->shipping_plan_id = mysql_insert_id();
        }
        return $r;
    }

    static function listAll($db, $dbr, $deleted=0)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $list = $dbr->getAll("SELECT * FROM shipping_plan WHERE deleted=$deleted ORDER BY title");
        return $list;
    }

    static function listArray($db, $dbr, $site=77)
    {
        $cur = $dbr->getOne("select distinct value from config_api where par_id=7 and siteid='$site'");
        $ret = array();
        $list = ShippingPlan::listAll($db, $dbr);
        foreach ((array)$list as $plan) {
            if ($plan->curr_code==$cur || !strlen($cur)) { 
			       $ret[$plan->shipping_plan_id] = $plan->title;
		    }   
        }
        return $ret;
    }

	function delete()
    {
     	$this->data->deleted = $this->data->deleted ? 0 : 1;
     	$this->update();
     	return;
        $r = $this->_db->query("DELETE FROM shipping_plan_country WHERE shipping_plan_id=".$this->data->shipping_plan_id);
        if (PEAR::isError($r)) {
            $this->_error = $r;
			print_r($this->_error);
            return;
        }
        $r = $this->_db->query("UPDATE offer SET shipping_plan_id=0 WHERE shipping_plan_id=".$this->data->shipping_plan_id);
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        $r = $this->_db->query("DELETE FROM shipping_plan WHERE shipping_plan_id=".$this->data->shipping_plan_id);
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
    }
    function validate(&$errors)
    {
        $errors = array();
        if (empty($this->data->title)) {
            $errors[] = 'Title is required';
        }
        if (strlen($this->data->description)>255) {
            $errors[] = 'Description length should be less than 255 symbols';
        }
        return !count($errors);
    }
	static function addCountry($db, $dbr, 
		$shipping_plan_id, 
		$country_code, 
		$estimate_cost,
		$shipping_cost, 
		$real_shipping_cost, 
		$diff_shipping_plan_id,
		$COD_cost, 
		$real_COD_cost,
		$cod_diff_shipping_plan_id,
		$island_cost, 
		$real_island_cost,
        $car_free_zone,
		$additional_cost, 
		$real_additional_cost, 
		$bonus, $add_percnt
		) {
        $shipping_plan_id = (int)$shipping_plan_id;
        $country_code = mysql_escape_string($country_code);
        $estimate_cost = mysql_escape_string($estimate_cost);
        $shipping_cost = mysql_escape_string($shipping_cost);
        $real_shipping_cost = mysql_escape_string($real_shipping_cost);
        $diff_shipping_plan_id = (int)$diff_shipping_plan_id;
        $COD_cost = mysql_escape_string($COD_cost);
        $real_COD_cost = mysql_escape_string($real_COD_cost);
        $cod_diff_shipping_plan_id = (int)$cod_diff_shipping_plan_id;
        $island_cost = mysql_escape_string($island_cost);
        $real_island_cost = mysql_escape_string($real_island_cost);
        $additional_cost = mysql_escape_string($additional_cost);
        $real_additional_cost = mysql_escape_string($real_additional_cost);
        $bonus = mysql_escape_string($bonus);
	$add_percnt = mysql_escape_string($add_percnt);
		$r = $db->query("INSERT INTO shipping_plan_country SET shipping_plan_id=$shipping_plan_id, country_code='$country_code', 
		estimate='$estimate_cost',
		shipping_cost='$shipping_cost', 
		real_shipping_cost='$real_shipping_cost', 
		COD_cost='$COD_cost',
		real_COD_cost='$real_COD_cost',
		island_cost='$island_cost',
		real_island_cost='$real_island_cost',
		car_free_zone='$car_free_zone',
		additional_cost='$additional_cost',
		real_additional_cost='$real_additional_cost',
		bonus='$bonus',
		add_percnt='$add_percnt'
		");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            print_r($r);
            return;
        }
		if (strlen($diff_shipping_plan_id) && $diff_shipping_plan_id) {
			if (!ShippingPlan::inChain($db, $dbr, $diff_shipping_plan_id, $country_code, $shipping_plan_id, 'diff_shipping_plan_id')) {
		        $r = $db->query("UPDATE shipping_plan_country SET 
				diff_shipping_plan_id=$diff_shipping_plan_id
				WHERE shipping_plan_id=$shipping_plan_id AND country_code='$country_code'
				");
		        if (PEAR::isError($r)) {
		            $this->_error = $r;
		            print_r($r);
		            return;
		        }
			}
		} else {	
		        $r = $db->query("UPDATE shipping_plan_country SET 
				diff_shipping_plan_id=$diff_shipping_plan_id
				WHERE shipping_plan_id=$shipping_plan_id AND country_code='$country_code'
				");
		        if (PEAR::isError($r)) {
		            $this->_error = $r;
		            print_r($r);
		            return;
		        }
		}		
		if (strlen($cod_diff_shipping_plan_id) && $cod_diff_shipping_plan_id) {
			if (!ShippingPlan::inChain($db, $dbr, $cod_diff_shipping_plan_id, $country_code, $shipping_plan_id, 'cod_diff_shipping_plan_id')) {
		        $r = $db->query("UPDATE shipping_plan_country SET 
				cod_diff_shipping_plan_id=$cod_diff_shipping_plan_id
				WHERE shipping_plan_id=$shipping_plan_id AND country_code='$country_code'
				");
		        if (PEAR::isError($r)) {
		            $this->_error = $r;
		            print_r($r);
		            return;
		        }
			}	
		} else {	
		        $r = $db->query("UPDATE shipping_plan_country SET 
				cod_diff_shipping_plan_id=$cod_diff_shipping_plan_id
				WHERE shipping_plan_id=$shipping_plan_id AND country_code='$country_code'
				");
		        if (PEAR::isError($r)) {
		            $this->_error = $r;
		            print_r($r);
		            return;
		        }
		}		
    }

    static function updateCountry($db, $dbr, $id, 
		$estimate_cost,
		$shipping_cost, 
		$real_shipping_cost, 
		$diff_shipping_plan_id,
		$COD_cost, 
		$real_COD_cost,
		$cod_diff_shipping_plan_id,
		$island_cost, 
		$real_island_cost,
        $car_free_zone,
		$additional_cost, 
		$real_additional_cost, 
		$bonus, $add_percnt
		) {
        $id = (int)$id;
        $estimate_cost = mysql_escape_string($estimate_cost);
        $shipping_cost = mysql_escape_string($shipping_cost);
        $real_shipping_cost = mysql_escape_string($real_shipping_cost);
        $diff_shipping_plan_id = (int)$diff_shipping_plan_id;
        $COD_cost = mysql_escape_string($COD_cost);
        $real_COD_cost = mysql_escape_string($real_COD_cost);
        $cod_diff_shipping_plan_id = (int)$cod_diff_shipping_plan_id;
        $island_cost = mysql_escape_string($island_cost);
        $real_island_cost = mysql_escape_string($real_island_cost);
        $additional_cost = mysql_escape_string($additional_cost);
        $real_additional_cost = mysql_escape_string($real_additional_cost);
        $bonus = mysql_escape_string($bonus);
	$add_percnt = mysql_escape_string($add_percnt);
        $r = $db->query("UPDATE shipping_plan_country SET 
		estimate='$estimate_cost',
		shipping_cost='$shipping_cost', 
		real_shipping_cost='$real_shipping_cost', 
		COD_cost='$COD_cost',
		real_COD_cost='$real_COD_cost',
		island_cost='$island_cost',
		real_island_cost='$real_island_cost',
		car_free_zone='$car_free_zone',
		additional_cost='$additional_cost',
		real_additional_cost='$real_additional_cost',
		bonus='$bonus',
		add_percnt='$add_percnt'
		WHERE shipping_plan_country_id=$id
		");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
		$country_code = $dbr->getOne("select country_code from shipping_plan_country where shipping_plan_country_id=$id");
		$shipping_plan_id = $dbr->getOne("select shipping_plan_id from shipping_plan_country where shipping_plan_country_id=$id");
		if (strlen($diff_shipping_plan_id) && $diff_shipping_plan_id) {
			if (!ShippingPlan::inChain($db, $dbr, $diff_shipping_plan_id, $country_code, $shipping_plan_id, 'diff_shipping_plan_id')) {
		        $r = $db->query("UPDATE shipping_plan_country SET 
				diff_shipping_plan_id=$diff_shipping_plan_id
				WHERE shipping_plan_id=$shipping_plan_id AND country_code='$country_code'
				");
		        if (PEAR::isError($r)) {
		            $this->_error = $r;
		            print_r($r);
		            return;
		        }
			}
		} else {	
		        $r = $db->query("UPDATE shipping_plan_country SET 
				diff_shipping_plan_id=$diff_shipping_plan_id
				WHERE shipping_plan_id=$shipping_plan_id AND country_code='$country_code'
				");
		        if (PEAR::isError($r)) {
		            print_r($r);
		            return;
		        }
		}		
		if (strlen($cod_diff_shipping_plan_id) && $cod_diff_shipping_plan_id) {
			if (!ShippingPlan::inChain($db, $dbr, $cod_diff_shipping_plan_id, $country_code, $shipping_plan_id, 'cod_diff_shipping_plan_id')) {
		        $r = $db->query("UPDATE shipping_plan_country SET 
				cod_diff_shipping_plan_id=$cod_diff_shipping_plan_id
				WHERE shipping_plan_id=$shipping_plan_id AND country_code='$country_code'
				");
		        if (PEAR::isError($r)) {
		            $this->_error = $r;
		            print_r($r);
		            return;
		        }
			}	
		} else {	
		        $r = $db->query("UPDATE shipping_plan_country SET 
				cod_diff_shipping_plan_id=$cod_diff_shipping_plan_id
				WHERE shipping_plan_id=$shipping_plan_id AND country_code='$country_code'
				");
		        if (PEAR::isError($r)) {
		            $this->_error = $r;
		            print_r($r);
		            return;
		        }
		}		
    }
	static function deleteCountry($db, $dbr, $id) {
        $id = (int)$id;
        $r = $db->query("DELETE FROM shipping_plan_country WHERE shipping_plan_country_id=$id");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
    }
	static function count_offers($db, $dbr, $id) {
        $id = (int)$id;
/*		$r = $dbr->getOne("SELECT COUNT(*) FROM (
		select * from 
		(select distinct value from  
		translation where table_name='offer' and field_name='shipping_plan_id' 
		WHERE id=$id and not hidden)");*/
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
		return $r;
    }
	static function count_articles($db, $dbr, $id) {
        $id = (int)$id;
		$r = $dbr->getOne("select count(*) from 
			(SELECT distinct article_id FROM offer o join offer_group og on o.offer_id=og.offer_id
				join article_list al on al.group_id=og.offer_group_id
				WHERE shipping_plan_id=$id and not hidden) a");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
		return $r;
    }
	static function getCostsByCountry($db, $dbr, $id, $country_code) {
        $id = (int)$id;
        $country_code = mysql_escape_string($country_code);
		$q = "SELECT 
				estimate,
				shipping_cost, 
				real_shipping_cost, 
				COD_cost, 
				real_COD_cost, 
				island_cost, 
				real_island_cost, 
				diff_shipping_plan_id, 
				cod_diff_shipping_plan_id
				FROM shipping_plan_country WHERE shipping_plan_id='$id' AND country_code='$country_code'";
		$r = $db->query($q);

        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        $list = array();
        while ($p = $r->fetchRow()) {
            $list[] = $p;
        }
        return $list[0];
    }

    function duplicate()
    {
        $command = "INSERT INTO shipping_plan SET ";
        $query = "";
        
        foreach ($this->data as $field => $value) {
            if ($field == 'shipping_plan_id') {
                continue;
            }
            if ($query) {
                $query .= ', ';
            }
            $query .= "`$field`='" . mysql_escape_string($value) . "'";
        }
        $this->_db->query($command . $query);
        $newid = mysql_insert_id();

        $countries = $this->_dbr->getAll ("SELECT * FROM shipping_plan_country WHERE shipping_plan_id = ".$this->get('shipping_plan_id'));
        foreach ($countries as $country) {
//		print_r($country);
			ShippingPlan::addCountry($this->_db, $this->_dbr, $newid, 
			$country->country_code, 
			$country->estimate,
			$country->shipping_cost, 
			$country->real_shipping_cost, 
			$country->diff_shipping_plan_id,
			$country->COD_cost, 
			$country->real_COD_cost,
			$country->cod_diff_shipping_plan_id,
			$country->island_cost, 
			$country->real_island_cost,
            $country->car_free_zone,
			$country->additional_cost, 
			$country->real_additional_cost,
			$country->bonus, $country->add_percnt
			) ;
        }
//		die();
        return $newid;
    }

    static function inChain($db, $dbr, $shipping_plan_id, $country_code, $diff_shipping_plan_id, $mode='diff_shipping_plan_id') {
    	     return false;
/*		$here = new ShippingPlan($db, $dbr, $shipping_plan_id);
		foreach ($here->countries as $key=>$row) {
			if ($row[1]==$country_code) {
				$here_country = $row;
				break;
			}
		}
		if ($shipping_plan_id==$diff_shipping_plan_id) return true;
		if ($mode=='diff_shipping_plan_id') {
			if (!$here_country[10]) return false;
			return ShippingPlan::inChain($db, $dbr, $here_country[10], $country_code, $diff_shipping_plan_id, $mode);
		}
		elseif ($mode=='cod_diff_shipping_plan_id') {
			if (!$here_country[11]) return false;
			return ShippingPlan::inChain($db, $dbr, $here_country[11], $country_code, $diff_shipping_plan_id, $mode);
		}*/
	}	

    static function findLeaf($db, $dbr, $shipping_plan_id, $country_code, $mode='diff_shipping_plan_id') {
    	     $here = new ShippingPlan($db, $dbr, $shipping_plan_id);
		if (count($here->countries)) foreach ($here->countries as $key=>$row) {
			if ($row[1]==$country_code) {
				$here_country = $row;
				break;
			}
		} else return;
		if ($mode=='diff_shipping_plan_id') {
			return $here_country[10] ? $here_country[10] : $here->get('shipping_cost_id');
		}
		elseif ($mode=='cod_diff_shipping_plan_id') {
			return $here_country[11] ? $here_country[11] : $here->get('shipping_cost_id');
		}	
/*		$here = new ShippingPlan($db, $dbr, $shipping_plan_id);
		foreach ($here->countries as $key=>$row) {
			if ($row[1]==$country_code) {
				$here_country = $row;
				break;
			}
		}
		if ($mode=='diff_shipping_plan_id') {
			if (!$here_country[10]) return $shipping_plan_id;
			return ShippingPlan::findLeaf($db, $dbr, $here_country[10], $country_code, $mode);
		}
		elseif ($mode=='cod_diff_shipping_plan_id') {
			if (!$here_country[11]) return $shipping_plan_id;
			return ShippingPlan::findLeaf($db, $dbr, $here_country[11], $country_code, $mode);
		}*/
	}	
	function inactive()
    {
     	$this->data->inactive = $this->data->inactive ? 0 : 1;
     	$this->update();
     	return;
    }
    /**
     * Move Offers from $from_id shipping plan to $to_id
     * @param int $from_id
     * @param int $to_id
     */
    static function moveOffersTo($from_id, $to_id) {
        $from_id = (int)$from_id;
        $to_id = (int)$to_id;
        
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        
        $offers = [];        
        $shipping_plan_fields = ['shipping_plan_id', 'fshipping_plan_id', 'sshipping_plan_id', 'ashipping_plan_id'];
        foreach ($shipping_plan_fields as $shipping_plan_field) {
            $q = "select offer.offer_id, translation.language from translation 
                join offer on translation.id = offer.offer_id
                where translation.table_name = 'offer' 
                and translation.field_name = '$shipping_plan_field' 
                and translation.value = $from_id
                and offer.hidden = 0
                and offer.old = 0";
            $list = $dbr->getAll($q);
            foreach ($list as $row) {
                $offers[$row->offer_id][$shipping_plan_field][] = $row->language;
            }
        }
        
        foreach ($offers as $offer_id => $plans) {
            $offer = new Offer($db, $dbr, $offer_id);
            $new_offer_id = $offer->separate();
            $offer->set('offer_id', $new_offer_id);
            $offer->update();
            
            foreach ($plans as $shipping_plan_field => $countries) {
                foreach ($countries as $country_id) {
                    $value = mysql_escape_string($value);
                    $q = "REPLACE INTO translation set value='$to_id'
                        ,id = $new_offer_id
                       , table_name = 'offer' 
                       , field_name = '$shipping_plan_field'
                       , language = '$country_id'";
                    $r = $db->query($q);
                }
            }
        }
    }
    /**
     * Move Articles from $from_id shipping plan to $to_id
     * @param int $from_id
     * @param int $to_id
     */
    static function moveArticlesTo($from_id, $to_id) {
        $from_id = (int)$from_id;
        $to_id = (int)$to_id;
        
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        
        $q = "select a.article_id,  t.language
			FROM article a
			join translation t on t.table_name='article' 
				and t.field_name='shipping_plan_id' 
				and t.id = a.article_id 
				and t.value='$from_id'
			WHERE a.admin_id=0 
				and a.article_id is not null
				AND NOT a.deleted";
        $list = $dbr->getAll($q);
        
        foreach ($list as $row) {
            $r = $db->query("REPLACE INTO translation set value='$to_id'
                , id = '" . $row->article_id . "' 
                , table_name = 'article' 
                , field_name = 'shipping_plan_id'
                , language = '" . $row->language . "'");
        }
    }
}
?>