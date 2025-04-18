<?php
    class WidgetBuilder
    {
        static function Build($db, $dbr, $category, $input, $siteid)
        {	//echo $category.'<br>';
//			print_r($input);
            $ret = array();
            $csid = $dbr->getOne("SELECT csid FROM CharacteristicsSetMap WHERE siteid='$siteid' and category='$category'");
			if (PEAR::isError($csid)) aprint_r($csid); 
            if (!$csid) {
                 $csid = array();
            } else {
                $csid = array($csid);
            }
/*			$sitewides = $dbr->getAll('SELECT distinct csid FROM `SiteWideExclude` where siteid='.$siteid);
            foreach ($sitewides as $sitewide) {
	            if ($category && !$dbr->getOne('SELECT COUNT(*) FROM SiteWideExclude WHERE category='.$category.' AND csid='.$sitewide->csid.' and siteid='.$siteid)) {
    	            $csid[] = $sitewide->csid;
	            }
			};*/	
            $csid = implode(',', $csid);
            if (!$csid) {
                return array();
            }
            $dependent = array();
			$q = 'SELECT a.*, w.input, w.kind, w.depends FROM Attributes a LEFT JOIN Widgets w ON w.value_id=a.id WHERE a.attrset IN (' . $csid .') 
				AND w.siteid='.$siteid.' GROUP BY a.id';
//			echo $q.'<br>';	
            $attrs = $dbr->getAll($q);
//			echo 'SELECT a.*, w.input, w.kind, w.depends FROM Attributes a LEFT JOIN Widgets w ON w.value_id=a.id WHERE a.attrset IN (' . $csid .') 
//				AND w.siteid='.$siteid.' GROUP BY a.id<br>';
	
            foreach ($attrs as $k=>$attr) {
				$attrs[$k]->label = utf8_decode($attrs[$k]->label);
                if ($attr->parent) {
//					echo 'attr->depends='.$attr->depends.'<br>';
//					echo 'attr->parent='.$attr->parent.'<br>';
//					print_r($input[$attr->parent]);
					$parent_keys = array();
					if (is_array($input[$attr->parent])) 
						foreach ($input[$attr->parent] as $parent_key => $dummy)
							$parent_keys[] = $parent_key;
					else $parent_keys[] = $input[$attr->parent];	
					if (!count($parent_keys)) $parent_keys = array(0);
                    $values = $dbr->getAll("SELECT * FROM `Values` WHERE attribute=".$attr->id." AND csid=".$attr->attrset
						." AND parent_value in ('".implode("','", $parent_keys)."') and siteid=$siteid");
//					echo 'SELECT * FROM `Values` WHERE attribute='.$attr->id.' AND csid='.$attr->attrset
//						.' AND parent_value in ('.implode(',', $parent_keys).') and siteid='.$siteid.'<br>';	
//					print_r($input[$attr->parent]); echo '<br/>'.$attr->parent.'<br/>'.$input[$attr->parent].'<br/>';	
                } else {
                    $values = $dbr->getAll('SELECT * FROM `Values` WHERE attribute='.$attr->id.' AND csid='.$attr->attrset
						.' and siteid='.$siteid);
//					echo 'SELECT * FROM `Values` WHERE attribute='.$attr->id.' AND csid='.$attr->attrset
//						.' and siteid='.$siteid.'<br>';
                }
				if (PEAR::isError($values)) { aprint_r($values); print_r($parent_keys);}
                $vals = array();
                foreach ($values as $kk=>$value) {
					$values[$kk]->value = utf8_decode($values[$kk]->value);
                    $vals[$value->id] = $value;
                }
                if ($vals || $attr->input == 'textfield') {
                    $attr->hasdependent = $dbr->getOne('SELECT COUNT(*) FROM Widgets WHERE depends='.$attr->id.' AND attrset='.$attr->attrset.''
						.' and siteid='.$siteid);
                    $attr->values=$vals;
                    $ret[$attr->id] = $attr;
                }
            }
            return $ret;
        }

        static function &SiteWideBuild($db, $dbr, $category, $input, $siteid)
        {	
            $ret = array();
            $csid = array();
  	    $sitewides = $dbr->getAll('SELECT distinct csid FROM `SiteWideExclude` where siteid='.$siteid);
//  	    print_r($sitewides);
            foreach ($sitewides as $sitewide) {
//            echo "SELECT COUNT(*) FROM SiteWideExclude WHERE (category='$category1' OR '$category1'='')
//		       AND (category='$category2' OR '$category2'='') AND csid=$sitewide->csid and siteid=$siteid";
	            if (!$dbr->getOne("SELECT COUNT(*) FROM SiteWideExclude WHERE (category='$category' OR '$category'='')
		       AND csid=$sitewide->csid and siteid=$siteid")) {
    	            $csid[] = $sitewide->csid;
	            }
			};	
            $csid = implode(',', $csid);
            if (!$csid) {
                return array();
            }
            $dependent = array();
			$q = 'SELECT a.*, w.input, w.kind, w.depends FROM Attributes a LEFT JOIN Widgets w ON w.value_id=a.id WHERE a.attrset IN (' . $csid .') 
				AND w.siteid='.$siteid.' GROUP BY a.id';
//			echo "$q<br>";	
            $attrs = $dbr->getAll($q);
//		print_r($input);echo "<br><br>";		
            foreach ($attrs as $k=>$attr) {
				$attrs[$k]->label = utf8_decode($attrs[$k]->label);
                if ($attr->parent) {
                   if (count($input[$attr->parent])) {
//                      print_r($input[$attr->parent]);echo "<br><br>";
                      $values = $dbr->getAll('SELECT * FROM `Values` WHERE attribute='.$attr->id.' AND csid='.$attr->attrset
						." AND parent_value='".(key($input[$attr->parent]))."' and siteid=".$siteid);
		    		}				
                } else {
                    $values = $dbr->getAll('SELECT * FROM `Values` WHERE attribute='.$attr->id.' AND csid='.$attr->attrset
						.' and siteid='.$siteid);
                }
                $vals = array();
				if (PEAR::isError($values)) { print_r($values); }
                foreach ($values as $kk=>$value) {
					$values[$kk]->value = utf8_decode($values[$kk]->value);
                    $vals[$value->id] = $value;
                }
//				echo $attr->id.': '.$attr->input.' '.count($vals).'<br>';
				$firstval = reset($vals);
                if ((count($vals) && $vals && $firstval->attribute==$attr->id) 
				   || $attr->input == 'textfield' 
				   || $attr->input == 'collapsible_textarea' 
				   ) {
		                    $attr->hasdependent = $dbr->getOne('SELECT COUNT(*) FROM Widgets WHERE depends='.$attr->id.' AND attrset='.$attr->attrset.''
								.' and siteid='.$siteid);
		                    $attr->values=$vals;
		                    $ret[$attr->id] = $attr;
		        }
		    }
//		print_r($ret);echo "<br><br>";		
            return $ret;
        }
    }