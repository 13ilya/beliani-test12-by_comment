<?php
require_once 'lib/Vat.php';
    require_once 'config.php';
    require_once 'lib/Listing.php';
require_once 'Services/Ebay.php';
require_once 'lib/SellerInfo.php';
require_once 'lib/Insurance.php';
require_once 'lib/Zip.php';
require_once 'lib/ShopCatalogue.php';
/**
 * Common utility functions
 * 
 * @package eBay_After_Sale
 * 
 */
if (get_magic_quotes_gpc()) {
    noslashes($_POST);
    noslashes($_GET);
    noslashes($_COOKIE);
}

    require_once 'memcached.php';


function generate_password($length = 10) {

	// This variable contains the list of allowable characters
	// for the password.  Note that the number 0 and the letter
	// 'O' have been removed to avoid confusion between the two.
	// The same is true of 'I' and 1
	$allowable_characters = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
	
	// We see how many characters are in the allowable list
	$ps_len = strlen($allowable_characters);

	// Seed the random number generator with the microtime stamp
	// (current UNIX timestamp, but in microseconds)
	mt_srand((double)microtime()*1000000);

	// Declare the password as a blank string.
	$pass = "";

	// Loop the number of times specified by $length
	for($i = 0; $i < $length; $i++) {
	
		// Each iteration, pick a random character from the
		// allowable string and append it to the password.
		$pass .= $allowable_characters[mt_rand(0,$ps_len-1)];
	
	} // End for

	// Retun the password we've selected
	return $pass;
}

	function getmicrotime(){ 
		list($usec, $sec) = explode(" ",microtime()); 
		return ((float)$usec + (float)$sec); 
		} 

    function listCurrencyArray($db, $dbr)
    {
        $r = $dbr->getAssoc("SELECT value, description FROM config_api_values WHERE par_id =7 and not inactive");
        return $r;
    }


/**
 * @return void
 * @param unknown $vars
 * @desc Utility function to strip slashes from
 * input depending on magic_quotes_gpc
*/
function noslashes(&$vars)
{
    foreach ($vars as $k=>$v) {
        if (!is_array($v)) {
            $vars[$k] = trim(stripslashes($v));
        } else {
            noslashes($vars[$k]);
        }
    }
}

/**
 * @return mixed
 * @param string $name
 * @param mixed $default
 * @param array $var
 * @desc Get request variable
*/
function requestVar($name, $default='', $var=NULL)
{
    if ($var=='GET') {
        $ret = isset($_GET[$name]) ? $_GET[$name] : $default;
    } elseif ($var=='POST') {
        $ret = isset($_POST[$name]) ? $_POST[$name] : $default;
    } elseif (isset($_POST[$name])) {
        $ret = $_POST[$name];
    } elseif (isset($_GET[$name])) {
        $ret = $_GET[$name];
    } else {
        $ret = $default;
    }
    settype($ret, gettype($default));
    return $ret;
}

/**
 * @return object
 * @param array $array
 * @desc Utility function converting array to object.
*/
function array_to_object($array)
{
    $ret = new stdClass;
    foreach ($array as $key => $value) {
        $ret->$key = $value;
    }
    return $ret;
}

/**
 * @return string
 * @param string $code
 * @desc Converts country code to country name
*/
function countryCodeToCountry ($code, $lang='english')
{
	$map = allCountries($lang);
    return $map[strtoupper($code)];
}

/**
 * @return string
 * @param string $country
 * @desc Converts country name to country code
*/
function CountryToCountryCode($country)
{
	global $db, $dbr;
	return $dbr->getOne("select IFNULL((select `code` from country c
		join translation t on c.id=t.id
					and t.table_name='country' and t.field_name='name' 
				where 1 and t.value='$country' limit 1), '$country')");
}

/**
 * @return array
 * @desc Returns array of countries names, indexed by country code
*/
function allCountries($lang='english', $seller_id=0, $shipping_plan_ids=array())
{
/*    static $countries = array(*/
	global $db, $dbr;
        if (!is_a($dbr, 'MDB2_Driver_mysql')) {
            $error = PEAR::raiseError('Auction::Auction expects its argument to be a MDB2_Driver_mysql object');
			print_r($error); die();
            return;
        }
	$joins = '';
	foreach ($shipping_plan_ids as $k=>$id) {
		$joins = " join shipping_plan_country spc$k on spc$k.shipping_plan_id=$id and spc$k.country_code=c.code ";
	}
	$q = "select c.code, t.value
		from country c
		left join seller_country sc on sc.country_id=c.id
		$joins
		join translation t on c.id=t.id
			and t.table_name='country' and t.field_name='name' 
		where 1 and t.language='$lang'
		and ($seller_id=0 or sc.seller_id=$seller_id)
		order by c.ordering";
//	echo '<br>'.$q;
	$countries = $dbr->getAssoc($q);
	if (PEAR::isError($countries)) { aprint_r($countries); die(' DIED!');}
    return $countries;
}

function allCountrieseBay($db, $dbr)
{
	$list = $dbr->getAssoc("select distinct siteid, cav.description from config_api ca
		join config_api_values cav on ca.siteid=cav.value and cav.par_id=5
		where siteid <> ''  and cav.inactive=0");
    return $list;
};
/**
 * @return string
 * @param integer $siteId
 * @desc Returns country code of particular eBay site
*/
function siteToCountryCode ($siteId)
{
    static $map = array(
        0=>'US',
        2=>'CA',
        3=>'UK',
        15=>'AU',
        16=>'AT',
        23=>'BE',
        71=>'FR',
        77=>'DE',
        101=>'IT',
        123=>'BE',
        146=>'NL',
        186=>'ES',
        193=>'CH',
        100=>'US',
        212=>'PL',
    );
    return $map[$siteId];
}

function CountryCodeToSite($country)
{
    static $map = array(
        0=>'US',
        2=>'CA',
        3=>'UK',
        15=>'AU',
        16=>'AT',
        23=>'BE',
        71=>'FR',
        77=>'DE',
        101=>'IT',
        123=>'BE',
        146=>'NL',
        186=>'ES',
        193=>'CH',
        100=>'US',
    );
    return array_search($country, $map);
}

/**
 * @return string
 * @param integer $siteId
 * @desc Returns currency symbol used by particular eBay site
*/
function siteToSymbol ($siteId, $fld='description')
{
	global $db, $dbr;
	$res = $dbr->getRow("SELECT ca.siteid, ca.value, cav.description FROM config_api ca
		LEFT join config_api_values cav on ca.par_id=cav.par_id and ca.value=cav.value
		where ca.par_id =7 and siteid='$siteId'");
    return $res->$fld;
    static $map = array(
        0 =>  '$',
        2 =>  'C $',
        3 =>  'GBP',
        15=>  'AU $',
        16 => 'EUR',
        23 => 'EUR',
        71 => 'EUR',
        77 => 'EUR',
        101 => 'EUR',
        123 => 'EUR',
        146 => 'EUR',
        186 => 'EUR',
        193 => 'Fr.',
        100 => '$',
    );
    return $map[$siteId];
}

/**
 * @return unknown
 * @param unknown $siteId
 * @desc Determine if a particular eBay site only 
 * allows shipping to Europe
*/
function europeOnly($siteId)
{
    return in_array($siteId, array(16,77,186,193));
}

/**
 * @return integer
 * @param integer $siteId
 * @desc Returns currency code used by particular eBay site
*/
function siteToCurrency ($siteId)
{
    static $map = array(
        0 => 1,
        2 => 2,
        3 => 3,
        15 => 5,
        16 => 7,
        23 => 7,
        71 => 7,
        77 => 7,
        101 => 7,
        123 => 7,
        146 => 7,
        186 => 7,
        193 => 13,
        196 => 41,
        100 => 1,
    );
    return $map[$siteId];
}

/**
 * @return string
 * @param string $tpl
 * @param array $vars
 * @desc Substitutes template variable
*/
function substitute($tpl, $vars)
{
    if (count($vars)) foreach ($vars as $name=>$value) {
        $template='/(?U)\[\[('.$name.')((\|([^\]]+))?)\]\]/e';
		if (is_a($value,'DB_Error')) {echo $name; print_r($value);}; 
//        $tpl=preg_replace($template," \$value ? \$value : '\\4'",$tpl);
//    	if (PEAR::isError($tpl)) print_r($tpl);
//    	if (PEAR::isError($template)) print_r($template);
        $tpl=preg_replace($template," \$value",$tpl);
    }
    return $tpl;
}

/**
 * @desc Calculates order totals
 *
 * @return boolean
 * @param object $auction
 * @param object $offer
 * @param array $input
 * @param array $groups
 * @param array $result
 * 
 */
// 20050607 {
function calcTotals($db, $dbr, $auction, $offer, $input, $input_prices, &$groups, &$result
, $country_shipping, $admin_items, $payment, $zip_shipping='', $admin=0, $promo, $basegroupsmode='')
{
	global $debug;
//print_r($promo);
//aprint_r($input);
    require_once 'lib/ShippingPlan.php';
    require_once 'lib/Zip.php';
    require_once 'lib/Auction.php';
    require_once 'lib/SellerInfo.php';
    global $english;
    $subtotal = 0;
	if ($debug) echo '$subtotal='.$subtotal.'<br>';
	if ($debug) print_r($input_prices);
    $subtotal_basegroup = 0; $basegroup_articles = array();
    $shipping = 0;
    $haserrors = false;
    $mainq = false;
    $another_country = false;
	$lang = $auction->getMyLang();
	$siteid = $auction->get('siteid');
	$seller_channel_id = $dbr->getOne("select seller_channel_id from seller_information
			where username='".$auction->data->username."'");
	if ($seller_channel_id==3) {
			$type = 'a';
	} else {
		switch($auction->get('txnid')) {
			case 1: $type = '';
			break;
			case 3: $type = 's';
			break;
			case 0: $type = '';
			break;
			default: $type = 'f';
			break;
		}
	}
	$q = "SELECT value
				FROM translation
				WHERE table_name = 'offer'
				AND field_name = '".$type."shipping_plan_id'
				AND language = '$siteid'
				AND id = ".(int)$offer->get('offer_id');
	if ($debug) echo $q.'<br>';
	$shipping_plan_id = $dbr->getOne($q);
	$q = "SELECT value
				FROM translation
				WHERE table_name = 'offer'
				AND field_name = '".$type."shipping_plan_free_tr'
				AND language = '$siteid'
				AND id = ".(int)$offer->get('offer_id');
	$shipping_plan_free = $dbr->getOne($q);
	if ($debug) echo $q.'<br>';
//	$shipping_plan_id = $offer->get('shipping_plan_id');

	$sellerInfo = SellerInfo::singleton($db, $dbr, $auction->get('username'));
	if ($offer->get($type.'shipping_plan_free')) $shipping_plan_free = 1;
	$costs = (array) ShippingPlan::getCostsByCountry($db, $dbr, $shipping_plan_id, $country_shipping);
	if ($debug) { echo  'shipping_plan_id='.$shipping_plan_id.', for country '.$country_shipping;} 
	$defcosts = (array) ShippingPlan::getCostsByCountry($db, $dbr, $shipping_plan_id, $sellerInfo->get('defshcountry'));
	if ($debug) { echo 'shipping_plan_free='.$shipping_plan_free.'<br>'; echo 'Costs: '.print_r($costs, true).'<br>'.'Defcosts: '.print_r($defcosts, true).'<br>'; }
	if ($shipping_plan_free) foreach ($costs as $shkey=>$value) {
		if ($shkey!='COD_cost') $costs[$shkey] -= $defcosts[$shkey]; if ($costs[$shkey]<0) $costs[$shkey]=0;
	};

	if ((! $costs) && (in_array($payment, array('1', '2', 'cc_shp','pofi_shp','bill_shp','bean_shp', 'pp_shp', 'sofo_shp', 'gc_shp', 'inst_shp')))) {
        $another_country = true;
    }
	if ($debug) file_put_contents('group',print_r($groups, true));
    foreach ($groups as $i => $group) {
        $noshipping = false;
        $groupshipping = 0;
        $groups[$i]->grouperros = '';
        $itemsingroup = 0;
        foreach ($group->articles as $j => $article) {
			if ($debug) { echo 'Article '.print_r($article, true).'<br>'; }
            $itemsingroup += $input[$article->article_list_id];
            if ($group->main) {
                if ($qu = $input[$article->article_list_id]) {
                    $subtotal += $qu * $input_prices[$article->article_list_id];
					if ($debug) echo '$subtotal='.$subtotal.'<br>';
//					echo $subtotal .'+= '.$qu.' * '.$input_prices[$article->article_list_id].'<br>';
                }
            } else {
                $subtotal += $input_prices[$article->article_list_id] * $input[$article->article_list_id];
				if ($debug) echo '$subtotal='.$subtotal.'<br>';
				if ($group->base_group_id && (is_array($promo->sa_array) && count($promo->sa_array))
						&& !$promo->basegroup) {
					$basegroup_articles[] = $article->article_list_id;
					$subtotal_basegroup += $input_prices[$article->article_list_id] * $input[$article->article_list_id];
				}
            }
            $quantity1 = $quantity = $input[$article->article_list_id];
			if ($debug) { echo $groupshipping . '+=(' .$article->shipping_cost .'+'.$article->island_cost.'+'.$article->additional_shipping_cost .")*$quantity<br>"; }
			if ($article->main) {
				if ($debug) { echo '1(main) ('.$article->shipping_cost.' + '.$article->additional_shipping_cost
					.'+'.(isZipInRange($db, $dbr, $country_shipping, 'islands', $zip_shipping) ? $article->island_cost : 0).') * '.$quantity.'<br>'; }
	            $groupshipping += ($article->shipping_cost + $article->additional_shipping_cost
					+(isZipInRange($db, $dbr, $country_shipping, 'islands', $zip_shipping) ? $article->island_cost : 0)) * $quantity;
			} elseif ($article->additional){
				if ($debug) echo '2(add) ';
	            	  if (!$article->noship) $groupshipping += $article->additional_cost * $quantity;
			} else	 {
				if ($debug) echo '3(neither nor) ';
	            $groupshipping += $article->additional_shipping_cost * $quantity;
			}	
//            $groupshipping += (!$article->additional ? $article->additional_shipping_cost : $article->shipping_cost) * $quantity;
            if ($group->main && $quantity) {
                $quantity--;
            }
            if ($quantity1 >= $group->noshipping_same && $group->noshipping_same && ($auction->get('quantity')==1)) {
                $noshipping = true;
				if ($debug) echo '1) $noshipping = true;';
            }
        }
        if (!$itemsingroup && $group->main && !$admin/* && count($group->articles)>1*/) {
            $groups[$i]->grouperros .= $english[91] . '<br>';
            $haserrors = true;
        }
		if ($debug) echo "$itemsingroup >= $group->noshipping but ".$auction->get('quantity')."==1<br>";
        if ($itemsingroup >= $group->noshipping && $group->noshipping && ($auction->get('quantity')==1)) {
            $noshipping = true;
			if ($debug) echo '2) $noshipping = true;';
        }
        if ($group->main) {
            //$groupshipping += $offer->data->shipping_cost;
//            $groupshipping += $costs['shipping_cost']
//				+(isZipInRange($db, $dbr, $country_shipping, 'islands', $zip_shipping) ? $costs['island_cost'] : 0);
        }
        if ($noshipping || $shipping_plan_free) {
#            $groupshipping = 0;
        } 
        $shipping += $groupshipping;
	}
    if (count($admin_items)) foreach ($admin_items as $item) {
        if ($item->deleted) {
		} else {
            $subtotal += $item->quantity * $item->price;
        }
    }
	if ($debug) echo '$subtotal='.$subtotal.'<br>';
	if ($debug) echo '$shipping='.$shipping.'<br>';
//	$shopCatalogue = new Shop_Catalogue($db, $dbr);
	$cod = /*$payment==1 ? 0 : */$costs['COD_cost'];
    $total_cod = $subtotal + $shipping + $cod;
    $total_shipping = $subtotal + $shipping ;
	if ($promo->percent>0 /*|| count($promo->disco_articles_amt)*/) {
		$promo_input_prices = array();
		$promo_amount = 0;//$promo->amount;
		foreach ($input_prices as $article_list_id=>$price) {
			if (!in_array($article_list_id, $basegroup_articles)) {
				$article_id = $dbr->getOne("select article_id from article_list where article_list_id=".$article_list_id);
				$newprice = round($price*(1-$promo->percent/100), 2);
				$newprice = round($newprice*(1-(int)$promo->disco_articles_perc[$article_id]/100), 2)
					/*- (int)$promo->disco_articles_amt[$article_id]*/;
				$promo_input_prices[$article_list_id] = $newprice;
				$promo_amount += $price - $newprice;
				$subtotal -= $input[$article_list_id]*($price - $newprice);
				$total_cod -= $input[$article_list_id]*($price - $newprice);
				$total_shipping -= $input[$article_list_id]*($price - $newprice);
			} // if not basegroup
		} // foreach input
	} // if percent discount
	if ($debug) echo '$subtotal6='.$subtotal.'<br>';
	$max_allowed_disco = 0;
	if ($promo || $promo->amount) {
		$max_allowed_disco = $subtotal-$subtotal_basegroup;
	}
    $result = compact('subtotal','shipping','total_cod','total_shipping','another_country', 'cod'
		, 'promo_input_prices', 'promo_amount', 'max_allowed_disco');
if ($debug)		print_r($result);
//echo $costs['COD_cost'].'; subtotal='.$subtotal.'; shipping='.$shipping.'; total_cod='.$total_cod.'; total_shipping='.$total_shipping.'<BR>';
    return $haserrors;
}
// 20050607 }

/**
 * @return void
 * @param object $db
 * @param object $auction
 * @param object $offer
 * @param array $groups
 * @param array $input
 * @param array $personal
 * @param array $payment
 * @param boolean $resend
 * @desc Writes order details to database
*/
function completeAuction(
    $db, $dbr, $auction, $offer, $groups, 
    $input, $input_old, $input_pos, $input_prices, $input_titles, $input_descriptions, 
    $personal, $payment, $custom_shipping, $custom_cod, $custom_cc_fee, $custom_vat, $admin_items, $resend = true,
	$bonus=array(), $promo, $total_input=array()
)
{
	global $debug;
$time = getmicrotime();
if ($debug) echo 'CA 0: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
//	print_r($promo); //die();
    require_once 'lib/Article.php';
    require_once 'lib/ArticleHistory.php';
    require_once 'lib/Order.php';
    require_once 'lib/Group.php';
    require_once 'lib/Invoice.php';
    require_once 'lib/SellerInfo.php';
    require_once 'lib/AuctionLog.php';
//	include 'login.php';
	global $loggedUser;
    $sellerInfo = SellerInfo::singleton($db, $dbr, $auction->get('username'));
    $order = array();
//	$r = $dbr->getAssoc("select distinct article_id, article_id from article_history where auction_number=".$auction->get('auction_number')
//		." and txnid=".$auction->get('txnid'));
//	if (PEAR::isError($r)) aprint_r($material);
//	foreach ($r as $article_id) ArticleHistory::stockRecals($db, $dbr, $article_id);
//	$r = $db->query("delete from article_history where auction_number=".$auction->get('auction_number')
//		." and txnid=".$auction->get('txnid'));
//	if (PEAR::isError($r)) aprint_r($r);
//	print_r($total_input); die();
	if (count($total_input)) {
		foreach($total_input as $ain => $input_article) {
//			if ($input_article->admin==3) continue;
			if ($input_article->admin) {
				$articleCur = new Article($db, $dbr, $input_article->article_id, -1, 0);
			} else { 
				$articleCur = new Article($db, $dbr, $input_article->article_id);
			}
			if (!$input_article->article_list_id) {
				$input_article->article_list_id =
					Group::addArticle($db, $dbr, 0, $input_article->article_id, 0, $input_article->price, 0, $input_article->quantity, 1);
			}
			$newprice = (1-$promo->percent/100)*$input_article->price;
			$newprice = round($newprice*(1-(int)$promo->disco_articles_perc[$input_article->article_id]/100), 2)
				/*- (int)$promo->disco_articles_amt[$input_article->article_id]*/;
		    $order[] = array(
	    		$input_article->title,
				$input_article->quantity,
				$newprice,
				$input_article->article_id,
				$input_article->admin,
	        	$input_article->description,
				$input_article->article_list_id,
				0, // hidden
				$input_article->pos, // position
				$input_article->reserve_warehouse_id, 
				$input_article->send_warehouse_id,
				$input_article->id,
				$input_article->alias_id,
				$input_article->oldprice,
				$input_article->code_id_free,
			);
//			echo $input_article->article_id; /*print_r($articleCur);*/ echo '<br>';
//			echo 'Count of materials: '.count($articleCur->materials).'<br>';
			if (count($articleCur->materials)) foreach ($articleCur->materials as $mat) {
//				echo 'Add material '.$mat->article_id.'<br>';
			    $order[] = array(
		    		NULL, // title
		    	    ceil($input_article->quantity / ((int)$articleCur->data->items_per_shipping_unit)) * $mat->shm_quantity , // qnty
			        0, // price
			        $mat->article_id,
		    	    $input_article->admin,
		        	NULL, // descr
					$input_article->article_list_id,
					1, // hidden
					$input_article->pos, // position
					$input_article->reserve_warehouse_id, 
					$input_article->send_warehouse_id,
					0, // id
					0, // $input_article->alias_id,
					0, // oldprice
				);
			}	// foreach mat
		} // foreach total
#		die();
	} else {
		$warehouse_id = 0;
		if (!(int)$warehouse_id && $auction->get('offer_id')) $warehouse_id = $offer->get('default_warehouse_id');
		if (!(int)$warehouse_id) $warehouse_id = $sellerInfo->get('default_warehouse_id');
		if (!(int)$warehouse_id) $warehouse_id = Warehouse::getDefault($db, $dbr);
	    $reserve_warehouse_id = $warehouse_id;
	    $send_warehouse_id = $warehouse_id;
	    foreach ($groups as $group) {
	        foreach ($group->articles as $article) {
	            if ($input[$article->article_list_id]) {
			        $articleCur = new Article($db, $dbr, $article->article_id);
//					echo $article->article_id; /*print_r($articleCur);*/ echo '<br>';
//					echo 'Count of materials: '.count($articleCur->materials).'<br>';
		            $order[] = array(
	    	            $article->article->data->name == $input_titles[$article->article_list_id] ? NULL : $input_titles[$article->article_list_id],
	        	        $input[$article->article_list_id],
	            	    (1-(int)$promo->disco_articles_perc[$article->article_id]/100)
							*(1-$promo->percent/100)*(
							$group->main ? $input_prices[$article->article_list_id] : $input_prices[$article->article_list_id]
							)
							/*- (int)$promo->disco_articles_amt[$article->article_id]*/,
		                $article->article_id,
	    	            0,
	        	        $article->article->data->description == $input_descriptions[$article->article_list_id] ? NULL : $input_descriptions[$article->article_list_id],
						$article->article_list_id,
						0, // hidden
						$input_pos[$article->article_id.':'.$input[$article->article_list_id]], // position
						$reserve_warehouse_id, // position
						$send_warehouse_id, // position
						0, // id
						$article->alias_id,
						0, // oldprice
		            );
					if (count($articleCur->materials)) foreach ($articleCur->materials as $mat) {
//						echo 'Add material '.$mat->article_id.'<br>';
		        	    $order[] = array(
	    		            NULL, // title
	    	    	        ceil($input[$article->article_list_id]/((int)$articleCur->data->items_per_shipping_unit)) * $mat->shm_quantity , // qnty
		            	    0, // price
		                	$mat->article_id,
	    	        	    0,
	        		        NULL, // descr
							$article->article_list_id,
							1, // hidden
							$input_pos[$article->article_id.':'.$input[$article->article_list_id]], // position
							$reserve_warehouse_id, // position
							$send_warehouse_id, // position
							0, // id
							0, // alias_id
							0, // oldprice
			            );
					}	// foreach mat
				}	// if quantity
	        }  // foreach articles
	    } // foreach group
	//	print_r($admin_items);
	    if (count($admin_items)) foreach ($admin_items as $pos=>$item) {
	        if ($item->deleted) continue;
	        $articleCur = new Article($db, $dbr, $item->article_id, -1, 0);
	        if (PEAR::isError($articleCur)) aprint_r($articleCur);
	        if (!PEAR::isError($articleCur->_error) /*&& strlen($articleCur->get('article_id'))*/) {
	            if ($item->quantity) {
					if (!$item->article_list_id) {
						$item->article_list_id =
				    		Group::addArticle($db, $dbr, 0, $item->article_id, 0, $item->price, 0, $item->quantity, 1);
					}
			        $order[] = array(
	        		    $item->custom_title,
			            $item->quantity,
	        		    $item->price,
			            $item->article_id,
			            $item->admin_id,
			            $item->custom_description,
						$item->article_list_id,
						0, // hidden
						$input_pos[$item->article_id.':'.$item->quantity],//$item->position
						$reserve_warehouse_id,
						$send_warehouse_id,
						0, // id
						0, //alias_id
						0, // oldprice
			        );
					if (count($articleCur->materials)) foreach ($articleCur->materials as $mat) {
		        		    $order[] = array(
	    		        	    NULL, // title
	    	    	        	ceil($item->quantity/(int)$articleCur->data->items_per_shipping_unit) * $mat->shm_quantity , // qnty
			            	    0, // price
			                	$mat->article_id,
	    		        	    0,
	        			        NULL, // descr
								$item->article_list_id,
								1, // hidden
								$input_pos[$item->article_id.':'.$item->quantity],//$item->position
								$reserve_warehouse_id, // position
								$send_warehouse_id, // position
								0, // id
								0, //alias_id
								0, // oldprice
				            );
					} // foreach sh_mat
				}	
			}; // not admin article	
	    }
	} // if no total_input
//	print_r($order); //die();
if ($debug) echo 'CA 1: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
    Order::Create($db, $dbr, $auction->get('auction_number'), $auction->get('txnid'), $order);
if ($debug) echo 'CA 2: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
    /*if ($resend) */{
        $auction->set('process_stage', STAGE_ORDERED);
        $auction->set('status_change', date('Y-m-d H:i:s'));
    }
    $country_shipping = $personal['country_shipping'];
    if (isset($personal['country_shipping'])) $personal['country_shipping'] = countryCodeToCountry($personal['country_shipping']);
    $personal['country_invoice'] = countryCodeToCountry($personal['country_invoice']);
    $auction->set('payment_method', $payment);
    $invoice = new Invoice($db, $dbr, $auction->get('invoice_number'));
    $result = array();
if ($debug) echo 'CA 3: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
    calcTotals($db, $dbr, $auction, $offer, $input, $input_prices, $groups, $result, $country_shipping, $admin_items, $payment, $personal['zip_shipping']
	, 0, $promo);
	if ($sellerInfo->get('free_shipping')) {	
		$total_shipping -= $shipping;
		$shipping = 0;
	};
if ($debug) echo 'CA 4: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
if ($debug) 	print_r($result);
    extract($result);
#	if ($auction->get('auction_number')==24396) echo '!$subtotal inside = '.$subtotal.'<br>';
	$q = "select count(*) master, sum(total_price) total_price
		, sum(total_shipping) total_shipping
		, sum(total_cod) total_cod
		from invoice 
		join auction on invoice.invoice_number=auction.invoice_number
		where deleted=0 and main_auction_number=".$auction->get('auction_number')."
		and main_txnid=".$auction->get('txnid')." 
		";
//	echo $q.'<br>';
	$subinvoices = $dbr->getRow($q);
if ($debug) echo 'CA 44: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
#	if ($auction->get('auction_number')==24396) print_r($subinvoices);
	$shipping_subinvoices = 0;	
	if ($subinvoices->master) {
		$subtotal += $subinvoices->total_price;
		$total_shipping += $subinvoices->total_price+$subinvoices->total_shipping;
		$shipping_subinvoices += $subinvoices->total_shipping;
		$total_cod += $subinvoices->total_price+$subinvoices->total_shipping+$subinvoices->total_cod;
//		print_r($subinvoices);
	}
#	if ($auction->get('auction_number')==24396) echo '!$subtotal inside complete = '.$subtotal.'<br>';
	$total_bonus = 0;
if ($debug) echo 'CA 5: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
    if (count($bonus)) {
		$order = array();
		foreach($bonus as $key=>$rec) {
			if ($rec<0) continue;
			$q = "select id,shop_id,percent,description_url,article_id,ordering,
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_bonus'
				AND field_name = 'title'
				AND language = '".$auction->get('lang')."'
				AND id = shop_bonus.id) as title, 
			(SELECT `value`
				FROM translation
				WHERE table_name = 'shop_bonus'
				AND field_name = 'description'
				AND language = '".$auction->get('lang')."'
				AND id = shop_bonus.id) as description
				, send_notification
				from shop_bonus where id=$key";
			$bonus_rec = $dbr->getRow($q);
			//$amount = $subtotal*($bonus_rec->percent/100);
			$amount = $rec;
			$total_bonus += $amount;
			$article_list_id = Group::addArticle($db, $dbr, 0, $bonus_rec->article_id, 0, $amount, 0, 1, 1);
			$order[] = array(
	        		    $bonus_rec->title,
			            1, //quantity,
	        		    $amount,
			            $bonus_rec->article_id,
			            2, // admin_id
			            $bonus_rec->title,
						$article_list_id,
						0, // hidden
						900+$key//$item->position
			        );
			if ($bonus_rec->send_notification) {
				standardEmail($db, $dbr, $auction, 'bonus_usage_notification', $bonus_rec->title);
			}
	    }
	    Order::Create($db, $dbr, $auction->get('auction_number'), $auction->get('txnid'), $order, false);
	}
if ($debug)	echo 'SUB $subtotal inside complete = '.$subtotal.'<br>';
if ($debug) echo 'CA 6: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
	$orig_amount = $promo->amount;
if ($debug)	echo '$promo->amount0 = '.$promo->amount.'<br>';
	if (isset($promo->dtotal)) $promo->amount = $promo->dtotal;
if ($debug)	echo '$promo->amount1 = '.$promo->amount.'<br>';
	if ($promo && $orig_amount && !$promo->notforshipping) {
    	if ($payment != 3 && $payment != 4) {
	        if ($custom_shipping!=='') {
				$promo->amount += $custom_shipping;
	        } else {
				$promo->amount += $total_shipping - $subtotal;
	        }
		}
	}
	if ($promo->amount>$orig_amount) $promo->amount=$orig_amount;
if ($debug)	echo '$promo->amount2 = '.$promo->amount.'<br>';
	if ($promo && $promo->amount && !$promo->notforbonus) {
		$promo->amount += $total_bonus;
	}
	if ($promo->amount>$orig_amount) $promo->amount=$orig_amount;
if ($debug)	echo '$promo->amount3 = '.$promo->amount.'<br>';
	if ($promo->amount && count($promo->free_articles)) {
		foreach($promo->free_articles as $ain=>$article) {
			$promo->amount += $article->new_price*$article->quantity;
		}
	}
	if ($promo->amount>$orig_amount) $promo->amount=$orig_amount;
if ($debug)	echo '$promo->amount4 = '.$promo->amount.'<br>';
    if ($promo->addamount && (int)$promo->article_id) {
		$order = array();
		$article_list_id = Group::addArticle($db, $dbr, 0, $promo->article_id, 0, $amount, 0, 1, 1);
		$order[] = array(
	        		    $promo->id.': '.$promo->name, //'Voucher '.$promo->code,
			            1, //quantity,
	        		    -$promo->amount,
			            $promo->article_id,
			            3, // admin_id
			            $promo->name,
						$article_list_id,
						0, // hidden
						1000//$item->position
			        );
	    Order::Create($db, $dbr, $auction->get('auction_number'), $auction->get('txnid'), $order, false);
		$subtotal -= $promo->amount;
		$total_shipping -= $promo->amount;
		$total_cod -= $promo->amount;
if ($debug)	echo 'promo->amount = '.$promo->amount.'<br>';
	}
#	echo 'PROMO $subtotal inside complete = '.$subtotal.'<br>';
#	echo '$total_shipping inside complete = '.$total_shipping.'<br>';
#	echo '$total_cod inside complete = '.$total_cod.'<br>';
if ($debug) echo 'CA 7: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
	$subtotal += $total_bonus;
	$total_shipping += $total_bonus;
	$total_cod += $total_bonus;
#	echo 'BONUS $subtotal inside complete = '.$subtotal.'<br>';
	if (in_array($payment, array('cc_shp','pofi_shp','bill_shp','bean_shp','ppcc_shp','pp_shp', 'sofo_shp','gc_shp'))) {
			$_subtotal += $_shipping;
	}
if ($debug)				echo 'SHIP $subtotal inside complete = '.$subtotal.'<br>';
	if ($promo->free_articles) {
 		foreach($promo->free_articles as $ain=>$article) {
			$subtotal += $article->new_price*$article->quantity;
			$total_shipping += $article->new_price*$article->quantity;
			$total_cod += $article->new_price*$article->quantity;
		}
	}
if ($debug)				echo 'FREE $subtotal inside complete = '.$subtotal.'<br>';
    $invoice->set('total_price', $subtotal);
    $invoice->set('payment_method', $payment);
    $invoice->set('total_shipping', 0);
if ($debug)				echo "custom_shipping=$custom_shipping<br>";
if ($debug)				echo "total_shipping=$total_shipping<br>";
    if ($payment != 3 && $payment != 4) {
        if ($custom_shipping!=='') {
            $invoice->set('total_shipping', $custom_shipping);
            $invoice->set('is_custom_shipping', 1);
        } else {
            $invoice->set('total_shipping', $total_shipping - $subtotal);
if ($debug)				echo "set total_shipping=".($total_shipping - $subtotal)."<br>";
            $invoice->set('is_custom_shipping', 0);
        }
        if ($custom_cod!=='') {
            $total_cod = $total_shipping + $custom_cod;
            $invoice->set('is_custom_cod', 1);
        } else {
            $invoice->set('is_custom_cod', 0);
        }
        if (in_array($payment, array('1','cc_shp','pofi_shp','bill_shp','bean_shp','pp_shp','sofo_shp','gc_shp','inst_shp'))) {
            #$invoice->set('total_shipping', $total_shipping - $subtotal);
            $invoice->set('total_cod', 0);
        } elseif ($payment == 2) {
            #$invoice->set('total_shipping', $total_shipping - $subtotal);
            $invoice->set('total_cod', $total_cod - $total_shipping);
        } 
		$shop_id = (int)$dbr->getOne("select fget_AShop(".$auction->get('auction_number').",".$auction->get('txnid').")");
		if ($shop_id) {
			$shopCatalogue = new Shop_Catalogue($db, $dbr, $shop_id);
			$payment_row = $shopCatalogue->getPaymentByCode($payment);
		} else {
			$payment_row = $sellerInfo->getPaymentByCode($payment);
		}
        if ($custom_cc_fee!=='') {
	        $invoice->set('total_cc_fee', $custom_cc_fee);
            $invoice->set('is_custom_cc_fee', 1);
if ($debug)			echo '$custom_cc_fee='.$custom_cc_fee.'<br>';
		} else {
			if (in_array($payment, array('cc_pck','bill_pck','bean_pck','pp_pck','gc_pck'))) {
				$total4fee = $subtotal;
	        }
			if (in_array($payment, array('inst_shp', 'cc_shp', 'pofi_shp','bill_shp','bean_shp','pp_shp','sofo_shp','gc_shp'))) {
				$total4fee = $subtotal + $shipping_subinvoices + $invoice->get('total_shipping');
    	    }
			$plus_cc_fees = $payment_row->fee_amt + $total4fee * $payment_row->fee/100;
	        $invoice->set('total_cc_fee', $plus_cc_fees);
            $invoice->set('is_custom_cc_fee', 0);
		}
        if ($custom_vat!=='') {
	        $invoice->set('total_vat', $custom_vat);
            $invoice->set('is_custom_vat', 1);
if ($debug)			echo '$custom_vat='.$custom_vat.'<br>';
		} else {
			$plusvat = $dbr->getOne("SELECT MAX(ca.value)
						FROM config_api ca
						WHERE ca.par_id = 24
						AND ca.siteid = '".$auction->get('siteid')."'
						");
			if ($plusvat) {
				$q = "select IFNULL(vat_state.vat_percent, vat.vat_percent) vat_percent 
					from seller_information si 
					join vat on vat.country_code='".countryToCountryCode($personal['country_shipping'])."'
					left join vat_state on vat.states_from and vat_state.state='".$personal['state_shipping']."'
						and vat_state.country_code='".countryToCountryCode($personal['country_shipping'])."'
					where '".$invoice->data->invoice_date."' between vat.date_from and vat.date_to 
					and vat.country_code_from='".$sellerInfo->get("defshcountry")."'
					LIMIT 0,1";
		//		echo $q;
				$vat = 1*$dbr->getOne($q);
				$invoice->data->total_vat = ($invoice->data->total_price 
					+ $invoice->data->total_shipping
					+ $invoice->data->total_cod
					+ $invoice->data->total_cc_fee)*$vat/100;
			} else {
	            $invoice->set('total_vat', 0);
			}
            $invoice->set('is_custom_vat', 0);
		}
    } else {
        $invoice->set('is_custom_shipping', 0);
        $invoice->set('is_custom_cod', 0);
    }
    $invoice->set('details', serialize($order));
    if (!$auction->get('invoice_number')) {
        $invoice->set('invoice_date', date('Y-m-d')/*$auction->get('end_time')*/);
    }
#	echo '<br>$total_cod='.$total_cod;
#	echo '<br>$total_shipping='.$total_shipping;
if ($debug) echo 'CA 8: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
    $invoice->update();
//	print_r($invoice->data);
    $auction->data->invoice_number = $invoice->get('invoice_number');
#	echo 'Set invoice number for '.$auction->get("auction_number").' to '.$invoice->get('invoice_number').'<br>';
    $auction->update();
#	echo ' Result is '.$dbr->getOne("select invoice_number from auction where invoice_number = ".$invoice->get('invoice_number')).'<br>';
	$vat_info = VAT::get_vat_attribs($db, $dbr, $auction);
    $auction->set('eu', $vat_info->eu);
/// MAIN AU
	if ($debug) {echo 'PERSONAL'; print_r($personal);}
		if ((int)$auction->get("main_auction_number")) {
			$mainAuction = new Auction($db, $dbr, $auction->get("main_auction_number"), $auction->get("main_txnid"));
		} else {
			$mainAuction = new Auction($db, $dbr, $auction->get("auction_number"), $auction->get("txnid"));
		}
	    foreach ($personal as $field => $value) {
	        $mainAuction->set($field, $value);
	        $auction->set($field, $value);
	    }
		if ($invoice->get('open_amount')>0) {
		} else {
			$mainAuction->data->paid = 1; // if TOTAL=0 set auction as PAID
			$auction->data->paid = 1; // if TOTAL=0 set auction as PAID
		}	
	    if (!$personal['when']) {
	        $mainAuction->set('delivery_date_customer', '0000-00-00');
	        $auction->set('delivery_date_customer', '0000-00-00');
	    } else {
	        $mainAuction->set('delivery_date_customer', $personal['shipon']);
	        $auction->set('delivery_date_customer', $personal['shipon']);
	    }
	    $mainAuction->set('payment_method', $payment);
	    $auction->set('payment_method', $payment);
/// MAIN AU
if ($debug) { echo 'MAINAU:'; print_r($mainAuction->data);}
if ($debug) echo 'CA 9: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
	$invoice->recalcOpenAmount();
if ($debug) echo 'CA 10: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
//	echo $auction->get('invoice_number').'!='.$mainAuction->get('invoice_number');
    if ($auction->get('auction_number')!=$mainAuction->get('auction_number') && $mainAuction->get('invoice_number')) {
		$invoice = new Invoice($db, $dbr, $mainAuction->get('invoice_number'));
		$invoice->recalcOpenAmount();
	}
if ($debug) echo 'CA 11: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
	$invoice = new Invoice($db, $dbr, $auction->get('invoice_number'));  // re-read invoice
if ($debug) echo 'CA 11 new invoice: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
	$auction->data->paid = (int)($invoice->get('open_amount')<=0);
	$mainAuction->data->paid = (int)($invoice->get('open_amount')<=0);
	$mainAuction->update();
if ($debug) echo 'CA 11 auction update: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
    $auction->update();
if ($debug) echo 'CA 11 auction update: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
#	echo ' Result is '.$dbr->getOne("select invoice_number from auction where invoice_number = ".$invoice->get('invoice_number')).'<br>';
#	print_r($invoice->data); echo ' Paid: '.(int)$mainAuction->data->paid.'<br>';
#	print_r($auction->data);
if ($debug) echo 'CA 11 0: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
    if ($resend && !(int)$auction->get('no_emails')) {
		$method = $auction->get('payment_method');
        if ($method=='bill_shp') {
		} else {
	        standardEmail($db, $dbr, $auction, 'order_confirmation');
		}
		if (!$sellerInfo->get('dontsend_ruckgabebelehrung')) {
			standardEmail($db, $dbr, $auction, 'send_Ruckgabebelehrung');
		}	
		$notify_using = $dbr->getOne("select notify_using from payment_method where `code`='$method'");
		if ($notify_using && strlen($sellerInfo->get('payment_notify_using_email'))) {
			standardEmail($db, $dbr, $auction, 'payment_notification'); 
		}
		$payment_instruction_template = $dbr->getOne("select email_template from payment_method where `code`='".$auction->get('payment_method')."'");
        if ($method=='bill_shp') {
		} else {
			if (strlen($payment_instruction_template) && !(int)$auction->data->paid) {
				standardEmail($db, $dbr, $auction, $payment_instruction_template); 
        	}
		}
    }
if ($debug) echo 'CA 11 1: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
	calcAuction($db, $dbr, $auction);
if ($debug) echo 'CA 11 2: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
	$subinvoices = $dbr->getAll("select auction_number, txnid from auction
		where main_auction_number=".$auction->get('auction_number')."
		and main_txnid=".$auction->get('txnid')." 
		");
	foreach($subinvoices as $subinvoice){
		$sub_au = new Auction($db, $dbr, $subinvoice->auction_number, $subinvoice->txnid);
		calcAuction($db, $dbr, $sub_au);
	}

//	$auction->stock_recalc();
if ($debug) echo 'CA 12: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
	AuctionLog::Log($db, $dbr,  $auction->get('auction_number'), $auction->get('txnid'), 
		$loggedUser ? $loggedUser->get('username') : 'customer', 'change');
if ($debug) echo 'CA 13: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();

	$q = "update auction set 
		dontsend_marked_as_Shipped=
		(select dontsend_marked_as_Shipped from payment_method where code=auction.payment_method)
		*
		(select dontsend_marked_as_Shipped from source_seller where id=auction.source_seller_id)
		where auction_number=".$auction->get('auction_number')." and txnid=".$auction->get('txnid');
	$db->query($q);
	unset($mainAuction);	
	
}

/**
 * @return string
 * @param array $order
 * @desc Formats items list
*/
function formatItemsList($order, $currCode='EUR', $lang='german')
{
    $width = 90;
    $ret = '';
	global $db, $dbr;
    foreach ($order as $k=>$item) {
        $ret .= "\n";
		if ($item->saved_id != $order[$k-1]->saved_id && !$item->manual) {
	        $ret .= $item->ShopDesription."\n\n";
	        $ret .= $item->available_text."\n\n";
		}
		if ($item->alias_id) {
			$name = 'A'.$item->alias_id.': '.$item->alias_name;
		} else {
	        $name = $item->custom_title ? $item->custom_title : $item->name;
		}
        $lines = explode("\n",wordwrap($name, $width - 15, "\n", true));
        $ret .= str_pad($lines[0], $width - 19, ' ', STR_PAD_RIGHT);
        $ret .= str_pad($item->quantity, 4, ' ', STR_PAD_LEFT) . ' x ' ;
        $ret .= $currCode.' ' . str_pad($item->price, 8, ' ', STR_PAD_LEFT);
//        $ret .= "\n";
        for ($i = 1; $i<count($lines); $i++) {
            $ret .= $lines[$i] . "\n";
        }
		if ($item->saved_id != $order[$k+1]->saved_id && isset($order[$k+1]->saved_id)) {
		    $ret .= "\n".str_repeat('=', $width)."\n";
		}
    }
    $ret .= "\n".str_repeat('=', $width)."\n";
    return $ret;
}

function formatCommentHistory($db, $dbr, $ins_id)
{
    $hist = $dbr->getAll("SELECT * 
    	  FROM (
	       SELECT ins_comment . * , IFNULL( users.name, ins_comment.username ) name 
	       FROM ins_comment 
	       LEFT JOIN users ON ins_comment.username = users.username
	       WHERE ins_id = $ins_id
	       ORDER BY id DESC 
	       LIMIT 1 , 100
	       )t
	       ORDER BY ID");
    $ret = '';
    foreach ($hist as $rec) {
        $ret .= "\n";
        $ret .= $rec->create_date.' '.$rec->name.' wrote:';
        $ret .= "\n";
        $ret .= $rec->comment;
        $ret .= "\n";
    }
    $ret = str_replace("<br>", "\n", $ret);
    $ret = str_replace("<br />", "\n", $ret);
    return $ret;
}

function formatInventory($db, $dbr, $company_id)
{
require_once 'lib/op_Order.php';
require_once 'lib/ArticleHistory.php';
	$reserve = (int)Config::get($db, $dbr, 'op_reserve');
	$autos = op_Auto::listAll($db, $dbr, 'bysupplier', NULL, $company_id, 'non-rep');
	$sups = op_Order::listCompaniesAll($db, $dbr, NULL, 'active');
	if (count($autos)) foreach ($autos as $id => $line) {
		$autos[$id]->article_available = $autos[$id]->available[0];
		if (($autos[$id]->desired_daily < 0)) {
		   $autos[$id]->sales_per_day = $autos[$id]->sales_in_period = 'STOP';
		} else {
			$autos[$id]->sales_per_day =  max(((int)(ArticleHistory::getSoldCountMonth($db, $dbr, $autos[$id]->article_id, 1)/0.3)/100), $autos[$id]->desired_daily);
			$autos[$id]->sales_in_period = $autos[$id]->sales_per_day*$autos[$id]->period;
		}	
		if ($autos[$id]->sales_per_day == 'STOP') $autos[$id]->quantity_to_order = 0;
		else
		  $autos[$id]->quantity_to_order = ($autos[$id]->sales_per_day * ($autos[$id]->period+$reserve)) 
			- ($autos[$id]->article_available
			+ $autos[$id]->order_in_prod_qnt
			+ $autos[$id]->order_on_way_qnt);
		if ($autos[$id]->active && $autos[$id]->quantity_to_order>0)
			  $autos[$id]->bgcolor = Config::get($db, $dbr, 'op_active_color'); 
		elseif (!$autos[$id]->active && $autos[$id]->quantity_to_order>0)
			  $autos[$id]->bgcolor = Config::get($db, $dbr, 'op_passive_color'); 
		elseif ($autos[$id]->active && $autos[$id]->quantity_to_order<=0)
			  $autos[$id]->bgcolor = Config::get($db, $dbr, 'op_active_noitems_color'); 
		elseif (!$autos[$id]->active && $autos[$id]->quantity_to_order<=0)
			  $autos[$id]->bgcolor = Config::get($db, $dbr, 'op_passive_noitems_color'); 
		$autos[$id]->volume_to_order = $autos[$id]->quantity_to_order * $autos[$id]->article_volume_per_single_unit;
		if ($autos[$id]->company_id && ($autos[$id]->volume_to_order>0)) {
			foreach ($sups as $idsup=>$sup) {
				if ($sups[$idsup]->id == $autos[$id]->company_id) {
					$sups[$idsup]->volume_to_order += $autos[$id]->volume_to_order;
				}	
			}
		}	
	}
    $ret = 'Supplier Article #	Last Months volume	In Stock	Available	Reserved	Orders in prod.	Orders on the way	Next EDA	Sales per day	Sales in period	Quantity to order';
        $ret .= "\n";
    foreach ($autos as $aline) {
        $ret .= $aline->supplier_article_id."	";
        $ret .= $aline->sold90_0."	";
        $ret .= $aline->pieces_0."	";
        $ret .= $aline->available_0."	";
        $ret .= $aline->reserved_0."	";
        $ret .= $aline->order_in_prod_qnt."	";
        $ret .= $aline->order_on_way_qnt."	";
        $ret .= $aline->mineda."	";
        $ret .= $aline->sales_per_day."	";
        $ret .= $aline->sales_in_period."	";
        $ret .= $aline->quantity_to_order."	";
        $ret .= "\n";
    }

	$autos = op_Auto::listAll($db, $dbr, 'bysupplier', NULL, $company_id, 'rep');
	if (count($autos)) foreach ($autos as $id => $line) {
		if ($autos[$id]->desired_daily < 0) $autos[$id]->sales_per_day = 'STOP';
		else
			$autos[$id]->sales_per_day =  max(((int)(ArticleHistory::getSoldCountMonth($db, $dbr, $autos[$id]->article_id, 1)/0.3)/100), $autos[$id]->desired_daily);
		$autos[$id]->sales_in_period = $autos[$id]->sales_per_day*$autos[$id]->period;
		  $autos[$id]->quantity_to_order = ($autos[$id]->sales_per_day * ($autos[$id]->period+$reserve)) 
			- ($autos[$id]->available_0
			+ $autos[$id]->order_in_prod_qnt
			+ $autos[$id]->order_on_way_qnt);
		if ($autos[$id]->active && $autos[$id]->quantity_to_order>0)
			  $autos[$id]->bgcolor = Config::get($db, $dbr, 'op_active_color'); 
		elseif (!$autos[$id]->active && $autos[$id]->quantity_to_order>0)
			  $autos[$id]->bgcolor = Config::get($db, $dbr, 'op_passive_color'); 
		elseif ($autos[$id]->active && $autos[$id]->quantity_to_order<=0)
			  $autos[$id]->bgcolor = Config::get($db, $dbr, 'op_active_noitems_color'); 
		elseif (!$autos[$id]->active && $autos[$id]->quantity_to_order<=0)
			  $autos[$id]->bgcolor = Config::get($db, $dbr, 'op_passive_noitems_color'); 
		$autos[$id]->volume_to_order = $autos[$id]->quantity_to_order * $autos[$id]->article_volume_per_single_unit;
		if ($autos[$id]->company_id && ($autos[$id]->volume_to_order>0)) {
			foreach ($sups as $idsup=>$sup) {
				if ($sups[$idsup]->id == $autos[$id]->company_id) {
					$sups[$idsup]->volume_to_order += $autos[$id]->volume_to_order;
				}	
			}
		}	
	}
    $ret .= '  Replacement parts:';
        $ret .= "\n";
    foreach ($autos as $aline) {
        $ret .= $aline->supplier_article_id."	";
        $ret .= $aline->sold90_0."	";
        $ret .= $aline->pieces_0."	";
        $ret .= $aline->available_0."	";
        $ret .= $aline->reserved_0."	";
        $ret .= $aline->order_in_prod_qnt."	";
        $ret .= $aline->order_on_way_qnt."	";
        $ret .= $aline->mineda."	";
        $ret .= $aline->sales_per_day."	";
        $ret .= $aline->sales_in_period."	";
        $ret .= $aline->quantity_to_order."	";
        $ret .= "\n";
    }	
    return $ret;
}

/**
 * @return string
 * @param object $db
 * @param string $auction_number
 * @desc Formats invoice
*/
function formatInvoice($db, $dbr, $auction_number, $txnid)
{
    require_once 'lib/Invoice.php';
    require_once 'lib/SellerInfo.php';
    require_once 'lib/Order.php';
    require_once 'lib/Auction.php';
    global $english;
    global $english_shop;
    $width = 90;
    $auction = new Auction ($db, $dbr, $auction_number, $txnid);
    $invoice = new Invoice ($db, $dbr, $auction->get('invoice_number'));
    $r = $dbr->getAssoc("SELECT CONCAT( auction_number, '/', txnid ), invoice_number
				FROM auction
				WHERE main_auction_number =".$auction->get('auction_number')."
				AND main_txnid =".$auction->get('txnid'));
	$english = Auction::getTranslation($db, $dbr, $auction->get('siteid'), $auction->getMyLang());
	$english_shop = Auction::getTranslationShop($db, $dbr, $auction->get('siteid'), $auction->getMyLang());
    $subinvoices = array_map('intval', (array)$r);
/*	if (count($subinvoices)) {
    	$subinvoices = implode(',', $subinvoices);
	    $r = $db->query("SELECT SUM(total_price) total_price,
			SUM(total_shipping) total_shipping,
			SUM(total_cod) total_cod
			FROM invoice WHERE invoice_number IN (".$subinvoices.")");
		$sub = $r->fetchRow();	
		$invoice->data->total_price += $sub->total_price;
		$invoice->data->total_shipping += $sub->total_shipping;
		$invoice->data->total_cod += $sub->total_cod;
	};*/
    $order = Order::listAll($db, $dbr, $auction->get('auction_number'), $auction->get('txnid'), 1, $auction->getMyLang());
    $orderBonus = Order::listBonus($db, $dbr, $auction->get('auction_number'), $auction->get('txnid'));
    $sellerInfo = SellerInfo::singleton($db, $dbr, $auction->get('username'));
	global $smarty;
	$smarty->assign('currCode', siteToSymbol($auction->get('siteid')));
	$smarty->assign('auction', $auction->data);
	$smarty->assign('invoice', $invoice->data);
	$smarty->assign('sellerInfo', $sellerInfo->data);
	$smarty->assign('english', $english);
	$smarty->assign('english_shop', $english_shop);
	$smarty->assign('order', $order);
	$result = $smarty->fetch('order_confirmation.tpl');
	return $result;
    $ret = str_pad($english[80], $width, ' ', STR_PAD_BOTH)."\r";
    $ret .= str_repeat('=', $width)."\r";
    $ret .= str_pad($sellerInfo->get('seller_name'), $width / 2, ' ', STR_PAD_RIGHT);
    $ret .= str_pad($english[81] . ' '.$auction->get('auction_number'), $width / 2, ' ', STR_PAD_LEFT)."\r";
    $ret .= str_pad($sellerInfo->get('street'), $width / 2, ' ', STR_PAD_RIGHT);
#    $ret .= str_pad($english[82] . ' '.$auction->get('email_invoice'), $width / 2, ' ', STR_PAD_LEFT);
    $ret .= "\r";
    $ret .= str_pad($sellerInfo->get('zip').' '.$sellerInfo->get('town'), $width / 2, ' ', STR_PAD_RIGHT);
    $ret .= str_pad($english[83] . ' '.$invoice->get('invoice_number'), $width / 2, ' ', STR_PAD_LEFT)."\r";
    $ret .= str_pad(countryCodeToCountry ($sellerInfo->get('country')), $width / 2, ' ', STR_PAD_RIGHT);
    $ret .= str_pad($english[84] . ' '.$invoice->get('invoice_date'), $width / 2, ' ', STR_PAD_LEFT)."\r";
    $ret .= str_pad($english[85] . ' ' . $sellerInfo->get('vat_id'), $width, ' ', STR_PAD_RIGHT)."\r";
    $ret .= str_repeat('=', $width)."\r";
    $currCode = siteToSymbol ($auction->get('siteid')); 
    $ret .= formatItemsList($order, $currCode, $auction->getMyLang());
    $subtotal = $invoice->get('total_price');
    $shipping = $invoice->get('total_shipping');
    $cod = $invoice->get('total_cod');
    if ($shipping > 0) {
        $ret .= str_pad($english[25] . ' '.$currCode.' '.number_format($subtotal,2), $width , ' ', STR_PAD_LEFT)."\r";
        $ret .= str_pad($english[26] . ' '.$currCode.' '. number_format($shipping,2), $width , ' ', STR_PAD_LEFT)."\r";
		$subtotal += $shipping;
    }
	if ($cod>0) {
		$ret .= str_pad($english[104] . ': '.$currCode.' '. number_format($cod,2), $width , ' ', STR_PAD_LEFT)."\r";
		$subtotal += $cod;
	}
    $fee = $invoice->get('total_cc_fee');
	if ($fee>0) {
		$fee_name = $dbr->getOne("select `value` from translation
			where table_name='payment_method' and field_name='fee_name'
			and language='".$auction->getMyLang()."' 
			and id=(select id from payment_method where code='".$auction->get('payment_method')."')");
		$ret .= str_pad($fee_name. ': '.$currCode.' '. number_format($fee,2), $width , ' ', STR_PAD_LEFT)."\r";
		$subtotal += $fee;
	}
/*	$shop_id = (int)$dbr->getOne("select fget_AShop(".$auction->get('auction_number').",".$auction->get('txnid').")");
	if ($shop_id) {
		$shopCatalogue = new Shop_Catalogue($db, $dbr, $shop_id);
		$payment_row = $shopCatalogue->getPaymentByCode($auction->get('payment_method'));
		if ($invoice->get('total_cc_fee')>0)
			$ret .= str_pad($payment_row->name . ': '.$currCode.' '. number_format($invoice->get('total_cc_fee'),2), $width , ' ', STR_PAD_LEFT)."\r";
	    $subtotal += $invoice->get('total_cc_fee');
	}*/
/*    foreach ($orderBonus as $bonus) {
        $ret .= str_pad($bonus->title . ' '.$currCode.' '.number_format($bonus->amount,2), $width , ' ', STR_PAD_LEFT)."\r";
        $subtotal += $bonus->amount;
    }*/
        $ret .= str_pad($english[27] . ' '.$currCode.' '. number_format($subtotal,2), $width , ' ', STR_PAD_LEFT)."\r";
    return $ret;
}

function formatBruttoIncomeXLS($calcs) {
	require_once 'Spreadsheet/Excel/Writer.php'; 
	$fn = 'tmppic/temp'.rand(100000, 999999).'.xls';
	$workbook = new Spreadsheet_Excel_Writer($fn);
	$workbook->setTempDir('tmppic/');
	$workbook->setVersion(8);
	$sheet = $workbook->addWorksheet();
	$sheet->setInputEncoding('UTF-8');

	$f_bold = $workbook->addFormat();
	$f_bold->setBold();
	$f_bold->setFgColor('white');
	$f_col_title = $workbook->addFormat();
	$f_col_title->setBold();
	$f_col_title->setFgColor('white');
//	$f_col_title->setBgColor(0);
//	$f_col_title->setFgColor(40);
//	$f_col_title->setFgColor('black');
	
	$sheet->writeString(0,0, 'Auction number', $f_col_title);
	$sheet->writeString(0,1, 'Sales date', $f_col_title);
	$sheet->writeString(0,2, 'Article', $f_col_title);
	$sheet->writeString(0,3, 'Quantity', $f_col_title);
	$sheet->writeString(0,4, 'Currency', $f_col_title);
	$sheet->writeString(0,5, 'Seller account', $f_col_title);
	$sheet->writeString(0,6, 'Country', $f_col_title);
	$sheet->writeString(0,7, 'Seller type', $f_col_title);
	$sheet->writeString(0,8, 'Price sold EUR', $f_col_title);
	$sheet->writeString(0,9, 'Ebay listing fee EUR', $f_col_title);
	$sheet->writeString(0,10, 'Additional listing fee EUR', $f_col_title);
	$sheet->writeString(0,11, 'Ebay commission EUR', $f_col_title);
	$sheet->writeString(0,12, 'VAT EUR', $f_col_title);
	$sheet->writeString(0,13, 'Netto sales price EUR', $f_col_title);
	$sheet->writeString(0,14, 'Purchase price EUR', $f_col_title);
	$sheet->writeString(0,15, 'Brutto income EUR', $f_col_title);
	$sheet->writeString(0,16, 'Shipping cost EUR', $f_col_title);
	$sheet->writeString(0,17, 'Shipping VAT', $f_col_title);
	$sheet->writeString(0,18, 'Effective shipping cost', $f_col_title);
	$sheet->writeString(0,19, 'COD cost EUR', $f_col_title);
	$sheet->writeString(0,20, 'COD VAT', $f_col_title);
	$sheet->writeString(0,21, 'Effective COD cost', $f_col_title);
	$sheet->writeString(0,22, 'Income shipping cost EUR', $f_col_title);
	$sheet->writeString(0,23, 'Cost packing matherial EUR', $f_col_title);
	$sheet->writeString(0,24, 'Revenue', $f_col_title);
	$sheet->writeString(0,25, 'Margin', $f_col_title);
	$sheet->writeString(0,26, 'Brutto income 2', $f_col_title);
	$sheet->writeString(0,27, 'Brutto income per item', $f_col_title);
	$n=1;
    foreach ($calcs as $calc) {
		$currency = siteToSymbol($calc->siteid);
		$sheet->writeString($n,0, $calc->auction_number.'/'.$calc->txnid);
		$sheet->writeString($n,1, $calc->end_time);
		$sheet->writeString($n,2, $calc->name);
 	   	$sheet->writeNumber($n,3, $calc->quantity);
		$sheet->writeString($n,4, $currency);
		$sheet->writeString($n,5, $calc->username);
		$sheet->writeString($n,6, $calc->site_country);
		$sheet->writeString($n,7, $calc->txnid_type);
			$sum_price_sold_EUR += $calc->price_sold_EUR;
 	   	$sheet->writeNumber($n,8, $calc->price_sold_EUR);
			$sum_ebay_listing_fee_EUR += $calc->ebay_listing_fee_EUR;
 	   	$sheet->writeNumber($n,9, $calc->ebay_listing_fee_EUR);
			$sum_additional_listing_fee_EUR += $calc->additional_listing_fee_EUR;
 	   	$sheet->writeNumber($n,10, $calc->additional_listing_fee_EUR);
			$sum_ebay_commission_EUR += $calc->ebay_commission_EUR;
 	   	$sheet->writeNumber($n,11, $calc->ebay_commission_EUR);
			$sum_vat_EUR += $calc->vat_EUR;
 	   	$sheet->writeNumber($n,12, $calc->vat_EUR);
			$sum_netto_sales_price_EUR += $calc->netto_sales_price_EUR;
 	   	$sheet->writeNumber($n,13, $calc->netto_sales_price_EUR);
			$sum_purchase_price_EUR += $calc->purchase_price_EUR;
 	   	$sheet->writeNumber($n,14, $calc->purchase_price_EUR);
			$sum_brutto_income_EUR += $calc->brutto_income_EUR;
 	   	$sheet->writeNumber($n,15, $calc->brutto_income_EUR);
			$sum_shipping_cost_EUR += $calc->shipping_cost_EUR;
 	   	$sheet->writeNumber($n,16, $calc->shipping_cost_EUR);
			$sum_vat_shipping_EUR += $calc->vat_shipping_EUR;
 	   	$sheet->writeNumber($n,17, $calc->vat_shipping_EUR);
			$sum_effective_shipping_cost_EUR += $calc->effective_shipping_cost_EUR;
 	   	$sheet->writeNumber($n,18, $calc->effective_shipping_cost_EUR);
			$sum_COD_cost_EUR += $calc->COD_cost_EUR;
 	   	$sheet->writeNumber($n,19, $calc->COD_cost_EUR);
			$sum_vat_COD_EUR += $calc->vat_COD_EUR;
 	   	$sheet->writeNumber($n,20, $calc->vat_COD_EUR);
			$sum_effective_COD_cost_EUR += $calc->effective_COD_cost_EUR;
 	   	$sheet->writeNumber($n,21, $calc->effective_COD_cost_EUR);
			$sum_income_shipping_cost_EUR += $calc->income_shipping_cost_EUR;
 	   	$sheet->writeNumber($n,22, $calc->income_shipping_cost_EUR);
			$sum_packing_cost_EUR += $calc->packing_cost_EUR;
 	   	$sheet->writeNumber($n,23, $calc->packing_cost_EUR);
			$sum_revenue_EUR += $calc->revenue_EUR;
 	   	$sheet->writeNumber($n,24, $calc->revenue_EUR);
		$margin = $calc->brutto_income_2*100/$calc->revenue;
 	   	$sheet->writeNumber($n,25, $margin);
			$sum_brutto_income_2_EUR += $calc->brutto_income_2_EUR;
 	   	$sheet->writeNumber($n,24, $calc->brutto_income_2_EUR);
			$sum_brutto_income_3_EUR += $calc->brutto_income_3_EUR;
 	   	$sheet->writeNumber($n,25, $calc->brutto_income_3_EUR);
	  	$n++;
	}
	$sheet->writeString($n,0, 'Total:');
	$sheet->writeNumber($n,8, $sum_price_sold_EUR, $f_bold);
	$sheet->writeNumber($n,9, $sum_ebay_listing_fee_EUR, $f_bold);
	$sheet->writeNumber($n,10, $sum_additional_listing_fee_EUR, $f_bold);
	$sheet->writeNumber($n,11, $sum_ebay_commission_EUR, $f_bold);
	$sheet->writeNumber($n,12, $sum_vat_EUR, $f_bold);
	$sheet->writeNumber($n,13, $sum_netto_sales_price_EUR, $f_bold);
	$sheet->writeNumber($n,14, $sum_purchase_price_EUR, $f_bold);
	$sheet->writeNumber($n,15, $sum_brutto_income_EUR, $f_bold);
	$sheet->writeNumber($n,16, $sum_shipping_cost_EUR, $f_bold);
	$sheet->writeNumber($n,17, $sum_vat_shipping_EUR, $f_bold);
	$sheet->writeNumber($n,18, $sum_effective_shipping_cost_EUR, $f_bold);
	$sheet->writeNumber($n,19, $sum_COD_cost_EUR, $f_bold);
	$sheet->writeNumber($n,20, $sum_vat_COD_EUR, $f_bold);
	$sheet->writeNumber($n,21, $sum_effective_COD_cost_EUR, $f_bold);
	$sheet->writeNumber($n,22, $sum_income_shipping_cost_EUR, $f_bold);
	$sheet->writeNumber($n,23, $sum_packing_cost_EUR, $f_bold);
	$sheet->writeNumber($n,24, $sum_revenue_EUR, $f_bold);
	$margin = $sum_brutto_income_2*100/$sum_revenue;
	$sheet->writeNumber($n,25, $margin, $f_bold);
	$sheet->writeNumber($n,26, $sum_brutto_income_2_EUR, $f_bold);
	$sheet->writeNumber($n,27, $sum_brutto_income_3_EUR, $f_bold);
	$workbook->close();        
//	echo $workbook->_data; die();
	$csv = file_get_contents($fn);
//	clear_dir($tmpdir, $temp_xls_export_pattern);
	unlink($fn);
    return $csv;
}

function formatBruttoIncome($calcs) {
        $csv = '';
		$csv .= '"Auction number";"Sales date";"Article";"Quantity";"Currency";"Seller account";"Country";"Seller type";"Price sold EUR ";"Ebay listing fee EUR ";"Additional listing fee EUR ";"Ebay commission EUR ";"VAT EUR ";"Netto sales price EUR ";"Purchase price EUR ";"Brutto income EUR ";"Shipping cost EUR ";"Shipping VAT ";"Effective shipping cost ";"COD cost EUR ";"COD VAT ";"Effective COD cost ";"Income shipping cost EUR ";"Cost packing matherial EUR ";"Revenue";"Margin";"Brutto income 2";"Brutto income per item"';
	    foreach ($calcs as $calc) {
	        $csv .= "\r\n";
				$currency = siteToSymbol($calc->siteid);
	        $csv .= '"'.$calc->auction_number.'/'.$calc->txnid.'";';
	        $csv .= '"'.$calc->end_time.'";';
	        $csv .= '"'.$calc->name.'";';
	        $csv .= '"'.$calc->quantity.'";';
	        $csv .= '"'.$currency.'";';
	        $csv .= '"'.$calc->username.'";';
	        $csv .= '"'.$calc->site_country.'";';
	        $csv .= '"'.$calc->txnid_type.'";';
			$sum_price_sold_EUR += $calc->price_sold_EUR;
	        $csv .= '"'.$calc->price_sold_EUR.'";';
			$sum_ebay_listing_fee_EUR += $calc->ebay_listing_fee_EUR;
	        $csv .= '"'.$calc->ebay_listing_fee_EUR.'";';
			$sum_additional_listing_fee_EUR += $calc->additional_listing_fee_EUR;
	        $csv .= '"'.$calc->additional_listing_fee_EUR.'";';
			$sum_ebay_commission_EUR += $calc->ebay_commission_EUR;
	        $csv .= '"'.$calc->ebay_commission_EUR.'";';
			$sum_vat_EUR += $calc->vat_EUR;
	        $csv .= '"'.$calc->vat_EUR.'";';
			$sum_netto_sales_price_EUR += $calc->netto_sales_price_EUR;
	        $csv .= '"'.$calc->netto_sales_price_EUR.'";';
			$sum_purchase_price_EUR += $calc->purchase_price_EUR;
	        $csv .= '"'.$calc->purchase_price_EUR.'";';
			$sum_brutto_income_EUR += $calc->brutto_income_EUR;
	        $csv .= '"'.$calc->brutto_income_EUR.'";';
			$sum_shipping_cost_EUR += $calc->shipping_cost_EUR;
	        $csv .= '"'.$calc->shipping_cost_EUR.'";';
			$sum_vat_shipping_EUR += $calc->vat_shipping_EUR;
	        $csv .= '"'.$calc->vat_shipping_EUR.'";';
			$sum_effective_shipping_cost_EUR += $calc->effective_shipping_cost_EUR;
	        $csv .= '"'.$calc->effective_shipping_cost_EUR.'";';
			$sum_COD_cost_EUR += $calc->COD_cost_EUR;
	        $csv .= '"'.$calc->COD_cost_EUR.'";';
			$sum_vat_COD_EUR += $calc->vat_COD_EUR;
	        $csv .= '"'.$calc->vat_COD_EUR.'";';
			$sum_effective_COD_cost_EUR += $calc->effective_COD_cost_EUR;
	        $csv .= '"'.$calc->effective_COD_cost_EUR.'";';
			$sum_income_shipping_cost_EUR += $calc->income_shipping_cost_EUR;
	        $csv .= '"'.$calc->income_shipping_cost_EUR.'";';
			$sum_packing_cost_EUR += $calc->packing_cost_EUR;
	        $csv .= '"'.$calc->packing_cost_EUR.'";';
			$sum_revenue_EUR += $calc->revenue_EUR;
	        $csv .= '"'.$calc->revenue_EUR.'";';
			$margin += $calc->brutto_income_2_EUR*100/$calc->revenue_EUR;
	        $csv .= '"'.$margin.'";';
			$sum_brutto_income_2_EUR += $calc->brutto_income_2_EUR;
	        $csv .= '"'.$calc->brutto_income_2_EUR.'";';
			$sum_brutto_income_3_EUR += $calc->brutto_income_3_EUR;
	        $csv .= '"'.$calc->brutto_income_3_EUR.'";';
	    }
	        $csv .= "\r\n";
	        $csv .= ';;;;;;;;';
	        $csv .= '"'.$sum_price_sold_EUR.'";';
	        $csv .= '"'.$sum_ebay_listing_fee_EUR.'";';
	        $csv .= '"'.$sum_additional_listing_fee_EUR.'";';
	        $csv .= '"'.$sum_ebay_commission_EUR.'";';
	        $csv .= '"'.$sum_vat_EUR.'";';
	        $csv .= '"'.$sum_netto_sales_price_EUR.'";';
	        $csv .= '"'.$sum_purchase_price_EUR.'";';
	        $csv .= '"'.$sum_brutto_income_EUR.'";';
	        $csv .= '"'.$sum_shipping_cost_EUR.'";';
	        $csv .= '"'.$sum_vat_shipping_EUR.'";';
	        $csv .= '"'.$sum_effective_shipping_cost_EUR.'";';
	        $csv .= '"'.$sum_COD_cost_EUR.'";';
	        $csv .= '"'.$sum_vat_COD_EUR.'";';
	        $csv .= '"'.$sum_effective_COD_cost_EUR.'";';
	        $csv .= '"'.$sum_income_shipping_cost_EUR.'";';
	        $csv .= '"'.$sum_packing_cost_EUR.'";';
	        $csv .= '"'.$sum_revenue_EUR.'";';
			$margin += $sum_brutto_income_2_EUR*100/$sum_revenue_EUR;
	        $csv .= '"'.$margin.'";';
	        $csv .= '"'.$sum_brutto_income_2_EUR.'";';
	        $csv .= '"'.$sum_brutto_income_3_EUR.'";';
	    return $csv;
}

function formatBruttoIncomePDF($calcs) {
        $pdf =File_PDF::factory('P', 'mm', 'A4');
        $pdf->open();
        $pdf->setAutoPageBreak(true);
	    $pdf->addPage();
        $pdf->setFillColor('rgb', 0, 0, 0);
        $pdf->setDrawColor('rgb', 0, 0, 0);
        $pdf->setFont('arial','B', 6);
		$pdf->setLeftMargin(0);
		$y=1; $x=1;
		$pdf->setXY(5, $y);	$pdf->multiCell(30, 10, 'Auction number', 1, 'L');
		$pdf->setXY(35, $y);	$pdf->multiCell(15, 10, 'Sales date', 1, 'L');
		$pdf->setXY(50, $y);	$pdf->multiCell(20, 10, 'Article', 1, 'L');
		$pdf->setXY(70, $y);	$pdf->multiCell(12, 10, 'Quantity', 1, 'L');
		$pdf->setXY(82, $y);	$pdf->multiCell(12, 10, 'Currency', 1, 'L');
		$pdf->setXY(94, $y);	$pdf->multiCell(10, 10, 'Price sold EUR', 1, 'L');
		$pdf->setXY(100, $y);	$pdf->multiCell(10, 10, 'Ebay listing fee EUR', 1, 'L');
		$pdf->setXY(110, $y);	$pdf->multiCell(10, 10, 'Ebay commission EUR', 0, 'L');
		$pdf->setXY(120, $y);	$pdf->multiCell(130, 10, 'VAT EUR', 0, 'L');
		$pdf->setXY(130, $y);	$pdf->multiCell(140, 10, 'Netto sales price EUR', 0, 'L');
		$pdf->setXY(140, $y);	$pdf->multiCell(150, 10, 'Purchase price EUR', 0, 'L');
		$pdf->setXY(150, $y);	$pdf->multiCell(160, 10, 'Brutto income EUR', 0, 'L');
		$pdf->setXY(160, $y);	$pdf->multiCell(170, 10, 'Shipping cost EUR', 0, 'L');
		$pdf->setXY(170, $y);	$pdf->multiCell(180, 10, 'Shipping VAT', 0, 'L');
		$pdf->setXY(180, $y);	$pdf->multiCell(190, 10, 'Effective shipping cost', 0, 'L');
		$pdf->setXY(190, $y);	$pdf->multiCell(200, 10, 'COD cost EUR', 0, 'L');
		$pdf->setXY(200, $y);	$pdf->multiCell(210, 10, 'COD VAT', 0, 'L');
		$pdf->setXY(210, $y);	$pdf->multiCell(220, 10, 'Effective COD cost', 0, 'L');
		$pdf->setXY(220, $y);	$pdf->multiCell(230, 10, 'Income shipping cost EUR', 0, 'L');
		$pdf->setXY(230, $y);	$pdf->multiCell(240, 10, 'Cost packing matherial EUR', 0, 'L');
		$pdf->setXY(240, $y);	$pdf->multiCell(250, 10, 'Brutto income 2', 0, 'L');
		$pdf->setXY(250, $y);	$pdf->multiCell(260, 10, 'Brutto income per item', 0, 'L');
/*		$pdf->setXY(1, $y);
		$y+=0.5;
		$pdf->line(1, $y, 20, $y);
		$pdf->setFont('arial','B', 8); 
		$pdf->setXY(1, $y);	$pdf->multiCell(4, 0.5, 'Auction number', 0, 'L');
		$pdf->setXY(5, $y);	$pdf->multiCell(5, 0.5, 'Brutto income EUR', 0, 'L');
		$pdf->setXY(10, $y);	$pdf->multiCell(5, 0.5, 'Brutto income 2 EUR', 0, 'L');
        $pdf->setFont('arial','', 8); 
	    foreach ($calcs as $calc) {
			$y = $pdf->getY();
			$y+=0.3;
			if ($y>=27) {$pdf->addPage(); $y=1; }
	        $pdf->setXY(1, $y);	$pdf->multiCell(4, 0.5, $calc->auction_number.'/'.$calc->txnid, 0, 'L');
	        $pdf->setXY(5, $y);	$pdf->multiCell(5, 0.5, $calc->brutto_income_EUR, 0, 'L');
	        $pdf->setXY(10, $y);	$pdf->multiCell(5, 0.5, $calc->brutto_income_2_EUR, 0, 'L');
	    }*/
	    $pdf->close();
	    return $pdf->getOutput();
}

/**
 * @return void
 * @param object $db
 * @param object $auction
 * @param string $template
 * @desc Sends a templated email
*/
function standardEmail($db, $dbr, $auction, $template, $ins_id=0, $rma_spec_id=0)
{
//	print_r($auction); echo '$template='.$template.' $ins_id='.$ins_id.' $rma_spec_id='.$rma_spec_id; die();
    global $smarty;
    global $loggedUser;
    global $english;
	global $errormsg;
	global $lang;
	global $siteURL;
	$dbr = $db;
    require_once 'lib/Account.php';
    require_once 'lib/Article.php';
    require_once 'lib/SellerInfo.php';
    require_once 'lib/ShippingMethod.php';
    require_once 'lib/Warehouse.php';
    require_once 'lib/Invoice.php';
    require_once 'lib/Offer.php';
    require_once 'lib/op_Order.php';
    require_once 'lib/EmailLog.php';
    require_once 'Mail/SMTP/mailman.class.php';
	$inside = 0;
	$attachments = array();
	if (isset($auction->attachments)) $attachments = $auction->attachments;
	if (isset($auction->notes)) $notes = $auction->notes;
	$issystem = (int)$dbr->getOne("select system from template_names where name='$template'");
	$send_anyway = (int)$dbr->getOne("select send_anyway from template_names where name='$template'");
    if (PEAR::isError($issystem)) aprint_r($issystem);
	if ($issystem) {
		$username4template = Config::get($db, $dbr, 'aatokenSeller');
	} else {
		$username4template = is_a($auction, 'Auction')?$auction->get('username'):$auction->username;
	}
    $sellerInfo = new SellerInfo($db, $dbr,is_a($auction, 'Auction')?$auction->get('username'):$auction->username, $lang);
    $sellerInfo4template = new SellerInfo($db, $dbr,$username4template, $lang);
    if (is_a($auction, 'Auction')) {
		$purchased='Auction';
		$src = '_auction';
		$txnid = $auction->get('txnid');
		if ($txnid==3) { $purchased='Shop'; $src = ''; }
		elseif ($txnid>1) $purchased='Fix';
		elseif ($txnid==1) $purchased='Auction';
		elseif ($txnid==0) {
			if ($auction->get('main_auction_number')) $aunumber=$auction->get('main_auction_number');
				else $aunumber=$auction->get('auction_number');
			$auserv = new Auction($db, $dbr, $aunumber, $txnid);
			if ($server = $auserv->get('server')) {
				$shopname = $dbr->getOne("select name from shop where url='$server'");
				if (strlen($shopname)) {
					$purchased='Shop: '.$shopname;
					$src='';
				}
			}
		}
		$siteid=$auction->get('siteid');
        $auction->data->tracking_numbers = $auction->tracking_numbers;
		$vars = unserialize($auction->get('details'));
		$vat_info = VAT::get_vat_attribs($db, $dbr, $auction);
		$accounts = Account::listArray($db, $dbr);	
        $auction = $auction->data;
		$auction->VAT_account = $accounts[$vat_info->vat_account_number];	
		$auction->purchased = $purchased;
		$auction->total_fees = $dbr->getOne("select sum(ebay_listing_fee+ebay_commission+additional_listing_fee)
			from auction_calcs where auction_number=".$auction->auction_number." and txnid=".$auction->txnid);
		if ($auction->total_fees=='') 
			$auction->total_fees = ($auction->listing_fee?$auction->listing_fee:
				($auction->listing_fee1?$auction->listing_fee1:$auction->listing_fee2));
    } else {
		$siteid=$auction->siteid;
	}
	if (!isset($auction->original_username)) $auction->original_username = $auction->username;
	$msg = $_SERVER['HTTP_HOST']." template:$template\n";
	$msg .= "lang:$lang\n";
	$locallang = $lang;
	if (!strlen($locallang)) {
		$fget_AType = $dbr->getOne("select fget_AType('{$auction->auction_number}', '{$auction->txnid}')");
		if (PEAR::isError($fget_AType)) { aprint_r($fget_AType); return 'nolang'; }
		if ($auction->customer_id) {
			$q = "select lang from customer{$fget_AType} 
			where id='".$auction->customer_id."'";
		} else {
			$q = "select lang from customer{$fget_AType} 
			where email='".mysql_escape_string((strlen($auction->email)?$auction->email:$auction->email_invoice))."'";
		}	
		$locallang = $dbr->getOne($q);
		if (PEAR::isError($locallang)) { aprint_r($locallang); return 'nolang1'; }
		$msg .= $q."\n";
		$msg .= "locallang:$locallang\n";
	}
	if (!strlen($locallang)) {
		$msg .= "Seller: ".$sellerInfo->get('username')."\n";
		$locallang = $sellerInfo->get('default_lang');
		$msg .= "locallang:$locallang\n";
	}
	if (!strlen($locallang) && strlen($siteid)) {
		$locallang = Auction::getLang($db, $dbr, $siteid);
		$msg .= "locallang:$locallang\n";
	}
//	echo $lang.'-'.$locallang; echo $msg; die();
	$english = Auction::getTranslation($db, $dbr, $auction->siteid, $locallang);
//	mail('baserzas@gmail.com', "standardEmail ".$auction->auction_number, $msg);
    $sellerInfo = new SellerInfo($db, $dbr,$auction->username, $locallang);
    $sellerInfo4template = new SellerInfo($db, $dbr,$username4template, $locallang);
        $auction->tracking_list = '';
        if (count($auction->tracking_numbers)) foreach ($auction->tracking_numbers as $number) {
            $meth = new ShippingMethod($db, $dbr, $number->shipping_method);
            $number->shipping_company = str_pad($meth->get('company_name'), 40);
            $number->tracking_url =  substitute($meth->get('tracking_url'), array('number' => $number->number));
            $number->tracking_url =  substitute($number->tracking_url, array('zip' => $auction->zip_shipping));
//            $number->tracking_url =  str_replace ('[number]', $number->number, $meth->get('tracking_url'));
            $auction->tracking_list .= substitute($sellerInfo4template->getTemplate('tracking_list', $locallang/*SiteToCountryCode($siteid)*/), $number);
            $auction->tracking_list .= "\n\n";
        }
	if ($auction->no_emails && !(int)$send_anyway) return;
	$auction->currency = siteToSymbol($auction->siteid);
	$auction->email_name = $sellerInfo->get('email_name');
	$auction->Handelsregisterort = $sellerInfo->get('Handelsregisterort');
	$auction->Handelsregisterort_Nummer = $sellerInfo->get('Handelsregisterort_Nummer');
	$auction->brand_name = $sellerInfo->get('brand_name');
	$auction->seller_name = $sellerInfo->get('seller_name');
	$auction->contact_name = $sellerInfo->get('contact_name');
	$auction->street = $sellerInfo->get('street');
	$auction->town = $sellerInfo->get('town');
	$auction->zip = $sellerInfo->get('zip');
	$auction->logistics_email = $sellerInfo->get('logistics_email');
	$auction->logistics_phone = $sellerInfo->get('logistics_phone');
	$auction->country = translate($db, $dbr, CountryCodeToCountry($sellerInfo->get('country')), $locallang, 'country', 'name');
	$auction->supervisor_email = $sellerInfo->get('supervisor_email');
	$auction->return_address = $sellerInfo->get('return_address');
	$auction->complain_text =substitute($sellerInfo->get('complain_text'),$sellerInfo->data);
	$auction->support_email = $sellerInfo->get('support_email');
	$auction->seller_email = $sellerInfo->get('email');
	$auction->phone = $sellerInfo->get('phone');
	$auction->phone_order = $sellerInfo->get('phone_order');
	$auction->call_center_hours = $sellerInfo->get('call_center_hours');
	$auction->fax = $sellerInfo->get('fax');
	$auction->vat_id = $sellerInfo->get('vat_id');
	$auction->bank = $sellerInfo->get('bank');
	$auction->bank_account = $sellerInfo->get('bank_account');
	$auction->blz = $sellerInfo->get('blz');
	$auction->bic = $sellerInfo->get('bic');
	$auction->bank_owner = $sellerInfo->get('bank_owner');
	$auction->iban = $sellerInfo->get('iban');
	$auction->swift = $sellerInfo->get('swift');
	$auction->contact_name = $sellerInfo->get('contact_name');
	$auction->seller_web_page = $sellerInfo->get('web_page');
	$auction->winning_bid = 1*$auction->winning_bid;
	$auction->paid_amount = number_format($dbr->getOne("select sum(amount) 
		from payment where auction_number=".$auction->auction_number." and txnid=".$auction->txnid), 2); 
	if ($auction->auction_number && !is_array($auction->auction_number)) $auction->wwo_ids = $dbr->getAssoc("select distinct wwo_id f1, wwo_id f2 from (
		select wwo_order_id from orders
		where auction_number=".$auction->auction_number." and txnid=".$auction->txnid."
		union all
		select wwo_order_id from orders
		join auction on auction.auction_number=orders.auction_number and auction.txnid=orders.txnid
		where auction.main_auction_number=".$auction->auction_number." and auction.main_txnid=".$auction->txnid."
		)t
		joi