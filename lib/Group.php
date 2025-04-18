<?php
/**
 * Article group
 * @package eBay_After_Sale
 */
/**
 * @ignore
 */
require_once 'PEAR.php';

/**
 * Article group
 * @package eBay_After_Sale
 */
class Group
{
    /**
    * Holds data record
    * @var object
    */
    var $data;
    /**
    * Reference to database
    * @var object
    */
    var $_db;
var $_dbr;
    /**
    * Error, if any
    * @var object
    */
    var $_error;
    /**
    * True if object represents a new account being created
    * @var boolean
    */
    var $_isNew;

    /**
    * @return Group
    * @param object $db
    * @param object $offer
    * @param int $id
    * @desc Constructor
    */
    function Group($db, $dbr, $offer, $id = 0, $country_code='', $siteid='77')
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Group::Group expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;$this->_dbr = $dbr;
        $id = (int)$id;
        $this->offer_id = (int)$offer->data->offer_id;
        if (!$id) {
            $r = $this->_db->query("EXPLAIN offer_group");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = new stdClass;
            while ($field = $r->fetchRow()) {
                $field = $field->Field;
                $this->data->$field = '';
            }
            $this->articles = array();
            $this->_isNew = true;
        } else {
			/*if ($offer) */{
	            $r = $this->_db->query("SELECT * FROM offer_group WHERE offer_group_id=$id AND offer_id=$this->offer_id");
	            if (PEAR::isError($r)) {
	                $this->_error = $r;
					aprint_r($r); die();
	                return;
	            }
	            $this->data = $r->fetchRow();
	            if (!$this->data) {
		            $r = $this->_db->query("SELECT * FROM offer_group WHERE copy_from_offer_group_id=$id AND offer_id=$this->offer_id");
		            if (PEAR::isError($r)) {
		                $this->_error = $r;
					aprint_r($r); die();
		                return;
		            }
		            $this->data = $r->fetchRow();
		            if (!$this->data) {
		                $this->_error = PEAR::raiseError("Group::Group : record $id does not exist");
					aprint_r($this->data); die();
		                return;
		            }
	            }
	            $this->articles = Group::getArticles($db, $dbr, $id, $country_code, 'german', $siteid);
	            $this->_isNew = false;
			}
        }
    }

    /**
    * @return void
    * @param string $field
    * @param mixed $value
    * @desc Set field value
    */
    function set($field, $value)
    {
        if (isset($this->data->$field)) {
            $this->data->$field = $value;
        }
    }

    /**
    * @return string
    * @param string $field
    * @desc Get field value
    */
    function get($field)
    {
        if (isset($this->data->$field)) {
            return $this->data->$field;
        } else {
            return null;
        }
    }

    /**
    * @return bool|object
    * @desc Update record
    */
    function update()
    {
        $query = '';
        if (!is_object($this->data)) {
            $this->_error = PEAR::raiseError('Offer::update : no data');
        }
        if ($this->_isNew) {
            $r = $this->_db->query("SELECT MAX(position) as pos FROM offer_group where offer_id=".$this->offer_id);
            $r = $r->fetchRow();
            $this->data->offer_group_id = '';
            $this->data->position = $r->pos+1;
        }
        $this->data->offer_id = $this->offer_id;
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
            $where = "WHERE offer_group_id='" . mysql_escape_string($this->data->offer_group_id) . "'";
        }
        $r = $this->_db->query("$command offer_group SET $query $where");
//		echo "$command offer_group SET $query $where<br>";
        if (PEAR::isError($r)) {
            $this->_error = $r;
			aprint_r($r); die();
        }
        if ($this->_isNew) {
            $this->data->offer_group_id = mysql_insert_id();
        }
        return $r;
    }
    /**
    * @return void
    * @param object $db
    * @param object $group
    * @desc Delete group in an offer
    */
	function delete(){
        if (!is_object($this->data)) {
            $this->_error = PEAR::raiseError('Offer::update : no data');
        }
		$group_id = (int)$this->data->offer_group_id;
		$r = $this->_db->query("DELETE FROM article_list WHERE group_id=$group_id");
        if (PEAR::isError($r)) {
			$msg = $r->getMessage();
			echo($msg);
			adminEmail($msg);
            $this->_error = $r;
        }
        $r = $this->_db->query("DELETE FROM offer_group WHERE offer_group_id=$group_id");
        if (PEAR::isError($r)) {
            $msg = $r->getMessage();
            echo($msg);
            adminEmail($msg);
            $this->_error = $r;
        }
    }



    /**
    * @return array
    * @param object $db
    * @param object $offer
    * @desc Get all groups in an offer
    */
    static function listAll($db, $dbr, $offer, $country_code='', $lang='german', $siteid='77', $type='', $defcountry_code='', $simple=0, $singlegroup=0, $ask4alias=0, $seller_username='')
    {
#		echo 'Group::listAll:'.$defcountry_code.'<br>';
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $offer_id = (int)$offer->data->offer_id;
		if ($offer_id) {
			$offer_where = " offer_group.offer_id=$offer_id and not IFNULL(b.deleted,0)";
			$orderby = " ORDER BY offer_group.position, offer_group.offer_group_id";
		} else  {
			$offer_where = "offer_group.offer_id=0";
			$orderby = " ORDER BY offer_group.title";
		}
        $basegroups = 1;
        if($seller_username){
            $basegroups = $dbr->getOne("select basegroups from seller_information where username='$seller_username'");
        }
		$q = "SELECT IFNULL(b.title, offer_group.title) title, 
			offer_group.deleted,
			offer_group.base_group_inactive,
			offer_group.position,
			offer_group.offer_group_id,
			offer_group.offer_id,
			IFNULL(b.main, offer_group.main) main,
			IFNULL(b.additional, offer_group.additional) additional,
			IFNULL(b.noshipping, offer_group.noshipping) noshipping,
			IFNULL(b.noshipping_same, offer_group.noshipping_same) noshipping_same,
			offer_group.base_group_id,
				IFNULL((SELECT value
				FROM translation
				WHERE table_name = 'offer_group'
				AND field_name = 'title'
				AND language = '$lang'
				AND id = IFNULL(b.offer_group_id, offer_group.offer_group_id) limit 1), offer_group.title) as translated_title,
				IFNULL((SELECT value
				FROM translation
				WHERE table_name = 'offer_group'
				AND field_name = 'description'
				AND language = '$lang'
				AND id = IFNULL(b.offer_group_id, offer_group.offer_group_id) limit 1), offer_group.description) as translated_description,
				(SELECT value
				FROM translation
				WHERE table_name = 'offer_group'
				AND field_name = 'subtitle'
				AND language = '$lang'
				AND id = IFNULL(b.offer_group_id, offer_group.offer_group_id) limit 1) as translated_subtitle
				, IF(offer_group.additional, 'This group contains additional items', 
					IF(offer_group.main, 'Price of items in this group will equal to winning bid', 
					'Item included to winning bid')) type
		FROM offer_group 
		left join offer_group b on offer_group.base_group_id=b.offer_group_id and b.offer_id=0
		WHERE $offer_where
		".($singlegroup?" and offer_group.offer_group_id=$singlegroup ":'')."
		" . ($basegroups ? '' : " and not offer_group.base_group_id ") . "
		$orderby";
//	echo $q.'<br>';
        $r = $db->query($q);
        $list = array();
        if (PEAR::isError($r)) {
            aprint_r($r); echo 'GROUP ERROR: '.nl2br($q).'<br>';
        } else {
	        while ($group = $r->fetchRow()) {
	            if (!$simple) $group->articles = Group::getArticles($db, $dbr, $group->offer_group_id, $country_code, $lang, $siteid, $type, $defcountry_code, $ask4alias);
	            $list[] = $group;
	        }
		}
        return $list;
    }

    /**
    * @return unknown
    * @param unknown $db
    * @param unknown $offer
    * @desc Get all groups in an offer as array suitable
    * for use with Smarty's {html_optios}
    */
    static function listArray($db, $dbr, $offer)
    {
        $ret = array();
        $list = Group::listAll($db, $dbr, $offer);
        foreach ((array)$list as $group) {
            if (!$group->deleted) $ret[$group->offer_group_id] = $group->title;
        }
        return $ret;
    }

    /**
    * @return void
    * @param object $db
    * @param object $offer
    * @param int $id
    * @param int $updown
    * @desc Move group up or down in display order
    */
    static function moveUpDown($db, $dbr, $offer, $id, $updown)
    {
        $offer = $offer->get('offer_id');
        $thispos = $dbr->getOne('SELECT position FROM offer_group WHERE offer_group_id='.$id);
        if ($updown < 0) {
            $previd = $dbr->getOne(
                "SELECT offer_group_id 
                 FROM offer_group 
                 WHERE position<$thispos AND offer_id=$offer 
                 ORDER BY position DESC 
                 LIMIT 1
			");
/*			echo                 "SELECT offer_group_id 
                 FROM offer_group 
                 WHERE position<$thispos AND offer_id=$offer 
                 ORDER BY position DESC 
                 LIMIT 1
			";*/
            $db->query("UPDATE offer_group SET position=position+1 WHERE offer_group_id =$previd");
//			echo "UPDATE offer_group SET position=position+1 WHERE offer_group_id =$previd";
            $db->query("UPDATE offer_group SET position=position-1 WHERE offer_group_id =$id");
//			echo "UPDATE offer_group SET position=position-1 WHERE offer_group_id =$id";
        } else {
            $nextid = $dbr->getOne(
                "SELECT offer_group_id 
                 FROM offer_group 
                 WHERE position>$thispos AND offer_id=$offer 
                 ORDER BY position
                 LIMIT 1
			");
            $db->query("UPDATE offer_group SET position=position-1 WHERE offer_group_id =$nextid");
            $db->query("UPDATE offer_group SET position=position+1 WHERE offer_group_id =$id");

        }
            $r = $dbr->getOne("SELECT MIN(position) as pos FROM offer_group where offer_id=$offer");
            $db->query("UPDATE offer_group SET position=position-$r where offer_id=$offer");
        return;
        $list = Group::listAll($db, $dbr, $offer);
        if (is_array($list)) {
            $tochange = false;
            foreach ($list as $group) {
                if ($group->offer_group_id == $id && $tochange && $updown<0) {
                    $db->query("UPDATE offer_group SET position = position+1 where offer_group_id = $tochange");
                    $db->query("UPDATE offer_group SET position = position-1 where offer_group_id = $id");
                    break;
                } elseif ($group->offer_group_id == $id && $updown>0) {
                    $tochange = true;
                } elseif ($updown>0 && $tochange) {
                    $db->query("UPDATE offer_group SET position = position-1 where offer_group_id = $group->offer_group_id");
                    $db->query("UPDATE offer_group SET position = position+1 where offer_group_id = $id");
                    break;
                } elseif ($updown<0) {
                    $tochange = $group->offer_group_id;
                }
            }
            $r = $db->query("SELECT MIN(position) as pos FROM offer_group");
            $r = $r->fetchRow();
            $db->query("UPDATE offer_group SET position=position-$r->pos");
        }
    }

    /**
    * @return bool
    * @param array $errors
    * @desc Validate record
    */
    function validate(&$errors)
    {
        $errors = array();
        if (empty($this->data->title)) {
//            $errors[] = 'Title is required';
        }
        return !count($errors);
    }

    /**
    * @return unknown
    * @param object $db
    * @param int $id
    * @desc Get array of all articles in a group
    */
// 20050607 {
    static function getArticles($db, $dbr, $id, $country_code='', $lang='german', $siteid='77', $type='', $defcountry_code='', $ask4alias=0)
    {
#		echo 'Group::getArticles:'.$defcountry_code.'<br>';
		if (strlen($defcountry_code)) $def=1; else $def=0;
		if (strlen($country_code)) {
			$q="SELECT 
			IFNULL((SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = '$lang'
				AND id = a.article_id), a.name) as translated_name,
			IFNULL((SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'description'
				AND language = '$lang'
				AND id = a.article_id), a.description) as translated_description,
			IFNULL((SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'add_item_cost'
				AND language = '$siteid'
				AND id = a.article_id), a.add_item_cost) as article_price,
			IFNULL((SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'high_price'
				AND language = '$siteid'
				AND id = a.article_id), a.high_price) as high_price,
			IFNULL((SELECT value
				FROM translation
				WHERE table_name = 'article_list'
				AND field_name = 'article_price'
				AND language = '$siteid'
				AND id = al.article_list_id), al.article_price) as additional_item_cost,
			IFNULL((SELECT value
				FROM translation
				WHERE table_name = 'article_list'
				AND field_name = 'additional_shipping_cost'
				AND language = '$siteid'
				AND id = al.article_list_id), al.article_price) as additional_shipping_cost,
			(SELECT value
				FROM translation
				WHERE table_name = 'article_list'
				AND field_name = 'subtitle'
				AND language = '$siteid'
				AND id = al.article_list_id) as subtitle,
			al.article_list_id, 
			al.group_id, 
			al.inactive, 
			al.article_id, 
			a.picture_URL, 
			a.wpicture_URL, 
			IF(a.picture_URL like '%pic_id%', substring_index(a.picture_URL,'pic_id=',-1)
				, REPLACE(substring_index(a.picture_URL,'picid_',-1),'_image.jpg','')) pic_id,
			IF(a.wpicture_URL like '%wpic_id%', substring_index(a.wpicture_URL,'wpic_id=',-1)
				, REPLACE(substring_index(a.wpicture_URL,'picid_',-1),'_image.jpg','')) wpic_id,
#			substring_index(a.picture_URL,'pic_id=',-1) pic_id, 
			al.position, 
			IFNULL(spco.shipping_cost, 0)
				-IF($def*IFNULL(defspco.shipping_cost,0)>IFNULL(spco.shipping_cost, 0),
					IFNULL(spco.shipping_cost, 0),
					$def*IFNULL(defspco.shipping_cost,0)) as shipping_cost, 
			IFNULL(spco.real_shipping_cost, 0) as real_shipping_cost, 
			IFNULL(spco.island_cost, 0) as island_cost, 
			IFNULL(spco.real_island_cost, 0)-$def*IFNULL(defspco.real_island_cost,0) as real_island_cost, 
			IFNULL(spca.shipping_cost, 0) as article_shipping_cost, 
			IFNULL(spca.additional_cost, 0) as additional_cost, 
			IFNULL(spca.real_additional_cost, 0) as real_additional_cost, 
			al.default_quantity, 
			al.show_overstocked,
			al.noship,
			IFNULL(bog.additional, og.additional) additional,
			IFNULL(bog.main, og.main) main,
			al.alias_id
			, t_alias_name.value alias_name
			, t_alias_description.value alias_description
		FROM offer_group og 
				LEFT JOIN offer_group bog ON bog.offer_group_id = og.base_group_id  and bog.offer_id=0
				JOIN article_list al ON IFNULL(bog.offer_group_id, og.offer_group_id) = al.group_id
				JOIN article a ON al.article_id = a.article_id and a.admin_id=0
				LEFT JOIN offer o ON og.offer_id = o.offer_id
				LEFT JOIN shipping_plan_country spco ON (
				     IFNULL((SELECT value
				     FROM translation
				     WHERE table_name = 'offer'
				     AND field_name = '".$type."shipping_plan_id'
				     AND language = '$siteid'
				     AND id = o.offer_id), o.shipping_plan_id))
				=spco.shipping_plan_id  
					and spco.country_code = '".$country_code."'
				LEFT JOIN shipping_plan_country defspco ON (
				     IFNULL((SELECT value
				     FROM translation
				     WHERE table_name = 'offer'
				     AND field_name = '".$type."shipping_plan_id'
				     AND language = '$siteid'
				     AND id = o.offer_id), o.shipping_plan_id))
				=defspco.shipping_plan_id  
					and defspco.country_code = '".$defcountry_code."'
				LEFT JOIN shipping_plan_country spca ON (IFNULL((SELECT value
				     FROM translation
				     WHERE table_name = 'article'
				     AND field_name = 'shipping_plan_id'
				     AND language = '$siteid'
				     AND id = a.article_id), a.shipping_plan_id))
				=spca.shipping_plan_id  
					and spca.country_code = '".$country_code."'
			left join translation t_alias_name on t_alias_name.table_name = 'article_alias'
				AND t_alias_name.field_name = 'name'
				AND t_alias_name.language = '$lang'
				AND t_alias_name.id = al.alias_id
			left join translation t_alias_description on t_alias_description.table_name = 'article_alias'
				AND t_alias_description.field_name = 'description'
				AND t_alias_description.language = '$lang'
				AND t_alias_description.id = al.alias_id
			WHERE og.offer_group_id = ".$id."
			ORDER BY al.position";
		} else {
	        $q = "SELECT 
			IFNULL((SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = '$lang'
				AND id = a.article_id), a.name) as translated_name,
			IFNULL((SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'description'
				AND language = '$lang'
				AND id = a.article_id), a.description) as translated_description,
			IFNULL((SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'add_item_cost'
				AND language = '$siteid'
				AND id = a.article_id), a.add_item_cost) as article_price,
			IFNULL((SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'high_price'
				AND language = '$siteid'
				AND id = a.article_id), a.high_price) as high_price,
			IFNULL((SELECT value
				FROM translation  
				WHERE table_name = 'article_list'
				AND field_name = 'article_price'
				AND language = '$siteid'
				AND id = al.article_list_id), al.article_price) as additional_item_cost,
			IFNULL((SELECT value
				FROM translation 
				WHERE table_name = 'article_list'
				AND field_name = 'additional_shipping_cost'
				AND language = '$siteid'
				AND id = al.article_list_id), al.article_price) as additional_shipping_cost,
			al.article_list_id, 
			al.group_id, 
			al.inactive, 
			al.article_id, 
			al.position, 
			IFNULL(spco.shipping_cost, 0) as shipping_cost, 
			0 as real_shipping_cost, 
			0 as island_cost, 
			0 as real_island_cost, 
			IFNULL(spca.shipping_cost, 0) as article_shipping_cost, 
			0 as additional_cost, 
			0 as real_additional_cost, 
			al.default_quantity, 
			al.show_overstocked,
			al.noship,
			IFNULL(bog.additional, og.additional) additional,
			IFNULL(bog.main, og.main) main,
			a.picture_URL, 
			a.wpicture_URL, 
			IF(a.picture_URL like '%pic_id%', substring_index(a.picture_URL,'pic_id=',-1)
				, REPLACE(substring_index(a.picture_URL,'picid_',-1),'_image.jpg','')) pic_id,
			IF(a.wpicture_URL like '%wpic_id%', substring_index(a.wpicture_URL,'wpic_id=',-1)
				, REPLACE(substring_index(a.wpicture_URL,'picid_',-1),'_image.jpg','')) wpic_id,
#			substring_index(a.picture_URL,'pic_id=',-1) pic_id, 
			al.alias_id
			, t_alias_name.value alias_name
			, t_alias_description.value alias_description
		FROM offer_group og 
				LEFT JOIN offer_group bog ON bog.offer_group_id = og.base_group_id and bog.offer_id=0
				JOIN article_list al ON IFNULL(bog.offer_group_id, og.offer_group_id) = al.group_id
				JOIN article a ON al.article_id = a.article_id and a.admin_id=0
				LEFT JOIN offer o ON og.offer_id = o.offer_id
				LEFT JOIN shipping_plan_country spco ON (
				     IFNULL((SELECT value
				     FROM translation
				     WHERE table_name = 'offer'
				     AND field_name = '".$type."shipping_plan_id'
				     AND language = '$siteid'
				     AND id = a.article_id), o.shipping_plan_id))
				=spco.shipping_plan_id  
					and spco.country_code = 'DE'
				LEFT JOIN shipping_plan_country spca ON (
				     IFNULL((SELECT value
				     FROM translation
				     WHERE table_name = 'article'
				     AND field_name = 'shipping_plan_id'
				     AND language = '$siteid'
				     AND id = a.article_id), a.shipping_plan_id))
				=spca.shipping_plan_id  
					and spca.country_code = 'DE'
			left join translation t_alias_name on t_alias_name.table_name = 'article_alias'
				AND t_alias_name.field_name = 'name'
				AND t_alias_name.language = '$lang'
				AND t_alias_name.id = al.alias_id
			left join translation t_alias_description on t_alias_description.table_name = 'article_alias'
				AND t_alias_description.field_name = 'description'
				AND t_alias_description.language = '$lang'
				AND t_alias_description.id = al.alias_id
			WHERE og.offer_group_id = ".$id."
			ORDER BY al.position";
		}
//		echo "q: " . $q . "<br /><br />\n";
		file_put_contents('alist',$q);
        $list = $dbr->getAll($q);
        if (PEAR::isError($list)) {
			aprint_r($list);
            return;
        }
		if ($ask4alias) {
	        foreach($list as $k=>$article) {
				$q_alias = "select article_alias.id, translation.value
					from article_alias
					left join translation on table_name = 'article_alias'
					     AND field_name = 'name'
					     AND language = '$lang'
					     AND translation.id = article_alias.id
					where article_alias.article_id='".$article->article_id."'";
				$r = $dbr->getAssoc($q_alias);
	        	if (PEAR::isError($article->aliases)) { 
					aprint_r($article->aliases); die();
				} else { 
					$list[$k]->aliases = $r; 
				}
			}
        }
        foreach($list as $k=>$article) {
			$list[$k]->picture_URL_50 = str_replace('_image.jpg','_x_50_image.jpg',$list[$k]->picture_URL);
			$list[$k]->picture_URL_maxx400 = str_replace('_image.jpg','_maxx_400_image.jpg',$list[$k]->picture_URL);
		}
        return $list;
    }
// 20050607 }

    /**
    * @return void
    * @param object $db
    * @param integer $id
    * @param string $artid
    * @param float $high_price
    * @param float $article_price
    * @param float $shipping
    * @param integer $default
    * @param boolean $overstocked
    * @desc Add new article to group
    */
    static function addArticle($db, $dbr, $id, $artid, $high_price, $article_price, $shipping, $default, $overstocked, $inactive, $noship=0, $position=0
		, $copy_from_article_list_id=0, $alias_id=0)
    {
        $id = (int)$id;
        $artid = mysql_escape_string($artid);
        $high_price = mysql_escape_string($high_price);
        $article_price = mysql_escape_string($article_price);
        $shipping = mysql_escape_string($shipping);
        $default = mysql_escape_string($default);
        $overstocked = mysql_escape_string($overstocked);
        $inactive = mysql_escape_string($inactive);
		$position = (int)$position;
		$alias_id = (int)$alias_id;
		$q = "INSERT INTO article_list SET group_id=$id, 
		article_id='$artid', 
		high_price='$high_price', 
		article_price='$article_price', 
		additional_shipping_cost='$shipping', 
		default_quantity='$default', 
		show_overstocked='$overstocked', 
		inactive='$inactive', 
		noship='$noship', 
		position='$position',
		alias_id=$alias_id,
		copy_from_article_list_id=$copy_from_article_list_id";
//		echo $q.'<br>';
        $r = $db->query($q);
        if (PEAR::isError($r)) { print_r($r); die();}
		return $db->getOne("select max(article_list_id) from article_list");
    }

    /**
    * @return void
    * @param object $db
    * @param int $id
    * @param int $listid
    * @param float $high_price
    * @param ufloat $article_price
    * @param float $shipping
    * @param int $default
    * @param bool $overstocked
    * @param int $position
    * @desc Update article record
    */
    static function updateArticle($db, $dbr, $id, $listid, $high_price, $article_price, $shipping, $default, $overstocked, $inactive, $noship, $position, $alias_id=0)
    {
        $id = (int)$id;
        $position=(int)$position;
        $listid = (int)$listid;
        $artid = mysql_escape_string($artid);
        $high_price = mysql_escape_string($high_price);
        $article_price = mysql_escape_string($article_price);
        $shipping = mysql_escape_string($shipping);
        $default = mysql_escape_string($default);
        $overstocked = mysql_escape_string($overstocked);
        $inactive = mysql_escape_string($inactive);
        $alias_id = (int)$alias_id;
		$q = "UPDATE article_list SET group_id=$id, 
		high_price='$high_price', 
		article_price='$article_price', 
		additional_shipping_cost='$shipping', 
		default_quantity='$default', 
		show_overstocked='$overstocked', 
		inactive='$inactive', 
		noship='$noship', 
		position='$position',
		alias_id=$alias_id
		WHERE article_list_id=$listid";
//		echo $q.'<br>';
        $r = $db->query($q);
        if (PEAR::isError($r)) { aprint_r($r); }
    }
}
?>