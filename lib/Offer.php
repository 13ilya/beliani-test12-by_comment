<?php
// vim:expandtab:sw=4:ts=4
require_once 'PEAR.php';
require_once 'lib/ShopCatalogue.php';
require_once 'lib/Group.php';

class Offer
{
    var $data;
    var $_db;
    var $_dbr;
    var $_error;
    var $_isNew;
    private $_lang;

    function Offer($db, $dbr, $id = 0, $lang = 'german')
    {
        $this->_lang = $lang;
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Offer::Offer expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $this->_db = $db;
        $this->_dbr = $dbr;
        $id = (int)$id;
        if (!$id) {
            $r = $this->_db->query("EXPLAIN offer");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = new stdClass;
            while ($field = $r->fetchRow()) {
                $field = $field->Field;
                $this->data->$field = '';
            }
            $this->_isNew = true;
        } else {
            $r = $this->_db->query("SELECT * FROM offer WHERE offer_id='$id'");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("Article::Article : record $id does not exist");
                return;
            }
            $this->data->default_shipping_method
                = $this->_dbr->getOne("SELECT default_shipping_method FROM shipping_plan
					WHERE shipping_plan_id = " . $this->data->shipping_plan_id);
            $this->translated_message_to_buyer = $dbr->getOne("SELECT value
					FROM translation
					WHERE table_name = 'offer'
					AND field_name = 'message_to_buyer'
					AND language = '$lang'
					AND id = '$id'");
            $this->translated_message_to_buyer2 = $dbr->getOne("SELECT value
					FROM translation
					WHERE table_name = 'offer'
					AND field_name = 'message_to_buyer2'
					AND language = '$lang'
					AND id = '$id'");
            $this->translated_message_to_buyer3 = $dbr->getOne("SELECT value
					FROM translation
					WHERE table_name = 'offer'
					AND field_name = 'message_to_buyer3'
					AND language = '$lang'
					AND id = '$id'");
            $this->_isNew = false;
            $this->names = Offer::getNames($db, $dbr, (int)$this->data->master_id ? (int)$this->data->master_id : $id);
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

    static function findByName($db, $dbr, $name)
    {
        $name = mysql_escape_string($name);
//        $r = $db->query("SELECT * FROM offer WHERE name = '$name' order by old, hidden");
        $r = $db->query("SELECT n.offer_id
	   FROM offer o
	   JOIN offer_name n ON o.offer_id = n.offer_id
	   WHERE n.name = '$name'
	   ORDER BY o.old, o.hidden, n.deleted");
        if ($r->numRows()) {
            $row = $r->fetchRow();
            return new Offer($db, $dbr, $row->offer_id);
        } else {
            return false;
        }
    }

    function update()
    {
        $query = '';
        if (!is_object($this->data)) {
            $this->_error = PEAR::raiseError('Offer::update : no data');
        }
        if ($this->_isNew) {
            $this->data->offer_id = '';
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
            $where = "WHERE offer_id='" . mysql_escape_string($this->data->offer_id) . "'";
        }
        $r = $this->_db->query("$command offer SET $query $where");
//		echo "$command offer SET $query $where";
        if (PEAR::isError($r)) {
            $this->_error = $r;
        }
        if ($this->_isNew) {
            $this->data->offer_id = mysql_insert_id();
            $this->_isNew = false;
        }
        return $r;
    }

    function delete()
    {
        $oid = mysql_escape_string($this->data->offer_id);
        $groups = $this->_dbr->getCol("SELECT offer_group_id FROM offer_group WHERE offer_id = '$oid'", 0);
        if (count($groups)) {
            $todelete = implode(',', $groups);
            $this->_db->query("DELETE FROM offer_group WHERE offer_group_id in ($todelete)");
            $this->_db->query("DELETE FROM article_list WHERE group_id in ($todelete)");
            $this->_db->query("DELETE FROM rules WHERE group_id in ($todelete)");
        }
        $this->_db->query("DELETE FROM offer WHERE offer_id = '$oid'");
    }

    function separate()
    {
        global $debug;
        $debug = 0;
        $time = getmicrotime();
        if (!$this->data->offer_id) return $this->data->offer_id;
        $nauctions = $this->_dbr->getOne("SELECT COUNT(*) FROM auction WHERE offer_id = " . $this->data->offer_id . " AND invoice_number"); //
        if ($debug) {
            echo "<br>" . "SELECT COUNT(*) FROM auction WHERE offer_id = " . $this->data->offer_id . " AND invoice_number";
        }
        if (PEAR::isError($nauctions)) print_r($nauctions);
        if (!$nauctions) return $this->data->offer_id;
        $saveds = $this->_dbr->getAssoc("select saved_id f1, saved_id f2
			from saved_params
			where saved_params.par_key='offer_id'
			and par_value=" . $this->data->offer_id);
        if (is_array($saveds) && !empty($saveds)) {
            foreach ($saveds as $saved_id) {
                cacheClear("Offer::getShopPrice($saved_id,%");
            }
        }
        $newid = $this->duplicate(0);
        if ($debug) {
            echo 'after DUPLICATE : ' . (getmicrotime() - $time) . '<br>';
            $time = getmicrotime();
        }
        $this->_db->query("UPDATE offer SET hidden=1 WHERE offer_id = " . $this->data->offer_id);
        $this->_db->query("UPDATE offer set master_id = $newid WHERE master_id = " . $this->get('offer_id'));
        if ($debug) {
            echo '01: ' . (getmicrotime() - $time) . '<br>';
            $time = getmicrotime();
        }
        $this->_db->query("UPDATE offer_name set offer_id = $newid WHERE offer_id = " . $this->get('offer_id'));
        if ($debug) {
            echo '02: ' . (getmicrotime() - $time) . '<br>';
            $time = getmicrotime();
        }
        $this->_db->query("insert into offer_name (offer_id, name, lang) select " . $this->get('offer_id') . ", name, lang from offer_name WHERE offer_id = $newid");
        if ($debug) {
            echo '03: ' . (getmicrotime() - $time) . '<br>';
            $time = getmicrotime();
        }
        $this->_db->query("UPDATE listings SET offer_id = $newid WHERE offer_id = " . $this->get('offer_id'));
        if ($debug) {
            echo '04: ' . (getmicrotime() - $time) . '<br>';
            $time = getmicrotime();
        }
        $this->_db->query("UPDATE auction SET offer_id = $newid WHERE IFNULL(invoice_number,0)=0 AND offer_id = " . $this->get('offer_id'));
        if ($debug) {
            echo '05: ' . (getmicrotime() - $time) . '<br>';
            $time = getmicrotime();
        }
//        $r = $this->_db->query("INSERT INTO translation (language, table_name, field_name, id, value)
//	   (select language, table_name, field_name, $newid, value 
//	   from translation where table_name='offer' and id=".$this->get('offer_id').")");
//        if (PEAR::isError($r)) print_r($r);
        $this->_db->query("UPDATE alarms SET type_id = $newid WHERE type='offer' and type_id = " . $this->get('offer_id'));
        $this->_db->query("insert into offer_comment (offer_id,comment,create_date,username) 
			select $newid,comment,create_date,username from offer_comment WHERE offer_id = " . $this->get('offer_id'));

        $saved_auctions = $this->_db->getAll("select sa.*
			from saved_auctions sa
			join saved_params sp on sp.saved_id=sa.id and sp.par_key='offer_id' and sp.par_value='" . $this->get('offer_id') . "'
			");
        foreach ($saved_auctions as $saved) {
            $saved_details = Saved::getDetails($saved->id);
//			echo $saved->saved_id.': offer_id='.$saved->details['offer_id'].'<br>';
            if ($saved_details['offer_id'] == $this->get('offer_id')) {
                $saved_details['offer_id'] = $newid;
                $r = $this->_db->execParam("update saved_auctions set details=? where id=?", array(serialize($saved_details), $saved->id));
                if ($debug) {
                    echo '06 for SA#' . $saved->id . ': ' . (getmicrotime() - $time) . '<br>';
                    $time = getmicrotime();
                }
                if (PEAR::isError($r)) {
                    print_r($r);
                }
                $r = $this->_db->query("delete from saved_params where saved_id={$saved->id} and par_key='offer_id'");
                if (PEAR::isError($r)) {
                    print_r($r);
                    die();
                }
                $r = $this->_db->query("replace saved_params set saved_id={$saved->id}, par_key='offer_id', par_value='$newid'");
                if (PEAR::isError($r)) {
                    print_r($r);
                    die();
                }
                
                cacheClear("Offer::getOffer({$saved->id})");
            }
        }
        $base_groups = $this->_dbr->getAll("select IFNULL(base_group_id,0) base, offer_group.* from offer_group
			where offer_id=" . $this->data->offer_id . " and IFNULL(base_group_id,0)>0");
        $hidden_offer = new Offer($this->_db, $this->_dbr, $this->data->offer_id);
        if ($debug) {
            echo '1: ' . (getmicrotime() - $time) . '<br>';
            $time = getmicrotime();
        }
        foreach ($base_groups as $base_group) {
            $old_group = new Group($this->_db, $this->_dbr, $hidden_offer, $base_group->offer_group_id);
            $real_base_group = $this->_dbr->getRow("select * from offer_group where offer_group_id=" . $base_group->base);
            $old_group->set('title', $real_base_group->title);
            $old_group->set('description', $real_base_group->description);
            $old_group->set('additional', $real_base_group->additional);
            $old_group->set('position', $real_base_group->position);
            $old_group->set('main', $real_base_group->main);
            $old_group->set('noshipping', $real_base_group->noshipping);
            $old_group->set('noshipping_same', $real_base_group->noshipping_same);
            $old_group->set('base_group_id', 0);
            $old_group->update();
            // copy group properties
            $q = "delete from translation where id='" . $base_group->offer_group_id . "'
				and table_name='offer_group' and field_name in ('description', 'subtitle')";
            if ($debug) {
                echo "$q<br>";
            }
            $r = $this->_db->query($q);
            if ($debug) {
                echo '2: ' . (getmicrotime() - $time) . '<br>';
                $time = getmicrotime();
            }
            if (PEAR::isError($r)) {
                print_r($r);
                die();
            }
            $q = "insert into translation (
				`language`,	table_name,	field_name,	id,	`value`
				)
				select `language`, table_name,	field_name,	'" . $base_group->offer_group_id . "' id, `value`
				from translation where id='" . $base_group->base . "'
				and table_name='offer_group' and field_name in ('description', 'subtitle')";
            if ($debug) {
                echo "$q<br>";
            }
            $r = $this->_db->query($q);
            if ($debug) {
                echo '3: ' . (getmicrotime() - $time) . '<br>';
                $time = getmicrotime();
            }
            if (PEAR::isError($r)) {
                print_r($r);
                die();
            }
            // copy articles
            $articles = $this->_dbr->getAll("select * from article_list where group_id=" . $base_group->base);
            foreach ($articles as $article) {
                // copy article properties
                $newALid = Group::addArticle($this->_db, $this->_dbr, $base_group->offer_group_id,
                    $article->article_id,
                    $article->high_price,
                    $article->article_price,
                    $article->additional_shipping_cost,
                    $article->default_quantity,
                    $article->show_overstocked,
                    $article->inactive,
                    $article->noship,
                    $article->position,
                    $article->article_list_id,
                    $article->alias_id
                );
                $q = "insert into translation (
					`language`,	table_name,	field_name,	id, `value`
					)
					select `language`, table_name,	field_name,	'" . $newALid . "' id, `value`
					from translation where id='" . $article->article_list_id . "'
					and table_name='article_list' and field_name in ('article_price','additional_shipping_cost','subtitle')";
                if ($debug) {
                    echo "$q<br>";
                }
                $r = $this->_db->query($q);
                if ($debug) {
                    echo '4: ' . (getmicrotime() - $time) . '<br>';
                    $time = getmicrotime();
                }
                if (PEAR::isError($r)) {
                    print_r($r);
                    die();
                }
                // update auctions
                $r = $this->_db->query("UPDATE orders SET article_list_id = " . $newALid . " WHERE article_list_id = " . $article->article_list_id);
                if (PEAR::isError($r)) {
                    print_r($r);
                    die();
                }
                $r = $this->_db->query("UPDATE auction_calcs SET article_list_id = " . $newALid . " WHERE article_list_id = " . $article->article_list_id);
                if (PEAR::isError($r)) {
                    print_r($r);
                    die();
                }
            }
        }
        if ($debug) {
            die('<br>End' . (getmicrotime() - $time) . '<br>');
        }
        return $newid;
    }
    
    function getAvailable($offer_obj, $shopCatalogue) {
        $this->data->available = $offer_obj->available;
        $this->data->available_weeks = $offer_obj->available_weeks;

        $this->data->shopAvailableDate = $offer_obj->available_date;
        $this->data->shopAvailable = str_replace($offer_obj->available_date
            , strftime($shopCatalogue->_seller->get('date_format'), strtotime($offer_obj->available_date))
            , $offer_obj->available_text);

        $shopAvailableDate = $this->data->shopAvailableDate == '0000-00-00' ? 'NOW()' : "'{$this->data->shopAvailableDate}'";
        $this->data->expecteddelivery_from = $this->_dbr->getOne("select date_add($shopAvailableDate, interval " . (int)$shopCatalogue->_shop->expecteddelivery_min . " day)");
        $this->data->expecteddelivery_to = $this->_dbr->getOne("select date_add($shopAvailableDate, interval " . (int)$shopCatalogue->_shop->expecteddelivery_max . " day)");
        while (in_array((int)$this->_dbr->getOne("select dayofweek('{$this->data->expecteddelivery_to}')"), array(1, 7))) {
            $this->data->expecteddelivery_to = $this->_dbr->getOne("select date_add('{$this->data->expecteddelivery_to}', interval 1 day)");
        }
        $this->data->expecteddelivery_from = strftime($shopCatalogue->_seller->get('date_format'), strtotime($this->data->expecteddelivery_from));
        $this->data->expecteddelivery_to = strftime($shopCatalogue->_seller->get('date_format'), strtotime($this->data->expecteddelivery_to));

        if ($this->data->shopAvailableDate == '0000-00-00') {
            $this->data->shopAvailableDate = $shopCatalogue->_shop->english_shop[192];
        } else {
            $this->data->shopAvailableDate = strftime($shopCatalogue->_seller->get('date_format'), strtotime($this->data->shopAvailableDate));
        }
    }

    static function listAllByArticle($db, $dbr, $article_id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Offer::listAll expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $q = "SELECT distinct o.*, al.inactive FROM offer o
			JOIN offer_group og ON o.offer_id=og.offer_id
			JOIN article_list al ON al.group_id=og.offer_group_id 
		WHERE al.article_id='$article_id' and not o.hidden  and not og.base_group_id
		ORDER BY o.offer_id";
//		echo $q;
        $r = $db->query($q);
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        $list = array();
        while ($offer = $r->fetchRow()) {
            $list[] = $offer;
        }
        return $list;
    }

    static function listBaseByArticle($db, $dbr, $article_id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Offer::listAll expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $dbr->query("SELECT distinct og.*
		, o.name
		FROM offer_group base
			JOIN article_list al ON al.group_id=base.offer_group_id 
			join offer_group og on base.offer_group_id=og.base_group_id
			join offer o on o.offer_id=og.offer_id
		WHERE al.article_id='$article_id' and base.offer_id=0 and og.base_group_id
		and o.old=0 and o.hidden=0
		");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        $list = array();
        while ($offer = $r->fetchRow()) {
            $list[] = $offer;
        }
        return $list;
    }

    static function listBasegroupsByArticle($db, $dbr, $article_id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Offer::listAll expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $db->query("SELECT distinct base.*, base.title name, al.inactive
		FROM offer_group base
			JOIN article_list al ON al.group_id=base.offer_group_id 
		WHERE al.article_id='$article_id' and base.offer_id=0 
		");
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        $list = array();
        while ($offer = $r->fetchRow()) {
            $list[] = $offer;
        }
        return $list;
    }

    static function listAll($db, $dbr, $hide = false, $empty = false, $old = '0', $shipping_plan_id = 0, $cat_id = 0, $shop_id = 0, $country_code = '')
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Offer::listAll expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        if ($cat_id) {
            $shop = new Shop_Catalogue($db, $dbr, $shop_id);
            $children = $shop->listAll($cat_id, 0);
            $catAll = array();
            $catAll[] = $cat_id;
            foreach ($children as $cat) $catAll[] = $cat->id;
            $catAll = implode(',', $catAll);
            $cat_join = "left join sa{$shop_id} sa on sa.offer_id=offer.offer_id";
        }
        if ($shipping_plan_id) {
            $sh_offer_ids = $dbr->getOne("select group_concat(id) from translation where
			  table_name='offer' and field_name='shipping_plan_id' 
			  and value='$shipping_plan_id'");
            if (!strlen($sh_offer_ids)) $sh_offer_ids = '0';
        }
        $q = "SELECT offer.*,
	      IF((select count(*) from rules where offer_id=offer.offer_id)>0, 'Yes', '') as active_rules
	      FROM offer 
		  $cat_join			
		  where 1=1 "
            . ($country_code == '' ? '' : ($country_code == '-1' ? " AND IFNULL(offer.country_code,'') = '' " : " AND offer.country_code='$country_code' "))
            . ($hide ? ' AND NOT offer.hidden ' : ' AND offer.hidden ')
            . ($old == '1' ? ' AND offer.old=1 ' : ($old == '0' ? ' AND offer.old=0 ' : ' '))
            . ($cat_id ? " AND sa.shop_catalogue_id in ($catAll) " : '')
            . ($shipping_plan_id ? " AND offer_id in ($sh_offer_ids) " : '')
            . ($empty ? ' AND exists (select * from offer_group join article_list
			on offer_group.offer_group_id = article_list.group_id 
			where offer_group.offer_id=offer.offer_id AND offer_group.additional = 0) ' : '')
            . ' group BY offer.offer_id'
            . ' ORDER BY offer.name';
        global $debug;
        if ($debug) echo $q . '<br>';    //die();
        $r = $db->query($q);
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        $list = array();
        global $array;
        while ($offer = $r->fetchRow()) {
            if (!$array) {
                $offer->names = Offer::getNames($db, $dbr, $offer->offer_id);
                $q = "select base.offer_group_id, base.title
					from offer_group
					join offer_group base on offer_group.base_group_id=base.offer_group_id
					where not offer_group.base_group_inactive and offer_group.offer_id=" . $offer->offer_id;
                $offer->basegroups = $dbr->getAll($q);
                if ($debug) echo $q . '<br>';    //die();
            }
            $list[] = $offer;
        }
        return $list;
    }

    static function listArray($db, $dbr, $hide = false, $empty = false, $old = '0')
    {
        $ret = array();
        global $array;
        $array = 1;
        $list = Offer::listAll($db, $dbr, $hide, $empty, $old);
        foreach ((array)$list as $offer) {
            $ret[$offer->offer_id] = $offer->name;
        }
        return $ret;
    }

    function validate(&$errors, &$warnings)
    {
        $errors = array();
        if ($this->data->offer_id) $id = ' and ' . $this->data->offer_id . '<>offer_id';
//		echo "select count(*) from offer where not hidden and name='".$this->data->name."' $id";
        if ($this->_dbr->getOne("select count(*) from offer where not hidden and name='" . $this->data->name . "' $id")) {
            $errors[] = 'Name does already exist in offer' . " select count(*) from offer where not hidden and name='" . $this->data->name . "' $id";
        }
        if (empty($this->data->name)) {
            $errors[] = 'Name is required';
        }
#        if ($offer_id = $this->_dbr->getOne("select offer_id from offer where not hidden and ean_code='".$this->data->ean_code."' $id limit 1")) {
#            $warnings[] = "EAN does already exist in offer #$offer_id";
#        }    
        if (!is_numeric($this->data->min_price_multiple_item)) {
            //$errors[] = 'Invalid price';
        }
        return !count($errors);
    }

    function duplicate($separate = 1)
    {
        global $debug;
        require_once 'lib/Rule.php';
        require_once 'lib/Group.php';
        $time = getmicrotime();
        $command = "INSERT INTO offer SET ";
        $query = "";

        foreach ($this->data as $field => $value) {
            if ($field == 'offer_id' || ($field == 'ean_code' && !$separate)) {
                continue;
            }
            if ($query) {
                $query .= ', ';
            }
            $query .= "`$field`='" . mysql_escape_string($value) . "'";
        }
        $this->_db->query($command . $query);
        if ($debug) echo "<br>Duplicate " . $command . $query;
        $newid = mysql_insert_id();
        $newOffer = new Offer($this->_db, $this->_dbr, $newid);
        if ($debug) {
            echo 'duplicate 0: ' . (getmicrotime() - $time) . '<br>';
            $time = getmicrotime();
        }
        $q = "INSERT INTO translation (language, table_name, field_name, id, value)
	       (select language, table_name, field_name, '$newid', value 
	       from translation where table_name='offer' and id='" . $this->data->offer_id . "')";
        if ($debug) {
            echo "$q<br>";
        }
        $r = $this->_db->query($q);
        $this->_db->query("insert into classifier_obj (obj, obj_id, classifier_id, level)
			select obj, $newid, classifier_id, level from classifier_obj where obj='offer' and obj_id=" . $this->data->offer_id . "
			");
        if ($debug) {
            echo 'duplicate 1: ' . (getmicrotime() - $time) . '<br>';
            $time = getmicrotime();
        }
        if (PEAR::isError($r)) {
            print_r($r);
            die();
        }
        $groupMap = array();
        $rules = $this->_dbr->getAll("SELECT * FROM rules WHERE offer_id = " . $this->get('offer_id'));
        $groups = $this->_dbr->getAll("SELECT * FROM offer_group WHERE offer_id = " . $this->get('offer_id') . " order by position");
        if ($debug) {
            echo 'duplicate 2: ' . (getmicrotime() - $time) . '<br>';
            $time = getmicrotime();
        }
        foreach ($groups as $group) {
            $newGroup = new Group($this->_db, $this->_dbr, $newOffer);
            $articles = $this->_dbr->getAll("SELECT * FROM article_list WHERE group_id={$group->offer_group_id} order by position");
            foreach ($group as $field => $value) {
                if ($field == 'offer_group_id') {
                    continue;
                }
                $newGroup->set($field, $value);
            }
            $newGroup->set('offer_id', $newid);
            $newGroup->set('copy_from_offer_group_id', $group->offer_group_id);
            $newGroup->update();
            if ($debug) {
                echo 'duplicate 3: ' . (getmicrotime() - $time) . '<br>';
                $time = getmicrotime();
            }
            $q = "INSERT INTO translation (language, table_name, field_name, id, value)
	       (select language, table_name, field_name, '" . $newGroup->get('offer_group_id') . "', value
	       from translation where table_name='offer_group' and id='" . $group->offer_group_id . "')";
            if ($debug) {
                echo "$q<br>";
            }
            $r = $this->_db->query($q);
            if ($debug) {
                echo 'duplicate 4: ' . (getmicrotime() - $time) . '<br>';
                $time = getmicrotime();
            }
            if (PEAR::isError($r)) {
                print_r($r);
                die();
            }
            $groupMap[$group->offer_group_id] = $newGroup->get('offer_group_id');
            foreach ($articles as $article) {
                $newALid = Group::addArticle($this->_db, $this->_dbr, $newGroup->get('offer_group_id'),
                    $article->article_id,
                    $article->high_price,
                    $article->article_price,
                    $article->additional_shipping_cost,
                    $article->default_quantity,
                    $article->show_overstocked,
                    $article->inactive,
                    $article->noship,
                    $article->position,
                    $article->article_list_id
                );
                if ($debug) {
                    echo 'duplicate 5 add article: ' . (getmicrotime() - $time) . '<br>';
                    $time = getmicrotime();
                }
                $q = "INSERT INTO translation (language, table_name, field_name, id, value)
			       (select language, table_name, field_name, '" . $newALid . "', value
			       from translation where table_name='article_list' and id='" . $article->article_list_id . "')";
                if ($debug) {
                    echo "$q<br>";
                }
                $r = $this->_db->query($q);
                if ($debug) {
                    echo 'duplicate 6: ' . (getmicrotime() - $time) . '<br>';
                    $time = getmicrotime();
                }
                if (PEAR::isError($r)) {
                    print_r($r);
                    die();
                }
                if ($separate) {
                    $this->_db->query("UPDATE orders SET article_list_id = " . $newALid . " WHERE article_list_id = " . $article->article_list_id);
                    $this->_db->query("UPDATE auction_calcs SET article_list_id = " . $newALid . " WHERE article_list_id = " . $article->article_list_id);
                    if ($debug) {
                        echo 'duplicate 7: ' . (getmicrotime() - $time) . '<br>';
                        $time = getmicrotime();
                    }
//				echo "UPDATE orders SET article_list_id = ".$newALid." WHERE article_list_id = ".$article->article_list_id;
                };
            }
        }
        foreach ($rules as $rule) {
            $newRule = new Rule($this->_db, $this->_dbr, $newOffer);
            foreach ($rule as $field => $value) {
                if ($field == 'copy_from_rule_id') {
                    $newRule->set('copy_from_rule_id', $rule->rule_id);
                    continue;
                }
                if ($field == 'rule_id') {
                    continue;
                }
                if ($field == 'group_id' || $field == 'linked_group_id') {
                    $newRule->set($field, $groupMap[$value]);
                } else {
                    $newRule->set($field, $value);
                }
            }
            $newRule->set('offer_id', $newid);
            $newRule->update();
            if ($debug) {
                echo 'duplicate 8: ' . (getmicrotime() - $time) . '<br>';
                $time = getmicrotime();
            }
            $q = "INSERT INTO translation (language, table_name, field_name, id, value)
	       (select language, table_name, field_name, '" . $newRule->get('rule_id') . "', value
	       from translation where table_name='rules' and id='" . $rule->rule_id . "')";
            if ($debug) {
                echo "$q<br>";
            }
            $r = $this->_db->query($q);
            if ($debug) {
                echo 'duplicate 9: ' . (getmicrotime() - $time) . '<br>';
                $time = getmicrotime();
            }
            if (PEAR::isError($r)) {
                print_r($r);
                die();
            }
        }
        return $newid;
    }

    static function getNames($db, $dbr, $offer_id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        return $dbr->getAll("SELECT * FROM offer_name WHERE offer_id=$offer_id and not deleted
			order by lang");
    }

    static function addName($db, $dbr, $offer_id, $newname, $lang)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return 0;
        }
        $offer = $dbr->getRow("select offer.* from offer_name JOIN offer ON offer_name.offer_id=offer.offer_id
			where offer_name.name='$newname' and not offer.hidden and not offer_name.deleted");
        if ($offer) {
            return $offer;
        } else {
            $db->query("insert into offer_name (offer_id, name, lang)
				values ($offer_id, '$newname', '$lang')");
            return 0;
        }
    }

    static function changeName($db, $dbr, $offer_id, $newname, $name_id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return 0;
        }
        $q = "select offer.* from offer_name JOIN offer ON offer_name.offer_id=offer.offer_id
			where offer_name.name='$newname' and not offer.hidden and not offer_name.deleted and offer_name.id<>$name_id";
//		echo $q;
        $offer = $dbr->getRow($q);
        if ($offer) {
            return $offer;
        } else {
            $db->query("update offer_name set name='$newname' where offer_id=$offer_id and id=$name_id");
            return 0;
        }
    }

    static function deleteName($db, $dbr, $id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $db->query("update offer_name set deleted=1 where id='$id'");
        $db->query("delete from from saved_gallery where name_id='$id'");
    }

    static function listAliasArray($db, $dbr, $offer_id = 0)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        if (!$offer_id) $offer_id = 'offer_name.offer_id';
        $q = "SELECT offer_name.id, offer_name.name
	FROM offer_name JOIN offer ON offer_name.offer_id = offer.offer_id  
	WHERE (offer_name.offer_id=$offer_id or $offer_id=0) 
	and offer.hidden = 0 and offer_name.deleted = 0 and offer.old = 0
	order by offer_name.name";
        $list = $dbr->getAssoc($q);
        if (PEAR::isError($list)) {
            aprint_r($list);
            return;
        }
        /*        $ret = array();
                foreach ((array)$list as $alias) {
                    $ret[$alias->id] = $alias->name;
                }*/
        return $list;
    }

    static function getPrice($db, $dbr, $saved_id, $article_list_id, $lang, $sitid)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            return;
        }
        $q = "select
			REPLACE(SUBSTRING_INDEX( SUBSTRING_INDEX( SUBSTRING_INDEX( details, 's:8:" . '"offer_id"' . ";', -1 ) , ';', 1 ), ':', -1),'" . '"' . "','') offer_id,
			SUBSTRING_INDEX( SUBSTRING_INDEX( SUBSTRING_INDEX( details, 's:13:" . '"BuyItNowPrice"' . ";', -1 ) , '" . '"' . "', 2 ), '" . '"' . "', -1) BuyItNowPrice
			from saved_auctions sa
			where id=$saved_id";
//		echo $q.'<br>';
        $saved = $dbr->getRow($q);
        $winning_bid = $saved->BuyItNowPrice;
        $offer = new Offer($db, $dbr, $saved->offer_id);
        $groups = Group::listAll($db, $dbr, $offer, '', $lang, $sitid, '', '');
        foreach ($groups as $i => $group) {
            foreach ($group->articles as $j => $article) {
                if ($article->article_list_id == $article_list_id) {
                    $price = $group->additional ? $article->article_price : $article->additional_item_cost;
                    if ($group->main) {
                        $price += $winning_bid;
                    }
                }
            }
        }
        return $price;
    }

    static function getShopPrice(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $saved_id, $article_list_id, $lang, $sitid, $cached = 0)
    {
        if (!$saved_id) {
            return false;
        }
        global $debug;
        
        $time = getmicrotime();
        
        if ($debug) echo 'Start getShopPrice for ' . $saved_id . '-' . $article_list_id . '<br>';
        
        $saved = $dbr->getRow("select offer_id, ShopPrice from sa_all where id=?", null, $saved_id);
        if (!(int)$saved->offer_id) {
            if ($debug) {
                echo "no offer ID in shops for SA# $saved_id <br>";
            }
            return false;
        }
        
        $winning_bid = $saved->ShopPrice;
        if ($debug) {
            echo "create offer $saved->offer_id <br>";
        }
        $offer = new Offer($db, $dbr, $saved->offer_id);
        if ($debug) {
            echo (getmicrotime() - $time) . '<br>';
            $time = getmicrotime();
        }
        $time = getmicrotime();
        if ($debug) {
            echo "getShopPrice Group::listAll for Offer#{$offer->data->offer_id}: $lang, $sitid ";
        }

        $groups = Group::listAll($db, $dbr, $offer, '', $lang, $sitid, '', '');
        if ($debug) {
            echo (getmicrotime() - $time) . '<br>';
            $time = getmicrotime();
        }
        
//if ($debug)		print_r($groups);
        
        $isset_price = false;
        
        $price = 0;
        foreach ($groups as $i => $group) {
            foreach ($group->articles as $j => $article) {
                if ($article->article_list_id == $article_list_id) {
                    if ($debug) {
                        echo $group->additional . " ? " . $article->article_price . " : " . $article->additional_item_cost . '<br>';
                    }
                    
                    $isset_price = true;
                    
                    $price += $group->additional ? $article->article_price : $article->additional_item_cost;
                    if ($group->main) {
                        $price += $winning_bid;
                        if ($debug) echo "+" . $winning_bid . "<br>";
                    }
                }
            }
        }
        
        if ( ! $isset_price) {
            return false;
        }
        
#if ($debug)		die();
        return $price;
    }

    static function getComments($db, $dbr, $id)
    {
        $q = "SELECT '' as prefix
			, offer_comment.id
			, offer_comment.create_date
			, offer_comment.username
			, IFNULL(users.name, offer_comment.username) username_name
			, users.deleted
			, IF(users.name is null, 1, 0) olduser
			, offer_comment.comment
			 from offer_comment 
			 LEFT JOIN users ON offer_comment.username = users.username
			where offer_id=$id
		UNION ALL
		select CONCAT('Alarm (',alarms.status,'):') as prefix
			, NULL as id
			, (select updated from total_log where table_name='alarms' and tableid=alarms.id limit 1) as create_date
			, alarms.username cusername
			, IFNULL(users.name, alarms.username) username_name
			, users.deleted
			, IF(users.name is null, 1, 0) olduser
			, alarms.comment 
			from offer
			join alarms on alarms.type='offer' and alarms.type_id=offer.offer_id
			LEFT JOIN users ON alarms.username = users.username
			where offer.offer_id=" . $id . "
		ORDER BY create_date";
//		echo $q;
        $r = $db->query($q);
        if (PEAR::isError($r)) {
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $list[] = $article;
        }
        return $list;
    }

    static function addComment($db, $dbr, $id,
                               $username,
                               $create_date,
                               $comment
    )
    {
        $id = (int)$id;
        $username = mysql_escape_string($username);
        $create_date = mysql_escape_string($create_date);
        $comment = mysql_escape_string($comment);
        $r = $db->query("insert into offer_comment set 
			offer_id=$id, 
			username='$username',
			create_date='$create_date',
			comment='$comment'");
    }

    static function delComment($db, $dbr, $id)
    {
        $id = (int)$id;
        $r = $db->query("delete from offer_comment where id=$id");
    }

    function getAvailableText()
    {
        $q = "select
			CONCAT(t47.value, ' ', 
				IF(offer.available
					, t214.value
					,IF(offer.available_weeks
						, CONCAT(t216.value, ' ', DATE(date_add(NOW(), INTERVAL offer.available_weeks week)))
						, IF(offer.available_date='0000-00-00'
							, t215.value
							, CONCAT(t216.value,' ', offer.available_date)
						)
					)
				) 
			) available_text
			from offer 
			join translation t214 on t214.table_name='translate_shop' and t214.field_name='translate_shop'
				and t214.id=214 and t214.language='" . $this->_lang . "'
			join translation t215 on t215.table_name='translate_shop' and t215.field_name='translate_shop'
				and t215.id=215 and t215.language='" . $this->_lang . "'
			join translation t216 on t216.table_name='translate_shop' and t216.field_name='translate_shop'
				and t216.id=216 and t216.language='" . $this->_lang . "'
			join translation t47 on t47.table_name='translate_shop' and t47.field_name='translate_shop'
				and t47.id=47 and t47.language='" . $this->_lang . "'
			WHERE offer_id = " . $this->data->offer_id;
        return $this->_dbr->getOne($q);
    }
    /**
     *
     */
    public static function alertSellersMass($offers, $vars)
    {
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        
        if (is_array($offers) && !empty($offers)) {
            $offer_ids = [];
            foreach ($offers as $offer) {
                $offer_ids[] = $offer->data->offer_id;
            }
            
            $list = $dbr->getAll("select u.email, sp.par_value offer_id
                from saved_params sp
                join saved_params spu on spu.saved_id=sp.saved_id and spu.par_key='username'
                join seller_information si on si.username=spu.par_value
                join seller_notif sn on sn.seller_id=si.id
                join seller_notif_type snt on snt.id=sn.notif_type_id and snt.code = 'offer_ava_alert_email'
                left join users u on u.id = sn.user_id
                where sp.par_value IN (" . implode(',', $offer_ids) . ") and sp.par_key='offer_id'");
                
            $emails = [];
            foreach ($list as $row) {
                $emails[$row->email][] = $row->offer_id;
            }

            if (APPLICATION_ENV == 'develop' || APPLICATION_ENV == 'heap') {
                $url = 'http://prolodev.prologistics.info/';
            } else {
                $url = 'https://www.prologistics.info/';
            }
            
            foreach ($emails as $email => $offer_ids) {
                $offer_list = '';
                
                foreach ($offer_ids as $offer_id) {
                    $offer_list .= '<a href="' . $url . 'offer.php?id=' . $offer_id . '">' . $offers[$offer_id]->data->name . '</a><br>';
                }
                
                $rec = new stdClass();
                $rec->offer_list = $offer_list;
                $rec->email_invoice = $email;
                //$rec->old_value = $rec->available ? 'Yes' : ($rec->available_weeks ? ($rec->available_weeks . ' weeks') : ($rec->available_date ? $rec->available_date : 'No'));
                $rec->new_value = $vars['available'] ? 'Yes' : ($vars['available_weeks'] ? ($vars['available_weeks'] . ' weeks') : ($vars['available_date'] ? $vars['available_date'] : 'No'));

                $r = standardEmail($db, $dbr, $rec, 'offer_ava_alert_email');
            }
        }
    }
    /**
     * Check if current order is hidden and return not hidden offer_id
     * @param int $offer_id
     * @return int
     */
    public static function getNotHiddenId($offer_id) {
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $name = $db->getOne("SELECT `name` FROM `offer` WHERE `offer_id` = " . (int)$offer_id);
        return $db->getOne("SELECT `offer_id` FROM `offer` WHERE `name` = '$name' AND `hidden` = 0");
    }
}
?>