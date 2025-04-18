<?php
require_once 'PEAR.php';

class Category
{
    var $data;
    var $_db;
var $_dbr;
    var $_error;
    var $_isNew;

    static function listAll($db, $dbr, $siteid, $parent)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Category::listAll expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $parent = (int)$parent;
        $siteid = (int)$siteid;
        if (!$parent) {
            $cond = 'CategoryLevel=1';
        } else {
            $cond = "CategoryParentId = $parent AND CategoryId <> $parent";
        }
		$q = "SELECT * FROM ebay_categories WHERE siteid=$siteid AND $cond order by CategoryName";
        $r = $db->query($q);
//		echo $q;
        if (PEAR::isError($r)) {
            $this->_error = $r;
            return;
        }
        $list = array();
        while ($category = $r->fetchRow()) {
            $list[] = $category;
        }
        return $list;
    }

    static function path($db, $dbr, $siteid, $id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Category::path expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $id = (int)$id;
        $siteid = (int)$siteid;
        $list = array();
        while ($id) {
            $r = $db->query("SELECT * FROM ebay_categories WHERE siteid=$siteid  AND CategoryId = $id");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $category = $r->fetchRow();
            $list[] = $category;
            if ($category->CategoryLevel == 1) {
                break;
            }
            $id = $category->CategoryParentId;
        }
        return array_reverse($list);
    }

}

class CategoryAmazon
{
    var $data;
    var $_db;
var $_dbr;
    var $_error;
    var $_isNew;

    static function listAll($db, $dbr, $siteid, $amazonCategory, $parent, $search)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Category::listAll expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $parent = (int)$parent;
        $amazonCategory = (int)$amazonCategory;
		if (strlen($search)) { 
			$cond = " title like '%$search%'";
			$q = "SELECT amazon_btg.id, amazon_btg.parent_id, getTreeTitleAmazonBTG(amazon_btg.id, $amazonCategory, $siteid) title
			FROM amazon_btg WHERE siteid=$siteid and category_id=$amazonCategory AND $cond";
		} else {
	        $cond = "parent_id = $parent AND id <> $parent";
			$q = "SELECT * FROM amazon_btg WHERE siteid=$siteid and category_id=$amazonCategory AND $cond";
		}
        $r = $dbr->getAll($q);
//		echo $q.'<br>';
        if (PEAR::isError($r)) {
			print_r($r);
            return;
        }
        return $r;
    }

    static function path($db, $dbr, $siteid, $amazonCategory, $id)
    {
        if (!is_a($db, 'MDB2_Driver_mysql')) {
            $this->_error = PEAR::raiseError('Category::path expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $id = (int)$id;
        $amazonCategory = (int)$amazonCategory;
        $list = array();
		if (strlen($search)) {
			$list = array();
		} else {
        	while (1 /*$id*/) {
			$q = "SELECT b1.*, (select count(*) from  amazon_btg b2 where b2.parent_id=b1.id) kids
				FROM amazon_btg b1 WHERE siteid=$siteid and category_id=$amazonCategory AND b1.id = $id";
//			echo $q.'<br>';	
            $r = $db->query($q);
            if (PEAR::isError($r)) {
                return;
            }
            $category = $r->fetchRow();
            $list[] = $category;
            if ($category->parent_id == 0) {
                break;
            }
            $id = $category->parent_id;
        	}
		}
        return array_reverse($list);
    }

}
?>