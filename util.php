<?php
require_once 'lib/Config.php';
require_once 'lib/Vat.php';
require_once 'config.php';
require_once 'lib/Listing.php';
require_once 'Services/Ebay.php';
require_once 'lib/Acl_Php.php';
require_once 'lib/Barcode.php';
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

if ( !function_exists('xdebug_time_index')) {
    function xdebug_time_index() {
        return time();
    }
}

/**
 * @description text translation
 * @param $text
 * @param $lang_from
 * @param $lang_to
 * @return array
 */
function translate_js_backend($text, $lang_from, $lang_to)
{
    $inputStr = $text;
    $fromLanguage = $lang_from;
    switch($fromLanguage) {
        case 'german': $fromLanguage='de'; break;
        case 'polish': $fromLanguage='pl'; break;
        case 'french': $fromLanguage='fr'; break;
        case 'spanish': $fromLanguage='es'; break;
        case 'english': $fromLanguage='en'; break;
    }
    $toLanguage = $lang_to;
    switch($toLanguage) {
        case 'german': $toLanguage='de'; break;
        case 'polish': $toLanguage='pl'; break;
        case 'french': $toLanguage='fr'; break;
        case 'spanish': $toLanguage='es'; break;
        case 'english': $toLanguage='en'; break;
    }

    //$Ocp_Apim_Subscription_Key = '0c4d40aa136c465591cee6f1d3eb323f';
    $Ocp_Apim_Subscription_Key = [
        '76000a5386154e7897200e5344efa47c', 
        'a519cd78a14648349090a56d30dc7f15', 
    ];
    
    shuffle($Ocp_Apim_Subscription_Key);
    $Ocp_Apim_Subscription_Key = $Ocp_Apim_Subscription_Key[0];

    $auth_url = 'https://api.cognitive.microsoft.com/sts/v1.0/issueToken';
    $translate_url = 'https://api.microsofttranslator.com/v2/http.svc/Translate?';

    $curl = new \Curl(null, [
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.4 (KHTML, like Gecko) Chrome/22.0.1229.92 Safari/537.4',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Accept: application/jwt",
            "Ocp-Apim-Subscription-Key: $Ocp_Apim_Subscription_Key",
        ],
    ]);

    // Get Auth key
    $curl->set_url($auth_url);
    $curl->set_post([]);
    $auth_key = $curl->exec();

    $params = [
        'text=' . rawurlencode($inputStr),
        'from=' . $fromLanguage,
        'to=' . $toLanguage,
        'contentType=text/plain',
    ];
    $params = implode('&', $params);

    $curl->set_url($translate_url . $params);
    $curl->initialize([
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/xml",
            "Authorization: Bearer $auth_key",
        ],
    ]);

    $response = $curl->exec();
    return [
        'string' => (string)simplexml_load_string($response), 
        'key' => $Ocp_Apim_Subscription_Key, 
    ];
    

//    require_once 'mstranslator.php';
//    $grantType = "client_credentials";
//    $scopeUrl = "http://api.microsofttranslator.com";
//    $clientID = "94c94528-0be5-4166-b0be-d6eedb2af3c1";
//    $clientSecret = "tHUmtL3SV8l41p+vfLylCagZNmB2EAf9tlE8w+9bCpk=";
//    $authUrl = "https://datamarket.accesscontrol.windows.net/v2/OAuth2-13/";
//
//    $user            = 'TestUser';
//    $category       = "general";
//    $uri             = null;
//    $contentType    = "text/plain";
//    $maxTranslation = 1;
//
//    //Create the string for passing the values through GET method.
//    $params = "from=$fromLanguage".
//                    "&to=$toLanguage".
//                    "&maxTranslations=$maxTranslation".
//                    "&text=".urlencode($inputStr).
//                    "&user=$user".
//                    "&uri=$uri".
//                    "&contentType=$contentType";
//
//    $authObj      = new AccessTokenAuthentication();
//    $accessToken  = $authObj->getTokens($grantType, $scopeUrl, $clientID, $clientSecret, $authUrl);
//    $authHeader = "Authorization: Bearer ". $accessToken;
//    $getTranslationUrl = "http://api.microsofttranslator.com/V2/Http.svc/GetTranslations?$params";
//    $translatorObj = new HTTPTranslator();
//    $curlResponse = $translatorObj->curlRequest($getTranslationUrl, $authHeader);
//    $xmlObj = simplexml_load_string($curlResponse);
//    $translationObj = $xmlObj->Translations;
//    $translationMatchArr = $translationObj->TranslationMatch;
//    $res1 = $params;
//    foreach($translationMatchArr as $translationMatch) {
//            $res .= $translationMatch->TranslatedText." ";
//    }
//    $result[0] = $res1;
//    $result[1] = $res;
//    return $result;
}

/**
 * @description get log of tables updates
 * @param string $table
 * @param string $field
 * @param string $tableid
 * @return array
 */
function getChangeDataLog($table, $field, $tableid)
{
    global $db, $dbr;
    $log_info = $dbr->getAll("
        SELECT users.name, users.id, tl.Old_value, tl.New_value, tl.Updated
        FROM total_log tl
        JOIN users ON users.system_username = tl.username
        WHERE tl.Table_name = '" . $table . "'
            AND tl.Field_name = '" . $field . "'
            AND tl.TableID = '" . $tableid . "'
        ORDER BY tl.Updated DESC");
    return $log_info;
}

function generate_password($length = 10, $use_low_letters = false, $use_special_chars = false) {

    // This variable contains the list of allowable characters
    // for the password.  Note that the number 0 and the letter
    // 'O' have been removed to avoid confusion between the two.
    // The same is true of 'I' and 1
    $allowable_characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    if ($use_low_letters) {
        $allowable_characters .= 'abcdefghjklmnpqrstuvwxyz';
    }
    if ($use_special_chars) {
        $allowable_characters .= '[|!@#$%&*\\/=?,;.:-_+~^';
    }

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
    if ($array && is_array($array)) {
        foreach ($array as $key => $value) {
            $ret->$key = $value;
        }
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
 * @return integer
 * @param string $country
 * @desc Converts country name to country ISO code
 */
function CountryToISOCode($country)
{
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
    $q = "SELECT IFNULL((
      SELECT LPAD(`iso_code`, 3, 0) FROM country c
        JOIN translation t ON c.id=t.id
            AND t.table_name='country'
            AND t.field_name='name'
        WHERE 1
        AND t.value='$country'
        LIMIT 1
    ),
    '$country')";

    return $dbr->getOne($q);
}
/**
 * @param string $code
 * @return int
 * @desc Get phone prefix by country code
 */
function CodeToPhonePrefix($code)
{
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
    return $dbr->getOne("select phone_prefix from country where code = '$code'");
}
/**
 * Returns array of countries names, indexed by country code
 *
 * @param string $lang
 * @param int $seller_id
 * @param array $shipping_plan_ids
 * @return array
*/
function allCountries($lang='english', $seller_id=0, $shipping_plan_ids=[])
{
    $db = \label\DB::getInstance(\label\DB::USAGE_READ);

    $joins = '';
    foreach ($shipping_plan_ids as $k=>$id) {
        $joins = " join shipping_plan_country spc$k on spc$k.shipping_plan_id=$id and spc$k.country_code=c.code ";
    }

    $q = "select distinct c.code, t.value
        from country c
        left join seller_country sc on sc.country_id=c.id
        $joins
        join translation t on c.id=t.id
            and t.table_name='country' and t.field_name='name'
        where 1 and t.language='$lang'
        and ($seller_id=0 or sc.seller_id=$seller_id)
        order by c.ordering";

    $countries = $db->getAssoc($q);
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
        901=>'SE',
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
 * @todo maybe error reporting have to be returned to previous value
 */
function substitute($tpl, $vars)
{
    if (strpos($tpl, '[[') === false)
    {
        return $tpl;
    }
    
    if ($vars) {
        
        $keys = [];
        $values = [];

        foreach ($vars as $name => $value) 
        {
            if (is_object($value))
            {
                foreach ($value as $name1 => $value1) 
                {
                    $keys[] = "[[$name1]]";
                    $values[] = $value1;
                }
            }
            else 
            {
                $keys[] = "[[$name]]";
                $values[] = $value;
            }
        }

        $tpl = str_ireplace($keys, $values, $tpl);
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
    }
    if ((! $costs) && (in_array($payment, array('1', '2', 'cc_shp','pofi_shp','epsgiro','bill_shp','bean_shp', 'pp_shp', 'sofo_shp', 'klr_shp', 'payever', 'cmcic', 'gc_shp', 'inst_shp')))) {
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
                      if (!$article->noship && !$sellerInfo->data->free_shipping) $groupshipping += $article->additional_cost * $quantity;
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
        if ($item->deleted || $item->admin_id==4) {
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
    $bonus=array(), $promo, $total_input=array(), $old_shipping = 0
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
                        $item->admin_id==4?0:$item->price,
                        $item->article_id,
                        $item->admin_id,
                        $item->custom_description,
                        $item->article_list_id,
                        0, // hidden
                        $input_pos[$item->article_id.':'.$item->quantity],//$item->position
                        $reserve_warehouse_id,
                        $send_warehouse_id,
                        $item->id, // id
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
if ($debug) echo 'CA 1: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
    Order::Create($db, $dbr, $auction->get('auction_number'), $auction->get('txnid'), $order);
if ($debug) echo 'CA 2: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
    /*if ($resend) */{
        $auction->set('process_stage', STAGE_ORDERED);
        $auction->set('status_change', date('Y-m-d H:i:s'));
    }
    
    // 0RHGq02Q/2385-hanna-kot-delivery-address-missing
    foreach ($personal as $field => $value) {
        if (strpos($field, '_invoice') !== false  && $personal['same_address']) {  // invoice field & same_address
            $parts = explode('_', $field);
            $shipping_field_name = $parts[0] . '_shipping';
            if (!isset($personal[$shipping_field_name]) || empty($personal[$shipping_field_name])) {
                $personal[$shipping_field_name] = $personal[$field];
            }
        }
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
    if (in_array($payment, array('cc_shp','pofi_shp','epsgiro','bill_shp','bean_shp','ppcc_shp','pp_shp', 'sofo_shp', 'klr_shp', 'payever', 'cmcic', 'gc_shp'))) {
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
        if ($promo->free_shipping) {
            $invoice->set('old_total_shipping', $invoice->set('total_shipping'));
            $invoice->set('total_shipping', 0);
        }

        if ($promo->free_article_price_limit) {
            $invoice->set('old_total_shipping', $old_shipping);
        }

        if ($custom_cod!=='') {
            $total_cod = $total_shipping + $custom_cod;
            $invoice->set('is_custom_cod', 1);
        } else {
            $invoice->set('is_custom_cod', 0);
        }
        if (in_array($payment, array('1','cc_shp','pofi_shp','epsgiro','bill_shp','bean_shp','pp_shp','sofo_shp','klr_shp','payever', 'cmcic', 'gc_shp','inst_shp'))) {
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
            if (in_array($payment, array('inst_shp', 'cc_shp', 'pofi_shp','epsgiro','bill_shp','bean_shp','pp_shp','sofo_shp','klr_shp','payever','cmcic','gc_shp'))) {
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
        if (!$personal['when'] & !$auction->get('delivery_date_customer')) {
            $mainAuction->set('delivery_date_customer', '0000-00-00');
            $auction->set('delivery_date_customer', '0000-00-00');
        } else if($personal['when']){
            $shipon_formatted = date('Y-m-d', strtotime($personal['shipon']));
            $mainAuction->set('delivery_date_customer', $shipon_formatted);
            $auction->set('delivery_date_customer', $shipon_formatted);
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
    if ($resend) {
        $method = $auction->get('payment_method');
        if ($method=='bill_shp') {
            // disabled by Stephan request https://trello.com/c/aHwxCpKO/742-justyna-emails-not-send
            // and the re-enabled due to https://trello.com/c/yQQMmaDF/1209-marta-problem-with-payment-information-in-order-confirmation-mail
        } else {
            if(!$auction->data->tel_invoice_formatted || !$auction->data->cel_invoice_formatted){
                $countries = $dbr->getAssoc("select country.code, country.* from country");
                if($auction->data->tel_invoice){
                    $prefix = $countries[$auction->data->tel_country_code_invoice]['phone_prefix'];
                    $phone_prefix = !empty($prefix) ? '+' . $prefix . ' ' : '';
                    $auction->data->tel_invoice_formatted = $phone_prefix . $auction->data->tel_invoice;
                }
                if ($auction->data->cel_invoice) {
                    $prefix = $countries[$auction->data->cel_country_code_invoice]['phone_prefix'];
                    $phone_prefix = !empty($prefix) ? '+' . $prefix . ' ' : '';
                    $auction->data->cel_invoice_formatted = $phone_prefix . $auction->data->cel_invoice;
                }
                if ($auction->data->tel_shipping) {
                    $prefix = $countries[$auction->data->tel_country_code_shipping]['phone_prefix'];
                    $phone_prefix = !empty($prefix) ? '+' . $prefix . ' ' : '';
                    $auction->data->tel_shipping_formatted = $phone_prefix . $auction->data->tel_shipping;
                }
                if ($auction->data->cel_shipping) {
                    $prefix = $countries[$auction->data->cel_country_code_shipping]['phone_prefix'];
                    $phone_prefix = !empty($prefix) ? '+' . $prefix . ' ' : '';
                    $auction->data->cel_shipping_formatted = $phone_prefix . $auction->data->cel_shipping;
                }
            }

            standardEmail($db, $dbr, $auction, 'order_confirmation');
        }
        /*if (!$sellerInfo->get('dontsend_ruckgabebelehrung')) {
            standardEmail($db, $dbr, $auction, 'send_Ruckgabebelehrung');
        }*/
        $notify_using = $dbr->getOne("select notify_using from payment_method where `code`='$method'");
        if ($notify_using && strlen($sellerInfo->get('payment_notify_using_email'))) {
            standardEmail($db, $dbr, $auction, 'payment_notification');
        }
        $payment_instruction_template = $dbr->getOne("select email_template from payment_method where `code`='".$auction->get('payment_method')."'");
        if ($method != 'bill_shp') {
            if (strlen($payment_instruction_template) && !(int)$auction->data->paid) {
                //standardEmail($db, $dbr, $auction, $payment_instruction_template);
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

    $q = "insert ignore into auction_marked_as_Shipped_src (type, auction_id)
        select 'source_seller', ".$auction->get('id')." from source_seller where not dontsend_marked_as_Shipped and id=".$auction->get('source_seller_id');
    $r = $db->query($q);
    if (PEAR::isError($r)) aprint_r($r);
    $q = "insert ignore into auction_marked_as_Shipped_src (type, auction_id)
        select 'payment_method', ".$auction->get('id')." from payment_method where not dontsend_marked_as_Shipped and code='".$auction->get('payment_method')."'";
    $r = $db->query($q);
    if (PEAR::isError($r)) aprint_r($r);
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
function formatInvoice($auction_number, $txnid)
{
    global $english;
    global $english_shop;
    global $smarty;

    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);

    $width = 90;
    $auction = new \Auction($db, $dbr, $auction_number, $txnid);
    $invoice = new \Invoice($db, $dbr, $auction->get('invoice_number'));
    $r = $dbr->getAssoc("SELECT CONCAT( auction_number, '/', txnid ), invoice_number
                FROM auction
                WHERE main_auction_number =" . $auction->get('auction_number') . "
                AND main_txnid =" . $auction->get('txnid'));
    $english = Auction::getTranslation($db, $dbr, $auction->get('siteid'), $auction->getMyLang());
    $english_shop = Auction::getTranslationShop($db, $dbr, $auction->get('siteid'), $auction->getMyLang());
    $subinvoices = array_map('intval', (array) $r);

    $order = \Order::listAll($db, $dbr, $auction->get('auction_number'), $auction->get('txnid'), 1, $auction->getMyLang());
    $orderBonus = \Order::listBonus($db, $dbr, $auction->get('auction_number'), $auction->get('txnid'));
    $sellerInfo = \SellerInfo::singleton($db, $dbr, $auction->get('username'));

    $smarty->assign('currCode', siteToSymbol($auction->get('siteid')));
    $smarty->assign('auction', $auction->data);
    $invoice->data->invoice_date = utf8_encode(strftime($sellerInfo->data->date_format_invoice, strtotime($invoice->data->invoice_date)));
    $smarty->assign('invoice', $invoice->data);
    $smarty->assign('sellerInfo', $sellerInfo->data);
    $smarty->assign('english', $english);
    $smarty->assign('english_shop', $english_shop);
    $smarty->assign('order', $order);

    $result = $smarty->fetch('order_confirmation.tpl');

    return $result;

    $ret = str_pad($english[80], $width, ' ', STR_PAD_BOTH) . "\r";
    $ret .= str_repeat('=', $width) . "\r";
    $ret .= str_pad($sellerInfo->get('seller_name'), $width / 2, ' ', STR_PAD_RIGHT);
    $ret .= str_pad($english[81] . ' ' . $auction->get('auction_number'), $width / 2, ' ', STR_PAD_LEFT) . "\r";
    $ret .= str_pad($sellerInfo->get('street'), $width / 2, ' ', STR_PAD_RIGHT);
#    $ret .= str_pad($english[82] . ' '.$auction->get('email_invoice'), $width / 2, ' ', STR_PAD_LEFT);
    $ret .= "\r";
    $ret .= str_pad($sellerInfo->get('zip') . ' ' . $sellerInfo->get('town'), $width / 2, ' ', STR_PAD_RIGHT);
    $ret .= str_pad($english[83] . ' ' . $invoice->get('invoice_number'), $width / 2, ' ', STR_PAD_LEFT) . "\r";
    $ret .= str_pad(countryCodeToCountry($sellerInfo->get('country')), $width / 2, ' ', STR_PAD_RIGHT);
    $ret .= str_pad($english[84] . ' ' . $invoice->get('invoice_date'), $width / 2, ' ', STR_PAD_LEFT) . "\r";
    $ret .= str_pad($english[85] . ' ' . $sellerInfo->get('vat_id'), $width, ' ', STR_PAD_RIGHT) . "\r";
    $ret .= str_repeat('=', $width) . "\r";
    $currCode = siteToSymbol($auction->get('siteid'));
    $ret .= formatItemsList($order, $currCode, $auction->getMyLang());
    $subtotal = $invoice->get('total_price');
    $shipping = $invoice->get('total_shipping');
    $cod = $invoice->get('total_cod');
    if ($shipping > 0)
    {
        $ret .= str_pad($english[25] . ' ' . $currCode . ' ' . number_format($subtotal, 2), $width, ' ', STR_PAD_LEFT) . "\r";
        $ret .= str_pad($english[26] . ' ' . $currCode . ' ' . number_format($shipping, 2), $width, ' ', STR_PAD_LEFT) . "\r";
        $subtotal += $shipping;
    }
    if ($cod > 0)
    {
        $ret .= str_pad($english[104] . ': ' . $currCode . ' ' . number_format($cod, 2), $width, ' ', STR_PAD_LEFT) . "\r";
        $subtotal += $cod;
    }
    $fee = $invoice->get('total_cc_fee');
    if ($fee > 0)
    {
        $fee_name = $dbr->getOne("select `value` from translation
            where table_name='payment_method' and field_name='fee_name'
            and language='" . $auction->getMyLang() . "'
            and id=(select id from payment_method where code='" . $auction->get('payment_method') . "')");
        $ret .= str_pad($fee_name . ': ' . $currCode . ' ' . number_format($fee, 2), $width, ' ', STR_PAD_LEFT) . "\r";
        $subtotal += $fee;
    }
    $ret .= str_pad($english[27] . ' ' . $currCode . ' ' . number_format($subtotal, 2), $width, ' ', STR_PAD_LEFT) . "\r";
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

function standardEmailFast($db, $dbr, $auction, $template, $ins_id=0, $rma_spec_id=0)
{
    if (!strlen($template)) return;

    if (isset($auction->data)){
        $data = $auction->data;
    }else{
        $data = $auction;
    }

    $sdata = serialize($data);

    $db->query("INSERT INTO email_queue (data, template, ins_id, rma_spec_id) VALUES ('{$sdata}', '{$template}', {$ins_id}, {$rma_spec_id})");

    return true;
}

/**
 * Sends a templated email
 * @param \MDB2_Driver_mysql $db
 * @param \MDB2_Driver_mysql $dbr
 * @param stdClass $auction
 * @param string $template
 * @param int|string $ins_id magic variable
 * @param int $rma_spec_id
 * @return bool|string
 */
function standardEmail($db, $dbr, $auction, $template, $ins_id=0, $rma_spec_id=0, $nosend=false, $_def_smtp = false)
{
    if ( ! $db)
    {
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    }

    if ( ! $dbr)
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
    }

    if (!strlen($template)) return;
//	print_r($auction); echo '$template='.$template.' $ins_id='.$ins_id.' $rma_spec_id='.$rma_spec_id; die();
    global $smarty;
    global $loggedUser;
    global $english;
    global $errormsg;
    global $lang;
    global $debug;
    global $siteURL;
    global $_SERVER_REMOTE_ADDR;
    global $shop_id;
    $dbrr = $dbr;
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
    $template_name = $dbr->getRow("select * from template_names where name='$template'");
    $issystem = (int)$template_name->system;
    $ishtml = (int)$template_name->html;
    $header_footer = (int)$template_name->header_footer;
    $default_send_anyway = (int)$template_name->send_anyway;
    $default_send_sms = (int)$template_name->sms;
    if(in_array($ins_id, ['email','sms'], true)) {
        $sendmethod = $ins_id;
    }
    if ($ishtml) {
        if (is_array($attachments) && count($attachments) && $attachments[0] != 'html') {
            array_unshift($attachments, 'html');
        } elseif ($attachments != 'html') {
            $attachments[] = 'html';
        }
    }
    if (PEAR::isError($issystem)) aprint_r($issystem);
    if ($template_name->seller) {
        $shipseller = $dbr->getOne("select shipseller from source_seller where id=" . intval($auction->source_seller_id));
        if ($shipseller) $username4template=$shipseller;
    }
	if (!strlen($username4template)) {
		if ($issystem) {
	        $username4template = Config::get($db, $dbr, 'aatokenSeller');
	    } else {
	        $username4template = is_a($auction, 'Auction') ? $auction->get('username') : $auction->username;
	    }
	}
    $sellerInfo = new SellerInfo($db, $dbr, is_a($auction, 'Auction') ? $auction->get('username') : $auction->username, $lang);

    // New send anyway logic
    $template_sendanyway_seller_list = $dbr->getAll("SELECT * FROM template_sendanyway_seller WHERE template_id={$template_name->id}");
    $template_sendanyway_source_seller_list = $dbr->getAll("SELECT * FROM template_sendanyway_source_seller WHERE template_id={$template_name->id}");
    $send_anyway = $default_send_anyway;
    $source_seller_id = is_a($auction, 'Auction') ? $auction->get('source_seller_id') : $auction->source_seller_id;
    $seller_id = !empty($sellerInfo) ? $sellerInfo->data->id : null;
    if(sizeof($template_sendanyway_seller_list) || sizeof($template_sendanyway_source_seller_list)) {
        $send_anyway = 0;
    }
    if(sizeof($template_sendanyway_source_seller_list)) {
        foreach ($template_sendanyway_source_seller_list as $template_sendanyway_source_seller) {
            if ($template_sendanyway_source_seller->source_seller_id == $source_seller_id) {
                $send_anyway = 1;
                break;
            }
        }
    }else{
        foreach($template_sendanyway_seller_list as $template_sendanyway_seller){
            if($template_sendanyway_seller->seller_id == $seller_id){
                $send_anyway = 1;
                break;
            }
        }
    }

    // New sms logic
    $template_sendsms_seller_list = $dbr->getAll("SELECT * FROM template_sms_seller WHERE template_id={$template_name->id}");
    $template_sendsms_source_seller_list = $dbr->getAll("SELECT * FROM template_sms_source_seller WHERE template_id={$template_name->id}");
    $send_sms = $default_send_sms;
    $source_seller_id = is_a($auction, 'Auction') ? $auction->get('source_seller_id') : $auction->source_seller_id;
    $seller_id = !empty($sellerInfo) ? $sellerInfo->data->id : null;
    if(sizeof($template_sendsms_seller_list) || sizeof($template_sendsms_source_seller_list)) {
        $send_sms = 0;
    }
    foreach($template_sendsms_seller_list as $template_sendsms_seller){
        if($template_sendsms_seller->seller_id == $seller_id){
            $send_sms = 1;
            break;
        }
    }
    foreach($template_sendsms_source_seller_list as $template_sendsms_source_seller){
        if($template_sendsms_source_seller->source_seller_id == $source_seller_id){
            $send_sms = 1;
            break;
        }
    }

    if (is_a($auction, 'Auction')) {
        $purchased = 'Auction';
        $src = '_auction';
        $txnid = $auction->get('txnid');
        if ($txnid == 3) {
            $purchased = 'Shop';
            $src = '';
        } elseif ($txnid > 1) $purchased = 'Fix';
        elseif ($txnid == 1) $purchased = 'Auction';
        elseif ($txnid == 0) {
            if ($auction->get('main_auction_number')) $aunumber = $auction->get('main_auction_number');
            else $aunumber = $auction->get('auction_number');
            $auserv = new Auction($db, $dbr, $aunumber, $txnid);
            if ($server = $auserv->get('server')) {
                $shopname = $dbr->getOne("select name from shop where url='$server'");
                if (strlen($shopname)) {
                    $purchased = 'Shop: ' . $shopname;
                    $src = '';
                }
            }
        }
        $siteid = $auction->get('siteid');
        $auction->data->tracking_numbers = $auction->tracking_numbers;
        $vars = unserialize($auction->get('details'));
        $vat_info = VAT::get_vat_attribs($db, $dbr, $auction);
        $accounts = Account::listArray($db, $dbr);
        $auction = $auction->data;
        $auction->VAT_account = $accounts[$vat_info->vat_account_number];
        $auction->purchased = $purchased;
        $auction->total_fees = $dbr->getOne("select sum(ebay_listing_fee+ebay_commission+additional_listing_fee)
            from auction_calcs where auction_number=" . $auction->auction_number . " and txnid=" . $auction->txnid);
        if ($auction->total_fees == '')
            $auction->total_fees = ($auction->listing_fee ? $auction->listing_fee :
                ($auction->listing_fee1 ? $auction->listing_fee1 : $auction->listing_fee2));
    } else {
        $siteid = $auction->siteid;
    }
    if (!isset($auction->original_username)) $auction->original_username = $auction->username;
    $msg = $_SERVER['HTTP_HOST'] . " template:$template\n";
    $msg .= "lang:$lang\n";
    $locallang = $lang;
    if (!strlen($locallang)) {
        $fget_AType = $dbr->getOne("select fget_AType('{$auction->auction_number}', '{$auction->txnid}')");
        if (PEAR::isError($fget_AType)) {
            aprint_r($fget_AType);
            return 'nolang';
        }
        if ($auction->customer_id) {
            $q = "select lang from customer{$fget_AType}
            where id='" . $auction->customer_id . "'";
        } else {
            $q = "select lang from customer{$fget_AType}
            where email='" . mysql_escape_string((strlen($auction->email) ? $auction->email : $auction->email_invoice)) . "'";
        }
        $locallang = $dbr->getOne($q);
        if (PEAR::isError($locallang)) {
            aprint_r($locallang);
            return 'nolang1';
        }
        $msg .= $q . "\n";
        $msg .= "locallang:$locallang\n";
    }
    if (!strlen($locallang)) {
        $msg .= "Seller: " . $sellerInfo->get('username') . "\n";
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

    $sellerInfo = new SellerInfo($db, $dbr, $auction->username, $locallang);
    $sellerInfo4template = new SellerInfo($db, $dbr, $username4template, $locallang);
    $auction->tracking_list = '';
    if (count($auction->tracking_numbers)) foreach ($auction->tracking_numbers as $number) {
        $meth = new ShippingMethod($db, $dbr, $number->shipping_method);
        $number->shipping_company = str_pad($meth->get('company_name'), 40);
        $number->tracking_url = substitute($meth->get('tracking_url'), array('number' => $number->number, 'zip' => $auction->zip_shipping, 'country_code2' => countryToCountryCode($auction->country_shipping)));
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
//	if ($debug) {echo $sellerInfo->get('country').'-'.CountryCodeToCountry($sellerInfo->get('country')); die($auction->country);}
    $auction->supervisor_email = $sellerInfo->get('supervisor_email');
    $auction->return_address = $sellerInfo->get('return_address');
    $auction->complain_text = substitute($sellerInfo->get('complain_text'), $sellerInfo->data);
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
    $auction->bank_giro = $sellerInfo->get('bank_giro');
    $auction->bank_owner = $sellerInfo->get('bank_owner');
    $auction->iban = $sellerInfo->get('iban');
    $auction->swift = $sellerInfo->get('swift');
    $auction->contact_name = $sellerInfo->get('contact_name');
    $auction->seller_web_page = $sellerInfo->get('web_page');
    $auction->winning_bid = 1 * $auction->winning_bid;
    $auction->paid_amount = $dbr->getOne("select sum(amount)
        from payment where auction_number=" . $auction->auction_number . " and txnid=" . $auction->txnid);
    if (!PEAR::isError($auction->paid_amount)) {
        $auction->paid_amount = number_format($auction->paid_amount, 2);
    } else {
        $auction->paid_amount = 0;
    }
    if ($auction->auction_number && !is_array($auction->auction_number)) $auction->wwo_ids = $dbr->getAssoc("select distinct wwo_id f1, wwo_id f2 from (
        select wwo_order_id from orders
        where auction_number=" . $auction->auction_number . " and txnid=" . $auction->txnid . "
        union all
        select wwo_order_id from orders
        join auction on auction.auction_number=orders.auction_number and auction.txnid=orders.txnid
        where auction.main_auction_number=" . $auction->auction_number . " and auction.main_txnid=" . $auction->txnid . "
        )t
        join wwo_article on t.wwo_order_id=wwo_article.id");
    if (count($auction->wwo_ids)) $auction->wwo_ids_string = ' from WWO ' . implode(', ', $auction->wwo_ids);
    if ($auction->paid_amount > 0)
        $auction->paid_amount_phrase = $english[185] . ' ' . $auction->paid_amount . ' ' . $auction->currency . ' ' . $english[186];
    else
        $auction->paid_amount_phrase = '';
    $invoice = new Invoice($db, $dbr, $auction->invoice_number);
    if ((int)$invoice->get('invoice_number')) {
        $auction->total = number_format($invoice->getTotal(), 2);
        $auction->open_amount = number_format($invoice->get('open_amount'), 2);
    } else {
        $auction->total = 0;
    }
    $key = substr(md5($auction->auction_number . $auction->txnid . $auction->username), 0, 8);
    $key_email = substr(md5($auction->auction_number . $auction->txnid . $auction->username . $auction->email_shipping), 0, 8);
// LOCK TABLE email_log
    if ((int)$auction->shop_id) {
//		print_r($auction); die();
        $srcs = '';
        $srcs .= "&src[]=" . $auction->src;
        $customer_shop = $dbr->getRow("select * from customer{$auction->src} where id=" . $auction->auction_number);
        $shop_row = $dbr->getRow("select * from shop where id=" . $auction->shop_id);
        if ((int)$customer_shop->id) {
            if (isset($auction->nextID)) $nextID = $auction->nextID;
            else {
                global $db_host;
                global $db_user;
                global $db_pass;
                global $db_name;
                list($host, $port) = explode(':', $db_host);
                $link = mysqli_connect($host, $db_user, $db_pass, $db_name, $port);
                $r = mysqli_query($link, "insert into {$template_name->log_table} (template,date,auction_number,txnid) values ('',now(),0,0)");
                $nextID = mysqli_insert_id($link);
                mysqli_close($link);
                /*				$db->query("insert into email_log (template,date,auction_number,txnid)
                values ('',now(),0,0)");
                                 $nextID = mysqli_insert_id();*/
                if (!$nextID) $nextID = $db->getOne("select max(id) from {$template_name->log_table} where template='' and auction_number=0 and txnid=0");
            }
            $auction->newsunassignurl = 'http://' . $shop_row->url
                . '/shop_catalogue.php?news_email=' . $auction->email . $srcs . '&step=1&btn_news_remove=1&email=' . $nextID;
            $auction->newsshowurl = 'http://' . $shop_row->url . '/news_show.php?id=' . $customer_shop->id
                . '&code=' . $customer_shop->code . '&email=' . $nextID;
            $auction->recommendurl = 'http://' . $shop_row->url . '/recommend.php?id=' . $customer_shop->id
                . '&code=' . $customer_shop->code . '&email=' . $nextID;
        }
    }
    $car = $dbr->getRow("select cars.*
        from cars
        join route on cars.id=route.car_id
        where route.id=" . (int)$auction->route_id);
    $auction->siteURL = $siteURL;
    $auction->delivery_confirmation_url = $siteURL . 'order.php?a=' . $auction->auction_number . '&t=' . $auction->txnid . '&k=' . $key . '&route=1';
#	$auction->car_url = "http://{$car->tracking_account}:{$car->tracking_password}@tracking.itakka.at/track/{$car->tracking_imei}";
#	$auction->car_url_tag = "<a href='http://{$car->tracking_account}:{$car->tracking_password}@tracking.itakka.at/track/{$car->tracking_imei}'>".$english[215]."</a>";
#	$auction->car_url_tag_204 = "<a href='http://{$car->tracking_account}:{$car->tracking_password}@tracking.itakka.at/track/{$car->tracking_imei}'>".$english[204]."</a>";
    $auction->tracking_car_url = $siteURL . "tracker.php?a=" . $auction->auction_number . "&t=" . $auction->txnid . "&k=" . $key_email;
    $auction->car_url_tag = "<a href='" . $auction->tracking_car_url . "'>" . $english[215] . "</a>";
    $auction->car_url_tag_204 = "<a href='" . $auction->tracking_car_url . "'>" . $english[204] . "</a>";
    $auction->voucher_url = $siteURL . 'shop_voucher.php?id=' . $auction->voucher_id . '&shop_id=' . $auction->shop_id;
    $auction->stock_take_url = $siteURL . 'stock_take.php?id=' . $auction->auction_number;
    $auction->rma_url = $siteURL . 'rma.php?rma_id=' . $auction->rma_id . '&number=' . $auction->auction_number . '&txnid=' . $auction->txnid;
    $auction->article_url = $auction->articleurl = $siteURL . 'article.php?original_article_id=' . $auction->article_id;
    $auction->article_img = '<img src="' . $siteURL . str_replace('_image.jpg', '_x_1024_image.jpg', $auction->picture_URL) . '"/>';
    $auction->wwourl = $siteURL . 'ware2ware_order.php?id=' . $auction->auction_number;
    $auction->understock_url = $siteURL . 'understock.php?warehouse_id=' . $auction->warehouse_id;
    $auction->opourl = $siteURL . 'op_order.php?id=' . $auction->auction_number;
    $auction->atsurl = $siteURL . 'ats.php?id=' . $auction->auction_number;
    $auction->empurl = $siteURL . 'employee.php?id=' . $auction->auction_number;
    $auction->auctionurl = $siteURL . 'auction.php?number=' . $auction->auction_number . '&txnid=' . $auction->txnid;
    $auction->tracker_url = $siteURL . "tracker.php?a=" . $auction->auction_number . "&t=" . $auction->txnid . "&k=" . $key_email;
    if (!strlen($auction->sa_url)) $auction->sa_url = $siteURL . 'newauction.php?edit=' . $auction->saved_id;

    if ($sellerInfo->get('seller_channel_id') == 4) {
        $auction->sa_url_comments = $siteURL . 'react/condensed/condensed_sa/' . $auction->saved_id . '/comments/';
    } else {
        $auction->sa_url_comments = $auction->sa_url;
    }
    $auction->shipping_cost_url = $siteURL . 'shipping_cost.php?id=' . $auction->auction_number;
    $auction->offerurl = $siteURL . 'offer.php?id=' . $auction->offer_id;
    if ($auction->offer_id) $auction->offer_name = $dbr->getOne("select name from offer where offer_id=" . $auction->offer_id);
    $auction->customerurl = $siteURL . 'customer.php?id=' . $auction->auction_number . '&src=' . ($auction->txnid == -6 ? '_auction' : ($auction->txnid == -7 ? '_jour' : ''));
    $auction->customerid = $auction->auction_number;
    $auction->shipauctionurl = $siteURL . 'shipping_auction.php?number=' . $auction->auction_number . '&txnid=' . $auction->txnid;
    $auction->route_task_url = $siteURL . 'route_task.php?auction_number=' . $auction->auction_number;
    $auction->car_url = $siteURL . 'car.php?id=' . $auction->car_id;
    $atype = $dbr->getOne("select fget_AType(" . $auction->auction_number . "," . $auction->txnid . ")");
    $auction->payment_method_name = $dbr->getOne("select value from payment_method pm
        join translation t on pm.id=t.id and t.table_name='payment_method' and t.field_name='name'
        and t.language='$locallang'
        where pm.code='" . $auction->payment_method . "'");

    if ($atype == '_auction') {
        $auction->orderurl = $siteURL . 'order.php?a=' . $auction->auction_number . '&t=' . $auction->txnid . '&k=' . $key;
    } else {
        $shopuser = $dbr->getRow("select * from customer where id=" . $auction->customer_id);
        $shoprow = $dbr->getRow("select * from shop where '{$auction->server}' in (url, concat('www.', url))");
        $shopURL = 'http://' . $auction->server . '/';
        if (strlen($auction->server)) {
            $auction->orderurl = $shopURL . "order/" . $auction->auction_number . "-" . $auction->txnid
                . "-" . substr(md5($auction->auction_number . $auction->txnid
                    . $auction->username . $shopuser->email . $shopuser->password), 0, 8) . "/";
            $auction->shop_rating_link = $shopURL . "rating/" . $auction->auction_number . "-" . $auction->txnid
                . "-" . substr(md5($auction->auction_number . $auction->txnid
                    . $auction->username . $shopuser->email . $shopuser->password), 0, 8) . "/";
            $auction->shop_rating_link1 = $shopURL . "rating/" . $auction->auction_number . "-" . $auction->txnid
                . "-" . substr(md5($auction->auction_number . $auction->txnid
                    . $auction->username . $shopuser->email . $shopuser->password), 0, 8) . "/1";
            $auction->shop_rating_link2 = $shopURL . "rating/" . $auction->auction_number . "-" . $auction->txnid
                . "-" . substr(md5($auction->auction_number . $auction->txnid
                    . $auction->username . $shopuser->email . $shopuser->password), 0, 8) . "/2";
            $auction->shop_rating_link3 = $shopURL . "rating/" . $auction->auction_number . "-" . $auction->txnid
                . "-" . substr(md5($auction->auction_number . $auction->txnid
                    . $auction->username . $shopuser->email . $shopuser->password), 0, 8) . "/3";
            $auction->shop_rating_link4 = $shopURL . "rating/" . $auction->auction_number . "-" . $auction->txnid
                . "-" . substr(md5($auction->auction_number . $auction->txnid
                    . $auction->username . $shopuser->email . $shopuser->password), 0, 8) . "/4";
            $auction->shop_rating_link5 = $shopURL . "rating/" . $auction->auction_number . "-" . $auction->txnid
                . "-" . substr(md5($auction->auction_number . $auction->txnid
                    . $auction->username . $shopuser->email . $shopuser->password), 0, 8) . "/5";
            $auction->shop_ambassador_link = $shopURL . "ambassador/" . $auction->subs_auction_number . "-" . $auction->txnid
                . "-" . substr(md5($auction->subs_auction_number . $auction->txnid
                    . $auction->username . $shopuser->email . $shopuser->password), 0, 8) . "/";
            $auction->shop_ambassador_link_yes = $shopURL . "ambassador/" . $auction->auction_number
                . "-" . substr(md5($auction->auction_number
                    . $auction->username . $shopuser->email . $shopuser->password), 0, 8) . "-yes-" . $nextID . "/";
            $auction->shop_ambassador_link_no = $shopURL . "ambassador/" . $auction->auction_number
                . "-" . substr(md5($auction->auction_number
                    . $auction->username . $shopuser->email . $shopuser->password), 0, 8) . "-no-" . $nextID . "/";
            $auction->shop_ambassador_link_never = $shopURL . "ambassador/" . $auction->auction_number
                . "-" . substr(md5($auction->auction_number
                    . $auction->username . $shopuser->email . $shopuser->password), 0, 8) . "-never-" . $nextID . "/";
//						.$auction->auction_number.'+'.$auction->txnid.'+'.$auction->username.'+'.$shopuser->email.'+'.$shopuser->password;
            $auction->shop_url = 'http://www.' . $auction->url . '/' . $auction->cat_route . $auction->ShopSAAlias . '.html';
            if ((int)$template_name->smarty && $shoprow->id) {
                $shopCatalogue = new Shop_Catalogue($db, $dbr, $shoprow->id);
                $smarty->assign('shopCatalogue', $shopCatalogue);
                $docs = Shop_Catalogue::getDocs($db, $dbr, $shoprow->id, $auction->lang, $auction->lang);
                foreach ($docs as $doc) {
                    if ($doc->code == 'logo') $smarty->assign('logo', $doc);
                }
                $smarty->assign('def_lang', $auction->lang);
                $banners_header = $shopCatalogue->listBanners(1, 'header');
                $smarty->assign('banners_header', $banners_header);
                if ($shopCatalogue->_shop->ssl) {
                    $http = 'https';
                } else {
                    $http = 'http';
                }
                $smarty->assign('http', $http);
            }
        } else {
            $auction->orderurl = $siteURL . 'order.php?a=' . $auction->auction_number . '&t=' . $auction->txnid . '&k=' . $key;
        }
//		die($auction->auction_number.'-'.$auction->txnid
//						.'-'.$auction->username.'-'.$shopuser->email.'-'.$shopuser->password);
    }
    $auction->orderurl_tag = "<a href='" . $auction->orderurl . "'>" . $english[216] . "</a>";
    $auction->feedbackurl = $siteURL . 'feedback.php?auction=' . $auction->auction_number . '&txnid=' . $auction->txnid . '&key=' . $key;
    $auction->eBayfeedbackurl = getParByName($db, $dbr, $auction->siteid, "_feedback_customer") . $auction->auction_number;
    $auction->eBayurl = getParByName($db, $dbr, $auction->siteid, "_cgi_eBay") . $auction->auction_number;
    if (!isset($auction->alias)) $auction->alias = $dbr->getOne("select name from offer_name where id=" . (int)$auction->name_id);
    $auction->smtp_num = $i;
    $auction->smtp = $sellerInfo->getDefSMTP();
    $auction->smtp = $auction->smtp->smtp;
    $auction->emailto = $sellerInfo->get('email');
    $auction->now = ServerTolocal(date("Y-m-d H:i:s"), $timediff);
    $auction->today = date("Y-m-d");
    $auction->gender_shipping_text = $english[$auction->gender_shipping];
    $auction->gender_invoice_text = $english[$auction->gender_invoice];
    if (is_a($loggedUser, 'User')) {
        $auction->name_of_user = $loggedUser->get('name');
        $auction->email_of_user = $loggedUser->get('email');
    } else {
        $auction->name_of_user = '';
        $auction->email_of_user = '';
    }
    if ($auction->delivery_date_customer != '0000-00-00') {
        $auction->delivery_notes = substitute($sellerInfo4template->getTemplate('delivery_notes', $locallang/*SiteToCountryCode($siteid)*/), $auction);
    } else {
        $auction->delivery_notes = substitute($sellerInfo4template->getTemplate('delivery_notes_default', $locallang/*SiteToCountryCode($siteid)*/), $auction);
    }

    if ($template == 'order_confirmation'){
            $payment_instruction_template = $dbr->getOne("select email_template from payment_method where `code`='" . (is_a($auction, 'Auction')?$auction->get('payment_method'):$auction->payment_method) . "'");
            $auction->payment_instruction = standardEmail($db, $dbr, $auction, $payment_instruction_template, 0, 0, true);
            if($auction->payment_instruction==1){
                $auction->payment_instruction = '';
            }
            $ashop_id = (int)$dbr->getOne("select fget_AShop(".(is_a($auction, 'Auction')?$auction->get('auction_number'):$auction->auction_number).",".(is_a($auction, 'Auction')?$auction->get('txnid'):$auction->txnid).")");
            if(!empty($ashop_id)){
            $subs_q = "
                select t.*
                , sum(
                    ROUND((IFNULL(ac.price_sold,0) - IFNULL(ac.ebay_listing_fee,0) - IFNULL(ac.additional_listing_fee,0) - IFNULL(ac.ebay_commission,0) - IFNULL(ac.vat,0) - IFNULL(ac.purchase_price,0)
                        + IFNULL(ac.shipping_cost,0) -IFNULL(ac.vat_shipping,0) - IFNULL(ac.effective_shipping_cost,0)
                        + IFNULL(ac.COD_cost,0) -IFNULL(ac.vat_COD,0) - IFNULL(ac.effective_COD_cost,0)
                        /*- IFNULL(ac.packing_cost,0)*/)*IFNULL(ac.curr_rate,0), 2)
        ) as brutto_income_2_eur
        , sum(
            ROUND((IFNULL(ac.price_sold,0) - IFNULL(ac.ebay_listing_fee,0) - IFNULL(ac.additional_listing_fee,0) - IFNULL(ac.ebay_commission,0) - IFNULL(ac.vat,0) - IFNULL(ac.purchase_price,0)
                + IFNULL(ac.shipping_cost,0) -IFNULL(ac.vat_shipping,0) - IFNULL(ac.effective_shipping_cost,0)
                + IFNULL(ac.COD_cost,0) -IFNULL(ac.vat_COD,0) - IFNULL(ac.effective_COD_cost,0)
                /*- IFNULL(ac.packing_cost,0)*/), 2)
        ) as brutto_income_2
        , sum(
            ROUND(IFNULL(ac.price_sold,0) + IFNULL(ac.shipping_cost,0) + IFNULL(ac.COD_cost,0)
                + IFNULL(ac.vat_COD,0) + IFNULL(ac.vat_shipping,0),2)) as revenue
        , max(ac.curr_rate) curr_rate
        from (
            select au.offer_id, tmessage_to_buyer3.value message_to_buyer3, offer.message3_activate, offer.add_shipping_rules
            , total_price+total_shipping+total_cod+total_cc_fee price
            , au.saved_id, au.auction_number, au.txnid
            , min(sa1.shop_catalogue_id) catalogue_id, au.quantity, alias.name alias
            , tcatalogue_name.value catalogue_name
            , CONCAT(mau.auction_number,'/',mau.txnid) `order`
            from auction au
            join offer on au.offer_id=offer.offer_id
            join invoice i on i.invoice_number=au.invoice_number
            join auction mau on au.main_auction_number=mau.auction_number and au.main_txnid=mau.txnid
            join sa{$ashop_id} sa1 on sa1.id=au.saved_id
            left join sa_all master_sa on sa1.master_sa=master_sa.id
            join translation tcatalogue_name on tcatalogue_name.id=sa1.shop_catalogue_id
            and tcatalogue_name.table_name='shop_catalogue'
            and tcatalogue_name.field_name='name'
            and tcatalogue_name.language = mau.lang
            join translation tShopDesription on tShopDesription.id=IFNULL(master_sa.id, sa1.id)
            and tShopDesription.table_name='sa'
            and tShopDesription.field_name='ShopDesription'
            and tShopDesription.language = mau.lang
            join translation tmessage_to_buyer3 on tmessage_to_buyer3.id=IFNULL(master_sa.offer_id, sa1.offer_id)
            and tmessage_to_buyer3.table_name='offer'
            and tmessage_to_buyer3.field_name='message_to_buyer3'
            and tmessage_to_buyer3.language = mau.lang
            join offer_name alias on tShopDesription.value=alias.id
            where au.main_auction_number=".(is_a($auction, 'Auction')?$auction->get('auction_number'):$auction->auction_number)." and au.main_txnid=".(is_a($auction, 'Auction')?$auction->get('txnid'):$auction->txnid)."
            group by au.auction_number
            ) t
        join orders o on t.auction_number=o.auction_number and t.txnid=o.txnid
        left JOIN article_list al ON  o.article_list_id = al.article_list_id
        LEFT JOIN auction_calcs ac ON o.article_list_id = ac.article_list_id
        AND o.auction_number = ac.auction_number
        AND o.txnid = ac.txnid
        and ac.article_id=o.article_id
        group by t.auction_number
        ";}else{
            $subs_q = "select t.*
                , sum(
                    ROUND((IFNULL(ac.price_sold,0) - IFNULL(ac.ebay_listing_fee,0) - IFNULL(ac.additional_listing_fee,0) - IFNULL(ac.ebay_commission,0) - IFNULL(ac.vat,0) - IFNULL(ac.purchase_price,0)
                        + IFNULL(ac.shipping_cost,0) -IFNULL(ac.vat_shipping,0) - IFNULL(ac.effective_shipping_cost,0)
                        + IFNULL(ac.COD_cost,0) -IFNULL(ac.vat_COD,0) - IFNULL(ac.effective_COD_cost,0)
                        /*- IFNULL(ac.packing_cost,0)*/)*IFNULL(ac.curr_rate,0), 2)
        ) as brutto_income_2_eur
        , sum(
            ROUND((IFNULL(ac.price_sold,0) - IFNULL(ac.ebay_listing_fee,0) - IFNULL(ac.additional_listing_fee,0) - IFNULL(ac.ebay_commission,0) - IFNULL(ac.vat,0) - IFNULL(ac.purchase_price,0)
                + IFNULL(ac.shipping_cost,0) -IFNULL(ac.vat_shipping,0) - IFNULL(ac.effective_shipping_cost,0)
                + IFNULL(ac.COD_cost,0) -IFNULL(ac.vat_COD,0) - IFNULL(ac.effective_COD_cost,0)
                /*- IFNULL(ac.packing_cost,0)*/), 2)
        ) as brutto_income_2
        , sum(
            ROUND(IFNULL(ac.price_sold,0) + IFNULL(ac.shipping_cost,0) + IFNULL(ac.COD_cost,0)
                + IFNULL(ac.vat_COD,0) + IFNULL(ac.vat_shipping,0),2)) as revenue
        , max(ac.curr_rate) curr_rate
        from (
            select au.offer_id, tmessage_to_buyer3.value message_to_buyer3, offer.message3_activate, offer.add_shipping_rules
            , total_price+total_shipping+total_cod+total_cc_fee price
            , au.saved_id, au.auction_number, au.txnid
 , au.quantity, alias.name alias
            , CONCAT(mau.auction_number,'/',mau.txnid) `order`
            from auction au
            join offer on au.offer_id=offer.offer_id
            join invoice i on i.invoice_number=au.invoice_number
            left join auction mau on au.main_auction_number=mau.auction_number and au.main_txnid=mau.txnid
            join translation tmessage_to_buyer3 on tmessage_to_buyer3.id=au.offer_id
            and tmessage_to_buyer3.table_name='offer'
            and tmessage_to_buyer3.field_name='message_to_buyer3'
            and tmessage_to_buyer3.language = IFNULL(mau.lang, au.lang)
            left join offer_name alias on au.name_id=alias.id
            where IFNULL(mau.auction_number,au.auction_number)=".(is_a($auction, 'Auction')?$auction->get('auction_number'):$auction->auction_number)." and au.txnid=".(is_a($auction, 'Auction')?$auction->get('txnid'):$auction->txnid)."
            group by au.auction_number
            ) t
        join orders o on t.auction_number=o.auction_number and t.txnid=o.txnid
        left JOIN article_list al ON  o.article_list_id = al.article_list_id
        LEFT JOIN auction_calcs ac ON o.article_list_id = ac.article_list_id
        AND o.auction_number = ac.auction_number
        AND o.txnid = ac.txnid
        and ac.article_id=o.article_id
        group by t.auction_number";
        }
        $subs = $dbr->getAll($subs_q);
        if(isset($subs[0]) && strlen($subs[0]->message_to_buyer3) && $subs[0]->message3_activate) {
            $auction->data->offer_id = $subs[0]->offer_id;
            $auction->offer_id = $subs[0]->offer_id;
        }
        $auction->conditions_agree = standardEmail($db, $dbr, $auction, 'conditions_agree', 0, 0, true);
        if($auction->payment_method == 1 && $sellerInfo->get('giro_transfer_form') == 1){
            $rec = new stdClass;
            $rec->data = file_get_contents('./Einzahlungsschein_Beliani.pdf');
            $rec->name = 'Einzahlungsschein_Beliani.pdf';
            $attachments[] = $rec;
        }
        if (!$sellerInfo->get('dontsend_ruckgabebelehrung')) {
            $auction->send_Ruckgabebelehrung = standardEmail($db, $dbr, $auction, 'send_Ruckgabebelehrung', 0, 0, true);
            $smarty->assign('title','Ruckgabebelehrung');
            $smarty->assign('content_html',$auction->send_Ruckgabebelehrung);
            $content_ruckgabebelehrung = WKHtmlToPDF::render($smarty->fetch('_layout.tpl'));
            $rec = new stdClass;
            $rec->data = $content_ruckgabebelehrung;
            $rec->name = 'Ruckgabebelehrung.pdf';
            $attachments[] = $rec;
        }
    }
    if ($template == 'supervisor_alert' || $template == 'improvent_email') {
        require_once 'lib/Category.php';
        $vars = unserialize($auction->details);
        $category = Category::path($db, $dbr, $siteid, $vars['category']);
        $fun = create_function('$a', 'return $a->CategoryName;');
        $categoryName = implode('::', array_map($fun, $category));
        $category2 = Category::path($db, $dbr, $vars['siteid'], $vars['category2']);
        $categoryName2 = implode('::', array_map($fun, $category2));
        $offer = new Offer($db, $dbr, $auction->offer_id, $locallang);
        $auction->offer_name = $offer->get('name');
        $auction->category = $vars['category'] . ': ' . $categoryName;
        $auction->category2 = $vars['category2'] . ': ' . $categoryName2;
        $auction->category_featured = isset($vars['featured']) && $vars['featured'] ? 'yes' : 'no';
        $auction->gallery = $dbr->getOne("SELECT
            SUBSTRING_INDEX( SUBSTRING_INDEX( SUBSTRING_INDEX( apt.value, 's:7:" . '"gallery"' . ";', -1 ) , '" . '"' . "', 2 ), '" . '"' . "', -1) gallery
            from auction_par_text apt
            where apt.key='details' and auction_number=$auction->auction_number and txnid=$auction->txnid");
        if (!PEAR::isError($auction->gallery)) if (strlen($auction->gallery)) {
            $fn = basename($auction->gallery);
            $exts = explode('.', $fn);
            $ext = end($exts);
            switch ($ext) {
                case 'jpeg':
                case 'jpg':
                    $im = imagecreatefromjpeg($auction->gallery);
                    break;
                case 'bmp':
                    $im = imagecreatefromwbmp($auction->gallery);
                    break;
            }
            $destsx = 500;
            $destsy = $destsx * imagesy($im) / imagesx($im);
            $img2 = imagecreatetruecolor($destsx, $destsy);
            imagecopyresized($img2, $im, 0, 0, 0, 0, $destsx, $destsy, imagesx($im), imagesy($im));
            if (imagesx($im) < 500)
                $img2 = $im;
            imagejpeg($img2, './tmp/' . $fn, 100);
            $rec = new stdClass;
            $rec->data = file_get_contents('./tmp/' . $fn);
            $rec->name = $fn;
            $attachments[] = $rec;
            unlink('./tmp/' . $fn);
        };
    }
    if (
        $template == 'resend_payment_instruction_1' ||
        $template == 'resend_payment_instruction_2' ||
        $template == 'payment_instruction_1'
    ) {
        if ($auction->payment_method == 3 || $auction->payment_method == 2 || $auction->paid ||
            !$invoice->get('open_amount') || !$sellerInfo->get('send_payment_info')) {
            echo 'do not send';
            return 1;
        }
        $email = $auction->email_invoice;
        if($sellerInfo->get('giro_transfer_form') == 1){
            $rec = new stdClass;
            $rec->data = file_get_contents('./Einzahlungsschein_Beliani.pdf');
            $rec->name = 'Einzahlungsschein_Beliani.pdf';
            $attachments[] = $rec;
        }
    } elseif ($template == 'rating_case') {
        $email = $dbrr->getOne("select group_concat(distinct users.email)
                                from users
                                where users.deleted=0 and users.rating_case
                                and (users.rating_case_if_manage=0 or
                                (users.rating_case_if_manage=1 and exists (select o.id
                                from auction au
                                left join orders o on o.auction_number=au.auction_number and o.txnid=au.txnid
                                join article a on a.article_id=o.article_id and a.admin_id=0
                                join op_company_emp opc on opc.company_id=a.company_id
                                join employee e on opc.emp_id=e.id
                                where au.auction_number={$auction->auction_number} and au.txnid={$auction->txnid}
                                and e.username=users.username
                                union
                                select o.id
                                from auction au
                                left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
                                left join orders o on o.auction_number=au.auction_number and o.txnid=au.txnid
                                join article a on a.article_id=o.article_id and a.admin_id=0
                                join op_company_emp opc on opc.company_id=a.company_id
                                join employee e on opc.emp_id=e.id
                                where mau.auction_number={$auction->auction_number} and mau.txnid={$auction->txnid}
                                and e.username=users.username))
                                )");
        $rating = $dbr->getRow("select text,code from auction_feedback
            where auction_number=$auction->auction_number and txnid=$auction->txnid and type='received'
            order by datetime desc limit 1 ");

        $auction->rating = $rating->text;

        if ($auction->txnid == 3) {
            if ($rating->code >= 4) $rating_received_color = 'green';
            elseif ($rating->code == 3) $rating_received_color = 'black';
            elseif ($rating->code <= 2) $rating_received_color = 'red';
            else $rating_received_color = '';
        } else {
            if ($rating->code == 1) $rating_received_color = 'green';
            elseif ($rating->code == 2) $rating_received_color = 'black';
            elseif ($rating->code == 3) $rating_received_color = 'red';
            else $rating_received_color = '';
        }
        $auction->rating_color = $rating_received_color;
        if ($auction->lang != 'english' && $auction->lang != 'german') {
            $result = translate_js_backend($rating->text, $auction->lang, 'english');
            $rating->text .= '<br><br>' . $result[1];
        }
        $auction->rating_case_text = $rating->text;
        $smarty->assign('stars_offset',(5-$rating->code)*20);
        $auction->rating_case_stars = $smarty->fetch('_stars.tpl');
    } elseif ($template == 'bonus_usage_notification') {
        $email = $sellerInfo->get('bonus_usage_notification_email');
        if (!strlen($email)) return false;
        $auction->bonus_title = $ins_id;
    } elseif ($template == 'wishlist_share_email') {
        $from = $auction->from;
        $from_name = $auction->from_name;
        $message = $auction->message;
        $subject = $auction->subject;
        $attachments = 'html';
    } elseif ($template == 'resend_wishlist_reminder') {
        $from = $auction->from;
        $message = $auction->message;
        $subject = $auction->subject;
        $attachments = 'html';
    } elseif ($template == 'supervisor_responsibles') {
        $email = $dbr->getOne("select email from users where deleted=0 and username='" . $auction->sv_username . "'");
        $message = $sellerInfo4template->getTemplate($template, '');
        list ($subject, $message) = explode("\n", $message, 2);
//   		    print_r($sellerInfo);
    } elseif ($template == 'improvent_email') {
        $email_array = $dbr->getAssoc('select username, email from users where deleted=0 and get_customer_comment');
        if (strlen($sellerInfo->get('improvement_email')))
            $email_array = array_merge(array($sellerInfo->get('improvement_email')), $email_array);
        $email = implode(', ', $email_array);
        $message = $sellerInfo4template->getTemplate($template, '');
        list ($subject, $message) = explode("\n", $message, 2);
//   		    print_r($sellerInfo);
    } elseif ($template == 'new_comment_auction') {
        $auction->lastcomment = $dbr->getOne("select comment
                          from auction_sh_comment where auction_number=" . $auction->auction_number . " and txnid=" . $auction->txnid . "
                      order by id desc
                      LIMIT 0, 1");
//			print_r($auction->lastcomment);
    } elseif ($template == 'resend_winning_mail_2') {
        //$subject = "Auction $auction->auction_number won - final reminder!";
        $email = $auction->email;
    } elseif ($template == 'payment_notification') {
        $auction->payment = $dbr->getOne("select value from translation where table_name='payment_method'
                and field_name='name' and id=(select id from payment_method where `code`='{$auction->payment_method}')
                and language='$auction->lang'");
        $email = $sellerInfo->get('payment_notify_using_email');
    } elseif ($template == 'resend_winning_mail_1') {
        //$subject = "Auction $auction->auction_number won - reminder!";
        $email = $auction->email;
    } elseif ($template == 'winning_mail') {
        //$subject = "Auction $auction->auction_number won !";
        $email = $auction->email;
    } elseif ($template == 'thank_receiving_money') {
        //$subject = "Thank you  for your payment";
        $email = $auction->email_invoice;
    } elseif ($template == 'ready_to_pickup') {
        //$subject = "Auction $auction->auction_number : ready to pickup";
        $email = $auction->email_invoice;
        $warehouse = new Warehouse($db, $dbr, $auction->pickup_warehouse);
        $auction->warehouse_name = $warehouse->get('name');
        $auction->warehouse_address = $warehouse->get('address1') . ' ' . $warehouse->get('address2') . ' ' . $warehouse->get('address3');
        $auction->warehouse_phone = $warehouse->get('phone');
    } elseif ($template == 'resend_ready_to_pickup_1') {
        //$subject = "Auction $auction->auction_number : ready to pickup - reminder";
        $email = $auction->email_invoice;
        $warehouse = new Warehouse($db, $dbr, $auction->pickup_warehouse);
        $auction->warehouse_name = $warehouse->get('name');
        $auction->warehouse_address = $warehouse->get('address1') . ' ' . $warehouse->get('address2') . ' ' . $warehouse->get('address3');
        $auction->warehouse_phone = $warehouse->get('phone');
    } elseif ($template == 'resend_ready_to_pickup_2') {
        //$subject = "Auction $auction->auction_number : ready to pickup - final reminder";
        $email = $auction->email_invoice;
        $warehouse = new Warehouse($db, $dbr, $auction->pickup_warehouse);
        $auction->warehouse_name = $warehouse->get('name');
        $auction->warehouse_address = $warehouse->get('address1') . ' ' . $warehouse->get('address2') . ' ' . $warehouse->get('address3');
        $auction->warehouse_phone = $warehouse->get('phone');
    } elseif ($template == 'rating_made') {
        //$subject = "Auction $auction->auction_number : rating made";
        $email = $auction->email;
    } elseif ($template == 'wait_receiving_rating') {
        //$subject = "Auction $auction->auction_number : waiting for rating";
        $email = $auction->email;
    }elseif ($template == 'op_order_container_arrived') {
        $email = $auction->email_invoice;
    }elseif ($template == 'add_stock_email') {
        $email = $auction->email_invoice;
    }elseif ($template == 'article_arrived_no_active_sa') {
        $email = $auction->email_invoice;
    }elseif ($template == 'order_confirmation') {
        $offer_id = $auction->offer_id;
        if (!$offer_id) {
            $offer_id = $dbr->getOne("select group_concat(offer_id) from auction where main_auction_number=" . $auction->auction_number . "
                    and main_txnid=" . $auction->txnid);
        }
        if ($sellerInfo4template->get('seller_channel_id') == 3) {
            $type = 'a';
        } else {
            switch ($auction->txnid) {
                case 1:
                    $type = '';
                    break;
                case 3:
                    $type = 's';
                    break;
                case 0:
                    $type = '';
                    break;
                default:
                    $type = 'f';
                    break;
            }
        }
        $shipping_plan_id = $dbr->getOne("SELECT value
                FROM translation
                WHERE table_name = 'offer'
                AND field_name = '" . $type . "shipping_plan_id'
                AND language = '" . $auction->siteid . "'
                AND id in ($offer_id)");

        $shipping_plan = new \ShippingPlan($db, $dbr, $shipping_plan_id, $locallang);
        $auction->rules_to_confirm = nl2br($shipping_plan->data->rules_to_confirm);

        $email = $auction->email_invoice;
        if (!strlen($email)) $email = $auction->email;
        $auction->order_confirmation = formatInvoice($auction->auction_number, $auction->txnid);
        $message = $sellerInfo4template->getTemplate($template, $locallang);
        list ($subject, $message) = explode("\n", $message, 2);
        $subject = substitute($subject, $auction);
        $message = substitute($message, $auction);
        $message = substitute($message, $auction); // 2nd time, yes, yes
        if($auction->payment_method == 'klr_shp'){ // remove invoice number in case of klarna
            $message = preg_replace('~Invoice.*\d*(<br>)?~', '', $message); // en
            $message = preg_replace('~Fakturanummer.*\d*(<br>)?~', '', $message); // svenska
            $message = preg_replace('~Factuurnummer.*\d*(<br>)?~', '', $message); // nl
            $message = preg_replace('~Rechnungsnummer.*\d*(<br>)?~', '', $message); // de
        }
        // add meta charset utf-8, otherwise pdf displays bad non-utf-8 symbols
        $re = '~(<\s*?head\b[^>]*>)(.*?)(<\/head\b[^>]*>)~s';
        $message = preg_replace($re, '$1$2' . '<meta charset="UTF-8">' . '$3', $message);

        $fn = md5($message);
        file_put_contents("tmp/$fn.html", $message);
        $comand = "/usr/local/bin/wkhtmltopdf \"tmp/$fn.html\" tmp/$fn.pdf";
        $r = exec($comand);
        if (file_exists("tmp/$fn.pdf")) {
            $result=file_get_contents("tmp/$fn.pdf");
            unlink("tmp/$fn.pdf");
            unlink("tmp/$fn.html");
        }
        if($result){
            $rec = new stdClass;
            $rec->data = $result;
            $rec->name = $english[183] . '.pdf';
            $attachments[] = $rec;
            $rec = new stdClass;
        }

        $message = '<html>
<head>
<meta	http-equiv="Content-Type"	content="charset=utf-8" />
</head>
<body>
' . (($message)) . '
</body>
</html>
';
    } elseif ($template == 'shipping_details' || $template == 'picked_up_details' || $template == 'ready_to_pick_up_details') {
        //$subject = "Auction $auction->auction_number : shipping details";
        $email = $auction->email_shipping;
        if (!strlen($email)) $email = $auction->email;
        if ($auction->main_auction_number) return; // we dont send shipping_details for subinvoices, https://trello.com/c/oHiYashA/3784-hanna-kot-subinvoice-e-mails
        if ($email == 'removed') return;
        $method = new ShippingMethod($db, $dbr, $auction->shipping_method);
        if ($sellerInfo->get('dontsendShippingConfirmation') || $method->get('dontsendShippingConfirmation')) {/* echo '!dontsendShippingConfirmation!'; print_r($sellerInfo); print_r($method);*/
            return;
        }
        $auction->shipping_company_name = $method->get('company_name');
        $auction->shipping_company_phone = $method->get('phone');
        $auction->items_list = formatShippingList($db, $dbr, $auction->auction_number, $auction->txnid, '', $locallang, '');
        $auction->items_list_past = formatShippingList($db, $dbr, $auction->auction_number, $auction->txnid, 'past', $locallang, '');
        $auction->items_list_present = formatShippingList($db, $dbr, $auction->auction_number, $auction->txnid, 'present', $locallang, '');
        $auction->items_list_future = formatShippingList($db, $dbr, $auction->auction_number, $auction->txnid, 'future', $locallang, '');
        $auction->items_list_past_present = formatShippingList($db, $dbr, $auction->auction_number, $auction->txnid, 'past_present', $locallang, '');
        $auction->ready_items_list = formatShippingList($db, $dbr, $auction->auction_number, $auction->txnid, '', $locallang, 'ready_to_pick_up_details');
        $auction->ready_items_list_past = formatShippingList($db, $dbr, $auction->auction_number, $auction->txnid, 'past', $locallang, 'ready_to_pick_up_details');
        $auction->ready_items_list_present = formatShippingList($db, $dbr, $auction->auction_number, $auction->txnid, 'present', $locallang, 'ready_to_pick_up_details');
        $auction->ready_items_list_future = formatShippingList($db, $dbr, $auction->auction_number, $auction->txnid, 'future', $locallang, 'ready_to_pick_up_details');
        $auction->ready_items_list_past_present = formatShippingList($db, $dbr, $auction->auction_number, $auction->txnid, 'past_present', $locallang, 'ready_to_pick_up_details');
#			die();
        $attachments[] = 'html';
        $message = $sellerInfo4template->getTemplate($template, $locallang);
        $sms = $sellerInfo4template->getSMS($template, $locallang);
        $bcc = $sellerInfo->get('bcc');
        list ($subject, $message) = explode("\n", $message, 2);

        if($template == 'shipping_details' && $auction->payment_method != 'klr_shp'){
            $invoice = new Invoice($db, $dbr, $auction->invoice_number);
            $rec = new stdClass;
            $rec->data = $invoice->getInvoicePDF($db, $dbr, $auction->auction_number, $auction->txnid);
            $rec->name = ($sellerInfo->get('dont_show_vat') ? $english[250] : $english[59]) . ' ' . $auction->invoice_number . '.pdf';
            $attachments[] = $rec;
            $rec = new stdClass;

            $items_list = Order::listAll($db, $dbr, $auction->auction_number, $auction->txnid, 1);
            $names = array();
            foreach ($items_list as $item) {
                if ($item->manual) continue;
                $senddocs = Article::getDocsTranslated($db, $dbr, $item->article_id, 1);
                $subarticles = Article::getSubArticles($item->article_id);
                foreach ($subarticles as $article) {
                    $senddocs = array_merge($senddocs, Article::getDocsTranslated($db, $dbr, $article, 1));
                }
                if (count($senddocs)) foreach ($senddocs as $fichier_attache) {
                    if (strlen($fichier_attache->$locallang->name)
                        && !in_array($fichier_attache->$locallang->name, $names)
                    ) {
                        $rec = new stdClass;
                        $rec->data = base64_decode($fichier_attache->$locallang->data);
                        $rec->name = $fichier_attache->$locallang->name;
                        $attachments[] = $rec;
                        $names[] = $fichier_attache->$locallang->name;
                    };
                }
            }
            // Tracking numbers
            $tracking_numbers = '';
            $tracking_numbers_buf = getTrackingNumbersList($db, $dbr, $auction->auction_number, $auction->txnid, 'present', $locallang, '');
            if(sizeof($tracking_numbers_buf)){
                foreach($tracking_numbers_buf as $tracking_number=>$tracking_url){
                    $tracking_numbers .= empty($tracking_numbers) ? $tracking_number : ','.$tracking_number;
                    if(!$auction->tracking_url){
                        $auction->tracking_url = $tracking_url;
                    }
                }
                $auction->separated_tracking_numbers = $tracking_numbers;
            }
        }

        $subject = substitute($subject, $auction);
        $message = substitute($message, $auction);
        $message = substitute($message, $auction); // 2nd time, yes, yes
    } elseif ($template == 'order_placed') {
        //$subject = "Auction $auction->auction_number : order placed";
        $email = $sellerInfo->get('email');
        $order = Order::listAll($db, $dbr, $auction->auction_number, $auction->txnid, 1);
        $currCode = siteToSymbol($auction->get('siteid'));
        $auction->items_list = formatItemsList($order, $currCode, $locallang);
    } elseif ($template == 'rma_based_invoice_sent' || $template == 'Ticket_based_driver_task_mark_as_shipped') {
        global $timediff;
        global $siteURL;
        $sellerInfo = new SellerInfo($db, $dbr, Config::get($db, $dbr, 'aatokenSeller'), $locallang);
        $au = new Auction($db, $dbr, $auction->auction_number, $auction->txnid);
        $rma = new Rma($db, $dbr, $au, $auction->rma_id);
        $user = new User($db, $dbr, $rma->get('responsible_uname'));
        $email = $user->get('email');
        if (!strlen($email)) $email = $sellerInfo->get('support_email');
        $auction->now = ServerTolocal(date("Y-m-d H:i:s"), $timediff);
        $message = $sellerInfo4template->getTemplate($template, '');
        list ($subject, $message) = explode("\n", $message, 2);
    } elseif ($template == 'call_goods_back') {
        $rma_id = $ins_id;
        $auction->rma_id = $rma_id;
        $tnid = $rma_spec_id;
        $shipping_method = $dbr->getRow("select n.called_back_to, p.name as packet_name, n.pickup_date, n.shipping_number, n.weight
            from tracking_numbers n LEFT JOIN tn_packets p ON p.id=n.packet_id
              where n.auction_number=" . $auction->auction_number . "
              and n.txnid=" . $auction->txnid . " and n.id=$tnid");
        if (PEAR::isError($shipping_method)) aprint_r($shipping_method);
        $auction->pickup_date = $shipping_method->pickup_date;
        if (!strlen($auction->pickup_date)) $auction->pickup_date = $english[154];
        $auction->weight = $shipping_method->weight;
        $auction->shipping_number = $shipping_method->shipping_number;
        if (!strlen($auction->shipping_number)) $auction->shipping_number = $english[154];
        $auction->packet_name = $shipping_method->packet_name;
        if (!strlen($auction->packet_name)) $auction->packet_name = $english[154];
        $shipping_method_id = $shipping_method->called_back_to;
        $meth = new ShippingMethod($db, $dbr, $shipping_method_id);
        $email = $meth->get('cs_email');
        if (!strlen($email)) {
            $errormsg = 'Email is not assigned to shipping company, call back order is NOT sent';
            return false;
        }
        if (is_a($loggedUser, 'User')) $email .= ', ' . $loggedUser->get('email');
        $auction->customer_number = $meth->get('customer_number');
        $auction->return_name = $meth->get('return_name');
        $auction->return_address1 = $meth->get('return_address1');
        $auction->return_address2 = $meth->get('return_address2');
        $auction->return_address3 = $meth->get('return_address3');
    } elseif ($template == 'stop_goods') {
        $rma_id = $ins_id;
        $tnid = $rma_spec_id;
        $row = $dbr->getRow("select number, stop_to from tracking_numbers
              where auction_number=" . $auction->auction_number . "
              and txnid=" . $auction->txnid . " and id=$tnid");
        $auction->trackingnumber = $row->number;
        $shipping_method_id = $row->stop_to;
        if (PEAR::isError($shipping_method_id)) aprint_r($shipping_method_id);
        $meth = new ShippingMethod($db, $dbr, $shipping_method_id);
        $email = $meth->get('cs_email');
        if (!strlen($email)) {
            $errormsg = 'Email is not assigned to shipping company, stop order is NOT sent';
            return false;
        }
        if (is_a($loggedUser, 'User')) $email .= ', ' . $loggedUser->get('email');
        $auction->customer_number = $meth->get('customer_number');
        $auction->return_name = $meth->get('return_name');
        $auction->return_address1 = $meth->get('return_address1');
        $auction->return_address2 = $meth->get('return_address2');
        $auction->return_address3 = $meth->get('return_address3');
    } elseif ($template == 'temp_rating_request') {
//			$auction->siteurl = $siteURL;
//            $email = $auction->email;
    } elseif ($template == 'mark_as_shipped') {
        $emails = $dbr->getAssoc("select 1, '" . $sellerInfo->get('marked_as_Shipped_email') . "'
            from payment_method
            where not dontsend_marked_as_Shipped and code='" . $auction->payment_method . "'
                union
            select 2, marked_as_Shipped_email
                from source_seller where not dontsend_marked_as_Shipped and id=" . $auction->source_seller_id);
        $email = implode(',', $emails);
//		$srcs = $dbr->getAll("select distinct `type` from auction_marked_as_Shipped_src where auction_id=".$auction->id);
        /*		foreach($srcs as $src) {
                    switch ($src->type) {
                        case 'payment_method':
                            $email .= $sellerInfo->get('marked_as_Shipped_email');
                        break;
                        case 'source_seller':
                            $email .= $dbr->getOne("select marked_as_Shipped_email from source_seller where id=".$auction->source_seller_id);
                        break;
                    }
                }*/
        if (!strlen($email)) {
            $email = 'not defined';
            return;
        }
//			die($email);
    } elseif ($template == 'supervisor_alert') {
        $email_array = $dbr->getAssoc('select username, email from users where deleted=0 and get_manag_alert');
        $email_array[] = $sellerInfo->get('management_alert_email');
        $email = implode(', ', $email_array);
    } elseif ($template == 'mass_email') {
        $email = $auction->email;
        if (strlen($auction->email_invoice))
            $email .= ', ' . $auction->email_invoice;
        if (strlen($auction->email_shipping))
            $email .= ', ' . $auction->email_shipping;
        /*			$template = mysql_escape_string('<a onclick="alert('."'"
                        .(str_replace("\r", "", str_replace("\n", '\n', ($auction->text))))."'".')">mass_email</a>');*/
        $message = $auction->text; //$sellerInfo->getTemplate('mass_email', SiteToCountryCode($siteid));//	$sellerInfo->get($template);
        list ($subject, $message) = explode("\n", $message, 2);
    } elseif ($template == 'mass_doc' || $template == 'mass_doc_win' || $template == 'mass_doc_after_ticket_open'
        || $template == 'ricardo_pics' || $template == 'supplier_packing_docs'
    ) {
        $email = $auction->email;
        if (strlen($auction->email_invoice))
            $email .= ', ' . $auction->email_invoice;
        if (strlen($auction->email_shipping))
            $email .= ', ' . $auction->email_shipping;
        foreach ($auction->docs as $doc) {
            $rec = new stdClass;
            $rec->data = $doc->data;
            $rec->name = $doc->name;
            $attachments[] = $rec;
        }
//			print_r($attachments);
    } elseif ($template == 'shop_requested_voucher_email') {
        $rec = new stdClass;
        $rec->data = $auction->templateHTML;
        $rec->name = 'doc.html';
        $attachments[] = $rec;
        $rec = new stdClass;
        $rec->data = $auction->templatePDF;
        $rec->name = 'doc.pdf';
        $attachments[] = $rec;
    } elseif ($template == 'ins_list') {
        $method = new ShippingMethod ($db, $dbr, $auction->shipping_method);
        $email = $method->get('email');
        if (is_a($loggedUser, 'User')) $email .= ',' . $loggedUser->get('email');
        $inses = Auction::findInsurance($db, $dbr, $auction->shipping_method, 1);
        foreach ($inses as $ins) {
            $list .= $siteURL . 'insurance.php?id=' . $ins->ins_id . "\r\n";
        }
        $auction->INS_list = $list;
        $message = $sellerInfo4template->getTemplate($template, '');
        list ($subject, $message) = explode("\n", $message, 2);
        $subject = substitute($subject, $auction);
        $message = substitute($message, $auction);
    } elseif ($template == 'brutto_income') {
        $rec = new stdClass;
        $static = '';
        $bi_sellers = "&bi_sellers[]=" . implode("&bi_sellers[]=", $auction->bi_sellers);
        $auction->calcs_to_send_url = $siteURL . "calcs_to_send.php?from_date_inv=" . $auction->from_date_inv
            . "&to_date_inv=" . $auction->to_date_inv
            . "&bi_sellers=" . $bi_sellers;
        for ($i = 0; $i < 10; $i++) {
            $file = fopen($siteURL . "calcs_to_send.php?from_date_inv=" . $auction->from_date_inv
                . "&to_date_inv=" . $auction->to_date_inv
                . "&bi_sellers=" . $bi_sellers, "r");
            if (!$file) {
                $rep .= "Unable to open remote file." . $siteURL . "calcs_to_send.php?from_date_inv=" . $auction->from_date_inv
                    . "&to_date_inv=" . $auction->to_date_inv
                    . "&bi_sellers=" . $bi_sellers . ", $i time\n";
                sleep(10);
            } else {
                break;
            }
        }
        while (!feof($file)) {
            $static .= fgets($file, 1024);
        }
        fclose($file);
        $rec->data = $static;
        $rec->name = 'Brutto Income.html';
        $attachments[] = $rec;
        $rec = new stdClass;
        $reccs = getCalcsBy($db, $dbr, '', '', '', '', '', '',
            $auction->from_date_inv, $auction->to_date_inv,
            '', '', '', '', '', '', '', '', "'" . implode("','", $auction->bi_sellers_orig) . "'");
        $rec->data = formatBruttoIncomeXLS($reccs);
        $rec->name = 'Brutto Income.xls';
        $attachments[] = $rec;
        $email = $auction->super_emails;
        $message = $sellerInfo4template->getTemplate($template, '');
        list ($subject, $message) = explode("\n", $message, 2);
        $total_amount = substr($static, strpos($static,
            '<b>Total brutto income - Total refunds - Total fees - Total Marketing = '));
        $total_amount = substr($total_amount, strlen(
            '<b>Total brutto income - Total refunds - Total fees - Total Marketing = '));
        $total_amount = substr($total_amount, 0, strpos($total_amount, '</b>'));
        if (isset($_GET['debugDate'])) {
            $dateForSubject = $_GET['debugDate'];
        } else {
            $dateForSubject  = date('Y-m-d');
        }
        $subject .= ' ' . $dateForSubject . ', ' . $auction->title . ', ' . $total_amount;
        if (strlen($rep)) {
            mail("baserzas@gmail.com", "brutto_income", $rep);
            echo $rep;
        }
    } elseif ($template == 'inventory_status') {
        if ($ins_id) {
            $rma_id = $ins_id;
            $sups = $dbr->getAll("select email, rma_id from op_company oc
                join article a on oc.id=a.company_id
                join rma_spec rs on a.article_id=rs.article_id and not a.admin_id
                where rs.rma_id=$rma_id
                ");
            $supplier_email = '';
            foreach ($sups as $suprow) $supplier_email .= $suprow->email . ', ';
            $pics = $dbr->getAll("select rp.* from rma_pic rp
                join rma_spec rs on rp.rma_spec_id=rs.rma_spec_id
                where rs.rma_id = $rma_id and not rp.sent and not IFNULL(rp.hidden, 0) and not IFNULL(rs.hidden, 0)");
            if (PEAR::isError($pics)) aprint_r($pics);
        }
        $rec = new stdClass;
        $rec->data = $auction->data;
        $rec->name = 'InventoryStatus.pdf';
        $attachments[] = $rec;
        $supplier_email = $dbr->getOne("select email from op_company where id=$ins_id");
        $email_array = $dbr->getAssoc('select username, email from users where deleted=0 and inventory');
        $message = $sellerInfo4template->getTemplate($template, '');
        $super_emails = implode(', ', $email_array);
        list ($subject, $message) = explode("\n", $message, 2);
        $auction->rma_id = $rma_id;
        $subject = substitute($subject, $auction);
        $message = substitute($message, $auction);
        $email = $supplier_email . ', ' . $super_emails;
        echo ": to $email<br>";
    } elseif ($template == 'supplier_broken_item_email') {
        $auction->rma_id = $rma_spec_id;
        $problem_condition = $auction->problem_condition;

        $t1 = $auction->t1;
        $t2 = $auction->t2;
        $company_id = $ins_id;
        $auction->rma_url = $siteURL . 'rma.php?rma_id=' . $auction->rma_id . '&number=' . $auction->auction_number . '&txnid=' . $auction->txnid;

        if ($auction->send_anyway) { // for ONE ticket row only! so $rma_spec_id is really rma_spec_id
            $pics = $dbr->getAll("select op_company.email, rma_pic.*, rma_spec.article_id
                    from rma_pic
                    join rma_spec on rma_pic.rma_spec_id=rma_spec.rma_spec_id
                    join article on rma_spec.article_id = article.article_id and not article.admin_id
                    join op_company on article.company_id = op_company.id
                    where rma_spec.rma_spec_id = $rma_spec_id
                    and not rma_pic.hidden and not IFNULL(rma_spec.hidden, 0)
                    and IFNULL( rma_spec.sell_channel, 0 )=0
                    #and (broken_if_manage=0 or (broken_if_manage=1 and e.username=u.username))
                ");
            $email = $pics[0]->email; // . ',' . $auction->email_invoice; //https://trello.com/c/bLWjLTL7/2788-marta-g-send-picture-to-supplier WE DONT NEED TO SEND IT TO CUSTOMER
            $manager_info = $dbr->getRow("SELECT em.name,em.name2,em.email 
								FROM employee em
                                JOIN rma_spec ON rma_spec.rma_id=$rma_spec_id
                                JOIN article ON article.article_id=rma_spec.article_id
                                JOIN op_company_emp ON op_company_emp.company_id=article.company_id
                                WHERE op_company_emp.emp_id=em.id
                                AND op_company_emp.type='purch'");
            $manager = $dbr->getOne("SELECT group_concat(concat(em.name,' ',em.name2,' ',em.email) separator '
')
								FROM employee em
                                JOIN rma_spec ON rma_spec.rma_spec_id=$rma_spec_id
                                JOIN article ON article.article_id=rma_spec.article_id
                                JOIN op_company_emp ON op_company_emp.company_id=article.company_id
                                WHERE op_company_emp.emp_id=em.id
                                AND op_company_emp.type='purch'");
        } else { // for the complete ticket, sent by cron. In $rma_spec_id we have really rma_id
            $auction->send2username = "'".trim($auction->send2username,"'")."'";
            $pics = $dbr->getAll("select rma_pic.*, rma_spec.article_id
                    from rma_pic
                      join rma_spec on rma_pic.rma_spec_id=rma_spec.rma_spec_id
/*					join article on rma_spec.article_id = article.article_id and not article.admin_id
                    join op_company on article.company_id = op_company.id
                    left join op_company_emp on op_company_emp.company_id = op_company.id
                    left join employee e on op_company_emp.emp_id=e.id
                    left join users u on u.username=e.username*/
                where rma_spec.rma_id = $rma_spec_id
                and not rma_pic.hidden and not IFNULL(rma_spec.hidden, 0)
                #and ((op_company.id=$company_id " . ($auction->send_anyway ? "" : "and not rma_pic.sent_supplier") . ")
                #	or ($company_id=0 and not rma_pic.sent))
                and rma_pic.date between '" . date('Y-m-d', $t1) . "' and '" . date('Y-m-d', $t2) . "'
                $problem_condition
                and IFNULL( rma_spec.sell_channel, 0 )=0
                #and (broken_if_manage=0 or (broken_if_manage=1 and e.username in ({$auction->send2username})))
                ");
            $email = $auction->email_invoice;
            $manager_info = $dbr->getRow("SELECT em.name,em.name2,em.email FROM employee em
                                JOIN rma_spec ON rma_spec.rma_id=$rma_spec_id
                                JOIN article ON article.article_id=rma_spec.article_id
                                JOIN op_company_emp ON op_company_emp.company_id=article.company_id
                                WHERE op_company_emp.emp_id=em.id
                                AND op_company_emp.type='purch'");
            $manager = $dbr->getOne("SELECT group_concat(concat(em.name,' ',em.name2,' ',em.email) separator '
')
								FROM employee em
                                JOIN rma_spec ON rma_spec.rma_id=$rma_spec_id
                                JOIN article ON article.article_id=rma_spec.article_id
                                JOIN op_company_emp ON op_company_emp.company_id=article.company_id
                                WHERE op_company_emp.emp_id=em.id
                                AND op_company_emp.type='purch'");
        }
        $auction->name_manager = $manager_info->name.' '.$manager_info->name2;
        $auction->email_manager = $manager_info->email;
        $auction->managers = $manager;
        $auction->company_id = $company_id;
        if (PEAR::isError($pics)) print_r($pics);
        if (!$company_id) {
            //$db->query("update rma_pic set sent=1 where rma_id = $rma_spec_id");
            #now update in cron
            global $pic_ids;
            $pic_ids[] = $dbr->getOne("select group_concat(pic_id) from rma_pic where sent=0 and rma_id = $rma_spec_id");
        }
        else {
            //$db->query("update rma_pic set sent_supplier=1 where rma_id = $rma_spec_id");
        }

        if (!count($pics)) { echo 'nothing to send 1'; return;}
        if (count($pics)) {
            foreach ($pics as $pic) {
                if ($pic->article_id && strlen($pic->description)) {
                    $auction->images .= "<img src='cid:$pic->pic_id'><br>";
                    $rec = new stdClass;
                    $rec->data = file_get_contents("http://{$_SERVER['HTTP_HOST']}/doc.php?doc_id={$pic->pic_id}&external=1");
                    $rec->name = $auction->rma_id . '-' . $pic->article_id . '-' . $pic->description;
                    $attachments[] = $rec;
                }
            }
        }
        if (!count($attachments)) { echo 'nothing to send 2'; return;}

        $message = $sellerInfo4template->getTemplate($template, '');
        list ($subject, $message) = explode("\n", $message, 2);
        $subject = substitute($subject, $auction);
        $message = substitute($message, $auction);
        echo ": to " . $auction->email_invoice . "<br>";
    } elseif ($template == 'supplier_broken_item_email_label') {
        $shipping_method_id = $ins_id;
        $problem_condition = $auction->problem_condition;
        $t1 = $auction->t1;
        $t2 = $auction->t2;
        $auction->rma_id = $rma_spec_id;
        $auction->rma_url = $siteURL . 'rma.php?rma_id=' . $auction->rma_id . '&number=' . $auction->auction_number . '&txnid=' . $auction->txnid;
        $pics = $dbr->getAll("select distinct rma_pic.*, rma_spec.article_id
                    from rma_pic
                      join rma_spec on rma_pic.rma_spec_id=rma_spec.rma_spec_id
                    join rma on rma_spec.rma_id = rma.rma_id
                    join article on rma_spec.article_id = article.article_id and not article.admin_id
                    join op_company on article.company_id = op_company.id
                    join auction on auction.main_auction_number=rma.auction_number and auction.main_txnid=rma.txnid
                    join orders on rma_spec.article_id = orders.article_id
                        and (orders.auction_number=auction.auction_number)
                        and (orders.txnid=auction.txnid)
                    join tn_orders on tn_orders.order_id = orders.id
                    join tracking_numbers on tn_orders.tn_id = tracking_numbers.id
                where rma_spec.rma_id = $rma_spec_id
                and not rma_pic.sent_label
                and not rma_pic.hidden and not IFNULL(rma_spec.hidden, 0)
                and IFNULL( rma_spec.sell_channel, 0 )=0
                and rma_pic.date between '" . date('Y-m-d', $t1) . "' and '" . date('Y-m-d', $t2) . "'
                $problem_condition
                and tracking_numbers.shipping_method=" . $shipping_method_id . "
                    union all
                select distinct rma_pic.*, rma_spec.article_id
                    from rma_pic
                      join rma_spec on rma_pic.rma_spec_id=rma_spec.rma_spec_id
                    join rma on rma_spec.rma_id = rma.rma_id
                    join article on rma_spec.article_id = article.article_id and not article.admin_id
                    join op_company on article.company_id = op_company.id
                    join orders on rma_spec.article_id = orders.article_id
                        and (orders.auction_number=rma.auction_number)
                        and (orders.txnid=rma.txnid)
                    join tn_orders on tn_orders.order_id = orders.id
                    join tracking_numbers on tn_orders.tn_id = tracking_numbers.id
                where rma_spec.rma_id = $rma_spec_id
                and not rma_pic.sent_label
                and not rma_pic.hidden and not IFNULL(rma_spec.hidden, 0)
                and IFNULL( rma_spec.sell_channel, 0 )=0
                and rma_pic.date between '" . date('Y-m-d', $t1) . "' and '" . date('Y-m-d', $t2) . "'
                $problem_condition
                and tracking_numbers.shipping_method=" . $shipping_method_id . "
                ");
        if (PEAR::isError($pics)) aprint_r($pics);
        $db->query("update rma_pic set sent_label=1
                        where rma_id = $rma_spec_id");
        if (!count($pics)) return;
        if (count($pics)) {
            foreach ($pics as $pic) {
                $auction->images .= "<img src='cid:$pic->pic_id'><br>";
                $rec = new stdClass;

                //$rec->data = base64_decode($pic->pic);

                                $rec->data = file_get_contents("http://{$_SERVER['HTTP_HOST']}/doc.php?doc_id={$pic->pic_id}&external=1");
                $rec->name = $auction->rma_id . '-' . $pic->article_id . '-' . $pic->name;
                $attachments[] = $rec;
            }
        }
        $message = $sellerInfo4template->getTemplate($template, '');
//      		$message = $sellerInfo4template->getTemplate($template, $locallang);
        list ($subject, $message) = explode("\n", $message, 2);
        $subject = substitute($subject, $auction);
        $message = substitute($message, $auction);
        $email = $auction->email_invoice;
        echo ": to $email<br>";
    } elseif ($template == 'send_invoice' || $template == 'send_invoice_to_seller_email' || $template == 'send_invoice_shippingcompany') {
        if ($template == 'send_invoice' || $template == 'send_invoice_to_seller_email') {
            $email = $auction->email_invoice;
        } else {
            $email = $auction->email_shipping;
        }
        if (!strlen($email))
            $email = $auction->email;
        if (!strlen($email)) $email = $auction->email;
        if ($email == 'removed') return;
        $message = $sellerInfo4template->getTemplate($template, $locallang/*SiteToCountryCode($siteid)*/);
        list ($subject, $message) = explode("\n", $message, 2);
        $subject = substitute($subject, $auction);
        $message = substitute($message, $auction);
        $invoice = new Invoice($db, $dbr, $auction->invoice_number);
        $rec = new stdClass;
        $rec->data = $invoice->getInvoicePDF($db, $dbr, $auction->auction_number, $auction->txnid);
        $rec->name = $english[59] . ' ' . $auction->invoice_number . '.pdf';
        $attachments[] = $rec;
        $rec = new stdClass;
    } elseif ($template == 'send_packing_list' || $template == 'send_packing_list_shippingcompany') {
        $email = $auction->email_shipping;
        if ($template == 'send_packing_list_shippingcompany' && !strlen($email)) return false;
        if (!strlen($email)) $email = $auction->email;
        $message = $sellerInfo4template->getTemplate($template, $locallang/*SiteToCountryCode($siteid)*/);
        list ($subject, $message) = explode("\n", $message, 2);
        $subject = substitute($subject, $auction);
        $message = substitute($message, $auction);
        if($auction->payment_method != 'klr_shp'){
            $invoice = new Invoice($db, $dbr, $auction->invoice_number);
            $rec = new stdClass;
            $rec->data = $invoice->getShippingListPDF($db, $dbr, $auction->auction_number, $auction->txnid);
            $rec->name = $english[150] . ' ' . $auction->invoice_number . '.pdf';
            $attachments[] = $rec;
            $rec = new stdClass;
        }
    } elseif ($template == 'shop_good_rating_thanks') {
        $q = "select t.value good_rate_thanks_html
                            , t1.value good_rate_thanks_subject
            from translation t
            left join translation t1 on {$auction->shop_id}=t1.id and t1.table_name='shop' and t1.field_name='good_rate_thanks_subject'
                            and t1.language='{$auction->lang}'
            where {$auction->shop_id}=t.id and t.table_name='shop' and t.field_name='good_rate_thanks_html'
                            and t.language='{$auction->lang}'
                ";
        $rec = $dbr->getRow($q);
        if (!strlen($auction->good_rate_thanks_html)) $auction->good_rate_thanks_html = $rec->good_rate_thanks_html;
        if (!strlen($auction->good_rate_thanks_subject)) $auction->good_rate_thanks_subject = $rec->good_rate_thanks_subject;
        $message = substitute($auction->good_rate_thanks_html, $auction);
        $message = substitute($message, $sellerInfo->data);
        $subject = substitute($auction->good_rate_thanks_subject, $auction);
        $subject = substitute($subject, $sellerInfo->data);
        $attachments = 'html';
    } elseif ($template == 'shop_rating_remember') {
        $message = substitute($auction->rating_remember_html, $auction);
        $message = substitute($message, $sellerInfo->data);
        $subject = substitute($auction->rating_remember_subject, $auction);
        $subject = substitute($subject, $sellerInfo->data);
        $attachments = 'html';
    } elseif ($template == 'send_SERVICEQUITTUNG') {
//			echo 'send_SERVICEQUITTUNG for '.$ins_id.'<br>';
        return; # KDqgfQpH/1076-marta-changes-to-shipping-details-to-customer-and-servicequittung-mails
        $tnid = $ins_id;
        $r = $dbr->getRow("select * from tracking_numbers where id=$tnid");
        $method = new ShippingMethod($db, $dbr, $r->shipping_method);
        if ($sellerInfo->get('dontsendSERVICEQUITTUNG')/* || $method->get('dontsendSERVICEQUITTUNG')*/) {
#				echo 'seller='.$sellerInfo->get('dontsendSERVICEQUITTUNG').' method='.$method->get('dontsendSERVICEQUITTUNG');
#				echo '<br>cant send Servicequitting';  die();
            return;
        }
        $email = $auction->email_shipping;
        if (!strlen($email)) $email = $auction->email;
        if ($email == 'removed') return;
        if ($tnid && $method->get('sendcustomSERVICEQUITTING')) {
            $message = $sellerInfo4template->getTemplate('send_SERVICEQUITTUNG_custom', $locallang);
        } else {
            $message = $sellerInfo4template->getTemplate($template, $locallang);
        }
        list ($subject, $message) = explode("\n", $message, 2);
        $subject = substitute($subject, $auction);
        $subject = substitute($subject, $method->data);
        $message = substitute($message, $auction);
        $message = substitute($message, $method->data);
        $rec = new stdClass;
        $auobj = new Auction($db, $dbr, $auction->auction_number, $auction->txnid);
        $rec->data = $auobj->getSERVICEQUITTUNG_PDF($tnid);
        $rec->name = 'SERVICEQUITTUNG.pdf';
        $attachments[] = $rec;
    } elseif ($template == 'warehouse_volume_report') {
        $email = $auction->email;
        $attachments[] = $auction->attachments;
    } elseif ($template == 'warehouse_report') {
        $email = $auction->email;
        $attachments[] = $auction->attachments;
    } elseif ($template == 'automatic_generation_barcodes') {
        $email = $auction->email;
        $attachments[] = $auction->attachments;
    } elseif ($template == 'send_documents') {
//			require_once 'mime_mail.class.php';
        $email = $auction->email_shipping;
        if (!strlen($email)) {
            $email = $auction->email;
            //           echo "No shipping email <br>";
//	       return;
        }
        if ($email == 'removed') return;
        $message = $sellerInfo4template->getTemplate($template, $locallang/*SiteToCountryCode($siteid)*/);
        list ($subject, $message) = explode("\n", $message, 2);
        $subject = substitute($subject, $auction);
        $message = substitute($message, $auction);
        $items_list = Order::listAll($db, $dbr, $auction->auction_number, $auction->txnid, 1);
        $names = array();
        $attach = 0;
        foreach ($items_list as $item) {
            if ($item->manual) continue;
            $senddocs = Article::getDocsTranslated($db, $dbr, $item->article_id, 1);
            $subarticles = Article::getSubArticles($item->article_id);
            foreach ($subarticles as $article) {
                $senddocs = array_merge($senddocs, Article::getDocsTranslated($db, $dbr, $article, 1));
            }
            if (count($senddocs)) foreach ($senddocs as $fichier_attache) {
                if (strlen($fichier_attache->$locallang->name)
                    && !in_array($fichier_attache->$locallang->name, $names)
                ) {
                    $rec = new stdClass;
                    $rec->data = base64_decode($fichier_attache->$locallang->data);
                    $rec->name = $fichier_attache->$locallang->name;
                    $attachments[] = $rec;
                    $names[] = $fichier_attache->$locallang->name;
                    $attach = 1;
                };
            }
        }
        if (!$attach) return;
    } elseif (
        $template == 'customer_invoice'
        || $template == 'insurance'
        || $template == 'pictures'
        || $template == 'insurance_invoice'
        || $template == 'newcomment'
        || $template == 'send_announce_insurance'
        || $template == 'send_insurance'
        || $template == 'insurance_letter'
    ) {
        if (!$ins_id)
            $ins_id = $db->getOne('select max(id) from insurance where auction_number=' . $auction->auction_number
                . ' and txnid=' . $auction->txnid);
        if (!$ins_id) return "You can't send this email because there is no insuarance cases in this auction";
        $ins = new Insurance($db, $dbr, $ins_id);
        $method = new ShippingMethod ($db, $dbr, $ins->get('shipping_method'));
        $email = $method->get('email');
        if (!strlen($email)) return false;
        $auction->insurance_url = $siteURL . 'insurance.php?id=' . $ins_id;
        $auction->insuranceurl = $siteURL . 'insurance.php?id=' . $ins_id;
        $auction->insid = $ins_id;
        $auction->ins_date = $ins->get('date');
        $auction->ins_shipping_company_name = $method->get('company_name');
        $auction->ins_responsible_username = $dbr->getOne("select name from users where username='" .
            $ins->get('responsible_username') . "'");
        $auction->lastcomment = $dbr->getOne("select comment
                          from ins_comment where ins_id=$ins_id
                      order by id desc
                      LIMIT 0, 1");
        $auction->comments_history = formatCommentHistory($db, $dbr, $ins_id);
        $message = $sellerInfo4template->getTemplate($template, $locallang/*SiteToCountryCode($siteid)*/);
        list ($subject, $message) = explode("\n", $message, 2);
        $subject = substitute($subject, $auction);
        $message = substitute($message, $auction);
        switch ($template) {
            case 'insurance':
                $rec = new stdClass;
                $rec->data = $ins->getSelfPDF();
                $rec->name = 'Insurance case.pdf';
                $attachments[] = $rec;
                break;
            case 'customer_invoice':
                $rec = new stdClass;
                $rec->data = $ins->getInvoiceCustomerPDF();
                $rec->name = 'Invoice customer.pdf';
                $attachments[] = $rec;
                break;
            case 'pictures':
                $rec = new stdClass;
                $rec->data = $ins->getRMAPicturesPDF();
                $rec->name = 'pictures of broken items.pdf';
                if ($rec->data) $attachments[] = $rec;
                break;
            case 'insurance_invoice':
                $rec = new stdClass;
                $rec->data = $ins->getInvoicePDF();
                $rec->name = 'Invoice.pdf';
                $attachments[] = $rec;
                break;
            case 'newcomment':
                break;
            case 'send_announce_insurance':
                break;
            case 'send_insurance':
                $rec = new stdClass;
                $rec->data = $ins->getSelfPDF();
                $rec->name = 'Insurance case.pdf';
                $attachments[] = $rec;
                $rec = new stdClass;
                $rec->data = $ins->getInvoicePDF();
                $rec->name = 'Invoice.pdf';
                $attachments[] = $rec;
                $rec = new stdClass;
                $rec->data = $ins->getLetterPDF();
                $rec->name = 'Insurance letter1.pdf';
                $attachments[] = $rec;
                $rec = new stdClass;
                $rec->data = $ins->getRMAPicturesPDF();
                $rec->name = 'pictures of broken items.pdf';
                $attachments[] = $rec;
                $rec = new stdClass;
                $rec->data = $ins->getInvoiceCustomerPDF();
                $rec->name = 'Invoice customer.pdf';
                $attachments[] = $rec;
                foreach ($ins->docs as $doc) {
                                        $doc->data = get_file_path($doc->data);
                    $attachments[] = $doc;
                }
                break;
            case 'insurance_letter':
                $rec = new stdClass;
                $rec->data = $ins->getLetterPDF();
                $rec->name = 'Insurance letter1.pdf';
                $attachments[] = $rec;
                break;
        }
    } elseif (
        $template == 'not_assigned_ins_comment'
        || $template == 'new_responsible_insurance'
    ) {
        if (!$ins_id)
            $ins_id = $db->getOne('select max(id) from insurance where auction_number=' . $auction->auction_number
                . ' and txnid=' . $auction->txnid);
        if (!$ins_id) return "You can't send this email because there is no insuarance cases in this auction";
        $ins = new Insurance($db, $dbr, $ins_id);
        $auction->insurance_url = $siteURL . 'insurance.php?id=' . $ins_id;
        $auction->insuranceurl = $siteURL . 'insurance.php?id=' . $ins_id;
        $auction->insid = $ins_id;
        $auction->ins_date = $ins->get('date');
        $auction->ins_responsible_username = $dbr->getOne("select name from users where username='" .
            $ins->get('responsible_username') . "'");
        $auction->lastcomment = $dbr->getOne("select comment
                          from ins_comment where ins_id=$ins_id
                      order by id desc
                      LIMIT 0, 1");
        $auction->comments_history = formatCommentHistory($db, $dbr, $ins_id);
        $message = $sellerInfo4template->getTemplate($template, $locallang/*SiteToCountryCode($siteid)*/);
        list ($subject, $message) = explode("\n", $message, 2);
        $subject = substitute($subject, $auction);
        $message = substitute($message, $auction);
    } elseif (($template == 'shipping_order_datetime_approvement')
        || ($template == 'shipping_order_datetime_pickup_approvement')
        || ($template == 'customer_confirm_delivery')
        || ($template == 'shipping_order_datetime_late')
        || ($template == 'customer_reject_delivery')
        || ($template == 'removing_from_route')
    ) {
		// check for pickup and change template
		if ($template == 'customer_confirm_delivery'
			&& (in_array($auction->payment_method, array('bean_pck', 'ppcc_pck', 'gc_pck', 'pp_pck', 'cc_pck', '3', '4')) || $auction->txnid==4)
			) {
			$template = 'customer_confirm_pickup';
		}
        $attachments = 'html';
        $email = $auction->email_shipping;
        $sendmethod = $ins_id;
        if($template == 'customer_confirm_delivery'){
            $loggedUser = new User($db, $dbr, $auction->shipping_username, 1);
            if ($loggedUser->data->shipping_method > 0) {
                $meth = new ShippingMethod ($db, $dbr, $loggedUser->data->shipping_method);
                if (
                    (strlen($auction->shipping_order_date) && $auction->shipping_order_date != '0000-00-00')
                    && ($auction->shipping_order_time == '00:00:00'
                        && ($meth->data->delivery_time_for_approvement_mail_from != '00:00:00'
                            && $meth->data->delivery_time_for_approvement_mail_to != '00:00:00'))
                ) {
                    $shipping_order_time = $meth->data->delivery_time_for_approvement_mail_from . "-" . $meth->data->delivery_time_for_approvement_mail_to;
                    $auction->shipping_order_time = $shipping_order_time;
                }
            }
        }
        if($template == 'shipping_order_datetime_approvement'){
            $loggedUser = new User($db, $dbr, $auction->shipping_username, 1);
            if(strlen($auction->shipping_order_date) && $auction->shipping_order_date != '0000-00-00'
                && $auction->shipping_order_time == '00:00:00'){
                if(empty($loggedUser->data->shipping_method)){
                    $msg = "Auction shipping order time is {$auction->shipping_order_time} but no shipping method assigned";
                    die($msg);
                }
                $meth = new ShippingMethod ($db, $dbr, $loggedUser->data->shipping_method);
                if($meth->data->delivery_time_for_approvement_mail_from == '00:00:00'
                    || $meth->data->delivery_time_for_approvement_mail_to == '00:00:00'){
                    $msg = "Please set delivery time for the <a target='_blank' href='/method.php?id={$meth->data->shipping_method_id}'>shipping method</a>";
                    die($msg);
                }
            }
        }
        list($auction->shipping_order_time_min,$auction->shipping_order_time_max) = get_auction_timeframes($auction);
    } elseif ($template == 'conditions_agree') {
        $email = $auction->email;
//	        $subject = substitute($english[101], $auction);
        $message = $sellerInfo4template->getTemplate($template, $locallang/*SiteToCountryCode($siteid)*/);
        list ($subject, $message) = explode("\n", $message, 2);
        $offer = new Offer($db, $dbr, $auction->offer_id, $locallang);
        $auction->text_from_offer = substitute($offer->translated_message_to_buyer3, $sellerInfo->data);
//			if (!strlen($message)) $message = substitute($offer->data->message_to_buyer3,$sellerInfo->data);
    } elseif ($template == 'rules_to_confirm') {
        return; // combined with order_confirm
        $email = $auction->email;
//	        $subject = substitute($english[119], $auction);
        $message = $sellerInfo4template->getTemplate($template, $locallang/*SiteToCountryCode($siteid)*/);
//			echo($message);
        list ($subject, $message) = explode("\n", $message, 2);
        if ($auction->offer_id) {
            $offer = new Offer($db, $dbr, $auction->offer_id, $locallang);
        } else {
            $offer_id = $dbr->getOne("select offer_id from auction where main_auction_number=" . $auction->auction_number . "
                    and main_txnid=" . $auction->txnid);
            $offer = new Offer($db, $dbr, $offer_id, $locallang);
        }
        if ($sellerInfo4template->get('seller_channel_id') == 3) {
            $type = 'a';
        } else {
            switch ($auction->txnid) {
                case 1:
                    $type = '';
                    break;
                case 3:
                    $type = 's';
                    break;
                case 0:
                    $type = '';
                    break;
                default:
                    $type = 'f';
                    break;
            }
        }
        $shipping_plan_id = $dbr->getOne("SELECT value
                FROM translation
                WHERE table_name = 'offer'
                AND field_name = '" . $type . "shipping_plan_id'
                AND language = '" . $auction->siteid . "'
                AND id = " . $offer->get('offer_id'));
        $shipping_plan = new ShippingPlan($db, $dbr, $shipping_plan_id, $locallang);
        $message = $shipping_plan->data->rules_to_confirm;
//			echo "<br>$message<br>";
    } elseif ($template == 'customer_shop_news') {
        $inside = 1;
        if (!isset($auction->email_invoice)) $auction->email_invoice = $auction->customer->email_invoice;
        if (!isset($auction->firstname_invoice)) $auction->firstname_invoice = $auction->customer->firstname_invoice;
        if (!isset($auction->name_invoice)) $auction->name_invoice = $auction->customer->name_invoice;
        $email = $auction->email;
        $subject = $auction->spam->subject;
        $from = $auction->spam->from_email;
        $from_name = $auction->spam->from_name;
        $xheaders = $auction->xheaders;
        foreach ($xheaders as $k => $r) {
            $xheaders[$k]->header = substitute($xheaders[$k]->header, $auction);
        }
        if (count($auction->spam->docs)) {
            $rec = new stdClass;
            $rec->data = substitute($auction->spam->body, $auction) . "<img src='" . $siteURL . "image_shop.php?id=$nextID'>";
            $rec->name = 'newsmail.html';
            $attachments[] = $rec;
            foreach ($auction->spam->docs as $doc) {
                $rec = new stdClass;

                $rec->data = $doc->data;
                $rec->name = $doc->name;
                $attachments[] = $rec;
            }
        } else {
            if($sendmethod=='sms'){
                $message = substitute($auction->spam->sms_body, $auction);
                $sms = substitute($auction->spam->sms_body, $auction);
            }else{
                $message = substitute($auction->spam->body, $auction) . "<img src='" . $siteURL . "image_shop.php?id=$nextID'>";
                $attachments = 'html';
            }
        }
        if (is_array($auction->auction_numbers)) $auction->auction_number = $auction->auction_numbers;
        $notes = serialize(array('shop_spam_id' => $auction->shop_spam_id));
    } elseif ($template == 'alarm') {
        $attachments = 'html';
    } elseif ($template == 'invoice_email') {
        $email = $auction->email;
        $message = $sellerInfo4template->getTemplate($template, '');
        list ($subject, $message_dummy) = explode("\n", $message, 2);
        $message = $auction->content;
        $attachments = 'html';
    } elseif ($template == 'shop_news_recommended') {
        $message = $sellerInfo4template->getTemplate($template, $locallang/*SiteToCountryCode($siteid)*/);
        list ($subject, $message) = explode("\n", $message, 2);
        $rec = new stdClass;
        $rec->data = $auction->content;
        $rec->name = 'newsletter.html';
        $attachments[] = $rec;
        $from = $auction->from;
        $from_name = $auction->from_name;
    } elseif ($template == 'send2friend') {
        $from = $auction->loggedCustomer->email_invoice;
        $from_name = $english[$auction->loggedCustomer->gender_invoice] . ' ' . $auction->loggedCustomer->firstname_invoice . ' ' . $auction->loggedCustomer->name_invoice;
    } elseif ($template == 'shop_question') {
        $from = $auction->from;
        $from_name = $auction->from_name;
    } elseif ($template == 'personal_voucher') {
        $message = $sellerInfo4template->getTemplate($template, $locallang);
        list ($subject, $message) = explode("\n", $message, 2);
        $rec = new stdClass;
        $rec->data = $auction->html;
        $rec->name = 'voucher.html';
        $attachments[] = $rec;
        $from = $sellerInfo->get('email');
        $from_name = $sellerInfo->get('email_name');
        $auction->auction_number = $auction->customer_id;
        $auction->txnid = -5;
        $notes = serialize(array('voucher_code' => $auction->voucher_code, 'voucher_id' => $auction->voucher_id));
//			$template .= $auction->voucher_id;
    } elseif ($template == 'send_container_booking') {
        $message = $auction->message;
        $subject = $auction->subject;
#			echo $message; die();
    } elseif ($template == 'newsletter_partner_email') {
        $message = $auction->message;
        $subject = $auction->subject;
#			echo $message; die();
    } elseif ($template == 'no_barcode_alert') {
        $warehouse = new Warehouse($db, $dbr, $auction->packed_warehouse);
        $auction->warehouse_name = $warehouse->get('name');

        $email = $dbr->getOne("SELECT GROUP_CONCAT(`u`.`email`) FROM `users` AS `u`
            JOIN `user_ware_no_barcode_alert` AS `nba` ON `u`.`username` = `nba`.`username`
            WHERE `nba`.`warehouse_id` = {$auction->packed_warehouse}");

        $subject = "No barcodes in {$auction->warehouse_name}";
    } elseif ($template == 'a_article_ticket_alert') {
        $email = $dbr->getOne("select group_concat(email) from users where deleted=0 and sendNotinvoice_alert=1");
    } elseif ($template == 'decompleted_barcode') {
        $email = $auction->email = $dbr->getOne("SELECT GROUP_CONCAT(email) FROM users WHERE deleted=0 AND barcode_alert=1;");
        $subject = 'You have decompleted articles, please assign barcodes';
        $message = '<h2>' . $subject . ":</h2>\n";
        $message .= $auction->auction_links;
    } elseif ($template === 'oneoff_token') {
        $message = $auction->message;
        $subject = 'Token';
        $email = $auction->email;
        if (empty($email)) {
            $sendmethod = 'sms';
        }
        $sms = $auction->message;
        $notes = $auction->smsNumber;
    }

    if ($auction->op_order_id) {
        $auction->auction_number = $auction->op_order_id;
        $auction->txnid = -1;
    }
    if (!strlen($email)) $email = $auction->email_invoice;
    if (!strlen($from)) {
        $from = $template_name->email_sender;
        switch ($from) {
            case 'seller':
                $from = $sellerInfo->get('email');
                break;
            case 'user':
                if (is_a($loggedUser, 'User')) $from = $loggedUser->get('email');
                break;
            case 'system':
                $from = Config::get($db, $dbr, 'system_email');
                break;
        }
    }
    if (!strlen($from_name)) {
        switch ($template_name->email_sender) {
            case 'seller':
                $from_name = $sellerInfo->get('email_name');
                break;
            case 'user':
                if (is_a($loggedUser, 'User')) $from_name = $loggedUser->get('name');
                break;
            /*			case 'customer':
                            $from_name = $loggedUser->get('name');
                            $from = $loggedUser->get('name');
                            break;*/
            case 'system':
                $from_name = Config::get($db, $dbr, 'system_email_name');
                break;
        }
    }
    $bcc = $sellerInfo->get('bcc');
    if (!$message) {
        if ($issystem) {
            $message = $sellerInfo4template->getTemplate($template, '');
            $sms = $sellerInfo4template->getSMS($template, '');
            $bcc = '';
        } else {
            $message = $sellerInfo4template->getTemplate($template, $locallang/*SiteToCountryCode($siteid)*/);//	$sellerInfo->get($template);
            $sms = $sellerInfo4template->getSMS($template, $locallang);
            $bcc = $sellerInfo->get('bcc');
        }
        list ($subject1, $message) = explode("\n", $message, 2);
//	   echo $subject.'-'.$subject1; die();
        if (!strlen($subject)) $subject = $subject1;
    }

    if ($auction->payment_method == 'bill_shp') {
        require_once 'XML/Unserializer.php';
        $q = "select `data` from billpay_notify where token = (select token from payment_saferpay where auction_number = {$auction->auction_number})";
        $data = $db->getOne($q);

        if (strlen($data)) {
            $opts = array('parseAttributes' => true);
            $us = new XML_Unserializer($opts);
            $us->unserialize($data);
            $POB = $us->getUnserializedData();
            unset($us);
            $sellerInfo->data->currency = siteToSymbol($auction->siteid);
            $sellerInfo->data->billpay_header_top = substitute($sellerInfo->data->billpay_header_top, $POB);
            $sellerInfo->data->billpay_header_right = substitute($sellerInfo->data->billpay_header_right, $POB);
            $sellerInfo->data->billpay_header_bottom = substitute($sellerInfo->data->billpay_header_bottom, $POB);
            $sellerInfo->data->billpay_header_bottom = substitute($sellerInfo->data->billpay_header_bottom, $sellerInfo->data);
            $sellerInfo->data->billpay_header_bottom = substitute($sellerInfo->data->billpay_header_bottom, $auction);
            $sellerInfo->data->country = translate($db, $dbr, CountryCodeToCountry($sellerInfo->get('country')), $locallang, 'country', 'name');
            $message = substitute($message, $POB);
//				die($message);
        }
    } // for billpay only
    else {
        $sellerInfo->data->billpay_header_top = '';
        $sellerInfo->data->billpay_header_right = '';
        $sellerInfo->data->billpay_header_bottom = '';
        $sellerInfo->data->currency = siteToSymbol($auction->siteid);
        $sellerInfo->data->country = translate($db, $dbr, CountryCodeToCountry($sellerInfo->get('country')), $locallang, 'country', 'name');
        $message = substitute($message, $sellerInfo->data);
    }
    $subject = substitute($subject, $auction);
    $message = substitute($message, $auction);
    $message = substitute($message, $auction); // 2nd time, yes, yes
    $message = substitute($message, $sellerInfo->data);
    $message = substitute($message, $sellerInfo->data); // 2nd time, yes, yes
    $message = substitute($message, $auction); // 3rd time, yes, yes
    if ($auction->payment_method == 'bill_shp') {
        /*require_once 'XML/Unserializer.php';
        $q = "select `data` from billpay_notify where token = (select token from payment_saferpay where auction_number = {$auction->auction_number})";
        $data = $db->getOne($q);*/
        if (strlen($data)) {
            /*$opts = array('parseAttributes' => true);
            $us = new XML_Unserializer($opts);
            $us->unserialize($data);
            $POB = $us->getUnserializedData();
            unset($us);
            $sellerInfo->data->billpay_header_top = substitute($sellerInfo->data->billpay_header_top, $POB);
            $sellerInfo->data->billpay_header_right = substitute($sellerInfo->data->billpay_header_right, $POB);
            $sellerInfo->data->billpay_header_bottom = substitute($sellerInfo->data->billpay_header_bottom, $POB);
            $sellerInfo->data->billpay_header_bottom = substitute($sellerInfo->data->billpay_header_bottom, $sellerInfo->data);
            $sellerInfo->data->billpay_header_bottom = substitute($sellerInfo->data->billpay_header_bottom, $auction);*/
            $message = substitute($message, $auction);
            $message = substitute($message, $sellerInfo->data);
            $message = substitute($message, $POB);
        }
    } // for billpay only
    else {
        $sellerInfo->data->billpay_header_top = '';
        $sellerInfo->data->billpay_header_right = '';
        $sellerInfo->data->billpay_header_bottom = '';
        $message = substitute($message, $sellerInfo->data);
    }
    if ($auction->check) {
        $message = $message . "<img src='" . $siteURL . "image_shop.php?id=$nextID'>";
    }

    global $def_smtp;
    if ((int)$def_smtp)
    {
        $defsmtp = $def_smtp;
    }
    else if ((int)$_def_smtp)
    {
        $defsmtp = $_def_smtp;
    }
    else
    {
        $defsmtp = array(0 => $sellerInfo4template->getDefSMTP());
    }

    $altsmtps = $sellerInfo4template->getAltSMTPs();
    if ((int)$template_name->smarty) {
        $message = fetchfromstring($message);
    }

    if ($issystem) {
        $emails_array = explode(',', $email);
        foreach ($emails_array as $k => $user_email) {
            $user_email = mysql_real_escape_string($user_email);
            $user = $dbr->getRow("select u.deleted, super_u.email super_email
                    from users u
                    left join employee e on e.username=u.username
                    left join emp_department ed on ed.id=e.department_id
                    left join users super_u on super_u.username=ed.direct_super_username
                    where u.email='$user_email'");
            if ($user->deleted && strlen($user->super_email)) {
                $emails_array[$k] = $user->super_email;
            }
        } // foreach email
        $email = implode(',', $emails_array);
    }

    if ((isset($sendmethod) && $sendmethod == 'email') || !isset($sendmethod)) {
        global $debug;
        require_once 'mail_util.php';
        if ($template=='customer_shop_news') if (checkDoubleEmails($db, $template, $email, $subject, 60)) {
            echo "!!! Double for $template, $email, $subject<br>";
            return false;
        }

        $message_body = $message;
        if(!trim(strip_tags($message_body, '<img>'))) return;
        if ($ishtml && ! $issystem && $header_footer) {
            $layouts = ['header', 'footer'];
            $layouts = mulang_files_Get($layouts, 'email_template_layout', $sellerInfo->data->id); // update template layout logic

            $header = isset($layouts['header']) ? $layouts['header'] : [];
            $header = isset($header[$locallang]->value) ? $header[$locallang]->value : (isset($header['english']->value) ? $header['english']->value : '');

            $footer = isset($layouts['footer']) ? $layouts['footer'] : [];
            $footer = isset($footer[$locallang]->value) ? $footer[$locallang]->value : (isset($footer['english']->value) ? $footer['english']->value : '');

            $message = $header . $message . $footer;
        }
        $message = substitute($message, ['link_online_html' => '<a href="[[link_online]]">online</a>']);
        if (empty($nextID)) {
            $db->exec('INSERT INTO ' . $template_name->log_table . ' (template, date, auction_number, txnid) VALUES (\'\', NOW(), 0, 0)');
            $nextID = $db->lastInsertID($template_name->log_table);
        }
        $emailKey = md5(SALT_EMAIL_ONLINE . $nextID);
        $shopCatalogue = new Shop_Catalogue($db, $dbr, $auction->shop_id);
        $http = $shopCatalogue->_shop->ssl ? 'https' : 'http';
        $link = (strpos($sellerInfo->data->web_page, 'http') !== false) ? $sellerInfo->data->web_page :
            $http . "://" . $sellerInfo->data->web_page;
        $link .= '/email/?id=' . $nextID . '&key=' . $emailKey;
        if($short_url = googleShortUrl($sellerInfo->get('google_apiKey'), $link)){
            $link = $short_url;
        }
        $message = substitute($message, ['link_online' => $link]);
        // if no send - return message body
        if($nosend){
            return $message_body;
        }
        $res = sendSMTP($db, $dbr, $email, $subject, $message, $attachments, $from, $from_name
            , $auction->auction_number, $auction->txnid, $template, $ins_id, $defsmtp, $inside, $notes, $bcc, $xheaders, $nextID);
        if ($template_name->alt) {
            $res1 = sendSMTP($db, $dbr, $email, $subject, $message, $attachments, $from, $from_name
                , $auction->auction_number, $auction->txnid, $template, $ins_id, $altsmtps, $inside, $notes, $bcc, $xheaders, $nextID);
            $res = $res || $res1;
        }
        $copy_to_email = $template_name->copy_to;
        if (strlen($copy_to_email)) {
            $nextID = 0;
            $res2 = sendSMTP($db, $dbr, $copy_to_email, $subject, $message, $attachments, $from, $from_name
                , $auction->auction_number, $auction->txnid, $template, $ins_id, $defsmtp, $inside, $notes, $bcc, $xheaders, $nextID);
            $res = $res || $res2;
        }
    }
// UNLOCK TABLE email_log
    global $conv;
    global $debug;
    $msg = print_r($defsmtp, true) . $sellerInfo->get('username') . "-SiteCode:" . SiteToCountryCode($siteid) . ", $template, $email, <br>Subj=$subject, <br><br>Msg=$message, <br><br>($from_name)$from<br>" . count($attachments) . "<br>" . $conv;
    if ($debug) echo $msg;
        if ($template != 'customer_shop_news' && (!$res || $debug)) {
        mail("baserzas@gmail.com", "standardEmail!!!", $msg);
    }
    $sms = substitute($sms, $auction);
    $number = $auction->shipping_order_datetime_sms_to;

    if ((isset($sendmethod) && $sendmethod == 'sms') || /*!isset($sendmethod)*/$send_sms) {
        if (strlen(Config::get($db, $dbr, 'clickatell_user'))
            && strlen(Config::get($db, $dbr, 'clickatell_password'))
            && strlen(Config::get($db, $dbr, 'clickatell_api_id'))
            && strlen(trim($sms))
            && !empty($number) && strlen($auction->$number)
        ) {
            if (substr($auction->$number, 0, 1) == '+') {
                $cleared_number = str_replace('+', '00', $auction->$number);
            } else {
                $pefix_field = str_replace('_','_country_code_', $auction->shipping_order_datetime_sms_to);
                $country_prefix = CodeToPhonePrefix($auction->{$pefix_field});
                if (substr($auction->$number, 0, 2) != $country_prefix) {
                    $cleared_number = $country_prefix . $auction->$number;
                } else {
                    $cleared_number = $auction->$number;
                }
            }

            if (Config::get($db, $dbr, 'use_php_sms_gateway')) {
                if (Config::get($db, $dbr, 'clickatell_cut0') == 1) {
                    $sms2number = ltrim($cleared_number, '0');
                } elseif (Config::get($db, $dbr, 'clickatell_cut0') == 2) {
                    $sms2number = ltrim($cleared_number, '0');
                    while (strpos($sms2number, '00') !== 0) $sms2number = '0' . $sms2number;
                } else {
                    $sms2number = $cleared_number;
                }

                $sms_emails = $dbr->getOne("select count(*) from sms_email
                        where email='clickatell' and inactive=0 and '$sms2number' like CONCAT(sms_email.number,'%')");
                if ($sms_emails) {
                    $id = sendSMS_Clickatell($sms2number, substr($sms, 0, Config::get($db, $dbr, 'sms_message_limit'))
                        , $auction->auction_number, $auction->txnid, $template, $nextID);
                } else {
                    $id = "Is requested to send the SMS to this number. Add to the SMS settings";
                }
            }
            if (Config::get($db, $dbr, 'smsemail_cut0') == 1) {
                $sms2number = ltrim($cleared_number, '0');
            } elseif (Config::get($db, $dbr, 'smsemail_cut0') == 2) {
                $sms2number = ltrim($cleared_number, '0');
                while (strpos($sms2number, '00') !== 0) $sms2number = '0' . $sms2number;
            } else {
                $sms2number =  $cleared_number;
            }
            $sms_emails = $dbr->getOne("select group_concat(email) from sms_email where inactive=0 and '$sms2number' like CONCAT(sms_email.number,'%')");
            /**
             * @todo rewrite and document it
             * sms@mail.abcde.biz should recieve number with leading + or 00
             */
            if (strlen($sms_emails)) {
                sendSMTP($db, $dbr, $sms_emails, $sms2number,
                    substr($sms, 0, Config::get($db, $dbr, 'sms_message_limit')), array(), $from, $from_name,
                    $auction->auction_number, $auction->txnid, $template, $ins_id,
                    $defsmtp, $inside, $notes, $bcc,
                    $xheaders, $nextID);
            }
            $res = '<br>SMS: ' . $sms_emails . ', ' . $id;
        }
    }

    return $res;
}

/**
 * Send sms message via Clicatell service
 * @param string $number phone number in international format (without leading + or 0)
 * @param string $message text message to send
 * @param string $auction_number
 * @param string $txnid
 * @param string $template
 * @param string $nextID
 * @todo make cover for develop environment to not send real sms
 * @return string link to current sms send api
 */
function sendSMS_Clickatell($number, $message, $auction_number = '', $txnid = '', $template = '', $nextID = null)
{
    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
    $message = iconv('UTF-8','ISO-8859-1',$message);

    $url = "http://api.clickatell.com/http/sendmsg?from=0041325108911&user="
        .Config::get($db, $dbr, 'clickatell_user')."&password="
        .Config::get($db, $dbr, 'clickatell_password')."&api_id="
        .Config::get($db, $dbr, 'clickatell_api_id')."&to="
        .$number."&text="
        .urlencode($message)
        .(strlen(urlencode($message))>459?'&concat=4'
            :(strlen(urlencode($message))>306?'&concat=3':(strlen(urlencode($message))>160?'&concat=2':'')))
        ."";
    $res = file_get_contents($url);
    if (strpos($res, 'ID: ')!==false) {
        $id = str_replace('ID: ', '', $res);
        include_once 'lib/EmailLog.php';
        EmailLog::logSMS($auction_number, $txnid, $template
                   , $number, 'Clickatell', $id, $message, $nextID);
    }
    return "<a href='$url'>URL</a><br>".$res;
}

function resend_email($db, $dbr, $template, $id, $smtp='') {
    $table_name = $dbr->getOne("select log_table from template_names where name='$template'");
    $email = $dbr->getRow("select * from $table_name where id=$id");
    $email_content = $dbr->getRow("select * from prologis_log.{$table_name}_content where id=$id");
    $email_attachments = $dbr->getAll("select * from {$table_name}_attachment where id=$id");

    if ($email->txnid>=0) {
        $auction = $dbr->getRow("select * from auction where auction_number={$email->auction_number} and txnid={$email->txnid}");
        $sellerInfo4template = new SellerInfo($db, $dbr, $auction->username);
    } else {
        $sellerInfo4template = new SellerInfo($db, $dbr, Config::get($db, $dbr, 'aatokenSeller'));
    }

    if (strlen($smtp)) $defsmtp =  $dbr->getRow("select * from smtp where smtp='$smtp'");
    else $defsmtp = $sellerInfo4template->getDefSMTP();

    if (count($email_attachments)) {
        $attachments = array();
        foreach($email_attachments as $r) {
            if ($r->name=='html') {
                $attachments[] = 'html';
            } else {
                $rec = new stdClass;
                $rec->data = base64_decode($r->content);
                $rec->name = $r->name;
                $attachments[] = $rec;
            }
        }
    }
    sendSMTP($db, $dbr, $email->recipient, $email_content->subject, $email_content->content, $attachments
        , $email->sender, ''
        , $email->auction_number, $email->txnid, $template, ''
        , $defsmtp, $inside
        , $email->notes
        , $bcc, $xheaders
        , $id
        , true);
    return $db->getOne("select failed from $table_name where id=$id");
}

function sendSMTP($db, $dbr, $email, $subject, $message, $attachments=0,
        $from='', $from_name='', $auction_number='', $txnid='', $template='', $ins_id='',
        $smtps, $inside, $notes, $bcc, $xheaders, $nextID, $isResend=false) {
    /**
     * send every email to mailcatcher ONLY, none of emails will be send outside docker
     */
    if (APPLICATION_ENV === 'docker') {
        $smtps = array(new stdClass());
        $smtps[0]->smtp = 'mailcatcher';//mailcatcher's host @todo make env variable for it
        $smtps[0]->auth = 0;
        $smtps[0]->port = 1025;
        $smtps[0]->encrypt = '';
        $smtps[0]->id = 1;

        $message = htmlentities($message, ENT_SUBSTITUTE, 'UTF-8');
    }

    $result = true;
    require_once 'PHPMailer/PHPMailerAutoload.php';
    $template_rec = $dbr->getRow("select * from template_names where name='$template'");

    // get predefined smtp for email address
    $email_mask = Config::get($db, $dbr, 'email_mask');

    /**
     * @description pick up smtp mask according to template
     * @var $template
     * @var $email_mask_smtp
     * @var DB $db, $dbr
     */
    if ($template == "customer_shop_news") {
        $email_mask_smtp = Config::get($db, $dbr, 'email_mask_newsletter_smtp');
    } else {
        $email_mask_smtp = Config::get($db, $dbr, 'email_mask_shopmails_smtp');
    }

    $masks = explode(',', $email_mask);
    foreach ($masks as $mask) {
        $mask = trim($mask);
        if (stripos($email, $mask) !== false) {
            $smtps = array($email_mask_smtp);
        }
    }
    // get predefined smtp for email address

    $nowSend = true;
    if (!$isResend && $template_rec->can_wait && (is_array($auction_number) || (strlen($auction_number)))) {
        $nowSend = false;
    }

    if (!is_array($smtps)) $smtps = array($smtps);
    foreach ($smtps as $smtp) {
        if (!$smtp->id) {
            $smtp = $dbr->getRow("select * from smtp where id=" . $smtp);
        }
        $m = new PHPMailer;
        $m->isSMTP();
        global $debug;
        global $conv;

        //$debug = true;

        $msg = $nextID . ': ' . print_r($smtps, true) . ", $template, $email, <br>Subj=$subject, <br><br>Msg=$message, <br><br>($from_name)$from<br>Attachments:" . count($attachments) . "<br>";
        if ($debug) echo $msg;
        try {
            //		$m->SMTPDebug = 2;
            $m->CharSet = 'utf-8';
            $email = trim($email);
            $email_array = array();
            if (strpos($email, ',') === false) {
                $email_array[] = $email;
            } else {
                $email_array = explode(",", $email);
                if (count($email_array)) foreach ($email_array as $k => $r) $email_array[$k] = trim($email_array[$k]);
                $email_array = array_unique($email_array);
                if (count($email_array)) foreach ($email_array as $k => $r) {
                    if (!strlen($email_array[$k])) unset($email_array[$k]);
                }
                $email = implode(", ", $email_array);
            } // email addr
            $message = str_replace("\r\n", "\r", $message);
            $message = str_replace("\n", "\r", $message);
            $m->Subject = $subject;
            if ($attachments == 'html' || $attachments[0] == 'html') {
                # chanes by Peppio 2014-10-04
                $m->isHTML(true);
                #		$m->Body = $message;
                $m->AltBody = 'To view the message, please use an HTML compatible email viewer!'; // optional - MsgHTML will create an alternate automatically
                $m->MsgHTML($message);
            } else {
                $m->Body = $message;
            }
            if (is_array($attachments)) foreach ($attachments as $r) {
                if ( ! is_object($r)) {
                    continue;
                }
                file_put_contents('tmp/' . $r->name, $r->data);
                $m->addAttachment('tmp/' . $r->name);
            } // attach
            if (strpos($from, ',')) $from = substr($from, 0, strpos($from, ','));
            $m->From = $from;
            $m->FromName = $from_name;
            //	print_r($m);
            $bcc_array = explode(",", $bcc);
            if (count($bcc_array)) foreach ($bcc_array as $k => $r) $bcc_array[$k] = trim($bcc_array[$k]);
            $bcc_array = array_unique($bcc_array);
            if (count($bcc_array)) foreach ($bcc_array as $k => $r) {
                if (strlen($bcc_array[$k])) $m->addBCC($bcc_array[$k]);
            }
            if (is_array($xheaders)) foreach ($xheaders as $xheader) {
                list($xname, $xvalue) = explode(':', $xheader->header, 2);
                $m->addCustomHeader($xname, $xvalue);
            }
            if ($_REQUEST['testmode']) {
                $tresult = true;
            } else {
                $m->Host = $smtp->smtp;
                $m->Port = $smtp->port;
                if ($smtp->auth) $m->SMTPAuth = true; else $m->SMTPAuth = false;
                $m->Username = $smtp->login;                 // SMTP username
                $m->Password = $smtp->pw;                           // SMTP password
                if (strlen($smtp->encrypt)) $m->SMTPSecure = $smtp->encrypt;
                if ($subject == 'smtptest' || $debug) {
                    $m->SMTPDebug = 4;
                    $m->Debugoutput = function ($str, $level) {
                        global $conv;
                        $conv .= 'ERROR!!' . str_replace('SMTP -> get_lines()', '', $str) . print_r($m, true);
                    };
                }
                if ($nowSend) {
                    if ($template_rec->split) {
                        foreach ($email_array as $single_email) {
                            $m->addAddress($single_email);
                            $tresult = $m->Send();
                            $m->ClearAddresses();
                        }
                    } else {
                        foreach ($email_array as $single_email) {
                            $m->addAddress($single_email);
                        }
                        $tresult = $m->Send();
                    }
                }
                $m->SMTPDebug = 0;
            }
            if ($debug) print_r($conv);
            if (!$tresult) {
                $result = false;
                if (!strlen($conv)) $conv = 'ErrorInfo!: ' . print_r($m->ErrorInfo, true) . print_r($m, true);
            }
        } catch (phpmailerException $e) {
            $result = false;
            $conv = 'errorMessage: ' . $e->errorMessage(); //Pretty error messages from PHPMailer
        } catch (Exception $e) {
            $result = false;
            $conv = 'getMessage: ' . $e->getMessage(); //Boring error messages from anything else!
        }
        unset($m);
        if (is_array($attachments)) foreach ($attachments as $r) {
            if (is_file('tmp/' . $r->name)) {
                unlink('tmp/' . $r->name);
            }
        }

        if ($debug) {
            echo "<pre>$conv</pre>";
        }

        if ($message == '') $message = $attachments[0]->data;
        $time = ServerTolocal(date("Y-m-d H:i:s"), $timediff);
        if (is_array($auction_number)) {
            foreach ($auction_number as $auction)
                EmailLog::log($db, $dbr, $auction->auction_number, $auction->txnid, $template
                    , $email, $from, $smtp->smtp
                    , $subject, $message, $notes, $attachments, $nextID, "'$time'", ($result ? 0 : 1));
        } elseif (strlen($auction_number))
            EmailLog::log($db, $dbr, $auction_number, $txnid, $template
                , $email, $from, $smtp->smtp, $subject, $message, $notes, $attachments, $nextID, "'$time'", ($result ? 0 : 1));
        if ($ins_id) {
            global $loggedUser;
            if (!is_a($loggedUser, 'User')) {
                $timediff = 0;
                $username = 'cron';
            } else {
                $timediff = $loggedUser->get('timezone');
                $username = $loggedUser->get('username');
            }
            Insurance::addLog($db, $dbr, $ins_id,
                $username,
                $time,
                $template, $email, $from, $smtp->smtp);
        } // if insurance
        if ($nextID) {
            $db->query("delete from {$template_rec->log_table} where template='' and id=$nextID");
        }
    } // foreach active SMTP
    return $result;
};

/**
 * @return string
 * @param array $db
 * @param string $auction_number
 * @desc Formats shipping list with weights
*/
function formatShippingList($db, $dbr, $auction_number, $txnid, $mode, $lang='german', $template)
{
#	echo '<br><br>$template='.$template.' $mode='.$mode.'<br>';
    require_once 'lib/Order.php';
    global $english;
    $order = Order::listAll($db, $dbr, $auction_number, $txnid, 1, $lang);
    $auction = new Auction($db, $dbr, $auction_number, $txnid);
    $order_[''] = $order;
    $order_['past_present'] = $order_['past'] = $order_['present'] = $order_['future'] = array();
    list ($Y,$M,$D,$h,$m,$s) = preg_split('/[:\-\sT]+/', date('Y-m-d H:i:s'));
    $date_from = date('Y-m-d H:i:s', mktime($h,$m,$s-60,$M,$D,$Y));

    foreach ($order as $item) {
        if($item->admin_id != 0) continue;
        if (in_array($auction->get('payment_method'), array(3,4)) && $template == 'ready_to_pick_up_details') {
            $updated = $dbr->getOne("select updated from total_log
                where tableid={$item->id} and table_name='orders' and field_name='ready2pickup' and new_value=1");
        } else {
            $updated = $dbr->getOne("select updated from total_log
                where tableid={$item->id} and table_name='orders' and field_name='sent' and new_value=1");
        }

        $item->updated = $updated;
        if (!$updated) {
            $order_['future'][] = $item;
        } elseif ($updated < $date_from) {
            $order_['past'][] = $item;
            $order_['past_present'][] = $item;
        } elseif ($updated > $date_from) {
            $order_['present'][] = $item;
            $order_['past_present'][] = $item;
        }
    }

        $width = 76;
    $ret = '';
    foreach ($order_[$mode] as $key => $item) {
        $tn_rows = $dbr->getAll("select m.company_name, tn.date_time, REPLACE(REPLACE(REPLACE(m.tracking_url,'[[zip]]','".$auction->get('zip_shipping')."'),'[[number]]',tn.number),'[[country_code2]]','".countryToCountryCode($auction->get('country_shipping'))."') url, tn.number
            , ttt.value tracking_text
            , ttt1.value route_tracking_html
            from tn_orders tno
            join tracking_numbers tn on tn.id=tno.tn_id
            join shipping_method m on tn.shipping_method=m.shipping_method_id
            left join translation ttt on ttt.table_name='method' and ttt.field_name='tracking_text'
                and ttt.language='$lang' and ttt.id=m.shipping_method_id
            left join translation ttt1 on ttt1.table_name='method' and ttt1.field_name='route_tracking_html'
                and ttt1.language='$lang' and ttt1.id=m.shipping_method_id
            where tno.order_id = ".$item->id);
        foreach ($tn_rows as $tn_row) {
            $order_[$mode][$key]->tracking_numbers[$tn_row->number] = $tn_row;
            if($key > 0 && array_key_exists($tn_row->number, $order_[$mode][$key - 1]->tracking_numbers)){
                continue;
            }
            $ret .= $english[193] . ': ' . $tn_row->company_name . "\n";
            $ret .= $tn_row->tracking_text . "\n";
            $ret .= $english[194] . ': ' . $tn_row->url . "\n";
            $ret .= $english[195] . ':' . " " . $tn_row->number . "\n";
            if (strlen($tn_row->route_tracking_html)) $route_tracking_html=$tn_row->route_tracking_html;
        }

        $weight = 0;
        $weight += $item->quantity * $item->weight_per_single_unit;
        $article = new Article($db, $dbr, $item->article_id, -1, 1, $lang);
        if (count($article->materials)) foreach ($article->materials as $item_material) {
            $weight += $item->quantity * ($item_material->weight / $item_material->items_per_shipping_unit)
                * $item_material->shm_quantity / $article->get('items_per_shipping_unit');
        };
        $ret .= $english [86] . ' : ';
        $ret .= sprintf('%8.2f kg', $weight);

        $ret .= "\n\n";
        if ($mode=='present' && strlen($route_tracking_html)) {
            $route_car = $dbr->getRow("select cars.* from cars
                join route on route.car_id=cars.id
                where route.id=".$auction->get('route_id'));
            $route_tracking_html = substitute($route_tracking_html, $route_car);
            $ret .= $route_tracking_html."\n";//.print_r($route_car,true)."\n";
        }

        // article 1, weight, scanning_date
        $warehouse = new Warehouse($db, $dbr, $item->reserve_warehouse_id);
        if ($item->alias_id) {
            $name = 'A'.$item->alias_id.': '.$item->custom_title ? $item->custom_title : $item->alias_name;
        } else {
            $name = $item->custom_title ? $item->custom_title : $item->name;
        }
//		$name = "'".$item->updated . "' " . $name;
        $lines = explode("\n",wordwrap($english[189].': '.$name, $width - 18, "\n", true));
        $ret .= str_pad($lines[0], $width, ' ', STR_PAD_RIGHT);
        $ret .= "\n";
        for ($i = 1; $i<count($lines); $i++) {
            $ret .= $lines[$i] . "\n";
        }
        $ret .= $english[190].': '.str_pad($item->quantity, 4, ' ', STR_PAD_LEFT) . ' x ';
        $ret .= sprintf('%7.2f kg', $item->weight_per_single_unit);
//        $weight += $item->quantity * $item->weight_per_single_unit;
        $ret .= "\n";
        if (strlen($item->scanning_date)) {
            $ret .= $english[191].': '.$item->scanning_date;
            $ret .= "\n";
        }
        if (in_array($auction->get('payment_method'), array(3,4)) && $template == 'ready_to_pick_up_details') {
            if ($warehouse->data->warehouse_id) {
                $ret .= $english[192].': '.$item->scanning_date;
                $ret .= $warehouse->data->address1.' '.$warehouse->data->address2.' '.$warehouse->data->address3.' '.$warehouse->data->phone;
                $ret .= "\n";
            }
        }
    }
    foreach ($order_[$mode] as $item) {
        $article = new Article($db, $dbr, $item->article_id, -1, 1, $lang);
        if (count($article->materials)) foreach ($article->materials as $item_material) {
               $name = $item_material->translated_name;
               $lines = explode("\n",wordwrap($name, $width - 18, "\n", true));
               $ret .= str_pad($lines[0], $width - 18, ' ', STR_PAD_RIGHT);
               $ret .= str_pad($item->quantity * $item_material->shm_quantity, 4, ' ', STR_PAD_LEFT) . ' x ';
//		   / $article->get('items_per_shipping_unit'), 4, ' ', STR_PAD_LEFT) . ' x ';
               $ret .= sprintf('%7.2f kg', ($item_material->weight / $item_material->items_per_shipping_unit)
                  * $item_material->shm_quantity / $article->get('items_per_shipping_unit'));
//               $weight += $item->quantity * ($item_material->weight / $item_material->items_per_shipping_unit)
//                  * $item_material->shm_quantity / $article->get('items_per_shipping_unit');
               $ret .= "\n";
               for ($i = 1; $i<count($lines); $i++) {
                $ret .= $lines[$i] . "\n";
               }
               $ret .= str_repeat('-', $width)."\n";
        };
    }

    return nl2br($ret);
}

/*
 * Get tracking numbers for auction
 * $mode = future|past|past_present|present|
 * return array
 */
function getTrackingNumbersList($db, $dbr, $auction_number, $txnid, $mode, $lang='german', $template){
    require_once 'lib/Order.php';

    $order = Order::listAll($db, $dbr, $auction_number, $txnid, 1, $lang);
    $auction = new Auction($db, $dbr, $auction_number, $txnid);
    $order_[''] = $order;
    $order_['past_present'] = $order_['past'] = $order_['present'] = $order_['future'] = array();
    list ($Y,$M,$D,$h,$m,$s) = preg_split('/[:\-\sT]+/', date('Y-m-d H:i:s'));
    $date_from = date('Y-m-d H:i:s', mktime($h,$m,$s-60,$M,$D,$Y));

    foreach ($order as $item) {
        if($item->admin_id != 0) continue;
        if (in_array($auction->get('payment_method'), array(3,4)) && $template == 'ready_to_pick_up_details') {
            $updated = $dbr->getOne("select updated from total_log
                where tableid={$item->id} and table_name='orders' and field_name='ready2pickup' and new_value=1");
        } else {
            $updated = $dbr->getOne("select updated from total_log
                where tableid={$item->id} and table_name='orders' and field_name='sent' and new_value=1");
        }

        $item->updated = $updated;
        if (!$updated) {
            $order_['future'][] = $item;
        } elseif ($updated < $date_from) {
            $order_['past'][] = $item;
            $order_['past_present'][] = $item;
        } elseif ($updated > $date_from) {
            $order_['present'][] = $item;
            $order_['past_present'][] = $item;
        }
    }

    $tracking_numbers = [];

    foreach ($order_[$mode] as $key => $item) {
        $tn_rows = $dbr->getAll("select m.company_name, tn.date_time, REPLACE(REPLACE(REPLACE(m.tracking_url,'[[zip]]','".$auction->get('zip_shipping')."'),'[[number]]',tn.number),'[[country_code2]]','".countryToCountryCode($auction->get('country_shipping'))."') url, tn.number
            , ttt.value tracking_text
            , ttt1.value route_tracking_html
            from tn_orders tno
            join tracking_numbers tn on tn.id=tno.tn_id
            join shipping_method m on tn.shipping_method=m.shipping_method_id
            left join translation ttt on ttt.table_name='method' and ttt.field_name='tracking_text'
                and ttt.language='$lang' and ttt.id=m.shipping_method_id
            left join translation ttt1 on ttt1.table_name='method' and ttt1.field_name='route_tracking_html'
                and ttt1.language='$lang' and ttt1.id=m.shipping_method_id
            where tno.order_id = ".$item->id);

        foreach ($tn_rows as $tn_row) {
            if(!isset($tracking_numbers[$tn_row->number])){
                $tracking_numbers[$tn_row->number] = $tn_row->url;
            }
        }
    }

    return $tracking_numbers;
}

/**
 * @return string
 * @param int $stage
 * @desc Converts stage code to stage name
*/
function stageName($stage) {
    $names = array(
        1 => 'Listed',
        2 => 'No winner',
        3 => 'Won',
        4 => 'Winning email resent 1',
        5 => 'Winning email resent 2',
        6 => 'No reply',
        7 => 'Ordered',
        8 => 'Payment instruction resent 1',
        9 => 'Payment instruction resent 2',
       10 => 'Paid',
       11 => 'Ready to pickup',
       12 => 'Ready to pickup resent 1',
       13 => 'Ready to pickup resent 2',
       14 => 'Waiting for rating',
       15 => 'Relisted',
    );
    return $names[$stage];
}

/**
 * @return array
 * @desc Returns array of all labels files registered in the system
*/
function allLabels()
{
    $labels = array();
    $d = dir('labels');
    while (false !== ($file = $d->read())) {
        if ($file{0} != '.') {
            $labels[] = $file;
        }
    }
    $d->close();
    return $labels;
}

function revise_auctions($db, $dbr, $new) {
    $info = SellerInfo::singleton($db, $dbr, $new['username']);
    $q = "select listings.auction_number
    , listings.finished
    , listings.params
    , listings.server
    from listings
    where listings.saved_id=".$new['saved_id']."
        and listings.finished=0 and listings.quantity>0
        and listings.end_time>NOW()
    ";
   $last20fixed = $dbr->getAll($q);
   foreach($last20fixed as $au) {
            $server = $info->getServer();
            $db->execParam("update listings set details=? where auction_number=?", array(serialize($new), $au->auction_number));
            $res = file_get_contents("http://{$au->server}/revise_auction.php?auction_number={$au->auction_number}");
   }
   $last20 = $dbr->getAll("
          select auction.auction_number
        , apt.value params
    from auction
    left join auction_par_text apt on auction.auction_number=apt.auction_number and auction.txnid=apt.txnid and apt.key='params'
    where auction.txnid = 1 and auction.saved_id=$saved_id and IFNULL(auction.deleted,0)=0
            and auction.end_time>NOW()
    ");
   foreach($last20 as $au) {
            $server = $info->getServer();
            $db->execParam("update auction_par_text set value=? where auction_number=? and txnid=1 and `key`='details'", array(serialize($new), $au->auction_number));
            $res = file_get_contents("http://{$au->server}/revise_auction.php?auction_number={$au->auction_number}");
   }
    //HTML or/and Item specifics, Galery, Alias, Duration Quantity, Price
}

/**
 *
 * @param string $username
 */
function revise_seller_auctions($username) {
    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $curl = new \Curl(null, [
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.4 (KHTML, like Gecko) Chrome/22.0.1229.92 Safari/537.4',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 3600
    ]);

    $info = \SellerInfo::singleton($db, $dbr, $username);
    $server = $info->getServer();

    $sas = $dbr->getAll("select sa.id
        from saved_auctions sa
        join saved_params sp on sp.par_key='username' and sp.saved_id=sa.id
        where sa.old=0 and sa.inactive=0 and sp.par_value='$username'");
    foreach($sas as $sa) {
        $new = \Saved::getDetails($sa->id);

        $last20fixed = $dbr->getAll("select listings.auction_number
        , listings.finished
        , listings.params
        , listings.server
        from listings
            where listings.saved_id='{$sa->id}'
            and listings.finished=0 and listings.quantity>0
                and listings.end_time>NOW()");
       foreach($last20fixed as $au) {
                echo 'Revise '.$au->auction_number.'<br>';
                $db->execParam("update listings set details=? where auction_number=?", array(serialize($new), $au->auction_number));
            $curl->set_url("http://{$au->server}/revise_auction.php?auction_number={$au->auction_number}");
            $res = $curl->exec();
                echo $res.'<br><br><br>';
       }

        $last20 = $dbr->getAll("select auction.auction_number
            , apt.value params
        from auction
        left join auction_par_text apt on auction.auction_number=apt.auction_number and auction.txnid=apt.txnid and apt.key='params'
            where auction.txnid = 1 and auction.saved_id='{$sa->id}' and IFNULL(auction.deleted,0)=0
                and auction.end_time>NOW()");
       foreach($last20 as $au) {
                echo 'Revise '.$au->auction_number.'<br>';
                $db->execParam("update auction_par_text set value=? where auction_number=? and txnid=1 and `key`='details'", array(serialize($new), $au->auction_number));
            $curl->set_url("http://{$server}/revise_auction.php?auction_number={$au->auction_number}");
            $res = $curl->exec();
                echo $res.'<br><br><br>';
       }
    } // foreach sa of seller
    //HTML or/and Item specifics, Galery, Alias, Duration Quantity, Price
}

function relist_auction($auction, $force=0, $saved_id)
{
    if ($auction->get('relist_failed') && !$force) return;
    require_once 'lib/Offer.php';
    require_once 'Services/Ebay.php';
    require_once 'lib/SellerInfo.php';
    global $devId, $appId, $certId,$aaToken, $db, $dbr;
    $info = SellerInfo::singleton($db, $dbr, $auction->data->username);
    if ($_SERVER['HTTP_HOST']!=$info->getServer()) /*die($_SERVER['HTTP_HOST'].'!='.$info->getServer());*/
        return PEAR::raiseError( 'Wrong server: current '.$_SERVER['HTTP_HOST'].'!='.$info->getServer(), 100 );
    $info->geteBay($devId, $appId, $certId);
    $eBay = new Services_Ebay( $devId, $appId, $certId );
    $eBay->setAuth( $info->get('aatoken') );
    $vars = unserialize($auction->get('details'));
//    $offers = Offer::listArray($db, $dbr);
    $params = array(
        'Item' => array (
            'ItemID'=>$auction->data->auction_number
        )
    );
    if (is_a($auction, 'Auction')) {
         $item = $eBay->RelistItem($params, 0);
    } else {
         $item = $eBay->RelistFixedPriceItem($params, 0);
    }
    if (!PEAR::isError($item) && !$verify) {
        $total_fees = 0;
        foreach ($item['Fees']['Fee'] as $fee) {
            if ($fee['Name']=='ListingFee') $total_fees = $fee['Fee'];
        };
        if (is_a($auction, 'Auction')) {
            $newauction = new Auction($db, $dbr);
            $newauction->data->username = $auction->data->username;
            $newauction->data->auction_number = $item['ItemID'];
            $newauction->data->txnid = 1;
            $newauction->data->offer_id = $auction->data->offer_id;
            $newauction->data->siteid = $auction->data->siteid;
            $newauction->data->end_time1=$auction->data->end_time;
            $newauction->data->end_time2=GMTToLocal($item['EndTime']);
            $newauction->data->end_time=GMTToLocal($item['EndTime']);
            $newauction->data->start_time1=$auction->data->start_time;
            $newauction->data->start_time2=$newauction->data->start_time=GMTToLocal($item['StartTime']);
            $newauction->data->details = serialize($vars);
            $newauction->data->params = serialize($params);
            $newauction->data->server = $_SERVER['HTTP_HOST'];
            $newauction->data->quantity = $vars['quantity'];
            if (!$vars['payment']['CashOnPickup']) {
                $newauction->data->no_pickup = 1;
            }
            $newauction->data->saved_id = $saved_id;
            $newauction->data->name_id = $auction->data->name_id;
            $newauction->data->listing_fee1 = $auction->data->listing_fee;
            $newauction->data->listing_fee2 = $total_fees;
            $newauction->data->listing_fee += $total_fees;
            $newauction->data->auction_number_prev = $auction->get('auction_number');
            $newauction->data->process_stage = STAGE_RELISTED;
            $newauction->data->status_change = date('Y-m-d H:i:s');
            $newauction->addComment(' Relisted. Old number was '.$auction->get('auction_number'), 'cron', date('Y-m-d H:i:s'));
            $newauction->data->allow_payment_1 = $auction->get('allow_payment_1');
            $newauction->data->allow_payment_2 = $auction->get('allow_payment_2');
            $newauction->data->allow_payment_3 = $auction->get('allow_payment_3');
            $newauction->data->allow_payment_4 = $auction->get('allow_payment_4');
            $newauction->data->allow_payment_cc = $auction->get('allow_payment_cc');
            $newauction->data->allow_payment_cc_sh = $auction->get('allow_payment_cc_sh');
            $newauction->data->_SERVER = 'relist: '.print_r($_SERVER,true);
            $newauction->data->_REQUEST = 'relist: '.print_r($_REQUEST,true);
            $newauction->update();
            echo 'Auction starty time saved as: '.$newauction->get('start_time').'<br>';
        } else {
            $listing = new Listing($db, $dbr);
            $listing->data->username = $auction->data->username;
            $listing->data->auction_number = $item['ItemID'];
            $listing->data->auction_type = $auction->get('auction_type');
            $listing->data->auction_type_vch = $auction->get('auction_type_vch');
            $listing->data->offer_id = $auction->data->offer_id;
            $listing->data->siteid = $auction->data->siteid;
            $listing->data->end_time1=$auction->data->end_time;
            $listing->data->end_time2=GMTToLocal($item['EndTime']);
            $listing->data->end_time=GMTToLocal($item['EndTime']);
            $listing->data->start_time1=$auction->data->start_time;
            $listing->data->start_time2=$listing->data->start_time=GMTToLocal($item['StartTime']);
            $listing->data->listing_fee1 = $auction->data->listing_fee;
            $listing->data->listing_fee2 = $total_fees;
            $listing->data->listing_fee += $total_fees;
            $listing->data->details = serialize($vars);
            $listing->data->params = serialize($params);
            $listing->data->server = $_SERVER['HTTP_HOST'];
            $listing->data->quantity = $vars['quantity'];
            $listing->data->saved_id = $saved_id;
            $listing->data->name_id = $auction->data->name_id;
            $listing->data->auction_number_prev = $auction->get('auction_number');
            if (!$vars['payment']['CashOnPickup']) {
                $listing->data->no_pickup = 1;
            }
            $listing->addComment(' Relisted. Old number was '.$auction->get('auction_number'), 'cron', date('Y-m-d H:i:s'));
            $listing->data->allow_payment_1 = $auction->get('allow_payment_1');
            $listing->data->allow_payment_2 = $auction->get('allow_payment_2');
            $listing->data->allow_payment_3 = $auction->get('allow_payment_3');
            $listing->data->allow_payment_4 = $auction->get('allow_payment_4');
            $listing->data->allow_payment_cc = $auction->get('allow_payment_cc');
            $listing->data->allow_payment_cc_sh = $auction->get('allow_payment_cc_sh');
            $listing->update();
            echo 'Auction starty time saved as: '.$listing->get('start_time').'<br>';
            if (PEAR::isError($listing)) {
                $_error = $listing;
            }
        }
        $auction->set('relist_failed', 2);
        $auction->update();
            echo 'GMT start time is: '.$item['StartTime'].'<br>';
            echo 'GMTToLocal is: '.GMTToLocal($item['StartTime']).'<br>';
            echo 'correction: '.(int)Config::get($db, $dbr, 'GMT_to_local').'<br>';
            echo 'Current server time is: '.date("Y-m-d H:i:s");
/*    if (!PEAR::isError($item)) {
        $total_fees = 0;
        foreach ($item['Fees']['Fee'] as $fee) {
            if ($fee['Name']=='ListingFee') $total_fees = $fee['Fee'];
        };
            $auction->data->end_time2=GMTToLocal($item['EndTime']);
            $auction->data->end_time=GMTToLocal($item['EndTime']);
            $auction->data->start_time=GMTToLocal($item['StartTime']);
            $auction->data->listing_fee1 = $auction->data->listing_fee;
            $auction->data->listing_fee2 = $total_fees;
            $auction->data->listing_fee += $total_fees;
            $old_auction_number = $auction->get('auction_number');
            $auction->data->auction_number_prev = $old_auction_number;
            $auction->data->auction_number = $item['ItemID'];
            $auction->addComment(' Relisted. Old number was '.$old_auction_number, 'cron', date('Y-m-d H:i:s'));
            if (is_a($auction,'Auction')) {
                $auction->data->process_stage = STAGE_RELISTED;
                $auction->data->status_change = date('Y-m-d H:i:s');
            };
            $auction->update($old_auction_number);
    } else {
       echo 'Error! Parameters was <br>';
       aprint_r($params);
       echo '<br> Result is <br>';
       aprint_r($item);
       echo '<br>';
    } */
        $db->query("insert into prologis_log.listing_log (dt, auction_number, txnid, server_host, server_script, php_function)
                values (
                    now(),
                    ".$item['ItemID'].",
                    NULL,
                    '".$_SERVER['HTTP_HOST']."',
                    '".$_SERVER['PHP_SELF']."',
                    'relist_auction'
                )");
    } else {
        $auction->set('relist_failed', 1);
        $auction->update();
       echo 'Error! Parameters was <br>';
       print_r($params);
       echo '<br> Result is <br>';
       print_r($item);
       echo '<br>';
    }
    return $item;
}

function stop_auction($auction)
{
    require_once 'lib/Offer.php';
    require_once 'Services/Ebay.php';
    require_once 'lib/SellerInfo.php';
    global $devId, $appId, $certId,$aaToken, $db, $dbr;
    $info = SellerInfo::singleton($db, $dbr, $auction->data->username);
#	if ($_SERVER['HTTP_HOST']!=$info->getServer()) /*die($_SERVER['HTTP_HOST'].'!='.$info->getServer());*/
#		return PEAR::raiseError( 'Wrong server: current '.$_SERVER['HTTP_HOST'].'!='.$info->getServer(), 100 );
    $info->geteBay($devId, $appId, $certId);
    $eBay = new Services_Ebay( $devId, $appId, $certId );
    $eBay->setAuth( $info->get('aatoken') );
    $params = array(
            'ItemID'=>$auction->data->auction_number,
            'EndingReason'=>'OtherListingError'
    );
    if (is_a($auction, 'Auction')) {
         $item = $eBay->EndItem($params);
    } else {
         $item = $eBay->EndFixedPriceItem($params);
    }
    if (!PEAR::isError($item) && !$verify) {
        $auction->data->end_time1=GMTToLocal($item['EndTime']);
        $auction->data->end_time=GMTToLocal($item['EndTime']);
    }
    if (is_a($auction, 'Auction')) {
        $auction->data->process_stage = STAGE_NO_WINNER;
        $auction->data->status_change = date('Y-m-d H:i:s');
        $auction->addComment(' Manual stopped', 'cron', date('Y-m-d H:i:s'));
    } else {
        $auction->data->finished = 1;
    }
    $r = $auction->update();
    return $item;
}

function secondchance_auction($auction, $userID, $email, $duration, $price)
{
    require_once 'lib/Offer.php';
    require_once 'Services/Ebay.php';
    require_once 'lib/SellerInfo.php';
    global $devId, $appId, $certId,$aaToken, $db, $dbr;
    $info = SellerInfo::singleton($db, $dbr, $auction->data->username);
    if ($_SERVER['HTTP_HOST']!=$info->getServer())
        return PEAR::raiseError( 'Wrong server: current '.$_SERVER['HTTP_HOST'].'!='.$info->getServer(), 100 );
    $info->geteBay($devId, $appId, $certId);
    $eBay = new Services_Ebay( $devId, $appId, $certId );
    $eBay->setAuth( $info->get('aatoken') );
    $vars = unserialize($auction->get('details'));
//    $offers = Offer::listArray($db, $dbr);
    $params = array(
            'ItemID'=>$auction->data->auction_number,
            'Duration'=> 'Days_'.$duration,
            'RecipientBidderUserID' => $userID,
            'SellerMessage' => $email,
    );
   $item = $eBay->AddSecondChanceItem($params);
    if (!PEAR::isError($item) && !$verify) {
        $total_fees = 0;
/*		foreach ($item['Fees']['Fee'] as $fee) {
            if ($fee['Name']=='ListingFee') $total_fees = $fee['Fee'];
        }; */
        if (is_a($auction, 'Auction')) {
            $newauction = new Auction($db, $dbr);
            $newauction->data->username = $auction->get('username');
            $newauction->data->auction_number = $item['ItemID'];
            $newauction->data->txnid = 1;
            $newauction->data->offer_id = $auction->get('offer_id');
            $newauction->data->siteid = $auction->get('siteid');
            $newauction->data->end_time1=$auction->data->end_time;
            $newauction->data->end_time2=GMTToLocal($item['EndTime']);
            $newauction->data->end_time=GMTToLocal($item['EndTime']);
            $newauction->data->start_time1=$auction->data->start_time;
            $newauction->data->start_time2=GMTToLocal($item['StartTime']);
            $newauction->data->start_time=GMTToLocal($item['StartTime']);
            $newauction->data->details = serialize($vars);
            $newauction->data->params = serialize($params);
            $newauction->data->server = $_SERVER['HTTP_HOST'];
            $newauction->data->quantity = $auction->get('quantity');
            if (!$vars['payment']['CashOnPickup']) {
                $newauction->data->no_pickup = 1;
            }
            $newauction->data->saved_id = $auction->get('saved_id');
            $newauction->data->name_id = $auction->get('name_id');
            $newauction->data->listing_fee1 = $auction->data->listing_fee;
            $newauction->data->listing_fee2 = $total_fees;
            $newauction->data->listing_fee += $total_fees;
            $newauction->data->auction_number_prev = $auction->get('auction_number');
            $newauction->data->process_stage = STAGE_RELISTED;
            $newauction->data->status_change = date('Y-m-d H:i:s');
            $newauction->data->old_price = $price;
            $newauction->addComment(' Second chance auction of '
                .$auction->get('auction_number').'/'.$auction->get('txnid')."($userID)", 'cron', date('Y-m-d H:i:s'));
            $newauction->data->allow_payment_1 = $auction->get('allow_payment_1');
            $newauction->data->allow_payment_2 = $auction->get('allow_payment_2');
            $newauction->data->allow_payment_3 = $auction->get('allow_payment_3');
            $newauction->data->allow_payment_4 = $auction->get('allow_payment_4');
            $newauction->data->allow_payment_cc = $auction->get('allow_payment_cc');
            $newauction->data->allow_payment_cc_sh = $auction->get('allow_payment_cc_sh');
            $newauction->update();
        } else {
            $listing = new Listing($db, $dbr);
            $listing->data->username = $auction->get('username');
            $listing->data->auction_number = $item['ItemID'];
            $listing->data->auction_type = $auction->get('auction_type');
            $listing->data->auction_type_vch = $auction->get('auction_type_vch');
            $listing->data->offer_id = $auction->get('offer_id');
            $listing->data->siteid = $auction->get('siteid');
            $listing->data->end_time1=$auction->data->end_time;
            $listing->data->end_time2=GMTToLocal($item['EndTime']);
            $listing->data->end_time=GMTToLocal($item['EndTime']);
            $listing->data->start_time1=$auction->data->start_time;
            $listing->data->start_time2=GMTToLocal($item['StartTime']);
            $listing->data->start_time=GMTToLocal($item['StartTime']);
            $listing->data->listing_fee1 = $auction->data->listing_fee;
            $listing->data->listing_fee2 = $total_fees;
            $listing->data->listing_fee += $total_fees;
            $listing->data->details = serialize($vars);
            $listing->data->params = serialize($params);
            $listing->data->server = $_SERVER['HTTP_HOST'];
            $listing->data->quantity = $auction->get('quantity');
            $listing->data->saved_id = $auction->get('saved_id');
            $listing->data->name_id = $auction->get('name_id');
            $listing->data->auction_number_prev = $auction->get('auction_number');
            if (!$vars['payment']['CashOnPickup']) {
                $listing->data->no_pickup = 1;
            }
            $listing->data->old_price = $price;
            $listing->addComment(' Second chance auction of '.$auction->get('auction_number')."($userID)", 'cron', date('Y-m-d H:i:s'));
            $listing->data->allow_payment_1 = $auction->get('allow_payment_1');
            $listing->data->allow_payment_2 = $auction->get('allow_payment_2');
            $listing->data->allow_payment_3 = $auction->get('allow_payment_3');
            $listing->data->allow_payment_4 = $auction->get('allow_payment_4');
            $listing->data->allow_payment_cc = $auction->get('allow_payment_cc');
            $listing->data->allow_payment_cc_sh = $auction->get('allow_payment_cc_sh');
            $listing->update();
            if (PEAR::isError($listing)) {
                $_error = $listing;
            }
        }
//		$auction->set('relist_failed', 2);
        $auction->update();
    } else {
//		$auction->set('relist_failed', 1);
        $auction->update();
       echo 'Error! Parameters was <br>';
       print_r($params);
       echo '<br> Result is <br>';
       print_r($item);
       echo '<br>';
    }
    return $item;
}

/**
 * @return array|object
 * @param array $vars
 * @desc Lists an auction on eBay
*/
function list_auction($vars, $verify = false, $revise_auction_number=0, $force=0)
{
    require_once 'lib/Auction.php';
    require_once 'lib/Offer.php';
    require_once 'Services/Ebay.php';
    require_once 'lib/SellerInfo.php';
    require_once 'lib/WidgetBuilder.php';
//	echo '1st vars:';print_r($vars['sp']);
    global $siteURL;
    global $devId, $appId, $certId,$aaToken, $db, $dbr;
//    $offers = Offer::listArray($db, $dbr);
    $info = SellerInfo::singleton($db, $dbr, $vars['username']);
    if (!$force && !$revise_auction_number && $_SERVER['HTTP_HOST']!='176.9.138.197' && $_SERVER['HTTP_HOST']!=$info->getServer()/* || strpos($_SERVER['PHP_SELF'], "newauction.php")===false*/) {
        echo  '!!Wrong server: current '.$_SERVER['HTTP_HOST'].'!='.$info->getServer();
        return PEAR::raiseError( '!!Wrong server: current '.$_SERVER['HTTP_HOST'].'!='.$info->getServer(), 100 );
    }
    $info->geteBay($devId, $appId, $certId);
//	echo $vars['siteid'].'<br>';
    $eBay = new Services_Ebay( $devId, $appId, $certId, $vars['siteid']);
    $eBay->setAuth( $info->get('aatoken') );
    $category =  (int)$vars['category'];
    $category2 = (int)$vars['category2'];
    $mail = 'Was '.$vars['offer_id'].'<br>';
    $offer = new Offer($db, $dbr, $vars['offer_id']);
    if ($vars['saved_id']) {
           $lastDetail = $dbr->getOne("select details from (
                  select apt.value as details, end_time
            from auction
            JOIN auction_par_text apt on apt.auction_number=auction.auction_number and apt.txnid=auction.txnid and apt.key='details'
            where auction.saved_id=".$vars['saved_id']."
            union select details, end_time from listings auction where auction.saved_id=".$vars['saved_id']."
            ) t order by end_time desc LIMIT 0 , 1");
        if (!$lastDetail) {
            $lastDetail = serialize(array());
        }

        {
            $lastvars = unserialize($lastDetail);
            $lastDescrNum = $lastvars['DescrNum'];
            if (!$lastDescrNum) $lastDescrNum=1;
            switch ($lastDescrNum) {
               case 1:
                    if ($vars['inactivedescription'][2] || !strlen($vars['description'][2]))
                        if ($vars['inactivedescription'][3] || !strlen($vars['description'][3]))
                            if ($vars['inactivedescription'][4] || !strlen($vars['description'][4]))
                                if ($vars['inactivedescription'][5] || !strlen($vars['description'][5]))
                                    if ($vars['inactivedescription'][6] || !strlen($vars['description'][6]))
                                        if ($vars['inactivedescription'][1] || !strlen($vars['description'][1]))
        ;//return PEAR::raiseError( 'No active description', 101 );
                                        else $DescrNum = 1;
                                    else $DescrNum = 6;
                                else $DescrNum = 5;
                            else $DescrNum = 4;
                        else $DescrNum = 3;
                    else $DescrNum = 2;
                    break;
               case 2:
                    if ($vars['inactivedescription'][3] || !strlen($vars['description'][3]))
                        if ($vars['inactivedescription'][4] || !strlen($vars['description'][4]))
                            if ($vars['inactivedescription'][5] || !strlen($vars['description'][5]))
                                if ($vars['inactivedescription'][6] || !strlen($vars['description'][6]))
                                    if ($vars['inactivedescription'][1] || !strlen($vars['description'][1]))
                                        if ($vars['inactivedescription'][2] || !strlen($vars['description'][2]))
        ;//return PEAR::raiseError( 'No active description', 101 );
                                        else $DescrNum = 2;
                                    else $DescrNum = 1;
                                else $DescrNum = 6;
                            else $DescrNum = 5;
                        else $DescrNum = 4;
                    else $DescrNum = 3;
                    break;
               case 3:
                    if ($vars['inactivedescription'][4] || !strlen($vars['description'][4]))
                        if ($vars['inactivedescription'][5] || !strlen($vars['description'][5]))
                            if ($vars['inactivedescription'][6] || !strlen($vars['description'][6]))
                                if ($vars['inactivedescription'][1] || !strlen($vars['description'][1]))
                                    if ($vars['inactivedescription'][2] || !strlen($vars['description'][2]))
                                        if ($vars['inactivedescription'][3] || !strlen($vars['description'][3]))
        ;//return PEAR::raiseError( 'No active description', 101 );
                                        else $DescrNum = 3;
                                    else $DescrNum = 2;
                                else $DescrNum = 1;
                            else $DescrNum = 6;
                        else $DescrNum = 5;
                    else $DescrNum = 4;
                    break;
               case 4:
                    if ($vars['inactivedescription'][5] || !strlen($vars['description'][5]))
                        if ($vars['inactivedescription'][6] || !strlen($vars['description'][6]))
                            if ($vars['inactivedescription'][1] || !strlen($vars['description'][1]))
                                if ($vars['inactivedescription'][2] || !strlen($vars['description'][2]))
                                    if ($vars['inactivedescription'][3] || !strlen($vars['description'][3]))
                                        if ($vars['inactivedescription'][4] || !strlen($vars['description'][4]))
        ;//return PEAR::raiseError( 'No active description', 101 );
                                        else $DescrNum = 4;
                                    else $DescrNum = 3;
                                else $DescrNum = 2;
                            else $DescrNum = 1;
                        else $DescrNum = 6;
                    else $DescrNum = 5;
                    break;
               case 5:
                    if ($vars['inactivedescription'][6] || !strlen($vars['description'][6]))
                        if ($vars['inactivedescription'][1] || !strlen($vars['description'][1]))
                            if ($vars['inactivedescription'][2] || !strlen($vars['description'][2]))
                                if ($vars['inactivedescription'][3] || !strlen($vars['description'][3]))
                                    if ($vars['inactivedescription'][4] || !strlen($vars['description'][4]))
                                        if ($vars['inactivedescription'][5] || !strlen($vars['description'][5]))
        ;//return PEAR::raiseError( 'No active description', 101 );
                                        else $DescrNum = 5;
                                    else $DescrNum = 4;
                                else $DescrNum = 3;
                            else $DescrNum = 2;
                        else $DescrNum = 1;
                    else $DescrNum = 6;
                    break;
               case 6:
                    if ($vars['inactivedescription'][1] || !strlen($vars['description'][1]))
                        if ($vars['inactivedescription'][2] || !strlen($vars['description'][2]))
                            if ($vars['inactivedescription'][3] || !strlen($vars['description'][3]))
                                if ($vars['inactivedescription'][4] || !strlen($vars['description'][4]))
                                    if ($vars['inactivedescription'][5] || !strlen($vars['description'][5]))
                                        if ($vars['inactivedescription'][6] || !strlen($vars['description'][6]))
        ;//return PEAR::raiseError( 'No active description', 101 );
                                        else $DescrNum = 6;
                                    else $DescrNum = 5;
                                else $DescrNum = 4;
                            else $DescrNum = 3;
                        else $DescrNum = 2;
                    else $DescrNum = 1;
                    break;
                default:
                    if ($vars['inactivedescription'][1] || !strlen($vars['description'][1]))
                        if ($vars['inactivedescription'][2] || !strlen($vars['description'][2]))
                            if ($vars['inactivedescription'][3] || !strlen($vars['description'][3]))
                                if ($vars['inactivedescription'][4] || !strlen($vars['description'][4]))
                                    if ($vars['inactivedescription'][5] || !strlen($vars['description'][5]))
                                        if ($vars['inactivedescription'][6] || !strlen($vars['description'][6]))
        ;//return PEAR::raiseError( 'No active description', 101 );
                                        else $DescrNum = 6;
                                    else $DescrNum = 5;
                                else $DescrNum = 4;
                            else $DescrNum = 3;
                        else $DescrNum = 2;
                    else $DescrNum = 1;
                    break;
            }// switch

            $lastRicardoDescrNum = $lastvars['DescrNumRicardo'];
            if (!$lastRicardoDescrNum) $lastRicardoDescrNum=1;
            switch ($lastRicardoDescrNum) {
               case 1:
                    if ($vars['inactivedescriptionRicardo'][2])
                        if ($vars['inactivedescriptionRicardo'][3])
                            if ($vars['inactivedescriptionRicardo'][4])
                                if ($vars['inactivedescriptionRicardo'][5])
                                    if ($vars['inactivedescriptionRicardo'][6])
                                        if ($vars['inactivedescriptionRicardo'][1])
        ;//return PEAR::raiseError( 'No active RICARDO description', 102 );
                                        else $DescrNumRicardo = 1;
                                    else $DescrNumRicardo = 6;
                                else $DescrNumRicardo = 5;
                            else $DescrNumRicardo = 4;
                        else $DescrNumRicardo = 3;
                    else $DescrNumRicardo = 2;
                    break;
               case 2:
                    if ($vars['inactivedescriptionRicardo'][3])
                        if ($vars['inactivedescriptionRicardo'][4])
                            if ($vars['inactivedescriptionRicardo'][5])
                                if ($vars['inactivedescriptionRicardo'][6])
                                    if ($vars['inactivedescriptionRicardo'][1])
                                        if ($vars['inactivedescriptionRicardo'][2])
        ;//return PEAR::raiseError( 'No active RICARDO description', 102 );
                                        else $DescrNumRicardo = 2;
                                    else $DescrNumRicardo = 1;
                                else $DescrNumRicardo = 6;
                            else $DescrNumRicardo = 5;
                        else $DescrNumRicardo = 4;
                    else $DescrNumRicardo = 3;
                    break;
               case 3:
                    if ($vars['inactivedescriptionRicardo'][4])
                        if ($vars['inactivedescriptionRicardo'][5])
                            if ($vars['inactivedescriptionRicardo'][6])
                                if ($vars['inactivedescriptionRicardo'][1])
                                    if ($vars['inactivedescriptionRicardo'][2])
                                        if ($vars['inactivedescriptionRicardo'][3])
        ;//return PEAR::raiseError( 'No active RICARDO description', 102 );
                                        else $DescrNumRicardo = 3;
                                    else $DescrNumRicardo = 2;
                                else $DescrNumRicardo = 1;
                            else $DescrNumRicardo = 6;
                        else $DescrNumRicardo = 5;
                    else $DescrNumRicardo = 4;
                    break;
               case 4:
                    if ($vars['inactivedescriptionRicardo'][5])
                        if ($vars['inactivedescriptionRicardo'][6])
                            if ($vars['inactivedescriptionRicardo'][1])
                                if ($vars['inactivedescriptionRicardo'][2])
                                    if ($vars['inactivedescriptionRicardo'][3])
                                        if ($vars['inactivedescriptionRicardo'][4])
        ;//return PEAR::raiseError( 'No active RICARDO description', 102 );
                                        else $DescrNumRicardo = 4;
                                    else $DescrNumRicardo = 3;
                                else $DescrNumRicardo = 2;
                            else $DescrNumRicardo = 1;
                        else $DescrNumRicardo = 6;
                    else $DescrNumRicardo = 5;
                    break;
               case 5:
                    if ($vars['inactivedescriptionRicardo'][6])
                        if ($vars['inactivedescriptionRicardo'][1])
                            if ($vars['inactivedescriptionRicardo'][2])
                                if ($vars['inactivedescriptionRicardo'][3])
                                    if ($vars['inactivedescriptionRicardo'][4])
                                        if ($vars['inactivedescriptionRicardo'][5])
        ;//return PEAR::raiseError( 'No active RICARDO description', 102 );
                                        else $DescrNumRicardo = 5;
                                    else $DescrNumRicardo = 4;
                                else $DescrNumRicardo = 3;
                            else $DescrNumRicardo = 2;
                        else $DescrNumRicardo = 1;
                    else $DescrNumRicardo = 6;
                    break;
               case 6:
                    if ($vars['inactivedescriptionRicardo'][1])
                        if ($vars['inactivedescriptionRicardo'][2])
                            if ($vars['inactivedescriptionRicardo'][3])
                                if ($vars['inactivedescriptionRicardo'][4])
                                    if ($vars['inactivedescriptionRicardo'][5])
                                        if ($vars['inactivedescriptionRicardo'][6])
        ;//return PEAR::raiseError( 'No active RICARDO description', 102 );
                                        else $DescrNumRicardo = 6;
                                    else $DescrNumRicardo = 5;
                                else $DescrNumRicardo = 4;
                            else $DescrNumRicardo = 3;
                        else $DescrNumRicardo = 2;
                    else $DescrNumRicardo = 1;
                    break;
                default:
                    if ($vars['inactivedescriptionRicardo'][1])
                        if ($vars['inactivedescriptionRicardo'][2])
                            if ($vars['inactivedescriptionRicardo'][3])
                                if ($vars['inactivedescriptionRicardo'][4])
                                    if ($vars['inactivedescriptionRicardo'][5])
                                        if ($vars['inactivedescriptionRicardo'][6])
        ;//return PEAR::raiseError( 'No active RICARDO description', 102 );
                                        else $DescrNumRicardo = 6;
                                    else $DescrNumRicardo = 5;
                                else $DescrNumRicardo = 4;
                            else $DescrNumRicardo = 3;
                        else $DescrNumRicardo = 2;
                    else $DescrNumRicardo = 1;
                    break;
            }// switch
        } // f there was prev auctions
    } //for saved
    if (!$DescrNum) $DescrNum=1;
    $vars['DescrNum'] = $DescrNum;
//    echo $DescrNum; die();
    if (!$DescrNumRicardo) $DescrNumRicardo=1;
    $vars['DescrNumRicardo'] = $DescrNumRicardo;

    $Description = $vars['description'][$DescrNum];
    if (!strlen(trim($Description))) return false;
//	$la_banner = file_get_contents($siteURL.'advs.php');
//	$la_banner = file_get_contents($siteURL.'auction_scroller_'.$vars['username'].'/index.html');
//	$la_banner = '';
//	echo '<br><br>'.$siteURL.'auction_scroller_'.$vars['username'].'/index.html'; die($la_banner);
    global $smarty;
    $la_bannerA = $smarty->fetch('scrollA.tpl');
    $auctions = $dbr->getAll("select * from advs_cache where seller_username='".$vars['username']."'");
    $english = Auction::getTranslation($db, $dbr, $vars['siteid'], $info->get('default_lang'));
    $smarty->assign('english', $english);
    $smarty->assign('auctions', $auctions);
    $la_bannerB = $smarty->fetch('scrollB.tpl');
    $smarty->assign('username', $vars['username']);
    $la_banner = $smarty->fetch('advs_flash_gallery.tpl');
    $Description = $info->get('ebay_descr_header').$info->get('ebay_descr_left').$Description.$info->get('ebay_descr_right');
    $Description .= $info->get('information_zur_lieferung').'
    '.$info->get('warum_sie_bei_uns_kaufen_solten');
    switch($info->get('banner_pos')) {
        case 'top':
            if (strpos($Description, '<body>')) $Description = str_replace('<body>', "<body>".$la_banner, $Description);
            else $Description = $la_banner.$Description;
        break;
        case 'bottom':
            if (strpos($Description, '</body>')) $Description = str_replace('</body>', $la_banner."</body>", $Description);
            else $Description = $Description.$la_banner;
        break;
        case 'both':
            if (strpos($Description, '<body>')) $Description = str_replace('<body>', "<body>".$la_banner, $Description);
            else $Description = $la_banner.$Description;
            if (strpos($Description, '</body>')) $Description = str_replace('</body>', $la_banner."</body>", $Description);
            else $Description = $Description.$la_banner;
        break;
    }
    //$Description = "<![CDATA[".$Description."]]>"; // sometimes we need it sometimes not, dont know the reason why
    $currCode = siteToSymbol ($vars['siteid'], 'value');
    if ((int)$offer->get('available_weeks')) {
        $available_date = "date_add(NOW(), INTERVAL ".(int)$offer->get('available_weeks')." week)";
    } else {
        $available_date = "'".$offer->get('available_date')."'";
    }
    $q = "select text_value
        from saved_available
        where channel_id=1
        and upto>IFNULL(datediff($available_date, now()),0)
        order by upto limit 1";
    $vars['DispatchTimeMax'] = $dbr->getOne($q);
    if ($vars['siteid']==77) $offer_limit = Config::get($db, $dbr, 'offer_limit_ebayDE');
    else $offer_limit = Config::get($db, $dbr, 'offer_limit_ebay');
#	$Description = '<font style="font-size:1px">'.date("Y-m-d H:i:s").'</font>'
#		.$Description.'<font style="font-size:2px">'.date("H:i:s Y-m-d").'</font>';
//	$Description = "<![CDATA[ $Description ]]>";
    $params = array(
        'Item' => array (
                    'ProductListingDetails' => array (
                        'EAN' => $vars['ean_code']
                    ),
                    'PrimaryCategory' => array (
                        'CategoryID' => $vars['category']
                    ),
                    'Site' => $vars['siteid']=='3'?'UK':($vars['siteid']=='0'?'US':CountryCodeToCountry(SiteToCountryCode($vars['siteid']))),
                    'Location' => $info->get('location'),// $vars['location'],
                    'Country'=>  $info->get('ebaycountry')=='UK' || $info->get('ebaycountry')=='U2'?'GB': (($info->get('ebaycountry') && strlen($info->get('ebaycountry')))  ? $info->get('ebaycountry') : 'DE'),
                        //($vars['country'] && strlen($vars['country']))  ? $vars['country'] : 'DE',
                    'Currency'=> siteToSymbol($vars['siteid']),
                    'Quantity'=>1,
                    'StartPrice'=> array_merge(array('__attrs__' => array('currencyID' => $currCode)),
                        (array)number_format($vars['startprice'], 2, '.', '')),
                    'ListingDuration'=> $vars['duration']?('Days_'.$vars['duration']):'GTC',
                    'RegionID'=> (int)$info->get('region'), //$vars['region'],
//			        'Title'=>$vars['name'],
                    'Title'=>mb_substr($vars['name'],0,$offer_limit, 'utf-8'),
                    'PrivateListing'=>$vars['private'] ? 1 : 0,
                    'DispatchTimeMax'=> (int)$vars['DispatchTimeMax'],
                    'Description'=>$Description
        )
//        'Condition'=>$vars['Condition'],
//        'Guarantee'=>$vars['Guarantee'],
    );
    if ($revise_auction_number && !strlen($params['Item']['Title'])) unset($params['Item']['Title']);
//	print_r($vars['description']);
//	$params['DescrNum']=$DescrNum;
    if ((int)$vars['StoreCategoryID'] || (int)$vars['StoreCategory2ID']) {
        $params['Item']['Storefront']['StoreCategoryID'] = $vars['StoreCategoryID'];
        $params['Item']['Storefront']['StoreCategory2ID'] = $vars['StoreCategory2ID'];
    }
    if (!(int)$vars['subtitleinactive']) {
        $params['Item']['SubTitle'] = substr($vars['subtitle'], 0, 60);
    }
    if ($vars['BuyItNowPrice'] && !$vars['fixedprice']) {
        $params['Item']['BuyItNowPrice'] = array_merge(array('__attrs__' => array('currencyID' => $currCode)),
                        (array)number_format($vars['BuyItNowPrice'], 2, '.', ''));
    }

    if ($vars['VATPercent']) {
        $params['Item']['VATDetails']['BusinessSeller'] = true;
        $params['Item']['VATDetails']['VATPercent'] = number_format($vars['VATPercent'], 2, '.', '');
    }

    $insurance = $vars['Insurance'];
    $insurancecost = sprintf('%0.2f', $vars['InsuranceCost']);
    if ($insurance > 0) {
        $params['Item']['ShippingDetails'] = array (
                'InsuranceOption' => $insurance,
                'InsuranceFee' => $insurancecost
                );
    }

    $service = $vars['ShippingService'];
    $cost = sprintf('%0.2f', $vars['ShippingCost']);
    $costAdd = sprintf('%0.2f', $vars['ShippingCostAdditional']);
    if ((float)$vars['CODCost']>0) $params['Item']['ShippingDetails']['CODCost'] = $vars['CODCost'];
    $countries = array();
    foreach($vars['ShipToLocations'] as $country_code) $countries[]=$country_code;

    if ($vars['fixedprice']) $type = 'f'; else $type = '';
    $shipping_plan_id = $dbr->getOne("SELECT value
                FROM translation
                WHERE table_name = 'offer'
                AND field_name = '".$type."shipping_plan_id'
                AND language = '".$vars['siteid']."'
                AND id = ".$vars['offer_id']);
    $shipping_plan_free = $dbr->getOne("SELECT value
                FROM translation
                WHERE table_name = 'offer'
                AND field_name = '".$type."shipping_plan_free_tr'
                AND language = '".$vars['siteid']."'
                AND id = ".$vars['offer_id']);
    $offer = new Offer($db, $dbr, $vars['offer_id']);
    if ($offer->get($type.'shipping_plan_free')) $shipping_plan_free = 1;
    if ($info->get('free_shipping') || $info->get('free_shipping_total')) $shipping_plan_free = 1;

    $q = "select shipping_cost
                                from saved_auctions sa
                join saved_params sp_offer on sa.id=sp_offer.saved_id and sp_offer.par_key='offer_id'
                join saved_params sp_username on sa.id=sp_username.saved_id and sp_username.par_key='username'
                join saved_params sp_site on sa.id=sp_site.saved_id and sp_site.par_key='siteid'
                join seller_information si on si.username=sp_username.par_value
                                join translation
                                    on language=sp_site.par_value
                                    and translation.id=sp_offer.par_value
                                    and table_name='offer' and field_name='".$type."shipping_plan_id'
                                join shipping_plan_country spc on shipping_plan_id=translation.value
                                and spc.country_code = si.defshcountry
                                where sa.id=".$vars['saved_id'];
    $shipping_cost_seller = $dbr->getOne($q);

    if (count($countries)) $params['Item']['ShipToLocations'] = $countries;
    /*if ($service) */{
         foreach($vars['ShippingContainer'] as $k1=>$r1) {
            $cost = sprintf('%0.2f', $shipping_cost_seller/*$r1['ShippingCost']*/);
            $costAdd = sprintf('%0.2f', $shipping_cost_seller/*$r1['ShippingCostAdditional']*/);
            $countries = array();
            $params['Item']['ShippingDetails']['ShippingServiceOptions'][] = array(
                    'ShippingServiceCost'=>array_merge(array('__attrs__' => array('currencyID' => $currCode)),
                        (array)number_format($shipping_plan_free?0:$cost, 2, '.', '')),
                    'ShippingServiceAdditionalCost'=>array_merge(array('__attrs__' => array('currencyID' => $currCode)),
                        (array)number_format($shipping_plan_free?0:$costAdd, 2, '.', '')),
                    'ShippingService' => $r1['ShippingService'],
                    'ShippingServicePriority' => 1,
//					'FreeShipping' => isset($r1['FreeShipping'])?1:0,
             );
         }
         foreach($vars['InternationalShippingContainer'] as $k1=>$r1) {
            $cost = sprintf('%0.2f', $shipping_cost_seller/*$r1['InternationalShippingCost']*/);
            $costAdd = sprintf('%0.2f', $shipping_cost_seller/*$r1['InternationalShippingCostAdditional']*/);
            $countries = [];
            foreach($r1['InternationalShippingCountry'] as $country_code) $countries[]=$country_code;
            $q = "select shipping_cost
                                        from saved_auctions sa
                        join saved_params sp_offer on sa.id=sp_offer.saved_id and sp_offer.par_key='offer_id'
                        join saved_params sp_username on sa.id=sp_username.saved_id and sp_username.par_key='username'
                        join saved_params sp_site on sa.id=sp_site.saved_id and sp_site.par_key='siteid'
                        join seller_information si on si.username=sp_username.par_value
                                        join translation
                                            on language=sp_site.par_value
                                            and translation.id=sp_offer.par_value
                                            and table_name='offer' and field_name='".$type."shipping_plan_id'
                                        join shipping_plan_country spc on shipping_plan_id=translation.value
                                        and spc.country_code = '".$countries[0]."'
                                        where sa.id=".$vars['saved_id'];
            $int_cost = $dbr->getOne($q);
//			print_r($countries); echo '$int_cost='.$int_cost.' $shipping_cost_seller='.$shipping_cost_seller; die($q);
            if ($shipping_plan_free) $int_cost -= $shipping_cost_seller;
            $cost = sprintf('%0.2f', $int_cost);
            $costAdd = sprintf('%0.2f', $int_cost);

            if ($countries) {
                $params['Item']['ShippingDetails']['InternationalShippingServiceOption'][] = [
                        'ShippingServiceCost'=>$cost,
                        'ShippingServiceAdditionalCost'=>$costAdd,
                        'ShippingService' => $r1['InternationalShippingService'],
                        'ShippingServicePriority' => 1,
                        'ShipToLocation' => $countries,
                ];
            }
         }
        $params['Item']['ShippingDetails']['ShippingType'] = 'Flat';//1;
//        $params['ShippingHandlingCosts'] = $cost;
        $params['Item']['ShippingDetails']['ChangePaymentInstructions'] = 1;
    }
    if ($category2 && !(int)$vars['category2_inactive']) {
        $params['Item']['SecondaryCategory']['CategoryID'] = $vars['category2'];
    }
    if ($vars['gallery']) {
        $local_id = $dbr->getOne("select id from gallery_lib where URL='".mysql_escape_string($vars['gallery'])."'");
//        $localURL = $siteURL.'gallerylib/'.$local_id.'.jpg';
        $localURL = mysql_escape_string($vars['gallery']);
       $localURL = trim(str_replace(' ', '%20', $localURL));
//        $params['Item']['VendorHostedPicture']['GalleryURL'] = $localURL;
        $PictureDetails = array();
        $PictureDetails['GalleryURL'] = $localURL;
        $PictureDetails['PictureURL'] = array();
        $PictureDetails['PictureURL'][] = $localURL;
        if ($vars['gal_featured']) {
//            $params['Item']['VendorHostedPicture']['GalleryType'] = 'Featured';
            $PictureDetails['GalleryType'] = 'Featured';
        } elseif ($vars['gal_plus']) {
//            $params['Item']['VendorHostedPicture']['GalleryType'] = 'Featured';
            $PictureDetails['GalleryType'] = 'Plus';
        } else {
  //          $params['Item']['VendorHostedPicture']['GalleryType'] = 'Gallery';
            $PictureDetails['GalleryType'] = 'Gallery';
        }
        if ($master_sa = (int)$vars['master_sa']) {
            require_once 'plugins/function.imageurl.php';
            global $smarty;
            $pics = $dbr->getAll("select doc_id, pic_type
                            from saved_master_pics
                            where saved_id=".(int)$vars['saved_id']."
                            ORDER BY ordering");
            foreach($pics as $pic) {
                $PictureDetails['PictureURL'][] = 'https://www.beliani.ch'.smarty_function_imageurl([
                    'src' => 'sa',
                    'picid'=>$pic->doc_id,
                    'type'=>$pic->pic_type,
                    'nochange'=>1], $smarty);
            }
        }
        $params['Item']['PictureDetails'] = $PictureDetails;
    }
    if ($vars['picture']) {
       $vars['picture'] = str_replace(' ', '%20', $vars['picture']);
//        $params['Item']['VendorHostedPicture']['SelfHostedURL'] = $vars['picture'];
//        $params['Item']['VendorHostedPicture']['PictureURL'] = $vars['picture'];
        $params['Item']['PictureDetails']['PictureURL'][] = $localURL;//$vars['picture'];
    }
//	if ($revise_auction_number && !strlen($params['Item']['PictureDetails']['PictureURL'])) unset($params['Item']['PictureDetails']['PictureURL']);

    if ($vars['bold']) {
        $params['Item']['ListingEnhancement'][] = 'BoldTitle';
    }
    if ($vars['highlight']) {
        $params['Item']['ListingEnhancement'][] = 'Highlight';
    }
    if ($vars['super']) {
        $params['Item']['ListingEnhancement'][] = 'HomePageFeatured';
    }
    if ($vars['featured']) {
        $params['Item']['ListingEnhancement'][] = 'Featured';
    }
    if ($vars['start']) {
        $params['Item']['ScheduleTime'] = $vars['Date_Year'].'-'.$vars['Date_Month'].'-'.$vars['Date_Day'].' '.$vars['Time_Hour'].':'.$vars['Time_Minute'].':'.$vars['Time_Second'];
    }
    if (is_array($vars['payment'])) {
        foreach ($vars['payment'] as $param => $value) {
            if ($param=='PayPal') {
                if (strlen(trim($info->get('paypal_ebay_email')))) {
                   $params['Item']['PayPalEmailAddress'] = $info->get('paypal_ebay_email'); //$value;
                }
            }
            if ($value)
                $params['Item']['PaymentMethods'][] = $param;
        }
        if (europeOnly($vars['siteid']) && $vars['payment']['ShippingOption']=='SitePlusRegions') {
//            $params['ShipToEurope'] = 1;
        }
    }
    if ($vars['reserveprice']) {
        $params['Item']['ReservePrice'] = number_format($vars['reserveprice'], '.', '');
    }
    $vars['quantity'] = max((int)$vars['quantity'], 0); // >= 0
    $params['Item']['ListingType'] = 'Chinese';//1
    if ($vars['fixedprice']) {
        // Fixed price listing
        $params['Item']['ListingType'] = 'FixedPriceItem';//9;
        $params['Item']['Quantity'] = $vars['quantity'];
        $params['Variation']['Variation']['Quantity'] = $vars['quantity'];
        if ($vars['nownew']) {
            $params['Item']['NowAndNew'] = 1;
            if ($vars['buynowprice']) {
                $params['Item']['BuyItNowPrice'] = array_merge(array('__attrs__' => array('currencyID' => $currCode)),
                    (array)number_format($vars['buynowprice'], '.', ''));
            }
        }
    } elseif ($vars['quantity'] > 1) {
        // Dutch auction
        $params['Item']['ListingType'] = 'Dutch';//2;
        $params['Item']['Quantity'] = $vars['quantity'];
    }
    if ($vars['BestOfferEnabled']) {
        $params['Item']['BestOfferDetails']['BestOfferEnabled'] = true;
        if ($vars['BestOfferAutoAcceptPrice']) {
            $params['Item']['ListingDetails']['BestOfferAutoAcceptPrice'] = array_merge(array('__attrs__' => array('currencyID' => $currCode)),
                    (array)($vars['BestOfferAutoAcceptPrice']));
            $params['Item']['ListingDetails']['MinimumBestOfferPrice'] = array_merge(array('__attrs__' => array('currencyID' => $currCode)),
                    (array)($vars['BestOfferAutoAcceptPrice']));
        }
/*	    if ($vars['MinimumBestOfferPrice']) {
            $params['Item']['ListingDetails']['MinimumBestOfferPrice'] = array_merge(array('__attrs__' => array('currencyID' => $currCode)),
                (array)number_format($vars['MinimumBestOfferPrice'], '.', ''));
        }*/
    }
    if (1 || $vars['use_CategorySpecific']) {
//		print_r($vars['sp']); die();
        $params['Item']['ItemSpecifics']['NameValueList'] = array();
//		echo 'var_sp='; print_r($vars['sp']);
        foreach($vars['sp'] as $name=>$values) {
            $deleted = $dbr->getOne("select count(*) from Sp_Names_Deleted
                where NameID=$name and saved_id=".$vars['saved_id']." #and categoryid=".$vars['category']);
            if ($deleted) continue;
            $values2send = array();
            foreach($values as $value_id=>$value) {
                if (strlen($value)) {
                    $values2send[] = $value;
                }
            } // foreach value
            $name = $dbr->getOne("select Name from Sp_Names where id=$name");
            $namevalue = array();
            if (count($values2send)>1) {
                $namevalue['Name'] = $name;
                $namevalue['Value'] = $values2send[0]; //$values2send
            } elseif (count($values)>=1) {
                $namevalue['Name'] = $name;
                $namevalue['Value'] = $values2send[0];
            }
            $params['Item']['ItemSpecifics']['NameValueList'][] = str_replace('&','&amp;',$namevalue);
        } // foreach name
//		echo 'NameValueList='; print_r($params['Item']['ItemSpecifics']['NameValueList']);
    } else {
        $suffixes = array('', 2);
        foreach ($suffixes as $suf) {
            if ($vars['input' . $suf]) {
                $asets = array();
                $input = $vars['input' . $suf];
                $w = WidgetBuilder::Build($db, $dbr, $vars['category' . $suf], $input, $vars['siteid']);
                foreach ($w as $widget) {
                    $attr = array(
                        '__attrs__' => array('attributeID' => $widget->id),
                    //    '__attrs__' => array(),
                    );
                    $values = array();
                    if ($widget->input == 'dropdown') {
                        if ($input[$widget->id] == -6) {
                            if (strlen($input['other'][$widget->id])) {
                                $values[] = array(
                                    'ValueID' => -6,
                                    'ValueLiteral' => $input['other'][$widget->id],
                                );
                            }
                        } else {
                            $values[] = array(
                                'ValueID' => $input[$widget->id],
                                'ValueLiteral' => $widget->values[$input[$widget->id]]->value//array('__attrs__' => array()),
                            );
                        }
                    } elseif ($widget->input == 'textfield' || $widget->input == 'collapsible_textarea'
                        || $widget->input == '') {
                        if (strlen($input[$widget->id])) {
                            $values[] = array(
                                'ValueID' => -3,
                                'ValueLiteral' => $input[$widget->id],
                            );
                        }
                    } elseif ($widget->input == 'radio') {
                        if(isset($input[$widget->id])) {
                            $values[] = array(
                                'ValueID' => $input[$widget->id],
                                'ValueLiteral' => $input[$widget->id]//'radio'//array('__attrs__' => array()),
                            );
                        }
                    } elseif ($widget->input == 'checkbox') {
                        foreach ($widget->values as $value) {
                            if ($input[$widget->id][$value->id]) {
                                $values[] = array(
                                    'ValueID' => $value->id,
                                    'ValueLiteral' => $value->id//'checkbox'//array('__attrs__' => array()),
                                );
                            }
                        }
                    }
                    if ($values) {
                        $attr['Value'] = $values;
                        $asets[$widget->attrset]['Attribute'][] = $attr;
                    }
                }
                foreach ($asets as $setid => $aset) {
                    $asetid = array('__attrs__' => array('attributeSetID' => $setid));
                    $aset = array_merge($asetid, (array)$aset);
                    $params['Item']['AttributeSetArray']['AttributeSet'][] = $aset;
                }
            }
        }
    } // if no category specific
    if ((int)$vars['ConditionID1']) {
        $params['Item']['ConditionID'] = (int)$vars['ConditionID1'];
    } elseif ((int)$vars['ConditionID2']) {
        $params['Item']['ConditionID'] = (int)$vars['ConditionID2'];
    }
    if ($vars['use_ReturnPolicy']) {
        foreach($vars['ReturnPolicy'] as $token=>$value) {
            if (strlen($value)) {
                $params['Item']['ReturnPolicy'][$token] = $value;
            }
        } // foreach token
    } else {
        foreach ($suffixes as $suf) {
            if ($vars['input_SW'. $suf]) {
                $asets = array();
                $input = $vars['input_SW'. $suf];
                $w = WidgetBuilder::SiteWideBuild($db, $dbr, $vars['category'. $suf], $input, $vars['siteid']);
                foreach ($w as $widget) {
    #				if (isset($vars['input_SW'][$widget->id]) && isset($vars['input_SW2'][$widget->id]))
                    {
                        $attr = array(
                            '__attrs__' => array('attributeID' => $widget->id),
                        //    '__attrs__' => array(),
                        );
                        $values = array();
                        if ($widget->input == 'dropdown') {
                            if ($input[$widget->id] == -6) {
                                if (strlen($input['other'][$widget->id])) {
                                    $values[] = array(
                                        'ValueID' => -6,
                                        'ValueLiteral' => $input['other'][$widget->id],
                                    );
                                }
                            } else {
                                $values[] = array(
                                    'ValueID' => $input[$widget->id],
                                    'ValueLiteral' => $widget->values[$input[$widget->id]]->value//array('__attrs__' => array()),
                                );
                            }
                        } elseif ($widget->input == 'textfield' || $widget->input == 'collapsible_textarea'
                            || $widget->input == '') {
                            if (strlen($input[$widget->id])) {
                                $values[] = array(
                                    'ValueID' => -3,
                                    'ValueLiteral' => $input[$widget->id],
                                );
                            }
                        } elseif ($widget->input == 'radio') {
                            if(isset($input[$widget->id])) {
                                $values[] = array(
                                    'ValueID' => $input[$widget->id],
                                    'ValueLiteral' => $input[$widget->id]//'radio'//array('__attrs__' => array()),
                                );
                            }
                        } elseif ($widget->input == 'checkbox') {
                            foreach ($widget->values as $value) {
                                if ($input[$widget->id][$value->id]) {
                                    $values[] = array(
                                        'ValueID' => $value->id,
                                        'ValueLiteral' => $value->id//'checkbox'//array('__attrs__' => array()),
                                    );
                                }
                            }
                        }
                        if ($values) {
                            $attr['Value'] = $values;
                            $asets[$widget->attrset]['Attribute'][] = $attr;
                        }
                    }
                    foreach ($asets as $setid => $aset) {
                        $asetid = array('__attrs__' => array('attributeSetID' => $setid));
                        $aset = array_merge($asetid, (array)$aset);
                        $params['Item']['AttributeSetArray']['AttributeSet'][] = $aset;
                    }
                } // foreach widget
            } // if widgets
        } // foreach suffixes
    }
    $asids = array();
    foreach ($params['Item']['AttributeSetArray']['AttributeSet'] as $askey => $as) {
        if (in_array($as['__attrs__']['attributeSetID'], $asids)) {
            unset($params['Item']['AttributeSetArray']['AttributeSet'][$askey]);
        } else {
            $asids[] = $as['__attrs__']['attributeSetID'];
        }
    }
/*    $params['Item']['ShippingDetails']['ShippingServiceOptions']['ShippingServiceCost']
                = array_merge(array('__attrs__' => array('currencyID' => 'EUR')),
                        number_format(10, 2));

     aprint_r($params);*/
/* 	require_once 'XML/Serializer.php';
    $opts = array(
                         'indent'             => '  ',
                         'linebreak'          => "\n",
                         'typeHints'          => false,
                         'addDecl'            => true,
                         'scalarAsAttributes' => false,
                         'encoding' => 'utf-8',
                        'attributesArray' => '__attrs__',
//                         'rootName'           => 'request',
                         'mode' => 'simplexml',
                         'rootAttributes'     => array( 'xmlns' => 'urn:ebay:apis:eBLBaseComponents' )
                    );
    $_ser   =new XML_Serializer( $opts );
    $_ser->serialize($params, $opt);
    $ret = $_ser->getSerializedData();
    echo $ret;*/
//	print_r($params);
//	die();
//    $verify = 1;
    echo 'revise_auction_number='.$revise_auction_number.'<br>';
    if ($revise_auction_number) {
        $params['Item']['ItemID'] = $revise_auction_number;
        $item = $eBay->ReviseItem($params, 0);
    } elseif ($verify) {
        $item = $eBay->VerifyAddItem($params, 0);
    } else {
        $item = $eBay->AddItem($params, 0);
    }
//	echo 'Result item:';print_r($item); //die();
    if (PEAR::isError($item)) {
        aprint_r($vars);
        aprint_r($item);
        if ($vars['saved_id']==228) {
//			print_r($vars);
//			print_r($item);
        }
    };
    if (!PEAR::isError($item) && !$verify) {
        $total_fees = 0;
        foreach ($item['Fees']['Fee'] as $fee) {
            if ($fee['Name']=='ListingFee') $total_fees = $fee['Fee'];
        };
        if ($params['Item']['ListingType'] == 'Chinese') {
            if ($revise_auction_number) {
                $auction = new Auction($db, $dbr, $revise_auction_number, 1);
            } else {
                $auction = new Auction($db, $dbr);
                $auction->set('username', $vars['username']);
                $auction->set('auction_number', $item['ItemID']);
                $auction->set('txnid', 1);
                $auction->set('offer_id', $vars['offer_id']);
                $mail.='become '.$vars['offer_id'];
                $auction->set('siteid', $vars['siteid']);
            }
            $auction->set('end_time', GMTToLocal($item['EndTime'])/*, (int)Config::get($db, $dbr, 'server_to_GMT')*/);
               $auction->set('end_time1', GMTToLocal($item['EndTime']));
            $auction->set('start_time', GMTToLocal($item['StartTime']));
            $auction->set('start_time1', GMTToLocal($item['StartTime']));
            $auction->set('listing_fee', $total_fees/*$item['Fees']['ListingFee']*/);
            $auction->set('details', serialize($vars));
            $auction->set('params', serialize($params));
            $auction->set('server', $_SERVER['HTTP_HOST']);
            $auction->set('_SERVER', print_r($_SERVER,true));
            $auction->set('_REQUEST', print_r($_REQUEST,true));
            $auction->set('quantity', $vars['quantity']);
            $auction->set('item', print_r($item,true));
            if (!$vars['payment']['CashOnPickup']) {
                $auction->set('no_pickup', 1);
            }
            $auction->set('saved_id', $vars['saved_id']);
            $auction->set('name_id', $vars['name_id']);
            $auction->data->listing_fee1 = $auction->data->listing_fee;
            $auction->data->auction_number_prev = $item['ItemID'];
            $auction->update();
            echo 'Auction starty time saved as: '.$auction->get('start_time').'<br>';
            $db->query("insert into auction_payment_method (payment_method_id, auction_number, txnid, allow)
                select payment_method_id, ".$item['ItemID'].", 1, 1 from saved_payment_method where saved_id=".$vars['saved_id']);
        } else {
            if ($revise_auction_number) {
                $listing = new Listing($db, $dbr, $revise_auction_number);
            } else {
                $listing = new Listing($db, $dbr);
                $listing->set('username', $vars['username']);
                $listing->set('auction_number', $item['ItemID']);
                $listing->set('auction_type', $params['Type']);
                $listing->set('auction_type_vch', $params['Item']['ListingType']);
                $listing->set('offer_id', $vars['offer_id']);
                $listing->set('siteid', $vars['siteid']);
            }
            if ($vars['duration']) {
                $listing->set('end_time', GMTToLocal($item['EndTime'])/*, (int)Config::get($db, $dbr, 'server_to_GMT')*/);
                $listing->set('end_time1', GMTToLocal($item['EndTime']));
            } else {
                $listing->set('end_time', '2999-12-31');
                $listing->set('end_time1', '2999-12-31');
            }
            $listing->set('start_time', GMTToLocal($item['StartTime']));
            $listing->set('start_time1', GMTToLocal($item['StartTime']));
            $listing->set('listing_fee', $total_fees/*$item['Fees']['ListingFee']*/);
            $listing->set('details', serialize($vars));
            $listing->set('params', serialize($params));
            $listing->set('server', $_SERVER['HTTP_HOST']);
            $listing->set('quantity', $vars['quantity']);
            $listing->set('saved_id', $vars['saved_id']);
            $listing->set('name_id', $vars['name_id']);
            $listing->data->listing_fee1 = $listing->data->listing_fee;
            $listing->data->auction_number_prev = $item['ItemID'];
            if (!$vars['payment']['CashOnPickup']) {
                $listing->set('no_pickup', 1);
            }
            $listing->set('item', print_r($item,true));
            $listing->update();
            echo 'Auction starty time saved as: '.$listing->get('start_time').'<br>';
            if (PEAR::isError($listing)) {
                $_error = $listing;
            }
               $auction = $listing;
            $db->query("insert into auction_payment_method (payment_method_id, auction_number, txnid, allow)
                select payment_method_id, ".$item['ItemID'].", 0, 1 from saved_payment_method where saved_id=".$vars['saved_id']);
        }
        $db->query("insert into prologis_log.listing_log (dt, auction_number, txnid, server_host, server_script, php_function)
                values (
                    now(),
                    ".$item['ItemID'].",
                    NULL,
                    '".$_SERVER['HTTP_HOST']."',
                    '".$_SERVER['PHP_SELF']."',
                    'list_auction'
                )");
            echo 'GMT start time is: '.$item['StartTime'].'<br>';
            echo 'GMTToLocal is: '.GMTToLocal($item['StartTime']).'<br>';
            echo 'correction: '.(int)Config::get($db, $dbr, 'GMT_to_local').'<br>';
            echo 'Current server time is: '.date("Y-m-d H:i:s");
    }
    return $item;
}

function GMTToLocal($date)
{
    global $db, $dbr;
    list ($Y,$M,$D,$h,$m,$s) = preg_split('/[:\-\sT]+/', $date);
//	echo 'Was '.$date.' become '.date('Y-m-d H:i:s', mktime($h + (int)Config::get($db, $dbr, 'GMT_to_local'),$m,$s,$M,$D,$Y)).'<br>';
    return date('Y-m-d H:i:s', mktime($h + (int)Config::get($db, $dbr, 'GMT_to_local'),$m,$s,$M,$D,$Y));
}

function ServerToGMT($date)
{
    list ($Y,$M,$D,$h,$m,$s) = preg_split('/[:\-\sT]+/', $date);
    return date('Y-m-d H:i:s', mktime($h + (int)Config::get($db, $dbr, 'server_to_GMT'),$m,$s,$M,$D,$Y));
}

/**
 * @return string
 * @param string $date
 * @param int $diff Time difference in hours
 * @desc Converts server date/time to seller local date/time
*/
function serverToLocal($date, $diff)
{
    if (!strlen($date)) return;
    list ($Y,$M,$D,$h,$m,$s) = preg_split('/[:\-\sT]+/', $date);
//	echo "$Y,$M,$D,$h,$m,$s<br>";
//    return date('Y-m-d H:i:s', mktime($h-date("Z")/3600+$diff,$m,$s,$M,$D,$Y));
    $diff = 0;
    return date('Y-m-d H:i:s', mktime($h+$diff,$m,$s,$M,$D,$Y));
}

/**
 * @return string
 * @param string $date
 * @param int $diff Time difference in hours
 * @desc Converts seller local date/time to server date/time
*/
function localToServer($date, $diff)
{
    list ($Y,$M,$D,$h,$m,$s) = preg_split('/[:\-\sT]+/', $date);
    $diff = 0;
    return date('Y-m-d H:i:s', mktime($h-$diff,$m,$s,$M,$D,$Y));
//    return date('Y-m-d H:i:s', mktime($h+date("Z")/3600-$diff,$m,$s,$M,$D,$Y));
}


/**
 *
 *
 */
function mark_auction_as_shipped($db, $dbr, $auction, $user, $tnid=0, $send_shipping_confirm=true)
{
    global $loggedUser;
    require_once 'lib/Order.php';
    require_once 'lib/Offer.php';
    require_once 'lib/Rma.php';
    require_once 'lib/Article.php';
    require_once 'lib/ShippingMethod.php';
    require_once 'lib/ArticleHistory.php';
    // Drivers can only scann when route is completly prepared-closed!
    $route_id = $auction->data->route_id;
    if(!empty($route_id)){
        $route = $dbr->getRow("SELECT * FROM route WHERE id = {$route_id}");
        if(empty($route->closed)){
           return;
        }
    }
    $offer = new Offer($db, $dbr, $auction->get('offer_id'));
    $method = new ShippingMethod($db, $dbr, $offer->get('default_shipping_method'));
    $auction2send = new Auction($db, $dbr, $auction->get('auction_number'), $auction->get('txnid'));
    if ($method->get('shipping_email_delay') == 0) {
/**/    if ($send_shipping_confirm) {
            if (in_array($auction->get('payment_method'), array(3,4))) {
                standardEmail($db, $dbr, $auction2send, 'picked_up_details');
            } else {
                standardEmail($db, $dbr, $auction2send, 'shipping_details');
            }
        }
    }
    if (!$auction->get('no_emails')) {
        if (!count($auction2send->tracking_numbers))
            standardEmail($db, $dbr, $auction2send, 'send_SERVICEQUITTUNG');
    }
    $q = "select count(*) from orders where sent=0 and auction_number=".$auction->data->auction_number." and txnid=".$auction->data->txnid;
    file_put_contents('lastquery', $q);
    if ($dbr->getOne($q)) { return; }
    $q = "select fget_delivery_date_real_id(".$auction->data->auction_number.", ".$auction->data->txnid.")";
    $delivery_date_real_id = $dbr->getOne($q);
//	die('!!!'.$delivery_date_real_id);
    if (!$delivery_date_real_id) {
        return;
    }
    $info = new SellerInfo($db, $dbr, $auction->get('username'));

    if ($auction->data->payment_method == 'klr_shp') {
        klarna_activate($auction);
    }

    $q = "select u.username delivery_username, updated delivery_date_real
        from total_log tl
        left join users u on u.system_username=tl.username
        where tl.id=$delivery_date_real_id";
    $order_auction = $dbr->getRow($q);

    $auction->set('shipping_email_delay', $method->get('shipping_email_delay'));
    $auction->set('shipping_method', 1);
    $already_delivered = $auction->get('delivery_date_real')=='0000-00-00 00:00:00' ? false : true;
    $date = serverToLocal(date('Y-m-d H:i:s'), $loggedUser->get('timezone'));
    $auction->data->delivery_date_real = $order_auction->delivery_date_real;
    $auction->data->delivery_username = $user->get('username');
/*    $auction->set('shipping_document', requestVar('shipping_document', '', 'POST'));
    $auction->set('tracking_number', requestVar('tracking_number', '', 'POST'));
    $auction->set('shipping_comments', requestVar('shipping_comments', '', 'POST'));*/
    $auction->set('process_stage', STAGE_WAIT_FOR_RATING);
    $r = $auction->update();
    if (PEAR::isError($r)) {
       $msg = $auction->get('username').' mark_auction_as_shipped Error: '.$r->getMessage();
       adminEmail($msg,'admin',$auction->get('username'),$auction->get('saved_id'));
       exit;
    }
    $r = $dbr->getAll("select * from tracking_numbers
        WHERE auction_number=".$auction->get('auction_number')." AND txnid=".$auction->get('txnid')."
        #AND shipping_date='0000-00-00 00:00:00'
        ");
    if (PEAR::isError($r)) {
       $msg = $auction->get('username').' mark_auction_as_shipped Error: '.$r->getMessage();
       adminEmail($msg,'admin',$auction->get('username'),$auction->get('saved_id'));
       aprint_r($r);
    }
#	foreach ($r as $tn) {
#	  	standardEmail($db, $dbr, $auction, 'send_SERVICEQUITTUNG', $tn->id);
#	}

    require_once 'lib/Invoice.php';
    include 'english.php';
    $english = Auction::getTranslation($db, $dbr, $auction->get('siteid'), $auction->get('lang'));
    $invoice = new Invoice($db, $dbr, $auction->get('invoice_number'));
    $invoice->make_static_HTML($db, $dbr, $auction->get('auction_number'), $auction->get('txnid'), 0, 0, 0);
    $invoice->make_static_shipping_list_HTML($db, $dbr, $auction->get('auction_number'), $auction->get('txnid'), 0, 0);
    $invoice->data->shipping_username = $loggedUser->get('username');
    $invoice->update();

    if (!$auction->get('no_emails')) {
        if ($method->get('shipping_email_delay') == 0) {
    /*    if ($send_shipping_confirm) standardEmail($db, $dbr, $auction, 'shipping_details');*/
            /*if (!$info->get('dontsendshippinglist')) standardEmail($db, $dbr, $auction, 'send_packing_list');*/
    /*        if (!$info->get('dontsendSERVICEQUITTUNG') && !$method->get('dontsendSERVICEQUITTUNG')) standardEmail($db, $dbr, $auction, 'send_SERVICEQUITTUNG');*/
            /*standardEmail($db, $dbr, $auction, 'send_documents');*/
        }
        if ($auction->get('rma_id')) {
            if ($auction->get('txnid')==4) {
                standardEmail($db, $dbr, $auction, 'Ticket_based_driver_task_mark_as_shipped');
            } else {
                standardEmail($db, $dbr, $auction, 'rma_based_invoice_sent');
            }
        }
    }
    $subinvoices = $dbr->getAll("select * from auction where main_auction_number=".$auction->get('auction_number')."
         and txnid=".$auction->get('txnid'));
    foreach ($subinvoices as $subinvoice) {
        $auction = new Auction($db, $dbr, $subinvoice->auction_number, $subinvoice->txnid);
        $auction->set('no_emails', 1);
        mark_auction_as_shipped($db, $dbr, $auction, $user);
    }
}

/**
 * Make Activate call towards Klarna
 * @param object $auction
 */
function klarna_activate($auction){
    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $q = "SELECT pk.klarna_order_id, pk.currency, pk.payment_date
        FROM payment_klarna pk
        WHERE pk.auction_number = {$auction->data->auction_number}
        AND pk.txnid = {$auction->data->txnid}";
    $order_item = $dbr->getRow($q);

    if ($order_item && !empty($order_item->klarna_order_id)) {
        $sellerInfo = new SellerInfo($db, $dbr, $auction->get('username'));
        $klarna = new Klarna\XMLRPC\Klarna();
        $currency = constant('Klarna\XMLRPC\Currency::' . $order_item->currency);
        $country_code = siteToCountryCode($auction->get('siteid'));
        $purchase_country = constant('Klarna\XMLRPC\Country::' . $country_code);
        $purchase_language_map = [
            'SE' => 'SV',
            'DE' => 'DE',
        ];
        $purchase_language = constant('Klarna\XMLRPC\Language::' . $purchase_language_map[$country_code]);
        $klarna->config(
            $sellerInfo->get('klarna_id'),     // Merchant ID
            $sellerInfo->get('klarna_secret'), // Shared secret
            $purchase_country,    // Purchase country
            $purchase_language,   // Purchase language
            $currency,      // Purchase currency
            Klarna\XMLRPC\Klarna::LIVE    // Server
        );

        // in case of timeframe between the purchase (reserve_amount() call) and the shipment (activate() call)
        // exceeds 30 days, extend_expiry_date() should be called
        $now = new DateTime;
        $payment_date = new DateTime($order_item->payment_date);
        $diff = $now->diff($payment_date);
        $days = $diff->y * 365 + $diff->m * 30 + $diff->d;
        if ($days > 30) {
            try {
                $klarna->extendExpiryDate($order_item->klarna_order_id);
            } catch (\Exception $e) {
                $msg = "Klarna extendExpiryDate error: {$e->getMessage()} (#{$e->getCode()})\n";
                $_SESSION['messages']['error'][] = $msg;
            }
        }

        $shipment_details = [];
        foreach ($auction->tracking_numbers as $tn) {
            $shipment_details[] = [
                'shipping_company' => utf8_decode($tn->company_name),
                'tracking_number' => utf8_decode($tn->number),
                'tracking_url' => utf8_decode($tn->tracking_url),
            ];
        }

        $klarna->setShipmentInfo('shipment_details', $shipment_details);
        $klarna->setShipmentInfo('delay_adjust', 1);
        $klarna->setActivateInfo('orderid1', $auction->data->auction_number . "_" . $auction->data->txnid);

        try {
            $result = $klarna->activate(
                utf8_decode($order_item->klarna_order_id),
                null,    // OCR Number
                Klarna\XMLRPC\Flags::RSRV_SEND_BY_EMAIL
            );
            if (count($result)) {
                $invNo = $result[1];
                $q = "UPDATE payment_klarna SET klarna_invoice = '$invNo'
                    WHERE klarna_order_id = {$order_item->klarna_order_id}
                    AND auction_number = {$auction->data->auction_number}
                    AND txnid = {$auction->data->txnid}";
                $db->query($q);
                $msg = "Klarna activation succesful: invoice №$invNo";
                $_SESSION['messages']['info'][] = $msg;
            }
        } catch (\Exception $e) {
            $msg = "Klarna activation error: {$e->getMessage()} (#{$e->getCode()})\n";
            $_SESSION['messages']['error'][] = $msg;
        }
    }
}

/**
 *
 *
 */
function split_string($cnt_max, $cnt_per_pege)
{
    if (!$cnt_max || !$cnt_per_pege) {
        return;
    }
    $p_cur = (int)$_GET['p'];
    if ($p_cur == 0) {
        $p_cur =1;
    }
    $str = "|";
    $cnt = $cnt_max/$cnt_per_pege;
    $href = (string)$_SERVER['SCRIPT_NAME']."?".str_replace("&p=".$p_cur, "", (string)$_SERVER['QUERY_STRING']);
    for ($i = 1; $i <= ceil($cnt); $i++) {
        if ($p_cur == $i) {
            $str .= "&nbsp;$i&nbsp;|";
        }else{
            $str .= "&nbsp;<a href='$href&p=$i'>$i</a>&nbsp;|";
        }
    }
    if (!floor($cnt) == $cnt && floor($cnt)>=1) {
        $cnt = floor($cnt);
        if ($p_cur == $cnt+2) {
            $str .= "&nbsp;" . ($cnt+2) . "&nbsp;|";
        }else{
            $str .= "&nbsp;<a href='$href&p=" . ($cnt+2) . "'>" . ($cnt+2) . "</a>&nbsp;|";
        }
    }
    return $str;
}

function split_string_action($cnt_max, $cnt_per_pege, $page, $action, $p_l=0, $p_r=0, $mobile = false)
{
    if (!$cnt_max || !$cnt_per_pege) {
        return;
    }
    $p_cur = (int)$page;
    if ($p_cur == 0) {
        $p_cur =1;
    }
    if (!$mobile)
    {
        $p_l_orig = $p_l;
        $p_r_orig = $p_r;
        if (($p_cur-$p_l)<=0) {
            $p_r += $p_l - ($p_cur-1);
            $p_l=$p_cur-1;
        } elseif (($p_cur+$p_r)>=$cnt_max) {
            $p_l += $p_r - ($cnt_max-$p_cur);
            $p_r=$cnt_max-$p_cur;
        }
    }
    else
    {
        $p_l = 2;
        $p_r = 2;
    }
    $cnt = $cnt_max/$cnt_per_pege;
    if ($mobile)
    {
        if ($page==1) {
            $str .= "<li><</li>";
        } elseif ($page > 3) {
            $str .= "<li><a href='javascript:".str_replace('[[p]]', ($page-1), $action).";'><</a></li>";
            $str .= "<li><a href='javascript:".str_replace('[[p]]', 1, $action).";'>1</a></li>";
        } else {
            $str .= "<li><a href='javascript:".str_replace('[[p]]', ($page-1), $action)."'><</a></li>";
        }
    }
    else
    {
        if ($page==1) {
            $str .= "<li><<</li>";
            $str .= "<li><</li>";
        } else {
            $str .= "<li><a href='javascript:".str_replace('[[p]]',1,$action)."'><<</a></li>";
            $str .= "<li><a href='javascript:".str_replace('[[p]]',($page-1),$action)."'><</a></li>";
        }
    }
    for ($i = 1; $i <= ceil($cnt); $i++) {
        if ($p_l) {
            if ($i < ($p_cur - $p_l)) {
                if (!strpos($str, '<li> ...</li>')) {
                    $str .= '<li> ...</li>';
                }
                continue;
            }
        }
        if ($p_r) {
            if ($i > ($p_cur + $p_r)) {
                if (!strpos($str, '<li>... </li>')) {
                    $str .= '<li>... </li>';
                }
                continue;
            }
        }
        if ($p_cur == $i) {
            $str .= "<li><b>$i</b></li>";
        } elseif ($mobile) {
            $str .= "<li><a href='javascript:".str_replace('[[p]]',$i,$action).";'>$i</a></li>";
        } else {
            $str .= "<li><a href='javascript:".str_replace('[[p]]',$i,$action)."'>$i</a></li>";
        }

    }
    if (!floor($cnt) == $cnt && floor($cnt)>=1) {
        $cnt = floor($cnt);
        if ($p_cur == $cnt+2) {
            $str .= "<li>" . ($cnt+2) . "</li>";
        }else{
            $str .= "<li><a href='javascript:".str_replace('[[p]]',($cnt+2),$action)."'>" . ($cnt+2) . "</a></li>";
        }
    }
    if ($mobile)
    {
        if ($page==ceil($cnt)) {
            $str .= "<li>></li>";
        } elseif ($page < (ceil($cnt) - 2)) {
            $str .= "<li><a href='javascript:".str_replace('[[p]]',ceil($cnt),$action).";'>".ceil($cnt)."</a></li>";
            $str .= "<li><a href='javascript:".str_replace('[[p]]',($page+1),$action).";'>></a></li>";
        } else {
            $str .= "<li><a href='javascript:".str_replace('[[p]]',($page+1),$action).";'>></a></li>";
        }
    }
    else {
        if ($page==ceil($cnt)) {
            $str .= "<li>></li>";
            $str .= "<li>>></li>";
        } else {
            $str .= "<li><a href='javascript:".str_replace('[[p]]',($page+1),$action)."'>></a></li>";
            $str .= "<li><a href='javascript:".str_replace('[[p]]',ceil($cnt),$action)."'>>></a></li>";
        }
    }
    return $str;
}

function split_string_href($cnt_max, $cnt_per_pege, $page, $action, $p_l=0, $p_r=0)
{
    if (!$cnt_max || !$cnt_per_pege) {
        return;
    }
    $p_cur = (int)$page;
    if ($p_cur == 0) {
        $p_cur =1;
    }
    $p_l_orig = $p_l;
    $p_r_orig = $p_r;
    if (($p_cur-$p_l)<=0) {
        $p_r += $p_l - ($p_cur-1);
        $p_l=$p_cur-1;
    } elseif (($p_cur+$p_r)>=$cnt_max) {
        $p_l += $p_r - ($cnt_max-$p_cur);
        $p_r=$cnt_max-$p_cur;
    }
    $cnt = $cnt_max/$cnt_per_pege;
    if ($page==1) {
        $str .= "<li><<</li>";
        $str .= "<li><</li>";
    } else {
        $str .= "<li><a href='".str_replace('[[p]]',1,$action)."'><<</a></li>";
        $str .= "<li><a href='".str_replace('[[p]]',($page-1),$action)."'><</a></li>";
    }
    for ($i = 1; $i <= ceil($cnt); $i++) {
        if ($p_l) {
            if ($i < ($p_cur - $p_l)) {
                if (!strpos($str, '<li> ...</li>')) {
                    $str .= '<li> ...</li>';
                }
                continue;
            }
        }
        if ($p_r) {
            if ($i > ($p_cur + $p_r)) {
                if (!strpos($str, '<li>... </li>')) {
                    $str .= '<li>... </li>';
                }
                continue;
            }
        }
        if ($p_cur == $i) {
            $str .= "<li><b>$i</b></li>";
        }else{
            $str .= "<li><a href='".str_replace('[[p]]',$i,$action)."'>$i</a></li>";
        }
    }
    if (!floor($cnt) == $cnt && floor($cnt)>=1) {
        $cnt = floor($cnt);
        if ($p_cur == $cnt+2) {
            $str .= "<li>" . ($cnt+2) . "</li>";
        }else{
            $str .= "<li><a href='".str_replace('[[p]]',($cnt+2),$action)."'>" . ($cnt+2) . "</a></li>";
        }
    }
    if ($page==ceil($cnt)) {
        $str .= "<li>></li>";
        $str .= "<li>>></li>";
    } else {
        $str .= "<li><a href='".str_replace('[[p]]',($page+1),$action)."'>></a></li>";
        $str .= "<li><a href='".str_replace('[[p]]',ceil($cnt),$action)."'>>></a></li>";
    }
    return $str;
}

function checkUserCreatorPermission($user)
{
    global $smarty;
    return $user->data->is_can_create_new_user;
}

function checkPermission($user, $page, $level, $newpage='')
{
    global $smarty;
    if ($user->data->admin) return;
    $r = array();
    $r = explode('/', $_SERVER['PHP_SELF']);
    $page = end($r);
    if (strlen($newpage)) $page = $newpage;
    if ($user->accessLevel($page) < $level) {
      $smarty->assign("loggedUser",$user);
      $smarty->display("accessdenied.tpl");
        die ();
    }
    return $user->accessLevel($page);
}

function getPermission($user, $page)
{
    global $smarty;
    if ($user->get('admin')) return;
    $r = array();
    $r = explode('/', $_SERVER['PHP_SELF']);
    $page = end($r);
    return $user->accessLevel($page);
}

function adminEmail($msg_orig, $to='admin', $seller=0, $sa=0, $vars=array()){
    require_once 'lib/SellerInfo.php';
     global $db, $dbr;
     global $siteURL;
     global $ebay_name;
    $email_array = $dbr->getAssoc("select username, email from users where deleted=0 and $to");
    if (PEAR::isError($email_array)) { aprint_r($email_array); return;}
    $sellerInfo = SellerInfo::singleton($db, $dbr, $seller);
    if (strlen($sellerInfo->get('adminEmail_email'))) $email_array[] = $sellerInfo->get('adminEmail_email');
    $email = implode(', ', $email_array);
    $ret = array_to_object($_SERVER);
    $ret->email_invoice = $email;
    $ret->seller = $seller;
    $ret->sa = $sa;
    $ret->username = $dbr->getOne("select par_value from saved_params where saved_id=$sa and par_key='username'");
    $ret->sa_url = $siteURL.'newauction.php?edit='.$sa;
    $ret->offer_name = $dbr->getOne("select name from offer_name where id=".(int)$vars['name_id']);
    $ret->offer_name = $vars['name_id'].': '.$ret->offer_name;
    $ret->auftrag_type = $vars['fixedprice']?'Fixed':'Auction';
    $ret->now = date("Y-m-d h:i:s");
    $ret->msg = $msg_orig;
    $ret->next_id = mysql_insert_id();
    $ret->ebay_name = $ebay_name;
    return standardEmail($db, $dbr, $ret, 'adminEmail');
//    mail($email, "prologistics error notification #".mysql_insert_id(), $msg);
}

function calcAuction($db, $dbr, $auction, $order_id=0)
{
global $debug;
$time = getmicrotime();
if ($debug) echo 'calc 0: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
//	die('util calcAuction');
//	print_r($auction->data);
    require_once 'lib/ShippingPlan.php';
    $lang = $auction->getMyLang();
    $siteid = $auction->get('siteid');
    $curr_code = $dbr->getOne("SELECT ca.value FROM config_api ca
        LEFT join config_api_values cav on ca.par_id=cav.par_id and ca.value=cav.value
        where ca.par_id =7 and siteid='$siteid'");
    $fxrates = getRates();
    $curr_rate = $fxrates[$curr_code.'US']/$fxrates['EURUS'];
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
    $where_al = (int)$order_id ? "AND o.id=$order_id" : "";
    if ($auction) {
        $auction_number = $auction->data->auction_number;
        $txnid = $auction->data->txnid;
        $where = " AND au.auction_number = ".$auction_number." AND au.txnid = ".$txnid;
    }
    $where_al_d = (int)$order_id ? "AND order_id=$order_id" : "";
    if ($auction) {
        $auction_number = $auction->data->auction_number;
        $txnid = $auction->data->txnid;
        $where_d = " AND auction_number = ".$auction_number." AND txnid = ".$txnid;
        $offer_id = $auction->data->offer_id;
    }
// calc total shipping cost for the auction
if ($debug) echo 'calc 1: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
        $q = "SELECT SUM(
                IF( (SELECT sum( o1.quantity )
                    FROM orders o1
                    JOIN auction au1 ON o1.auction_number = au1.auction_number
                        AND o1.txnid = au1.txnid
                    JOIN offer_group og1 ON au1.offer_id = og1.offer_id
                    JOIN article_list al1 ON al1.group_id = og1.offer_group_id
                        AND al1.article_list_id = o1.article_list_id
                    WHERE og1.offer_group_id = og.offer_group_id
                    AND au.auction_number = au1.auction_number
                    AND au.txnid = au1.txnid
                    ) >= og.noshipping and og.noshipping>0 and au.quantity=1,
                    0,
                    IF (og.additional = 1,
                        IF(al.noship, 0, IFNULL(spca.shipping_cost, 0)),
                        IF (og.main = 1,
                            IFNULL(spco.shipping_cost, 0)
                                 - IF(IF(of.".$type."shipping_plan_free,1,IFNULL(t_o.value,0)) * IFNULL(defspco.shipping_cost, 0)>IFNULL(spco.shipping_cost, 0)
                                     ,IFNULL(spco.shipping_cost, 0)
                                    ,IF(of.".$type."shipping_plan_free,1,IFNULL(t_o.value,0)) * IFNULL(defspco.shipping_cost, 0))
                                + IF(f_isZipInRange(au_country_shipping.value,'islands',au_zip_shipping.value),IFNULL(spco.island_cost, 0),0)
                                + IFNULL(tt1.value, 0)
                                ,
                            IFNULL(tt1.value, 0)
                        )
                    )*o.quantity
                )
                ) as sumsh
            FROM orders o
                JOIN auction au ON o.auction_number = au.auction_number
                    AND o.txnid = au.txnid
                left JOIN auction mau ON mau.auction_number = au.main_auction_number
                    AND mau.txnid = au.main_txnid
                left join auction_par_varchar au_country_shipping on IFNULL(mau.auction_number,au.auction_number)=au_country_shipping.auction_number
                    and au.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
                left join auction_par_varchar au_zip_shipping on IFNULL(mau.auction_number,au.auction_number)=au_zip_shipping.auction_number
                    and au.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
                JOIN offer_group og ON au.offer_id = og.offer_id
                left JOIN article_list al ON o.article_list_id = al.article_list_id
                    and og.offer_group_id = al.group_id
                left join translation tt1 on tt1.table_name = 'article_list'
                    AND tt1.field_name = 'additional_shipping_cost'
                    AND tt1.language = '$siteid'
                    AND tt1.id = al.article_list_id
                JOIN article a ON a.article_id = al.article_id
                JOIN offer of ON au.offer_id = of.offer_id
                JOIN seller_information si ON au.username=si.username
                LEFT JOIN country c1 ON IF(au_country_shipping.value='United Kingdom','UK',au_country_shipping.value) = c1.name AND au.payment_method not in (3,4)
                JOIN country c2 ON si.defshcountry=c2.code
                left join translation t_o
                    on t_o.language='$siteid'
                    and t_o.id=of.offer_id
                    and t_o.table_name='offer' and t_o.field_name='".$type."shipping_plan_free_tr'
                JOIN shipping_plan_country spco ON spco.shipping_plan_id = ( IFNULL( (
                     SELECT value
                     FROM translation
                     WHERE table_name = 'offer'
                     AND field_name = '".$type."shipping_plan_id'
                     AND language = '$siteid'
                     AND id = of.offer_id
                     ), of.shipping_plan_id ) ) AND spco.country_code = IFNULL(c1.code,c2.code)
                LEFT JOIN shipping_plan_country defspco ON defspco.shipping_plan_id = (
                     IFNULL((SELECT value
                     FROM translation
                     WHERE table_name = 'offer'
                     AND field_name = '".$type."shipping_plan_id'
                     AND language = '$siteid'
                     AND id = of.offer_id), of.shipping_plan_id))
                    and defspco.country_code = si.defshcountry
                left JOIN shipping_plan_country spca ON spca.shipping_plan_id = ( IFNULL( (
                     SELECT value
                     FROM translation
                     WHERE table_name = 'article'
                     AND field_name = 'shipping_plan_id'
                     AND language = '$siteid'
                     AND id = a.article_id
                     ), a.shipping_plan_id ) ) AND spca.country_code = IFNULL(c1.code,c2.code)
            WHERE 1=1 $where ";
        $total_shipping = $dbr->getOne($q);
#if ($debug) echo $q.'<br>';
if ($debug) echo 'calc 2: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
        file_put_contents("lastCALC.txt", "total_shipping=".$q);
        if (PEAR::isError($total_shipping)) {
            aprint_r($total_shipping);
        }

// calc total sum for COD forthe auction
        $q  ="SELECT IFNULL(SUM(
                IFNULL(IF(au.payment_method='2' AND og.main = 1,
                            o.price*o.quantity, 0), 0)
                ), 0) as sumsh
            FROM orders o
                JOIN auction au ON o.auction_number = au.auction_number
                    AND o.txnid = au.txnid
                left JOIN auction mau ON mau.auction_number = au.main_auction_number
                    AND mau.txnid = au.main_txnid
                left join auction_par_varchar au_country_shipping on IFNULL(mau.auction_number,au.auction_number)=au_country_shipping.auction_number
                    and au.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
                left join auction_par_varchar au_zip_shipping on IFNULL(mau.auction_number,au.auction_number)=au_zip_shipping.auction_number
                    and au.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
                JOIN offer_group og ON au.offer_id = og.offer_id
                left JOIN article_list al ON o.article_list_id = al.article_list_id
                    and og.offer_group_id = al.group_id
                JOIN article a ON a.article_id = al.article_id
                JOIN offer of ON au.offer_id = of.offer_id
                JOIN seller_information si ON au.username=si.username
                LEFT JOIN country c1 ON IF(au_country_shipping.value='United Kingdom','UK',au_country_shipping.value) = c1.name AND au.payment_method not in (3,4)
                JOIN country c2 ON si.defshcountry=c2.code
                JOIN shipping_plan_country spco ON spco.shipping_plan_id = ( IFNULL( (
                     SELECT value
                     FROM translation
                     WHERE table_name = 'offer'
                     AND field_name = '".$type."shipping_plan_id'
                     AND language = '$siteid'
                     AND id = of.offer_id
                     ), of.shipping_plan_id ) ) AND spco.country_code = IFNULL(c1.code,c2.code)
                left JOIN shipping_plan_country spca ON spca.shipping_plan_id = ( IFNULL( (
                     SELECT value
                     FROM translation
                     WHERE table_name = 'article'
                     AND field_name = 'shipping_plan_id'
                     AND language = '$siteid'
                     AND id = a.article_id
                     ), a.shipping_plan_id ) ) AND spca.country_code = IFNULL(c1.code,c2.code)
            WHERE 1=1 $where ";
        $total_sum_cod = $dbr->getOne($q);
        file_put_contents("lastCALC_cod.txt", "total_cod=".$q);
        if (PEAR::isError($total_sum_cod)) {
            aprint_r($total_sum_cod);
        }
if ($debug) echo 'calc 3: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();

// calc ebay listing fee for the auction
        $listing_fee = $dbr->getOne("SELECT IFNULL(IFNULL(l.listing_fee, au.listing_fee)*au.quantity/
                (select sum( au1.quantity) from auction au1 where au1.auction_number = au.auction_number), 0) listing_fee
            FROM auction au
                LEFT JOIN listings l ON au.auction_number = l.auction_number
            WHERE 1=1 $where");
        if (PEAR::isError($listing_fee)) {
            aprint_r($listing_fee);
        }
        if (!$listing_fee) $listing_fee = 0;
if ($debug) echo 'calc 4: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();

// calc total quantity for the main group of the auction
        $r = $dbr->getAll("SELECT IFNULL(sum(o.quantity), 0) main_quantity, IFNULL(sum(o.quantity*o.price), 0) main_sum
            FROM orders o join auction au on o.auction_number=au.auction_number and o.txnid=au.txnid
                join offer_group og on au.offer_id=og.offer_id
                left JOIN article_list al ON o.article_list_id = al.article_list_id
                    and al.group_id=og.offer_group_id
            WHERE 3=3 $where
            and og.main=1");
        if (PEAR::isError($r)) {
            aprint_r($r);
        }
        $res = $r[0];
        $main_quantity = $res->main_quantity;
        $main_sum = $res->main_sum;
        if (!$main_quantity) $main_quantity = 'o.quantity';
        if (!$main_sum) $main_sum = '(o.quantity*o.price)';
if ($debug) echo 'calc 5: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();

// calc total sum for the auction
        $total_sum = $dbr->getOne("SELECT sum(o.quantity*o.price) total_sum
            FROM orders o join auction au on o.auction_number=au.auction_number and o.txnid=au.txnid
            WHERE 1=1 and manual=0 $where");
        if (PEAR::isError($total_sum)) {
            aprint_r($total_sum);
        }
if ($debug) echo 'calc 6: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
    if (!$total_sum) $total_sum=0;
        $delres = $db->query("delete from auction_calcs WHERE 1=1 $where_d $where_al_d");
        if (PEAR::isError($delres)) aprint_r($delres);
        $subinvoices = (int)$dbr->getOne("select count(*) from auction where main_auction_number=$auction_number and txnid=$txnid");
        $shipping_cost_formula = "
            IF(si.free_shipping_total or (si.free_shipping and si.defshcountry=c1.code and $total_sum>si.free_shipping_above), 0,
                IF(o.manual in (3,2), 0, IF(au.txnid=3 and o.manual=4, i.total_shipping, IF($subinvoices and au.txnid=3 and o.manual<>4, 0
                    , IF(o.hidden, 0, IF (i.is_custom_shipping,
                    IF($total_sum = 0,
                        0,
                        o.price*o.quantity*(i.total_shipping)
                        /$total_sum
                    ),
                IF( (SELECT sum( o1.quantity )
                    FROM orders o1
                    JOIN auction au1 ON o1.auction_number = au1.auction_number
                        AND o1.txnid = au1.txnid
                    JOIN offer_group og1 ON au1.offer_id = og1.offer_id
                    JOIN article_list al1 ON al1.group_id = og1.offer_group_id
                        AND al1.article_list_id = o1.article_list_id
                    WHERE og1.offer_group_id = og.offer_group_id
                    AND au.auction_number = au1.auction_number
                    AND au.txnid = au1.txnid
                    ) >= og.noshipping and og.noshipping>0 and au.quantity=1,
                    0,
                    IF (og.additional = 1,
                        IF(al.noship, 0, IFNULL(spca.shipping_cost, 0)),
                        IF (og.main = 1,
                            IFNULL(spco.shipping_cost, 0)
                                 - IF(IF(of.".$type."shipping_plan_free,1,IFNULL(t_o.value,0)) * IFNULL(defspco.shipping_cost, 0)>IFNULL(spco.shipping_cost, 0)
                                     ,IFNULL(spco.shipping_cost, 0)
                                    ,IF(of.".$type."shipping_plan_free,1,IFNULL(t_o.value,0)) * IFNULL(defspco.shipping_cost, 0))
                                + IF(f_isZipInRange(au_country_shipping.value,'islands',au_zip_shipping.value),IFNULL(spco.island_cost, 0),0)
                                + IFNULL((SELECT value
                                    FROM translation
                                    WHERE table_name = 'article_list'
                                    AND field_name = 'additional_shipping_cost'
                                    AND language = '$siteid'
                                    AND id = al.article_list_id), 0),
                            IFNULL((SELECT value
                                FROM translation
                                WHERE table_name = 'article_list'
                                AND field_name = 'additional_shipping_cost'
                                AND language = '$siteid'
                                AND id = al.article_list_id), 0)
                        )
                    ) *o.quantity
                )
                ))))))";
        $qry = "insert into auction_calcs
            (
                auction_number,
                txnid,
                article_list_id,
                article_id,
                price_sold,
                ebay_listing_fee,
                additional_listing_fee,
                ebay_commission,
                vat,
                purchase_price,
                shipping_cost,
                effective_shipping_cost,
                COD_cost,
                effective_COD_cost,
                curr_code,
                curr_rate,
                packing_cost,
                vat_shipping,
                vat_COD,
                order_id,
                vat_percent
            )
            (SELECT distinct
            ttt.auction_number,
            ttt.txnid,
            ttt.article_list_id,
            ttt.article_id,
            ttt.price_sold,
            ttt.ebay_listing_fee,
            ttt.additional_listing_fee,
            ttt.ebay_commission,
            ttt.vat,
            ttt.purchase_price,
            ttt.shipping_cost,
            ttt.effective_shipping_cost,
            ttt.COD_cost,
            ttt.effective_COD_cost,
            ttt.curr_code,
            ttt.curr_rate,
            ttt.packing_cost,
            (IFNULL(ttt.vat_percent, 0))*ttt.shipping_cost/(100+IFNULL(ttt.vat_percent, 0)) as vat_shipping,
            (IFNULL(ttt.vat_percent, 0))*ttt.COD_cost/(100+IFNULL(ttt.vat_percent, 0)) as vat_COD,
            order_id,
                vat_percent
            FROM (SELECT
                IF(IFNULL(mau.customer_vat,au.customer_vat)<>'', 0, v.vat_percent) vat_percent,
                o.quantity,
                o.auction_number,
                o.txnid,
                o.article_list_id,
                o.article_id,
                IF (
                    og.additional =1, o.price*o.quantity, o.price*o.quantity
                    ) AS price_sold,
                IF (o.hidden, 0, IF (og.main = 1, $listing_fee*o.quantity/$main_quantity, 0)) AS ebay_listing_fee,
                IF (o.hidden, 0, IF (og.main = 1, au.additional_listing_fee*o.quantity/$main_quantity, 0)) AS additional_listing_fee,
                IF(ss.provision, o.price*o.quantity*ss.provision/100,
                    IF($main_sum=0 or o.manual, 0, IF (o.hidden, 0,
                        IF(au.txnid in (0,2,3)
                        , o.price*o.quantity*`fget_Fee_Percent_channel`(au.winning_bid*au.quantity
                            + IF(exists (select null from saved_fee_algo sfa
                            join seller_information si on si.seller_channel_id=sfa.seller_channel_id
                            where sfa.fieldname='winning_bid'
                            and si.username='Amazon'
                            and `type`='withship'
                            limit 1), ($shipping_cost_formula), 0)
                        , IF(si.seller_channel_id=4 and o.txnid=2, 3, si.seller_channel_id))/$total_sum
                    , IFNULL(IF (og.main = 1,
                        (au.winning_bid*au.quantity * 0.11 / 115) * 100
                        /*IF (au.winning_bid <=25,
                            au.winning_bid * 0.0525,
                            IF (au.winning_bid <=1000,
                                1.31 + ( au.winning_bid -25 ) * 0.0275,
                                1.31 + 26.81 + ( au.winning_bid -1000 ) * 0.015)
                        )*/, 0)*o.quantity*o.price/$main_sum, 0)))))
                AS ebay_commission,
                IF(IFNULL(mau.customer_vat,au.customer_vat)<>'', 0, (IFNULL(v.vat_percent, 0))*o.price*o.quantity/(100+IFNULL(v.vat_percent, 0))) as vat,
                IF(o.manual=3
                    , -(select min(sold_for_amount) from shop_promo_codes where article_id=o.article_id)/(1+v.vat_percent/100)
                    , IFNULL(
                        (select article_import.total_item_cost from article_import
                            join country on country.code=article_import.country_code
                            where article_import.country_code=si.defcalcpricecountry
                            and article_import.article_id=a.article_id and o.manual=0
                            order by import_date desc limit 1)
                        ,(select article_import.total_item_cost from article_import
                                where article_import.article_id=a.article_id and o.manual=0
                                order by import_date desc limit 1)
/*						, IFNULL(
                            (select article_import.total_item_cost from article_import
                                where article_import.country_code=w.country_code
                                and article_import.article_id=a.article_id and o.manual=0
                                order by import_date desc limit 1)
                            ,(select article_import.total_item_cost from article_import
                                where article_import.article_id=a.article_id and o.manual=0
                                order by import_date desc limit 1)
                        )*/
                    )*o.quantity/$curr_rate) as purchase_price,

                $shipping_cost_formula as shipping_cost,

                IF(IFNULL(ss.free_shipping,0) OR o.hidden, 0, IFNULL(IF (og.additional = 1,
                    IF(spca.estimate, IFNULL(spca.real_additional_cost,0), IFNULL(scc_art.real_additional_cost, 0)),
                    IF (og.main = 1,
                        IF(spco.estimate, IFNULL(spco.real_shipping_cost,0), IFNULL(scc_off.real_shipping_cost, 0))
                            + IF(f_isZipInRange(au_country_shipping.value,'islands',au_zip_shipping.value)
                                ,IF(spco.estimate, IFNULL(spco.real_island_cost,0), IFNULL(scc_off.real_island_cost, 0)),0)
                        ,0
                    )
                )*o.quantity, 0))	AS effective_shipping_cost,

                IF(o.manual=4, i.total_cod+IFNULL(i.total_cc_fee,0), IF(o.manual in (1,2,3), 0, IF(o.hidden, 0, IFNULL(IF(au.payment_method='2',
                    IF (i.is_custom_cod,
                        IF($total_sum = 0,
                            0,
                            o.price*o.quantity*(i.total_cod)/$total_sum
                        ),
                        IF (og.main = 1,
                            IF($total_sum_cod=0, spco.COD_cost, o.price*o.quantity*spco.COD_cost/$total_sum_cod),
                        0)),
                    0
                ), 0)))) as COD_cost,

                IF(o.hidden, 0, IFNULL(IF((au.payment_method='2' AND og.main = 1),
                    IF(spco.estimate, spco.real_COD_cost, scc_off.real_COD_cost), 0), 0))
                as effective_COD_cost,

                '$curr_code' as curr_code,
                $curr_rate as curr_rate,
                IF(o.hidden, 0, (IFNULL((SELECT SUM(asm.total_item_cost)
                                            FROM article asm
                                            JOIN article_sh_material asmsm
                                                ON asmsm.sh_material_id = asm.article_id
                                            WHERE asmsm.article_id = a.article_id), 0)
                    *o.quantity
                )) as packing_cost,
                o.id order_id
            FROM orders o
                left join warehouse w on o.send_warehouse_id=w.warehouse_id
                JOIN auction au ON o.auction_number = au.auction_number
                    AND o.txnid = au.txnid
                left JOIN auction mau ON mau.auction_number = au.main_auction_number
                    AND mau.txnid = au.main_txnid
                left join source_seller ss on ss.id=IFNULL(mau.source_seller_id,au.source_seller_id)
                left join auction_par_varchar au_country_shipping on IFNULL(mau.auction_number,au.auction_number)=au_country_shipping.auction_number
                    and au.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
                left join auction_par_varchar au_zip_shipping on IFNULL(mau.auction_number,au.auction_number)=au_zip_shipping.auction_number
                    and au.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
                left JOIN article_list al ON o.article_list_id = al.article_list_id
                LEFT JOIN offer_group og ON al.group_id = og.offer_group_id
                LEFT JOIN article a ON a.article_id = o.article_id AND a.admin_id=o.manual
                JOIN invoice i ON au.invoice_number = i.invoice_number
                JOIN seller_information si ON au.username=si.username
                LEFT JOIN country c1 ON IF(au_country_shipping.value='United Kingdom','UK',au_country_shipping.value) = c1.name AND au.payment_method not in (3,4)
                JOIN country c2 ON si.defshcountry=c2.code
                LEFT JOIN vat v on v.country_code=IFNULL(c1.code, c2.code) and DATE(i.invoice_date) between v.date_from and v.date_to
                    and v.country_code_from=si.defshcountry
                LEFT JOIN offer of ON au.offer_id = of.offer_id
                left join translation t_o
                    on t_o.language='$siteid'
                    and t_o.id=of.offer_id
                    and t_o.table_name='offer' and t_o.field_name='".$type."shipping_plan_free_tr'
                LEFT JOIN shipping_plan sp_off ON sp_off.shipping_plan_id =  ( IFNULL( (
                     SELECT value
                     FROM translation
                     WHERE table_name = 'offer'
                     AND field_name = '".$type."shipping_plan_id'
                     AND language = '$siteid'
                     AND id = of.offer_id
                     ), of.shipping_plan_id ) )
                LEFT JOIN shipping_plan_country defspco ON (
                     IFNULL((SELECT value
                     FROM translation
                     WHERE table_name = 'offer'
                     AND field_name = '".$type."shipping_plan_id'
                     AND language = '$siteid'
                     AND id = of.offer_id), of.shipping_plan_id))
                =defspco.shipping_plan_id
                    and defspco.country_code = si.defshcountry
                LEFT JOIN shipping_plan sp_art ON sp_art.shipping_plan_id =  ( IFNULL( (
                     SELECT value
                     FROM translation
                     WHERE table_name = 'article'
                     AND field_name = 'shipping_plan_id'
                     AND language = '$siteid'
                     AND id = a.article_id
                     ), a.shipping_plan_id ) )
                LEFT JOIN shipping_cost sc_off ON sp_off.shipping_cost_id = sc_off.id
                LEFT JOIN shipping_cost sc_art ON sp_art.shipping_cost_id = sc_art.id
                LEFT JOIN shipping_cost_country scc_off ON scc_off.shipping_cost_id = sc_off.id AND scc_off.country_code = IFNULL(c1.code, c2.code)
                LEFT JOIN shipping_cost_country scc_art ON scc_art.shipping_cost_id = sc_art.id AND scc_art.country_code = IFNULL(c1.code, c2.code)
                LEFT JOIN shipping_plan_country spco ON spco.shipping_plan_id = sp_off.shipping_plan_id AND spco.country_code = IFNULL(c1.code, c2.code)
                LEFT JOIN shipping_plan_country spca ON spca.shipping_plan_id = sp_art.shipping_plan_id AND spca.country_code = IFNULL(c1.code, c2.code)
            WHERE 1=1 $where $where_al AND NOT au.deleted) ttt
            ) ";
        file_put_contents('lastquery_calc', $qry);
//			echo $qry.'<br>';// die();
        $r = $db->query($qry);
if ($debug) echo 'calc 7: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
        if (PEAR::isError($r)) {
            aprint_r($r);
        }
        // au.offer_id = og.offer_id AND
        $q = "select o.id order_id, o.article_list_id, au.payment_method, og.main, og.additional,
            spco.estimate, sp_off.shipping_plan_id, sp_off.curr_code,
            spco.real_shipping_cost, spco.real_COD_cost, spco.real_island_cost, spco.real_additional_cost,
            o.quantity,
            spca.estimate a_estimate, sp_art.shipping_plan_id a_shipping_plan_id, sp_art.curr_code a_curr_code,
            spca.real_shipping_cost a_real_shipping_cost, spca.real_COD_cost a_real_COD_cost,
            spca.real_island_cost a_real_island_cost, spca.real_additional_cost a_real_additional_cost,
            IFNULL(c1.code, c2.code) country_code, o.article_id
            , ss.free_shipping
            FROM orders o
                JOIN auction au ON o.auction_number = au.auction_number
                    AND o.txnid = au.txnid
                left JOIN auction mau ON mau.auction_number = au.main_auction_number
                    AND mau.txnid = au.main_txnid
                left join source_seller ss on ss.id=IFNULL(mau.source_seller_id,au.source_seller_id)
                left join auction_par_varchar au_country_shipping on IFNULL(mau.auction_number,au.auction_number)=au_country_shipping.auction_number
                    and au.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
                left join auction_par_varchar au_zip_shipping on IFNULL(mau.auction_number,au.auction_number)=au_zip_shipping.auction_number
                    and au.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
                left JOIN article_list al ON o.article_list_id = al.article_list_id
                LEFT JOIN offer_group og ON al.group_id = og.offer_group_id
                JOIN article a ON a.article_id = al.article_id AND a.admin_id=o.manual
                JOIN invoice i ON au.invoice_number = i.invoice_number
                JOIN seller_information si ON au.username=si.username
                LEFT JOIN country c1 ON IF(au_country_shipping.value='United Kingdom','UK',au_country_shipping.value) = c1.name AND au.payment_method not in (3,4)
                JOIN country c2 ON si.defshcountry=c2.code
                LEFT JOIN vat v on v.country_code=IFNULL(c1.code, c2.code) and DATE(i.invoice_date) between v.date_from and v.date_to
                    and v.country_code_from=si.defshcountry
                LEFT JOIN offer of ON au.offer_id = of.offer_id
                LEFT JOIN shipping_plan sp_off ON sp_off.shipping_plan_id = ( IFNULL( (
                     SELECT value
                     FROM translation
                     WHERE table_name = 'offer'
                     AND field_name = '".$type."shipping_plan_id'
                     AND language = '$siteid'
                     AND id = of.offer_id
                     ), of.shipping_plan_id ) )
                LEFT JOIN shipping_plan sp_art ON sp_art.shipping_plan_id = ( IFNULL( (
                     SELECT value
                     FROM translation
                     WHERE table_name = 'article'
                     AND field_name = 'shipping_plan_id'
                     AND language = '$siteid'
                     AND id = a.article_id
                     ), a.shipping_plan_id ) )
                LEFT JOIN shipping_cost sc_off ON sp_off.shipping_cost_id = sc_off.id
                LEFT JOIN shipping_cost sc_art ON sp_art.shipping_cost_id = sc_art.id
                LEFT JOIN shipping_cost_country scc_off ON scc_off.shipping_cost_id = sc_off.id AND scc_off.country_code = IFNULL(c1.code, c2.code)
                LEFT JOIN shipping_cost_country scc_art ON scc_art.shipping_cost_id = sc_art.id AND scc_art.country_code = IFNULL(c1.code, c2.code)
                LEFT JOIN shipping_plan_country spco ON spco.shipping_plan_id = sp_off.shipping_plan_id AND spco.country_code = IFNULL(c1.code, c2.code)
                LEFT JOIN shipping_plan_country spca ON spca.shipping_plan_id = sp_art.shipping_plan_id AND spca.country_code = IFNULL(c1.code, c2.code)
            WHERE 1=1 $where $where_al AND au.deleted=0 and o.hidden=0";
//		echo $q; 	die();
        $inserted = $dbr->getAll($q);
if ($debug) echo 'calc 8: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
        if (PEAR::isError($inserted)) {
            aprint_r($inserted);
        }
        foreach ($inserted as $row) {
            if ($row->free_shipping) continue;
            if ($row->estimate) {
                if (($row->payment_method==2 && $row->main)) {
                    $effective_COD_cost = $fxrates[$row->curr_code.'US']/$fxrates[$curr_code.'US']
                                * $row->real_COD_cost;
                } else $effective_COD_cost = 0;
                if ($row->additional) {
                    $effective_shipping_cost = $fxrates[$row->a_curr_code.'US']/$fxrates[$curr_code.'US']
                                 * $row->a_real_additional_cost;
                }
                elseif ($row->main) {
                    $real_shipping_cost = $fxrates[$row->curr_code.'US']/$fxrates[$curr_code.'US']
                                 * $row->real_shipping_cost;
//					echo ($auction->data->country_shipping. ' islands '. $auction->data->zip_shipping);
                    if (isZipInRange($db, $dbr, $auction->data->country_shipping, 'islands', $auction->data->zip_shipping)) {
                        $real_island_cost = $fxrates[$row->curr_code.'US']/$fxrates[$curr_code.'US']
                                 * $row->real_island_cost;
                    } else $real_island_cost = 0;
                    $effective_shipping_cost = $real_shipping_cost + $real_island_cost;
                } else
                    $effective_shipping_cost = 0;
            } else { // not estimate
                $cod_shipping_plan_id = ShippingPlan::findLeaf($db, $dbr, $row->shipping_plan_id, $row->country_code, 'cod_diff_shipping_plan_id');
                $shipping_plan_id = ShippingPlan::findLeaf($db, $dbr, $row->shipping_plan_id, $row->country_code, 'diff_shipping_plan_id');
                if (!$shipping_plan_id) $shipping_plan_id = $row->shipping_plan_id;
                if (($row->payment_method==2 && $row->main)) {
                    $r = $dbr->getRow("select real_cod_cost, sc.curr_code
                        from shipping_cost_country scc_off
                        JOIN shipping_cost sc ON scc_off.shipping_cost_id = sc.id
                        WHERE scc_off.shipping_cost_id=$cod_shipping_plan_id and country_code='$row->country_code'
                        ");
                          if (PEAR::isError($r)) aprint_r($r);
                    $effective_COD_cost = $fxrates[$r->curr_code.'US']/$fxrates[$curr_code.'US']
                                * $r->real_cod_cost;
                } else $effective_COD_cost = 0;
                if ($row->additional) {
                    $r = $dbr->getRow("select real_additional_cost, sc.curr_code
                        from shipping_cost_country scc_off
                        JOIN shipping_cost sc ON scc_off.shipping_cost_id = sc.id
                        JOIN shipping_plan sp_off ON sp_off.shipping_cost_id = scc_off.shipping_cost_id
                        WHERE sp_off.shipping_plan_id='$row->a_shipping_plan_id' and country_code='$row->country_code'
                     ");
                          if (PEAR::isError($r)) aprint_r($r);
                    $effective_shipping_cost = $fxrates[$r->curr_code.'US']/$fxrates[$curr_code.'US']
                                 * $r->real_additional_cost;
                }
                elseif ($row->main) {
                    $q = "select real_shipping_cost, sc.curr_code
                        from shipping_cost_country scc_off
                        JOIN shipping_cost sc ON scc_off.shipping_cost_id = sc.id
                        WHERE scc_off.shipping_cost_id=$shipping_plan_id and country_code='$row->country_code'
                     ";
                    file_put_contents('lastquery_realshippingcalc', $q);
                    $r = $dbr->getRow($q);
                          if (PEAR::isError($r)) { echo "<br>CompleteAuction $auction_number/$txnid:"; print_r($r); }
                    $real_shipping_cost = $fxrates[$r->curr_code.'US']/$fxrates[$curr_code.'US']
                                 * $r->real_shipping_cost;
//					echo $r->curr_code.' '.$real_shipping_cost.' = '.$fxrates[$r->curr_code.'US'].'/'.$fxrates[$curr_code.'US']
//								 .'*'. $r->real_shipping_cost;		die();
                    if (isZipInRange($db, $dbr, $auction->data->country_shipping, 'islands', $auction->data->zip_shipping)) {
                            $r = $dbr->getRow("select real_island_cost, sc.curr_code
                           from shipping_cost_country scc_off
                           JOIN shipping_cost sc ON scc_off.shipping_cost_id = sc.id
                           JOIN shipping_plan sp_off ON sp_off.shipping_cost_id = scc_off.shipping_cost_id
                           WHERE sp_off.shipping_plan_id=$row->shipping_plan_id and country_code='$row->country_code'
                            ");
                              if (PEAR::isError($r)) aprint_r($r);
                        $real_island_cost = $fxrates[$r->curr_code.'US']/$fxrates[$curr_code.'US']
                                 * $r->real_island_cost;
                    } else $real_island_cost = 0;
                    $effective_shipping_cost = $real_shipping_cost + $real_island_cost;
                } else
                    $effective_shipping_cost = 0;
            }
            $effective_shipping_cost *= $row->quantity;
            $q = "update auction_calcs set
                effective_shipping_cost = $effective_shipping_cost,
                effective_COD_cost = $effective_COD_cost
                WHERE 1=1 $where_d $where_al_d
                and order_id = '$row->order_id'
                ";
            $updated = $db->query($q);
            if (PEAR::isError($updated)) aprint_r($updated);
        };
if ($debug) echo 'calc 9: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
#    	require_once 'lib/Group.php';
#    	$orderBonus = Order::listBonus($db, $dbr, $auction_number, $txnid);
#		foreach($orderBonus as $bonus) {
#			$article_list_id = Group::addArticle($db, $dbr, 0, $bonus->article_id, 0, $bonus->price, 0, 1, 1);
            $q = "insert into auction_calcs
                (
                    auction_number,
                    txnid,
                    article_list_id,
                    article_id,
                    price_sold,
                    curr_code,
                    curr_rate
                ) select
                    auction_number,
                    txnid,
                    article_list_id,
                    article_id,
                    price,
                    '$curr_code' as curr_code,
                    $curr_rate as curr_rate
                    from orders
                    where auction_number = $auction_number
                    and txnid = $txnid
                ";
#			$r = $db->query($q);
#		    if (PEAR::isError($r)) { print_r($r); die();}
#		}
//		echo 'Calculated';
}

function calcAuctionByNumber($db, $dbr, $auction_number, $txnid, $order_id, $currency, $auction_country_shipping, $auction_zip_shipping)
{
    require_once 'lib/ShippingPlan.php';
    $curr_code = $currency;
    $fxrates = getRates();
    $auction = new Auction ($db, $dbr, $auction_number, $txnid);
    $offer_id = $auction->data->offer_id;
    $siteid = $auction->get('siteid');
    $sellerInfo = SellerInfo::singleton($db, $dbr, $auction->get('username'));
    $defshcountry = countryCodeToCountry ($sellerInfo->get('defshcountry'));
    $curr_rate = $fxrates[$curr_code.'US']/$fxrates['EURUS'];
    if ($sellerInfo->get('seller_channel_id')==3) {
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
    $where_au = " AND au.auction_number = $auction_number AND au.txnid = $txnid";
    $where = " AND au.auction_number = $auction_number AND au.txnid = $txnid AND o.order_id=$order_id";
    $where_d = " AND auction_number = $auction_number AND txnid = $txnid AND order_id=$order_id";
// calc total shipping cost for the auction
        $q = "SELECT SUM(
                IF( (SELECT sum( o1.quantity )
                    FROM orders o1
                    JOIN auction au1 ON o1.auction_number = au1.auction_number
                        AND o1.txnid = au1.txnid
                    JOIN offer_group og1 ON au1.offer_id = og1.offer_id
                    JOIN article_list al1 ON al1.group_id = og1.offer_group_id
                        AND al1.article_list_id = o1.article_list_id
                    WHERE og1.offer_group_id = og.offer_group_id
                    AND au.auction_number = au1.auction_number
                    AND au.txnid = au1.txnid
                    ) >= og.noshipping and og.noshipping>0,
                    0,
                    IF (og.additional = 1,
                        IF(al.noship, 0, IFNULL(spca.shipping_cost, 0)),
                        IF (og.main = 1,
                            IFNULL(spco.shipping_cost, 0) + "
                .(isZipInRange($db, $dbr, $auction_country_shipping, 'islands', $auction_zip_shipping) ? "IFNULL(spco.island_cost, 0) +" : "")." al.additional_shipping_cost,
                            IFNULL(spco.shipping_cost, 0) + al.additional_shipping_cost
                        )
                    )*o.quantity
                )
                ) as sumsh
            FROM orders o
                JOIN auction au ON o.auction_number = au.auction_number
                    AND o.txnid = au.txnid
                left JOIN auction mau ON mau.auction_number = au.main_auction_number
                    AND mau.txnid = au.main_txnid
                left join auction_par_varchar au_country_shipping on IFNULL(mau.auction_number,au.auction_number)=au_country_shipping.auction_number
                    and au.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
                JOIN offer_group og ON au.offer_id = og.offer_id
                left JOIN article_list al ON o.article_list_id = al.article_list_id
                     and og.offer_group_id = al.group_id
                JOIN article a ON a.article_id = al.article_id
                JOIN offer of ON au.offer_id = of.offer_id
                JOIN country c ON IF(au.payment_method >2 or au_country_shipping.value='', '$defshcountry', au_country_shipping.value)=c.name
                JOIN shipping_plan_country spco ON spco.shipping_plan_id = ( IFNULL( (
                     SELECT value
                     FROM translation
                     WHERE table_name = 'offer'
                     AND field_name = '".$type."shipping_plan_id'
                     AND language = '$siteid'
                     AND id = of.offer_id
                     ), of.shipping_plan_id ) ) AND spco.country_code = c.code
                left JOIN shipping_plan_country spca ON spca.shipping_plan_id = ( IFNULL( (
                     SELECT value
                     FROM translation
                     WHERE table_name = 'article'
                     AND field_name = 'shipping_plan_id'
                     AND language = '$siteid'
                     AND id = a.article_id
                     ), a.shipping_plan_id ) ) AND spca.country_code = c.code
            WHERE 1=1 $where";
        $total_shipping = $dbr->getOne($q);
        file_put_contents("lastCALC.txt", "total_shipping=".$q);
        if (PEAR::isError($total_shipping)) {
            aprint_r($total_shipping);
        }


// calc total sum for COD forthe auction
        $total_sum_cod = $dbr->getOne("SELECT IFNULL(SUM(
                IFNULL(IF(au.payment_method='2' AND og.main = 1,
                            o.price*o.quantity, 0), 0)
                ), 0) as sumsh
            FROM orders o
                JOIN auction au ON o.auction_number = au.auction_number
                    AND o.txnid = au.txnid
                left JOIN auction mau ON mau.auction_number = au.main_auction_number
                    AND mau.txnid = au.main_txnid
                left join auction_par_varchar au_country_shipping on IFNULL(mau.auction_number,au.auction_number)=au_country_shipping.auction_number
                    and au.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
                JOIN offer_group og ON au.offer_id = og.offer_id
                left JOIN article_list al ON o.article_list_id = al.article_list_id
                    and og.offer_group_id = al.group_id
                JOIN article a ON a.article_id = al.article_id
                JOIN offer of ON au.offer_id = of.offer_id
                JOIN country c ON IF(au.payment_method >2 or au_country_shipping.value='', '$defshcountry', au_country_shipping.value)=c.name
                JOIN shipping_plan_country spco ON spco.shipping_plan_id = ( IFNULL( (
                     SELECT value
                     FROM translation
                     WHERE table_name = 'offer'
                     AND field_name = '".$type."shipping_plan_id'
                     AND language = '$siteid'
                     AND id = of.offer_id
                     ), of.shipping_plan_id ) ) AND spco.country_code = c.code
                left JOIN shipping_plan_country spca ON spca.shipping_plan_id = ( IFNULL( (
                     SELECT value
                     FROM translation
                     WHERE table_name = 'article'
                     AND field_name = 'shipping_plan_id'
                     AND language = '$siteid'
                     AND id = a.article_id
                     ), a.shipping_plan_id ) ) AND spca.country_code = c.code
            WHERE 1=1 $where ");
        if (PEAR::isError($total_sum_cod)) {
            aprint_r($total_sum_cod);
        }

// calc ebay listing fee for the auction
        $listing_fee = $dbr->getOne("SELECT IFNULL(IFNULL(l.listing_fee, au.listing_fee)*au.quantity/
                (select sum( au1.quantity) from auction au1 where au1.auction_number = au.auction_number), 0) listing_fee
            FROM auction au
                LEFT JOIN listings l ON au.auction_number = l.auction_number
            WHERE 1=1 $where_au");
        if (PEAR::isError($listing_fee)) {
            aprint_r($listing_fee);
        }
        if (!$listing_fee) $listing_fee = 0;

// calc total quantity for the main group of the auction
        $r = $dbr->getAll("SELECT IFNULL(sum(o.quantity), 0) main_quantity, IFNULL(sum(o.quantity*o.price), 0) main_sum
            FROM orders o join auction au on o.auction_number=au.auction_number and o.txnid=au.txnid
                join offer_group og on au.offer_id=og.offer_id
                left JOIN article_list al ON o.article_list_id = al.article_list_id
                    and al.group_id=og.offer_group_id
            WHERE 2=2 $where
            and og.main=1");
        if (PEAR::isError($r)) {
            aprint_r($r);
        }
        $res = $r[0];
        $main_quantity = $res->main_quantity;
        $main_sum = $res->main_sum;
        if (!$main_quantity) $main_quantity = 'o.quantity';
        if (!$main_sum) $main_sum = '(o.quantity*o.price)';

// calc total sum for the auction
        $total_sum = $dbr->getOne("SELECT sum(o.quantity*o.price) total_sum
            FROM orders o join auction au on o.auction_number=au.auction_number and o.txnid=au.txnid
            WHERE 1=1 and manual=0 $where_au");
        if (PEAR::isError($total_sum)) {
            aprint_r($total_sum);
        }
    if (!$total_sum) $total_sum=0;
        $delres = $db->query("delete from auction_calcs WHERE 1=1 $where_d");
        if (PEAR::isError($delres)) {
            aprint_r($delres);
        }
        $subinvoices = (int)$dbr->getOne("select count(*) from auction where main_auction_number=$auction_number and txnid=$txnid");
        $shipping_cost_formula = "
            IF(si.free_shipping_total or (si.free_shipping and si.defshcountry=c1.code and $total_sum>si.free_shipping_above), 0,
                IF(o.manual in (3,2), 0, IF(au.txnid=3 and o.manual=4, i.total_shipping, IF($subinvoices and au.txnid=3 and o.manual<>4, 0
                    , IF (i.is_custom_shipping,
                    IF($total_sum = 0,
                        0,
                        o.price*o.quantity*(i.total_shipping)
                        /$total_sum
                    ),
                IF( (SELECT sum( o1.quantity )
                    FROM orders o1
                    JOIN auction au1 ON o1.auction_number = au1.auction_number
                        AND o1.txnid = au1.txnid
                    JOIN offer_group og1 ON au1.offer_id = og1.offer_id
                    JOIN article_list al1 ON al1.group_id = og1.offer_group_id
                        AND al1.article_list_id = o1.article_list_id
                    WHERE og1.offer_group_id = og.offer_group_id
                    AND au.auction_number = au1.auction_number
                    AND au.txnid = au1.txnid
                    ) >= og.noshipping and og.noshipping>0,
                    0,
                    IF (og.additional = 1,
                        IF(al.noship, 0, IFNULL(spca.shipping_cost, 0)),
                        IF (og.main = 1,
                            IFNULL(spco.shipping_cost, 0)"
                .(isZipInRange($db, $dbr, $auction_country_shipping, 'islands', $auction_zip_shipping) ? " + IFNULL(spco.island_cost, 0)" : "")." + al.additional_shipping_cost,
                            IFNULL(al.additional_shipping_cost, 0)
                        )
                    ) *o.quantity
                )
                )))))";
        $qry = "insert into auction_calcs
            (
                auction_number,
                txnid,
                article_list_id,
                price_sold,
                ebay_listing_fee,
                additional_listing_fee,
                ebay_commission,
                vat,
                purchase_price,
                shipping_cost,
                effective_shipping_cost,
                COD_cost,
                effective_COD_cost,
                curr_code,
                curr_rate,
                packing_cost,
                vat_shipping,
                vat_COD,
                order_id,
                vat_percent
            )
            (SELECT distinct
            ttt.auction_number,
            ttt.txnid,
            ttt.article_list_id,
            ttt.price_sold,
            ttt.ebay_listing_fee,
            ttt.additional_listing_fee,
            ttt.ebay_commission,
            ttt.vat,
            ttt.purchase_price,
            ttt.shipping_cost,
            ttt.effective_shipping_cost,
            ttt.COD_cost,
            ttt.effective_COD_cost,
            ttt.curr_code,
            ttt.curr_rate,
            ttt.packing_cost,
            (IFNULL(ttt.vat_percent, 0))*ttt.shipping_cost/(100+IFNULL(ttt.vat_percent, 0)) as vat_shipping,
            (IFNULL(ttt.vat_percent, 0))*ttt.COD_cost/(100+IFNULL(ttt.vat_percent, 0)) as vat_COD,
            ttt.order_id,
                vat_percent
            FROM (SELECT
                IF(IFNULL(mau.customer_vat,au.customer_vat)<>'', 0, v.vat_percent) vat_percent,
                o.quantity,
                o.auction_number,
                o.txnid,
                o.article_list_id,
                IF (
                    og.additional =1, o.price*o.quantity, o.price*o.quantity
                    ) AS price_sold,
                IF (0, 0, IF (og.main = 1, $listing_fee*o.quantity/$main_quantity, 0)) AS ebay_listing_fee,
                IF (0, 0, IF (og.main = 1, au.additional_listing_fee*o.quantity/$main_quantity, 0)) AS additional_listing_fee,
                IF(ss.provision, o.price*o.quantity*ss.provision/100,
                    IF($main_sum=0 or o.manual, 0, IF (o.hidden, 0,
                        IF(au.txnid in (0,2,3)
                        , o.price*o.quantity*`fget_Fee_Percent_channel`(au.winning_bid*au.quantity
                            + IF(exists (select null from saved_fee_algo sfa
                            join seller_information si on si.seller_channel_id=sfa.seller_channel_id
                            where sfa.fieldname='winning_bid'
                            and si.username='Amazon'
                            and `type`='withship'
                            limit 1), ($shipping_cost_formula), 0)
                        , IF(si.seller_channel_id=4 and o.txnid=2, 3, si.seller_channel_id))/$total_sum
                    , IFNULL(IF (og.main = 1,
                        (au.winning_bid*au.quantity * 0.11 / 115) * 100
                        /*IF (au.winning_bid <=25,
                            au.winning_bid * 0.0525,
                            IF (au.winning_bid <=1000,
                                1.31 + ( au.winning_bid -25 ) * 0.0275,
                                1.31 + 26.81 + ( au.winning_bid -1000 ) * 0.015)
                        )*/, 0)*o.quantity*o.price/$main_sum, 0)))))
                AS ebay_commission,
                IF(IFNULL(mau.customer_vat,au.customer_vat)<>'', 0, (IFNULL(v.vat_percent, 0))*o.price*o.quantity/(100+IFNULL(v.vat_percent, 0))) as vat,
                IF(o.manual=3
                    , -(select min(sold_for_amount) from shop_promo_codes where article_id=o.article_id)/(1+v.vat_percent/100)
                    , IFNULL(
                        (select article_import.total_item_cost from article_import
                            join country on country.code=article_import.country_code
                            where article_import.country_code=si.defcalcpricecountry
                            and article_import.article_id=a.article_id and o.manual=0
                            order by import_date desc limit 1)
                        ,(select article_import.total_item_cost from article_import
                                where article_import.article_id=a.article_id and o.manual=0
                                order by import_date desc limit 1)
/*						, IFNULL(
                            (select article_import.total_item_cost from article_import
                                where article_import.country_code=w.country_code
                                and article_import.article_id=a.article_id and o.manual=0
                                order by import_date desc limit 1)
                            ,(select article_import.total_item_cost from article_import
                                where article_import.article_id=a.article_id and o.manual=0
                                order by import_date desc limit 1)
                        )*/
                    )*o.quantity/$curr_rate) as purchase_price,

                $shipping_cost_formula as shipping_cost,

                IF(IFNULL(ss.free_shipping,0) OR o.hidden, 0, IFNULL(IF (og.additional = 1,
                    IF(spca.estimate, IFNULL(spca.real_additional_cost,0), IFNULL(scc_art.real_additional_cost, 0)),
                    IF (og.main = 1,
                        IF(spco.estimate, IFNULL(spco.real_shipping_cost,0), IFNULL(scc_off.real_shipping_cost, 0))"
                .(isZipInRange($db, $dbr, $auction_country_shipping, 'islands', $auction_zip_shipping)
                    ? " + IF(spco.estimate, IFNULL(spco.real_island_cost,0), IFNULL(scc_off.real_island_cost, 0))" : "").",
                        0
                    )
                )*o.quantity, 0))	AS effective_shipping_cost,

                IF(o.manual=4, i.total_cod+IFNULL(i.total_cc_fee,0), IF(o.manual in (1,2,3), 0, IFNULL(IF(au.payment_method='2',
                    IF (i.is_custom_cod,
                        IF($total_sum = 0,
                            0,
                            o.price*o.quantity*(i.total_cod)/$total_sum
                        ),
                        IF (og.main = 1,
                            IF($total_sum_cod=0, spco.COD_cost, o.price*o.quantity*spco.COD_cost/$total_sum_cod),
                        0)),
                    0
                ), 0))) as COD_cost,

                IF(o.hidden, 0, IFNULL(IF((au.payment_method='2' AND og.main = 1),
                    IF(spco.estimate, spco.real_COD_cost, scc_off.real_COD_cost), 0), 0))
                as effective_COD_cost,

                '$curr_code' as curr_code,
                $curr_rate as curr_rate,
                (IFNULL((SELECT SUM(asm.total_item_cost)
                                            FROM article asm
                                            JOIN article_sh_material asmsm
                                                ON asmsm.sh_material_id = asm.article_id
                                            WHERE asmsm.article_id = a.article_id), 0)
                    *o.quantity
                ) as packing_cost
            , o.id order_id
            FROM orders o
                left join warehouse w on o.send_warehouse_id=w.warehouse_id
                JOIN auction au ON o.auction_number = au.auction_number
                    AND o.txnid = au.txnid
                left JOIN auction mau ON mau.auction_number = au.main_auction_number
                    AND mau.txnid = au.main_txnid
                left join source_seller ss on ss.id=IFNULL(mau.source_seller_id,au.source_seller_id)
                left join auction_par_varchar au_country_shipping on IFNULL(mau.auction_number,au.auction_number)=au_country_shipping.auction_number
                    and au.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
                left JOIN article_list al ON o.article_list_id = al.article_list_id
                LEFT JOIN offer_group og ON au.offer_id = og.offer_id
                     AND al.group_id = og.offer_group_id
                LEFT JOIN article a ON a.article_id = al.article_id AND a.admin_id=o.manual
                JOIN invoice i ON au.invoice_number = i.invoice_number
                JOIN country c ON IF(au.payment_method >2 or au_country_shipping.value='', '$defshcountry', au_country_shipping.value)=c.name
                LEFT JOIN vat v on v.country_code=c.code and DATE(i.invoice_date) between v.date_from and v.date_to
                    and v.country_code_from=si.defshcountry
                LEFT JOIN offer of ON au.offer_id = of.offer_id
                LEFT JOIN shipping_plan sp_off ON sp_off.shipping_plan_id = ( IFNULL( (
                     SELECT value
                     FROM translation
                     WHERE table_name = 'offer'
                     AND field_name = '".$type."shipping_plan_id'
                     AND language = '$siteid'
                     AND id = of.offer_id
                     ), of.shipping_plan_id ) )
                LEFT JOIN shipping_plan sp_art ON sp_art.shipping_plan_id = ( IFNULL( (
                     SELECT value
                     FROM translation
                     WHERE table_name = 'article'
                     AND field_name = 'shipping_plan_id'
                     AND language = '$siteid'
                     AND id = a.article_id
                     ), a.shipping_plan_id ) )
                LEFT JOIN shipping_cost sc_off ON sp_off.shipping_cost_id = sc_off.id
                LEFT JOIN shipping_cost sc_art ON sp_art.shipping_cost_id = sc_art.id
                LEFT JOIN shipping_cost_country scc_off ON scc_off.shipping_cost_id = sc_off.id AND scc_off.country_code = c.code
                LEFT JOIN shipping_cost_country scc_art ON scc_art.shipping_cost_id = sc_art.id AND scc_art.country_code = c.code
                LEFT JOIN shipping_plan_country spco ON spco.shipping_plan_id = sp_off.shipping_plan_id AND spco.country_code = c.code
                LEFT JOIN shipping_plan_country spca ON spca.shipping_plan_id = sp_art.shipping_plan_id AND spca.country_code = c.code
            WHERE 1=1 $where AND NOT au.deleted) ttt
            ) ";
        file_put_contents('lastquery', $qry);
//			echo $qry;
        $r = $db->query($qry);
        if (PEAR::isError($r)) {
            aprint_r($r);
        }
//		echo $qry;
        $q = "select o.id order_id, o.article_list_id, au.payment_method, og.main, og.additional,
            spco.estimate, sp_off.shipping_plan_id, sp_off.curr_code,
            spco.real_shipping_cost, spco.real_COD_cost, spco.real_island_cost, spco.real_additional_cost,
            o.quantity,
            spca.estimate a_estimate, sp_art.shipping_plan_id a_shipping_plan_id, sp_art.curr_code a_curr_code,
            spca.real_shipping_cost a_real_shipping_cost, spca.real_COD_cost a_real_COD_cost,
            spca.real_island_cost a_real_island_cost, spca.real_additional_cost a_real_additional_cost,
            c.code country_code
            , ss.free_shipping
            FROM orders o
                JOIN auction au ON o.auction_number = au.auction_number
                    AND o.txnid = au.txnid
                left JOIN auction mau ON mau.auction_number = au.main_auction_number
                    AND mau.txnid = au.main_txnid
                left join source_seller ss on ss.id=IFNULL(mau.source_seller_id,au.source_seller_id)
                left join auction_par_varchar au_country_shipping on IFNULL(mau.auction_number,au.auction_number)=au_country_shipping.auction_number
                    and au.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
                left JOIN article_list al ON o.article_list_id = al.article_list_id
                LEFT JOIN offer_group og ON au.offer_id = og.offer_id
                     AND al.group_id = og.offer_group_id
                JOIN article a ON a.article_id = al.article_id AND a.admin_id=o.manual
                JOIN invoice i ON au.invoice_number = i.invoice_number
                JOIN country c ON IF(au.payment_method >2 or au_country_shipping.value='', '$defshcountry', au_country_shipping.value)=c.name
                LEFT JOIN vat v on v.country_code=c.code and DATE(i.invoice_date) between v.date_from and v.date_to
                    and v.country_code_from=si.defshcountry
                LEFT JOIN offer of ON au.offer_id = of.offer_id
                LEFT JOIN shipping_plan sp_off ON sp_off.shipping_plan_id = ( IFNULL( (
                     SELECT value
                     FROM translation
                     WHERE table_name = 'offer'
                     AND field_name = '".$type."shipping_plan_id'
                     AND language = '$siteid'
                     AND id = of.offer_id
                     ), of.shipping_plan_id ) )
                LEFT JOIN shipping_plan sp_art ON sp_art.shipping_plan_id = ( IFNULL( (
                     SELECT value
                     FROM translation
                     WHERE table_name = 'article'
                     AND field_name = 'shipping_plan_id'
                     AND language = '$siteid'
                     AND id = a.article_id
                     ), a.shipping_plan_id ) )
                LEFT JOIN shipping_cost sc_off ON sp_off.shipping_cost_id = sc_off.id
                LEFT JOIN shipping_cost sc_art ON sp_art.shipping_cost_id = sc_art.id
                LEFT JOIN shipping_cost_country scc_off ON scc_off.shipping_cost_id = sc_off.id AND scc_off.country_code = c.code
                LEFT JOIN shipping_cost_country scc_art ON scc_art.shipping_cost_id = sc_art.id AND scc_art.country_code = c.code
                LEFT JOIN shipping_plan_country spco ON spco.shipping_plan_id = sp_off.shipping_plan_id AND spco.country_code = c.code
                LEFT JOIN shipping_plan_country spca ON spca.shipping_plan_id = sp_art.shipping_plan_id AND spca.country_code = c.code
            WHERE 1=1 $where AND NOT au.deleted";
//		echo $q;
        $inserted = $dbr->getAll($q);
        if (PEAR::isError($inserted)) {
            aprint_r($inserted);
        }
        foreach ($inserted as $row) {
            if ($row->free_shipping) continue;
            if ($row->estimate) {
                if (($row->payment_method==2 && $row->main)) {
                    $effective_COD_cost = $fxrates[$row->curr_code.'US']/$fxrates[$curr_code.'US']
                                * $row->real_COD_cost;
                } else $effective_COD_cost = 0;
                if ($row->additional) {
                    $effective_shipping_cost = $fxrates[$row->a_curr_code.'US']/$fxrates[$curr_code.'US']
                                 * $row->a_real_additional_cost;
                }
                elseif ($row->main) {
                    $real_shipping_cost = $fxrates[$row->curr_code.'US']/$fxrates[$curr_code.'US']
                                 * $row->real_shipping_cost;
                    if (isZipInRange($db, $dbr, $auction->data->country_shipping, 'islands', $auction->data->zip_shipping)) {
                        $real_island_cost = $fxrates[$owr->curr_code.'US']/$fxrates[$curr_code.'US']
                                 * $row->real_island_cost;
                    } else $real_island_cost = 0;
                    $effective_shipping_cost = $real_shipping_cost + $real_island_cost;
                } else
                    $effective_shipping_cost = 0;
            } else { // not estimate
                $cod_shipping_plan_id = ShippingPlan::findLeaf($db, $dbr, $row->shipping_plan_id, $row->country_code, 'cod_diff_shipping_plan_id');
                $shipping_plan_id = ShippingPlan::findLeaf($db, $dbr, $row->shipping_plan_id, $row->country_code, 'diff_shipping_plan_id');
                if (!$shipping_plan_id) $shipping_plan_id = $row->shipping_plan_id;
                if (($row->payment_method==2 && $row->main)) {
                    $r = $dbr->getRow("select real_cod_cost, sc.curr_code
                        from shipping_cost_country scc_off
                        JOIN shipping_cost sc ON scc_off.shipping_cost_id = sc.id
                        WHERE scc_off.shipping_cost_id='$cod_shipping_plan_id' and country_code='$row->country_code'
                        ");
                          if (PEAR::isError($r)) aprint_r($r);
                    $effective_COD_cost = $fxrates[$r->curr_code.'US']/$fxrates['EURUS']
                                * $r->real_cod_cost;
                } else $effective_COD_cost = 0;
                if ($row->additional) {
                    $r = $dbr->getRow("select real_additional_cost, sc.curr_code
                        from shipping_cost_country scc_off
                        JOIN shipping_cost sc ON scc_off.shipping_cost_id = sc.id
                        JOIN shipping_plan sp_off ON sp_off.shipping_cost_id = scc_off.shipping_cost_id
                        WHERE sp_off.shipping_plan_id='$row->a_shipping_plan_id' and country_code='$row->country_code'
                     ");
                          if (PEAR::isError($r)) aprint_r($r);
                    $effective_shipping_cost = $fxrates[$r->curr_code.'US']/$fxrates['EURUS']
                                 * $r->real_additional_cost;
                }
                elseif ($row->main) {
                    $r = $dbr->getRow("select real_shipping_cost, sc.curr_code
                        from shipping_cost_country scc_off
                        JOIN shipping_cost sc ON scc_off.shipping_cost_id = sc.id
                        WHERE scc_off.shipping_cost_id='$shipping_plan_id' and country_code='$row->country_code'
                     ");
                          if (PEAR::isError($r)) aprint_r($r);
                    $real_shipping_cost = $fxrates[$r->curr_code.'US']/$fxrates['EURUS']
                                 * $r->real_shipping_cost;
                    if (isZipInRange($db, $dbr, $auction->data->country_shipping, 'islands', $auction->data->zip_shipping)) {
                            $r = $dbr->getRow("select real_island_cost, sc.curr_code
                           from shipping_cost_country scc_off
                           JOIN shipping_cost sc ON scc_off.shipping_cost_id = sc.id
                           JOIN shipping_plan sp_off ON sp_off.shipping_cost_id = scc_off.shipping_cost_id
                           WHERE sp_off.shipping_plan_id='$row->shipping_plan_id' and country_code='$row->country_code'
                            ");
                              if (PEAR::isError($r)) aprint_r($r);
                        $real_island_cost = $fxrates[$r->curr_code.'US']/$fxrates['EURUS']
                                 * $r->real_island_cost;
                    } else $real_island_cost = 0;
                    $effective_shipping_cost = $real_shipping_cost + $real_island_cost;
                } else
                    $effective_shipping_cost = 0;
            }
            $effective_shipping_cost *= $row->quantity;
            $q = "update auction_calcs set
                effective_shipping_cost = $effective_shipping_cost,
                effective_COD_cost = $effective_COD_cost
                WHERE 1=1 $where_d
                AND order_id = $row->order_id
                ";
            $updated = $db->query($q);
            if (PEAR::isError($updated)) aprint_r($updated);
        };
}

function getCalcsBy($db, $dbr, $from_number, $to_number,
    $from_date, $to_date,
    $from_date_confirmation, $to_date_confirmation,
    $from_date_inv, $to_date_inv,
    $from_date_paid, $to_date_paid,
    $auction_number, $txnid,
    $from_date_mship, $to_date_mship,
    $from_date_ship, $to_date_ship, $username, $source_seller_id, $extra_where = '', $payment_method = 0
    )
{
      global $seller_filter;
    if (strlen($seller_filter)) $seller_filter_str1 = " and au.username in ($seller_filter) ";
    $where = strlen($from_number) ? ' and a.article_id >= '.$from_number : '';
    $where .= strlen($to_number) ? ' and a.article_id <= '.$to_number : '';
    $where .= $from_date_mship ? " and fget_delivery_date_real(au.auction_number, au.txnid) >= '".$from_date_mship."' " : '';
    $where .= $to_date_mship ? " and fget_delivery_date_real(au.auction_number, au.txnid) <= '".$to_date_mship."' " : '';
    $where .= $from_date_ship ? " and exists (select 1 from tracking_numbers tn where
           o.auction_number = tn.auction_number and o.txnid = tn.txnid and tn.shipping_date >= '".$from_date_ship."') " : '';
    $where .= $to_date_ship ? "  and exists (select 1 from tracking_numbers tn where
           o.auction_number = tn.auction_number and o.txnid = tn.txnid and tn.shipping_date <= '".$to_date_ship."') " : '';
    $where .= $from_date ? " and au.end_time >= '".$from_date."' " : '';
    $where .= $to_date ? " and au.end_time <= '".$to_date."' " : '';
    if (is_array($source_seller_id)) {
        $where .= count($source_seller_id)?" and ifnull(ifnull(mau.source_seller_id,au.source_seller_id),0) in (".implode(',',$source_seller_id).") ": '';
    } else {
        $where .= $source_seller_id ? " and ifnull(mau.source_seller_id,au.source_seller_id) = ".$source_seller_id." " : '';
    }
    if (strlen($from_date_confirmation)) {
        $where .= " and (
            (au.confirmation_date >= '$from_date_confirmation' and au.confirmation_date <= '$to_date_confirmation')
            or
            (mau.confirmation_date >= '$from_date_confirmation' and mau.confirmation_date <= '$to_date_confirmation')
            )";
    }
    if (strlen($from_date_paid) || strlen($to_date_paid)) {
        $where .= " and exists (select null from payment where payment.auction_number=o.auction_number
            and payment.txnid=o.txnid "
            .(strlen($from_date_paid)? " and payment.payment_date >= '".$from_date_paid."' " : '')
            .(strlen($to_date_paid)? " and payment.payment_date <= '".$to_date_paid."' " : '')
        .")";
    }
    $where .= $from_date_inv ? " and i.invoice_date >= '".$from_date_inv."' " : '';
    $where .= $to_date_inv ? " and i.invoice_date <= '".$to_date_inv."' " : '';
    $where .= $auction_number ? " and IFNULL(mau.auction_number, o.auction_number) = '".$auction_number."' " : '';
    $where .= $txnid || $txnid=='0' ? " and IFNULL(mau.txnid, o.txnid) = '".$txnid."' " : '';
    if($payment_method){
        $where .= " and au.payment_method = '$payment_method' ";
    }
    if (strlen($username)) $where .= " and au.username in ($username) ";
    // SQL to calculate brutto
            $brutto_sql = "(
                            IFNULL(ac.price_sold,0) - IFNULL(ac.ebay_listing_fee,0) - IFNULL(ac.additional_listing_fee,0) - IFNULL(ac.ebay_commission,0) - IFNULL(ac.vat,0) - IFNULL(ac.purchase_price,0)
                        + IF(a.admin_id=4
                            ,IFNULL(i.total_shipping
                                , IFNULL(ac.shipping_cost,0)
                            )
                        , 0)
                            - IFNULL(ac.effective_shipping_cost,0)
                        + IF(a.admin_id=4
                            ,IFNULL(i.total_cod
                                ,IFNULL(ac.COD_cost,0)
                            )
                            - IFNULL(ac.effective_COD_cost,0)
                        , 0)
                            - IFNULL(ac.packing_cost,0)/IFNULL(ac.curr_rate,0)
                             -IFNULL(ac.vat_shipping,0)
                              -IFNULL(ac.vat_COD,0)
                            )
                    ";
    if (strlen($from_date_confirmation) || strlen($to_date_confirmation) || 1) $el = " LEFT JOIN email_log el
                    ON el.template = 'order_confirmation'
                    AND el.auction_number = au.auction_number
                    AND el.txnid = au.txnid ";
    $qry = "SELECT distinct IF(o.txnid=1,'A','F') type, o.article_list_id, au.siteid, DATE(au.end_time) end_time
                , IFNULL(
                    IF(o.manual=4,'Shipping cost',
                        IF(a.admin_id=2,
                (SELECT value FROM translation join shop_bonus on translation.id = shop_bonus.id
WHERE table_name = 'shop_bonus' AND field_name = 'title' AND language = IFNULL(mau.lang, au.lang)
AND shop_bonus.article_id = a.article_id limit 1)
                , IF(a.admin_id=3,
                (SELECT IF(shop_promo_codes.descr_is_name, shop_promo_codes.name,value) FROM translation join shop_promo_codes on translation.id = shop_promo_codes.id
WHERE table_name = 'shop_promo_codes' AND field_name = 'name' AND language = IFNULL(mau.lang, au.lang)
AND shop_promo_codes.article_id = a.article_id limit 1)
                ,IF(a.admin_id=0,(SELECT value
                FROM translation
                WHERE table_name = 'article'
                AND field_name = 'name'
                AND language = IFNULL(mau.lang, au.lang)
                AND id = a.article_id),o.custom_title))))
                    , IF(a.admin_id=3,(select name FROM shop_promo_codes where shop_promo_codes.article_id = a.article_id limit 1)
                    ,'Shipping cost')) name
                , IF(a.admin_id=2,
                (SELECT id FROM shop_bonus where shop_bonus.article_id = a.article_id limit 1)
                , IF(a.admin_id=3,
                (SELECT id FROM shop_promo_codes where shop_promo_codes.article_id = a.article_id limit 1)
                ,a.article_id)) real_id
                , IF(a.admin_id=3,-ROUND(ac.purchase_price*(1+ac.vat_percent/100), 2),0) income_voucher_cost
                , o.manual as article_admin, o.auction_number, o.txnid, o.article_id,
                ac.purchase_price as purchase_price,
                ac.price_sold as price_sold,
                ac.ebay_listing_fee as ebay_listing_fee,
                ac.additional_listing_fee as additional_listing_fee,
                ac.ebay_commission as ebay_commission,
                ac.vat as vat,
                (ac.price_sold - ac.ebay_listing_fee - ac.additional_listing_fee - ac.ebay_commission - ac.vat) as netto_sales_price,
                (ac.price_sold - ac.ebay_listing_fee - ac.additional_listing_fee - ac.ebay_commission - ac.vat - ac.purchase_price) as brutto_income,
                (ac.shipping_cost-ac.vat_shipping) as shipping_cost,
                ac.effective_shipping_cost as effective_shipping_cost,
                ac.vat_shipping,
                (ac.COD_cost-ac.vat_COD) as COD_cost,
                ac.effective_COD_cost as effective_COD_cost,
                ac.vat_COD,
                ac.packing_cost as packing_cost_EUR,
                (ac.shipping_cost-ac.vat_shipping-ac.effective_shipping_cost
                    +ac.COD_cost-ac.vat_COD-effective_COD_cost) as income_shipping_cost,
                    ROUND({$brutto_sql}, 2) as brutto_income_2,
                    ROUND({$brutto_sql}/o.quantity, 2) as brutto_income_3,
                o.quantity,
                o.price,
                au.confirmation_date confirm_date,
                CONCAT(IFNULL(mau.auction_number, o.auction_number),'/',IFNULL(mau.txnid, o.txnid)) main_auction_number_txnid,
                IFNULL(mau.auction_number, o.auction_number) main_auction_number,
                IFNULL(mau.txnid, o.txnid) main_txnid,
                    au.siteid, au.username, si.seller_channel_id
                , (select GROUP_CONCAT(c.number order by co.level separator '.' )
                    from classifier_obj co
                    join classifier c on co.classifier_id=c.id
                    where obj='article' and obj_id=a.iid) classifier
                , (select GROUP_CONCAT(c.number order by co.level separator '.' )
                    from classifier_obj co
                    join classifier c on co.classifier_id=c.id
                    where obj='offer' and obj_id=offer.offer_id) offer_classifier
                , ss.name source_seller
                , REPLACE(a.picture_URL,'_image.jpg','_x_200_image.jpg') picture_URL_200
                FROM orders o JOIN auction au ON o.auction_number = au.auction_number
                        AND o.txnid = au.txnid
                    left join offer on au.offer_id=offer.offer_id
                    join seller_information si on au.username=si.username
                LEFT JOIN auction mau ON (mau.auction_number = au.main_auction_number
                    AND mau.txnid = au.main_txnid)
                JOIN invoice i ON i.invoice_number = au.invoice_number
                left JOIN article_list al ON  o.article_list_id = al.article_list_id
                left JOIN article a ON a.article_id = o.article_id AND a.admin_id=o.manual
                LEFT JOIN auction_calcs ac ON o.article_list_id = ac.article_list_id
                    AND o.auction_number = ac.auction_number
                    AND o.txnid = ac.txnid
                    and ac.article_id=o.article_id
                left join shop_bonus sb on sb.article_id=o.article_id and o.manual=2
                left join source_seller ss on ss.id=ifnull(mau.source_seller_id,au.source_seller_id)
            WHERE 1=1 and o.hidden=0 $where  AND au.deleted=0 and au.txnid<>4
            and ((o.manual=2 and sb.show_in_calc) or (o.manual<>2))
            $seller_filter_str1
            {$extra_where}
            ORDER BY main_auction_number_txnid, article_list_id";
        $r = $dbr->query($qry);
        if (PEAR::isError($r)) {
            print_r($r);
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $curr_rate = 1*$dbr->getOne("select curr_rate from auction au
                LEFT JOIN auction mau ON mau.auction_number = au.main_auction_number AND mau.txnid = au.main_txnid
                LEFT JOIN auction_calcs ac ON IFNULL(mau.auction_number, au.auction_number) = ac.auction_number
                        AND IFNULL(mau.txnid, au.txnid) = ac.txnid
                where au.auction_number = {$article->auction_number} AND au.txnid = {$article->txnid}
                limit 1
                ");
                    $article->income_voucher_cost_EUR = round($article->income_voucher_cost*$curr_rate,2);
                    $article->purchase_price_EUR = round($article->purchase_price*$curr_rate,2);
                    $article->price_sold_EUR = round($article->price_sold*$curr_rate,2);
                    $article->ebay_listing_fee_EUR = round($article->ebay_listing_fee*$curr_rate,2);
                    $article->additional_listing_fee_EUR = round($article->additional_listing_fee*$curr_rate,2);
                    $article->ebay_commission_EUR = round($article->ebay_commission*$curr_rate,2);
                    $article->vat_EUR = round($article->vat*$curr_rate,2);
                    $article->netto_sales_price_EUR = round($article->netto_sales_price*$curr_rate,2);
                    $article->brutto_income_EUR = round($article->brutto_income*$curr_rate,2);
                    $article->shipping_cost_EUR = round($article->shipping_cost*$curr_rate,2);
                    $article->effective_shipping_cost_EUR = round($article->effective_shipping_cost*$curr_rate,2);
                    $article->vat_shipping_EUR = round($article->vat_shipping*$curr_rate,2);
                    $article->COD_cost_EUR = round($article->COD_cost*$curr_rate,2);
                    $article->effective_COD_cost_EUR = round($article->effective_COD_cost*$curr_rate,2);
                    $article->vat_COD_EUR = round($article->vat_COD*$curr_rate,2);
                    $article->income_shipping_cost_EUR = round($article->income_shipping_cost*$curr_rate,2);
                    $article->brutto_income_2_EUR = round($article->brutto_income_2*$curr_rate,2);
                    $article->brutto_income_3_EUR = round($article->brutto_income_3*$curr_rate,2);
                    $article->packing_cost = round($article->packing_cost_EUR/$curr_rate,2);

                $article->site_country = countryCodeToCountry( siteToCountryCode($article->siteid));
                $article->txnid_type = $article->txnid==3?'Shop':
                    ($article->txnid<=1?'Auction':
                        ($article->txnid>3?'Fixed':
                            ($article->txnid==2?($article->seller_channel_id==3?'Amazon':'Fixed')
                                :'')));
                $article->revenue = $article->price_sold + $article->shipping_cost + $article->COD_cost + $article->vat_COD + $article->vat_shipping + $article->income_voucher_cost;
                $article->revenue_EUR = $article->price_sold_EUR + $article->shipping_cost_EUR + $article->COD_cost_EUR + $article->vat_COD_EUR + $article->vat_shipping_EUR + $article->income_voucher_cost_EUR;
            $list[] = $article;
        }
        return $list;
}

function getCalcBy($db, $dbr, $article_list_id, $auction_number, $txnid, $article_id)
{
    $where .= $auction_number ? " and o.article_list_id = '".$article_list_id."' " : '';
    $where .= $auction_number ? " and o.auction_number = '".$auction_number."' " : '';
    $where .= $auction_number ? " and o.article_id = '".$article_id."' " : '';
    $where .= $txnid || $txnid=='0' ? " and o.txnid = '".$txnid."' " : '';
    // SQL to calculate brutto
            $brutto_sql = "(
                            IFNULL(ac.price_sold,0) - IFNULL(ac.ebay_listing_fee,0) - IFNULL(ac.additional_listing_fee,0) - IFNULL(ac.ebay_commission,0) - IFNULL(ac.vat,0) - IFNULL(ac.purchase_price,0)
                        + IF(a.admin_id=4
                            ,IFNULL(i.total_shipping
                                , IFNULL(ac.shipping_cost,0)
                            )
                        , 0)
                            - IFNULL(ac.effective_shipping_cost,0)
                        + IF(a.admin_id=4
                            ,IFNULL(i.total_cod
                                ,IFNULL(ac.COD_cost,0)
                            )
                            - IFNULL(ac.effective_COD_cost,0)
                        , 0)
                            - IFNULL(ac.packing_cost,0)/IFNULL(ac.curr_rate,0)
                             -IFNULL(ac.vat_shipping,0)
                              -IFNULL(ac.vat_COD,0)
                            )
                    ";
    $qry = "SELECT au.siteid, au.end_time, au_country_shipping.value country_shipping, au_zip_shipping.value zip_shipping
                , IFNULL((SELECT value
                FROM translation
                WHERE table_name = 'article'
                AND field_name = 'name'
                AND language = au.lang
                AND id = o.article_id)
            , IF(o.manual=4,'Shipping cost', IF(a.admin_id=3,(select name FROM shop_promo_codes where shop_promo_codes.article_id = a.article_id limit 1)
                    ,'Shipping cost'))) name
                , a.admin_id as article_admin, o.auction_number, o.txnid, o.article_id,
                ac.purchase_price as purchase_price, ROUND(ac.purchase_price*ac.curr_rate, 2) as purchase_price_EUR,
                ac.price_sold as price_sold, ROUND(ac.price_sold*ac.curr_rate, 2) as price_sold_EUR,
                ac.ebay_listing_fee as ebay_listing_fee, ROUND(ac.ebay_listing_fee*ac.curr_rate, 2) as ebay_listing_fee_EUR,
                ac.additional_listing_fee as additional_listing_fee, ROUND(ac.additional_listing_fee*ac.curr_rate, 2) as additional_listing_fee_EUR,
                ac.ebay_commission as ebay_commission, ROUND(ac.ebay_commission*ac.curr_rate, 2) as ebay_commission_EUR,
                ac.vat as vat, ROUND(ac.vat*ac.curr_rate, 2) as vat_EUR,
                (ac.price_sold - ac.ebay_listing_fee - ac.additional_listing_fee - ac.ebay_commission - ac.vat) as netto_sales_price,
                ROUND((ac.price_sold - ac.ebay_listing_fee - ac.additional_listing_fee - ac.ebay_commission - ac.vat)*ac.curr_rate, 2) as netto_sales_price_EUR,
                (ac.price_sold - ac.ebay_listing_fee - ac.additional_listing_fee - ac.ebay_commission - ac.vat - ac.purchase_price) as brutto_income,
                ROUND((ac.price_sold - ac.ebay_listing_fee - ac.additional_listing_fee - ac.ebay_commission - ac.vat - ac.purchase_price)*ac.curr_rate, 2) as brutto_income_EUR,
                (ac.shipping_cost-ac.vat_shipping) as shipping_cost, ROUND((ac.shipping_cost-ac.vat_shipping)*ac.curr_rate, 2) as shipping_cost_EUR,
                ac.effective_shipping_cost as effective_shipping_cost, ROUND(ac.effective_shipping_cost*ac.curr_rate, 2) as effective_shipping_cost_EUR,
                ac.vat_shipping, ROUND(ac.vat_shipping*ac.curr_rate, 2) as vat_shipping_EUR,
                (ac.COD_cost-ac.vat_COD) as COD_cost, ROUND((ac.COD_cost-ac.vat_COD)*ac.curr_rate, 2) as COD_cost_EUR,
                ac.effective_COD_cost as effective_COD_cost, ROUND(ac.effective_COD_cost*ac.curr_rate, 2) as effective_COD_cost_EUR,
                ac.vat_COD, ROUND(ac.vat_COD*ac.curr_rate, 2) as vat_COD_EUR,
                ac.packing_cost as packing_cost_EUR, ROUND(ac.packing_cost/ac.curr_rate, 2) as packing_cost,
                (ac.shipping_cost-ac.vat_shipping-ac.effective_shipping_cost
                    +ac.COD_cost-ac.vat_COD-effective_COD_cost) as income_shipping_cost,
                ROUND((ac.shipping_cost-ac.vat_shipping-ac.effective_shipping_cost
                    +ac.COD_cost-ac.vat_COD-effective_COD_cost)*ac.curr_rate, 2) as income_shipping_cost_EUR,
                    ROUND({$brutto_sql}, 2) as brutto_income_2,
                    ROUND({$brutto_sql}*IFNULL(ac.curr_rate,0), 2) as brutto_income_2_EUR,
                    ROUND({$brutto_sql}/o.quantity, 2) as brutto_income_3,
                    ROUND({$brutto_sql}*IFNULL(ac.curr_rate,0)/o.quantity, 2) as brutto_income_3_EUR,
                o.quantity,
                o.price,
                    au.siteid, au.username, si.seller_channel_id
                , (select GROUP_CONCAT(c.number order by co.level separator '.' )
                    from classifier_obj co
                    join classifier c on co.classifier_id=c.id
                    where obj='article' and obj_id=a.iid) classifier
                , (select GROUP_CONCAT(c.number order by co.level separator '.' )
                    from classifier_obj co
                    join classifier c on co.classifier_id=c.id
                    where obj='offer' and obj_id=offer.offer_id) offer_classifier
                , au.confirmation_date confirm_date
                , ss.name source_seller
                , IF(a.admin_id=3,-ROUND(ac.purchase_price*(1+ac.vat_percent/100),2),0) income_voucher_cost
                , IF(a.admin_id=3,-ROUND(ac.purchase_price*ac.curr_rate*(1+ac.vat_percent/100), 2),0) income_voucher_cost_EUR
                FROM orders o JOIN auction au ON o.auction_number = au.auction_number
                        AND o.txnid = au.txnid
                    left join offer on au.offer_id=offer.offer_id
                    join seller_information si on au.username=si.username
                left join auction_par_varchar au_country_shipping on au.auction_number=au_country_shipping.auction_number
                    and au.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
                left join auction_par_varchar au_zip_shipping on au.auction_number=au_zip_shipping.auction_number
                    and au.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
                JOIN invoice i ON i.invoice_number = au.invoice_number
                left JOIN article_list al ON  (o.article_list_id = al.article_list_id )
                    OR ( o.manual AND o.article_list_id = al.article_list_id )
                LEFT JOIN offer_group og ON og.offer_group_id = al.group_id
                left JOIN article a ON a.article_id = o.article_id AND a.admin_id=o.manual
                LEFT JOIN auction_calcs ac ON o.article_list_id = ac.article_list_id
                    AND o.auction_number = ac.auction_number
                    AND o.txnid = ac.txnid
                    AND o.article_id = ac.article_id
                left join shop_bonus sb on sb.article_id=o.article_id and o.manual=2
                left join source_seller ss on ss.id=au.source_seller_id
            WHERE 1=1 and o.hidden=0 $where  AND NOT au.deleted
            and ((o.manual=2 and sb.show_in_calc) or (o.manual<>2))
            ORDER BY o.auction_number, o.txnid, og.offer_id, og.position, al.position";
        $r = $db->query($qry);
//		echo $qry; //die();
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        $list = array();
        while ($article = $r->fetchRow()) {
            $curr_rate = 1*$dbr->getOne("select curr_rate from auction au
                LEFT JOIN auction mau ON mau.auction_number = au.main_auction_number AND mau.txnid = au.main_txnid
                LEFT JOIN auction_calcs ac ON IFNULL(mau.auction_number, au.auction_number) = ac.auction_number
                        AND IFNULL(mau.txnid, au.txnid) = ac.txnid
                where au.auction_number = {$article->auction_number} AND au.txnid = {$article->txnid}
                limit 1
                ");
                    $article->income_voucher_cost_EUR = round($article->income_voucher_cost*$curr_rate,2);
                    $article->purchase_price_EUR = round($article->purchase_price*$curr_rate,2);
                    $article->price_sold_EUR = round($article->price_sold*$curr_rate,2);
                    $article->ebay_listing_fee_EUR = round($article->ebay_listing_fee*$curr_rate,2);
                    $article->additional_listing_fee_EUR = round($article->additional_listing_fee*$curr_rate,2);
                    $article->ebay_commission_EUR = round($article->ebay_commission*$curr_rate,2);
                    $article->vat_EUR = round($article->vat*$curr_rate,2);
                    $article->netto_sales_price_EUR = round($article->netto_sales_price*$curr_rate,2);
                    $article->brutto_income_EUR = round($article->brutto_income*$curr_rate,2);
                    $article->shipping_cost_EUR = round($article->shipping_cost*$curr_rate,2);
                    $article->effective_shipping_cost_EUR = round($article->effective_shipping_cost*$curr_rate,2);
                    $article->vat_shipping_EUR = round($article->vat_shipping*$curr_rate,2);
                    $article->COD_cost_EUR = round($article->COD_cost*$curr_rate,2);
                    $article->effective_COD_cost_EUR = round($article->effective_COD_cost*$curr_rate,2);
                    $article->vat_COD_EUR = round($article->vat_COD*$curr_rate,2);
                    $article->income_shipping_cost_EUR = round($article->income_shipping_cost*$curr_rate,2);
                    $article->brutto_income_2_EUR = round($article->brutto_income_2*$curr_rate,2);
                    $article->brutto_income_3_EUR = round($article->brutto_income_3*$curr_rate,2);
                    $article->packing_cost = round($article->packing_cost_EUR/$curr_rate,2);

                $article->site_country = countryCodeToCountry( siteToCountryCode($article->siteid));
                $article->txnid_type = $article->txnid==3?'Shop':
                    ($article->txnid<=1?'Auction':
                        ($article->txnid>3?'Fixed':
                            ($article->txnid==2?($article->seller_channel_id==3?'Amazon':'Fixed')
                                :'')));
                $article->revenue = $article->price_sold + $article->shipping_cost + $article->COD_cost + $article->vat_COD + $article->vat_shipping + $article->income_voucher_cost;
                $article->revenue_EUR = $article->price_sold_EUR + $article->shipping_cost_EUR + $article->COD_cost_EUR + $article->vat_COD_EUR + $article->vat_shipping_EUR + $article->income_voucher_cost_EUR;
            $list[] = $article;
        }
        return $list;
}

function prereserveAuction($db, $dbr, $auction, $onlymain = true, $force_reserve = false) {
    require_once 'lib/ArticleHistory.php';
    require_once 'lib/Offer.php';
    require_once 'lib/Order.php';
    require_once 'lib/Article.php';
    $offer = new Offer($db, $dbr, $auction->get('offer_id'));
    if ($offer->get('confirmed_reserve_only') && !$force_reserve) return;

    $auction_number = $auction->get('auction_number');
    $txnid = $auction->get('txnid');
    $main = $onlymain ? ' and og.main ' : '';

    if ($auction_number) {
        $q = "SELECT au.auction_number, al.article_id, 1 AS quantity, IF(og.main, au.winning_bid, a.purchase_price) AS price, au.txnid, al.article_list_id
        FROM article_list al
            JOIN article a ON al.article_id = a.article_id
                AND NOT a.admin_id
            JOIN offer_group og ON al.group_id = og.offer_group_id
            JOIN auction au ON au.offer_id = og.offer_id
        WHERE auction_number = ".$auction_number."
        AND txnid = ".$txnid.$main;
//		echo $q;
        $r = $db->query($q);
        if (PEAR::isError($r)) {
            return;
        }
        while ($rec = $r->fetchRow()) {
            $order[] = array(
                    '',
                    $auction->get('quantity'),
                    $rec->price,
                    $rec->article_id,
                    0,
                    '',
                    $rec->article_list_id
                );
        }
        Order::Create($db, $dbr, $auction_number, $txnid, $order);
    }
}

function get_default_direction($db, $dbr, $what)
{
    return $dbr->getOne("select direction from SR_default_order where what='".$what."'");
}
function get_default_sort($db, $dbr, $what)
{
    return $dbr->getOne("select sort from SR_default_order where what='".$what."'");
}
function set_default_sort($db, $dbr, $what, $sort)
{
    $db->query("REPLACE INTO SR_default_order SET what='".$what."', sort='".$sort."'");
}
function set_default_direction($db, $dbr, $what, $direction)
{
    $db->query("REPLACE INTO SR_default_order SET what='".$what."', direction='".$direction."'");
}

function getRates() {
    global $db, $dbr;
    $rates = array();
    $r = $dbr->getOne("select value from rate where TO_DAYS(`date`)=TO_DAYS(now()) and curr_code='CZK'");
    if (PEAR::isError($r)) aprint_r($r);
    if (!$r && Config::get($db, $dbr, 'getRates')) {
        $fh = @fopen('http://www.x-rates.com/table/?from=CZK','r');
        if($fh) {
            while(!feof($fh)) @$a.=fread($fh,4096);
            fclose($fh);
            $a=substr($a, strpos($a, "<a href='/graph/?from=CZK&amp;to=USD'>")
                  +strlen("<a href='/graph/?from=CZK&amp;to=USD'>"));
            $rates = array_merge((array)$rates,array('CZKUS' => round(substr($a, 0, strpos($a, '<')), 5)));
            $r = $db->query("replace into rate set `date`=now(), curr_code='CZK', value=".$rates['CZKUS']);
            if (PEAR::isError($r)) aprint_r($r);
        } else {echo 'cant open http://www.x-rates.com/table/?from=CZK';}
    } else {
            $rates = array_merge((array)$rates,array('CZKUS' => $r));
    }
    $r = $dbr->getOne("select value from rate where TO_DAYS(`date`)=TO_DAYS(now()) and curr_code='DKK'");
    if (PEAR::isError($r)) aprint_r($r);
    if (!$r && Config::get($db, $dbr, 'getRates')) {
        $fh = @fopen('http://www.x-rates.com/table/?from=DKK','r');
        if($fh) {
            while(!feof($fh)) @$a.=fread($fh,4096);
            fclose($fh);
            $a=substr($a, strpos($a, "<a href='/graph/?from=DKK&amp;to=USD'>")
                  +strlen("<a href='/graph/?from=DKK&amp;to=USD'>"));
            $rates = array_merge((array)$rates,array('DKKUS' => round(substr($a, 0, strpos($a, '<')), 5)));
            $r = $db->query("replace into rate set `date`=now(), curr_code='DKK', value=".$rates['DKKUS']);
            if (PEAR::isError($r)) aprint_r($r);
        } else {echo 'cant open http://www.x-rates.com/table/?from=DKK';}
    } else {
            $rates = array_merge((array)$rates,array('DKKUS' => $r));
    }
    $r = $dbr->getOne("select value from rate where TO_DAYS(`date`)=TO_DAYS(now()) and curr_code='SEK'");
    if (PEAR::isError($r)) aprint_r($r);
    if (!$r && Config::get($db, $dbr, 'getRates')) {
        $fh = @fopen('http://www.x-rates.com/table/?from=SEK','r');
        if($fh) {
            while(!feof($fh)) @$a.=fread($fh,4096);
            fclose($fh);
            $a=substr($a, strpos($a, "<a href='/graph/?from=SEK&amp;to=USD'>")
                  +strlen("<a href='/graph/?from=SEK&amp;to=USD'>"));
            $rates = array_merge((array)$rates,array('SEKUS' => round(substr($a, 0, strpos($a, '<')), 5)));
            $r = $db->query("replace into rate set `date`=now(), curr_code='SEK', value=".$rates['SEKUS']);
            if (PEAR::isError($r)) aprint_r($r);
        } else {echo 'cant open http://www.x-rates.com/table/?from=SEK';}
    } else {
            $rates = array_merge((array)$rates,array('SEKUS' => $r));
    }
    $r = $dbr->getOne("select value from rate where TO_DAYS(`date`)=TO_DAYS(now()) and curr_code='EUR'");
    if (PEAR::isError($r)) aprint_r($r);
    if (!$r && Config::get($db, $dbr, 'getRates')) {
        $fh = @fopen('http://www.x-rates.com/table/?from=EUR','r');
        if($fh) {
            while(!feof($fh)) @$a.=fread($fh,4096);
            fclose($fh);
/*			$a=substr($a, strpos($a, '<span class="s1">')+strlen('<span class="s1">'));
            $rates = array_merge($rates,array('EURUS' => substr($a, 0, strpos($a, '</span>'))));
            $a=substr($a, strpos($a, "curUSD2X['EUR'] = new currency(")+strlen("curUSD2X['EUR'] = new currency("));
            substr($a, 0, strpos($a, ','));*/
            $a=substr($a, strpos($a, "<a href='/graph/?from=EUR&amp;to=USD'>")
                  +strlen("<a href='/graph/?from=EUR&amp;to=USD'>"));
            $rates = array_merge((array)$rates,array('EURUS' => round(substr($a, 0, strpos($a, '<')), 5)));
            $r = $db->query("replace into rate set `date`=now(), curr_code='EUR', value=".$rates['EURUS']);
            if (PEAR::isError($r)) aprint_r($r);
        } else {echo 'cant open http://www.x-rates.com/table/?from=EUR';}
    } else {
            $rates = array_merge((array)$rates,array('EURUS' => $r));
    }
    $r = $dbr->getOne("select value from rate where TO_DAYS(`date`)=TO_DAYS(now()) and curr_code='CAD'");
    if (PEAR::isError($r)) aprint_r($r);
    if (!$r && Config::get($db, $dbr, 'getRates')) {
        $fh = @fopen('http://www.x-rates.com/table/?from=CAD','r');
        if($fh) {
            while(!feof($fh)) @$a.=fread($fh,4096);
            fclose($fh);
/*			$a=substr($a, strpos($a, '<span class="s1">')+strlen('<span class="s1">'));
            $rates = array_merge($rates,array('EURUS' => substr($a, 0, strpos($a, '</span>'))));
            $a=substr($a, strpos($a, "curUSD2X['EUR'] = new currency(")+strlen("curUSD2X['EUR'] = new currency("));
            substr($a, 0, strpos($a, ','));*/
            $a=substr($a, strpos($a, "<a href='/graph/?from=CAD&amp;to=USD'>")
                  +strlen("<a href='/graph/?from=CAD&amp;to=USD'>"));
            $rates = array_merge((array)$rates,array('CADUS' => round(substr($a, 0, strpos($a, '<')), 5)));
            $r = $db->query("replace into rate set `date`=now(), curr_code='CAD', value=".$rates['CADUS']);
            if (PEAR::isError($r)) aprint_r($r);
        } else {echo 'cant open http://www.x-rates.com/table/?from=CAD';}
    } else {
            $rates = array_merge((array)$rates,array('CADUS' => $r));
    }
    $r = $dbr->getOne("select value from rate where TO_DAYS(`date`)=TO_DAYS(now()) and curr_code='GBP'");
    if (PEAR::isError($r)) aprint_r($r);
    if (!$r && Config::get($db, $dbr, 'getRates')) {
        $fh = @fopen('http://www.x-rates.com/table/?from=GBP','r');
        if($fh) {
            while(!feof($fh)) @$a.=fread($fh,4096);
            fclose($fh);
            $a=substr($a, strpos($a, "<a href='/graph/?from=GBP&amp;to=USD'>")
                  +strlen("<a href='/graph/?from=GBP&amp;to=USD'>"));
            $rates = array_merge((array)$rates,array('GBPUS' => round(substr($a, 0, strpos($a, '<')), 5)));
            $r = $db->query("replace into rate set `date`=now(), curr_code='GBP', value=".$rates['GBPUS']);
            if (PEAR::isError($r)) aprint_r($r);
        } else {echo 'cant open http://www.x-rates.com/table/?from=GBP';}
    } else {
            $rates = array_merge((array)$rates,array('GBPUS' => $r));
    }
    $r = $dbr->getOne("select value from rate where TO_DAYS(`date`)=TO_DAYS(now()) and curr_code='CHF'");
    if (PEAR::isError($r)) aprint_r($r);
    if (!$r && Config::get($db, $dbr, 'getRates')) {
        $fh = @fopen('http://www.x-rates.com/table/?from=CHF','r');
        if($fh) {
            while(!feof($fh)) @$a.=fread($fh,4096);
            fclose($fh);
            $a=substr($a, strpos($a, "<a href='/graph/?from=CHF&amp;to=USD'>")
                  +strlen("<a href='/graph/?from=CHF&amp;to=USD'>"));
            $rates = array_merge((array)$rates,array('CHFUS' => round(substr($a, 0, strpos($a, '<')), 5)));
            $r = $db->query("replace into rate set `date`=date(now()), curr_code='CHF', value=".$rates['CHFUS']);
            if (PEAR::isError($r)) aprint_r($r);
        };
    } else {
            $rates = array_merge((array)$rates,array('CHFUS' => $r));
    }
    $r = $dbr->getOne("select value from rate where TO_DAYS(`date`)=TO_DAYS(now()) and curr_code='PLN'");
    if (PEAR::isError($r)) aprint_r($r);
    if (($r*1)==0 && Config::get($db, $dbr, 'getRates')) {
        $fh = @fopen('http://www.x-rates.com/table/?from=PLN','r');
        if($fh) {
            while(!feof($fh)) @$a.=fread($fh,4096);
            fclose($fh);
            $a=substr($a, strpos($a, "<a href='/graph/?from=PLN&amp;to=USD'>")
                  +strlen("<a href='/graph/?from=PLN&amp;to=USD'>"));
            $rates = array_merge((array)$rates,array('PLNUS' => round(substr($a, 0, strpos($a, '<')), 5)));
            $r = $db->query("delete from rate where `date`=date(now()) and curr_code='PLN'");
            $r = $db->query("replace into rate set `date`=date(now()), curr_code='PLN', value=".$rates['PLNUS']);
            if (PEAR::isError($r)) aprint_r($r);
        };
    } else {
            $rates = array_merge((array)$rates,array('PLNUS' => $r));
    }
    $r = $dbr->getOne("select value from rate where TO_DAYS(`date`)=TO_DAYS(now()) and curr_code='HUF'");
    if (PEAR::isError($r)) aprint_r($r);
    if (($r*1)==0 && Config::get($db, $dbr, 'getRates')) {
        $fh = @fopen('http://www.x-rates.com/table/?from=HUF','r');
        if($fh) {
            while(!feof($fh)) @$a.=fread($fh,4096);
            fclose($fh);
            $a=substr($a, strpos($a, "<a href='/graph/?from=HUF&amp;to=USD'>")
                  +strlen("<a href='/graph/?from=HUF&amp;to=USD'>"));
            $rates = array_merge((array)$rates,array('HUFUS' => round(substr($a, 0, strpos($a, '<')), 5)));
            $r = $db->query("delete from rate where `date`=date(now()) and curr_code='HUF'");
            $r = $db->query("replace into rate set `date`=date(now()), curr_code='HUF', value=".$rates['HUFUS']);
            if (PEAR::isError($r)) aprint_r($r);
        };
    } else {
            $rates = array_merge((array)$rates,array('HUFUS' => $r));
    }
    $rates = array_merge((array)$rates,array('USDUS' => 1));
    return $rates;
}

function scan_auction($db, $dbr, $number, $txnid, $seller)
{
global $devId, $appId, $certId;
        $auction = new Auction($db, $dbr, $number, $txnid);
        $seller->geteBay($devId, $appId, $certId);
        $eBay = new Services_Ebay( $devId, $appId, $certId, $auction->get('siteid'));
// !!!!!!!!!delete
//		$seller1 = SellerInfo::Singleton($db, $dbr, 'basertest');
//        $eBay->setAuth( $seller1->get('aatoken') );
// !!!!!!!!!delete
        $eBay->setAuth( $seller->get('aatoken') );
        if ($txnid==1) {
            $item = $eBay->getItem($number, 'ReturnAll');
            print_r($item); //die();
            if (PEAR::isError($item)) {
                $msg = $seller->get('username').' scan_auction Error: '.$item->toString()  . ' ' . __LINE__;
                echo $msg;
                adminEmail($msg,'admin',$auction->get('username'),$auction->get('saved_id'));
                return;
            }
            if ($item['TimeLeft'] == 'PT0S') {
                if (
                    (
                        ($item['ReservePrice']>0 && $item['SellingStatus']['ReserveMet']) ||
                        ($item['ReservePrice'] == 0)
                    ) && $item['SellingStatus']['HighBidder']['UserID']
                   ) {
                    AuctionWonUtil($db, $dbr, $item);
                } else {
                    AuctionCancelledUtil($db, $dbr, $item);
                }
            }
        } else {
            processMultiItemsUtil($seller, $number, $txnid);
        }
}

function AuctionWonUtil($db, $dbr, $item)
{
    $auction = new Auction($db, $dbr, $item['ItemID'], 1);
    $auction->set('process_stage',STAGE_WON);
    $siteid = CountryCodeToSite($item['Site']);
    if (strlen($siteid)) $auction->set('siteid',  $siteid);
//    echo '<br> Won SiteId='.$auction->get('siteid');
    $auction->set('end_time', GMTToLocal($item['ListingDetails']['EndTime']));
    $auction->set('status_change', date('Y-m-d H:i:s'));
    $auction->set('winning_bid', $item['SellingStatus']['CurrentPrice']);
    $auction->set('username_buyer', $item['SellingStatus']['HighBidder']['UserID']);
    $auction->set('quantity', 1);
    $email = $item['SellingStatus']['HighBidder']['Email'];
    $auction->set('email', $email);
    if (strlen($email)) {
        $customer_id = (int)$db->getOne("select id from customer_auction where email='$email'");
    }
    if (!$customer_id) {
        $customer_id = 1+$db->getOne("select fget_customer_id()");
        list($firstname, $lastname) = explode(' ', $item['SellingStatus']['HighBidder']['RegistrationAddress']['Name']);
        $q = "insert into customer_auction set id=$customer_id, email='$email', code=round(rand()*1000000)
            , email_invoice='$email'
            , firstname_invoice='".mysql_real_escape_string($firstname)."'
            , name_invoice='".mysql_real_escape_string($lastname)."'
            , country_invoice='".mysql_real_escape_string($item['SellingStatus']['HighBidder']['RegistrationAddress']['Country'])."'
            , zip_invoice='".mysql_real_escape_string($item['SellingStatus']['HighBidder']['RegistrationAddress']['PostalCode'])."'
            , tel_invoice='".mysql_real_escape_string($item['SellingStatus']['HighBidder']['RegistrationAddress']['Phone'])."'
            , street_invoice='".mysql_real_escape_string(trim($item['SellingStatus']['HighBidder']['RegistrationAddress']['Street1']))."'
            , house_invoice='".mysql_real_escape_string(trim($item['SellingStatus']['HighBidder']['RegistrationAddress']['Street2']))."'
            , city_invoice='".mysql_real_escape_string($item['SellingStatus']['HighBidder']['RegistrationAddress']['CityName'])."'
        , email_shipping='$email'
            , firstname_shipping='".mysql_real_escape_string($firstname)."'
            , name_shipping='".mysql_real_escape_string($lastname)."'
            , country_shipping='".mysql_real_escape_string($item['SellingStatus']['HighBidder']['RegistrationAddress']['Country'])."'
            , zip_shipping='".mysql_real_escape_string($item['SellingStatus']['HighBidder']['RegistrationAddress']['PostalCode'])."'
            , tel_shipping='".mysql_real_escape_string($item['SellingStatus']['HighBidder']['RegistrationAddress']['Phone'])."'
            , street_shipping='".mysql_real_escape_string(trim($item['SellingStatus']['HighBidder']['RegistrationAddress']['Street1']))."'
            , house_shipping='".mysql_real_escape_string(trim($item['SellingStatus']['HighBidder']['RegistrationAddress']['Street2']))."'
            , city_shipping='".mysql_real_escape_string($item['SellingStatus']['HighBidder']['RegistrationAddress']['CityName'])."'
            , seller_username='".$auction->get('username')."'";
        $r = $db->query($q);
        if (PEAR::isError($r)) { aprint_r($r); die();}
        $customer_id = (int)$db->getOne("select id from customer_auction where email='$email'");
    }
    if ($customer_id) {
        $personal = $db->getRow("select * from customer_auction where id=$customer_id");
        $auction->set('city_invoice', $personal->city_invoice);
        $auction->set('city_shipping', $personal->city_shipping);
        $auction->set('country_invoice', $personal->country_invoice);
        $auction->set('country_shipping', $personal->country_shipping);
        $auction->set('email_invoice', $personal->email_invoice);
        $auction->set('email_shipping', $personal->email_shipping);
        $auction->set('gender_invoice', $personal->gender_invoice);
        $auction->set('gender_shipping', $personal->gender_shipping);
        $auction->set('firstname_invoice', $personal->firstname_invoice);
        $auction->set('firstname_shipping', $personal->firstname_shipping);
        $auction->set('name_invoice', $personal->name_invoice);
        $auction->set('name_shipping', $personal->name_shipping);
        $auction->set('street_invoice', $personal->street_invoice);
        $auction->set('street_shipping', $personal->street_shipping);
        $auction->set('house_invoice', $personal->house_invoice);
        $auction->set('house_shipping', $personal->house_shipping);
        $auction->set('tel_invoice', $personal->tel_invoice);
        $auction->set('tel_shipping', $personal->tel_shipping);
        $auction->set('cel_invoice', $personal->cel_invoice);
        $auction->set('cel_shipping', $personal->cel_shipping);
        $auction->set('zip_invoice', $personal->zip_invoice);
        $auction->set('zip_shipping', $personal->zip_shipping);
        $auction->set('company_invoice', $personal->company_invoice);
        $auction->set('company_shipping', $personal->company_shipping);
        $auction->set('customer_id', $personal->id);
    }
    $r = $auction->update();
    if (PEAR::isError($r)) {
       $msg = $auction->get('username').' AuctionWonUtil Error: '.$r->toString()  . ' ' . __LINE__;
       adminEmail($msg,'admin',$auction->get('username'),$auction->get('saved_id'));
       return ;
    }
//	echo 'Was '.$item['ListingDetails']['EndTime'].' become '.GMTToLocal($item['ListingDetails']['EndTime']);
//	die();
    $offer = new Offer($db, $dbr, $auction->get('offer_id'));
    if ($offer->get('supervisor_alert') > 0 && $offer->get('supervisor_alert') > $item['SellingStatus']['CurrentPrice']) {
        standardEmail($db, $dbr, $auction, 'supervisor_alert');
    }
    standardEmail($db, $dbr, $auction, 'winning_mail');
}

function AuctionCancelledUtil($db, $dbr, $item)
{
    $auction = new Auction($db, $dbr, $item['ItemID'],1);
    $auction->set('process_stage', STAGE_NO_WINNER);
    $siteid = CountryCodeToSite($item['Site']);
    if (strlen($siteid)) $auction->set('siteid',  $siteid);
//    echo '<br> Cancelled SiteId='.$auction->get('siteid');
    $auction->set('end_time', GMTToLocal($item['ListingDetails']['EndTime']));
    $auction->set('end_time1', GMTToLocal($item['ListingDetails']['EndTime']));
    $auction->set('status_change', date('Y-m-d H:i:s'));
    $r = $auction->update();
    if (PEAR::isError($r)) {
       $msg = $auction->get('username').' AuctionCancelledUtil Error: '.$r->toString()  . ' ' . __LINE__;
       adminEmail($msg,'admin',$auction->get('username'),$auction->get('saved_id'));
       return ;
    }
}

function processMultiItemsUtil($seller, $number, $txnid)
{
    global $db, $dbr, $devId, $appId, $certId ;
    $auction = new Auction($db, $dbr, $number, $txnid);
    $listing = $auction->data;
    $seller->geteBay($devId, $appId, $certId);
                $eBay = new Services_Ebay( $devId, $appId, $certId, $listing->siteid);
// !!!!!!!!!delete
//		$seller1 = SellerInfo::Singleton($db, $dbr, 'basertest');
//        $eBay->setAuth( $seller1->get('aatoken') );
// !!!!!!!!!delete
                $eBay->setAuth( $seller->get('aatoken') );
                $time = $eBay->GeteBayOfficialTime();
                list ($Y, $M, $D,) = preg_split('~[\s:-]~', $time);
                $t1 =  gmmktime(12, 0, 0, $M, $D-25, $Y);
                $t2 =  gmmktime(23, 59 ,59, $M, $D+1, $Y);
                $params = array(
                    'ModTimeFrom' => date('Y-m-d H:i:s', $t1),
                    'ModTimeTo' =>  date('Y-m-d H:i:s', $t2),
                    'DetailLevel' => 0,
                    'TransactionID' => $txnid
                );
                $transactions = $eBay->GetItemTransactions($listing->auction_number, $params, 'ReturnAll');
//					aprint_r($transactions);
                if (PEAR::isError($transactions)) {
                    $msg = 'Error: '.$transactions->toString()  . ' ' . __LINE__;
                    echo '<br>auction_number='.$listing->auction_number.' '.$msg.'<br>';
//					aprint_r($params);
                    return;
                }
                if (count($transactions['TransactionArray']['Transaction']) == 0) {
//					echo 'count=0<br>';
                    return;
                }
                $list = $transactions['TransactionArray']['Transaction'];
                if ($list['TransactionID']) {
                    $list = array($list);
                }
                foreach ($list as $transaction) {
                    if ($transaction['TransactionID'] != $txnid) continue;
//                    if ($auction->get('auction_number') == $transactions['ItemId']) continue;

                    $auction->set('auction_number', $transactions['Item']['ItemID']);
                    $auction->set('txnid', $transaction['TransactionID']);
                    $auction->set('process_stage',STAGE_WON);
                    $auction->set('siteid',CountryCodeToSite($transaction['Site']));
                    $auction->set('email',$transaction['Buyer']['Email']);
                    $auction->set('username_buyer', $transaction['Buyer']['UserID']);
                    $auction->set('end_time', GMTToLocal($transaction['CreatedDate'], 0));
                    $auction->set('status_change', date('Y-m-d H:i:s'));
                    $auction->set('winning_bid', $transaction['TransactionPrice']);
                    $auction->set('quantity', $transaction['QuantityPurchased']);
                    $auction->set('allow_payment_1', $listing->allow_payment_1);
                    $auction->set('allow_payment_2', $listing->allow_payment_2);
                    $auction->set('allow_payment_3', $listing->allow_payment_3);
                    $auction->set('allow_payment_4', $listing->allow_payment_4);
                    $auction->set('allow_payment_cc', $listing->allow_payment_cc);
                    $auction->set('allow_payment_cc_sh', $listing->allow_payment_cc_sh);
                    $r = $auction->update();
                    if (PEAR::isError($r)) {
                       $msg = $auction->get('username').' processMultiItemsUtil Error: '.$r->toString()  . ' ' . __LINE__;
                       adminEmail($msg,'admin',$auction->get('username'),$auction->get('saved_id'));
                       continue;
                    }
                    standardEmail($db, $dbr, $auction, 'winning_mail');
                }
            if ($listing->end_time < $time) {
                $l = new Listing($db, $dbr, $listing->auction_number);
                $l->set('finished', 1);
                $l->update();
            }
}

function get_rating($db, $dbr, $number, $txnid, $seller)
{
    global $timediff;
    global $devId, $appId, $certId;
        set_time_limit(0);
        $auction = new Auction($db, $dbr, $number, $txnid);
        $seller = SellerInfo::Singleton($db, $dbr, $auction->get('username'));
        $seller->geteBay($devId, $appId, $certId);
        $items = array();
        $page = 1;
        $error = "";
        $total = 0;
        $atype = $dbr->getOne("SELECT IFNULL(listings.auction_type_vch,  'Chinese') FROM auction
            LEFT JOIN listings ON auction.auction_number=listings.auction_number
            WHERE auction.auction_number=".$number);
        while (true) {
            $eBay = new Services_Ebay( $devId, $appId, $certId, $auction->get('siteid'));
            $eBay->setAuth( $seller->get('aatoken'));
            $params = array(
                    'Pagination' => array (
                        'EntriesPerPage' => 200,
                        'PageNumber' => $page
                    ),
                    'UserID' => $seller->get('ebay_username')
            );
            $feedback = $eBay->GetFeedback ($params, 'ReturnAll');
            if (PEAR::isError($feedback)) {
                $error = 'Error: '.$feedback->getMessage();
                break;
               }
            $feedback = (array)$feedback['FeedbackDetailArray']['FeedbackDetail'];
            foreach($feedback as $item) {
                if ($atype!='Chinese') $_txnid = $item['TransactionID'];
                    else $_txnid = 1;
                if ($item['Role'] == 'Seller' && $item['ItemID'] == $number && $_txnid == $txnid)
                    $items[] = $item;
            }
//			echo $page.': '.count($feedback).' / '.count($items).'<br>';
            if (count($feedback)<200) break;
            if (count($items)) break;
//			if ($page>5) break;
            $page++;
        } // download feedback entries
//die('!!!');
/*	$item = array(
        'TransactionID'=>1,
        'ItemID'=>'280209888932',
        'CommentType'=>'Negative',
        'CommentText'=>'CommentText',
        'FeedbackID'=>'111',
        'CommentType'=>'Negative',
        'Role'=>'Seller',
        );
    $items[] = $item;*/
        if (is_array($items) && count($items)) {
            $errors = array();
            foreach ($items as $feedback) {
                if ($feedback['Role'] == 'Seller') {
                    if ($atype!='Chinese') {
                        $txnid = $feedback['TransactionID'];
                    } else {
                        $txnid = 1;
                    } //txnid
                    if ($txnid==0) $txnid=1;
                    echo $feedback['ItemID'] .'/'. $txnid.'<br>';
                    if (($feedback['ItemID'] == $auction->get('auction_number')) && ($txnid == $auction->get('txnid'))) {
                        $type = 0;
                        switch ($feedback['CommentType']) {
                            case 'Positive' :
                                $type = 1;
                                break;
                            case 'Neutral' :
                                $type = 2;
                                break;
                            case 'Negative' :
                                $type = 3;
                                break;
                            case 'Withdrawn' :
                                $type = 4;
                                break;
                        } //switch comment type
                        $auction->set('rating_received', $type);
                        $auction->set('rating_received_date', ServerTolocal(date("Y-m-d H:i:s"), $timediff));
                        $auction->set('rating_text_received', $feedback['CommentText']);
                        $auction->set('rating_received_id', $feedback['FeedbackID']);
                        $auction->update();
                        $q = "insert into auction_feedback (
                            `auction_number`,`txnid`,`type`,`id`,`datetime`,`code`,`text`)
                            values ($number, $txnid, 'received', ".$feedback['FeedbackID'].",
                                '".ServerTolocal(date("Y-m-d H:i:s"), $timediff)."',$type,
                                '".mysql_real_escape_string(iconv("windows-1250", "UTF-8", $feedback['CommentText']))."'
                            )";
                        $r = $db->query($q);
                        if (PEAR::isError($r)) {/*aprint_r($r);*/}

                        clearRatingCache($number, $txnid);

                        if ($type==3 || $type==2) {
                            $rating = new Rating($db, $db, 0, $auction->get('auction_number'), $auction->get('txnid'), $timediff);
                            $rating->update();
                            $ret = new stdClass;
                            $ret->original_username = $auction->get('username');
                            $ret->username = Config::get($db, $dbr, 'aatokenSeller');
                            $ret->auction_number = $auction->get('auction_number');
                            $ret->txnid = $auction->get('txnid');
                            global $siteURL;
                            $rating_id = $db->getOne("select max(id) from rating where auction_number=".$auction->get('auction_number')." and txnid=".$auction->get('txnid'));
                            $ret->rating_case_url = $siteURL . "rating_case.php?id=" . $rating_id;
                            standardEmail($db, $dbr, $ret, 'rating_case');
//							$rating->update();
                        };
                    } // if the auction
                } // role = S
            } // for each feedback enties
        } // if no error
    return $error;
}

function clearRatingCache($auction_number, $txnid = 3)
{
    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    $saved_ids = $db->getAll("
        select IFNULL(subau.saved_id, au.saved_id) AS `saved_id`, shop.id
        from auction_feedback af
        join auction au on af.auction_number=au.auction_number and af.txnid=au.txnid
        left join auction subau on subau.main_auction_number=au.auction_number and subau.main_txnid=au.txnid
        join shop on IFNULL(subau.username, au.username)=shop.username and IFNULL(subau.siteid, au.siteid)=shop.siteid
        where not au.hiderating and af.auction_number = '$auction_number' and not af.hidden and au.txnid='$txnid'
        group by `saved_id`, id
    ");

    foreach ($saved_ids as $_saved_id)
    {
        cacheClear("getRating({$_saved_id->saved_id})", $_saved_id->id);
        cacheClear("fill_shop_ratings_json({$_saved_id->saved_id},%", $_saved_id->id);
        cacheClear("fill_shop_ratings({$_saved_id->saved_id},%", $_saved_id->id);
        cacheClear("fill_shop_ratings_json(0,%", $_saved_id->id);
        cacheClear("fill_shop_ratings(0,%", $_saved_id->id);
        cacheClear("getOverallRatings(%", $_saved_id->id);
    }
}

function set_rating($db, $dbr, $number, $txnid, $seller)
{
    global $timediff;
    global $devId, $appId, $certId;
        set_time_limit(0);
        $auction = new Auction($db, $dbr, $number, $txnid);
        $sid = requestVar('seller');
        $errors = array();
        $seller = SellerInfo::Singleton($db, $dbr, $auction->get('username'));
        $seller->geteBay($devId, $appId, $certId);
        $eBay = new Services_Ebay( $devId, $appId, $certId, $auction->get('siteid'));
        $eBay->setAuth( $seller->get('aatoken'));
        $params = array(
                'ItemID' =>      $auction->get('auction_number'),
                'TargetUser' =>  $auction->get('username_buyer'),
                'CommentType' => 'Positive',
                   'CommentText' => $seller->get('rating_text'),
        );
        if (($auction->get('type') != 'Chinese')) {
            $params['TransactionID'] = $auction->get('txnid');
        }
        $result = $eBay->LeaveFeedback ($params);
        if (PEAR::isError($result)) {
            $errors[$auction->get('auction_number')] = $result->getCode() . ' ' . htmlspecialchars($result->getMessage());
            echo ($result->getCode() . ' ' . htmlspecialchars($result->getMessage()));
            return $errors;
        }
        if ($result['Ack'] == 'Success') {
            $auction->set('rating_given', 1);
            $auction->set('rating_given_date', ServerTolocal(date("Y-m-d H:i:s"), $timediff));
            $auction->set('rating_text_given', $seller->get('rating_text'));
            $auction->update();
            $r = $db->query("insert into auction_feedback (
                `auction_number`,`txnid`,`type`,`id`,`datetime`,`code`,`text`)
                values ($number, $txnid, 'given', 0,
                    '".ServerTolocal(date("Y-m-d H:i:s"), $timediff)."',1,
                    '".mysql_escape_string($seller->get('rating_text'))."'
                )");
            if (PEAR::isError($r)) {/*aprint_r($r);*/}

            clearRatingCache($number, $txnid);

            $cnt++;
           }

    if (count($errors)) {
        $error = '';
        foreach ($errors as $n => $text) {
            $error .= $n . ' : ' . $text . '<br>';
        }
    }
    return $error;
}

function getLangsArray($db = null, $dbr = null) {
    global $loggedUser;
    $username = isset($loggedUser) ? $loggedUser->get('username') : '';

    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    return $dbr->getAssoc("select v.value, v.description
        from  config_api_values v
        left join config_api_values_user_ordering uo on uo.par_id=v.par_id and uo.value=v.value
            and uo.username=?
        where v.par_id=6 and not v.inactive
        order by IFNULL(uo.ordering, v.ordering), v.value", null, [$username]);
}

function getLangsArrayByName($db = null, $dbr = null) {
    global $loggedUser;
    $username = isset($loggedUser) ? $loggedUser->get('username') : '';

    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    return $dbr->getAssoc("select v.value, v.description
        from  config_api_values v
        left join config_api_values_user_ordering uo on uo.par_id=v.par_id and uo.value=v.value
            and uo.username='$username'
        where v.par_id=6 and not v.inactive
        order by v.description ");
}

    function mb_unserialize($serial_str) {
        $serial_str= preg_replace('!s:(\d+):"(.*?)";!se', "'s:'.strlen('$2').':\"$2\";'", $serial_str );
        $serial_str= str_replace("\r", "", $serial_str);
        //$serial_str= str_replace("\n", "", $serial_str);
        return unserialize($serial_str);
    }

    function getFLVFrame($flv, $frameno) {
        file_put_contents('tmp/frametmp.flv', $flv);
        $movie = new ffmpeg_movie('tmp/frametmp.flv');
        $frame = $movie->getFrame($frameno);
        $gdframe = $frame->toGDImage();
        $r = imagejpeg ( $gdframe, 'tmp/frametmp.jpg',  100);
        $frame = file_get_contents('tmp/frametmp.jpg');
        unlink('tmp/frametmp.jpg');
        unlink('tmp/frametmp.flv');
        return $frame;
    }

    function pageSetup($page, $_defsortOrder, $_defdirection, $var_name, &$sort, &$direction, &$vars) {
        global $smarty, $loggedUser, $db, $dbr;
        $uri = (string)$_SERVER['SCRIPT_NAME']."?".(string)$_SERVER['QUERY_STRING'];
        if (is_a($loggedUser,'User')) {
            $username=$loggedUser->get('username');
        } else {
            $username='customer';
        }
        $setup = $dbr->getRow("SELECT * FROM user_sort_order WHERE page='$page' AND username='$username'");
        $defsortOrder = $setup->sortorder;
        $defdirection = $setup->direction;
        $defvars = $setup->vars;
        if (!strlen($defsortOrder)) $defsortOrder=$_defsortOrder;
        $sort = requestVar('order', $defsortOrder);
        if (!$defdirection) $defdirection=$_defdirection;
        $direction = requestVar('dir', $defdirection);
        if (isset($_GET[$var_name])) {
            $vars = $_GET[$var_name];
        } else {
            $vars = unserialize($defvars);
        }
        $db->execParam("REPLACE INTO user_sort_order SET page='$page', sortorder='".$sort."', direction='".$direction."', username='$username'
            ,vars=?",array(serialize($vars)));
        if (!$direction) $direction=1;
        $smarty->assign('dir'.$sort, -$direction);
        $smarty->assign('uri', preg_replace('/&order=.+/', '', $uri));
        $langs = getLangsArray();
        foreach ($langs as $lang_id=>$dummy) {
            if (isset($_GET['trposition']["'".$lang_id."'"])) {
                $trposition = $_GET['trposition']["'".$lang_id."'"];
                $q = "replace config_api_values_user_ordering set `username`='$username'
                    , ordering='$trposition', value='$lang_id', par_id=6";
                $r = $db->query($q);
                if (PEAR::isError($r)) {aprint_r($r);}
            } // if available lang
        } // foreach lang
    }

    function pageLangs($shop_id, $vars){
        global $smarty, $loggedUser, $db, $dbr;
        $langs_selected = array();
        foreach($vars as $lang_id) $langs_selected[$lang_id]=$lang_id;

        $langs = getLangsArray();
        if ($shop_id) {
            $shopCatalogue = new Shop_Catalogue($db, $dbr, $shop_id);
/*			$sellerInfo = new SellerInfo($db, $dbr, $shopCatalogue->_shop->username);
            $available_langs = unserialize($sellerInfo->data->available_langs);
            foreach($langs as $lang_id=>$dummy) {
                if (!isset($available_langs[$lang_id])) {
                    unset($langs[$lang_id]);
                }
            }*/
        $langs = $dbr->getAssoc("select v.value, v.description
            from  config_api_values v
            join seller_lang sl on sl.lang=v.value
                and sl.username='{$shopCatalogue->_shop->username}'
            where v.par_id=6 and not v.inactive
            and sl.useit=1
            order by sl.ordering");
        }
//		var_dump($langs);
//		var_dump($langs_selected);
        $smarty->assign('langs', $langs);
        $smarty->assign('langs_selected', $langs_selected);
        $smarty->assign('tritems_cnt', (count($langs)-1)*2);
        return $langs;
    }

    function pageLangsCountry($country_code, $vars){
        global $smarty, $loggedUser, $db, $dbr;
        $langs_selected = array();
        foreach($vars as $lang_id) $langs_selected[$lang_id]=$lang_id;

        /*$langs = getLangsArray();
        $r = $dbr->getAll("select available_langs from seller_information
            join shop on seller_information.username=shop.username
            where ebaycountry='$country_code'");
        $available_langs = array();
        foreach($r as $rec ){
            $available_langs = array_merge($available_langs, unserialize($rec->available_langs));
        }
    //echo $country_code; print_r($available_langs);
            foreach($langs as $lang_id=>$dummy) {
                if (!isset($available_langs[$lang_id])) {
                    unset($langs[$lang_id]);
                }
            }
        */
        $langs = $dbr->getAssoc("select v.value, v.description
            from  config_api_values v
            join seller_lang sl on sl.lang=v.value
            join seller_information si on si.username=sl.username
            where si.ebaycountry='$country_code'
             and v.par_id=6 and not v.inactive
            and sl.useit=1
            order by sl.ordering");
#		var_dump($langs);
#		var_dump($langs_selected);
        $smarty->assign('langs', $langs);
        $smarty->assign('langs_selected', $langs_selected);
        $smarty->assign('tritems_cnt', (count($langs)-1)*2);
        return $langs;
    }

    function mulang_fields_Update($mulang_fields, $table_name, $edit_id, $source='') {
        if ($source=='') $source = $_POST;
        $q_call = array();
        global $smarty, $loggedUser, $db, $dbr, $debug;
        foreach ($mulang_fields as $fld) {
            if (count($source[$fld])) {
                $changed = 0;
                foreach ($source[$fld] as $lang => $value) {
                    if ($lang == '0' && $table_name!='offer') $lang = '';
                    $q = "select iid from translation where id='$edit_id'
                        and table_name='$table_name' and field_name='$fld' and language = '$lang'";
                    $iid = (int)$db->getOne($q);
//					echo $q; echo '<br>';
                    $value = mysql_escape_string($value);
                    if ($iid) {
                        if ($value !== '')
                        {
                            $q = "update translation set value='$value'	where iid='$iid'";
                        }
                        else
                        {
                            $q = "delete from translation where iid='$iid'";
                        }
                    } else {
                        if ($value !== '')
                        {
                            $q = "insert into translation set value='$value'
                            , id='$edit_id'
                            , table_name='$table_name' , field_name='$fld'
                            , language = '$lang'";
                        }
                    }
//					echo $q;echo '<br>';
                    $r = $db->exec($q);
                    if (PEAR::isError($r)) aprint_r($r);
                    $affectedRows = $r;
                    if (/*$r && */$table_name=='sa' && $fld=='ShopDesription' && (int)$value) {
                        $doc_ids = $dbr->getOne("select group_concat(doc_id) from saved_doc where saved_id=$edit_id");
                        if (strlen($doc_ids)) {
                            $q_call[] = "delete from translation where
                                table_name='saved_doc' and field_name in ('alt','title') and language='$lang'
                                and id in ($doc_ids)";
                        }
                        $q_call[] = "call sp_Title_Change($edit_id, '$lang', $value);";
                    }
                    $q = "update translation set `updated`=$affectedRows
                        where language='$lang' and table_name='$table_name'
                        and field_name='$fld' and id='$edit_id'";
//					echo $q;echo '<br>';
                    $r = $db->query($q);
                    if (PEAR::isError($r)) {aprint_r($r); }
                    $changed += $affectedRows;
                }
            }
            if ($changed) {
                $unchecked = $dbr->getAssoc("select 0 f1,0 f2 union all
                    select iid, iid from translation where table_name='$table_name' and field_name='$fld'
                        and not updated and id='$edit_id'");
                $q = "update translation set `unchecked`=1	where iid in (".implode(',',$unchecked).")";
                $r = $db->query($q);
                if (PEAR::isError($r)) {aprint_r($r);}
            }
        } // mulang
        if (count($q_call)) {
            foreach($q_call as $q) {
                $r = $db->query($q);
                if ($debug) echo print_r($q_call, true).'<br>';
                if (PEAR::isError($r)) { print_r($r); die();}
            }
        }
    }

    function mulang_files_Update($mulang_files, $table_name, $edit_id, $source='') {
        global $smarty, $loggedUser, $db, $dbr;
        if ($source=='') $source = $_FILES;
        foreach ($mulang_files as $fld) {
            $q = "update translation set `updated`=0
                        where table_name='$table_name'
                        and field_name='$fld' and id='$edit_id'";
            $r = $db->query($q);
            if (PEAR::isError($r)) {aprint_r($r); }
            $changed = 0;
            foreach ($source[$fld]['name'] as $lang => $value) {// print_r($_POST[$fld]);
                if (strlen($source[$fld]['name'][$lang])) {
                    update_version($table_name, $fld, $edit_id, $lang);
                    $iid = (int)$dbr->getOne("select iid from prologis_log.translation_files2 where id='$edit_id'
                        and table_name='$table_name' and field_name='$fld' and language = '$lang'");

                    $content = file_get_contents($source[$fld]['tmp_name'][$lang]);
                    $md5 = md5($content);

                    $filename = set_file_path($md5);
                    if ( ! is_file($filename)) {
                        file_put_contents($filename, $content);
                    }

                    if ($iid) {
                        $q = "UPDATE prologis_log.translation_files2 SET md5='$md5' WHERE iid='$iid'";
                    } else {
                        $q = "INSERT INTO prologis_log.translation_files2 SET md5='$md5'
                        , id='$edit_id'
                        , table_name='$table_name' , field_name='$fld'
                        , language = '$lang'";
                    }
                    $r = $db->query($q);
                    if (PEAR::isError($r)) print_r($r);

//					$value = base64_encode(file_get_contents($source[$fld]['tmp_name'][$lang]));
//					$md5 = md5($value);
//					$data_id = (int)$dbr->getOne("select id from prologis_log.data_storage where md5sum='$md5'");
//					if (!$data_id) {
//						$r = $db->query("insert into prologis_log.data_storage set md5sum='$md5', value='$value'");
//						$data_id = (int)$db->getOne("select id from prologis_log.data_storage where md5sum='$md5'");
//					}
//					if ($iid) {
//						$q = "update prologis_log.translation_files2 set data_id=$data_id where iid='$iid'";
//					} else {
//						$q = "insert into prologis_log.translation_files2 set data_id=$data_id
//						, id='$edit_id'
//						, table_name='$table_name' , field_name='$fld'
//						, language = '$lang'";
//					}
//					$r = $db->query($q);
//					if (PEAR::isError($r)) print_r($r);

                    $iid = (int)$db->getOne("select iid from translation where id='$edit_id'
                        and table_name='$table_name' and field_name='$fld' and language = '$lang'");
                    $value = strtolower(basename($source[$fld]['name'][$lang]));
                    if ($iid) {
                        $q = "update translation set value='$value'	where iid='$iid'";
                    } else {
                        $q = "insert into translation set value='$value'
                        , id='$edit_id'
                        , table_name='$table_name' , field_name='$fld'
                        , language = '$lang'";
                    }
                    $r = $db->exec($q);
                    if (PEAR::isError($r)) aprint_r($r);
                    $affectedRows = $r;
                    $q = "update translation set `updated`=$affectedRows
                        where language='$lang' and table_name='$table_name'
                        and field_name='$fld' and id='$edit_id'";
                    $r = $db->query($q);
                    if (PEAR::isError($r)) {aprint_r($r); }
                    $changed += $affectedRows;

//					echo "$q<br>";
                }
            }
            if ($changed) {
                $unchecked = $dbr->getAssoc("select 0 f1,0 f2 union all
                    select iid, iid from translation where table_name='$table_name' and field_name='$fld'
                        and not updated and id='$edit_id'");
                $q = "update translation set `unchecked`=1	where iid in (".implode(',',$unchecked).")";
                $r = $db->query($q);
                if (PEAR::isError($r)) {aprint_r($r);}
            }
        } // mulang
    }

    /**
     * Magic function to get and assign to smarty some magic fields
     * @todo wtf is that function?! rewrite it!
     * @param string[] $mulang_fields fields names
     * @param string $table_name
     * @param int $edit_id
     * @param int $log usually 1, I didn't found another value
     * @return array
     */
    function mulang_fields_Get($mulang_fields, $table_name, $edit_id, $log=1) {
        global $smarty, $dbr;
        $res_array = array();
        foreach ($mulang_fields as $fld) {
            $r = $dbr->getAssoc("select language, iid from translation where id='$edit_id'
                           and table_name='$table_name' and field_name='$fld'");
            foreach($r as $language=>$iid) {
                $r[$language] = $dbr->getRow("select * from translation where iid=$iid");
                if ($log) {
                    $r[$language]->last_on=
                    $dbr->getOne("select SUBSTRING_INDEX(GROUP_CONCAT(total_log.Updated order by total_log.Updated),',',-1) last_on
                        from translation
                        left join total_log on total_log.Table_name='translation' and total_log.TableID=translation.iid
                        where 1
                        and (total_log.field_name='value' or (total_log.field_name='unchecked' and total_log.old_value=1 and total_log.new_value=0))
                        and translation.iid = $iid
                        group by translation.id
                        order by translation.id+1
                        ");
                    $r[$language]->last_by=
                    $dbr->getOne("select SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(users.name,total_log.username) order by total_log.Updated),',',-1) last_on
                        from translation
                        left join total_log on total_log.Table_name='translation' and total_log.TableID=translation.iid
                        left join users on users.system_username=total_log.username
                        where 1
                        and (total_log.field_name='value' or (total_log.field_name='unchecked' and total_log.old_value=1 and total_log.new_value=0))
                        and translation.iid = $iid
                        group by translation.id
                        order by translation.id+1
                        ");
                } // log
            }
            $smarty->assign($fld.'_translations', $r);
            $res_array[$fld.'_translations'] = $r;
        }
        return $res_array;
    }

    function mulang_fields_GetArray($mulang_fields, $table_name, $rec_array, $id_field_name='id') {
        global $smarty, $loggedUser, $db, $dbr;
        foreach ($mulang_fields as $fld) {
            $r = array();
            foreach ($rec_array as $rec) {
                $edit_id = $rec->$id_field_name;
                $r[$edit_id] = $dbr->getAssoc("select language, iid from translation where id='$edit_id'
                               and table_name='$table_name' and field_name='$fld'");
                foreach($r[$rec->$id_field_name] as $language=>$iid) {
                    $r[$edit_id][$language] = $dbr->getRow("select * from translation where iid=$iid");
    //				$r[$language]->value = str_replace("'","\'",$r[$language]->value);
                    $r[$edit_id][$language]->last_on=
                        $dbr->getOne("select SUBSTRING_INDEX(GROUP_CONCAT(total_log.Updated order by total_log.Updated),',',-1) last_on
                            from translation
                            left join total_log on total_log.Table_name='translation' and total_log.TableID=translation.iid
                            where 1
                            and (total_log.field_name='value' or (total_log.field_name='unchecked' and total_log.old_value=1 and total_log.new_value=0))
                            and translation.iid = $iid
                            group by translation.id
                            order by translation.id+1
                            ");
                    $r[$edit_id][$language]->last_by=
                        $dbr->getOne("select SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(users.name,total_log.username) order by total_log.Updated),',',-1) last_on
                            from translation
                            left join total_log on total_log.Table_name='translation' and total_log.TableID=translation.iid
                            left join users on users.system_username=total_log.username
                            where 1
                            and (total_log.field_name='value' or (total_log.field_name='unchecked' and total_log.old_value=1 and total_log.new_value=0))
                            and translation.iid = $iid
                            group by translation.id
                            order by translation.id+1
                            ");
                }
//			if ($fld=='ValueText' || $fld=='title_suffix') print_r($r);
    //			echo $fld.'<br>';print_r($r);
            } // foreach record
            $smarty->assign($fld.'_translations', $r);
        }
    }

    function mulang_fields_Get2List($mulang_fields, $table_name, &$list, $id_field='id') {
        global $db, $dbr;
        foreach($list as $k=>$r) {
            foreach ($mulang_fields as $fld) {
                $q = "select language, value from translation where id='{$r->$id_field}'
                           and table_name='$table_name' and field_name='$fld'";
//				echo $q.'<br>';
                $list[$k]->$fld = $dbr->getAssoc($q);
                if (PEAR::isError($list[$k]->$fld)) {
                    print_r($list[$k]->$fld);
                }
            }
        }
    }

    function mulang_files_Get($mulang_files, $table_name, $edit_id) {
        $return = [];
        global $smarty, $loggedUser, $db, $dbr;
        foreach ($mulang_files as $fld) {
            $r = $dbr->getAssoc("select language, iid from translation where id='$edit_id'
                           and table_name='$table_name' and field_name='$fld'");
            foreach($r as $language=>$iid) {
                $r[$language] = $dbr->getRow("select translation.*, IFNULL(versions.version,0) version
                    from translation
                    left join versions on translation.id=versions.id and translation.table_name=versions.table_name
                                and translation.field_name=versions.field_name and translation.language = versions.language
                    where translation.iid=$iid");
//				$r[$language]->value = str_replace("'","\'",$r[$language]->value);
                $trrec = $dbr->getOne("select SUBSTRING_INDEX(GROUP_CONCAT(total_log.Updated order by total_log.Updated),',',-1) last_on
                            ,SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(users.name,total_log.username) order by total_log.Updated),',',-1) last_by
                        from translation
                        left join total_log on total_log.Table_name='translation' and total_log.TableID=translation.iid
                        left join users on users.system_username=total_log.username
                        where 1
                        and (total_log.field_name='value' or (total_log.field_name='unchecked' and total_log.old_value=1 and total_log.new_value=0))
                        and translation.iid = $iid
                        group by translation.id
                        order by translation.id+1
                        ");
                $r[$language]->last_on = $trrec->last_on;
                $r[$language]->last_by = $trrec->last_by;
            }
            $smarty->assign($fld.'_translations', $r);
            $return[$fld] = $r;
        }

        return $return;
    }

    function mulang_files_GetArray($mulang_files, $table_name, $rec_array, $id_field_name='id') {
        global $smarty, $loggedUser, $db, $dbr;
        foreach ($mulang_files as $fld) {
            $r = array();
            foreach ($rec_array as $rec) {
                $edit_id = $rec->$id_field_name;
                $r[$edit_id] = $dbr->getAssoc("select language, iid from translation where id='$edit_id'
                           and table_name='$table_name' and field_name='$fld'");
                foreach($r[$edit_id] as $language=>$iid) {
                    $r[$edit_id][$language] = $dbr->getRow("select translation.*, IFNULL(versions.version,0) version
                    from translation
                    left join versions on translation.id=versions.id and translation.table_name=versions.table_name
                                and translation.field_name=versions.field_name and translation.language = versions.language
                    where translation.iid=$iid");
                $trrec = $dbr->getOne("select SUBSTRING_INDEX(GROUP_CONCAT(total_log.Updated order by total_log.Updated),',',-1) last_on
                            ,SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(users.name,total_log.username) order by total_log.Updated),',',-1) last_by
                        from translation
                        left join total_log on total_log.Table_name='translation' and total_log.TableID=translation.iid
                        left join users on users.system_username=total_log.username
                        where 1
                        and (total_log.field_name='value' or (total_log.field_name='unchecked' and total_log.old_value=1 and total_log.new_value=0))
                        and translation.iid = $iid
                        group by translation.id
                        order by translation.id+1
                        ");
                $r[$language]->last_on = $trrec->last_on;
                $r[$language]->last_by = $trrec->last_by;
                }
            }
//			print_r($r);
            $smarty->assign($fld.'_translations', $r);
        }
    }

    function translate($db, $dbr, $word, $lang_to, $table='translate', $field='translate') {
        $q = "select IFNULL((select `value` from translation where `language`='$lang_to' and table_name='$table' and field_name='$field'
            and id=(select id from translation where `language`='master' and table_name='$table' and field_name='$field'
            and `value`='$word' limit 1)), '$word')";
        $tr = $dbr->getOne($q);
        return $tr;
    }

function itemize_dir_util($contents) {
    foreach ($contents as $file) {
        if(ereg("([-dl][rwxstST-]+).* ([0-9]*) ([a-zA-Z0-9]+).* ([a-zA-Z0-9]+).* ([0-9]*) ([a-zA-Z]+[0-9: ]*[0-9])[ ]+(([0-9]{2}:[0-9]{2})|[0-9]{4}) (.+)", $file, $regs)) {
           $type = (int) strpos("-dl", $regs[1]{0});
           $tmp_array['line'] = $regs[0];
           $tmp_array['type'] = $type;
           $tmp_array['rights'] = $regs[1];
           $tmp_array['number'] = $regs[2];
           $tmp_array['user'] = $regs[3];
           $tmp_array['group'] = $regs[4];
           $tmp_array['size'] = $regs[5];
           $tmp_array['date'] = date("Y-m-d",strtotime($regs[6]));
           $tmp_array['time'] = $regs[7];
           $tmp_array['datetime'] = $tmp_array['date']." ".$tmp_array['time'];
           $tmp_array['name'] = $regs[9];
           }
           if ($tmp_array['name'][0] != ".") {
               $dir_list[] = $tmp_array;
           }
   }
   return $dir_list;
}

function promo_subinvoices($db, $dbr, $auction, $promo) {
    $q = "select auction_number, txnid, invoice_number from auction
        where main_auction_number={$auction->data->auction_number} and main_txnid={$auction->data->txnid}";
#	echo $q.'<br>';
    $subs = $dbr->getAll($q);
    foreach($subs as $sub) {
        $q = "select * from orders
            where auction_number={$sub->auction_number} and txnid={$sub->txnid}";
#		echo $q.'<br>';
        $items = $dbr->getAll($q);
        $subtotal = 0;
        foreach ($items as $article) {
            $oldprice = ($article->oldprice*1?$article->oldprice:$article->price);
            $article_id = $article->article_id;
            $newprice = round($oldprice*(1-$promo->percent/100), 2);
//			echo '$newprice='.$newprice.'<br>';
            $newprice = round($newprice*(1-(int)$promo->disco_articles_perc[$article_id]/100), 2)
                /*- (int)$promo->disco_articles_amt[$article_id]*/;
            if ($newprice==$oldprice) $oldprice=0;
            $q = "update orders set price=$newprice, oldprice=$oldprice where id={$article->id}";
//			echo $q.'<br>';
            $db->query($q);
            $subtotal += $article->quantity*$newprice;
        }
        $q = "update invoice set total_price=$subtotal
            where invoice_number={$sub->invoice_number}";
#		echo $q.'<br>';
        $db->query($q);
    }
};

function twit($consumerKey, $consumerSecret, $oAuthToken, $oAuthSecret, $message) {
    require_once('twitteroauth.php');
    $tweet = new TwitterOAuth($consumerKey, $consumerSecret, $oAuthToken, $oAuthSecret);
    $message = substr($message, 0, 139);
    return $tweet->post('statuses/update', array('status' => $message));
}

function googleShortUrl($apiKey, $url)
{
#     $apiKey      = 'AIzaSyDPDLH11GFnTHCZJBUAXh6BOLqttgaFmU4';
//     $apiKey      = 'AIzaSyCJv7-ZtKHrLOYDJ7vMsyE7cO2ndnzQNY4';
     $curlHandler = curl_init();

     //preparing the request
     curl_setopt($curlHandler, CURLOPT_URL, 'https://www.googleapis.com/urlshortener/v1/url?key=' . $apiKey);
     curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, 1);
     curl_setopt($curlHandler, CURLOPT_SSL_VERIFYPEER, 0);
     curl_setopt($curlHandler, CURLOPT_HTTPHEADER, array('content-type: application/json'));
     curl_setopt($curlHandler, CURLOPT_POST, 1);
     curl_setopt($curlHandler, CURLOPT_POSTFIELDS, json_encode(array('longUrl' => $url)));

     $response = json_decode(curl_exec($curlHandler), 1);
     /*
         array('kind', 'id', 'longUrl')
         id - is shortened url
     */

     curl_close($curlHandler);

     return $response['id'];
}

    function listClassifier($db, $dbr, $id)
    {
        $ret = array();
        global $allNodes_array;
        $allNodes_array = (array)getAllNodes($db, $dbr, $id);
        $allNodes =  implode(',', $allNodes_array);
        if (!$id) {
            $q = "select group_concat(sc.id) from classifier sc
                where 1";
            $id=$dbr->getOne($q);
            if (!strlen($id)) $id=0;
        }
//		print_r($allNodes_array);
        $q = "select sc.id f1, sc.id f2 from classifier sc
            where 1
            and sc.parent_id in
            ($id, $allNodes)";
        global $ids2show;
        global $level1_id;
        $ids2show = $dbr->getAssoc($q);
//		print_r($ids2show);
        $level1_id = $id;
        if (PEAR::isError($ids2show)) {
            aprint_r($ids2show);
            return;
        }
        $list = listAllClassifier($db, $dbr);
        foreach ((array)$list as $rec) {
            $ret[] = $rec;
        }
//		print_r($ret);
        return $ret;
    }

    function listClassifierArray($db, $dbr, $id)
    {
        $ret = array();
        $list = listClassifier($db, $dbr, $id);
        foreach ((array)$list as $rec) {
                $ret[$rec->id] = str_repeat("	", $rec->level).$rec->number." (".$rec->name.")";
        }
        return $ret;
    }

    function listAllClassifier($db, $dbr, $parent_id=0, $level=0)
    {
        $q = "SELECT sc.id
            , sc.name
            , sc.number
            , sc.parent_id
            , $level level
            FROM classifier sc
            where 1
            and sc.parent_id=$parent_id ORDER BY number";
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }
        $res = array();
        global $ids2show;
        global $level1_id;
        global $allNodes_array;
        global $getall;
        foreach($r as $key=>$rec){
            $rec->level_nbsp = str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $rec->level);
            $children = listAllClassifier($db, $dbr, $rec->id, $level+1);
//			echo 'Run listAllClassifier($db, $dbr, '.$rec->id.', '.($level+1).') count='.count($children).'<br>';
            $rec->childcount = count($children);
            if ($rec->opened
                    || in_array($rec->id, $allNodes_array)
                    || $getall) {
                $rec->children = $children;
            }
            $res[] = $rec;
            $res = array_merge((array)$res);
        }
        return $res;
    }

    function getAllNodes($db, $dbr, $id)
    {
//		echo 'getAllNodes($db, $dbr, '.$id.')<br>';
        if (PEAR::isError($id)) {
            aprint_r($id);
            die(' DIED!');
        }
        if (!$id) return 0;
        $q = "SELECT sc.id
            , sc.name
            , sc.number
            , sc.parent_id
            , sc.level
            FROM classifier sc
            where 1
        and sc.id=$id";
        $cat = $dbr->getRow($q);
        if (PEAR::isError($cat)) {
            aprint_r($cat);
            die(' DIED!');
        }
        return array_merge((array)$cat->id,(array)getAllNodes($db, $dbr, $cat->parent_id));
    }

    function getClassifiersArray($db, $dbr) {
        $q = "select c4.id, CONCAT(
            c1.name,'(',c1.number,')'
            , ' - ', c2.name,'(',c2.number,')'
            , ' - ', c3.name,'(',c3.number,')'
            , ' - ', c4.name,'(',c4.number,')'
            )
            from classifier c4
            join classifier c3 on c3.id=c4.parent_id
            join classifier c2 on c2.id=c3.parent_id
            join classifier c1 on c1.id=c2.parent_id
            where c4.level=4
            order by c1.number, c2.number, c3.number, c4.number";
        $res = $dbr->getAssoc($q);
        return $res;
    }

function get_route_distances($db, $dbr, $array){
    usort($array, function ($a, $b) {
        return $a->route_id_label == $b->route_id_label ? 0 : ($a->route_id_label < $b->route_id_label ? -1 : 1);
    });

    $lastAddress = '';
    $lastZIPCity = '';
    $lastRoute = 0;

    foreach ($array as $key => $rec) {
        if (!$rec->route_id) {
            continue;
        }

        $speed_factor = $dbr->getOne("SELECT `car_speed`.`factor` FROM `car_speed`
            JOIN `route` ON `car_speed`.`id` = `route`.`speed_id` WHERE `route`.`id`=?", null, [$rec->route_id]);

        if (!$speed_factor) {
            $speed_factor = $dbr->getOne("SELECT `car_speed`.`factor` FROM `car_speed`
                JOIN `cars` ON `car_speed`.`id`=`cars`.`speed_id`
                JOIN `route` ON `cars`.`id`=`route`.`car_id`
                WHERE `route`.`id`=?
                LIMIT 1", null, [$rec->route_id]);
        }
        if (!$speed_factor) $speed_factor = 1;

        if ($lastRoute != $rec->route_id) {
            $lastAddress = '';
            $db->query("UPDATE `auction` SET `route_distance` = NULL, `route_duration` = NULL
                WHERE `auction_number`='{$rec->auction_number}' AND `txnid`='{$rec->txnid}'");
            $array[$key]->route_distance_duration = '';
            $start_datetime = $dbr->getOne("SELECT CONCAT(`start_date`, ' ', `start_time`)
                    FROM `route` WHERE `id`=?", null, [$rec->route_id]);
        }

        $lastRoute = $rec->route_id;
        if ($lastAddress == '') {
            $lastAddress = $dbr->getOne("SELECT IFNULL(CONCAT(address1, ', ', address2, ', ', address3), CONCAT(start_address, ' ', start_country))
                    FROM route
                    LEFT JOIN warehouse ON warehouse.warehouse_id=route.start_warehouse_id
                    WHERE route.id=$lastRoute");
            $lastZIPCity = $dbr->getOne("SELECT IFNULL(CONCAT(address1, ', ', address2, ', ', address3), CONCAT(start_address, ' ', start_country))
                    FROM route
                    LEFT JOIN warehouse ON warehouse.warehouse_id=route.start_warehouse_id
                    WHERE route.id=$lastRoute");
            $lasware_Address = $dbr->getOne("SELECT IFNULL(CONCAT(address1, ', ', address2, ', ', address3), CONCAT(end_address, ' ', end_country))
                    FROM route
                    LEFT JOIN warehouse ON warehouse.warehouse_id=route.end_warehouse_id
                    WHERE route.id=$lastRoute");
            $lasware_ZIPCity = $dbr->getOne("SELECT IFNULL(CONCAT(address1, ', ', address2, ', ', address3), CONCAT(end_address, ' ', end_country))
                    FROM route
                    LEFT JOIN warehouse ON warehouse.warehouse_id=route.end_warehouse_id
                    WHERE route.id=$lastRoute");
        }
        $rec->all_shipping_address_only = str_replace('removed', '', $rec->all_shipping_address_only);

        $ures = gmap($db, $dbr, $lastAddress, $rec->all_shipping_address_only, $rec->auction_number, $rec->txnid);
        $distance = 0;
        $duration = 0;
        if ($ures['status'] == 'OK') {
            $distance = round(($ures['route']['leg']['distance']['value'] / 1000));
            $distance_txt = $distance . " km";
            $duration = round($speed_factor * ($ures['route']['leg']['duration']['value'] / 60));
            $duration_txt = $duration . " mins";
        } else {
            for ($i = 0; $i < count($ures['geocoded_waypoint']); $i++) {
                if ($ures['geocoded_waypoint'][$i]['geocoder_status'] != 'OK') {
                    $addr = ($i == 0) ? $lastAddress : $rec->all_shipping_address_only;
                    break;
                }
            }
            if ($ures['status'] == 'OVER_QUERY_LIMIT') {
                $distance = "<br>status: {$ures['status']}, <a href='{$ures['url']}&key={$ures['key']}' target='_blank'>link</a><br>";
            } else {
                $distance = "<br>Address <span style='color:red'>$addr</span> is wrong (status: {$ures['status']}, <a href='{$ures['url']}&key={$ures['key']}' target='_blank'>link</a>)<br>";
            }
        } // addr 1 2 3

        if (!strlen($duration)) {
            echo "cannot get result between $lastAddress and $rec->all_shipping_address_only for " . $ures['url'] . "&key=" . $ures['key'] . "<br>";
        }
        else {
            $route_duration_distance_text = "from $lastZIPCity to {$rec->ZIPCity} $distance km, <b>$duration min</b>";
        }

        if (
            ($key == count($array) - 1) ||
            ($key > 0 && isset($array[$key + 1]) && $array[$key + 1]->route_id != $rec->route_id)
        ) {
            $ures = gmap($db, $dbr, $rec->all_shipping_address_only, $lasware_Address, $rec->route_id, -1);
            $last_distance = 0;
            $last_duration = 0;
            if ($ures['status'] == 'OK') {
                $last_distance = round(($ures['route']['leg']['distance']['value'] / 1000));
                $last_distance_txt = $last_distance . " km";
                $last_duration = round($speed_factor * ($ures['route']['leg']['duration']['value'] / 60));
                $last_duration_txt = $last_duration . " mins";
            }
            else {
                for ($i = 0; $i < count($ures['geocoded_waypoint']); $i++) {
                    if ($ures['geocoded_waypoint'][$i]['geocoder_status'] != 'OK') {
                        $addr = ($i == 0) ? $rec->all_shipping_address_only : $lasware_Address;
                        break;
                    }
                }
                if ($ures['status'] == 'OVER_QUERY_LIMIT') {
                    $last_distance = "<br>status: {$ures['status']}, <a href='{$ures['url']}&key={$ures['key']}' target='_blank'>link</a><br>";
                } else {
                    $last_distance = "<br>Address <span style='color:red'>$addr</span> is wrong (status: {$ures['status']}, <a href='{$ures['url']}&key={$ures['key']}' target='_blank'>link</a>)<br>";
                }
            } // addr 1 2 3

            $q = "SELECT DATE_ADD('$start_datetime', INTERVAL $last_duration minute)";
            $last_start_datetime = $dbr->getOne($q);

            $q = "UPDATE `route` SET `last_route_distance` = '$last_distance'
                , `last_route_duration` = '$last_duration'
                , `last_route_duration_distance_text` = 'from $lastZIPCity to {$lasware_ZIPCity} $distance km, <b>$last_duration min</b>'
                , `last_route_proposition_time_text` = '$last_start_datetime'
                WHERE `id` = '{$rec->route_id}'";
            $db->query($q);


            if(!$last_duration) $last_duration = round($speed_factor * ($ures['route']['leg']['duration']['value'] / 60));
            $route_duration_distance_text .= "<div style=\"margin-top:40px;border:2px solid #F704B7\">Back to WH $lasware_Address: $last_distance km, <b>$last_duration mins</b>";
            // calculate time to WH
            $q = "SELECT DATE_ADD('$start_datetime', INTERVAL (IFNULL(IFNULL(rdt.minutes, auction.route_delivery_other_minutes), 0) + $duration +  $last_duration) minute)
            FROM auction
            LEFT JOIN route_delivery_type rdt ON rdt.id=auction.route_delivery_type
            WHERE auction_number={$rec->auction_number}
            and txnid={$rec->txnid}";
            $end_datetime = $dbr->getOne($q);
            $route_duration_distance_text .= "<br><br>End time:<br><b>$end_datetime</b></div>";
        }

//        $route_duration_distance_text = mysql_real_escape_string($route_duration_distance_text);
        $array[$key]->route_duration_distance_text = $route_duration_distance_text;

        if(!$duration) $duration = 0;

        $q = "SELECT DATE_ADD('$start_datetime', INTERVAL $duration minute)";
        $start_datetime_text = $dbr->getOne($q);
        $confirmed = $array[$key]->shipping_order_datetime_confirmed;
        if(!$confirmed){
            list($start_date, $start_time_text) = explode(' ', $start_datetime_text);
            $hour = date('H', strtotime($start_time_text));
            $minute = date('i', strtotime($start_time_text));
            
            $minutes2round = 5;
            $minute = (ceil($minute) % $minutes2round === 0) ? 
                ceil($minute) : 
                round(($minute + $minutes2round / 2) / $minutes2round) * $minutes2round;
            if($minute == '60'){
                $minute = '00';
                $hour++;
            }
//            if ($minute >= 0 && $minute < 15) {
//                $minute = '00';
//            }
//            elseif ($minute > 15 && $minute < 45) {
//                $minute = '30';
//            }
//            elseif ($minute > 45) {
//                $minute = '00';
//                $hour++;
//            }
            $start_time_text = "$hour:$minute:00";
            $array[$key]->start_time_date = $start_date;
            $array[$key]->start_time_text = $start_time_text;
//            $array[$key]->shipping_order_time = $start_time_text;
        }

        $q = "UPDATE `auction` SET
             `route_distance` = '$distance'
            , `route_duration` = '$duration'
            , `route_duration_distance_text` = '$route_duration_distance_text'
            , `route_proposition_time_text` = '$start_datetime_text'
            " . (!$confirmed ? ", shipping_order_date = '$start_date'" : "") . "
            " . (!$confirmed ? ", shipping_order_time = '$start_time_text'" : "") . "
            WHERE `auction_number` = '{$rec->auction_number}' AND `txnid` = '{$rec->txnid}'";
        $db->query($q);

        $q = "SELECT DATE_ADD('$start_datetime', INTERVAL (IFNULL(IFNULL(rdt.minutes, auction.route_delivery_other_minutes), 0) + $duration) minute)
            FROM auction
            LEFT JOIN route_delivery_type rdt ON rdt.id=auction.route_delivery_type
            WHERE auction_number={$rec->auction_number}
            and txnid={$rec->txnid}";
        $start_datetime = $dbr->getOne($q);

        $lastAddress = $rec->all_shipping_address_only;
        $lastZIPCity = $rec->ZIPCity;
    }
}

/**
 * Evaluate time when car back to end warehouse
 * @param int $routeId
 * @return false|string false if can not evaluate finish time
 */
function getFinishRouteTime($routeId)
{
    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
    $routeId = (int)$routeId;

    $q = "SELECT auction_number
        , txnid
        , route_proposition_time_text
        , route_delivery_type
        , route_duration_distance_text
        ,concat(route_id, LPAD(100000*ROUND(route_label,1),11,'0')) route_id_label
    FROM auction
    WHERE route_id = $routeId
    ORDER BY route_id_label DESC
    LIMIT 1";
    $lastDeliveryInfo = $dbr->getRow($q);
    if (empty($lastDeliveryInfo)) return false;

    // get finish time in case it was calculated using 'Recalc distances'
    preg_match('~\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}~', $lastDeliveryInfo->route_duration_distance_text, $matches);
    if (count($matches)) {
        return date('Y-m-d H:i:s',strtotime($matches[0]));
    }

    // if not, calculate it
    $lastCustomerTime = $lastDeliveryInfo->route_proposition_time_text;

    // driver extra time
    $q = "SELECT minutes
    FROM route_delivery_type
    WHERE id = {$lastDeliveryInfo->route_delivery_type}";
    $deliveryTypeTime = $dbr->getOne($q);

    // last auction address
    $q = "SELECT `key`, value
    FROM auction_par_varchar
    WHERE auction_number = {$lastDeliveryInfo->auction_number}
    AND txnid = {$lastDeliveryInfo->txnid}
    AND (
        `key` = 'zip_shipping'
        OR `key` = 'country_shipping'
        OR `key` = 'city_shipping'
        OR `key` = 'street_shipping'
        OR `key` = 'house_shipping'
    )";
    $lastDeliveryAddressRaw = $dbr->getAssoc($q);

    $lastDeliveryAddress = implode(', ', array_values([
        $lastDeliveryAddressRaw['zip_shipping'],
        $lastDeliveryAddressRaw['country_shipping'],
        $lastDeliveryAddressRaw['city_shipping'],
        $lastDeliveryAddressRaw['street_shipping'] != 'removed' ? $lastDeliveryAddressRaw['street_shipping'] : '',
        $lastDeliveryAddressRaw['house_shipping'],
    ]));

    // return to last wirehouse duration
    $q = "SELECT IFNULL(
        CONCAT(address1, ', ', address2, ', ', address3),
        IF(TRIM(route.end_address) = '', '', CONCAT(route.end_address, ', ', route.end_country))
    )
    FROM route
    LEFT JOIN warehouse ON warehouse.warehouse_id = route.end_warehouse_id
    WHERE route.id = $routeId";
    $warehouseAddress = $dbr->getOne($q);

    if (!trim($warehouseAddress)) {
        $route = json_decode(gmapApiQuery($lastDeliveryAddress, $warehouseAddress, []));
        $timeToVarehouse = $route->routes[0]->legs[0]->duration->value;
    } else {
        $timeToVarehouse = 0;
    }
    $end_timestamp = strtotime($lastCustomerTime) + $deliveryTypeTime * 60 + $timeToVarehouse;
    $end_time = date("Y-m-d H:i:s", $end_timestamp);
    if($end_time){
        $q = "UPDATE auction
        SET route_duration_distance_text = '$end_time'
        WHERE auction_number = {$lastDeliveryInfo->auction_number}
        AND txnid = {$lastDeliveryInfo->txnid}";
        $db->query($q);
    }

    return $end_time;
}

function gmap($db, $dbr, $address1, $address2, $auction_number = null, $txnid = null)
{
    $function = "gmap('$address1', '$address2')";
    $result = cacheGet($function, 0, '');
    if ($result) {
        return json_decode($result, $assoc = true);
    }

    require_once 'XML/Unserializer.php';
    require_once 'lib/Config.php';
    global $interface_context;
    $url = 'https://maps.googleapis.com/maps/api/directions/xml'
        . '?origin=' . urlencode($address1)
        . '&destination=' . urlencode($address2);
    $us = new XML_Unserializer();

    $n = str_replace('GMAP_API_key', '', str_replace('_default', '', Config::get($db, $dbr, 'defgmap')));
    if (!strlen($n)) {
        $n = 1;
    }

    for ($i = 1; $i <= 8; $i++) {
        $GMAP_API_key = Config::get($db, $dbr, 'GMAP_API_key' . $n);
        $res = file_get_contents($url . '&key=' . $GMAP_API_key, false, $interface_context);
        $us->unserialize($res);
        $ures = $us->getUnserializedData();
        gmap_stat($url, $ures['status'], $auction_number, $txnid);
        $ures['url'] = $url;
        $ures['key'] = $GMAP_API_key;

        if (
            $ures['status'] == 'OK' ||
            $ures['status'] == 'NOT_FOUND' ||
            $ures['status'] == 'ZERO_RESULTS'
        ) {
            break;
        }
        elseif ($ures['status'] == 'OVER_QUERY_LIMIT') {
            $n++;
            $n = ($n > 8) ? 1 : $n;
            Config::set($db, $dbr, 'defgmap', "GMAP_API_key{$n}_default");
            sleep(1);
        }
    };

    if($ures['status'] == 'OK'){
        cacheSet($function, 0, '', json_encode($ures));
    }

    return $ures;
}


    // The radius of the earth
    define('EARTH_RADIUS', 6372795);

    /*
     * The distance between the two points
     * $φA, $λA - latitude, longitude 1st point,
     * $φB, $λB - latitude, longitude 2nd point
     */
    function calculate_distance ($φA, $λA, $φB, $λB) {

        // translate coordinates in radians
        $lat1 = $φA * M_PI / 180;
        $lat2 = $φB * M_PI / 180;
        $long1 = $λA * M_PI / 180;
        $long2 = $λB * M_PI / 180;

        // sines and cosines of the latitudes and longitudes of the difference
        $cl1 = cos($lat1);
        $cl2 = cos($lat2);
        $sl1 = sin($lat1);
        $sl2 = sin($lat2);
        $delta = $long2 - $long1;
        $cdelta = cos($delta);
        $sdelta = sin($delta);

        // calculating the length of the great circle
        $y = sqrt(pow($cl2 * $sdelta, 2) + pow($cl1 * $sl2 - $sl1 * $cl2 * $cdelta, 2));
        $x = $sl1 * $sl2 + $cl1 * $cl2 * $cdelta;

        //
        $ad = atan2($y, $x);
        $dist = $ad * EARTH_RADIUS;

        return $dist;
    }

    function get_ip_location($ip, $format="xml") {

        /* Set allowed output formats */
        $formats_allowed = array("json", "xml", "raw");

        /* IP location query url */
        $query_url = "http://iplocationtools.com/ip_query.php?ip=";

        /* Male sure that the format is one of json, xml, raw.
           Or else default to xml */
        if(!in_array($format, $formats_allowed)) {
            $format = "xml";
        }

        $query_url = $query_url . "{$ip}&output={$format}";

        /* Init CURL and its options*/
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $query_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        /* Execute CURL and get the response */
        $html = curl_exec($ch);

        $lat_i = strpos($html,'Latitude');
        $lng_i = strpos($html,'Longitude');
        $str_lat = substr($html,$lat_i+18,8);
        $lat = doubleval($str_lat);
        $str_lng = substr($html,$lng_i+20,8);
        $lng = doubleval($str_lng);

        $zip_i = strpos($html,'Postal Code');
        $city_i = strpos($html,'City Name');
        $zip_str = explode('<',substr($html,$zip_i+33,10))[0];
        $city_str = explode('<',substr($html,$city_i+43,20))[0];

        $result= new stdClass();
        $result->lat = $lat;
        $result->lng = $lng;
        $result->zip = $zip_str;
        $result->city = $city_str;

        return 	$result;
    }

    function get_coordinates($address)
    {
        $xml = simplexml_load_file('http://maps.google.com/maps/api/geocode/xml?address='.$address);

        $status = $xml->status;

        if ($status == 'OK')
        {
            return $xml->result->geometry->location;
        }
        return ;
    }

    function save_customer_coordinates($db, $customer_ids,$latilong)
    {
        $db->query(
            "update customer set latilong = '".$latilong."' where id in (".$customer_ids.")"
            );
    }

    function save_craft_coordinates($db, $cc_id,$address)
    {
        $db->query('update customer_craft set latilong = '.$address.' where id = '.$cc_id);
    }

    function get_distances($db, $dbr, $address1, $address2) {
        if (!strlen(trim($address1)) || !strlen(trim($address2))) return;
                $ures = gmap($db, $dbr, $address1, $address2);
                if ($ures['status']=='OK') {
                    $distance = round(($ures['route']['leg']['distance']['value']/1000));
                } else {
                    $distance = $ures['status'];
                }
        return $distance;

    }

    function get_distance_duration($address1, $address2) {
                $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
                $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
                $ures = gmap($db, $dbr, $address1, $address2);
                $rec = array();
                if ($ures['status']=='OK') {
                    $rec['distance'] = round(($ures['route']['leg']['distance']['value']/1000));
                    $rec['duration'] = round(($ures['route']['leg']['duration']['value']/60));
//					$distance_txt = $distance." km";
//					$duration = round(($ures['route']['leg']['duration']['value']/60));
//					$duration_txt = $duration." mins";
                } else {
                    $rec['distance'] = $ures['status'];
                    return $distance;
                }
            return $rec;
    }

function check_route_overload($db, $dbr, $auction_number, $txnid) {
    $auction = new Auction($db, $dbr, $auction_number, $txnid);
        $car = $dbr->getRow("SELECT r.use_max_shipping_capacity
                     ,car.weight*config_w.value weight_car
                     ,trailer.weight*config_w.value weight_trailer
                     ,(car.height_int*car.width_int*car.length_int)*config.value/1000000 volume_car
                     ,(trailer.height_int*trailer.width_int*trailer.length_int)*config.value/1000000 volume_trailer
                     FROM route r
                     LEFT JOIN cars car ON car.id = r.car_id
                     LEFT JOIN cars trailer ON trailer.id = r.trailer_car_id
                     JOIN config on config.name='carvolumeCfactor'
                     JOIN config config_w on config_w.name='carweightCfactor'
                     WHERE r.id =".(int)$auction->get("route_id"));
        if (!$car->use_max_shipping_capacity) return false;
        $routes = $dbr->getOne("select group_concat(id) from route where main_route_id=".(int)$auction->get("route_id"));
        if (strlen($routes)) $routes = (int)$auction->get("route_id").', '.$routes;
        else $routes = (int)$auction->get("route_id");
        $loaded = $dbr->getRow("select sum(a.weight_per_single_unit*o.quantity) weight, sum(a.volume_per_single_unit*o.quantity) volume
            from auction au
            left join auction mau on au.main_auction_number=mau.auction_number
                and au.main_txnid=mau.txnid
            join orders o on o.auction_number=au.auction_number and o.txnid=au.txnid
            join article a on a.article_id=o.article_id and a.admin_id=0
            where o.sent=0
            and IFNULL(mau.id, au.id)<>".(int)$auction->get("id")."
            and ifnull(mau.route_id, au.route_id) in ($routes)
            and ifnull(mau.shipping_order_datetime_confirmed, au.shipping_order_datetime_confirmed)
#			and ifnull(mau.shipping_order_delivery_confirmed, au.shipping_order_delivery_confirmed)
            ");
        $thisorder = $dbr->getRow("select sum(a.weight_per_single_unit*o.quantity) weight, sum(a.volume_per_single_unit*o.quantity) volume
            from auction au
            left join auction mau on au.main_auction_number=mau.auction_number
                and au.main_txnid=mau.txnid
            join orders o on o.auction_number=au.auction_number and o.txnid=au.txnid
            join article a on a.article_id=o.article_id and a.admin_id=0
            where o.sent=0
            and IFNULL(mau.id, au.id)=".(int)$auction->get("id")."
            ");
        $car->volume1 = $car->volume_car + $car->volume_trailer;
        $car->weight1 = $car->weight_car + $car->weight_trailer;
        if (($car->volume1 < ($loaded->volume + $thisorder->volume) && $car->use_max_shipping_capacity==2)
            ) {
                return "2: $car->volume1 < $loaded->volume + $thisorder->volume";
        }
        if (($car->weight1 < ($loaded->weight + $thisorder->weight) && $car->use_max_shipping_capacity==1)
            ) {
                return "1: $car->weight1 < $loaded->weight + $thisorder->weight";
        }
        if ((($car->volume1 < ($loaded->volume + $thisorder->volume)
                || $car->weight1 < ($loaded->weight + $thisorder->weight)) && $car->use_max_shipping_capacity==3)
            ) {
                return "3:  $car->volume1 < $loaded->volume + $thisorder->volume || $car->weight1 < $loaded->weight + $thisorder->weight";
        }
        if (
            /*($car->weight < ($loaded->weight + $thisorder->weight))
            ||*/
            ($car->volume1 < ($loaded->volume + $thisorder->volume) && $car->use_max_shipping_capacity==2)
            ||
            ($car->weight1 < ($loaded->weight + $thisorder->weight) && $car->use_max_shipping_capacity==1)
            ||
            (($car->volume1 < ($loaded->volume + $thisorder->volume)
                || $car->weight1 < ($loaded->weight + $thisorder->weight)) && $car->use_max_shipping_capacity==3)
            ) {
                return true;
        } else {
                return false;
        }
    }

    function listBonus($db, $dbr, $username, $lang, $group_id=0, $inactive='', $country_code='', $sas=array()){
        if (!strlen($country_code)) $country_code = $dbr->getOne("select ebaycountry from seller_information where username='$username'");
        $date_format = $dbr->getOne("select date_format_invoice from seller_information where username='$username'");
        $q = "select shop_bonus.id
            , shop_bonus.shop_id
            , shop_bonus.description_url
            , shop_bonus.article_id
            , shop_bonus.ordering
            , (select def from shop_bonus_seller where bonus_id=shop_bonus.id and username='$username') def
            , shop_bonus.inactive
            , shop_bonus.add_date
            , shop_bonus.add_date_exclude_sat
            , shop_bonus.add_date_exclude_sun
            , shop_bonus.add_date_exclude_holi
            , shop_bonus.add_date_days
            , date(date_add(NOW(),interval shop_bonus.add_date_days day)) shipon_date
            , date(date_add(NOW(),interval shop_bonus.add_date_days-1 day)) shipon_date_1
            , date_format(date(date_add(NOW(),interval shop_bonus.add_date_days day)), '$date_format') shipon_date_formatted
            , shop_bonus.group_id
            , shop_bonus.type_in_group,
            (SELECT `value`
                FROM translation
                WHERE table_name = 'shop_bonus'
                AND field_name = 'title'
                AND language = '$lang'
                AND id = shop_bonus.id) as title,
            (SELECT `value`
                FROM translation
                WHERE table_name = 'shop_bonus'
                AND field_name = 'description'
                AND language = '$lang'
                AND id = shop_bonus.id) as description
            , shop_bonus_seller.percent
            , shop_bonus_seller.amount
            , shop_bonus.donf_offer_overstocked
            , shop_bonus.dont_offer_overstocked_except1
            , (select group_concat(saved_id)
                from saved_params where par_key = CONCAT('bonus_exclude[',shop_bonus.id,']')) excluded_sas
            , (select group_concat(saved_id)
                from shop_bonus_sa where bonus_id=shop_bonus.id) included_sas
            , (select concat('Was set ',IF(shop_bonus.inactive, 'INACTIVE', 'ACTIVE')
                    , ' by ', IFNULL(u.name, tl.username)
                    , ' on ', tl.updated)
                from total_log tl
                left join users u on u.system_username=tl.username
                where tl.table_name='shop_bonus' and tl.field_name='inactive' and tl.tableid=shop_bonus.id
                order by updated desc limit 1) last_inactive
            , shop_bonus_seller.percent seller_percent
            , shop_bonus_seller.amount seller_amount
            from shop_bonus
            left join shop_bonus_seller on shop_bonus_seller.bonus_id=shop_bonus.id
                    and shop_bonus_seller.username='$username'
            where shop_bonus.country_code='$country_code'
            and (shop_bonus.group_id=$group_id or $group_id=0)
            ".(strlen($inactive)?" and shop_bonus.inactive=$inactive ":'')."
            order by shop_bonus.ordering";
        $bonuses = $dbr->getAll($q);
        foreach($bonuses as $k=>$dummy) {
            $bonuses[$k]->description_nl2br =
                strip_tags(str_replace("\r",'',str_replace("\n",'',nl2br($bonuses[$k]->description))));
        }

        /**
         * Filling shipping arts
         */
        if (count($bonuses) > 0) {
            $bonusesIds = [];
            foreach ($bonuses as $id => $bonus) {
                $bonusesIds[] = $bonus->id;
                $bonuses[$id]->onlyShippingArts = [];
            }
            $queryShippingArts = '
                SELECT shipping_art_id, shop_bonus_id
                FROM shop_bonus_shipping_art
                WHERE shipping_art_id and shop_bonus_id IN (' . implode(', ', $bonusesIds) . ')
            ';
            $shippingArtsRaw = $dbr->getAll($queryShippingArts);
            $shippingArts = [];
            foreach ($shippingArtsRaw as $shippingArt) {
                $shippingArts[$shippingArt->shop_bonus_id][] = $shippingArt->shipping_art_id;
            }
            foreach ($bonuses as $id => $bonus) {
                if (isset($shippingArts[$bonus->id])) {
                    $bonuses[$id]->onlyShippingArts = $shippingArts[$bonus->id];
                }
            }
        }

        return $bonuses;
    }

/**
 *
 * @param MDB2_Driver_mysql $db
 * @param MDB2_Driver_mysql $dbr
 * @param String $username
 * @param String $lang
 * @param String $country_code
 * @return Array
 */
function listBonusGroup($db, $dbr, $username, $lang, $country_code=''){
    if (!strlen($country_code)) {
        $country_code = $dbr->getOne("select ebaycountry from seller_information where username='$username'");
    }

    $query = "select id,ordering,stitle,
        (SELECT `value`
            FROM translation
            WHERE table_name = 'shop_bonus_group'
            AND field_name = 'title'
            AND language = '$lang'
            AND id = shop_bonus_group.id) as title
        from shop_bonus_group
        where country_code='$country_code'
        order by ordering";
    return $dbr->getAll($query);
}

function addLargeDoc($db, $dbr, $id,
                     $name, $description,
                     $fn, $lang, $table_name, $fld, $shop_ids = '0'
)
{
    $name = mysql_escape_string($name);

    $iid = (int)$db->getOne("SELECT iid FROM translation WHERE id='$id'
            AND table_name='$table_name' AND field_name='$fld' AND language = '$lang'");
    if ($iid) {
        $db->query("UPDATE translation SET value='$name' WHERE iid=$iid");
    }
    else {
        $query = "INSERT INTO translation (value, id, table_name, field_name, language)
            VALUES ('$name', '$id', '$table_name', '$fld', '$lang')";
        $db->query($query);
    }

    update_version($table_name, $fld, $id, $lang);

    $content = file_get_contents($fn);
    $md5 = md5($content);

    $filename = set_file_path($md5);
    if ( ! is_file($filename)) {
        file_put_contents($filename, $content);
    }

    $iid = (int)$dbr->getOne("SELECT iid FROM prologis_log.translation_files2 WHERE id='$id'
            AND table_name='$table_name' AND field_name='$fld' AND language = '$lang'");

    if ($iid) {
        $db->query("UPDATE prologis_log.translation_files2 SET md5='$md5' WHERE iid=$iid");
    }
    else {
        $db->query("INSERT INTO prologis_log.translation_files2 (id, table_name, field_name, language, md5)
            VALUES ('$id', '$table_name', '$fld', '$lang', '$md5')");
    }

//    $query = "SELECT DISTINCT shop.id, ftp_server,  ftp_password, ftp_username
//			FROM shop WHERE id IN ($shop_ids)";
//    foreach ($dbr->getAll($query) as $shop) {
//        if ( ! $shop->ftp_server) continue;
//        $conn_id = ftp_connect($shop->ftp_server);
//        $r = ftp_login($conn_id, $shop->ftp_username, $shop->ftp_password);
////        var_dump($r);
//        ftp_pasv($conn_id, TRUE);
//        $buff = ftp_nlist($conn_id, "public_html/images/cache/*picid_" . $id . "_*.*");
//        foreach ($buff as $fn) {
//            if (strpos($fn, "picid_" . $id)) {
//                ftp_delete($conn_id, $fn);
//            }
//        }
//        ftp_close($conn_id);
//    }

    foreach (glob("images/cache/*_picid_" . $id . "_*.*") as $filename) {
        unlink($filename);
    }

    return $id;
}

//function addLargeDoc($db, $dbr, $id,
//                     $name,
//                     $description,
//                     $fn, $lang, $table_name, $fld, $shop_ids = '0'
//)
//{
//    $name = mysql_escape_string($name);
//    global $db_user;
//    global $db_pass;
//    global $db_name;
//    global $db_host_no_port;
//    $pass = escapeshellcmd($db_pass);
//    $user = escapeshellcmd($db_user);
//    $newfn = $fn . '.sql';
//    $iid = (int)$db->getOne("select iid from translation where id='$id'
//			and table_name='$table_name' and field_name='$fld' and language = '$lang'");
//    if ($iid) {
//        $q = "update translation set value='$name' where iid=$iid";
//        $r = $db->query($q);
//        if (PEAR::isError($r)) {
//            aprint_r($r);
//            die();
//        }
//    } else {
//        $q = "insert into translation set value='$name'
//			, id=$id
//			, table_name='$table_name' , field_name='$fld'
//			, language = '$lang'";
//        $r = $db->query($q);
//        if (PEAR::isError($r)) {
//            aprint_r($r);
//            die();
//        }
//    }
//
//    update_version($table_name, $fld, $id, $lang);
//
//    $iid = (int)$dbr->getOne("select iid from prologis_log.translation_files2 where id='$id'
//			and table_name='$table_name' and field_name='$fld' and language = '$lang'");
//
//    $md5 = md5(base64_encode(file_get_contents($fn)));
//    $data_id = (int)$dbr->getOne("select id from prologis_log.data_storage where md5sum='$md5'");
//    if (!$data_id) {
//        file_put_contents($newfn, "insert into prologis_log.data_storage set
//					md5sum='$md5',
//					value='" . base64_encode(file_get_contents($fn)) . "'");
//    }
//    file_put_contents('tmp/pic.sql', file_get_contents($newfn));
//    exec("mysql -u $user --password=$pass -h $db_host_no_port $db_name < $newfn");
//    unlink($newfn);
//    if (!$data_id) $data_id = (int)$db->getOne("select id from prologis_log.data_storage where md5sum='$md5'");
//    if ($iid) {
//        $r = $db->query("update prologis_log.translation_files2 set data_id=$data_id where iid=$iid");
//    } else {
//        $r = $db->query("insert into prologis_log.translation_files2 set
//					id='$id',
//					table_name='$table_name' , field_name='$fld',
//					language = '$lang',
//					data_id=$data_id");
//    }
//    if (PEAR::isError($r)) {
//        aprint_r($r);
//        die();
//    }
//
//    $q = "select distinct shop.id, ftp_server,  ftp_password, ftp_username
//			from shop
//			where id in ($shop_ids)";
//    $shops = $dbr->getAll($q);
//    foreach ($shops as $shop) {
//        if (!strlen($shop->ftp_server)) continue;
//        $conn_id = ftp_connect($shop->ftp_server);
//        $r = ftp_login($conn_id, $shop->ftp_username, $shop->ftp_password);
//        var_dump($r);
//        ftp_pasv($conn_id, TRUE);
//        $buff = ftp_nlist($conn_id, "public_html/images/cache/*picid_" . $id . "_*.*");
//        foreach ($buff as $fn) {
//            if (strpos($fn, "picid_" . $id)) {
//                ftp_delete($conn_id, $fn);
//            }
//        }
//        ftp_close($conn_id);
//    }
//    foreach (glob("images/cache/*_picid_" . $id . "_*.*") as $filename) {
//        unlink($filename);
//    }
//    return $id;
//}

    function comment_notif($db, $dbr, $obj, $obj_id) {
        global $loggedUser, $siteURL;
        $email_invoice = $dbr->getOne("select group_concat(email)
                from comment_notif
                join users on users.username=comment_notif.username
                where obj='$obj' and obj_id=$obj_id");
        if (strlen($email_invoice)) {
            $ret = new stdClass;
            $ret->from = $loggedUser->get('email');
            $ret->from_name = $loggedUser->get('name');
            $user = new User($db, $dbr, $newuser);
            $ret->email_invoice = $email_invoice;
            switch ($obj) {
                case 'issuelog':
                    $ret->auction_number = $obj_id;
                    $ret->txnid = -21;
                    $ret->obj_url = $siteURL."react/logs/issue_logs/".$obj_id."/";
                    $ret->obj_title = 'Issue '.$obj_id;
                break;
                case 'auction':
                    $auction = $dbr->getRow("select * from auction where id=$obj_id");
                    $ret->auction_number = $auction->auction_number;
                    $ret->txnid = $auction->txnid;
                    $ret->obj_url = $siteURL."auction.php?number=".$ret->auction_number."&txnid=".$ret->txnid;
                    $ret->obj_title = 'Auftrag '.$auction->auction_number.'/'.$auction->txnid;
                break;
                case 'rma':
                    $auction = $dbr->getRow("select * from rma where rma_id=$obj_id");
                    $ret->auction_number = $auction->auction_number;
                    $ret->txnid = $auction->txnid;
                    $ret->obj_url = $siteURL."rma.php?rma_id=$obj_id&number=".$ret->auction_number."&txnid=".$ret->txnid;
                    $ret->obj_title = 'Ticket #'.$obj_id;
                break;
                case 'insurance':
                    $auction = $dbr->getRow("select * from insurance where id=$obj_id");
                    $ret->auction_number = $auction->auction_number;
                    $ret->txnid = $auction->txnid;
                    $ret->obj_url = $siteURL."insurance.php?id=$obj_id";
                    $ret->ins_id = $obj_id;
                    $ret->obj_title = 'Insurance case #'.$obj_id;
                break;
                case 'rating':
                    $auction = $dbr->getRow("select * from rating where id=$obj_id");
                    $ret->auction_number = $auction->auction_number;
                    $ret->txnid = $auction->txnid;
                    $ret->obj_url = $siteURL."rating_case.php?id=$obj_id";
                    $ret->rating_case_id = $obj_id;
                    $ret->obj_title = 'Rating case #'.$obj_id;
                break;
            }
            standardEmail($db, $dbr, $ret, 'comment_notification');
        }
    }

 // socks_connect( proxy_host, proxy_port, destination_host, destination_port )
function socks_connect($host, $port, $dh, $dp)
{
  $f = fsockopen($host, $port) or die("Can't connect to proxy");
  $h = gethostbyname($dh);
  preg_match("#(\d+)\.(\d+)\.(\d+)\.(\d+)#", $h, $m);
  fwrite($f, "\x05\x01\x00");
  $r = fread($f, 2);
  if(!( ord($r[0])==5 and ord($r[1])==0))
    die("Invalid SOCKS reply");
  fwrite($f, "\x05\x01\x00\x01" . chr($m[1]).chr($m[2]).chr($m[3]).chr($m[4]).chr($dp/256).chr($dp%256));
  $r = fread($f, 10);
  if(!( ord($r[0])==5 and ord($r[1])==0))
    die("Invalid SOCKS reply");
  return $f;
}

function proxySMTP() {
 $mailserver = "smtp.newmail.ru";

      $user = "login@nm.ru";

      $pass = "12345";

      $mailto = "mail@inbox.ru";


 $host = '78.214.217.106';
$port = '8181';
$id=Base64_Encode("login:pass");
$fp = fsockopen($host, $port)
or die ("ERROR: Could not connect to proxy server $host on port $port");

fputs($fp, "CONNECT $mailserver:25 HTTP/1.0 \r\n");
fputs($fp, "Proxy-Authorization: Basic $id\r\nConnection: close\r\n\r\n");
fputs($fp, "EHLO nm.ru\r\n");
fputs($fp, "AUTH LOGIN\r\n");
fputs($fp, base64_encode($user)."\r\n".base64_encode($pass)."\r\n");
fputs($fp, "MAIL FROM: $user\r\n");
fputs($fp, "RCPT TO: $mailto\r\n");
fputs($fp, "DATA\r\n");

fputs($fp, "Subject: bla\r\n");
fputs($fp, "From: Pupkin <login@nm.ru>\r\n\r\n");
fputs($fp, "text.\r\n\r\n");
fputs($fp, ".\r\n");
echo fread($fp,1024)."<br>";
fputs($fp, "QUIT\r\n");
 /*
 */
fclose ($fp);
}

function proxySurf($proxyHost,$proxyPort) {
    //==========================================================[ Proxy Settings ]
#	 $proxyHost = 'proxy.prov.ru';
#	 $proxyPort = '3128';
     $proxyUser = '';
     $proxyPass = '';
     $proxyAuth = base64_encode ("$proxyUser:$proxyPass");

     //====================================================[ Remote Hots Settings ]
     $host = 'www.beliani.net';
     $port = '80';
     $page = 'ok.htm';
        echo '<br>Try to connect proxy '.$proxyHost.', '.$proxyPort;
     //========================================================[ Proxy Connection ]
     $fp = fsockopen ($proxyHost, $proxyPort, $errno, $errstr, 30);
     if (!$fp) {
         echo "$errstr ($errno)";
     } else {
//		 echo '<br> Get page '."GET http://$host/$page HTTP/1.0\r\nHost: $host\r\nProxy-Authorization: $proxyAuth\r\n\r\n<br>";
        $str = "GET http://$host/$page HTTP/1.0\r\nHost: $host\r\n\r\n";
         echo '<br> Get page '.$str."<br>";
         fputs ($fp, $str);
         while (!feof($fp)) {
             $buffer .= fgets ($fp, 128);
         }
         fclose ($fp);
     }

     print ($buffer);
     echo '<br><hr><br>';
     return $buffer;
 }

    function proxySurfCURL($proxyHost,$proxyPort) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_URL, 'https://www.beliani.net/ok.htm');
        curl_setopt($curl, CURLOPT_USERAGENT, 'Opera/9.00 (Windows NT 5.1; U; ru)');
//		curl_setopt($curl, CURLOPT_PROXY, "$proxyHost:$proxyPort");
        curl_setopt($ch, CURLOPT_SSLVERSION, 3);
        //curl_setopt($curl, CURLOPT_PROXYUSERPWD, "����:������");
        $out = curl_exec ($curl);
         print_r ($out);
         echo '<br><hr><br>';
         return $out;
    }

function post_content ($proxy) {
  $uagent = "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.1.4322)";
  if($proxy != null) {
    $pr = explode(":",$proxy->address);
  }
  $ch = curl_init( $url );

  curl_setopt($ch, CURLOPT_URL, $proxy->url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_HEADER, 1);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
  if($proxy != null) {
    curl_setopt($ch, CURLOPT_PROXY, $pr[0].":".$pr[1]);
//	echo 'Proxy '.$pr[0].":".$pr[1];
  }
  curl_setopt($ch, CURLOPT_ENCODING, "");
  curl_setopt($ch, CURLOPT_USERAGENT, $uagent);  // useragent
  curl_setopt($ch, CURLOPT_TIMEOUT, $proxy->timeout);
  curl_setopt($ch, CURLOPT_COOKIEJAR, "cook.txt");
  curl_setopt($ch, CURLOPT_COOKIEFILE,"cook.txt");
     curl_setopt($ch, CURLOPT_SSLVERSION, 3);

  $content = curl_exec( $ch );
  $err     = curl_errno( $ch );
  $errmsg  = curl_error( $ch );
  $header  = curl_getinfo( $ch );
  curl_close( $ch );

  $header['errno']   = $err;
  $header['errmsg']  = $errmsg;
  $header['content'] = $content;
  return $header;
}

function deleteComment($db, $dbr, $id) {
    return $db->query("delete from comments where id=$id");
};

/**
 * @description get comments and alarms
 * @param object DB $db
 * @param object DB $dbr
 * @param string $table
 * @param int $id
 * @return array of objects
 */
function getComments($db, $dbr, $table, $id) {
    $comments = $dbr->getAll("
        SELECT c.id
            , c.content `comment`
            , tl.updated create_date
            , u.username
            , '' AS prefix
            , IFNULL(u.name, tl.username) full_username
            , u.username cusername
        FROM comments c
        JOIN total_log tl ON tl.table_name='comments'
            AND tl.field_name = 'id'
            AND tl.TableID = c.id
        LEFT JOIN users u ON u.system_username=tl.username
            WHERE c.obj_id = " . $id . "
                AND c.obj = '" . $table . "'
        UNION ALL
        SELECT NULL as id
            , alarms.comment
            , (SELECT updated from total_log WHERE table_name='alarms' AND tableid=alarms.id limit 1) AS create_date
            , users.username
            , CONCAT('Alarm (',alarms.status,'):') as prefix
            , users.name full_username
            , users.username cusername
        FROM alarms
        LEFT JOIN users ON users.username = alarms.username
        WHERE alarms.type_id = " . $id . "
            AND alarms.type = '" . $table . "'
        ORDER BY create_date");
    if (PEAR::isError($comments)) { print_r($comments);}
    return $comments;
};

/***/
function addComment($db, $dbr, $table, $id, $text) {
    $q = "insert into comments (content, obj_id, obj) values ('$text', $id, '$table')";
    $r = $db->query($q);
    if (PEAR::isError($r)) { print_r($r);}
    return mysql_insert_id();
};

function check_pars($saved_id, $key, $value) {
    global $db, $dbr;

    global $langs;

    $bad_params = [
        'saved_custom_params',
        'def[',
        'custom_cat_par[',
        'inactivedoc[',
        'shopimgordering[',
        'dimensionsdoc[',
        'framepreview[',
        'youtube_code[',
        'docchange_alt[',
        'docchange_title[',
        'docchange_use[',
        '%youtube_codenew',
        '%docnew_alt',
        '%docnew_title',
        '%sim_sa',
        '%other_sa',
        '%other_NameID',
        'sim_ordering[',
        'saved_master_pics',
    ];

    foreach ($langs as $_lang) {
        $bad_params[] = "[$_lang]";
    }

    foreach ($bad_params as $_param) {
        if (stripos($key, $_param) !== false) {
//            var_dump($key);
            return false;
        }
    }

    $value = mysql_escape_string($value);

    $sp_id = $db->getRow("SELECT id, par_value FROM saved_params WHERE saved_id=$saved_id AND par_key='$key'");

    if ($value === '') {
        if ($sp_id && $sp_id->id /*&& $sp_id->par_value != $value*/) {
            $query = "DELETE FROM saved_params WHERE id={$sp_id->id}";
            $db->query($query);
        }
        return false;
    }

    $query = false;
    if ($sp_id && $sp_id->id && $sp_id->par_value != $value) {
        $query = "UPDATE saved_params SET par_value='$value' WHERE id={$sp_id->id}";
    }
    else if ( ! $sp_id || ! $sp_id->id) {
        $query = "INSERT INTO saved_params SET saved_id=$saved_id, par_key='$key', par_value='$value'";
    }

//    if (stripos($key, 'custom_cat_par') !== false) {
//        var_dump($query);
//    }

    if ($query) {
        $db->query($query);
    }

    return true;
}

function update_pars($details, $saved_id) {
    global $db, $dbr, $mulang_fields, $debug, $content_title_nolang, $names_array_lang;
    $saved_id = (int)$saved_id;

    $langs = $dbr->getAssoc('select lang AS `k`, lang AS `v` from seller_lang group by `k`');

    if ($debug) echo 'update_pars INIT: '. xdebug_time_index() .'<br>';

    $old_details = unserialize($dbr->getOne("SELECT details FROM saved_auctions WHERE id=$saved_id"));
#	print_r($details['ShopSAAlias']);
    foreach($details['ShopSAAlias'] as $lang_id => $new_value) {
        $old_value = $dbr->getOne("SELECT value FROM translation
                WHERE table_name='sa' AND field_name='ShopSAAlias' AND language='$lang_id' AND id=$saved_id");
#		echo '<br>$old_value='.$old_value;
        if ( ! strlen($new_value)) {
            $new_value = $dbr->getOne("SELECT fget_aliasbyid('{$details['ShopDesription'][$lang_id]}')");
        }

        if ($old_value != $new_value && strlen($new_value)) {
#			echo '<br>add redirects';
            foreach($content_title_nolang as $shop_id=>$route) {
#				echo '<br>for shop '.$shop_id.' and lang '.$lang_id.' route is '.$route[$lang_id];
                $redirect_id = $dbr->getOne("SELECT id FROM redirect WHERE src_url='{$route[$lang_id]}{$old_value}.html'");
                if ($redirect_id) {
                    $query = "UPDATE redirect SET dest_url='{$route[$lang_id]}{$new_value}.html' WHERE id=$redirect_id";
                }
                else {
                    $query = "INSERT IGNORE INTO redirect (src_url, req_url, dest_url)
                        VALUES ('{$route[$lang_id]}{$old_value}.html', '{$route[$lang_id]}{$old_value}.html', '{$route[$lang_id]}{$new_value}.html')";
                }
                $db->query($query);
            }
        } // if changed
    } // for changed ShopSAAlias
#	die('update_pars');

if ($debug) echo 'update_pars 0: '. xdebug_time_index() .'<br>';

    $shop_catalogue_id = [];
    $query = "SELECT DISTINCT REPLACE(REPLACE(par_key,'shop_catalogue_id[',''),']','') par_key
        FROM saved_params WHERE saved_id=$saved_id AND par_key LIKE 'shop_catalogue_id%'";
    foreach($dbr->getAll($query) as $shop) {
        $shop_catalogue_id[$shop->par_key] = [];

        $cats_query = "SELECT par_value FROM saved_params WHERE saved_id=$saved_id AND par_key = 'shop_catalogue_id[{$shop->par_key}]'";
        foreach($dbr->getAll($cats_query) as $cat) {
            $shop_catalogue_id[$shop->par_key][] = $cat->par_value;
        }
    }

    if ($shop_catalogue_id != $_POST['shop_catalogue_id']) {
        $details['shop_catalogue_changed'] = (int)$details['shop_catalogue_changed'] + 1;
        $db->execParam("UPDATE saved_auctions SET details=? WHERE id=?", [serialize($details), $saved_id]);
    }

    foreach ($details['ratings_inherited_from'] as $rat_id => $rat_value) {
        if ( ! $rat_value) {
            unset($details['ratings_inherited_from'][$rat_id]);
        }
    }

    $queries = [
        "DELETE FROM saved_params WHERE saved_id=$saved_id AND par_key LIKE 'ratings_inherited_from%'",
        "DELETE FROM saved_params WHERE saved_id=$saved_id AND par_key LIKE 'fixedprice'",
        "DELETE FROM saved_params WHERE saved_id=$saved_id AND par_key LIKE 'shop_catalogue_id%'",
        "DELETE FROM saved_params WHERE saved_id=$saved_id AND par_key LIKE 'stop_empty_warehouse%'",
        "DELETE FROM saved_params WHERE saved_id=$saved_id AND par_key LIKE 'InternationalShippingContainer%'",
        "DELETE FROM saved_params WHERE saved_id=$saved_id AND par_key LIKE 'sp[%'",
    ];
    foreach ($queries as $query) {
        $db->query($query);
    }

    $shops = $dbr->getAssoc("SELECT id f1, id f2 FROM shop");
    foreach($shops as $id=>$id) {
        $db->query("DELETE FROM saved_params WHERE saved_id=$saved_id AND par_key = 'shops[$id]'");
        if (isset($details["shops[$id]"])) {
            $db->query("INSERT INTO saved_params SET saved_id=$saved_id, par_key='shops[$id]', par_value='$id'");
            unset($details["shops[$id]"]);
        }
    }

if ($debug) echo 'update_pars 1: '. xdebug_time_index() .'<br>';
    foreach ($details as $key=>$value) {
if ($debug) echo "update_pars 1-1 ($key) : ". xdebug_time_index() .'<br>';
        if (0 && $old_details[$key]==$details[$key]) {
            continue;
        }

        if (is_array($value)) {
            foreach($value as $key1 => $value1) {
                if (is_array($value1)) {
                    foreach($value1 as $key2 => $value2) {
                        if (is_array($value2)) {
                            $par_key = mysql_escape_string("{$key}[{$key1}][{$key2}]");
                            $db->query("DELETE FROM saved_params WHERE saved_id=$saved_id AND par_key = '$par_key'");
                            foreach($value2 as $value3) {
                                $value3 = mysql_escape_string($value3);
                                $query = "INSERT INTO saved_params SET saved_id=$saved_id, par_key='$par_key', par_value='$value3'";
                                $db->query($query);
                            }
                        }
                        else {
                            if ($key == 'shop_catalogue_id') {
                                $par_key = mysql_escape_string("{$key}[{$key1}]");
                                $value2 = mysql_escape_string($value2);

                                $sp_id = $dbr->getOne("SELECT id FROM saved_params WHERE saved_id=$saved_id and par_key='$par_key'");
                                if (0 && $sp_id) {
                                    $query = "UPDATE saved_params SET par_value='$value2' WHERE id=$sp_id";
                                }
                                else {
                                    $query = "INSERT INTO saved_params SET saved_id=$saved_id, par_key='$par_key', par_value='$value2'";
                                }
                                $db->query($query);
                            }
                            else {
                                $par_key = mysql_escape_string("{$key}[{$key1}][{$key2}]");
                                check_pars($saved_id, $par_key, $value2);
                            }
                        } // if value2 scalar
                    }
                }  // if (is_array($value1))
                else {
                    $par_key = mysql_escape_string("{$key}[{$key1}]");
                    check_pars($saved_id, $par_key, $value1);
                }
            }
        }  // if (is_array($value))
        else {
            $par_key = mysql_escape_string("$key");
            check_pars($saved_id, $par_key, $value);
        }
//if ($debug) { echo "for : $key => "; var_dump($value); '<br>';}
    }
//	print_r($mulang_fields); print_r($_POST['ShopSAAlias']); echo '$saved_id='.$saved_id;
if ($debug) echo 'update_pars 2: '. xdebug_time_index() .'<br>';

    if (in_array('descriptionTextShop2', $mulang_fields) && isset($_POST['descriptionTextShop2'])) {
        if ((int)$old_details['master_shop']) {
            $shop_id = (int)$old_details['master_shop'];
        }
        else if ($old_details['siteid']) {
            $old_details['siteid'] = (int)$old_details['siteid'];
            $shop_id = $dbr->getOne("SELECT id FROM shop WHERE username='{$old_details['username']}'
                    AND siteid={$old_details['siteid']} ORDER BY id LIMIT 1");
        }

        if ($shop_id) {
            foreach ((array)$_POST['descriptionTextShop2'] as $lang => $dummy) {
                $descriptionTextShop2_def_translations[$lang] = \Saved::getDescriptionTextShop2($saved_id, $shop_id, $lang);
            }

            foreach ((array)$_POST['descriptionTextShop2'] as $_lang => $_value) {
                if ( ! $_value && isset($descriptionTextShop2_def_translations[$_lang])) {
                    $_POST['descriptionTextShop2'][$_lang] = $descriptionTextShop2_def_translations[$_lang];
                }
            }
        }
    }

    mulang_fields_Update($mulang_fields, 'sa', $saved_id);
if ($debug) echo 'update_pars 3: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
    #$db->query("call sp_Alias_SA()"); removed, Niki agreed
if ($debug) echo 'update_pars 4: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
    if (isset($_POST['snooze'])
            || isset($_POST['snooze_shop'])
            || isset($_POST['snooze_amazon'])
            || isset($_POST['snooze_ricardo'])
            || isset($_POST['snooze_allegro'])
            ) {
        $db->query("update saved_auctions set inactive=0 where id=".$saved_id);
    }
if ($debug) echo 'update_pars 5: '.round((getmicrotime()-$time),3).'<br>';if ($debug) $time = getmicrotime();
//	die();
}

function checkInactiveMerchantArticle($db, $dbr, $id) {
    $errors = array();
    $rec = $dbr->getRow("select * from merchant_item where id=$id");
    if (!strlen($rec->merchant_article_id)) $errors[] = "You cannot activate item without 'Product ID of merchant'";
    $sa = $dbr->getRow("select * from saved_auctions where id={$rec->saved_id}");
    $vars = unserialize($sa->details);
    if (!strlen($vars['ShopPrice'])) $errors[] = "You cannot activate item without 'Product Price'";
    if (!count($vars['shop_catalogue_id'][$rec->shop_id])) $errors[] = "You cannot activate item without 'Merchant MAIN category'";
    $langs = $dbr->getAssoc("select distinct language f1 ,language f2 from translation where table_name='sa' and id={$rec->saved_id}
        and field_name in ('ShopDesription', 'descriptionTextShop1') and value<>''
    union
        select distinct language,language from translation where table_name='saved_doc' and id in
            (select doc_id from saved_doc where saved_id={$rec->saved_id} and `primary` and not file)
        and field_name in ('data') and value<>''");
    if (!count($langs)) $langs=array('choose any');
    if ($dbr->getOne("select count(*) from translation where table_name='sa' and id={$rec->saved_id}
            and field_name = 'ShopDesription' and value<>'' and language in ('".implode("','",$langs)."')")<count($langs))
        $errors[] = "You cannot activate item without 'Product title' in all used languages '".implode("','",$langs)."'";
    if ($dbr->getOne("select count(*) from translation where table_name='sa' and id={$rec->saved_id}
            and field_name = 'descriptionTextShop1' and value<>'' and language in ('".implode("','",$langs)."')")<count($langs))
        $errors[] = "You cannot activate item without 'Product description' in all used languages '".implode("','",$langs)."'";
/*	if ($dbr->getOne("select count(*) from translation where table_name='saved_doc' and id in
            (select doc_id from saved_doc where saved_id={$rec->saved_id} and `primary` and not file)
            and field_name = 'data' and value<>'' and language in ('".implode("','",$langs)."')")<count($langs))
        $errors[] = "You cannot activate item without 'Main picture URL' in all used languages '".implode("','",$langs)."'";*/
        // we need to check the defshcountry of the seller
    return $errors;
}

function check_user_pw($password) {
        if ( strlen( $password ) < Config::get($db, $dbr, 'user_pw_length') )  {
            $res .= 'Password must be longer '.Config::get($db, $dbr, 'user_pw_length').' chars<br>';
        }

        /*** get the numbers in the password ***/
        preg_match_all('/[A-Z]/', $password, $numbers);
        if ( count($numbers[0]) < Config::get($db, $dbr, 'user_pw_upp_letters') )  {
            $res .= 'Password must have minimum '.Config::get($db, $dbr, 'user_pw_upp_letters').' capital chars<br>';
        }

        /*** get the numbers in the password ***/
        preg_match_all('/[a-z]/', $password, $numbers);
        if ( count($numbers[0]) < Config::get($db, $dbr, 'user_pw_low_letters') )  {
            $res .= 'Password must have minimum '.Config::get($db, $dbr, 'user_pw_low_letters').' small chars<br>';
        }

        /*** get the numbers in the password ***/
        preg_match_all('/[0-9]/', $password, $numbers);
        if ( count($numbers[0]) < Config::get($db, $dbr, 'user_pw_digits') )  {
            $res .= 'Password must have minimum '.Config::get($db, $dbr, 'user_pw_digits').' digits<br>';
        }

        /*** check for special chars ***/
        preg_match_all('/[|!@#$%&*\/=?,;.:\-_+~^\\\]/', $password, $specialchars);
        if ( count($specialchars[0]) < Config::get($db, $dbr, 'user_pw_spec_chars') )  {
            $res .= 'Password must have minimum '.Config::get($db, $dbr, 'user_pw_spec_chars').' special chars<br>';
        }
        return $res;
}

function get_shop_url($db, $dbr, $shops_obj, $saved_id) {
    $shop_rec = $dbr->getRow("select par_value, substring_index(substring_index(par_key,'[',-1),']',1) shop_id
        from saved_params
        join shop_catalogue_shop scs on par_value=scs.shop_catalogue_id and scs.shop_id=8
        where par_key like 'shop_catalogue_id[%]'
        and saved_id={$saved_id} and scs.hidden=0
        order by saved_params.id desc limit 1");
    $cat_id = (int)$shop_rec->par_value;
    $shop_id = (int)$shop_rec->shop_id;
    if (!isset($shops_obj[$shop_id])) $shops_obj[$shop_id] = new Shop_Catalogue($db, $dbr, $shop_id);
    global $getall;
    $getall = 1;
    $cats = $shops_obj[$shop_id]->getAllNodes($cat_id);
    $cats = array_reverse($cats);
    $lang_id = $shops_obj[$shop_id]->_shop->lang;
            $cat_route = ''; #"lang/$lang_id/";
            foreach($cats as $cat) {
                if ($cat) $cat_route .= $dbr->getOne("
                    SELECT `value`
                    FROM translation
                    WHERE table_name = 'shop_catalogue'
                    AND field_name = 'alias'
                    AND language = '{$lang_id}'
                    AND id = ".$cat."
                    ").'/';
            }
    $ShopSAAlias = $dbr->getOne("SELECT translation.`value`
        FROM sa{$shop_id} sa
        join translation on translation.id=sa.id
        WHERE 1
        AND translation.table_name = 'sa'
        AND translation.language = '{$lang_id}'
        AND translation.field_name = 'ShopSAAlias'
        AND sa.id = {$saved_id}
        ");
    $shopurl = ($shops_obj[$shop_id]->_shop->ssl?'https':'http')
        .'://www.'.$shops_obj[$shop_id]->_shop->url
        .'/'.$cat_route.$ShopSAAlias.'.html';
    return $shopurl;
}

/**
 *
 * @global array $configs Global congigs
 * @global array $routes_array
 * @global array $route_labels_array
 * @param object $rec
 * @return array
 */
function getGMAPaddress($rec) {
        global $configs;
        global $routes_array;
        global $route_labels_array;

    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
    if(!$configs) $configs = \Config::getAll($db, $dbr);

    $auction = new \Auction($db, $dbr, $rec->auction_number, $rec->txnid);
    $english = \Auction::getTranslation($db, $dbr, $auction->get('siteid'));

    /**
     * @description get shipping comments
     */

    /**
     * @description check if comment hidden or empty
     */
    $comment_check = 0;
    foreach ($auction->getShippingComments() as $comment) {
        if ($comment->comment == "hidden" || $comment->comment == '') {
            $comment_check++;
        }
    }

    /**
     * @description gather comment's table
     */
    $comments = '';
    if (count($auction->getShippingComments()) && count($auction->getShippingComments()) != $comment_check) {
        $comments .= '<div style="text-align: center;"><b>Comments</b></div>';
        $comments .= '<table border="1" style="font-size: 12px;">';
        $comments .= '<tr>';
        $comments .= '<td>Date</td>';
        $comments .= '<td>Author</td>';
        $comments .= '<td>Comment</td>';
        $comments .= '</tr>';
        foreach ($auction->getShippingComments() as $comment) {
            if ($comment->comment != "hidden" && $comment->comment != '') {
                $comments .= '<tr>';
                $comments .= '<td>' . $comment->create_date . '</td>';
                $comments .= '<td>' . $comment->full_username . '</td>';
                $comments .= '<td>' . $comment->comment . '</td>';
                $comments .= '</tr>';
             }
        }
        $comments .= '</table>';
    } else {
        $comments .= '<div style="text-align: center;"><b>No comments</b></div>';
    }

    $address = [];

                $address['route_id'] = $rec->route_id;
                $address['route_label_notags'] = $rec->route_label_notags;
                $address['route_ignore'] = $rec->route_ignore;
                $address['address'] = str_replace('"','',$auction->get('street_shipping').' '.$auction->get('house_shipping'))
                    .(strlen($auction->get('street_shipping'))+strlen($auction->get('house_shipping'))?', ':'')
                    .$auction->get('zip_shipping').' '.$auction->get('city_shipping').', '.$auction->get('country_shipping');

                $paid = $dbr->getRow("select max(payment_date) payment_date, DATEDIFF(now(), max(payment_date)) payment_diff
                    from payment where auction_number={$rec->auction_number} and txnid={$rec->txnid}");
                $paid_date = $paid->payment_date;
                $payment_diff = strip_tags($rec->days_due);
    if ($payment_diff <= $configs['shipping_order_period3_days'])
        $paid_color = $configs['shipping_order_period3_color'];
    if ($payment_diff <= $configs['shipping_order_period2_days'])
        $paid_color = $configs['shipping_order_period2_color'];
    if ($payment_diff <= $configs['shipping_order_period1_days'])
        $paid_color = $configs['shipping_order_period1_color'];
    if ($rec->shipping_order_datetime_confirmed)
        $paid_color = $configs['shipping_order_period5_color'];
    if ($rec->priority_all || $auction->get('priority')) {
        $paid_color = $configs['shipping_order_period4_color'];
    }

                $routes_options = '';
                foreach($routes_array as $route_id=>$route_name) {
                    $routes_options .= '<option value="'.$route_id.'"'
                    .($route_id==$auction->get('route_id')?' selected':'').'>'.$route_name.'</option>';
                }

    $address['auction'] = '<a target="_blank" href="auction.php?number=' . $rec->auction_number . '&txnid=' . $rec->txnid . '">Sales#' . $rec->auction_number . '/' . $rec->txnid . '</a>';
                $address['title'] = '<a target="_blank" href="auction.php?number='.$rec->auction_number.'&txnid='.$rec->txnid.'">Sales#'.$rec->auction_number.'/'.$rec->txnid.'</a><br>'
                    .'<a target="_blank" href="shipping_auction.php?number='.$rec->auction_number.'&txnid='.$rec->txnid.'">Shipping Order#'.$rec->auction_number.'/'.$rec->txnid.'</a><br>'
            . ($auction->get('delivery_date_customer') != '0000-00-00' ? ($auction->get('delivery_date_customer') < date("Y-m-d") ? '<font color="red">Shipping date: ' . $auction->get('delivery_date_customer') . "</font>" : 'Shipping date: ' . $auction->get('delivery_date_customer')) . '<br>' : '')
                    .(strlen($auction->get('company_shipping'))?$auction->get('company_shipping').'<br>':'')
                    .$english[$auction->get('gender_shipping')].' '.$auction->get('firstname_shipping').' '.$auction->get('name_shipping').'<br>'
                    .$address['address'].'<br>'
                    .'Tel fix '.$auction->get('tel_shipping').'<br>'
                    .'Tel mobile '.$auction->get('cel_shipping').'<br>'
                    .'End time '.$auction->get('end_time').'<br>'
                    .'Planned shipping date '.$auction->get('delivery_date_customer').'<br>'
                    .'Paid date '.$paid_date.'<br>'
                    .'# of Days due <font color="'.$paid_color.'">'.$rec->days_due.'</font><br>'
                    .'Delivery date <b>'.$auction->get('shipping_order_date').'&nbsp;'.$auction->get('shipping_order_time').'</b><br>'
                    .'Route <select id="route_id_'.$auction->get('id').'" name="route_id"><option label="" value="">---</option>'
                    .$routes_options
                      .'</select>Sequence <input id="route_label_'.$auction->get('id').'" name="route_label" value="'.$auction->get('route_label').'" type="text" size="3">'
            . '<input type="button" value="Update" id="route_btn_' . $auction->get('id') . '" onClick="changeRoute(' . $auction->get('id') . ')"/>';

    $address['color'] = str_replace('#', '', $paid_color);
    $address['label'] = $route_labels_array[$auction->get('route_id')] . ": " . $auction->get('route_label');
    // articlees info
    $q = "select orders.quantity, a.admin_id, a.article_id, orders.id, orders.reserve_warehouse_id,
            GROUP_CONCAT(CONCAT(
                IF(orders.manual=0,CONCAT(orders.quantity, ' x <a target=\"_blank\" href=\"article.php?original_article_id=',orders.article_id,'\">'),'')
                                ,IF(a.admin_id and a.article_id='', CONCAT('Driver task: <b>',a.description,'</b>'),
                                concat('<b>',orders.article_id, '</b>: '
                                , IF(IFNULL(orders.custom_title,'')='',IFNULL(t.value,a.name),orders.custom_title))),IF(orders.manual=0,'</a>','')
                ) ORDER BY orders.manual DESC SEPARATOR '<br>') a_href
                , IF(wwo.id, CONCAT(wwa.qnt, ' x WWO#',wwo.id,CONCAT(' (',wwo.comment,')'),';', IF(wwa.delivered,' <span style=\"color:green\">arrived</span>','')), '') state_wwo
                , wwo.id wwo_id
                from orders
                LEFT JOIN wwo_article wwa ON orders.wwo_order_id=wwa.id
                LEFT JOIN ww_order wwo ON wwa.wwo_id=wwo.id
                join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
                join article a on a.article_id=orders.article_id and a.admin_id=orders.manual
                left join translation t on a.article_id=t.id and t.table_name='article' and t.field_name='name' and t.language='german'
                where ((orders.auction_number={$rec->auction_number} and orders.txnid={$rec->txnid})
                OR (au.main_auction_number={$rec->auction_number} and au.main_txnid={$rec->txnid}))
                    and (orders.manual=0 or (orders.manual=2 and
                        orders.article_id in (select bonus_id from route_delivery_type_bonus where delivery_type_id=0)
                ))";
    $articles = $dbr->getAll($q);
    $address['articles'] = '';
    foreach ($articles as $au) {
        if (!$au->admin_id && strlen($rec->wr_start_country_code)) {
            $q = "select warehouse.name, warehouse.warehouse_id
                    , round(fget_Article_stock('{$au->article_id}', warehouse.warehouse_id)
                        - fget_Article_reserved('{$au->article_id}', warehouse.warehouse_id)) stock
                    from warehouse where country_code='{$rec->wr_start_country_code}' and not inactive
                    order by trim(warehouse.name)";
            $wares = $db->getAll($q);
            $q = "select distinct wwo.id, planned_arrival_date
                        , ROUND((select sum(wwa1.qnt*a1.volume) from ww_order wwo1
                        join wwo_article wwa1 on wwa1.wwo_id=wwo1.id
                        join article a1 on wwa1.article_id=a1.article_id and a1.admin_id=0
                        where wwo1.id=wwo.id),2) wwo_volume
                        , c.volume car_volume
                        , CONCAT(' (',wwo.comment,')') comment
                    from ww_order wwo
                    left join cars c on c.id=wwo.car_id
                    join wwo_article wwa on wwa.wwo_id=wwo.id
                    join article a on wwa.article_id=a.article_id and a.admin_id=0
                    where /*wwa.article_id='{$au->article_id}' and*/ wwa.to_warehouse={$rec->start_warehouse_id}
                    and not wwo.blocked and not closed";
            $wwos = $dbr->getAll($q);
            $wwo_list = 'WWO List:<br>';
            foreach ($wwos as $w) {
                $wwo_list .= '<a href="ware2ware_order.php?id=' . $w->id . '" target="_blank">WWO#' . $w->id . $w->comment . '</a> Planned on ' . $w->planned_arrival_date . ' <font color="' . ($w->wwo_volume > $w->car_volume ? 'red' : 'green') . '">' . $w->wwo_volume . 'm3</font>'
                        . '. Add <input type="text" id="wwo_text_' . $w->id . '_' . $au->id . '" size="3"> pcs from <select id="wwo_select_' . $w->id . '_' . $au->id . '">' . $warehouse_options . '</select> to this WWO <input type="button" value="Add" id="wwo_btn_' . $w->id . '_' . $au->id . '" onClick="add_to_wwo(' . $w->id . ', ' . $au->id . ')"/><br>';
            }
        } else {
            $q = "select warehouse.name, warehouse.warehouse_id
                            , round(fget_Article_stock('{$au->article_id}', warehouse.warehouse_id)
                                - fget_Article_reserved('{$au->article_id}', warehouse.warehouse_id)) stock
                            from warehouse where country_code='" . countryToCountryCode($rec->shipping_country) . "' and not inactive";
            $wares = $db->getAll($q);
            $wwo_list = '';
            $warehouse_options_reserve = '';
        }
        if (!$au->admin_id) {
            $warehouse_options_reserve = '';
            foreach ($warehouses as $warehouse_id => $warehouse_name) {
                $warehouse_options_reserve .= '<option value="' . $warehouse_id . '"'
                        . ($warehouse_id == $au->reserve_warehouse_id ? ' selected' : '') . '>' . $warehouse_name . '</option>';
            }
            $warehouse_options_reserve = '<br><select id="reserve_warehouse_id_select[' . $au->id . ']" onChange="change_orders_db(\'reserve_warehouse_id\',' . $au->id . ',this.value)"><option value="0">---</option>'
                    . $warehouse_options_reserve
                    . '</select><br>';
                    }
                    $address['articles'] .= $au->a_href
                        .(strlen($au->state_wwo)?"<br><a target='_blank' style='font-size:10px;color:#FF00FF' href='ware2ware_order.php?id={$au->wwo_id}'>{$au->state_wwo}</a>":'')
                        .$warehouses_table
                        .'<br>';
                }
                $address['title'] .= '<br>'.$address['articles'];

    // display volume
    $allorder = \Order::listAll($db, $dbr, $rec->auction_number, $rec->txnid, 1, 'german', '0,1', 1);
    $volume = 0;
    $weight = 0;
    foreach ($allorder as $item) {
        if ($item->admin_id == 0) {
            $q = "select sum(dimension_h*dimension_l*dimension_w/1000000) * $item->quantity / $item->items_per_shipping_unit as volume
            , sum(weight_parcel) * $item->quantity / $item->items_per_shipping_unit as weight
            from article_parcel
            where article_id={$item->article_id}";
            $res = $dbr->getRow($q);
            $volume += $res->volume;
            $weight += $res->weight;
        }
    }
    $volume = round($volume, 2);
    $weight = round($weight, 2);
    $address['title'] .= "<div style=\"text-align:right; margin-top:10px\">$volume m3, $weight kg</div>";
    $address['volume'] = $volume;
    $address['weight'] = $weight;
                /**
                 * @description add comments
                 */
                $address['title'] .= '<br>' . $comments;
    $address['id'] = $rec->id;
    $address['labelcnt'] = count($address['route_label_notags']) . $address['route_label_notags'];
    $address['gps'] = $auction->data->gps_lat . "," . $auction->data->gps_lng;
    return $address;
}

/**
 * Get unread message for employee
 *
 * @param int $emp_id
 * @return object
 */
function get_emp_messages($emp_id) {
    $db = \label\DB::getInstance(\label\DB::USAGE_READ);
    return $db->getAll("SELECT * FROM `emp_message`
            WHERE `emp_id` = '$emp_id' AND `read` = 0 ORDER BY `id`");
}

function get_emp_monitor($db, $dbr, $username) {
    $qrystr = "select CONCAT(e.name, ' ', e.name2, ': ', IF(login, 'Logged in to ', 'Logged out from '), a.company, ' on ', `time`) log
        , ut.login, m.id
        from emp_user_monitor m
        join employee e on e.id=m.emp_id
        join user_timestamp ut on ut.username=e.username
        join company c on c.id=ut.company_id
        join address_obj ao on ao.obj_id=c.id and ao.obj='company'
        join address a on a.id=ao.address_id
        where m.showme and m.username='$username'
        order by ut.`time` desc limit 1
        ";
        $emp_monitor = $dbr->getRow($qrystr);
    return($emp_monitor);
}

/**
 *
 * Get minstocks + some pareameters by $offer_id
 *
 * @global type $article_obj_array
 * @global type $debug
 * @param type $db
 * @param type $dbr
 * @param type $saved_id
 * @param type $offer_id
 * @param type $stop_empty_warehouse
 * @param type $cache
 * @return type
 */
function getMinStock($db, $dbr, $saved_id, $offer_id, $stop_empty_warehouse, $cache, $warehouse_migration = false)
{
    global $article_obj_array;
    global $debug;

    $q = "SELECT al.article_id, al.default_quantity, article.deleted, op_company.name op_company_name
        FROM article_list al
        JOIN offer_group og ON al.group_id = og.offer_group_id and not base_group_id
		join article on article.article_id=al.article_id and article.admin_id=0
		left join op_company on op_company.id=article.company_id
        WHERE og.offer_id =? and not al.inactive and not og.additional and al.default_quantity > 0";

    if ($debug) {
        echo "$q<br>";
    }

    $articles = $dbr->getAll($q, null, [$offer_id]);
    $minstocks = [];
    $minavas = [];
    $sumstocks = [];
    $sumavas = [];
    $weight = 0;
    $total_article_number = 0;
    $allparcels = [];
    $stock_cache = [];
    $ava_cache = [];
    $ava_cache_understaffed = [];
    $default_quantities = [];

    if (!isset($article_obj_array)) {
        $article_obj_array = [];
    }

    foreach ($articles as $ka => $rarticle) {
        $article_id = $rarticle->article_id;
        $default_quantities[$rarticle->article_id] = $rarticle->default_quantity;
        if (!isset($article_obj_array[$article_id])) {
            $article_obj_array[$article_id] = new Article($db, $dbr, $article_id, -1, -1);
        }
        $parcels = Article::getParcels($db, $dbr, $article_id);
        $allparcels = array_merge($allparcels, $parcels);
        $articles[$ka]->parcels = $parcels;
        $weight += (int)$rarticle->default_quantity * $article_obj_array[$article_id]->get("weight_per_single_unit");
        $total_article_number += $article_obj_array[$article_id]->get("items_per_shipping_unit");
    }

    $stop_empty_warehouse = array_unique($stop_empty_warehouse);
    foreach ($stop_empty_warehouse as $warehouse_id) {
        $minstocks[$warehouse_id] = 1000000;
        $minavas[$warehouse_id] = 1000000;
        foreach ($articles as $rarticle) {
            $article_id = $rarticle->article_id;
            if (!isset($stock_cache[$article_id][$warehouse_id])) {
                $pc = $article_obj_array[$article_id]->getPieces($warehouse_id, $cache);
                $stock_cache[$article_id][$warehouse_id] = $pc;
                if ($debug) {
                    echo "Stock for article $article_id warehouse $warehouse_id: $pc<br>";
                }
                $reserved = $article_obj_array[$article_id]->getReserved($warehouse_id, $cache);
                if ($debug) {
                    echo "Reserved for article $article_id warehouse $warehouse_id: $reserved<br>";
                }
                $ava_cache[$article_id][$warehouse_id] = floor(($pc - $reserved) / $default_quantities[$article_id]);
                $ava_cache_understaffed[$article_id][$warehouse_id] = $pc - $reserved;
                if ($debug) {
                    echo "Available for article $article_id warehouse $warehouse_id: " . $ava_cache[$article_id][$warehouse_id] . "<br>";
                }
            } else {
                continue;
            }

            $minstocks[$warehouse_id] = min($minstocks[$warehouse_id], $stock_cache[$article_id][$warehouse_id]);
            if ($debug) {
                echo "Minstock for warehouse $warehouse_id: " . $minstocks[$warehouse_id] . "<br>";
            }

            $minavas[$warehouse_id] = min($minavas[$warehouse_id], $ava_cache[$article_id][$warehouse_id]);
            if ($debug)
                echo "Minava for warehouse $warehouse_id: " . $minavas[$warehouse_id] . "<br>";

            $sumavas[$article_id] = $ava_cache[$article_id][$warehouse_id] > $sumavas[$article_id]
                ? $ava_cache[$article_id][$warehouse_id]
                : $sumavas[$article_id];

            if ($debug)
                echo $sumavas[$article_id] . " -> sumavas plus for $warehouse_id : " . $ava_cache[$article_id][$warehouse_id] . " -> " . $sumavas[$article_id] . "<br>";

            $sumstocks[$article_id] += $stock_cache[$article_id][$warehouse_id];
        }
    }

    $minstock = max(0, min($sumstocks));
    if ($debug) {
        echo "minstock for all warehouses : " . print_r($sumstocks, true) . "<br>";
    }
    
    /* Calculating min available if warehouse migration is turned OFF */
    $minava_without_migration = 0;
    foreach ($minavas as $minavas_warehouse) {
        $minava_without_migration += $minavas_warehouse;
    }
    
    /* Calculating min available if warehouse migration is turned ON */
    $summ = [];
    foreach ($ava_cache_understaffed as $article_id => $ware_data) {
        foreach ($ware_data as $qty) {
            $summ[$article_id] = $summ[$article_id] + $qty;
        }
    }
    
    $minava_with_migration = 1000000;
    foreach ($summ as $article_id => $total) {
        $boxed = floor($total / $default_quantities[$article_id]);
        $minava_with_migration = $minava_with_migration > $boxed ? $boxed : $minava_with_migration;
    }
    
    /* Select min available depending from 'warehouse migration' switch */
    $minava = $warehouse_migration ? $minava_with_migration : $minava_without_migration;

    if ($debug) {
        echo "minavas for all warehouses : " . print_r($sumavas, true) . "<br>";
    }

    return compact('minstock', 'minava', 'minstocks', 'minavas', 'articles', 'weight', 
        'total_article_number', 'allparcels', 'minava_with_migration', 'minava_without_migration');
}

function sort_barcode_state_log($a, $b)
{
    $res = -strcmp($a->updated, $b->updated);
    return $res;
}
/**
 * Calculate SA Margin
 * @param int $saved_id
 * @param array $predefined Predefined parameters that will overwrite db values
 * @return Object
 */
function getSAMargin($saved_id, $predefined = []) {
global $dbr;

    $LowPrice = isset($predefined['LowPrice']) ? (int)$predefined['LowPrice'] : 'LowPrice';

    $vars = Saved::getDetails($saved_id);
    switch ($dbr->getOne("select seller_channel_id
        from seller_information si
        join saved_params sp on sp.saved_id=$saved_id and sp.par_key='username' and sp.par_value=si.username")) {
        case 1: if ($vars['fixedprice']) {
            $shipping_plan_fn = 'fshipping_plan';
            $shipping_plan_id_fn = 'fshipping_plan_id';
            $shipping_plan_free_fn = 'fshipping_plan_free_tr';
                } else {
            $shipping_plan_fn = 'shipping_plan';
            $shipping_plan_id_fn = 'shipping_plan_id';
            $shipping_plan_free_fn = 'shipping_plan_free_tr';
                }
        break;
        case 2: if ($vars['Ricardo']['Channel']==2) {
            $shipping_plan_fn = 'fshipping_plan';
            $shipping_plan_id_fn = 'fshipping_plan_id';
            $shipping_plan_free_fn = 'fshipping_plan_free_tr';
                } else {
            $shipping_plan_fn = 'shipping_plan';
            $shipping_plan_id_fn = 'shipping_plan_id';
            $shipping_plan_free_fn = 'shipping_plan_free_tr';
                }
        break;
        case 3: $shipping_plan_id_fn = 'sshipping_plan_id';
            $shipping_plan_fn = 'sshipping_plan';
            $shipping_plan_free_fn = 'sshipping_plan_free_tr';
        break;
        case 4: $shipping_plan_id_fn = 'sshipping_plan_id';
            $shipping_plan_fn = 'sshipping_plan';
            $shipping_plan_free_fn = 'sshipping_plan_free_tr';
//			if ($vars['master_sa']) $saved_id=$vars['master_sa'];
        break;
        case 5: if ($vars['Allegro']['Channel']==2) {
            $shipping_plan_fn = 'fshipping_plan';
            $shipping_plan_id_fn = 'fshipping_plan_id';
            $shipping_plan_free_fn = 'fshipping_plan_free_tr';
                } else {
            $shipping_plan_fn = 'shipping_plan';
            $shipping_plan_id_fn = 'shipping_plan_id';
            $shipping_plan_free_fn = 'shipping_plan_free_tr';
                }
        break;
    }
$q = "select tt.*
    , ($LowPrice+shipping_cost)/(1+vat_percent/100)-total_purchase_price_local_sh_vat margin_abs
    , 100*(($LowPrice+shipping_cost)/(1+vat_percent/100)-total_purchase_price_local_sh_vat)/($LowPrice+shipping_cost) margin_perc
    , ($LowPrice+shipping_cost)  ShopPriceSh
    from (select t.*
    , ROUND(rate/eurrate,2) rate2eur
    , ROUND(total_purchase_price/(rate/eurrate),2) total_purchase_price_local
    , ROUND(prev_total_purchase_price/(rate/eurrate),2) prev_total_purchase_price_local
    , ROUND((ROUND(total_purchase_price/(rate/eurrate),2)+real_shipping_cost)
        ,2) total_purchase_price_local_sh_vat
    from (select sa.id, offer.offer_id, offer.name
    , group_concat(CONCAT('<a href=\"article.php?original_article_id=',al.article_id,'\" target=\"_blank\">',al.article_id,'</a>') order by al.article_id separator '<br>') article_id
    , group_concat(al.default_quantity order by al.article_id separator '<br>') default_quantity
    , sum(al.default_quantity*
        IFNULL((select article_import.purchase_price from article_import
                    where article_import.country_code=IF(defcalcpricecountry='',defshcountry,defcalcpricecountry)
                    and article_import.article_id=a.article_id
                    order by import_date desc limit 1
                    ),(select article_import.purchase_price from article_import
                    where article_import.article_id=a.article_id
                    order by import_date desc limit 1
                    ))) purchase_price
    , sum(al.default_quantity*
        IFNULL((select article_import.total_item_cost from article_import
                    where article_import.country_code=IF(defcalcpricecountry='',defshcountry,defcalcpricecountry)
                    and article_import.article_id=a.article_id
                    order by import_date desc limit 1
                    ),(select article_import.total_item_cost from article_import
                    where article_import.article_id=a.article_id
                    order by import_date desc limit 1
                    ))) total_purchase_price
    , sum(al.default_quantity*
        IFNULL((select article_import.total_item_cost from article_import
                    where article_import.country_code=IF(defcalcpricecountry='',defshcountry,defcalcpricecountry)
                    and article_import.article_id=a.article_id
                    order by import_date desc limit 1,1
                    ),(select article_import.total_item_cost from article_import
                    where article_import.article_id=a.article_id
                    order by import_date desc limit 1,1
                    ))) prev_total_purchase_price
    , IF(si.free_shipping=0 or ROUND(IF(si.seller_channel_id=1, sp_startprice.par_value,
        IF(si.seller_channel_id=2, IF(sp_fixed2.par_value=1, sp_Rstartprice.par_value, sp_RBuyItNowPrice.par_value),
        IF(si.seller_channel_id=3, IFNULL(sp_amazonPrice1.par_value,sp_amazonPrice2.par_value),
        IF(si.seller_channel_id=4, sp_ShopPrice.par_value, IF(si.seller_channel_id=5,sp_allegroPrice.par_value,0)
        )))))<IFNULL(si.free_shipping_above,0),
        IF(IF(si.seller_channel_id=1, IF(sp_fixed1.par_value=1, IF(offer.fshipping_plan_free, 1, IFNULL(t_shipping_plan_free.value, 0))
            , IF(offer.shipping_plan_free, 1, IFNULL(t_shipping_plan_free.value, 0)))
        , IF(si.seller_channel_id=2, IF(sp_fixed2.par_value=2, IF(offer.fshipping_plan_free, 1, IFNULL(t_shipping_plan_free.value, 0))
            , IF(offer.shipping_plan_free, 1, IFNULL(t_shipping_plan_free.value, 0)))
        , IF(si.seller_channel_id=3, IF(offer.ashipping_plan_free, 1, IFNULL(t_shipping_plan_free.value, 0))
        , IF(si.seller_channel_id=4, IF(offer.sshipping_plan_free, 1, IFNULL(t_shipping_plan_free.value, 0))
        , IF(si.seller_channel_id=5, IF(offer.fshipping_plan_free, 1, IFNULL(t_shipping_plan_free.value, 0))
            , IF(offer.shipping_plan_free, 1, IFNULL(t_shipping_plan_free.value, 0))
        ))))),0, spc.shipping_cost)
        ,0) shipping_cost
    , offer.sshipping_plan_free shipping_plan_free
    , IF(spc.estimate,spc.real_shipping_cost,IFNULL(diff_scc.real_shipping_cost, scc.real_shipping_cost)) real_shipping_cost
        , si.defshcountry
    , ca.value currency
    , IFNULL((select value from rate where curr_code=ca.value order by date desc limit 1),1) rate
    , (select value from rate where curr_code='EUR' order by date desc limit 1) eurrate
    , vat.vat_percent
    , si.seller_channel_id
    , sp_fixed1.par_value fixed1
    , sp_fixed2.par_value fixed2
    , ROUND(IF(si.seller_channel_id=1, sp_startprice.par_value,
        IF(si.seller_channel_id=2, IF(sp_fixed2.par_value=1, sp_Rstartprice.par_value, sp_RBuyItNowPrice.par_value),
        IF(si.seller_channel_id=3, IFNULL(sp_amazonPrice1.par_value,sp_amazonPrice2.par_value),
        IF(si.seller_channel_id=4, sp_ShopPrice.par_value, IF(si.seller_channel_id=5,sp_allegroPrice.par_value,0)
    ))))) LowPrice
    , IF(si.seller_channel_id=1, sp_BuyItNowPrice.par_value,
        IF(si.seller_channel_id=2, IF(sp_fixed2.par_value=1, sp_RBuyItNowPrice.par_value, 0),
        IF(si.seller_channel_id=3, 0,
        IF(si.seller_channel_id=4, sp_ShopHPrice.par_value, 0
    )))) TopPrice
    , IF(si.seller_channel_id=1, IF(sp_fixed1.par_value=1, 'f', '')
        , IF(si.seller_channel_id=2, IF(sp_fixed2.par_value=2, 'f', '')
        , IF(si.seller_channel_id=3, 'a'
        , IF(si.seller_channel_id=4, 's', ''
    )))) chr
    , IF(si.seller_channel_id=1, IF(sp_fixed1.par_value=1, 'Fixed', 'Auction')
        , IF(si.seller_channel_id=2, IF(sp_fixed2.par_value=2, 'Fixed', 'Auction')
        , IF(si.seller_channel_id=3, 'Amazon'
        , IF(si.seller_channel_id=4, 'Shop', ''
    )))) type
    , si.username
    , sa.inactive
    from saved_auctions sa
    left join saved_params master_sa on sa.id=master_sa.saved_id and master_sa.par_key='master_sa'
    join saved_params sp_offer on /*IF(IFNULL(master_sa.par_value,0)=0, sa.id, master_sa.par_value)*/ sa.id = sp_offer.saved_id and sp_offer.par_key='offer_id'
    left join saved_params sp_amazonPrice1 on sa.id=sp_amazonPrice1.saved_id and sp_amazonPrice1.par_key = 'amazon[item-price]'
    left join saved_params sp_amazonPrice2 on sa.id=sp_amazonPrice2.saved_id and sp_amazonPrice2.par_key = 'amazon[ItemPrice]'
    left join saved_params sp_fixed1 on sa.id=sp_fixed1.saved_id and sp_fixed1.par_key='fixedprice'
    left join saved_params sp_fixed2 on sa.id=sp_fixed2.saved_id and sp_fixed2.par_key='Ricardo[Channel]'
    left join saved_params sp_startprice on sa.id=sp_startprice.saved_id and sp_startprice.par_key='startprice'
    left join saved_params sp_BuyItNowPrice on sa.id=sp_BuyItNowPrice.saved_id and sp_BuyItNowPrice.par_key='BuyItNowPrice'
    left join saved_params sp_ShopPrice on sa.id=sp_ShopPrice.saved_id and sp_ShopPrice.par_key='ShopPrice'
    left join saved_params sp_ShopHPrice on sa.id=sp_ShopHPrice.saved_id and sp_ShopHPrice.par_key='ShopHPrice'
    left join saved_params sp_Rstartprice on sa.id=sp_Rstartprice.saved_id and sp_Rstartprice.par_key='Ricardo[startprice]'
    left join saved_params sp_RBuyItNowPrice on sa.id=sp_RBuyItNowPrice.saved_id and sp_RBuyItNowPrice.par_key='Ricardo[BuyItNowPrice]'
    left join saved_params sp_allegroPrice on sa.id=sp_allegroPrice.saved_id and sp_allegroPrice.par_key = 'Allegro[BuyItNowPrice]'
	left join saved_params sp_allegroChannel on sa.id=sp_allegroChannel.saved_id and sp_allegroChannel.par_key = 'Allegro[Channel]'
    join offer on sp_offer.par_value=offer.offer_id
    join offer_group og on sp_offer.par_value=og.offer_id
    join article_list al on al.group_id=og.offer_group_id
    join article a on a.article_id=al.article_id and a.admin_id=0
    join saved_params sp_username on sa.id=sp_username.saved_id and sp_username.par_key='username'
    join saved_params sp_site on sa.id=sp_site.saved_id and sp_site.par_key='siteid'
    join config_api ca on ca.siteid = sp_site.par_value
    JOIN config_api_par cap ON ca.par_id = cap.id AND cap.name = 'currency'
    join seller_information si on si.username=sp_username.par_value
    left join translation t_shipping_plan_id
        on t_shipping_plan_id.language=sp_site.par_value
        and t_shipping_plan_id.id=sp_offer.par_value
        and t_shipping_plan_id.table_name='offer'
        and t_shipping_plan_id.field_name='$shipping_plan_id_fn'
    left join translation t_shipping_plan_free
        on t_shipping_plan_free.language=sp_site.par_value
        and t_shipping_plan_free.id=sp_offer.par_value
        and t_shipping_plan_free.table_name='offer'
        and t_shipping_plan_free.field_name='$shipping_plan_free_fn'
    left join shipping_plan sp on sp.shipping_plan_id=t_shipping_plan_id.value
    left join shipping_plan_country spc on spc.shipping_plan_id=t_shipping_plan_id.value
        and spc.country_code = IF(defcalccountry='',defshcountry,defcalccountry)
    left join shipping_cost_country scc on scc.shipping_cost_id=sp.shipping_cost_id
        and scc.country_code = IF(defcalccountry='',defshcountry,defcalccountry)
    left join shipping_plan diff_sp on diff_sp.shipping_plan_id=spc.diff_shipping_plan_id
    left join shipping_cost_country diff_scc on diff_scc.shipping_cost_id=spc.diff_shipping_plan_id
        and diff_scc.country_code = IF(defcalccountry='',defshcountry,defcalccountry)
    left join vat on vat.country_code = IF(defcalccountry='',defshcountry,defcalccountry) and vat.country_code_from = IF(defcalccountry='',defshcountry,defcalccountry)
        and NOW() between vat.date_from and vat.date_to
    where not sa.old
    and sa.id=".$saved_id."
    and al.default_quantity and not al.inactive and si.isActive
    ) t ) tt
";
    global $debug;
    if ($debug) echo $q.'<br>';
    $margin = $dbr->getRow($q);

    if (isset($predefined['LowPrice'])) {
        $margin->LowPrice = (int)$predefined['LowPrice'];
    }

    return $margin;
}

function fetchfromstring($message) {
    global $smarty, $smartyStringTemplates;
            $smartyStringTemplates = array('{php}date(){/php}', $message);
            $smarty->caching = false;
            $smarty->clear_compiled_tpl("string:1");
            $message = $smarty->fetch("string:1");
    return $message;
}

function warestock_color($dbr, $start_warehouse_country, $au) {
    $q = "select warehouse.name, warehouse.warehouse_id, warehouse.country_code
        , fget_Article_stock_cache('{$au->article_id}', warehouse.warehouse_id, 24) stock
        , fget_Article_reserved_cache('{$au->article_id}', warehouse.warehouse_id, 24) reserved
        from warehouse
        where warehouse.country_code='$start_warehouse_country' and not warehouse.inactive
        order by trim(warehouse.name)";
    $wares = $dbr->getAll($q);
    $warehouses_table = '<table border="1">';
    $warestock = '';
    foreach($wares as $w) {
        if ($au->reserve_warehouse_id==$w->warehouse_id) {
//								$warestock = ($w->stock-$w->reserved)." of <b>".(int)$w->stock."</b> in ".$w->name." warehouse";
            $warestock = "<b>".(int)$w->stock."</b>";
            $warestock_color = $w->reserved?'orange':'';
        }
        $warehouses_table .= '<tr><td>';
        if (($w->stock-$w->reserved)>=$au->quantity)
            $warehouses_table .= '<span style="color:green;font-size:xx-small">'.$w->country_code.' '.$w->name.': '.$w->stock.'</span>';
        elseif (($w->stock-$w->reserved)>0)
            $warehouses_table .= '<span style="color:black;font-size:xx-small">'.$w->country_code.' '.$w->name.': '.$w->stock.'</span>';
        else
            $warehouses_table .= '<span style="color:red;font-size:xx-small">'.$w->country_code.' '.$w->name.': '.$w->stock.'</span>';
        $warehouses_table .= '</td></tr>';
    }
    if (!strlen($warestock)) {
            $q = "select warehouse.name, warehouse.warehouse_id
                , fget_Article_stock('{$au->article_id}', warehouse.warehouse_id) stock
                , fget_Article_reserved('{$au->article_id}', warehouse.warehouse_id) reserved
                from warehouse where 1
                    and warehouse_id = {$au->reserve_warehouse_id}";
            $w = $dbr->getRow($q);
//								$warestock = ($w->stock-$w->reserved)." of <b>".(int)$w->stock."</b> in ".$w->name." warehouse";
            $warestock = "<b>".(int)$w->stock."</b>";
            $warestock_color = $w->reserved?'orange':'';
    }
    $warehouses_table .= '</table>';
    $res = compact('warehouses_table','warestock_color','warestock');
    return $res;
}

function getVouchersPaidNotPaid($dbr, $where='') {
    global $dbr_spec;
/*	$all = $dbr_spec->getAll("select name
        , IFNULL((select sum((select count(*) from auction where deleted=0 and code_id=shop_promo_codes.id)*sold_for_amount) from shop_promo_codes where name=t.name  and not dont_take_sold_for_amount),0)
           -IFNULL((select sum(amount) from shop_promo_payment where code_name=t.name),0) paid
        from
         (select distinct name from shop_promo_codes where name<>''
          and now() between date_from and date_to $where) t
         order by name
        ");*/ # old way
    $all = $dbr_spec->getAll("select t.name
        , IFNULL(sum(shop_promo_codes.sold_for_amount)*count(DISTINCT auction.id)/count(*),0)
         -IFNULL(sum(shop_promo_payment.amount)*count(DISTINCT shop_promo_payment.payment_id)/count(*),0) payments
                from
                 (select distinct name from shop_promo_codes where name<>''
                  and now() between date_from and date_to $where) t
                left join shop_promo_payment on shop_promo_payment.code_name=t.name
                left join shop_promo_codes on shop_promo_codes.name=t.name and shop_promo_codes.dont_take_sold_for_amount=0 and shop_promo_codes.sold_for_amount>0
                left join auction on auction.deleted=0 and auction.code_id=shop_promo_codes.id
                group by t.name
                 order by t.name
        ");
    $names_active = $names_inactive = array();
    foreach($all as $rec) {
        if ($rec->paid>0) $names_active[$rec->name] = $rec->name;
        else $names_inactive[$rec->name] = $rec->name;
    }
    return compact('names_active','names_inactive');
}

function get_deduct_record_barcode($dbr, $deduct_id) {
                $deduct_record = $dbr->getRow("select pb.*, pbab.barcode_id
                    from parcel_barcode_article_barcode pbab
                    join vparcel_barcode pb on pb.id=pbab.parcel_barcode_id
                    where pbab.id='$deduct_id' AND `pbab`.`deleted` = 0");
    return $deduct_record;
}

function get_deduct_record_article($dbr, $deduct_id, $article_id) {
        $deduct_record = $dbr->getRow("select pba.parcel_barcode_id id
                    , t.value  as article_name
                    , pba.article_id
                    , sum(quantity) quantity
                    , CONCAT(w.ware_char,ware_loc.hall,'-',ware_loc.row,'-',ware_loc.bay,'-',ware_loc.level) location
                    , CONCAT(w.ware_char,warehouse_halls.title_id,'-',warehouse_cells.row,'-',warehouse_cells.bay,'-',warehouse_cells.level) location_new
                    , 'pba' as tablename
                    , CONCAT(tp.code,'/',lpad(pb.id,10,0)) parcel_barcode
                    , '' barcode
                    , pb.ware_loc_id
                    , pb.warehouse_cell_id
                    from parcel_barcode_article pba
                    join parcel_barcode pb on pb.id=pba.parcel_barcode_id
                    join tn_packets tp on tp.id=pb.tn_packet_id
                    left join ware_loc on ware_loc.id=pb.ware_loc_id
                    left join warehouse_cells on warehouse_cells.id=pb.warehouse_cell_id
                    left join warehouse_halls on warehouse_halls.id=warehouse_cells.hall_id
                    left join warehouse w on ware_loc.warehouse_id=w.warehouse_id
                    join translation t on table_name='article' and field_name='name' and language='german' and t.id=pba.article_id
                    where pba.parcel_barcode_id=$deduct_id and pba.article_id=$article_id");
    return $deduct_record;
}

/**
 * Get routes array
 * @return array
 */
function get_routes_array() {
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $routes_array = $dbr->getAssoc("select route.id, route.name from route
    where deleted=0 order by route.name, route.start_date, route.start_time");

    $day = 0;
    foreach($routes_array as $rid=>$rname) {
        if ($routes_array[$rid]==$oldname) {
            $oldname = $rname;
            if (!$day) {
                $day++;
                $routes_array[$oldid] .= " day ".$day;
            }
            $day++;
            $routes_array[$rid] .= " day ".$day;
        } else {
            $day = 0;
            $oldname = $rname;
        }
        $oldid = $rid;
    }
    return $routes_array;
}

/**
 * Get routes array not closed
 * @return array
 */
function get_routes_array_not_closed(){
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $routes_array = $dbr->getAssoc("select route.id, route.name from route
    where deleted=0 and closed=0 order by route.name, route.start_date, route.start_time");

    $day = 0;
    foreach($routes_array as $rid=>$rname) {
        if ($routes_array[$rid]==$oldname) {
            $oldname = $rname;
            if (!$day) {
                $day++;
                $routes_array[$oldid] .= " day ".$day;
            }
            $day++;
            $routes_array[$rid] .= " day ".$day;
        } else {
            $day = 0;
            $oldname = $rname;
        }
        $oldid = $rid;
    }
    return $routes_array;
}

function get_countries_array($dbr){

    $countries_array = $dbr->getAssoc("select name as id,name from country order by ordering");
    return $countries_array;

}

function update_version($table_name, $fld, $edit_id, $lang = '') {
    global $db, $dbr;

    $where = '';
    if ($lang) {
        $where = " AND `language` = '$lang' ";
    }

    $version_id = $dbr->getOne("SELECT `iid` FROM `versions` WHERE `table_name` = '$table_name'
                AND `field_name` = '$fld' AND `id` = $edit_id
                $where");

    if ($version_id) {
        $query = "UPDATE `versions` SET `version` = `version` + 1 WHERE `table_name` = '$table_name'
                AND `field_name` = '$fld' AND `id` = $edit_id
                $where";
    }
    else {
        $fields = ['`version`', '`table_name`', '`field_name`', '`id`'];
        $values = ["'1'", "'$table_name'", "'$fld'", "'$edit_id'"];

        if ($lang) {
            $fields[] = "`language`";
            $values[] = "'$lang'";
        }

        $fields = implode(', ', $fields);
        $values = implode(', ', $values);

        $query = "REPLACE INTO `versions` ($fields) VALUES ($values)";
    }

    $db->query($query);
    cacheClearFast('versions()', 0);
}

    function getRedirects($shop_id = 0) {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        $function = 'getRedirects()';
        $chached_ret = cacheGet($function, $shop_id, '');

        if ($chached_ret) {
            return $chached_ret;
        }

        $circle2step = $dbr->getOne("SELECT GROUP_CONCAT(LEAST(`r1`.`id`, `r2`.`id`))
            FROM `redirect` `r1`
            JOIN `redirect` `r2` ON `r1`.`src_url` = `r2`.`dest_url`
                AND `r2`.`src_url` = `r1`.`dest_url`
            WHERE `r1`.`src_url` != `r1`.`dest_url`
            ");

        $where = '';
        if ($circle2step)
        {
            $where = " AND `id` NOT IN ($circle2step) ";
        }

        $query = "SELECT `src_url`, `dest_url`
            FROM `redirect`
            WHERE NOT `disabled`
                AND `dest_url` != ''
                AND `dest_url` != `src_url`
                AND `dest_url` NOT LIKE '%/.html'
                AND `shop_id` = '" . $shop_id . "'
                $where

            UNION

            SELECT `req_url`, `dest_url`
            FROM `redirect`
            WHERE NOT `disabled`
                AND `dest_url` != ''
                AND `dest_url` != `src_url`
                AND `dest_url` NOT LIKE '%/.html'
                AND `shop_id` = '" . $shop_id . "'
                $where
             ";

        $redirects = $dbr->getAssoc($query);
        cacheSet($function, $shop_id, '', $redirects);
        return $redirects;
    }

    function addRedirect($src_url, $req_url, $shop_id = 0, $clear = false) {
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);

        $src_url = mysql_real_escape_string($src_url);
        $req_url = mysql_real_escape_string($req_url);

        $db->query("INSERT IGNORE INTO `redirect` (`src_url`, `req_url`, `shop_id`)
                        VALUES('$src_url', '$req_url', " . ($shop_id ? (int)$shop_id : 'NULL') . ")");

        if ($clear)
        {
            cacheClearFast('getRedirects()', $shop_id);
        }
    }


// Parcels class test fuctions (temporarily, will be moved to /lib/Parcel.php) ALEXJJ

function get_barcode_parcel_data($parcel_barcode_id){
    global $dbr;
    $parcel = $dbr->getRow("select pb.id barcode_id, pb.*
            from vparcel_barcode pb
        where pb.id = ".$parcel_barcode_id);
    if (PEAR::isError($parcel)) {aprint_r($parcel); die();}
    else return $parcel;
}
function parcel_contains_barcodes($parcel_barcode_id){
    global $dbr;
    $sql_query = "SELECT barcode_id FROM parcel_barcode_article_barcode WHERE
                    parcel_barcode_id=".$parcel_barcode_id." AND `deleted` = 0 LIMIT 1
                UNION ALL SELECT article_id FROM parcel_barcode_article WHERE
                    parcel_barcode_id=".$parcel_barcode_id." GROUP BY parcel_barcode_id HAVING SUM(quantity)<>0 LIMIT 1;";

    $parcel_contains_barcodes = $dbr->getAll($sql_query);

    if (PEAR::isError($parcel_contains_barcodes)) {aprint_r($parcel_contains_barcodes); die();}
    else return $parcel_contains_barcodes;
}

function get_packed_quantity($dbr, $article_id, $warehouse_id) {
    $res = $dbr->getOne("select sum(quantity)
            from parcel_barcode_article pba
            left join vparcel_barcode bom on bom.id=pba.parcel_barcode_id
            where pba.article_id=$article_id and bom.warehouse_id=$warehouse_id");
    return $res;
}

function hex2rgb($hex) {
   $hex = str_replace("#", "", $hex);

   if(strlen($hex) == 3) {
      $r = hexdec(substr($hex,0,1).substr($hex,0,1));
      $g = hexdec(substr($hex,1,1).substr($hex,1,1));
      $b = hexdec(substr($hex,2,1).substr($hex,2,1));
   } else {
      $r = hexdec(substr($hex,0,2));
      $g = hexdec(substr($hex,2,2));
      $b = hexdec(substr($hex,4,2));
   }
   $rgb = array($r, $g, $b);
   //return implode(",", $rgb); // returns the rgb values separated by commas
   return $rgb; // returns an array with the rgb values
}

function get_article_picture_URL($article_id, $width = 1024) {
    global $db, $dbr;

    $picture_id = $dbr->getOne("SELECT MAX(`doc_id`) FROM `article_doc` WHERE `article_id` = '$article_id' AND `type` = 'pic'");

    require_once __DIR__ . '/plugins/function.imageurl.php';
    global $smarty;
    return smarty_function_imageurl([
        'src' => 'article',
        'picid' => $picture_id,
        'nochange' => 1,
        'x' => $width], $smarty);
}

function addToStock($db, $dbr,$loggedUser, $siteURL, $order_article_id,$qnt_delivered, $warehouse_id, $ware_la_id = 0)
{
    $out = new stdClass;

    global $smarty;
    require_once 'lib/Article.php';
    $timediff = $loggedUser->get('timezone');
    $op_article = op_Order::getArticle($db, $dbr, $order_article_id);
    $add_article = !$op_article->add_to_warehouse;
    $out->res3 = $add_article;
    if (!$add_article) {
        $q = "SELECT
            a.barcode_type a_type,
            w.barcode_type w_type
        FROM op_article opa
            LEFT JOIN article a ON a.article_id = opa.article_id and a.admin_id = 0
            LEFT JOIN warehouse w ON w.warehouse_id = opa.warehouse_id
        WHERE opa.id = ".$order_article_id;
        $r = $dbr->getRow($q);
        if ($r->a_type == $r->w_type && $r->a_type == 'A') {
            $out->res4 = (int)$loggedUser->get('abilityRemove_fromStock');
            if (!$out->res4) $out->error = 'You are not allowed to change stock, please contact Technology department';
        }
    }
    if (!isset($out->error)){
        $articleObj = new Article($db, $dbr, $op_article->article_id);
        $warehouseObj = new Warehouse($db, $dbr, $warehouse_id);
        $warehouses = $articleObj->getWarehouses();
        foreach($warehouses as $r) {
            if ($r->warehouse_id==$warehouse_id)
                $warehouse = $r;
        }

        $companies = op_Order::listCompaniesArray($db, $dbr, NULL, '');
        $company_id = $articleObj->get('company_id');
        $sup_name = isset($companies[$company_id]) ? $companies[$company_id] : '';

        op_Order::updateArticle($db, $dbr, $order_article_id, $op_article->op_order_id,
                $op_article->article_id,
                $op_article->container_id,
                $op_article->qnt_ordered,
                $qnt_delivered,
                $op_article->volume,
                $op_article->purchase_price,
                !$op_article->add_to_warehouse
                , ServerTolocal(date("Y-m-d H:i:s"), $timediff)
                , $loggedUser->get('username')
                , $warehouse_id
                , $ware_la_id
        );
        $op_article = op_Order::getArticle($db, $dbr, $order_article_id);
        $out->res = $op_article->add_to_stock_text;
        $log = ArticleHistory::listByOrder($db, $dbr, $op_article->op_order_id);
        foreach ($log as $lid=>$dummy)
            $log[$lid]->date = ServerTolocal($log[$lid]->date, $timediff);
        $out->log=$log;
        $smarty->assign('log',$log);
        $log_table = $smarty->fetch('op_order_log.tpl');
        $old_sp = $warehouse->available;
        file_put_contents("lastAJAX1.txt", $add_article);
        if (/*($warehouse->available) < 0
        && */$add_article) {
            $qrystr = "select GROUP_CONCAT(CONCAT('<a href=\"{$siteURL}auction.php?number=',IFNULL(mau.auction_number, au.auction_number),'&txnid=',IFNULL(mau.txnid, au.txnid),'\">',IFNULL(mau.auction_number, au.auction_number),'/',IFNULL(mau.txnid, au.txnid),'</a>') SEPARATOR '<br>') auctions,
                u.email from orders o
                join auction au on o.auction_number=au.auction_number and o.txnid=au.txnid
                left join auction mau on au.main_auction_number=mau.auction_number and au.main_txnid=mau.txnid
                join users u on u.username=IFNULL(mau.shipping_username, au.shipping_username)
                where o.sent=0 and o.article_id='{$op_article->article_id}'
                and IFNULL(mau.deleted, au.deleted)=0
                and IFNULL(mau.username, au.username)
                    in (select username from seller_information where defshcountry='{$warehouse->country_code}')
                and o.reserve_warehouse_id
                    in (select warehouse_id from warehouse where country_code='{$warehouse->country_code}' and unassigned)
                group by IFNULL(mau.shipping_username, au.shipping_username)";
            file_put_contents("lastAJAX1.txt", $qrystr);
            $emails = $dbr->getAssoc($qrystr);
            foreach($emails as $auctions=>$email_invoice) {
                $ret = $warehouse;
                $ret->username = Config::get($db, $dbr, 'aatokenSeller');
                $ret->article_name = $articleObj->get('title');
                $ret->article_id = $op_article->article_id;
                $ret->sup_name = $sup_name;
                $ret->pcs = $qnt_delivered;
                $ret->email_invoice = $email_invoice;
                $ret->from = $loggedUser->get('email');
                $ret->from_name = $loggedUser->get('name');
                $ret->auctions = $auctions;
                $ret->auction_number = $op_article->op_order_id;
                $ret->txnid = -1;
                $ret->attachments = 'html';
                $vars = $ret;
                standardEmail($db, $dbr, $ret, 'add_stock_email');
            }
            $qrystr = "select count(*)
                from saved_auctions sa
                join saved_params sp_offer on sp_offer.par_key='offer_id' and sp_offer.saved_id=sa.id
                JOIN offer_group og  on (sp_offer.par_value*1)=og.offer_id and not og.additional
                join article_list al ON al.group_id = og.offer_group_id and not base_group_id
                and not al.inactive
                where sa.inactive=0 and al.article_id='{$op_article->article_id}'";
            $active_sa = $dbr->getOne($qrystr);

            if (!$active_sa) {
                $ret = $warehouse;
                $ret->username = Config::get($db, $dbr, 'aatokenSeller');
                $ret->article_name = $articleObj->get('title');
                $ret->article_id = $op_article->article_id;
                $ret->pieces = $articleObj->getPieces($warehouse->warehouse_id);
                $ret->reserved = $articleObj->getReserved($warehouse->warehouse_id);
                $ret->available = $ret->pieces - $ret->reserved;
                $ret->sup_name = $sup_name;
                $ret->purchase_price = $op_article->purchase_price;
                $ret->article_description = $articleObj->get('description');
                $ret->picture_URL = $siteURL . get_article_picture_URL($op_article->article_id, 1024);
                $ret->attachments = array();
                $ret->attachments[] = 'html';
                $att = new stdClass;
                $att->data = file_get_contents($ret->picture_URL);
                $att->name = 'image.jpg';
                $ret->attachments[] = $att;
                $ret->pcs = $qnt_delivered;
                //$ret->email_invoice = $dbr->getOne("select group_concat(email) from users where deleted=0 and get_article_arrived_no_active_sa");
                // CcqTjHUp/2378-justyna-system-mails-add-option-only-if-user-is-product-manager-or-product-assistant-of-this-article
                $ret->email_invoice = $dbr->getOne("SELECT GROUP_CONCAT(email) FROM (
                SELECT users.email from users where deleted=0 and get_article_arrived_no_active_sa AND NOT get_article_arrived_no_active_sa_if_manage
                UNION
                SELECT users.email FROM users 
                JOIN employee ON users.username = employee.username
                JOIN op_company_emp oce ON oce.emp_id = employee.id 
                WHERE oce.company_id = ".$company_id." AND deleted=0 AND get_article_arrived_no_active_sa AND get_article_arrived_no_active_sa_if_manage) t");
                $ret->from = $loggedUser->get('email');
                $ret->from_name = $loggedUser->get('name');
                $ret->auction_number = $op_article->op_order_id;
                $ret->txnid = -1;
                $ret->datetime = ServerTolocal(date("Y-m-d H:i:s"), $timediff);

                $vars = $ret;
                $res1 = standardEmail($db, $dbr, $ret, 'article_arrived_no_active_sa');
            }
        }
    }
    $out->vars=$vars;
    $out->res1=$res1;

    return $out;
}

/**
  * Return the datetime and person who did the last change
  *
  * @param $dbr db object
  * @param $table string - table name
  * @param $field string - field name
  * @param $id int - the table primary key
  *
  * @return string 'on DATETIME by PERSON'
  */
 function getLastUpdate($dbr, $table, $field, $id) {
    $qrystr1 = "select CONCAT('on ', Updated, ' by ', IFNULL(users.name, total_log.username))
                from total_log
                left join users on users.system_username=total_log.username
                where table_name='$table' and Field_name='$field'
                and TableID=$id and new_value is not null
                order by Updated desc limit 1";
    $res = $dbr->getOne($qrystr1);
    return $res;
}

 /**
  * Process the sort ordering and filter parameters
  *
  * @param $db write DB
  * @param $dbr read DB
  * @param $page string - page name, the script filename as default
  * @param $defsortOrder string - default field name for ordering
  * @param $defdirection signed int, 1 or -1 - default ordering direction
  *
  * @return int Number of correct barcodes (1 or 0)
  */
function user_sort_order($db, $dbr, $page, $defsortOrder, $defdirection) {
    global $smarty, $loggedUser;
    $uri = (string)$_SERVER['SCRIPT_NAME']."?".(string)$_SERVER['QUERY_STRING'];
    if (!strlen($page)) $page=basename($_SERVER['SCRIPT_NAME']);
    $def_row = $dbr->getRow("SELECT * FROM user_sort_order WHERE page='$page' AND username='".$loggedUser->get('username')."'");
    $defsortOrder = $def_row->sortorder;
    $defdirection = $def_row->direction;
    if (!strlen($def_row->sortorder)) $def_row->sortorder = $defsortOrder;
    $sort = requestVar('order', $def_row->sortorder);
    if (!strlen($def_row->direction)) $def_row->direction = $defdirection;
    $direction = requestVar('dir', $def_row->direction);
    $db->query("REPLACE INTO user_sort_order SET page='$page', sortorder='".$sort."', direction='".$direction."', username='".$loggedUser->get('username')."'");
    if (!$direction) $direction=1;
    $smarty->assign('uri', preg_replace('/&order=.+/', '', $uri));
    $smarty->assign('dir'.$sort, -$direction);
}

/**
 * Brute hack for local docker - geoip doesn't installed
 * @todo intall library on docker
 */
if (APPLICATION_ENV === 'docker' || APPLICATION_ENV === 'local') {
    if ( !function_exists('geoip_record_by_name'))
    {
        function geoip_record_by_name($hostname)
        {
            return [
                'continent_code' => 'xx',
                'country_code' => 'xx',
                'country_code3' => 'xxx',
                'country_name' => 'Undefined',
                'region' => 'Undefined',
                'city' => 'Undefined',
                'postal_code' => '00000',
                'latitude' => 0,
                'longitude' => 0,
                'dma_code' => '',
                'area_code' => '',
            ];
        }
    }

    if ( !function_exists('geoip_country_code_by_name'))
    {
        function geoip_country_code_by_name($hostname)
        {
            return 'xx';
        }
    }
}

function checkEmailPerson($email, $parent_email, $field) {
    global $dbr;

    $email = str_replace(';', ',', $email);
    $email = explode(',', $email);

    $parent_email = mysql_real_escape_string($parent_email);

    foreach ($email as $key => $_email) {
        $email[$key] = trim($email[$key]);
        $email[$key] = filter_var($email[$key], FILTER_SANITIZE_EMAIL);

        if ( ! $email[$key]) {
            unset($email[$key]);
            continue;
        }
        else if ( !filter_var($email[$key], FILTER_VALIDATE_EMAIL)) {
            unset($email[$key]);
            continue;
        }

//        $_email = mysql_real_escape_string($email[$key]);
//
//        $query1 = "SELECT `$field` FROM `customer` WHERE `email` != '$parent_email' AND `$field` LIKE '%$_email%'";
//        $query2 = "SELECT `$field` FROM `customer_auction` WHERE `email` != '$parent_email' AND `$field` LIKE '%$_email%'";
//
//        if ($dbr->getOne($query1) || $dbr->getOne($query2)) {
//            unset($email[$key]);
//            continue;
//        }
    }

    return implode(', ', array_unique($email));
}

function validateZIP($zip, $country) {
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $query = "SELECT postal_preg, postal_placeholder
                    FROM country WHERE `code` = '$country'";
    $rule = $dbr->getRow($query);

    if ( ! $rule || ! $rule->postal_preg) {
        return false;
    }

    if (preg_match("#^{$rule->postal_preg}$#iu", trim($zip))) {
        return false;
    }

    return $rule->postal_placeholder;
}

/**
 * Clear main page for shop
 *
 * @param int $shop_id
 * @return boolean
 */
function shop_purge_main_page($shop_id) {
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $shop_id = (int)$shop_id;
    $shop = $dbr->getRow("SELECT `ssl`, `url` FROM `shop` WHERE `id` = $shop_id AND NOT inactive");
    if ( ! $shop) {
        return false;
    }

    add_url_to_spider($shop_id, '/');
    add_url_to_spider($shop_id, '/_shop_header_banner.php');
}

/**
 * Clear content page for shop *
 *
 * @param int $shop_id
 * @param int $content_id
 * @return boolean
 */
function shop_purge_content_page($shop_id, $content_id) {
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $shop_id = (int)$shop_id;
    $content_id = (int)$content_id;

    $shop = $dbr->getRow("SELECT `ssl`, `url` FROM `shop` WHERE `id` = $shop_id AND NOT inactive");
    if ( ! $shop) {
        return false;
    }

    foreach (mulang_fields_Get(['alias'], 'shop_content', $content_id) as $fields) {
        foreach ($fields as $_alias) {
            if ($_alias->value) {
                add_url_to_spider($shop_id, "/content/{$_alias->value}/");
            }
        }
    }
}

/**
 * Get list of refunds.
 * All next params are filter variables, optional.
 * @param string|null $username list of sellers comma separated (f.e. "'Beliani', 'Beliani AT'")
 * @param string|null $fromDateInv invoice date in YYYY-MM-DD
 * @param string|null $toDateInv invoice date in YYYY-MM-DD
 * @param int|null $sellerSourceId
 * @param int|null $supplier identifier
 * @param int|null $responsible user identifier
 * @param int|null $rassistant user identifier - responsible assistant
 * @param string|null $fromDatePay date in YYYY-MM-DD
 * @param string|null $toDatePay date in YYYY-MM-DD
 * @param string|null $fromDateShip date in YYYY-MM-DD
 * @param string|null $toDateShip date in YYYY-MM-DD
 * @param int[]|null $problemId
 * @return array
 * @todo join only if table really needed
 */
function getRefundsList($username = null, $fromDateInv = null, $toDateInv = null, $sellerSourceId = null, $supplier = null, $responsible = null, $rassistant = null, $fromDatePay = null, $toDatePay = null, $fromDateShip = null, $toDateShip = null, $problemId = null)
{
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $q = "SELECT
            rss.invoice_date,
            rss.rma_spec_sol_id,
            IF((select value from config_api where siteid=au.siteid and par_id=7)='EUR',
            rss.amount,
            rss.amount*
                IFNULL((select `value` from rate where curr_code=
                    (select value from config_api where siteid=au.siteid and par_id=7)
                    and `date`<=rss.invoice_date order by `date` desc limit 1),1)
                    /
                    (select `value` from rate where curr_code='EUR'
                    and `date`<=rss.invoice_date order by `date` desc limit 1)) amount,
            rs.article_id,
            (SELECT value
                FROM translation
                WHERE table_name = 'article'
                AND field_name = 'name'
                AND language = 'german'
                AND id = a.article_id) name,
            r.rma_id,
            r.auction_number,
            r.txnid,
            (
                SELECT payment_date
                FROM payment p
                WHERE au.auction_number = p.auction_number
                ORDER BY p.payment_date DESC
                LIMIT 1
            ) payed_date,
            p.name problem_name
        FROM `rma_spec_solutions` rss
        JOIN rma_spec rs on rss.rma_spec_id=rs.rma_spec_id
        JOIN rma r on rs.rma_id=r.rma_id
        join auction au on au.auction_number=r.auction_number and au.txnid=r.txnid
        JOIN article a on rs.article_id=a.article_id and not a.admin_id
        LEFT JOIN rma_problem p ON p.problem_id=rs.problem_id
        WHERE
            rss.`solution_id` = 7";

    if (isset($sellerSourceId)) {
        $q .= "
            AND au.source_seller_id in (" . implode(', ', $sellerSourceId) . ")";
    }
    if (isset($fromDateInv)) {
        $q .= "
            AND rss.`invoice_date` >= date( '$fromDateInv' )";
    }
    if (isset($toDateInv)) {
        $q .= "
            AND rss.`invoice_date` <= date( '$toDateInv')";
    }
    if (isset($fromDateShip)) {
        $q .= "
            AND fget_Adelivery_date_real(au.auction_number, au.txnid) >= date('$fromDateShip' )";
    }
    if (isset($toDateShip)) {
        $q .= "
            AND fget_Adelivery_date_real(au.auction_number, au.txnid) <= date('$toDateShip')";
    }
    if (isset($username)) {
        $q .= "
            AND au.username in ('" . implode("', '", $username) . "')";
    }
    if (isset($supplier)) {
        $q .= "
            AND a.company_id = " . $supplier;
    }
    if (isset($responsible)) {
        $q .= "
            AND (
                SELECT COUNT(*)
                FROM op_company_emp oer
                WHERE
                    oer.company_id = a.company_id
                    AND oer.type = 'purch'
                    AND oer.emp_id = " . $responsible . "
            ) > 0";
    }
    if (isset($rassistant)) {
        $q .= "
            AND (
                SELECT COUNT(*)
                FROM op_company_emp oera
                WHERE
                    oera.company_id = a.company_id
                    AND oera.type = 'assist'
                    AND oera.emp_id = " . $rassistant . "
            ) > 0";
    }
    if (isset($problemId)) {
        $q .= "
            AND rs.problem_id IN (" . implode(', ', $problemId) . ")";
    }
    $q .= "
        HAVING 1";
    if (isset($fromDatePay)) {
        $q .= "
            AND payed_date >= date('$fromDatePay' )";
    }
    if (isset($toDatePay)) {
        $q .= "
            AND payed_date <= date('$toDatePay')";
    }
 
    $refunds = $dbr->getAll($q);
    if (PEAR::isError($refunds)) {
        echo '<pre>'; print_r($refunds);
    }
    return $refunds;
}

/**
 * Create path to files from md5 hash
 *
 * @param string $filename file md5 hash
 * @return string | boolean full path to file
 */
function get_file_path($hash) {
    $filename = FILES_PATH . substr($hash, 0, 2) . '/' . $hash;
    return is_file($filename) ? file_get_contents($filename) : false;
}

/**
 * @param string $filename file md5 hash
 * @return full path to file
 */
function set_file_path($filename) {

    $dirname = FILES_PATH . substr($filename, 0, 2) . '/';
    if ( !is_dir($dirname)) {
        mkdir($dirname, 0755, true);
    }

    return $dirname . $filename;
}

/**
 * Sent message when removing customer from route
 * If parameters `removing_from_route_email` / `removing_from_route_sms` setted
 *
 * @param int $auction_id
 */
function send_email_when_removing_from_route($auction_id) {
    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $au = $dbr->getRow("select * from auction where id=$auction_id");
    $ret = new Auction($db, $dbr, $au->auction_number, $au->txnid);

    if ($au->removing_from_route_email) {
        standardEmail($db, $dbr, $ret, 'removing_from_route', 'email');
    }

    if ($au->removing_from_route_sms) {
        $ret->data->sendsms = 1;
        standardEmail($db, $dbr, $ret, 'removing_from_route', 'sms');
    }
}
/**
 * Send test email to user with this template layout
 *
 * @param stdClass $user - user object
 * @param $template_name - template name
 */
function testSendingEmailTemplate($user, $template_name){

    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    if (!strlen($template_name)) return;
    global $lang;
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

    $username4template = $user->username;
    $locallang = $lang;
    $sellerInfo4template = new SellerInfo($db, $dbr, $username4template, $locallang);
    $template_content = $sellerInfo4template->getTemplate($template_name, $locallang);
    list ($subject, $template_content) = explode("\n", $template_content, 2);
    $template_obj = $dbr->getRow("select * from template_names where name='$template_name'");
    $ishtml = (int)$template_obj->html;
    $header_footer = (int)$template_obj->header_footer;
    $issystem = (int)$template_name->system;

    $username = $user->username;
    if ($username) {
        $username = mysql_real_escape_string($username);
        $seller_id = (int)$dbr->getOne("SELECT `id` FROM `seller_information` WHERE `username` LIKE '$username' AND `isActive` = 1");
    }

    $seller_id = $seller_id ? $seller_id : 55;

    require_once 'mail_util.php';

    if($ishtml && ! $issystem && $header_footer) {

        $layouts = ['header', 'footer'];
        $layouts = mulang_files_Get($layouts, 'email_template_layout', $seller_id);
        $header = isset($layouts['header']) ? $layouts['header'] : [];
        $header = isset($header[$locallang]->value) ? $header[$locallang]->value : (isset($header['english']->value) ? $header['english']->value : '');
        $footer = isset($layouts['footer']) ? $layouts['footer'] : [];
        $footer = isset($footer[$locallang]->value) ? $footer[$locallang]->value : (isset($footer['english']->value) ? $footer['english']->value : '');

        $message = $header . $template_content . $footer;

        $message = '<html>
<head>
<meta	http-equiv="Content-Type"	content="charset=utf-8" />
</head>
<body>
' . ($message) . '
</body>
</html>
';

    }else{
        $message = $template_content;
    }

    $email = $user->email_invoice;
    $from = 'system@businesscontrolltd.com';
    $from_name = 'test_email';
    //$subject = 'Email template test sending';
    $attachments = array();
    if($ishtml) {
        $attachments[] = 'html';
    }

    global $def_smtp;
    if ((int)$def_smtp) $defsmtp = $def_smtp;
    else $defsmtp = array(0 => $sellerInfo4template->getDefSMTP());

    $res = sendSMTP($db, $dbr, $email, $subject, $message, $attachments, $from, $from_name,null,null,$template_name,null,$defsmtp);

    return $res;

}
/**
 * Replace relative image pathes with absolute if cdn used
 * @param string $content
 * @param string $cdn
 * @return string
 */
function processContent($content, $cdn) {
    $patterns = ['/\"\/images\/cache\//'];
    $replacements = ['"' . $cdn . '/images/cache/'];
    return preg_replace($patterns, $replacements, $content);
}
/**
 * Send test sms to user with this template layout
 *
 * @param stdClass $user - user object
 * @param $template_name - template name
 * @param $phone_number - telephone number
 */
function testSendingPhoneTemplate($user, $template_name, $phone_number){

    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    if (!strlen($template_name)) return;
    global $lang;
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

    $username4template = $user->username;
    $locallang = $lang;
    $sellerInfo4template = new SellerInfo($db, $dbr, $username4template, $locallang);
    $sms = $sellerInfo4template->getSMS($template_name, $locallang);

    if (substr($phone_number, 0, 1) == '+') {
        $cleared_number = str_replace('+', '00', $phone_number);
    } else {
        $cleared_number = $phone_number;
    }

    if (Config::get($db, $dbr, 'smsemail_cut0') == 1) {
        $sms2number = ltrim($cleared_number, '0');
    } elseif (Config::get($db, $dbr, 'smsemail_cut0') == 2) {
        $sms2number = ltrim($cleared_number, '0');
        while (strpos($sms2number, '00') !== 0) $sms2number = '0' . $sms2number;
    } else {
        $sms2number =  $cleared_number;
    }
    $sms_emails = $dbr->getOne("select group_concat(email) from sms_email where inactive=0 and '$sms2number' like CONCAT(sms_email.number,'%')");

    $from = 'system@businesscontrolltd.com';
    $from_name = 'test_sms';

    global $def_smtp;
    if ((int)$def_smtp) $defsmtp = $def_smtp;
    else $defsmtp = array(0 => $sellerInfo4template->getDefSMTP());

    if (strlen($sms_emails)) {
        $res = sendSMTP($db, $dbr, $sms_emails, $sms2number,
            substr($sms, 0, Config::get($db, $dbr, 'sms_message_limit')),[], $from, $from_name,
            null, null, $template_name, null,
            $defsmtp);
        return $res;
    }

    return false;
}

/**
 *
 * @param int $shop_id
 * @param string $url
 * @return string
 */
function add_url_to_spider($shop_id, $url) {
    $spider = new \label\Spider\SpiderBuild($shop_id);
    return $spider->pushQueue($url);
}

/**
 * Function makes call to google map directions service and switches API key in case of "over query limit" error
 * Result is cached in redis.
 * @param string $start Route start address
 * @param string $finish End address
 * @param array $addresses Route waypoints
 *
 * @return mixed
 */
function gmapApiQuery($start, $finish, $addresses = []){
    $function = "gmapApiQuery('$start', '$finish', '" . json_encode($addresses) . "')";
    $result = cacheGet($function, 0, '');
    if ($result) return $result;

    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
    $n = str_replace('GMAP_API_key', '', str_replace('_default', '', \Config::get($db, $dbr, 'defgmap')));
    if (!strlen($n)) $n = 1;

    foreach ($addresses as $key => $address) {
        $address = str_replace('removed', '', $address);
        $address = trim($address);
        if($address[0] == ',') $address = trim(substr($address, 1));
        $addresses[$key] = $address;
    }

    $url_str = 'https://maps.googleapis.com/maps/api/directions/json?'
        . 'origin=' . urlencode($start)
        . '&destination=' . urlencode($finish)
        . '&waypoints=optimize:true|' . urlencode(implode('|', $addresses));
    $errors = '';

    for ($i = 1; $i <= 8; $i++) {
        $gkey = \Config::get($db, $dbr, 'GMAP_API_key' . $n);
        $url2route = $url_str . '&key=' . $gkey;

        $ch = new \Curl($url2route, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_ENCODING => '',
        ]);
        $route_json = $ch->exec();
        $route_decoded = json_decode($route_json);
        gmap_stat($url_str, $route_decoded->status);

        switch ($route_decoded->status) {
            case "OK":
                break 2;
            case "ZERO_RESULTS":    // check all addresses
            case "NOT_FOUND":       // and show just points on map without route
                $errors .= gmapCheckAddress($start, $gkey);
                $errors .= gmapCheckAddress($finish, $gkey);
                foreach ($addresses as $auction_id => $addr) {
                    $errors .=  gmapCheckAddress($addr, $gkey, $auction_id);
                }
                $route_json = false;
                break 2;
            case "OVER_QUERY_LIMIT": // switch key, no break foreach
                $n_with_error = $n;
                $n++;
                $n = ($n > 8) ? 1 : $n;
                \Config::set($db, $dbr, 'defgmap', "GMAP_API_key{$n}_default");
                $errors .= "<p>Status: {$route_decoded->status}; key #{$n_with_error}; query: <a href='$url2route'
                    target='_blank'>link</a>; message: {$route_decoded->error_message}</p>";
                sleep(1);
                break 1;
            default: // unknown error
                $errors .= "<p>Status: {$route_decoded->status}; key #$n; query: <a href='$url2route'
                    target='_blank'>link</a>; message: {$route_decoded->error_message}</p>";
                break 2;
        }
    }

    if(strlen($errors)){
        echo "<div style='margin:10px;padding:0 20px;background:pink;border:1px solid red;'>$errors</div>";
    };

    if($route_decoded->status != 'OVER_QUERY_LIMIT'){
        cacheSet($function, 0, '', $route_json);
    }

    return $route_json;
}

function gmapCheckAddress($addr, $key, $auction_id = null){
    $function = "gmapCheckAddress('$addr')";
    $result = cacheGet($function, 0, '');
    if ($result) {
        $route_json = $result;
    }
    else {
        $url_str = 'https://maps.googleapis.com/maps/api/directions/json?'
            . 'origin=Switzerland'
            . '&destination=' . urlencode($addr);
        $url2route = $url_str . '&key=' . $key;
        $ch = new \Curl($url2route, [
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_ENCODING => '',
        ]);
        $route_json = $ch->exec();
    }

    $route_decoded = json_decode($route_json);
    gmap_stat($url_str, $route_decoded->status);

    $msg = false;
    if ($route_decoded->status == 'ZERO_RESULTS' || $route_decoded->status == 'NOT_FOUND') {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        if($auction_id) $auction = $dbr->getRow("select auction_number, txnid from auction where id=$auction_id");
        $type = $auction ? "<a href='/auction.php?number={$auction->auction_number}&txnid={$auction->txnid}'>auction</a>" : "warehouse";
        $msg = "<p>Status: {$route_decoded->status}. Please check following $type address: <b>$addr</b>";
        $msg .= "<br><small>$url_str</small></p>";
    }

    if($route_decoded->status != 'OVER_QUERY_LIMIT'){
        cacheSet($function, 0, '', $route_json);
    }

    return $msg;
}

/**
 *
 * @global Object $shopCatalogue
 * @param int $shop_id
 */
function versions($shop_id = 0)
{
    static $version_already_loading;
    if ($version_already_loading)
    {
        return;
    }
    
    return false;
        
    $version_already_loading = true;
    global $shopCatalogue, $smarty;
    
    $function = 'versions()';
    $ver_array = cacheGet($function, 0, '');
    
    if ($ver_array && $ver_array['shop_doc_codes'] && $ver_array['ver_array'])
    {
        $smarty->assign('shop_doc_codes', $ver_array['shop_doc_codes']);
        $smarty->assign('versions', $ver_array['ver_array']);
        return false;
    }

    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $shop_id = $shop_id ? (int)$shop_id : (int) requestVar('shop_id', 0);
    if ($shopCatalogue && isset($shopCatalogue->_shop->id)) {
        $shop_id = $shopCatalogue->_shop->id;
    }

    $shop_doc_codes = $dbr->getAssoc("select code, doc_id from shop_doc where shop_id={$shop_id}");

    $shop_doc_codes_flip = array_flip($shop_doc_codes);
    $smarty->assign('shop_doc_codes', $shop_doc_codes);

    $ver_array = [];
    $versions = $dbr->query("select * from versions");
    while ($ver = $versions->fetchRow()) {
        $ver_array[$ver->table_name][$ver->field_name][$ver->id][$ver->language] = $ver->version;
        $ver_array[$ver->table_name][$ver->field_name][$ver->id]['alllangs'] = max($ver_array[$ver->table_name][$ver->field_name][$ver->id]);
        if ($ver->table_name == 'shop_doc' && $ver->field_name == 'data') {
            $ver_array[$ver->table_name][$ver->field_name][$shop_doc_codes_flip[$ver->id]][$ver->language] = $ver->version;
            $ver_array[$ver->table_name][$ver->field_name][$shop_doc_codes_flip[$ver->id]]['alllangs'] = max($ver_array[$ver->table_name][$ver->field_name][$shop_doc_codes_flip[$ver->id]]);
        }
        if ($ver->table_name == 'payment_method') {
            $ver_array[$ver->table_name]['allow_payment_icon'][$ver->id][$ver->language] = $ver->version;
            $ver_array[$ver->table_name]['allow_payment_icon'][$ver->id]['alllangs'] = max($ver_array[$ver->table_name]['allow_payment_icon'][$ver->id]);
        }
    }
    
    cacheSet($function, 0, '', ['ver_array' => $ver_array, 'shop_doc_codes' => $shop_doc_codes]);
    $smarty->assign('versions', $ver_array);
}

function send_skype_message($skype_uuid, $message) {
    $skype_bot_app_id = \Config::get(0, 0, 'skype_bot_app_id');
    $skype_bot_secret = \Config::get(0, 0, 'skype_bot_secret');

    if ( ! $skype_bot_app_id || ! $skype_bot_secret) {
        return false;
    }

    $curl = new \Curl(null, [
        CURLOPT_HEADER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.4 (KHTML, like Gecko) Chrome/22.0.1229.92 Safari/537.4',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_TIMEOUT => 60
    ]);

    $curl->set_url('https://login.microsoftonline.com/common/oauth2/v2.0/token');
    $curl->set_post([
        'client_id' => $skype_bot_app_id,
        'client_secret' => $skype_bot_secret,
        'grant_type' => 'client_credentials',
        'scope' => 'https://graph.microsoft.com/.default'
    ]);
    $result = $curl->exec();
    $result = json_decode($result);

    $access_token = isset($result->access_token) ? $result->access_token : '';
    if ( ! $access_token) {
        return false;
    }

    $message = json_encode([
        "message" => ['content' => $message],
    ]);

    $curl->initialize([CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$access_token}",
        'Content-Type: application/json',
        'Content-Length: ' . strlen($message),
    ]]);
    $curl->set_url("https://apis.skype.com/v2/conversations/8:{$skype_uuid}/activities");
    $curl->set_post($message, false);
    return $curl->exec();
}

function gmap_stat($query_url, $status, $auction_number = null, $txnid = null)
{
    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);

    $backtrace = debug_backtrace();
    $function = $backtrace[1]['function'];
    $referer = (!empty($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];

    $q = "INSERT INTO prologis_log.gmap_log SET url='$query_url', datetime=NOW(), status='$status', function='$function',
    auction_number='$auction_number', txnid='$txnid', referer='$referer',
    apikey = (SELECT `value` FROM prologis2.config
      WHERE CONCAT(name, '_default') = (SELECT `value` FROM prologis2.config WHERE name='defgmap')
        )";
    $db->query($q);
}

/**
 * Get all locations for A article in warehouse
 *
 * @param int $article_id
 * @param Warehouse $warehouse
 * @return int
 */
function article_get_location_A($article_id, $warehouse_id, $forklift_data = null) {
    if ( ! $article_id || ! $warehouse_id)
    {
        return [];
    }

    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $warehouse = new \Warehouse($db, $dbr, $warehouse_id);

    $vbw = 'vbarcode_warehouse';
    $bt = 'b1';
    if (\Config::get(null, null, 'use_dn')) {
        $vbw = 'barcode_dn';
        $bt = 'bw';
    }

    $colors = $dbr->getAssoc('select `digs`, `color` from `barcode_color`');

    $locations = [];
    $_locations = [];

    $query = "SELECT pbab.id
            , ifnull(b1.op_order_id, 99999999999) AS op_order_id
            , b1.ware_loc
            , CONCAT('" . $warehouse->data->ware_char . "',ware_loc.hall,'-',ware_loc.row,'-',ware_loc.bay,'-',ware_loc.level) location
            , CONCAT('" . $warehouse->data->ware_char . "',warehouse_halls.title_id,'-',warehouse_cells.row,'-',warehouse_cells.bay,'-',warehouse_cells.level) location_new
            , CONCAT(tp.code,'/',lpad(pb.id,10,0)) AS parcel_barcode
            , pbab.barcode_id
            , bw.state2filter
            , bw.reserved
            , COUNT(*) quantity
        FROM vbarcode b1
        JOIN {$vbw} AS bw ON b1.id = bw.id
        LEFT JOIN parcel_barcode_article_barcode pbab ON pbab.barcode_id=b1.id AND `pbab`.`deleted` = 0
        LEFT JOIN vparcel_barcode pb ON pb.id=pbab.parcel_barcode_id
        LEFT JOIN tn_packets tp on tp.id=pb.tn_packet_id
        LEFT JOIN ware_loc on ware_loc.id=pb.ware_loc_id and ware_loc.warehouse_id='" . $warehouse->data->warehouse_id . "'
        LEFT JOIN warehouse_cells on warehouse_cells.id=pb.warehouse_cell_id
        LEFT JOIN warehouse_halls on warehouse_halls.id=warehouse_cells.hall_id
        LEFT JOIN `barcode_state` AS `bs` ON `bs`.`code` = `bw`.`state2filter`
        WHERE {$bt}.article_id = '" . $article_id . "' AND b1.inactive=0
            AND IFNULL(`pb`.`warehouse_id`, `bw`.`last_warehouse_id`) = '" . $warehouse->data->warehouse_id . "'
            AND NOT bw.reserved
            AND `bs`.`type` = 'in'
        GROUP BY parcel_barcode
        ORDER BY quantity DESC, op_order_id ASC";

    $result = $dbr->getAll($query);

    foreach ($result as $_article) {
        $_article->location = $_article->location_new ? $_article->location_new : $_article->location;

        if ($_article->parcel_barcode && $_article->location == $_article->ware_loc)
        {
            if ( ! isset($_locations[$_article->location])) {
                $_loc = explode('-', $_article->location);

                if ($forklift_data)
                {
                    if ((int)$forklift_data->level_from > (int)$_loc[3] || (int)$forklift_data->level_to < (int)$_loc[3])
                    {
                        continue;
                    }
                }

                $_color = isset($colors[$_loc[3]]) ? $colors[$_loc[3]] : 'transparent';
                $_loc = "{$_loc[0]}-{$_loc[1]}-{$_loc[2]}-<span style='background:{$_color};display:inline-block;padding:1px 2px;'>{$_loc[3]}</span>";
                $_locations[$_article->location] = $_loc;
            }
            else {
                $_loc = $_locations[$_article->location];
            }

            $locations[$_loc][$_article->parcel_barcode] = $_article->quantity;
        }
        else if ($forklift_data && $forklift_data->take_from_la && $_article->quantity)
        {
            $locations['la'] = $_article->quantity;
        }

    }

    return $locations;
}

/**
 * Get all locations for C article in warehouse
 *
 * @param int $article_id
 * @param Warehouse $warehouse
 * @return int
 */
function article_get_location_C($article_id, $warehouse_id, $forklift_data = null) {
    if ( ! $article_id || ! $warehouse_id)
    {
        return [];
    }

    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $warehouse = new \Warehouse($db, $dbr, $warehouse_id);

    $query = "SELECT pba.parcel_barcode_id id
            , '0' AS op_order_id
            , pba.article_id
            , sum(quantity) quantity
            , CONCAT(?,ware_loc.hall,'-',ware_loc.row,'-',ware_loc.bay,'-',ware_loc.level) location
            , CONCAT(?,warehouse_halls.title_id,'-',warehouse_cells.row,'-',warehouse_cells.bay,'-',warehouse_cells.level) location_new
            , 'pba' as tablename
            , CONCAT(tp.code,'/',lpad(pb.id,10,0)) parcel_barcode
            , '' barcode
        FROM parcel_barcode_article pba
        JOIN parcel_barcode pb ON pb.id=pba.parcel_barcode_id
        JOIN parcel_barcode_object bom ON bom.barcode_id=pb.id AND bom.obj='barcodes_manual'
        JOIN parcel_barcode_manual bm ON bom.obj_id=bm.id
        join warehouse w on bm.warehouse_id=w.warehouse_id
        JOIN tn_packets tp on tp.id=pb.tn_packet_id
        LEFT JOIN ware_loc on ware_loc.id=pb.ware_loc_id and ware_loc.warehouse_id=?
        LEFT JOIN warehouse_cells on warehouse_cells.id=pb.warehouse_cell_id
        LEFT JOIN warehouse_halls on warehouse_halls.id=warehouse_cells.hall_id
        WHERE pba.article_id = ? and w.warehouse_id=?
        GROUP BY pba.parcel_barcode_id
        HAVING quantity
        ORDER BY quantity DESC";

    $articles = $dbr->getAll($query, null, [$warehouse->data->ware_char, $warehouse->data->ware_char, $warehouse->data->warehouse_id, $article_id, $warehouse->data->warehouse_id]);
    $locations = [];

    $_locations = [];
    $all_locations = 0;

    $_count = 0;

    foreach ($articles as $_article) {
        $_article->location = $_article->location_new ? $_article->location_new : $_article->location;

        if ($_article->location && $_article->parcel_barcode) {
            $all_locations += $_article->quantity;

            if ( ! isset($_locations[$_article->location])) {
                $_loc = explode('-', $_article->location);

                if ($forklift_data)
                {
                    if ((int)$forklift_data->level_from > (int)$_loc[3] || (int)$forklift_data->level_to < (int)$_loc[3])
                    {
                        continue;
                    }
                }

                $_color = $dbr->getOne("SELECT color FROM barcode_color where digs='{$_loc[3]}'");
                $_loc = "{$_loc[0]}-{$_loc[1]}-{$_loc[2]}-<span style='background:{$_color};display:inline-block;padding:1px 2px;'>{$_loc[3]}</span>";
                $_locations[$_article->location] = $_loc;
            }
            else {
                $_loc = $_locations[$_article->location];
            }

            if ( ! isset($locations[$_loc][$_article->parcel_barcode])) {
                $locations[$_loc][$_article->parcel_barcode] = 0;
            }

            $locations[$_loc][$_article->parcel_barcode] += $_article->quantity;
        }
        else if (( ! $forklift_data || $forklift_data->take_from_la) && $_article->quantity)
        {
            $all_locations += $_article->quantity;
            if ($_article->parcel_barcode)
            {
                if ( ! isset($locations['---'][$_article->parcel_barcode])) {
                    $locations['---'][$_article->parcel_barcode] = 0;
                }

                $locations['---'][$_article->parcel_barcode] += $_article->quantity;
            }
            else
            {
                $locations['la'] = $_article->quantity;
            }
        }
    }

    if ( ! $locations && ( ! $forklift_data || $forklift_data->take_from_la))
    {
        $art = new \Article($db, $dbr, $article_id, -1, 0);
        $total = $art->getPieces($warehouse->data->warehouse_id);
        $diff = $total - $all_locations;

        if ($diff > 0)
        {
            $locations['la'] = $diff;
        }
    }

    return $locations;
}

/**
 * Check next picking order
 *
 * @global type $picking_ids
 * @global type $warehouse_id
 * @global type $loggedUser
 */
function check_next_po_old($ramp_id, $prev = false) {
    global $picking_ids, $warehouse_id, $loggedUser, $picking_ids;

    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $prev = $prev ? ' DESC ' : ' ASC ';

    $all_ids = [];
    $query = "SELECT DISTINCT(`picking_order`.`id`) AS `id`
        FROM `picking_order`
        JOIN `ware_la` ON `picking_order`.`ware_la_id` = `ware_la`.`id`
        WHERE
            `picking_order`.`delivered` = 0 AND IFNULL(`picking_order`.`opened_by_username`, ?) = ?
            AND `ware_la`.`warehouse_id` = ?
            AND `picking_order`.`ware_la_id` = ?
        ORDER BY `picking_order`.`id` $prev ";

    $ids = $db->getAll($query, null,
            [$loggedUser->get('username'), $loggedUser->get('username'), $warehouse_id, $ramp_id]);
    foreach ($ids as $_id) {
        $all_ids[] = (int)$_id->id;
    }

    if ($all_ids) {

        $_picking_ids = explode('.', $picking_ids);
        $_picking_ids = array_map('intval', $_picking_ids);

        $all_ids_not_filled = [];
        foreach ($all_ids as $id) {
            if (!in_array($id, $_picking_ids) || !check_po_full($id)) {
                $all_ids_not_filled[] = $id;
            }
        }

        $query = "SELECT `picking_order`.`id`
            FROM `picking_order`
            JOIN `ware_la` ON `picking_order`.`ware_la_id` = `ware_la`.`id`
            WHERE
                `picking_order`.`delivered` = 0 AND `picking_order`.`opened_by_username` = ?
                AND `ware_la`.`warehouse_id` = ?
                AND `picking_order`.`ware_la_id` = ?
            ORDER BY `picking_order`.`id` $prev LIMIT 1";

        $active_id = (int)$db->getOne($query, null, [$loggedUser->get('username'), $warehouse_id, $ramp_id]);
        if (in_array($active_id, $all_ids)) {

            $_ids = $all_ids_not_filled ? $all_ids_not_filled : $all_ids;
            if (in_array($active_id, $_ids)) {
                for ($i = 0; $i < count($_ids); ++$i) {
                    if ($active_id == $_ids[$i]) {
                        $active_id = isset($_ids[$i + 1]) ? $_ids[$i + 1] : $_ids[0];
                        break;
                    }
                }
            } else {
                $active_id = $_ids[0];
            }
        } else {
            $active_id = $all_ids[0];
        }

        if ($all_ids) {
            $db->query("UPDATE `picking_order` SET `opened_by_username` = NULL WHERE `id` IN ( " . implode(', ', $all_ids) . ' )');
        }
        $db->execParam("UPDATE `picking_order` SET `opened_by_username` = ? WHERE `id` = ?", [$loggedUser->get('username'), $active_id]);
    }
}

/**
 * Check next picking order
 *
 * @global type $picking_ids
 * @global type $warehouse_id
 * @global type $loggedUser
 */
function check_next_po_findway($ramp_id, $prev) {
    global $picking_ids, $warehouse_id, $warehouse, $forklift_data, $loggedUser;
    global $all_halls, $hall, $cell, $steps_array;
    global $mazes, $mazes_storage, $mazes_ramps;

    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $all_ids = [];
    $query = "SELECT DISTINCT(`picking_order`.`id`) AS `id`, `picking_order`.`opened_by_username`
        FROM `picking_order`
        JOIN `ware_la` ON `picking_order`.`ware_la_id` = `ware_la`.`id`
        WHERE
            `picking_order`.`delivered` = 0 AND IFNULL(`picking_order`.`opened_by_username`, ?) = ?
            AND `ware_la`.`warehouse_id` = '" . $warehouse_id . "'
            AND `picking_order`.`ware_la_id` = '" . $ramp_id . "'
        ORDER BY `picking_order`.`id` ASC";

    $ids = $db->getAssoc($query, null, [$loggedUser->get('username'), $loggedUser->get('username')]);
    $all_ids = array_keys($ids);

    if ($all_ids) {

        $_picking_ids = explode('.', $picking_ids);
        $_picking_ids = array_map('intval', $_picking_ids);

        $all_ids_not_filled = [];
        foreach ($all_ids as $id) {
            if (!in_array($id, $_picking_ids) || !check_po_full($id)) {
                $all_ids_not_filled[] = $id;
            }
        }

        $all_ids = $all_ids_not_filled ? $all_ids_not_filled : $all_ids;

        $query = "SELECT `picking_order`.`id`, `picking_order`.`id` `v`
            FROM `picking_order`
            JOIN `ware_la` ON `picking_order`.`ware_la_id` = `ware_la`.`id`
            WHERE
                `picking_order`.`delivered` = 0 AND `picking_order`.`opened_by_username` = ?
                AND `ware_la`.`warehouse_id` = ?
                AND `picking_order`.`ware_la_id` = ?
            ORDER BY `picking_order`.`id` ASC";

        $active_ids = $db->getAssoc($query, null, [$loggedUser->get('username'), $warehouse_id, $ramp_id]);

        foreach ($all_ids as $_key => $_id)
        {
            if (isset($active_ids[$_id]))
            {
                unset($all_ids[$_key]);
            }
        }

        $all_ids_temp = $all_ids;
        if ($current_picking_ids)
        {
            $all_ids_temp = array_diff($all_ids, $current_picking_ids);
        }

        if ( ! $all_ids_temp)
        {
            $all_ids_temp = $all_ids;
        }

        if ($all_ids) {
//            $db->query("UPDATE `picking_order` SET `opened_by_username` = NULL WHERE `id` IN ( " . implode(', ', $all_ids) . ' )');
        }

        $query = "SELECT *
            FROM `picking_order`
            WHERE `delivered` = 0
                AND `opened_by_username` IS NULL
                AND `ware_la_id` = '" . $ramp_id . "'
                AND `id` IN (" . implode(', ', $all_ids_temp) . ")
            ORDER BY `picking_order`.`opened_by_username` DESC, `picking_order`.`id` ASC";

        $picking_orders = $db->getAll($query);

        $articles = [];
        foreach ($picking_orders as $po)
        {
            if (in_array($po->id, $current_picking_ids))
            {
                break;
            }

            $_articles = [];
            if ($po->shipping_username)
            {
                $query = "SELECT `article`.`article_id`, `article`.`barcode_type`, `article`.`items_per_shipping_unit`,
                        `orders`.`id`, `orders`.`auction_number`, `orders`.`txnid`, `orders`.`picking_order_id`,
                        SUM(`orders`.`quantity`) AS `quantity`,
                        `article`.`items_per_shipping_unit`, `picking_order`.`shipping_username`, `article`.`group_id`,
                        `picking_order`.`wwo_id`, `route`.`name` AS `route_name`
                    FROM `article`
                    JOIN `orders` ON `article`.`article_id` = `orders`.`article_id` AND `orders`.`manual` = 0
                    JOIN `auction` ON `auction`.`auction_number` = `orders`.`auction_number` AND `auction`.`txnid` = `orders`.`txnid`
                    LEFT JOIN `auction` AS `main_auction` ON `auction`.`main_auction_number` = `main_auction`.`auction_number`
                        AND `auction`.`txnid` = `main_auction`.`txnid`
                    LEFT JOIN `route` ON IFNULL(`main_auction`.`route_id`, `auction`.`route_id`) = `route`.`id`
                    JOIN `picking_order` ON `picking_order`.`id` = `orders`.`picking_order_id`
                    WHERE IFNULL(`main_auction`.`shipping_username`, `auction`.`shipping_username`) = '" . mysql_real_escape_string($po->shipping_username) . "'
                        AND `article`.`article_id` = '" . (int)$po->article_id . "' AND `article`.`admin_id` = 0
                        AND `orders`.`picking_order_id` = '" . (int)$po->id . "'
                    GROUP BY `article`.`article_id`";

                $_articles[] = $dbr->getRow($query);
            }
            else
            {
                $query = "SELECT `article`.`article_id`, `article`.`barcode_type`, `article`.`items_per_shipping_unit`,
                        `wwo_article`.`wwo_id`, `wwo_article`.`picking_order_id`,
                        SUM(`wwo_article`.`qnt`) AS `quantity`,
                        `article`.`items_per_shipping_unit`, CONCAT('WWO #', `picking_order`.`wwo_id`) AS `shipping_username`, `article`.`group_id`,
                        `picking_order`.`wwo_id`, '' AS `route_name`
                    FROM `wwo_article`
                    JOIN `article` ON `wwo_article`.`article_id` = `article`.`article_id`
                    JOIN `picking_order` ON `picking_order`.`id` = `wwo_article`.`picking_order_id`
                    WHERE `wwo_article`.`wwo_id` = '" . (int)$po->wwo_id . "'
                        AND `article`.`article_id` = '" . (int)$po->article_id . "' AND `article`.`admin_id` = 0
                        AND `wwo_article`.`picking_order_id` = '" . (int)$po->id . "'
                        AND `wwo_article`.`taken` = '0'
                    GROUP BY `article`.`article_id`";

                $_articles[] = $dbr->getRow($query);
            }

            if ( ! $_articles || ! $_articles[0])
            {
                continue;
            }

            if ($_articles[0]->group_id)
            {
                $associated = $dbr->getAssoc("SELECT `article`.`article_id`, `article`.`article_id` AS `v`
                    FROM `article`
                    JOIN `picking_order` ON `picking_order`.`article_id` = `article`.`article_id`
                    WHERE `article`.`admin_id` = 0
                        AND `picking_order`.`delivered` = '0'
                        AND `picking_order`.`ware_la_id` = '" . $ramp_id . "'
                        AND `picking_order`.`shipping_username` = '" . mysql_real_escape_string($_articles[0]->shipping_username) . "'
                        AND `article`.`group_id` = '" . (int)$_articles[0]->group_id . "'");
                $associated = array_values($associated);
                sort($associated);

                if ($associated)
                {
                    $query = "SELECT `article`.`article_id`, `article`.`barcode_type`, `article`.`items_per_shipping_unit`,
                            `orders`.`id`, `orders`.`auction_number`, `orders`.`txnid`, `orders`.`picking_order_id`,
                            SUM(`orders`.`quantity`) AS `quantity`,
                            `article`.`items_per_shipping_unit`, `picking_order`.`shipping_username`, `article`.`group_id`
                        FROM `article`
                        JOIN `orders` ON `article`.`article_id` = `orders`.`article_id` AND `orders`.`manual` = 0
                        JOIN `auction` ON `auction`.`auction_number` = `orders`.`auction_number` AND `auction`.`txnid` = `orders`.`txnid`
                        LEFT JOIN `auction` AS `main_auction` ON `auction`.`main_auction_number` = `main_auction`.`auction_number`
                            AND `auction`.`txnid` = `main_auction`.`txnid`
                        JOIN `picking_order` ON `picking_order`.`id` = `orders`.`picking_order_id`
                        WHERE IFNULL(`main_auction`.`shipping_username`, `auction`.`shipping_username`) = ?
                            AND `article`.`article_id` IN (" . implode(',', $associated) . ") AND `article`.`admin_id` = 0
                        GROUP BY `article`.`article_id`";

                    $_articles = $dbr->getAll($query, null, [$po->shipping_username]);

                    foreach ($_articles as $key => $article)
                    {
                        $_articles[$key]->associated = [];
                        if ($_articles[$key]->group_id)
                        {
                            $_articles[$key]->associated = $dbr->getAssoc("SELECT `article`.`article_id`, `article`.`article_id` AS `v`
                                FROM `article`
                                JOIN `picking_order` ON `picking_order`.`article_id` = `article`.`article_id`
                                WHERE `article`.`admin_id` = 0
                                    AND `picking_order`.`delivered` = '0'
                                    AND `picking_order`.`ware_la_id` = '" . $ramp_id . "'
                                    AND `picking_order`.`shipping_username` = '" . mysql_real_escape_string($_articles[$key]->shipping_username) . "'
                                    AND `article`.`group_id` = '" . (int)$_articles[$key]->group_id . "'");

                            $_articles[$key]->associated = array_values($_articles[$key]->associated);
                            sort($_articles[$key]->associated);

                            if (count($_articles[$key]->associated) <= 1)
                            {
                                $_articles[$key]->associated = [];
                            }
                        }
                    }
                }
            }

            foreach ($_articles as $key => $article)
            {
                $_articles[$key]->quantity_in_la = 0;
                if ($_articles[$key]->barcode_type == 'A')
                {
                    foreach ($picked_articles as $_article)
                    {
                        if ($_article->article_id == $article->article_id)
                        {
                            $_articles[$key]->quantity_in_la ++;
                        }
                    }
                }
                else
                {
                    foreach ($picked_articles as $_article)
                    {
                        if ($_article->article_id == $article->article_id)
                        {
                            $_articles[$key]->quantity_in_la += $_article->quantity;
                        }
                    }
                }

                $_articles[$key]->quantity_total = max(0, $_articles[$key]->quantity - $_articles[$key]->quantity_in_la);

                $location = '';
                if ($_articles[$key]->barcode_type == 'A') {
                    $location = 'article_get_location_A';
                }
                else
                {
                    $location = 'article_get_location_C';
                }

                $_articles[$key]->locations = $location($_articles[$key]->article_id, $warehouse_id, $forklift_data);
            }

            $articles = array_merge($articles, $_articles);
        }

        $articles = checkAssociatedArticles($articles);

        $articles_locations = getArticlesLocations($articles, $hall);
        if ( ! $articles_locations)
        {
            foreach ($all_halls as $_id => $_hall)
            {
                if ($_id != $cell[0])
                {
                    $hall = $warehouse->data->ware_char . $_hall;
                    $articles_locations = getArticlesLocations($articles, $hall);

                    if ($articles_locations)
                    {
                        break;
                    }
                }
            }
        }

        $finally_artilces_arr = getPathByNextArticle($mazes[$hall], $steps_array, -200, $articles, $articles_locations, $plan_table);
        if ($finally_artilces_arr)
        {
            foreach ($finally_artilces_arr as $article_id => $dummy)
            {
                $article_id = explode('.', $article_id);
                foreach ($articles as $article)
                {
                    if (in_array($article->article_id, $article_id))
                    {
                        $db->execParam("UPDATE `picking_order` SET `opened_by_username` = ? WHERE `id` = ?",
                                [$loggedUser->get('username'), $article->picking_order_id]);

                        if ( ! $article->associated)
                        {
                            break 2;
                        }
                    }
                }
            }

            return true;
        }
    }

    return false;
}

function check_next_po($ramp_id, $prev = false) {
    global $picking_ids, $warehouse_id, $warehouse, $forklift_data, $loggedUser;
    global $all_halls, $hall, $cell, $steps_array;
    global $mazes, $mazes_storage, $mazes_ramps;

//    if (check_next_po_findway($ramp_id, $prev))
//    {
//        return;
//    }

    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $prev = $prev ? ' DESC ' : ' ASC ';

    $all_ids = [];
    $query = "SELECT DISTINCT(`picking_order`.`id`) AS `id`, `picking_order`.`opened_by_username`
        FROM `picking_order`
        JOIN `ware_la` ON `picking_order`.`ware_la_id` = `ware_la`.`id`
        WHERE
            `picking_order`.`delivered` = 0 AND IFNULL(`picking_order`.`opened_by_username`, ?) = ?
            AND `ware_la`.`warehouse_id` = '" . $warehouse_id . "'
            AND `picking_order`.`ware_la_id` = '" . $ramp_id . "'
        ORDER BY `picking_order`.`id` $prev ";

    $ids = $db->getAssoc($query, null, [$loggedUser->get('username'), $loggedUser->get('username')]);
    $all_ids = array_keys($ids);

    if ($all_ids) {

        $_picking_ids = explode('.', $picking_ids);
        $_picking_ids = array_map('intval', $_picking_ids);

        $all_ids_not_filled = [];
        foreach ($all_ids as $id) {
            if (!in_array($id, $_picking_ids) || !check_po_full($id)) {
                $all_ids_not_filled[] = $id;
            }
        }

        $query = "SELECT `picking_order`.`id`, `picking_order`.`id` `v`
            FROM `picking_order`
            JOIN `ware_la` ON `picking_order`.`ware_la_id` = `ware_la`.`id`
            WHERE
                `picking_order`.`delivered` = 0 AND `picking_order`.`opened_by_username` = ?
                AND `ware_la`.`warehouse_id` = ?
                AND `picking_order`.`ware_la_id` = ?
            ORDER BY `picking_order`.`id` $prev ";

        $active_ids = $db->getAssoc($query, null, [$loggedUser->get('username'), $warehouse_id, $ramp_id]);
        if (count($active_ids) == 1)
        {
            $active_id = (int) array_shift($active_ids);
            $active_ids = null;
        }

        if ($active_ids)
        {
            foreach ($active_ids as $_active_id)
            {
                $_isset = false;
                foreach ($ids as $_id => $_username)
                {
                    if ($_id == $_active_id)
                    {
                        $_isset = true;
                    }

                    if ($_isset && ! $_username)
                    {
                        $active_id = $_id;
                        break 2;
                    }
                }
            }

            if ( ! $active_id)
            {
                $_ids = $all_ids_not_filled ? $all_ids_not_filled : $all_ids;
                $active_id = $_ids[0];
            }
        }
        else if (in_array($active_id, $all_ids))
        {
            $_ids = $all_ids_not_filled ? $all_ids_not_filled : $all_ids;
            if (in_array($active_id, $_ids))
            {
                for ($i = 0; $i < count($_ids); ++$i)
                {
                    if ($active_id == $_ids[$i])
                    {
                        $active_id = isset($_ids[$i + 1]) ? $_ids[$i + 1] : $_ids[0];
                        break;
                    }
                }
            }
            else
            {
                $active_id = $_ids[0];
            }
        }
        else
        {
            $active_id = $all_ids[0];
        }

        if ($all_ids) {
            $db->query("UPDATE `picking_order` SET `opened_by_username` = NULL WHERE `id` IN ( " . implode(', ', $all_ids) . ' )');
        }

        $db->execParam("UPDATE `picking_order` SET `opened_by_username` = ? WHERE `id` = ?", [$loggedUser->get('username'), $active_id]);
    }
}

/**
 * Check this picking order if full
 *
 * @param int $picking_order_id
 * @return int
 */
function check_po_full($picking_order_id) {
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $query = "SELECT `article`.`article_id`, `article`.`barcode_type`, SUM(`orders`.`quantity`) AS `quantity`
        FROM `article`
        JOIN `orders` ON `article`.`article_id` = `orders`.`article_id` AND `orders`.`manual` = 0
        WHERE `article`.`admin_id` = 0 AND `orders`.`picking_order_id` = ?
        GROUP BY `article`.`article_id`";

    $article = $dbr->getRow($query, null, [$picking_order_id]);

    if ($article->barcode_type == 'A') {
        $picked_query = "SELECT v.id, v.barcode
            FROM vbarcode v
            LEFT JOIN parcel_barcode_article_barcode_deduct pob ON pob.barcode_id = v.id
            WHERE pob.picking_order_id = ?";
    }
    else {
        $picked_query = "SELECT popb.parcel_barcode_id AS `id`, CONCAT(tp.code,'/',lpad(pb.id,10,0)) AS `barcode`, -SUM(`popb`.`quantity`) AS `quantity`
            FROM parcel_barcode_article_deduct popb
                LEFT JOIN parcel_barcode pb ON popb.parcel_barcode_id = pb.id
                LEFT JOIN tn_packets tp on tp.id=pb.tn_packet_id
            WHERE popb.picking_order_id = ?
            GROUP BY `barcode` HAVING `quantity` > 0";
    }

    $picked_articles = $dbr->getAll($picked_query, null, $picking_order_id);

    $article->quantity_in_la = 0;
    if ($article->barcode_type == 'A') {
        $article->quantity_in_la = count($picked_articles);
    }
    else {
        foreach ($picked_articles as $_article) {
            $article->quantity_in_la += $_article->quantity;
        }
    }

    return max(0, $article->quantity - $article->quantity_in_la) == 0;
}

/**
 * Drop old opened_by_username for picking order
 *
 * @global type $loggedUser
 */
function drop_old_opener() {
    global $loggedUser;

    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $query = "SELECT `picking_order`.`id`, MAX(`tl`.`updated`) - DATE_ADD(CURRENT_TIMESTAMP, INTERVAL -2 MINUTE) AS `ts`
        FROM `picking_order`
        JOIN `total_log` AS `tl` ON `tl`.`tableid` = `picking_order`.`id`
        WHERE
            `tl`.`table_name` = 'picking_order'
            AND `tl`.`field_name` = 'opened_by_username'
            AND IFNULL(`picking_order`.`opened_by_username`, '') != ''
            AND `picking_order`.`opened_by_username` != ?
        GROUP BY `picking_order`.`id`
        HAVING `ts` < 0";

    $ids = [];
    foreach ($dbr->getAll($query, null, [$loggedUser->get('username')]) as $id) {
        $ids[] = (int)$id->id;
    }

    if ($ids) {
        $db->query("UPDATE `picking_order` SET `opened_by_username` = NULL WHERE `id` IN ( " . implode(', ', $ids) . ' )');
    }
}

/**
 * Close old forklifts
 */
function close_old_forklift()
{
    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $query = "SELECT `fork_lift_statistics`.`id`, MAX(`total_log`.`updated`) - DATE_ADD(CURRENT_TIMESTAMP, INTERVAL -2 MINUTE) AS `ts`
        FROM `fork_lift_statistics`
        JOIN `total_log` ON `total_log`.`tableid` = `fork_lift_statistics`.`id`
        WHERE
            `fork_lift_statistics`.`open` = '1'
            AND `total_log`.`table_name` = 'fork_lift_statistics'
            AND `total_log`.`field_name` = 'seconds'
        GROUP BY `fork_lift_statistics`.`id`
        HAVING `ts` < 0";

    $ids = [];
    foreach ($dbr->getAll($query) as $id)
    {
        $ids[] = (int)$id->id;
    }

    if ($ids) {
        $db->query("UPDATE `fork_lift_statistics` SET `open` = '0' WHERE `id` IN ( " . implode(', ', $ids) . ' )');
    }
}

/**
 * Open statistics for forklift
 *
 * @param int $forklift_id
 */
function open_forklift($forklift_id = 0)
{
    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $forklift_id = (int)$forklift_id;
    if ($forklift_id)
    {
        $query = "SELECT `id` FROM `fork_lift_statistics` WHERE `fork_lift_id` = '" . $forklift_id . "' AND `open` = '1'";
        $statistics_id = (int)$db->getOne($query);
        if ( ! $statistics_id)
        {
            $db->query("INSERT INTO `fork_lift_statistics` (`fork_lift_id`) VALUES ('" . $forklift_id . "')");
        }
        else
        {
            $db->query("UPDATE `fork_lift_statistics`
                    SET `seconds` = TIMESTAMPDIFF(SECOND, `time_start_use`, NOW())
                    WHERE `id` = '" . $statistics_id . "'");
        }
    }
}

/**
 * Close statistics for forklift
 *
 * @param int $forklift_id
 */
function close_forklift($forklift_id = 0)
{
    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $forklift_id = (int)$forklift_id;
    if ($forklift_id)
    {
        $query = "SELECT `id` FROM `fork_lift_statistics` WHERE `fork_lift_id` = '" . $forklift_id . "' AND `open` = '1'";
        $statistics_id = (int)$dbr->getOne($query);
        if ($statistics_id)
        {
            $db->query("UPDATE `fork_lift_statistics`
                    SET
                        `seconds` = TIMESTAMPDIFF(SECOND, `time_start_use`, NOW())
                        , `open` = '0'
                    WHERE `id` = '" . $statistics_id . "'");
        }
    }
}

function getArticlesInWarehouse($warehouse_id, $ramp_id, $forklift_data)
{
    global $loggedUser;
    global $warehouse;

    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $values = [];
    $translate = [];

    foreach (\LoadingArea::$ARTICLES_NAMES_LANGUAGES as $_lang)
    {
        $values[] = " `t_{$_lang}`.`value` AS `article_name_{$_lang}` ";
        $translate[] = "
            LEFT JOIN `translation` AS `t_{$_lang}` ON `t_{$_lang}`.`table_name` = 'article'
                AND `t_{$_lang}`.`field_name` = 'name'
                AND `t_{$_lang}`.`language` = '{$_lang}'
                AND `t_{$_lang}`.`id` = `article`.`article_id`
        ";
    }

    $query = "SELECT `picking_order`.*, `ware_la`.`la_name` AS `ramp_name`
        FROM `picking_order`
        JOIN `ware_la` ON `picking_order`.`ware_la_id` = `ware_la`.`id`
        WHERE `picking_order`.`delivered` = 0
            AND IFNULL(`picking_order`.`opened_by_username`, ?) = ?
            AND `ware_la`.`warehouse_id` = ?
            AND `picking_order`.`ware_la_id` = ?";
    $picking_orders = $dbr->getAll($query, null, [$loggedUser->get('username'), $loggedUser->get('username'), $warehouse_id, $ramp_id]);
    if ( ! $picking_orders)
    {
        return false;
    }

    $articles = [];

    foreach ($picking_orders as $picking_order)
    {
        if ($picking_order->shipping_username)
        {
            $query = "SELECT `article`.`article_id`, `article`.`barcode_type`, `article`.`items_per_shipping_unit`,
                    `orders`.`id`, `orders`.`auction_number`, `orders`.`txnid`, `orders`.`picking_order_id`,
                    SUM(`orders`.`quantity`) AS `quantity`,
                    `article`.`items_per_shipping_unit`, `article`.`volume`, `article`.`group_id`
                    , `picking_order`.`shipping_username`
                    , `picking_order`.`wwo_id`
                    , " . implode(', ', $values) . "
                FROM `article`
                JOIN `orders` ON `article`.`article_id` = `orders`.`article_id` AND `orders`.`manual` = 0
                JOIN `picking_order` ON `picking_order`.`id` = `orders`.`picking_order_id`
                JOIN `auction` ON `auction`.`auction_number` = `orders`.`auction_number` AND `auction`.`txnid` = `orders`.`txnid`
                LEFT JOIN `auction` AS `main_auction` ON `auction`.`main_auction_number` = `main_auction`.`auction_number`
                    AND `auction`.`txnid` = `main_auction`.`txnid`

                " . implode("\n", $translate) . "

                WHERE IFNULL(`main_auction`.`shipping_username`, `auction`.`shipping_username`) = ?
                    AND `article`.`article_id` = ? AND `article`.`admin_id` = 0
                    AND `orders`.`picking_order_id` = ?
                GROUP BY `article`.`article_id`, `orders`.`picking_order_id`";

            $articles[] = $dbr->getRow($query, null, [$picking_order->shipping_username, $picking_order->article_id, $picking_order->id]);
        }
        else
        {
            $query = "SELECT `article`.`article_id`, `article`.`barcode_type`, `article`.`items_per_shipping_unit`,
                    `wwo_article`.`wwo_id`, `wwo_article`.`picking_order_id`,
                    SUM(`wwo_article`.`qnt`) AS `quantity`,
                    `article`.`items_per_shipping_unit`, `article`.`volume`, `article`.`group_id`
                    , CONCAT('WWO #', `picking_order`.`wwo_id`) AS `shipping_username`
                    , `picking_order`.`wwo_id`
                    , " . implode(', ', $values) . "
                FROM `wwo_article`
                JOIN `article` ON `wwo_article`.`article_id` = `article`.`article_id`
                JOIN `picking_order` ON `picking_order`.`id` = `wwo_article`.`picking_order_id`

                " . implode("\n", $translate) . "

                WHERE `wwo_article`.`wwo_id` = ?
                    AND `article`.`article_id` = ? AND `article`.`admin_id` = 0
                    AND `wwo_article`.`picking_order_id` = ?
                    AND `wwo_article`.`taken` = '0'
                GROUP BY `article`.`article_id`, `wwo_article`.`picking_order_id`";

            $articles[] = $dbr->getRow($query, null, [$picking_order->wwo_id, $picking_order->article_id, $picking_order->id]);
        }
    }

    if ( ! $articles)
    {
        return false;
    }

    foreach ($articles as $key => $article)
    {
        $location = '';
        if ($article->barcode_type == 'A') {
            $location = 'article_get_location_A';
            $picked_query = "SELECT distinct v.id, v.article_id, v.barcode, pob.delivered
                FROM vbarcode v
                LEFT JOIN parcel_barcode_article_barcode_deduct pob ON pob.barcode_id = v.id
                WHERE pob.picking_order_id = ?";
        }
        else
        {
            $location = 'article_get_location_C';
            $picked_query = "SELECT popb.parcel_barcode_id AS `id`,
                    popb.article_id
                    CONCAT(tp.code,'/',lpad(pb.id,10,0)) AS `barcode`,
                    -SUM(`popb`.`quantity`) AS `quantity`, popb.delivered
                FROM parcel_barcode_article_deduct popb
                    LEFT JOIN parcel_barcode pb ON popb.parcel_barcode_id = pb.id
                    LEFT JOIN tn_packets tp on tp.id=pb.tn_packet_id
                WHERE popb.picking_order_id = ?
                GROUP BY `barcode` HAVING `quantity` > 0";
        }

        $picked_articles = $dbr->getAll($picked_query, null, $article->picking_order_id);

        $articles[$key]->quantity_in_la = 0;
        if ($articles[$key]->barcode_type == 'A') {
            foreach ($picked_articles as $_article) {
                if ($_article->article_id == $article->article_id)
                {
                    $articles[$key]->quantity_in_la ++;
                }
            }
        }
        else {
            foreach ($picked_articles as $_article) {
                if ($_article->article_id == $article->article_id)
                {
                    $articles[$key]->quantity_in_la += $_article->quantity;
                }
            }
        }

        $articles[$key]->quantity_total = max(0, $articles[$key]->quantity - $articles[$key]->quantity_in_la);

        $articles[$key]->locations = [];

        $dont_have_location = false;
        if ($articles[$key]->quantity_in_la < $articles[$key]->quantity)
        {
            $locations = $location($article->article_id, $warehouse_id, $forklift_data);
            if ( ! $locations)
            {
                $dont_have_location = true;
            }

            foreach ($locations as $_loc => $parcel_data) {
                foreach ($parcel_data as $_parcel => $_count) {
                    $_count = (int)$_count;
                    $articles[$key]->locations[$_loc][$_parcel] = $_count;
                }
            }
        }
        else
        {
            $dont_have_location = true;
        }

        if ($dont_have_location)
        {
            unset($articles[$key]);
        }
        else
        {
            $articles[$key]->dimensions = "<strong style='font-weight:bold'>" . (int)$articles[$key]->items_per_shipping_unit . " pcs/box</strong>";
            $parcels = \Article::getParcels($db, $dbr, $articles[$key]->article_id);
            if ($parcels[0]) {
                $parcels[0]->dimension_l = round($parcels[0]->dimension_l, 2);
                $parcels[0]->dimension_w = round($parcels[0]->dimension_w, 2);
                $parcels[0]->dimension_h = round($parcels[0]->dimension_h, 2);
                $parcels[0]->weight_parcel = round($parcels[0]->weight_parcel, 2);

                $articles[$key]->dimensions .= " | {$parcels[0]->dimension_l}x{$parcels[0]->dimension_w}x{$parcels[0]->dimension_h}cm | {$parcels[0]->weight_parcel}kg";
            }

            $articles[$key]->associated = [];
            if ($articles[$key]->group_id)
            {
                $articles[$key]->associated = $dbr->getAssoc("SELECT `article`.`article_id`, `article`.`article_id` AS `v`
                    FROM `article`
                    JOIN `picking_order` ON `picking_order`.`article_id` = `article`.`article_id`
                    WHERE `article`.`admin_id` = 0
                        AND `picking_order`.`delivered` = '0'
                        AND `picking_order`.`ware_la_id` = '" . $ramp_id . "'
                        AND `picking_order`.`shipping_username` = '" . mysql_real_escape_string($articles[$key]->shipping_username) . "'
                        AND `article`.`group_id` = '" . (int)$articles[$key]->group_id . "'");

                $articles[$key]->associated = array_values($articles[$key]->associated);
                sort($articles[$key]->associated);

                if (count($articles[$key]->associated) <= 1)
                {
                    $articles[$key]->associated = [];
                }
            }

            $articles[$key]->article_name = '';
            foreach (\LoadingArea::$ARTICLES_NAMES_LANGUAGES as $_lang)
            {
                if ($articles[$key]->{"article_name_{$_lang}"})
                {
                    $articles[$key]->article_name = $articles[$key]->{"article_name_{$_lang}"};
                    break;
                }
            }

            if ($articles[$key]->quantity_total == 0)
            {
                $_delivered = true;
                foreach ($picked_articles as $_article)
                {
                    if ( ! $_article->delivered)
                    {
                        $_delivered = false;
                        break;
                    }
                }

                if ($_delivered)
                {
                    unset($articles[$key]);
                }
            }
        }
    }

    $articles = checkAssociatedArticles($articles);

    if ( ! $articles)
    {
        return false;
    }

    foreach ($articles as $key => $article)
    {
        $query = "
            SELECT
                IFNULL(`ma`.`auction_number`, `au`.`auction_number`) AS `auction_number`
                , IFNULL(`ma`.`txnid`, `au`.`txnid`) AS `txnid`
                , `o`.`quantity`

            FROM `orders` AS `o` FORCE INDEX (picking_order_id)

            JOIN `auction` AS `au` ON `au`.`auction_number` = `o`.`auction_number`
                AND `au`.`txnid` = `o`.`txnid`

            LEFT JOIN `auction` AS `ma` ON `au`.`main_auction_number` = `ma`.`auction_number`
                AND `au`.`txnid` = `ma`.`txnid`

            WHERE `o`.`picking_order_id` = '" . $article->picking_order_id . "'
                AND `o`.`article_id` = '" . $article->article_id . "'
                AND `o`.`sent` = '0'
                AND `o`.`manual` = '0'
                AND `o`.`hidden` = '0'
            ";
        $articles[$key]->auctions = $dbr->getAll($query);
        $comments = [];
        
        foreach ($articles[$key]->auctions as $auction)
        {
            $comments = array_merge($comments, $dbr->getAll("SELECT `comment`, `auction_number`, `txnid` 
                FROM `auction_comment`
                WHERE `auction_number` = '" . $auction->auction_number . "'
                    AND `txnid` = '" . $auction->txnid . "'
                    AND `src` = 'warehouse'"));
        }
        
        $articles[$key]->comments = $comments;
    }
    return array_values($articles);
}

function checkAssociatedArticles($articles)
{
    foreach ($articles as $item_key => $item_article)
    {
        if ($item_article->associated)
        {
            $parcels = [];

            foreach ($item_article->associated as $associated)
            {
                foreach ($articles as $article)
                {
                    if ($article->article_id == $associated)
                    {
                        $parcels[$associated] = [];
                        foreach ($article->locations as $locations)
                        {
                            foreach (array_keys($locations) as $location)
                            {
                                $parcels[$associated][] = $location;
                            }
                        }
                    }
                }
            }

            $total_parcels = [];

            $first = true;
            foreach ($item_article->associated as $associated)
            {
                if ($first)
                {
                    $first = false;
                    $total_parcels = $parcels[$associated];
                }
                else
                {
                    $total_parcels = array_intersect($total_parcels, $parcels[$associated]);
                }
            }

            if ( ! $total_parcels)
            {
                foreach ($item_article->associated as $associated)
                {
                    foreach ($articles as $key => $article)
                    {
                        if ($article->article_id == $associated)
                        {
                            $articles[$key]->associated = [];
                        }
                    }
                }
            }
        }
    }

    return $articles;
}

function createMazes($warehouse_id, $ramp_id)
{
    global $warehouse;
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    $mazes = [];
    $mazes_storage = [];
    $mazes_storage_temp = [];
    $mazes_ramps = [];

    $halls = $dbr->getAll("SELECT * FROM `warehouse_halls`
            WHERE `warehouse_id` = '" . $warehouse_id . "' AND `status` = '1'");

    foreach ($halls as $hall_parameters)
    {
        $data_hall = $dbr->getAll("SELECT * FROM warehouse_cells WHERE hall_id = '" . $hall_parameters->id . "'");
        $form_data = [];
        foreach ($data_hall as $sql_array_unit) {
            $form_data['type'][$sql_array_unit->cell_y][$sql_array_unit->cell_x] = $sql_array_unit->type;
            $form_data['y'][$sql_array_unit->cell_y][$sql_array_unit->cell_x] = $sql_array_unit->row;
            $form_data['x'][$sql_array_unit->cell_y][$sql_array_unit->cell_x] = $sql_array_unit->bay;
            $form_data['v'][$sql_array_unit->cell_y][$sql_array_unit->cell_x][$sql_array_unit->cell_v] = $sql_array_unit->level;
            $form_data['status'][$sql_array_unit->cell_y][$sql_array_unit->cell_x][$sql_array_unit->cell_v] = $sql_array_unit->status;
            $form_data['ramp'][$sql_array_unit->cell_y][$sql_array_unit->cell_x] = $sql_array_unit->ramp;
        }

        $yy = 1;
        for ($y = $hall_parameters->cells_quantity_y; $y >= 1; $y--)
        {
            $xx = 1;
            for ($x = 1; $x <= $hall_parameters->cells_quantity_x; $x++)
            {
                if ($form_data['type'][$yy][$xx]) {
                    $cell_type = $form_data['type'][$yy][$xx];
                } else {
                    $cell_type = 'transport';
                }//storage

                switch ($cell_type)
                {
                    case 'storage':
                        $type = 2;
                        break;
                    case 'wall':
                        $type = 1;
                        break;
                    default:
                        $type = 0;
                        break;
                }

                if ($cell_type == 'ramp' && $form_data['ramp'][$yy][$xx] == $ramp_id)
                {
                    $mazes_ramps[$warehouse->data->ware_char . $hall_parameters->title_id] = [
                        $yy-1 => $xx-1
                    ];
                }

                $mazes[$warehouse->data->ware_char . $hall_parameters->title_id][$yy-1][$xx-1] = $type;

                if ($cell_type == 'storage')
                {
                    for ($v = $hall_parameters->cells_quantity_v; $v >= 1; $v--) {
                        if ($form_data['status'][$yy][$xx][$v] != '0')
                        {
                            $mazes_storage_temp[$warehouse->data->ware_char . $hall_parameters->title_id][$yy-1][$xx-1] =
                                $form_data['y'][$yy][$xx] . '-' . $form_data['x'][$yy][$xx];
                        }
                    }
                }

                $xx++;
            }

            $yy++;
        }
    }

    foreach ($mazes_storage_temp as $hall_id => $hall_data)
    {
        foreach ($hall_data as $x => $y_data)
        {
            foreach ($y_data as $y => $cell)
            {
//                if (isset($mazes[$hall_id][$x][$y - 1]) && $mazes[$hall_id][$x][$y - 1] == 0)
//                {
//                    $mazes_storage[$hall_id . '-' . $cell][$x] = $y - 1;
//                }
//                else if (isset($mazes[$hall_id][$x + 1][$y]) && $mazes[$hall_id][$x + 1][$y] == 0)
//                {
//                    $mazes_storage[$hall_id . '-' . $cell][$x + 1] = $y;
//                }
//                else if (isset($mazes[$hall_id][$x][$y + 1]) && $mazes[$hall_id][$x][$y + 1] == 0)
//                {
//                    $mazes_storage[$hall_id . '-' . $cell][$x] = $y + 1;
//                }
//                else if (isset($mazes[$hall_id][$x - 1][$y]) && $mazes[$hall_id][$x - 1][$y] == 0)
//                {
//                    $mazes_storage[$hall_id . '-' . $cell][$x - 1] = $y;
//                }
//                else if (isset($mazes[$hall_id][$x - 1][$y - 1]) && $mazes[$hall_id][$x - 1][$y - 1] == 0)
//                {
//                    $mazes_storage[$hall_id . '-' . $cell][$x - 1] = $y - 1;
//                }
//                else if (isset($mazes[$hall_id][$x - 1][$y + 1]) && $mazes[$hall_id][$x - 1][$y + 1] == 0)
//                {
//                    $mazes_storage[$hall_id . '-' . $cell][$x - 1] = $y + 1;
//                }
//                else if (isset($mazes[$hall_id][$x + 1][$y - 1]) && $mazes[$hall_id][$x + 1][$y - 1] == 0)
//                {
//                    $mazes_storage[$hall_id . '-' . $cell][$x + 1] = $y - 1;
//                }
//                else if (isset($mazes[$hall_id][$x + 1][$y + 1]) && $mazes[$hall_id][$x + 1][$y + 1] == 0)
//                {
//                    $mazes_storage[$hall_id . '-' . $cell][$x + 1] = $y + 1;
//                }
//                else
//                {
//                    $mazes_storage[$hall_id . '-' . $cell][$x] = $y;
//                }

                $mazes_storage[$hall_id . '-' . $cell][$x] = $y;
            }
        }
    }

    return [
        'mazes' => $mazes,
        'mazes_storage' => $mazes_storage,
        'mazes_ramps' => $mazes_ramps,
    ];
}

function getArticlesLocations($articles, $hall = false)
{
    global $mazes_storage;

    $locations = [];
    foreach ($articles as $article)
    {
        foreach ($article->locations as $_loc => $parcel_data)
        {
            foreach ($parcel_data as $_parcel => $_count)
            {
                $_loc = explode('-', $_loc);
                if ( ! $hall || $_loc[0] == $hall)
                {
                    if ($_loc[0])
                    {
                        $_loc = $_loc[0] . '-' . $_loc[1] . '-' . $_loc[2];
                        if ( ! isset($locations[$article->article_id][$_loc][$_parcel]))
                        {
                            $locations[$article->article_id][$_loc][$_parcel] = 0;
                        }

                        $locations[$article->article_id][$_loc][$_parcel] += $_count;
                    }
                }
            }
        }
    }

    $_articles_locations_set = [];
    foreach ($articles as $article)
    {
        if ($article->associated)
        {
            $key = implode('.', $article->associated);
            if ( ! isset($_articles_locations_set[$key]))
            {
                foreach ($article->associated as $associated_id)
                {
                    foreach ($locations[$associated_id] as $_loc => $parcel_data)
                    {
                        foreach ($parcel_data as $_parcel => $_count)
                        {
                            if ( ! isset($_articles_locations_set[$key][$_loc . '|' . $_parcel][$associated_id]))
                            {
                                $_articles_locations_set[$key][$_loc . '|' . $_parcel][$associated_id] = 0;
                            }

                            $_articles_locations_set[$key][$_loc . '|' . $_parcel][$associated_id] += $_count;
                        }
                    }
                }
            }
        }
    }

    $articles_locations_set = [];
    foreach ($_articles_locations_set as $_set => $_loc)
    {
        $_set_articles = explode('.', $_set);
        foreach ($_loc as $_location => $_articles)
        {
            if (count($_articles) == count($_set_articles))
            {
                $articles_locations_set[$_set][$_location]['qnt'] = min($_articles);
            }
        }
    }

    $articles_locations = [];
    foreach ($locations as $_article_id => $_locations_articles)
    {
        $is_set = false;
        foreach ($articles as $article)
        {
            if ($article->article_id == $_article_id && $article->associated)
            {
                $is_set = true;
                break;
            }
        }

        if ($is_set)
        {
            continue;
        }

        foreach ($_locations_articles as $_loc => $parcel_data)
        {
            foreach ($parcel_data as $_parcel => $_count)
            {
                if ($articles_locations_set)
                {
                    foreach ($articles_locations_set as $_articles_locations_set_articles => $dummy)
                    {
                        if ( ! in_array($_article_id, explode('.', $_articles_locations_set_articles)))
                        {
                            if ( ! isset($articles_locations[$_article_id][$_loc . '|' . $_parcel]['qnt']))
                            {
                                $articles_locations[$_article_id][$_loc . '|' . $_parcel]['qnt'] = 0;
                            }
                            $articles_locations[$_article_id][$_loc . '|' . $_parcel]['qnt'] += $_count;
                        }
                    }
                }
                else
                {
                    $articles_locations[$_article_id][$_loc . '|' . $_parcel]['qnt'] += $_count;
                }
            }
        }
    }

    foreach ($articles_locations_set as $key => $_locations)
    {
        foreach ($_locations as $_loc => $dummy)
        {
            $_maze = explode('|', $_loc);
            $_maze = explode('-', $_maze[0]);
            $_maze = $_maze[0] . '-' . $_maze[1] . '-' . $_maze[2];

            if (isset($mazes_storage[$_maze]))
            {
                foreach ($mazes_storage[$_maze] as $y => $x)
                {
                    $articles_locations_set[$key][$_loc]['coord'] = ($y+1) . '-' . ($x+1);
                }
            }
        }
    }

    foreach ($articles_locations as $key => $_locations)
    {
        foreach ($_locations as $_loc => $dummy)
        {
            $_maze = explode('|', $_loc);
            $_maze = explode('-', $_maze[0]);
            $_maze = $_maze[0] . '-' . $_maze[1] . '-' . $_maze[2];

            if (isset($mazes_storage[$_maze]))
            {
                foreach ($mazes_storage[$_maze] as $y => $x)
                {
                    $articles_locations[$key][$_loc]['coord'] = ($y+1) . '-' . ($x+1);
                }
            }
        }
    }

//    $articles_locations_set = [];
    foreach ($articles_locations as $key => $location)
    {
        $articles_locations_set[$key] = $location;
        ksort($articles_locations_set[$key]);
    }

    foreach ($articles_locations_set as $key => $values)
    {
        uksort($values, function($a, $b) {
            return $a > $b ? 1 : -1;
        });
        $articles_locations_set[$key] = $values;
    }

    uksort($articles_locations_set, function($a, $b) use ($articles_locations_set) {
        $a_keys = min(array_keys($articles_locations_set[$a]));
        $b_keys = min(array_keys($articles_locations_set[$b]));

        return $a_keys < $b_keys ? -1 : 1;
    });

    return $articles_locations_set;
}

function getPathByNextArticleCheckSet($articles_locations, $articles_quantity)
{
    foreach ($articles_locations as $articles_ids_str => $_location)
    {
        if (strpos($articles_ids_str, '.') !== false)
        {
            foreach ($_location as $_parcel => $_loc)
            {
                if ($_loc['qnt'])
                {
                    $articles_ids = explode('.', $articles_ids_str);
                    $set_quantity = 0;
                    foreach ($articles_ids as $_article_id)
                    {
                        $set_quantity += $articles_quantity[$_article_id];
                    }

                    if ($set_quantity)
                    {
                        return true;
                    }
                }
            }
        }
    }

    return false;
}

function getPathByNextArticle($maze, $steps_array, $forklift_data_volume, $articles, $articles_locations, &$plan_table)
{
    global $mazes_storage, $hall;

    $articles_quantity = [];
    foreach ($articles as $article)
    {
        $articles_quantity[$article->article_id] = $article->quantity_total;
    }

    $articles_total_quantity = max($articles_quantity);

    $plan_table = '';
    $yc = 1;
    $xc = 1;
    $f = true;
    $plan_table .= '<table style="font-size:smaller">';
    foreach ($maze as $x => $y_data)
    {
        if ($f)
        {
            $f = false;
            $plan_table .= "<tr><th></th>";
            foreach ($y_data as $y)
            {
                $_val_txt = '';
                foreach ($mazes_storage as $_storage => $_coords)
                {
                    if (stripos($_storage, $hall) === 0)
                    {
                        foreach ($_coords as $_sty => $_stx)
                        {
                            if ($_stx + 1 == $xc)
                            {
                                $_storage = explode('-', $_storage);
                                $_val_txt = $_storage[2];
                                break 2;
                            }
                        }
                    }
                }

                $xc++;
                $plan_table .= "<th>" . $_val_txt . "</th>";
            }
            $plan_table .= "</tr>";
        }

        $xc = 1;

        $_val_txt = '';
        foreach ($mazes_storage as $_storage => $_coords)
        {
            if (stripos($_storage, $hall) === 0)
            {
                foreach ($_coords as $_sty => $_stx)
                {
                    if ($_sty + 1 == $yc)
                    {
                        $_storage = explode('-', $_storage);
                        $_val_txt = $_storage[1];
                        break 2;
                    }
                }
            }
        }

        $plan_table .= "<tr><th>" . $_val_txt . "</th>";
        foreach ($y_data as $y)
        {
            $background = '#ffffff';
            if ($y == 1)
            {
                $background = '#dfdfdf';
            }
            else if ($y == 2)
            {
                $background = '#fd5c5c';
            }

            $sa = explode('-', $steps_array[0]);
            if ($sa[0] + 1 == $yc && $sa[1] + 1 == $xc)
            {
                $background = '#67de78';
            }

            $articles_ids = [];
            foreach ($articles_locations as $name => $a)
            {
                foreach ($a as $loc => $p)
                {
                    $p = explode('-', $p['coord']);
                    if ($p[0] == $yc && $p[1] == $xc)
                    {
                        $background = '#fe9d9d';

                        $name = explode('.', $name);
                        if (count($name) > 1)
                        {
                            $name = "SET: " . implode('/', $name);
                        }
                        else
                        {
                            $name = $name[0];
                        }

                        if ( ! isset($articles_ids[$name]))
                        {
                            $articles_ids[$name] = 0;
                        }
                        $articles_ids[$name]++;
                    }
                }
            }

            $plan_table .= "<td style='white-space:nowrap;border:1px solid #444;color:#333;border-collapse:collapse;background:" . $background . "'>";
            if ($articles_ids)
            {
                $names = [];
                foreach ($articles_ids as $name => $count)
                {
                    $names[] = $name . ' x' . $count;
                }

                $plan_table .= implode('<br>', $names);
            }
            else
            {
                $plan_table .= '&nbsp;';
            }
            $plan_table .= "</td>";
            $xc++;
        }
        $yc++;
        $plan_table .= '</tr>';
    }
    $plan_table .= '</table>';

    $finally_artilces = [];
    $finally_artilces_loc = [];

    $exists_sets = false;

    $max_xx = 0;
    $max_yy = 0;
    foreach ($articles_locations as $articles_ids_str => $_location)
    {
        foreach (array_keys($_location) as $_loc)
        {
            $_loc = explode('|', $_loc);
            $_loc = explode('-', $_loc[0]);
            $_loc = array_map('intval', $_loc);

            $max_xx = max($max_xx, $_loc[1]);
            $max_yy = max($max_yy, $_loc[2]);
        }
    }

    if ( ! $max_xx) $max_xx = 100;
    if ( ! $max_yy) $max_yy = 250;

    for ($repeat = 1; $repeat <= 2; ++$repeat)
    {
        for ($xx = 1; $xx <= $max_xx; $xx++)
        {
            $row = str_pad($xx, 2, '0', STR_PAD_LEFT);
            for ($yy = 1; $yy <= $max_yy; $yy++)
            {
                $bay = str_pad($yy, 3, '0', STR_PAD_LEFT);

                $isset_article = false;
                $isset_sets = false;

                foreach ($articles_locations as $articles_ids_str => $_location)
                {
                    foreach ($_location as $_parcel => $_loc)
                    {
                        $count = (int)$_loc['qnt'];
                        if ( ! $count)
                        {
                            continue;
                        }

                        if (stripos($_parcel, "{$hall}-{$row}-{$bay}") === 0)
                        {
                            $isset_sets = getPathByNextArticleCheckSet($articles_locations, $articles_quantity);
                            if ( ! $exists_sets && $isset_sets)
                            {
                                $exists_sets = true;
                            }

                            $articles_ids = explode('.', $articles_ids_str);
                            if ($isset_sets && count($articles_ids) == 1)
                            {
                                break;
                            }

                            $volume_set = [];
                            $qnt_set = [];
                            foreach ($articles as $article)
                            {
                                if (in_array($article->article_id, $articles_ids))
                                {
                                    $volume_set[$article->article_id] = $article->volume;
                                    $qnt_set[$article->article_id] = $articles_quantity[$article->article_id];
                                }
                            }
                            $volume_set = array_sum($volume_set);
                            $qnt_set = min($qnt_set);

                            if ($forklift_data_volume == -100 || $forklift_data_volume == -200)
                            {
                                $qnt_set = $articles_total_quantity;
                            }

                            if ( ! $qnt_set)
                            {
                                break;
                            }

                            if ($forklift_data_volume == -100 || $forklift_data_volume == -200)
                            {
                                $finally_artilces[$articles_ids_str][$_parcel] = $count;

                                $articles_total_quantity -= $count;

                                $count = 0;
                                $articles_locations[$articles_ids_str][$_parcel]['qnt'] = 0;
                                $isset_article = true;
                            }
                            else
                            {
                                if ($qnt_set <= $count)
                                {
                                    $volume = $volume_set * $qnt_set;
                                    if ($forklift_data_volume - $volume >= 0)
                                    {
                                        $forklift_data_volume -= $volume;
                                        $finally_artilces[$articles_ids_str][$_parcel] = $qnt_set;
                                        $finally_artilces_loc[$_parcel][$articles_ids_str] = $qnt_set;
                                        foreach ($articles_ids as $article_id)
                                        {
                                            $articles_quantity[$article_id] = 0;
                                        }

                                        $count -= $qnt_set;
                                        $count = max(0, $count);
                                        $articles_locations[$articles_ids_str][$_parcel]['qnt'] = $count;
                                        $isset_article = true;
                                    }
                                }
                                else
                                {
                                    $volume = $volume_set * $count;
                                    if ($forklift_data_volume - $volume >= 0)
                                    {
                                        $forklift_data_volume -= $volume;

                                        $finally_artilces[$articles_ids_str][$_parcel] = $count;
                                        $finally_artilces_loc[$_parcel][$articles_ids_str] = $count;
                                        foreach ($articles_ids as $article_id)
                                        {
                                            $articles_quantity[$article_id] -= $count;
                                            $articles_quantity[$article_id] = max(0, $articles_quantity[$article_id]);
                                        }

                                        $count = 0;
                                        $articles_locations[$articles_ids_str][$_parcel]['qnt'] = 0;
                                        $isset_article = true;
                                    }
                                }
                            }
                        }
                    }
                }

                if ($isset_article && $forklift_data_volume == -200)
                {
                    return  $finally_artilces;
                }
            }
        }

        if ( ! $exists_sets)
        {
            break;
        }
    }

    return ($forklift_data_volume == -100 || $forklift_data_volume == -200) ? $finally_artilces : $finally_artilces_loc;
}

function getPathByNextArticleOld($maze, $steps_array, $forklift_data_volume, $articles, $articles_locations, &$plan_table)
{
    global $mazes_storage, $hall;

    $articles_quantity = [];
    foreach ($articles as $article)
    {
        $articles_quantity[$article->article_id] = $article->quantity_total;
    }

    $articles_total_quantity = max($articles_quantity);

    $plan_table = '';
    $yc = 1;
    $xc = 1;
    $f = true;
    $plan_table .= '<table style="font-size:smaller">';
    foreach ($maze as $x => $y_data)
    {
        if ($f)
        {
            $f = false;
            $plan_table .= "<tr><th></th>";
            foreach ($y_data as $y)
            {
                $_val_txt = '';
                foreach ($mazes_storage as $_storage => $_coords)
                {
                    if (stripos($_storage, $hall) === 0)
                    {
                        foreach ($_coords as $_sty => $_stx)
                        {
                            if ($_stx + 1 == $xc)
                            {
                                $_storage = explode('-', $_storage);
                                $_val_txt = $_storage[2];
                                break 2;
                            }
                        }
                    }
                }

                $xc++;
                $plan_table .= "<th>" . $_val_txt . "</th>";
            }
            $plan_table .= "</tr>";
        }

        $xc = 1;

        $_val_txt = '';
        foreach ($mazes_storage as $_storage => $_coords)
        {
            if (stripos($_storage, $hall) === 0)
            {
                foreach ($_coords as $_sty => $_stx)
                {
                    if ($_sty + 1 == $yc)
                    {
                        $_storage = explode('-', $_storage);
                        $_val_txt = $_storage[1];
                        break 2;
                    }
                }
            }
        }

        $plan_table .= "<tr><th>" . $_val_txt . "</th>";
        foreach ($y_data as $y)
        {
            $background = '#ffffff';
            if ($y == 1)
            {
                $background = '#dfdfdf';
            }
            else if ($y == 2)
            {
                $background = '#fd5c5c';
            }

            $sa = explode('-', $steps_array[0]);
            if ($sa[0] + 1 == $yc && $sa[1] + 1 == $xc)
            {
                $background = '#67de78';
            }

            $articles_ids = [];
            foreach ($articles_locations as $name => $a)
            {
                foreach ($a as $loc => $p)
                {
                    $p = explode('-', $p['coord']);
                    if ($p[0] == $yc && $p[1] == $xc)
                    {
                        $background = '#fe9d9d';

                        $name = explode('.', $name);
                        if (count($name) > 1)
                        {
                            $name = "SET: " . implode('/', $name);
                        }
                        else
                        {
                            $name = $name[0];
                        }

                        if ( ! isset($articles_ids[$name]))
                        {
                            $articles_ids[$name] = 0;
                        }
                        $articles_ids[$name]++;
                    }
                }
            }

            $plan_table .= "<td style='white-space:nowrap;border:1px solid #444;color:#333;border-collapse:collapse;background:" . $background . "'>";
            if ($articles_ids)
            {
                $names = [];
                foreach ($articles_ids as $name => $count)
                {
                    $names[] = $name . ' x' . $count;
                }

                $plan_table .= implode('<br>', $names);
            }
            else
            {
                $plan_table .= '&nbsp;';
            }
            $plan_table .= "</td>";
            $xc++;
        }
        $yc++;
        $plan_table .= '</tr>';
    }
    $plan_table .= '</table>';

    $finally_artilces = [];
    $finally_artilces_loc = [];

    $maze_array = [];

    $lenght_y = count($maze);
    $lenght_x = count($maze[0]);

    while (true)
    {
        $steps_array_temp = [];
        $all_cells = true;

        foreach ($steps_array as $coords_yx)
        {
            $coords_yx = explode('-', $coords_yx);
            $coords_y = (int)$coords_yx[0];
            $coords_x = (int)$coords_yx[1];

            if ( ! isset($maze_array[$coords_y][$coords_x]))
            {
                $maze_array[$coords_y][$coords_x] = 1;
            }

            $current_summ = (int)$maze_array[$coords_y][$coords_x];

            $isset_article = false;
            $isset_sets = false;
            foreach ($articles_locations as $articles_ids_str => $_location)
            {
                if (strpos($articles_ids_str, '.') !== false)
                {
                    foreach ($_location as $_parcel => $_loc)
                    {
                        if ($_loc['qnt'])
                        {
                            $articles_ids = explode('.', $articles_ids_str);
                            $set_quantity = 0;
                            foreach ($articles_ids as $_article_id)
                            {
                                $set_quantity += $articles_quantity[$_article_id];
                            }

                            if ($set_quantity)
                            {
                                $isset_sets = true;
                                break 2;
                            }
                        }
                    }
                }
            }

            foreach ($articles_locations as $articles_ids_str => $_location)
            {
                foreach ($_location as $_parcel => $_loc)
                {
                    $count = (int)$_loc['qnt'];
                    if ( ! $count)
                    {
                        continue;
                    }

                    $coords = explode('-', $_loc['coord']);
                    if (abs($coords[0] - $coords_y) <= 1 && abs($coords[1] - $coords_x) <= 1)
                    {
                        $articles_ids = explode('.', $articles_ids_str);

                        if ($isset_sets && count($articles_ids) == 1)
                        {
                            continue;
                        }

                        $volume_set = [];
                        $qnt_set = [];
                        foreach ($articles as $article)
                        {
                            if (in_array($article->article_id, $articles_ids))
                            {
                                $volume_set[$article->article_id] = $article->volume;
                                $qnt_set[$article->article_id] = $articles_quantity[$article->article_id];
                            }
                        }
                        $volume_set = array_sum($volume_set);
                        $qnt_set = min($qnt_set);

                        if ($forklift_data_volume == -100 || $forklift_data_volume == -200)
                        {
                            $qnt_set = $articles_total_quantity;
                        }

                        if ( ! $qnt_set)
                        {
                            break;
                        }

                        if ($forklift_data_volume == -100 || $forklift_data_volume == -200)
                        {
                            $finally_artilces[$articles_ids_str][$_parcel] = $count;

                            $articles_total_quantity -= $count;

                            $count = 0;
                            $articles_locations[$articles_ids_str][$_parcel]['qnt'] = 0;
                            $isset_article = true;
                        }
                        else
                        {
                            if ($qnt_set <= $count)
                            {
                                $volume = $volume_set * $qnt_set;
                                if ($forklift_data_volume - $volume >= 0)
                                {
                                    $forklift_data_volume -= $volume;
                                    $finally_artilces[$articles_ids_str][$_parcel] = $qnt_set;
                                    $finally_artilces_loc[$_parcel][$articles_ids_str] = $qnt_set;
                                    foreach ($articles_ids as $article_id)
                                    {
                                        $articles_quantity[$article_id] = 0;
                                    }

                                    $count -= $qnt_set;
                                    $count = max(0, $count);
                                    $articles_locations[$articles_ids_str][$_parcel]['qnt'] = $count;
                                    $isset_article = true;
                                }
                            }
                            else
                            {
                                $volume = $volume_set * $count;
                                if ($forklift_data_volume - $volume >= 0)
                                {
                                    $forklift_data_volume -= $volume;

                                    $finally_artilces[$articles_ids_str][$_parcel] = $count;
                                    $finally_artilces_loc[$_parcel][$articles_ids_str] = $count;
                                    foreach ($articles_ids as $article_id)
                                    {
                                        $articles_quantity[$article_id] -= $count;
                                        $articles_quantity[$article_id] = max(0, $articles_quantity[$article_id]);
                                    }

                                    $count = 0;
                                    $articles_locations[$articles_ids_str][$_parcel]['qnt'] = 0;
                                    $isset_article = true;
                                }
                            }
                        }
                    }
                }
            }

            if ($isset_article)
            {
                $all_cells = false;

                $maze_array = [];
                $steps_array[] = "{$coords_y}-{$coords_x}";
                $steps_array_temp[] = "{$coords_y}-{$coords_x}";

                if ($forklift_data_volume == -200)
                {
                    return  $finally_artilces;
                }
                break;
            }

            if (isset($maze[$coords_y - 1][$coords_x]) && ! isset($maze_array[$coords_y - 1][$coords_x]))
            {
                $weight = (int)$maze[$coords_y - 1][$coords_x];
                if ($weight == 0)
                {
                    $maze_array[$coords_y - 1][$coords_x] = 1 + $current_summ;
                    $steps_array_temp[] = ($coords_y - 1) . "-" . ($coords_x);
                }
                else
                {
                    $maze_array[$coords_y - 1][$coords_x] = 0;
                }

                $all_cells = false;
            }

            if (isset($maze[$coords_y + 1][$coords_x]) && ! isset($maze_array[$coords_y + 1][$coords_x]))
            {
                $weight = (int)$maze[$coords_y + 1][$coords_x];
                if ($weight == 0)
                {
                    $maze_array[$coords_y + 1][$coords_x] = 1 + $current_summ;
                    $steps_array_temp[] = ($coords_y + 1) . "-" . ($coords_x);
                }
                else
                {
                    $maze_array[$coords_y + 1][$coords_x] = 0;
                }

                $all_cells = false;
            }

            if (isset($maze[$coords_y][$coords_x - 1]) && ! isset($maze_array[$coords_y][$coords_x - 1]))
            {
                $weight = (int)$maze[$coords_y][$coords_x - 1];
                if ($weight == 0)
                {
                    $maze_array[$coords_y][$coords_x - 1] = 1 + $current_summ;
                    $steps_array_temp[] = ($coords_y) . "-" . ($coords_x - 1);
                }
                else
                {
                    $maze_array[$coords_y][$coords_x - 1] = 0;
                }

                $all_cells = false;
            }

            if (isset($maze[$coords_y][$coords_x + 1]) && ! isset($maze_array[$coords_y][$coords_x + 1]))
            {
                $weight = (int)$maze[$coords_y][$coords_x + 1];
                if ($weight == 0)
                {
                    $maze_array[$coords_y][$coords_x + 1] = 1 + $current_summ;
                    $steps_array_temp[] = ($coords_y) . "-" . ($coords_x + 1);
                }
                else
                {
                    $maze_array[$coords_y][$coords_x + 1] = 0;
                }

                $all_cells = false;
            }

            if (isset($maze[$coords_y - 1][$coords_x - 1]) && ! isset($maze_array[$coords_y - 1][$coords_x - 1]))
            {
                $weight = (int)$maze[$coords_y - 1][$coords_x - 1];
                if ($weight == 0)
                {
                    $maze_array[$coords_y - 1][$coords_x - 1] = 1 + $current_summ;
                    $steps_array_temp[] = ($coords_y - 1) . "-" . ($coords_x - 1);
                }
                else
                {
                    $maze_array[$coords_y - 1][$coords_x - 1] = 0;
                }

                $all_cells = false;
            }

            if (isset($maze[$coords_y + 1][$coords_x - 1]) && ! isset($maze_array[$coords_y + 1][$coords_x - 1]))
            {
                $weight = (int)$maze[$coords_y + 1][$coords_x - 1];
                if ($weight == 0)
                {
                    $maze_array[$coords_y + 1][$coords_x - 1] = 1 + $current_summ;
                    $steps_array_temp[] = ($coords_y + 1) . "-" . ($coords_x - 1);
                }
                else
                {
                    $maze_array[$coords_y + 1][$coords_x - 1] = 0;
                }

                $all_cells = false;
            }

            if (isset($maze[$coords_y - 1][$coords_x + 1]) && ! isset($maze_array[$coords_y - 1][$coords_x + 1]))
            {
                $weight = (int)$maze[$coords_y - 1][$coords_x + 1];
                if ($weight == 0)
                {
                    $maze_array[$coords_y - 1][$coords_x + 1] = 1 + $current_summ;
                    $steps_array_temp[] = ($coords_y - 1) . "-" . ($coords_x + 1);
                }
                else
                {
                    $maze_array[$coords_y - 1][$coords_x + 1] = 0;
                }

                $all_cells = false;
            }

            if (isset($maze[$coords_y + 1][$coords_x + 1]) && ! isset($maze_array[$coords_y + 1][$coords_x + 1]))
            {
                $weight = (int)$maze[$coords_y + 1][$coords_x + 1];
                if ($weight == 0)
                {
                    $maze_array[$coords_y + 1][$coords_x + 1] = 1 + $current_summ;
                    $steps_array_temp[] = ($coords_y + 1) . "-" . ($coords_x + 1);
                }
                else
                {
                    $maze_array[$coords_y + 1][$coords_x + 1] = 0;
                }

                $all_cells = false;
            }
        }

        $steps_array = $steps_array_temp;
        if ($all_cells)
        {
            break;
        }
    }

    return ($forklift_data_volume == -100 || $forklift_data_volume == -200) ? $finally_artilces : $finally_artilces_loc;
}

function getNextPickingOrderListId($current_picking_id)
{
    global $picking_ids;
    $picking_ids_total_array = explode(',', $picking_ids);

    $picking_articles_list = isset($_SESSION['picking_articles_list']) ? (array)$_SESSION['picking_articles_list'] : [];
    $picking_articles_list = array_keys($picking_articles_list);

    for ($step = 0; $step < count($picking_articles_list); ++$step)
    {
        if ($current_picking_id == $picking_articles_list[$step])
        {
            $from_index = $step == count($picking_articles_list) - 1 ? 0 : $step+1;
            for ($key = $from_index; $key < count($picking_articles_list); ++$key)
            {
                $picking_ids = explode('.', $picking_articles_list[$key]);

                $in_array = 0;
                foreach ($picking_ids as $_id)
                {
                    if (in_array($_id, $picking_ids_total_array))
                    {
                        $in_array++;
                    }
                }

                if ($in_array < count($picking_ids))
                {
                    return $picking_articles_list[$key];
                }
            }

            if ($from_index != 0)
            {
                for ($key = 0; $key <= $from_index; ++$key)
                {
                    $picking_ids = explode('.', $picking_articles_list[$key]);

                    $in_array = 0;
                    foreach ($picking_ids as $_id)
                    {
                        if (in_array($_id, $picking_ids_total_array))
                        {
                            $in_array++;
                        }
                    }

                    if ($in_array < count($picking_ids))
                    {
                        return $picking_articles_list[$key];
                    }
                }
            }

            return 0;
        }
    }

    return 0;
}

function getPrevPickingOrderListId($current_picking_id)
{
    global $picking_ids;
    $picking_ids_total_array = explode(',', $picking_ids);

    $picking_articles_list = isset($_SESSION['picking_articles_list']) ? (array)$_SESSION['picking_articles_list'] : [];
    $picking_articles_list = array_keys($picking_articles_list);

    for ($step = 0; $step < count($picking_articles_list); ++$step)
    {
        if ($current_picking_id == $picking_articles_list[$step])
        {
            $from_index = $step == 0 ? count($picking_articles_list) - 1 : $step-1;
            for ($key = $from_index; $key >= 0; --$key)
            {
                $picking_ids = explode('.', $picking_articles_list[$key]);

                $in_array = 0;
                foreach ($picking_ids as $_id)
                {
                    if (in_array($_id, $picking_ids_total_array))
                    {
                        $in_array++;
                    }
                }

                if ($in_array < count($picking_ids))
                {
                    return $picking_articles_list[$key];
                }
            }

            if ($from_index != count($picking_articles_list) - 1)
            {
                for ($key = count($picking_articles_list) - 1; $key >= $from_index; --$key)
                {
                    $picking_ids = explode('.', $picking_articles_list[$key]);

                    $in_array = 0;
                    foreach ($picking_ids as $_id)
                    {
                        if (in_array($_id, $picking_ids_total_array))
                        {
                            $in_array++;
                        }
                    }

                    if ($in_array < count($picking_ids))
                    {
                        return $picking_articles_list[$key];
                    }
                }
            }

            return 0;
        }
    }

    return 0;
}

function deliverOldOrders($ramp_id)
{
    $dbr = \label\db::getInstance(\label\db::USAGE_READ);
    $db = \label\db::getInstance(\label\db::USAGE_WRITE);


    $ids = $dbr->getOne("SELECT group_concat(`id`)
        FROM `picking_order`
        WHERE `delivered` = '0'
            AND `ware_la_id` = '" . $ramp_id . "'");

    if ($ids) {
        $ids = explode(',', $ids);
        $ids = array_map('intval', $ids);

        foreach ($ids as $_key => $_id) {
            $_order_id = $dbr->getOne("SELECT `id` FROM `orders` WHERE `picking_order_id` = '" . $_id . "'");
            $_wwo_id = $dbr->getOne("SELECT `id` FROM `wwo_article` WHERE `picking_order_id` = '" . $_id . "'");

            if ( ! $_order_id && ! $_wwo_id)
            {
                $db->execParam('UPDATE `picking_order` SET `delivered` = 1 WHERE `id` = ?', [$_id]);
                unset($ids[$_key]);
            }
        }

        if ($ids)
        {
            $ids = implode(',', $ids);
            $_orders = $dbr->getAll("SELECT
                `o`.`picking_order_id`
                , `o`.`sent`
                , IFNULL(`ma`.`deleted`, `au`.`deleted`) AS `deleted`
                , `tn_orders`.`tn_id` AS `number`

                FROM `orders` AS `o` FORCE INDEX (picking_order_id)

                JOIN `auction` AS `au` ON `au`.`auction_number` = `o`.`auction_number`
                    AND `au`.`txnid` = `o`.`txnid`

                LEFT JOIN `auction` AS `ma` ON `au`.`main_auction_number` = `ma`.`auction_number`
                    AND `au`.`txnid` = `ma`.`txnid`

                LEFT JOIN `tn_orders` ON `tn_orders`.`order_id` = `o`.`id`

                WHERE `o`.`picking_order_id` IN (" . $ids . ")

                AND `o`.`manual` = '0'
                /*AND `o`.`hidden` = '0'*/");

            $_deleted_ids = [];
            $_not_deleted_ids = [];
            foreach ($_orders as $_order)
            {
                if ($_order->sent || $_order->deleted || $_order->number)
                {
                    $_deleted_ids[] = $_order->picking_order_id;
                }
                else
                {
                    $_not_deleted_ids[] = $_order->picking_order_id;
                }
            }

            $_wwos = $dbr->getAll("SELECT
                        `wwo_article`.`picking_order_id`
                        , `ww_order`.`deleted`
                        , `ww_order`.`closed`
                    FROM `wwo_article`
                    JOIN `ww_order` ON `wwo_article`.`wwo_id` = `ww_order`.`id`
                    WHERE `wwo_article`.`picking_order_id` IN (" . $ids . ")
                    ORDER BY `wwo_article`.`delivered_datetime` DESC");

            foreach ($_wwos as $_wwo)
            {
                if ($_wwo->deleted || $_wwo->closed)
                {
                    $_deleted_ids[] = $_wwo->picking_order_id;
                }
                else
                {
                    $_not_deleted_ids[] = $_wwo->picking_order_id;
                }
            }

            $_deleted_ids = array_values(array_unique($_deleted_ids));
            $_not_deleted_ids = array_values(array_unique($_not_deleted_ids));

            $_ids = array_diff($_deleted_ids, $_not_deleted_ids);
            if ($_ids)
            {
                $db->query("UPDATE `picking_order` SET `delivered` = 1 WHERE `id` IN (" . implode(',', $_ids) . ")");
            }
        }
    }
}

/**
 * @description convert file to pdf and save it
 * @param hash $data
 * @return string
 */
function convertAndSavePDF($data)
{
    $source = tempnam(__DIR__ . '/tmp', 'src');
    $dest = tempnam(__DIR__ . '/tmp', 'pdf');
    
    $source_esc = escapeshellarg($source);
    $dest_esc = escapeshellarg($dest);
    
    file_put_contents($source, $data);
    
    putenv('PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin');
    
    $command = "/usr/bin/unoconv -v --format=pdf --output={$dest_esc} {$source_esc}";
    `$command`;
    
    $pdf_data = file_get_contents($dest);
    $md5_pdf = md5($pdf_data);
    $filename_pdf = set_file_path($md5_pdf);
    if ( ! is_file($filename_pdf)) {
        file_put_contents($filename_pdf, $pdf_data);
    }
    
    unlink($source);
    unlink($dest);
    return $md5_pdf;
}
/**
 * @description add quotes to string, for example to implode method trough array_map
 * @param string $str
 * @return string
 */
function addQuotes($str) {
    return sprintf("'%s'", $str);
}

/**
 * Get color and status for username
 *
 * @param String $username
 * @return Object
 */
function get_status_color($username)
{
    $function = "get_statuses_colors($username)";
    $result = cacheGet($function, 0, '');
    if ($result) {
        return $result;
    }
    
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
    $username = mysql_real_escape_string($username);

    // Query for get status_id for vacation_sick if he exist
    $query_sick = "(SELECT `status_id` FROM `emp_vacation_sick`
        WHERE DATE(NOW()) BETWEEN `date_from` AND `date_to`
            AND `username` = '{$username}'
            AND (`direct_sv_applied` AND `main_sv_applied`) LIMIT 1)";

    // If status_id for vacation_sick not exist, get status login/logout
    $query_login = "(SELECT IF((SELECT `login` FROM `user_timestamp`
            WHERE `username` = '{$username}'
            ORDER BY `time` DESC LIMIT 1) = 1, '1', '2'))";

    // Combine statuses
    $query = "SELECT `config`, `title` FROM `timestamp_states`
            WHERE `id` = (SELECT IFNULL($query_sick, $query_login))";
    $status = $dbr->getAssoc($query);

    $color = array_shift(array_keys($status));

    $return = new stdClass;
    $return->status = ucfirst(str_replace('_', ' ', $status[$color]));
    $return->color = \Config::get(null, null, $color);

    cacheSet($function, 0, '', $return);
    return $return;
}

/**
 * Get color and status for all users
 * @return \stdClass
 */
function get_statuses_colors()
{
    $function = "get_statuses_colors()";
    $result = cacheGet($function, 0, '');
    if ($result) {
        return $result;
    }
    
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

    // Query for get status_id for vacation_sick if he exist
    $vac_sick = $dbr->getAssoc("SELECT `username`, `status_id` FROM `emp_vacation_sick`
        WHERE DATE(NOW()) BETWEEN `date_from` AND `date_to`
            AND (`direct_sv_applied` AND `main_sv_applied`)");

    $where = $vac_sick ? " AND `username` NOT IN (" . implode(',', array_map(function($v) {return "'" . mysql_real_escape_string($v) . "'";}, array_keys($vac_sick))) . ")" : '';
    $login = $dbr->getAssoc("SELECT * FROM
        (SELECT `username`, IF(`login` = 1, '1', '2') FROM `user_timestamp`
            WHERE `time` > DATE_ADD(CURRENT_TIMESTAMP, INTERVAL -7 DAY)
            " . $where . "
            ORDER BY `time` DESC
        ) `t` GROUP BY `username`");

    $statuses = $dbr->getAssoc("SELECT `id`, `config`, `title` FROM `timestamp_states`");

    $users = [];
    foreach ($vac_sick as $username => $status)
    {
        $status = $statuses[$status];

        if ($status)
        {
            $users[$username] = new stdClass;
            $users[$username]->status = ucfirst(str_replace('_', ' ', $status['title']));
            $users[$username]->color = \Config::get(null, null, $status['config']);
        }
    }

    foreach ($login as $username => $status)
    {
        $status = $statuses[$status];

        if ($status)
        {
            $users[$username] = new stdClass;
            $users[$username]->status = ucfirst(str_replace('_', ' ', $status['title']));
            $users[$username]->color = \Config::get(null, null, $status['config']);
        }
    }

    cacheSet($function, 0, '', $users, 600);
    return $users;
}

/**
 * Update action for user in warehouse
 *
 * @global type $loggedUser
 * @param type $action
 */
function update_user_warehouse_action($action)
{
    global $loggedUser;
    global $ramp_id;
    if ( ! $loggedUser || ! $loggedUser->get('timestamped_warehouse_id'))
    {
        return false;
    }

    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);

    $db->query('DELETE FROM `prologis_log`.`user_warehouse_action` WHERE `time` < DATE_ADD(CURRENT_TIMESTAMP, INTERVAL -15 MINUTE)');
    $db->query("DELETE FROM `prologis_log`.`user_warehouse_action`
            WHERE
                `username` = '" . mysql_real_escape_string($loggedUser->get('username')) . "'
                AND `warehouse_id` = '" . (int)$loggedUser->get('timestamped_warehouse_id') . "'"
            );

    $db->query("INSERT INTO `prologis_log`.`user_warehouse_action` (`username`, `warehouse_id`, `ware_la_id`, `action`)
            VALUES ('" . mysql_real_escape_string($loggedUser->get('username')) . "',
                    '" . (int)$loggedUser->get('timestamped_warehouse_id') . "',
                    " . ($ramp_id ? "'" . (int)$ramp_id . "'" : 'NULL') . ",
                    '" . mysql_real_escape_string($action) . "')"
            );

    return true;
}

/**
 * Check what $code - is valid PHP code (for use before `eval`)
 *
 * @param String $code
 * @return Boolean
 */
function is_validPHP($code) {
    $code = escapeshellarg('<?php ' . $code . ' ?>');
    $lint = `echo $code | php -l`; // command-line PHP

    // maybe there are other messages for good code?
    return (preg_match('/No syntax errors detected in -/', $lint));
}

/**
 * Save calculation values for route
 *
 * @param int $route_id
 */

function save_calculation_values($route_id){
    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
    $shipping_username = $dbr->getOne("SELECT a.shipping_username FROM auction a
                                           WHERE a.route_id = {$route_id} and a.deleted=0
                                           ORDER BY a.end_time DESC");
    $pars = array();
    $pars['username'] = $shipping_username;
    $pars['shipping_mode'] = 0;
    $pars['route_id'] = $route_id;
    $pars['ina_route_id'] = $route_id;
    $pars['route_unassigned'] = 0;
    $pars['country_shipping'] = 0;
    $pars['wares_res'] = 0;
    $route = $dbr->getRow("SELECT * FROM route WHERE id={$route_id}");
    $main_route_id = $route->main_route_id;

    if($main_route_id) $pars['main_route_id'] = $main_route_id;
    $results = Auction::findByShipUser($db, $dbr, $pars);

    $total_route_weight = 0;
    $total_route_volume = 0;
    foreach ($results as $ri => $au) {
        $total_route_weight += $au->weight;
        $total_route_volume += $au->volume;

        if ( ! $route_info) {
            $route_info = new stdClass;

            $route_info->driver_amt = $au->driver_amt;
            $route_info->weight_drv = $au->driver_amt * Config::get($db, $dbr, 'def_driver_weight');

            $route_info->weight_max = (float)$au->car_weight;
            $route_info->weight_max_corr = $route_info->weight_max * Config::get($db, $dbr, 'carweightCfactor');
            $route_info->weight_max_tr = (float)$au->trailer_weight;
            $route_info->weight_max_tr_corr = $route_info->weight_max_tr * Config::get($db, $dbr, 'carweightCfactor');
            $route_info->weight_total = $route_info->weight_max + $route_info->weight_max_tr;
            $route_info->weight_total_corr = $route_info->weight_total * Config::get($db, $dbr, 'carweightCfactor');
            $route_info->weight_ava = $route_info->weight_total - $route_info->weight_drv;
            $route_info->weight_ava_corr = $route_info->weight_total_corr - $route_info->weight_drv;

            $route_info->volume_max = (float)$au->car_volume;
            $route_info->volume_max_corr = $route_info->volume_max * Config::get($db, $dbr, 'carvolumeCfactor');
            $route_info->volume_max_tr = (float)$au->trailer_volume;
            $route_info->volume_max_tr_corr = $route_info->volume_max_tr * Config::get($db, $dbr, 'carvolumeCfactor');
            $route_info->volume_total = $route_info->volume_max + $route_info->volume_max_tr;
            $route_info->volume_total_corr = $route_info->volume_total * Config::get($db, $dbr, 'carvolumeCfactor');
            $route_info->volume_ava = $route_info->volume_total;
            $route_info->volume_ava_corr = $route_info->volume_total_corr;
        }

    }

    $storage = new stdClass;
    $storage->total_route_weight = round($total_route_weight,3);
    $storage->total_route_volume = round($total_route_volume,3);
    $storage->weight_drv = $route_info->weight_drv;

    $storage->car_max_weight = round($route_info->weight_max,3);
    $storage->car_max_weight_corr = round($route_info->weight_max_corr,3);
    $storage->trailer_max_weight = round($route_info->weight_max_tr,3);
    $storage->trailer_max_weight_corr = round($route_info->weight_max_tr_corr,3);
    $storage->total_max_weight = round($route_info->weight_total,3);
    $storage->total_max_weight_corr = round($route_info->weight_total_corr,3);
    $storage->available_max_weight = round($route_info->weight_ava,3);
    $storage->available_max_weight_corr = round($route_info->weight_ava_corr,3);

    $storage->car_max_volume = round($route_info->volume_max,3);
    $storage->car_max_volume_corr = round($route_info->volume_max_corr,3);
    $storage->trailer_max_volume = round($route_info->volume_max_tr,3);
    $storage->trailer_max_volume_corr = round($route_info->volume_max_tr_corr,3);
    $storage->total_max_volume = round($route_info->volume_total,3);
    $storage->total_max_volume_corr = round($route_info->volume_total_corr,3);
    $storage->available_max_volume = round($route_info->volume_ava,3);
    $storage->available_max_volume_corr = round($route_info->volume_ava_corr,3);

    $db->query("UPDATE route SET storage = '".serialize($storage)."' WHERE id = " . $route_id);
}

/**
 * Get timeframes of delivery hour
 *
 * @param $auction
 * @return array
 */
function get_auction_timeframes($auction){

    $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
    $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);

    if (is_a($auction, 'Auction')) {
        $shipping_order_time = $auction->data->shipping_order_time;
    }else{
        $shipping_order_time = $auction->shipping_order_time;
    }

    $config = \Config::getAll($db, $dbr);
    $timeframes_minus_hours = intval(trim($config['timeframes_minus_hours']));
    $timeframes_plus_hours = intval(trim($config['timeframes_plus_hours']));
    $shipping_order_time_min = date('H:i',strtotime("{$shipping_order_time} -{$timeframes_minus_hours} hours"));
    $shipping_order_time_max = date('H:i',strtotime("{$shipping_order_time} +{$timeframes_plus_hours} hours"));
    if(strtotime($config['timeframes_min_hours'])>strtotime($shipping_order_time_min)){
        $shipping_order_time_min = date('H:i',strtotime($config['timeframes_min_hours']));
    }

    return [$shipping_order_time_min,$shipping_order_time_max];
}

/**
 * http://php.net/manual/en/function.uniqid.php#94959
 * generate VALID RFC 4211 COMPLIANT Universally Unique IDentifiers (UUID) version 4.
 * @return type
 */
function gen_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        // 32 bits for "time_low"
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

        // 16 bits for "time_mid"
        mt_rand( 0, 0xffff ),

        // 16 bits for "time_hi_and_version",
        // four most significant bits holds version number 4
        mt_rand( 0, 0x0fff ) | 0x4000,

        // 16 bits, 8 bits for "clk_seq_hi_res",
        // 8 bits for "clk_seq_low",
        // two most significant bits holds zero and one for variant DCE1.1
        mt_rand( 0, 0x3fff ) | 0x8000,

        // 48 bits for "node"
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}