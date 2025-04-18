<?php

namespace label\Recache;

/**
 * Class RecacheSA
 * Class used to process recache and recreate export for all SA
 */
class RecacheSA
{
    /**
     * @var int
     */
    private $limitSA;

    /**
     * @var int[]
     */
    private $saIds;
    
    /**
     * Flag - use cache for SA
     * @var bool 
     */
    private $useCache = true;

    /**
     * Export only that sa ids
     * @param int[] $saIds
     */
    public function setSaIds($saIds)
    {
        $this->saIds = $saIds;
    }

    /**
     * Process recache and recreate export
     * @param object $item single row from saved_csv table
     * @return \Generator messages about current operation
     */
    public function recache($item)
    {
        require_once ROOT_DIR . '/plugins/function.imageurl.php';
        
        global $smarty;
        global $redis;
        global $db, $dbr;
        global $db_user, $db_pass, $db_host, $db_name;
        global $read_db_user, $read_db_pass, $read_db_host, $read_db_name;
        $imgsExcel = [];
        yield ['level' => 'main', 'message' => date('Y-m-d H:i:s')];
        yield ['level' => 'main', 'message' => 'Started id:' . $item->id];
        yield ['level' => 'debug', 'message' => 'Start:'];
        require_once ROOT_DIR . '/Spreadsheet/Excel/Writer.php';
        require_once ROOT_DIR . '/PHPExcel.php';
        $objPHPExcel = new \PHPExcel();
        $objPHPExcel->getProperties()->setTitle("export");
        $objPHPExcel->getActiveSheet()->setTitle('export');

        $mulang_fields = \Saved::$MULANG_FIELDS;
        $RicardoAvailabilityValue = $dbr->getAssoc("select `key`,`value` from list_value where par_name='AvailabilityValue'");
        $types = ['1' => 'Auktion', '2' => 'Fixed', '3' => 'Fixpreis-Auktion'];
        $langs = getLangsArray();

        yield ['level' => 'debug', 'message' => 'Langs: ' . print_r($langs, true)];

        $csv = [];
        $csv_comma = [];
        $csv_pipe = [];
        $txt = '';
        $where = '';
        $csv_a = [];
        $csv_comma_a = [];
        $csv_pipe_a = [];
        if (isset($this->saIds)) {
            $where .= 'AND sa.id IN (' . implode(', ', $this->saIds) . ')';
        }
        if (strlen($item->seller_username)) {
            $where .= "and si.username = '{$item->seller_username}' ";
        }
        if ((int)$item->seller_channel_id) {
            $where .= "and si.seller_channel_id = '{$item->seller_channel_id}'";
            $item->seller_channel = $dbr->getOne("select name from seller_channel where id=" . $item->seller_channel_id);
        }

        $shop_id = 0;
        $shop = null;
        if ((int)$item->shop_id) {
            $shop_id = (int)$item->shop_id;
            $shop = new \Shop_Catalogue($db, $dbr, $shop_id);
        }
        
        $shop_id = (int)$shop_id;

        $ss_list = $dbr->getAll("select * from source_seller where pp_show=1");
        $already_unsered = 0;
        if (strlen($item->date_from) && strlen($item->date_to)) {
            $sa_list = \Auction::findStarted($db, $dbr, $item->date_from, $item->date_to, $item->seller_username, $item->seller_channel);
            $already_unsered = 1;
        } else {
            $q = "select 
                    sa.id saved_id
                    , si.username
                    , si.seller_channel_id
                    , IFNULL(master_sa.id, sa.id) master_saved_id
				from saved_auctions sa
				left join saved_params sp_master_sa on sa.id = sp_master_sa.saved_id and sp_master_sa.par_key='master_sa'
				left join sa_all master_sa on sp_master_sa.par_value=master_sa.id
				join saved_params sp_username on sa.id = sp_username.saved_id and sp_username.par_key='username'
				join seller_information si on sp_username.par_value=si.username
				where 1 $where
				and sa.inactive=0 and sa.old=0 and sa.export
                group by sa.id";

            $sa_list = $dbr->getAll($q);
            if (\PEAR::isError($sa_list)) {
                yield ['level' => 'report', 'message' => "Error SA LIST#{$item->id} " . print_r($sa_list, true)];
                die('Error');
            }
        }

        // Mixin articles into $SA_LIST

        $sa_ids = [];
        foreach ($sa_list as $_sa) {
            $sa_ids[] = (int)$_sa->saved_id;
        }
        $sa_ids = array_values(array_unique($sa_ids));

        if ($sa_ids && $item->articles_from_main_groups) {
            $query = "SELECT `sp_offer`.`saved_id`, 
                            `a`.`article_id` AS `articleId`, `a`.`barcode_type` AS `articleType`, SUM(`o`.`quantity`) AS `articleSoldToDate`
                        FROM `saved_params` AS `sp_offer`
                            JOIN `offer_group` AS `og` ON `sp_offer`.`par_value` = `og`.`offer_id` AND `og`.`main` = 1
                            JOIN `article_list` AS `al` ON `al`.`group_id` = `og`.`offer_group_id`
                            JOIN `article` AS `a` ON `a`.`article_id` = `al`.`article_id` AND `a`.`admin_id` = 0 
                            LEFT JOIN `orders` AS `o` ON `o`.`article_id` = `a`.`article_id`
                            LEFT JOIN `auction` AS `au` ON `o`.`auction_number` = `au`.`auction_number` AND `o`.`txnid` = `au`.`txnid`
                        WHERE `sp_offer`.`saved_id` IN (" . implode(',', $sa_ids) . ") AND `sp_offer`.`par_key` = 'offer_id'
                            AND `au`.`deleted` = 0 AND `o`.`manual` = 0 AND `o`.`sent` = 1
                        GROUP BY `sp_offer`.`saved_id`, `a`.`article_id`, `a`.`barcode_type`";

            $mix_articles = $dbr->getAll($query);

            $articles_ids = [];
            foreach ($mix_articles as $_article) {
                if ($_article->articleId) {
                    $articles_ids[] = (int)$_article->articleId;
                }
            }
            $articles_ids = array_values(array_unique($articles_ids));

            $article_names = [];
            $article_parcels = [];
            $article_real_parcels = [];
            if ($articles_ids) {
                foreach ($dbr->getAll("SELECT `id`, `language`, `value` FROM `translation` WHERE `id` IN (" . implode(',', $articles_ids) . ")
                            AND `table_name` = 'article' AND `field_name` = 'name'") as $_name) {
                    $article_names[$_name->id]["articleName_{$_name->language}"] = $_name->value;
                }

                $_parcel = null;
                foreach ($dbr->getAll("SELECT * FROM `article_parcel` WHERE `article_id` IN (" . implode(',', $articles_ids) . ")") as $_parcel) {
                    $article_parcels[$_parcel->article_id][] = [
                        'bandmass' => (max($_parcel->dimension_l, $_parcel->dimension_w, $_parcel->dimension_h)
                                + 2 * ($_parcel->dimension_l + $_parcel->dimension_w + $_parcel->dimension_h
                                    - max($_parcel->dimension_l, $_parcel->dimension_w, $_parcel->dimension_h))) / 100,
                        'dimension' => $_parcel->dimension_l * $_parcel->dimension_h * $_parcel->dimension_w / 1000000,

                        'length' => $_parcel->dimension_l,
                        'width' => $_parcel->dimension_w,
                        'height' => $_parcel->dimension_h,
                        'weight' => $_parcel->weight_parcel,
                    ];
                }
                $_parcel = null;
                foreach ($dbr->getAll("SELECT * FROM `article_real_parcel` WHERE `article_id` IN (" . implode(',', $articles_ids) . ")") as $_parcel) {
                    $article_real_parcels[$_parcel->article_id][] = [
                        'bandmass' => (max($_parcel->dimension_l, $_parcel->dimension_w, $_parcel->dimension_h)
                                + 2 * ($_parcel->dimension_l + $_parcel->dimension_w + $_parcel->dimension_h
                                    - max($_parcel->dimension_l, $_parcel->dimension_w, $_parcel->dimension_h))) / 100,
                        'dimension' => $_parcel->dimension_l * $_parcel->dimension_h * $_parcel->dimension_w / 1000000,

                        'length' => $_parcel->dimension_l,
                        'width' => $_parcel->dimension_w,
                        'height' => $_parcel->dimension_h,
                        'weight' => $_parcel->weight_parcel,
                        'price' => $_parcel->price,
                    ];
                }
            }

            $mixin_sa_list = [];
            foreach ($mix_articles as $_article) {

                for ($key = 1; $key <= 20; $key++) {
                    $_article_parser = isset($article_parcels[$_article->articleId][$key - 1]) ? $article_parcels[$_article->articleId][$key - 1] : false;
                    if ($_article_parser) {
                        $_article->{"articleDimensionLength_$key"} = $_article_parser['length'];
                        $_article->{"articleDimensionWidth_$key"} = $_article_parser['width'];
                        $_article->{"articleDimensionHeight_$key"} = $_article_parser['height'];
                        $_article->{"articleDimensionWeight_$key"} = $_article_parser['weight'];
                        $_article->{"articleDimensionBandmass_$key"} = $_article_parser['bandmass'];
                    }
                    $_article_parser = isset($article_real_parcels[$_article->articleId][$key - 1]) ? $article_real_parcels[$_article->articleId][$key - 1] : false;

                    if ($_article_parser) {
                        $_article->{"articleDimensionLengthReal_$key"} = $_article_parser['length'];
                        $_article->{"articleDimensionWidthReal_$key"} = $_article_parser['width'];
                        $_article->{"articleDimensionHeightReal_$key"} = $_article_parser['height'];
                        $_article->{"articleDimensionWeightReal_$key"} = $_article_parser['weight'];
                        $_article->{"articleDimensionBandmassReal_$key"} = $_article_parser['bandmass'];
                        $_article->{"articlePriceReal_$key"} = $_article_parser['price'];
                    }
                }

                if (isset($article_names[$_article->articleId])) {
                    $_article = (object)array_merge((array)$_article, $article_names[$_article->articleId]);
                }

                foreach ($sa_list as $_sa_item) {
                    if ($_sa_item->saved_id == $_article->saved_id) {
                        $mixin_sa_list[] = (object)array_merge((array)$_sa_item, (array)$_article);
                    }
                }
            }

            if ($mixin_sa_list) {
                $sa_list = $mixin_sa_list;
            }
            $mixin_sa_list = null;
            $mix_articles = null;
        }

        $maxline = $dbr->getOne("select max(line) from saved_csv_field where sa_csv_id={$item->id}");
        $q = "select * from saved_csv_field where sa_csv_id={$item->id} order by pos";
        yield ['level' => 'debug', 'message' => $q];
        $fields = $dbr->getAll($q);
        yield ['level' => 'debug', 'message' => 'Fields: ' . print_r($fields, true)];
        $allfields = '';
        
        /**
         * Clear mandatory fields
         */
        $fields_ids = array_map(function($v) {return (int)$v->id;}, $fields);
        if ($fields_ids)
        {
            $db->query("DELETE FROM `saved_csv_errorlog` 
                    WHERE `sa_csv_field_id` IN (" . implode(',', $fields_ids) . ")");
        }

        foreach ($fields as $key => $field) {
            if ($field->hidden) {
                continue;
            }
            
            $allfields .= $field->field;
            if (!$item->no_header && $field->line == 1) {
                if ($item->encoding == 'utf-8') {
                    $csv[] = utf8_decode($field->title);
                    $csv_comma[] = utf8_decode($field->title);
                    $csv_pipe[] = utf8_decode($field->title);
                    $txt .= utf8_decode($field->title) . "	";
                } else {
                    $csv[] = $field->title;
                    $csv_comma[] = $field->title;
                    $csv_pipe[] = $field->title;
                    $txt .= $field->title . "	";
                }
                $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($key, 1, $field->title);
            }
        }
        
        $empty_fields = [];
        $fill_fields = [];
        
        $csv = implode(";", $csv);
        $csv_comma = implode(",", $csv_comma);
        $csv_pipe = implode('|', $csv_pipe);
        
        $txt = trim($txt);
        
        $image_numbers = [];
        $image_sizes = [];
        $dim_image_sizes = [];
        
        $all_fields = $dbr->getAll("select * from saved_csv_field");
        foreach ($all_fields as $key => $field) {
            if (stripos($field->field, 'shop_img_color_') !== false
                || stripos($field->field, 'shop_img_white_') !== false
                || stripos($field->field, 'shop_img_quad_') !== false
                || stripos($field->field, 'shop_img_main_color') !== false
                || stripos($field->field, 'shop_img_main_whitesh') !== false
                || stripos($field->field, 'shop_img_main_whitenosh') !== false
                || stripos($field->field, 'shop_img_details_color_') !== false
                || stripos($field->field, 'shop_img_details_whitesh_') !== false
                || stripos($field->field, 'shop_img_details_whitenosh_') !== false
                || stripos($field->field, 'shop_img_dimension_color_') !== false
                || stripos($field->field, 'shop_img_dimension_cm_color_') !== false
                || stripos($field->field, 'shop_img_dimension_inch_color_') !== false
                || stripos($field->field, 'shop_img_dimension_whitesh_') !== false
                || stripos($field->field, 'shop_img_dimension_cm_whitesh_') !== false
                || stripos($field->field, 'shop_img_dimension_inch_whitesh_') !== false
                || stripos($field->field, 'shop_img_dimension_whitenosh') !== false
                || stripos($field->field, 'shop_img_dimension_cm_whitenosh') !== false
                || stripos($field->field, 'shop_img_dimension_inch_whitenosh') !== false
            ) {
                $image_fields = explode(']]', $field->field);
                
                foreach ($image_fields as $image_field) {
                    $words = explode('_', $image_field);

                    if (strlen($words[3]) && is_numeric($words[3])) {
                        $image_numbers[$words[3]] = $words[3];
                        
                        if (strlen($words[4]) && is_numeric($words[4]) && (int)$words[4]) {
                            $image_sizes[$words[3]][$words[4]] = $words[4];
                            if (stripos($field->field, '_dim')) {
                                $dim_image_sizes[$words[3]][$words[4]] = $words[4];
                            }
                        }
                    } else {
                        if (strlen($words[4])) {
                            $image_numbers[$words[4]] = $words[4];
                            if ( ! isset($words[5]) && is_numeric($words[4])) {
                                $image_sizes[0][$words[4]] = $words[4];
                            }
                        }
                        if (strlen($words[5]) && (int)$words[5]) {
                            $image_sizes[$words[4]][$words[5]] = $words[5];
                            if (stripos($field->field, '_dim')) {
                                $dim_image_sizes[$words[4]][$words[5]] = $words[5];
                            }
                        }
                    }
                }
            } elseif (stripos($field->field, 'shop_img_') !== false) {
                $image_fields = explode(']]', $field->field);
                foreach ($image_fields as $image_field) {
                    $words = explode('_', $image_field);
                    if (strlen($words[2]) && is_numeric($words[2])) {
                        $image_numbers[$words[2]] = $words[2];
                        
                        if (strlen($words[3]) && is_numeric($words[3]) && (int)$words[3]) {
                            $image_sizes[$words[2]][$words[3]] = $words[3];
                        }
                    } else {
                        if (strlen($words[3])) {
                            $image_numbers[$words[3]] = $words[3];
                        }
                        if (strlen($words[4]) && (int)$words[4]) {
                            $image_sizes[$words[3]][$words[4]] = $words[4];
                        }
                    }
                }
            }
        }
        
        yield ['level' => 'debug', 'message' => 'Fields1: ' . print_r($fields, true)];
        yield ['level' => 'debug', 'message' => '$image_sizes: ' . print_r($image_sizes, true)];

        if (!$item->no_header) {
            $csv = trim($csv, ';');
            $csv .= "\r\n";
            $csv_comma .= "\n";
            $csv_pipe .= "\n";
            $txt .= "\n";
            $n = 0;
            $xls_n = 1;
            $xls_key_total = 2;

            $xml_root = $item->xml_root ? $item->xml_root : 'xml_root';
            $xml = '<?xml version="1.0" encoding="UTF-8" standalone="no" ?>' . "\n<$xml_root>\n{$item->xml_header}\n";
        } else {
            $n = -1;
            $xls_n = 1;
            $xls_key_total = 1;
        }
        yield ['level' => 'debug', 'message' => 'before list:'];
        $ii = 0;
        yield ['level' => 'main', 'message' => 'Found ' . count($sa_list) . ' SA.'];
        if (isset($this->limitSA)) {
            yield ['level' => 'main', 'message' => 'Limit ' . $this->limitSA];
        }
        
        foreach ($sa_list as $sa) {
            if (isset($this->limitSA)) {
                if ($ii === $this->limitSA) {
                    break;
                }
            }
            
            $r = $db->getOne("select count(*) from rma_problem");
            if (\PEAR::isError($r)) {
                yield ['level' => 'report', 'message' => "Lost connection, db relogin"];
                $db = dblogin($db_user, $db_pass, $db_host, $db_name);
            }
            $r = $dbr->getOne("select count(*) from rma_problem");
            if (\PEAR::isError($r)) {
                yield ['level' => 'report', 'message' => "Lost connection, dbr relogin"];
                $dbr = dblogin($read_db_user, $read_db_pass, $read_db_host, $read_db_name, 32);
            }
            if (!(int)$sa->saved_id) break;
            $need2process = false;

            yield ['level' => 'debug', 'message' => 'SA#' . $sa->saved_id];

            $function = "sa_csv_all({$sa->saved_id})";
            $chached_ret = false;
            
            if ($shop && $this->useCache) {
                $chached_ret = cacheGet($function, $shop->_shop->id, $shop->_shop->lang);
                yield ['level' => 'main', 'message' => "CACHED: " . count($chached_ret) . 
                    "; FUNCTION: {$function}" . 
                    "; SHOP: {$shop->_shop->id}" . 
                    "; LANG: {$shop->_shop->lang}" ];
            }
            yield ['level' => 'debug', 'message' => 'memcache for ' . $function . ' is ' . $chached_ret];

            // @todo Cache disabled, this can cause issues
            if ($chached_ret) {
                $details = $chached_ret;
            } else {
                yield ['level' => 'debug', 'message' => 'cannot get ' . $function . ' , have to calculate'];
                $need2process = true;
                yield ['level' => 'report', 'message' => date('Y-m-d H:i:s') . ' cannot get ' . $function . " have to calculate"];
            }
            yield ['level' => 'report', 'message' => date('Y-m-d H:i:s') . ' CSV: ' . $item->id . '; ' . ($ii++) . ': SA#' . $sa->saved_id];
            $mem = exec("ps aux|grep " . getmypid() . "|grep -v grep|awk {'print $6'}");
            yield ['level' => 'report', 'message' => "mem before =$mem"];
            yield ['level' => 'debug', 'message' => 'iteration start:'];

            if ($need2process) {
                yield ['level' => 'debug', 'message' => 'iteration start:'];
                if (!$already_unsered) {
                    $details = \Saved::getDetails($sa->master_saved_id);
                    $orig_details = \Saved::getDetails($sa->saved_id);

                    $alt_saved_id = $details['saved_id'];
                    $details['saved_id'] = $sa->saved_id;
                } else {
                    $details = \Saved::getDetails($sa->master_saved_id);
                    $orig_details = \Saved::getDetails($sa->saved_id);

                    $alt_saved_id = $details['saved_id'];
                    $details['multi_start_time'] = $sa->start_time;
                    $details['multi_end_time'] = $sa->end_time;
                }
                if (!$details) {
                    yield ['level' => 'debug', 'message' =>  'cannot unserialize SA#' . $sa->saved_id];
                    yield ['level' => 'debug', 'message' =>  print_r($sa->details, true)];
                    die($sa->details);
                } else {
                    yield ['level' => 'debug', 'message' => 'iteration parsed:'];
                    yield ['level' => 'debug', 'message' => 'SA#' . $sa->saved_id];
                }
                
                $details['articleId'] = isset($sa->articleId) ? $sa->articleId : '';
                $details['articleType'] = isset($sa->articleType) ? $sa->articleType : '';
                $details['articleSoldToDate'] = isset($sa->articleSoldToDate) ? $sa->articleSoldToDate : '';
                
                $details['active_articles'] = $dbr->getAll("SELECT GROUP_CONCAT(DISTINCT `article_list`.`article_id`)
						FROM `article_list`
						JOIN `offer_group` ON `article_list`.`group_id` = `offer_group`.`offer_group_id`
						WHERE `offer_group`.`offer_id` = '" . (int)$details['offer_id'] . "' 
                            AND NOT `article_list`.`inactive` 
                            AND NOT `offer_group`.`additional`
                            AND NOT `offer_group`.`base_group_id`");
                
                foreach ($langs as $lang => $dummy) {
                    $lang = strtolower($lang);
                    $details["articleName_$lang"] = isset($sa->{"articleName_$lang"}) ? $sa->{"articleName_$lang"} : '';
                    $details["articleName"][$lang] = isset($sa->{"articleName_$lang"}) ? $sa->{"articleName_$lang"} : '';
                }
                $details["articleName"] = implode(',', $details["articleName"]);

                for ($i = 1; $i <= 20; ++$i) {
                    $details["articleDimensionLength_$i"] = isset($sa->{"articleDimensionLength_$i"}) ? $sa->{"articleDimensionLength_$i"} : '';
                    $details["articleDimensionWidth_$i"] = isset($sa->{"articleDimensionWidth_$i"}) ? $sa->{"articleDimensionWidth_$i"} : '';
                    $details["articleDimensionHeight_$i"] = isset($sa->{"articleDimensionHeight_$i"}) ? $sa->{"articleDimensionHeight_$i"} : '';
                    $details["articleDimensionWeight_$i"] = isset($sa->{"articleDimensionWeight_$i"}) ? $sa->{"articleDimensionWeight_$i"} : '';
                    $details["articleDimensionBandmass_$i"] = isset($sa->{"articleDimensionBandmass_$i"}) ? $sa->{"articleDimensionBandmass_$i"} : '';
                    $details["articleDimensionLengthReal_$i"] = isset($sa->{"articleDimensionLengthReal_$i"}) ? $sa->{"articleDimensionLengthReal_$i"} : '';
                    $details["articleDimensionWidthReal_$i"] = isset($sa->{"articleDimensionWidthReal_$i"}) ? $sa->{"articleDimensionWidthReal_$i"} : '';
                    $details["articleDimensionHeightReal_$i"] = isset($sa->{"articleDimensionHeightReal_$i"}) ? $sa->{"articleDimensionHeightReal_$i"} : '';
                    $details["articleDimensionWeightReal_$i"] = isset($sa->{"articleDimensionWeightReal_$i"}) ? $sa->{"articleDimensionWeightReal_$i"} : '';
                    $details["articleDimensionBandmassReal_$i"] = isset($sa->{"articleDimensionBandmassReal_$i"}) ? $sa->{"articleDimensionBandmassReal_$i"} : '';
                    $details["articlePriceReal_$i"] = isset($sa->{"articlePriceReal_$i"}) ? $sa->{"articlePriceReal_$i"} : '';
                }

                $details['ShopPrice_master'] = $details['ShopPrice'];
                $details['ShopPrice'] = $orig_details['ShopPrice'];
                $details['ShopHPrice'] = $orig_details['ShopHPrice'];
                $details['shipping_cost_seller'] = $orig_details['shipping_cost_seller'];
                $details['total_cost_seller'] = $orig_details['total_cost_seller'];
                $details['shopAvailable'] = $orig_details['shopAvailable'];

                $sellerinfo = new \SellerInfo($db, $dbr, $details['username'], 'english');
                
                $stop_empty_warehouse = [];
                switch ($sa->seller_channel_id) {
                    case 1:
                        $_stock_warehouse_parkey = 'stop_empty_warehouse';
//                        $stop_empty_warehouse = $orig_details['stop_empty_warehouse'];
                        if ($details['fixedprice']) {
                            $shipping_plan_id_fn = 'f';
                        } else {
                            $shipping_plan_id_fn = '';
                        }
                        break;
                    case 2:
                        $_stock_warehouse_parkey = 'stop_empty_warehouse_ricardo';
//                        $stop_empty_warehouse = $orig_details['stop_empty_warehouse_ricardo'];
                        if ($details['Ricardo']['Channel'] == 2)
                            $shipping_plan_id_fn = 'f';
                        else
                            $shipping_plan_id_fn = '';
                        break;
                    case 3:
                        $_stock_warehouse_parkey = 'stop_empty_warehouse_amazon';
//                        $stop_empty_warehouse = $orig_details['stop_empty_warehouse_amazon'];
                        $shipping_plan_id_fn = '';
                        break;
                    case 4:
                        $_stock_warehouse_parkey = 'stop_empty_warehouse_shop';
//                        $stop_empty_warehouse = $orig_details['stop_empty_warehouse_shop'];
                        $shipping_plan_id_fn = 's';
                        break;
                    case 5:
                        $_stock_warehouse_parkey = 'stop_empty_warehouse_Allegro';
//                        $stop_empty_warehouse = $orig_details['stop_empty_warehouse_Allegro'];
                        $shipping_plan_id_fn = 'a';
                        break;
                }
                
                $stop_empty_warehouse = $dbr->getCol("select `par_value`
                    from `saved_params` where `saved_id` = '" . (int)$details['saved_id'] . "' and `par_key` like '{$_stock_warehouse_parkey}%'");

                yield ['level' => 'debug', 'message' => 'iteration pos 1.1:'];
                if (!$details['offer_id']) continue;
                $resMinStock = getMinStock($db, $dbr, (int)$details['saved_id'], (int)$orig_details['offer_id'], $stop_empty_warehouse, 4, $sellerinfo->get('warehouse_migration'));
                
                $minstock = $resMinStock['minstock'];
                $minava = $resMinStock['minava'];
                $minavas = $resMinStock['minavas'];
                $articles = $resMinStock['articles'];
                $weight = $resMinStock['weight'];
                $swarehouses = \Warehouse::listArray($db, $dbr);
                foreach ($swarehouses as $wid => $wname) {
                    $details['minavailable_' . strtolower(str_replace(':', '', str_replace(' ', '_', $wname)))] = $minavas[$wid];
                }
                $details['total_article_number'] = count($articles);
                if (!isset($details['total_carton_number'])) $details['total_carton_number'] = count($articles);

                for ($key = 0; $key < 20; $key++) {
                    $details["dimension_article_" . ($key + 1) . "_length_cm"] = '';
                    $details["dimension_article_" . ($key + 1) . "_width_cm"] = '';
                    $details["dimension_article_" . ($key + 1) . "_height_cm"] = '';
                    $details["dimension_article_" . ($key + 1) . "_weight_kg"] = '';
                    $details["dimension_article_" . ($key + 1) . "_shipping_art"] = '';
                    $details["dimension_article_real_" . ($key + 1) . "_length_cm"] = '';
                    $details["dimension_article_real_" . ($key + 1) . "_width_cm"] = '';
                    $details["dimension_article_real_" . ($key + 1) . "_height_cm"] = '';
                    $details["dimension_article_real_" . ($key + 1) . "_weight_kg"] = '';
                    $details["dimension_article_real_" . ($key + 1) . "_shipping_art"] = '';
                    $details["dimension_article_real_" . ($key + 1) . "_price"] = '';
                    $details["articleDimensionLength_" . ($key + 1)] = isset($sa->{"articleDimensionLength_" . ($key + 1)}) ? $sa->{"articleDimensionLength_" . ($key + 1)} : '';
                    $details["articleDimensionWidth_" . ($key + 1)] = isset($sa->{"articleDimensionWidth_" . ($key + 1)}) ? $sa->{"articleDimensionWidth_" . ($key + 1)} : '';
                    $details["articleDimensionHeight_" . ($key + 1)] = isset($sa->{"articleDimensionHeight_" . ($key + 1)}) ? $sa->{"articleDimensionHeight_" . ($key + 1)} : '';
                    $details["articleDimensionWeight_" . ($key + 1)] = isset($sa->{"articleDimensionWeight_" . ($key + 1)}) ? $sa->{"articleDimensionWeight_" . ($key + 1)} : '';
                    $details["articleDimensionBandmass_" . ($key + 1)] = isset($sa->{"articleDimensionBandmass_" . ($key + 1)}) ? $sa->{"articleDimensionBandmass_" . ($key + 1)} : '';
                    $details["articleDimensionLengthReal_" . ($key + 1)] = isset($sa->{"articleDimensionLengthReal_" . ($key + 1)}) ? $sa->{"articleDimensionLengthReal_" . ($key + 1)} : '';
                    $details["articleDimensionWidthReal_" . ($key + 1)] = isset($sa->{"articleDimensionWidthReal_" . ($key + 1)}) ? $sa->{"articleDimensionWidthReal_" . ($key + 1)} : '';
                    $details["articleDimensionHeightReal_" . ($key + 1)] = isset($sa->{"articleDimensionHeightReal_" . ($key + 1)}) ? $sa->{"articleDimensionHeightReal_" . ($key + 1)} : '';
                    $details["articleDimensionWeightReal_" . ($key + 1)] = isset($sa->{"articleDimensionWeightReal_" . ($key + 1)}) ? $sa->{"articleDimensionWeightReal_" . ($key + 1)} : '';
                    $details["articleDimensionBandmassReal_" . ($key + 1)] = isset($sa->{"articleDimensionBandmassReal_" . ($key + 1)}) ? $sa->{"articleDimensionBandmassReal_" . ($key + 1)} : '';
                    $details["articlePriceReal_" . ($key + 1)] = isset($sa->{"articlePriceReal_" . ($key + 1)}) ? $sa->{"articlePriceReal_" . ($key + 1)} : '';
                }
                yield ['level' => 'debug', 'message' => 'iteration pos 1.3:'];

                /******************************************************************************************************/

                $details['minstock'] = $minstock;
                $details['minavailable'] = $minava;
                $details['minavailable_set_various_warehouses'] = $resMinStock['minava_with_migration'];
                $details['minavailable_set_one_warehouse'] = $resMinStock['minava_without_migration'];
                $details['weight_kg'] = $weight;
                $details['weight_g'] = $weight * 1000;
                $parcels = $dbr->getAll("SELECT 0 forsa, IFNULL(sp.id, -ap.id) id, ap.`dimension_l`, ap.`dimension_h`, ap.`dimension_w`, ap.`weight_parcel`, IFNULL(sp.export,1) export
					, sp.shipping_art
						FROM article_list al 
						JOIN offer_group og ON al.group_id = og.offer_group_id and not base_group_id
						join article_parcel ap on ap.article_id=al.article_id
						left join saved_parcel sp on sp.saved_id=" . $sa->master_saved_id . " and sp.parcel_id=ap.id
						WHERE og.offer_id =" . (int)$details['offer_id'] . " and not al.inactive and not og.additional
						union 
						SELECT 1 forsa, sp.id, sp.`dimension_l`, sp.`dimension_h`, sp.`dimension_w`, sp.`weight_parcel`, sp.export
					, sp.shipping_art
						FROM saved_parcel sp 
						where sp.saved_id=" . $sa->master_saved_id . " and sp.parcel_id is null
						");
                yield ['level' => 'debug', 'message' => 'Parcels:'];
                yield ['level' => 'debug', 'message' => print_r($parcels, true)];
                $weight = 0;
                $volume_m3_carton = 0;
                $volume = 0;
                $parcel_key = 0;
                foreach ($parcels as $kp => $parcel) {
                    $parcels[$kp]->bandmass = (max($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h)
                            + 2 * ($parcel->dimension_l + $parcel->dimension_w + $parcel->dimension_h
                                - max($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h))) / 100;
                    $parcels[$kp]->dimension = $parcel->dimension_l * $parcel->dimension_h * $parcel->dimension_w / 1000000;
                    if (!strlen($parcels[$kp]->shipping_art)) {
                        if ($parcel->weight_parcel < 30
                            && max($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h)
                            + 2 * min($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h)
                            + ($parcel->dimension_l + $parcel->dimension_w + $parcel->dimension_h
                                - min($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h)
                                - max($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h))
                            < 360
                        ) {
                            $parcels[$kp]->shipping_art = 'Paketversand';
                        } elseif ($parcel->weight_parcel >= 30 && $parcel->weight_parcel < 35
                            && min($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h) <= 120
                            && max($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h) <= 320
                            && ($parcel->dimension_l + $parcel->dimension_w + $parcel->dimension_h
                                - min($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h)
                                - max($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h)) <= 225
                        ) {
                            $parcels[$kp]->shipping_art = 'DE-1Man-2Options';
                        } else {
                            $parcels[$kp]->shipping_art = 'De-2Man-2Options';
                        }
                    } // if no shipping art
                    if ($parcel->export) {
                        $weight += $parcel->weight_parcel;
                        $volume_m3_carton += ($parcel->dimension_l * $parcel->dimension_w * $parcel->dimension_h) / 1000000;
                        $parcel_key++;
                        $details["dimension_article_" . ($parcel_key) . "_bandmass_m"] = 1 * $parcels[$kp]->bandmass;
                        $details["dimension_article_" . ($parcel_key) . "_volume_m3"] = ($parcel->dimension_l * $parcel->dimension_w * $parcel->dimension_h) / 1000000;
                        $details["dimension_article_" . ($parcel_key) . "_length_cm"] = 1 * $parcel->dimension_l;
                        $details["dimension_article_" . ($parcel_key) . "_width_cm"] = 1 * $parcel->dimension_w;
                        $details["dimension_article_" . ($parcel_key) . "_height_cm"] = 1 * $parcel->dimension_h;
                        $details["dimension_article_" . ($parcel_key) . "_weight_kg"] = 1 * $parcel->weight_parcel;
                        $details["dimension_article_" . ($parcel_key) . "_shipping_art"] = $parcels[$kp]->shipping_art;
                    }
                    if (!$parcel->forsa) {
                        $volume += ($parcel->dimension_l * $parcel->dimension_w * $parcel->dimension_h) / 1000000;
                    }
                }
                $details['volume_m3'] = $volume;
                $details['weight_kg_carton'] = $weight;
                $details['weight_g_carton'] = $weight * 1000;
                $details['volume_m3_carton'] = $volume_m3_carton;

                /******************************************************************************************************/
                $real_parcels = $dbr->getAll("SELECT 0 forsa, IFNULL(sp.id, -ap.id) id, ap.`dimension_l`, ap.`dimension_h`, ap.`dimension_w`, ap.`weight_parcel`, ap.`price`, IFNULL(sp.export,1) export
					, sp.shipping_art
						FROM article_list al 
						JOIN offer_group og ON al.group_id = og.offer_group_id and not base_group_id
						join article_real_parcel ap on ap.article_id=al.article_id
						left join saved_real_parcel sp on sp.saved_id=" . $sa->master_saved_id . " and sp.parcel_id=ap.id
						WHERE og.offer_id =" . (int)$details['offer_id'] . " and not al.inactive and not og.additional
						union 
						SELECT 1 forsa, sp.id, sp.`dimension_l`, sp.`dimension_h`, sp.`dimension_w`, sp.`weight_parcel`, sp.`price`, sp.export
					, sp.shipping_art
						FROM saved_real_parcel sp 
						where sp.saved_id=" . $sa->master_saved_id . " and sp.parcel_id is null
						");
                yield ['level' => 'debug', 'message' => 'Real Parcels:'];
                yield ['level' => 'debug', 'message' => print_r($real_parcels, true)];
                $real_weight = 0;
                $real_volume_m3_carton = 0;
                $real_volume = 0;
                $real_parcel_key = 0;
                foreach ($real_parcels as $kp => $parcel) {
                    $real_parcels[$kp]->bandmass = (max($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h)
                            + 2 * ($parcel->dimension_l + $parcel->dimension_w + $parcel->dimension_h
                                - max($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h))) / 100;
                    $real_parcels[$kp]->dimension = $parcel->dimension_l * $parcel->dimension_h * $parcel->dimension_w / 1000000;
                    if (!strlen($real_parcels[$kp]->shipping_art)) {
                        if ($parcel->weight_parcel < 30
                            && max($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h)
                            + 2 * min($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h)
                            + ($parcel->dimension_l + $parcel->dimension_w + $parcel->dimension_h
                                - min($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h)
                                - max($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h))
                            < 360
                        ) {
                            $real_parcels[$kp]->shipping_art = 'Paketversand';
                        } elseif ($parcel->weight_parcel >= 30 && $parcel->weight_parcel < 35
                            && min($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h) <= 120
                            && max($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h) <= 320
                            && ($parcel->dimension_l + $parcel->dimension_w + $parcel->dimension_h
                                - min($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h)
                                - max($parcel->dimension_l, $parcel->dimension_w, $parcel->dimension_h)) <= 225
                        ) {
                            $real_parcels[$kp]->shipping_art = 'DE-1Man-2Options';
                        } else {
                            $real_parcels[$kp]->shipping_art = 'De-2Man-2Options';
                        }
                    } // if no shipping art

                    if ($parcel->export) {
                        $real_weight += $parcel->weight_parcel;
                        $real_volume_m3_carton += ($parcel->dimension_l * $parcel->dimension_w * $parcel->dimension_h) / 1000000;
                        $real_parcel_key++;
                        $details["dimension_article_real_" . ($real_parcel_key) . "_bandmass_m"] = 1 * $real_parcels[$kp]->bandmass;
                        $details["dimension_article_real_" . ($real_parcel_key) . "_volume_m3"] = ($parcel->dimension_l * $parcel->dimension_w * $parcel->dimension_h) / 1000000;
                        $details["dimension_article_real_" . ($real_parcel_key) . "_length_cm"] = 1 * $parcel->dimension_l;
                        $details["dimension_article_real_" . ($real_parcel_key) . "_width_cm"] = 1 * $parcel->dimension_w;
                        $details["dimension_article_real_" . ($real_parcel_key) . "_height_cm"] = 1 * $parcel->dimension_h;
                        $details["dimension_article_real_" . ($real_parcel_key) . "_weight_kg"] = 1 * $parcel->weight_parcel;
                        $details["dimension_article_real_" . ($real_parcel_key) . "_shipping_art"] = $real_parcels[$kp]->shipping_art;
                        $details["dimension_article_real_" . ($real_parcel_key) . "_price"] = 1 * $real_parcels[$kp]->price;
                    }
                    if (!$parcel->forsa) {
                        $real_volume += ($parcel->dimension_l * $parcel->dimension_w * $parcel->dimension_h) / 1000000;
                    }
                }

                yield ['level' => 'debug', 'message' => 'Real Parcels Details:'];
                yield ['level' => 'debug', 'message' => print_r($details, true)];

                $details['real_volume_m3'] = $real_volume;
                $details['real_weight_kg_carton'] = $real_weight;
                $details['real_weight_g_carton'] = $real_weight * 1000;
                $details['real_volume_m3_carton'] = $real_volume_m3_carton;

                /******************************************************************************************************/
                $sellerUsername = $sellerinfo->get('username');
                $shopPrice = isset($details[$sellerUsername]['BuyItNowPrice']) ? $details[$sellerUsername]['BuyItNowPrice'] : $details['ShopPrice'];
                
                $q = "
                    select CONCAT('COD_', spc.country_code), spc.COD_cost
                    from saved_auctions sa
                    join saved_params sp_offer on sa.id=sp_offer.saved_id and sp_offer.par_key='offer_id'
                    join offer o on sp_offer.par_value=o.offer_id 
                    join saved_params sp_username on sa.id=sp_username.saved_id and sp_username.par_key='username'
                    join saved_params sp_site on sa.id=sp_site.saved_id and sp_site.par_key='siteid'
                    join seller_information si on si.username=sp_username.par_value 
                    join translation 
                        on translation.language=sp_site.par_value
                        and translation.id=sp_offer.par_value
                        and translation.table_name='offer' 
                        and translation.field_name='{$shipping_plan_id_fn}shipping_plan_id'
                    join shipping_plan_country spc on spc.shipping_plan_id=translation.value
                    and spc.country_code = si.defshcountry
                    where sa.id=" . $sa->saved_id;
                foreach ($dbr->getAssoc($q) as $_k => $_v)
                {
                    $details[$_k] = $_v;
                }
                
//                print_r($dbr->getAssoc($q));
                
                $q = "select spc.shipping_cost,
                        IF((si.free_shipping AND si.free_shipping_above <= '{$shopPrice}') or si.free_shipping_total or IFNULL(t_o.value,0) or o.{$shipping_plan_id_fn}shipping_plan_free, 0, spc.shipping_cost) shipping_cost_new
						, IF(o.{$shipping_plan_id_fn}shipping_plan_free, 1, IFNULL(t_o.value,0)) shipping_plan_free
											from saved_auctions sa
							join saved_params sp_offer on sa.id=sp_offer.saved_id and sp_offer.par_key='offer_id'
							join offer o on sp_offer.par_value=o.offer_id 
							join saved_params sp_username on sa.id=sp_username.saved_id and sp_username.par_key='username'
							join saved_params sp_site on sa.id=sp_site.saved_id and sp_site.par_key='siteid'
							join seller_information si on si.username=sp_username.par_value 
					left join translation t_o
						on t_o.language=sp_site.par_value
						and t_o.id=sp_offer.par_value
						and t_o.table_name='offer' and t_o.field_name='sshipping_plan_free_tr'
											join translation 
												on translation.language=sp_site.par_value
												and translation.id=sp_offer.par_value
												and translation.table_name='offer' 
												and translation.field_name='{$shipping_plan_id_fn}shipping_plan_id'
											join shipping_plan_country spc on spc.shipping_plan_id=translation.value
											and spc.country_code = si.defshcountry
											where sa.id=" . $sa->saved_id;
                $shipping_cost_seller = $dbr->getRow($q);
                yield ['level' => 'debug', 'message' => 'iteration pos 1.4:'];

                $shipping_cost_seller = $shipping_cost_seller->shipping_cost_new;

                $saved_custom_params = $dbr->getAssoc("select t.par_key, (select scp.par_value from saved_custom_params scp 
					where scp.par_key=t.par_key and saved_id=" . $sa->master_saved_id . " and not inactive limit 1) par_value
					from (select distinct(par_key) par_key from saved_custom_params) t 
					order by par_key");

                yield ['level' => 'debug', 'message' => 'iteration pos 1.5:'];

                foreach ($saved_custom_params as $par_key => $par_value) {
                    $details[$par_key] = $par_value;
                }

                yield ['level' => 'debug', 'message' =>'iteration pos 2:'];

                $offer = new \Offer($db, $dbr, $details['offer_id']);
                $orig_offer = new \Offer($db, $dbr, $orig_details['offer_id']);
                if ($orig_offer->get('available')) {
                    $details['AvailableDate'] = '';
                } else {
                    if ((int)$orig_offer->get('available_weeks')) {
                        $details['AvailableDate'] = $dbr->getOne("select date_add(NOW(), INTERVAL " . (int)$orig_offer->get('available_weeks') . " week)");
                    } else {
                        $details['AvailableDate'] = $orig_offer->get('available_date');
                    }
                }
                yield ['level' => 'debug', 'message' => 'iteration pos 2.1 after offer:'];

                $details['ean_code_master'] = $details['ean_code'];
                $details['offer_name'] = $offer->get('name');
                $details['assembled'] = $offer->get('assembled');
                $details['assemble_mins'] = $offer->get('assemble_mins');
                yield ['level' => 'debug', 'message' => print_r($mulang_fields, true)];
                foreach ($mulang_fields as $details_key) {
                    yield ['level' => 'debug', 'message' => 'Try ' . $details_key];
                    $descriptionTextShop2_def_translations = [];
                    if ($details_key == 'descriptionTextShop2') {
                        $master_shop_id = 0;
                        if ((int)$details['master_shop']) {
                            $master_shop_id = (int)$details['master_shop'];
                        } else {
                            $master_shop_id = $dbr->getOne("select id from shop where username='" . $details['username'] . "'
                                    and siteid='" . (int)$details['siteid'] . "' order by id limit 1");
                        }
                        
                        if ($master_shop_id) {
                            $descriptionTextShop2_def_translations = [];
                            foreach ($langs as $lang => $dummy) {
                                $descriptionTextShop2_def_translations[$lang] = \Saved::getDescriptionTextShop2($sa->master_saved_id, $master_shop_id, $lang);
                            }
                        }
                    }

                    foreach ($langs as $lang_id => $dummy) {
                        $q = "select value 
								from translation where table_name='sa' and field_name='$details_key' 
								and id='{$sa->master_saved_id}' and language='$lang_id'";
                        yield ['level' => 'debug', 'message' => 'Process ' . $details_key];
                        if ($details_key == 'descriptionTextShop1' || $details_key == 'descriptionTextShop2' || $details_key == 'descriptionShop' || $details_key == 'descriptionShop1' || $details_key == 'descriptionShop2' || $details_key == 'descriptionShop3') {
                            yield ['level' => 'debug', 'message' => $details_key . '_' . $lang_id . ' REPLACED!!!!!!'];
                            $details[$details_key . '_' . $lang_id] = str_replace("\n", ' ', str_replace("\r", ' ', $dbr->getOne($q)));
                        } else {
                            $details[$details_key . '_' . $lang_id] = $dbr->getOne($q);
                        }

                        if ($details_key == 'descriptionTextShop2') {
                            if (!$details[$details_key . '_' . $lang_id] && $descriptionTextShop2_def_translations[$lang_id]) {
                                $details[$details_key . '_' . $lang_id] = $descriptionTextShop2_def_translations[$lang_id];
                            }
                        }
                    }

                    if ($details_key == 'ShopDesription') {
                        $ShopDesription = $dbr->getAssoc("select language, value from translation where table_name='sa' 
								and field_name='ShopDesription' and id=" . $sa->master_saved_id);
                        foreach ($langs as $lang_id => $dummy) {
                            $details[$details_key . '_' . $lang_id] = $dbr->getOne("select name from offer_name where id='" . (int)$details[$details_key . '_' . $lang_id] . "'");
                            if (!strlen($details[$details_key . '_' . $lang_id])) {
                                $details[$details_key . '_' . $lang_id] = $dbr->getOne("select name from offer_name where id='" . (int)$ShopDesription[$lang_id] . "'");
                            }
                        }
                    }
                }
                yield ['level' => 'debug', 'message' => 'iteration pos 3:'];

                foreach ($details['Ricardo'] as $kric => $r) {
                    $details['Ricardo' . $kric] = $r;
                }
                $details['RicardoChannelName'] = $types[$details['Ricardo']['Channel']];
                $q = "select offer_name.name, saved_gallery.gallery, offer_namefr.name namefr
					from saved_gallery_ricardo saved_gallery
					join offer_name on saved_gallery.name_id=offer_name.id
					left join offer_name offer_namefr on saved_gallery.name_idfr=offer_namefr.id
					where not IFNULL(saved_gallery.inactive,0) and saved_gallery.saved_id=" . $details['saved_id'];
                $galleries = $dbr->getRow($q);
                $details['RicardoTitleDe'] = $galleries->name;
                $details['RicardoTitleFr'] = $galleries->namefr == '' ? $galleries->name : $galleries->namefr;
                $details['RicardoGalleryURL'] = $galleries->gallery;
                $details['shipping_cost_seller'] = number_format($shipping_cost_seller, 2);
                $details['total_cost_seller'] = $shipping_cost_seller + $details['ShopPrice'];
                yield ['level' => 'debug', 'message' => 'iteration pos 4:'];

                if ($shop_id) {
                    foreach ($langs as $lang => $dummy) {
                        $q = "select distinct concat(IFNULL(t_suf,''), ' | ', IFNULL(price,''), ' | ', IFNULL(avail,''), ' | ', IFNULL(color,''), ' | ', IFNULL(rating,'')) descriptionTextShop2
							, avail
							from (
							select t_suf.value t_suf, CONCAT(sa.ShopPrice, ' ', config_api.value) price
							, CONCAT(t1.value, ' ', t2.value, IF(offer.available, '', IF(offer.available_weeks, date_add(NOW(), INTERVAL offer.available_weeks week), offer.available_date))) avail
							, (select CONCAT(t3.value, ': ', round(AVG(t.code),2),' / 5.00 ', t4.value, ' ', count(*), ' ', t3.value)
										from (
										select af.code
													from auction_feedback af
													join auction au on af.auction_number=au.auction_number and af.txnid=au.txnid
													left join auction subau on subau.main_auction_number=au.auction_number and subau.main_txnid=au.txnid
													where not au.hiderating and subau.saved_id=" . (int)$orig_details['saved_id'] . " and not af.hidden and au.txnid=3
										union all
										select rating
													from saved_custom_ratings scr
													join saved_params sp on sp.saved_id=scr.saved_id and sp.par_key='username'
													where 1 and scr.saved_id=" . (int)$orig_details['saved_id'] . " and not hidden) t) rating
							, t5.value assembled
							, CONCAT(t6.value,': ',t_color.value) color
							from sa" . $shop_id . " sa
							left join saved_params sp_color on sp_color.saved_id=sa.id and sp_color.par_key='color_id'
							left join translation t_color on t_color.table_name='sa_color' and t_color.field_name='name'
								and t_color.id=sp_color.par_value and t_color.language='$lang'
							join translation t_suf on t_suf.table_name='shop' and t_suf.field_name='title_suffix'
								and t_suf.id=$shop_id and t_suf.language='$lang'
							join config_api on config_api.par_id=7 and config_api.siteid=sa.siteid
							join translation t1 on t1.table_name='translate_shop' and t1.field_name='translate_shop'
								and t1.id='47' and t1.language='$lang'
							join offer on offer.offer_id=sa.offer_id
							join translation t2 on t2.table_name='translate_shop' and t2.field_name='translate_shop'
								and t2.id=(57-offer.available)  and t2.language='$lang'
							join translation t3 on t3.table_name='translate_shop' and t3.field_name='translate_shop'
								and t3.id='116' and t3.language='$lang'
							join translation t4 on t4.table_name='translate_shop' and t4.field_name='translate_shop'
								and t4.id='115' and t4.language='$lang'
							left join translation t5 on t5.table_name='translate' and t5.field_name='translate'
								and t5.id=offer.assembled and t5.language='$lang'
							left join translation t6 on t6.table_name='translate' and t6.field_name='translate'
							and t6.id='226' and t6.language='$lang'
						where sa.id=" . (int)$orig_details['saved_id'] . " ) t limit 1
						";

                        $rrr = $dbr->getRow($q);

                        yield ['level' => 'debug', 'message' => 'iteration pos 4-0-1:'];

                        $details['avail_' . $lang] = $rrr->avail;
                    };

                    $q = "select sp.par_value
							from saved_params sp 
							join shop_catalogue sc on sp.par_value=sc.id
							join shop_catalogue_shop spc on sc.id=spc.shop_catalogue_id and spc.shop_id=$shop_id
							where sp.par_key = 'shop_catalogue_id[$shop_id]'
							and saved_id=" . (int)$orig_details['saved_id'] . " and spc.hidden=0
							order by sp.id desc limit 1";
                    $cat_id = $dbr->getOne($q);

                    yield ['level' => 'debug', 'message' => 'iteration pos 4-0-2:'];

                    $details['catalogue_id'] = $cat_id;
                    $q = "select t.par_key, (select scp.par_value from shop_cat_custom_params scp 
						join saved_shop_cat_custom_params ssccp on ssccp.shop_catalogue_id=scp.shop_catalogue_id 
							and ssccp.par_key=scp.par_key
						where ssccp.par_key=t.par_key and ssccp.shop_id=$shop_id and ssccp.saved_id=" . (int)$details['saved_id'] . " 
						order by IF(ssccp.shop_id=scp.shop_id,0,1) limit 1) par_value
						from (select distinct(par_key) par_key from shop_cat_custom_params) t 
						order by par_key";
                    $shop_cat_custom_params = $dbr->getAssoc($q);
                    yield ['level' => 'debug', 'message' => $q];
                    yield ['level' => 'debug', 'message' => 'iteration pos 4-0-3:'];
                    foreach ($shop_cat_custom_params as $par_key => $par_value) {
                        $details[$par_key] = $par_value;
                    }
                    
                    $details['test_shop_cat_custom_params'] = $q;
                    $details['test_cat_main_de'] = isset($shop_cat_custom_params['cat_main_de']) ? $shop_cat_custom_params['cat_main_de'] : 'FUU';
                    
                    $route = $shop->getAllNodes($cat_id);
                    yield ['level' => 'debug', 'message' => 'iteration pos 4-0-4:'];
                    $route = array_reverse($route);
                    // ------- PARS
                    $q = "select distinct sn.* from Shop_Name_Cat snc
						join Shop_Names sn on sn.id=snc.NameID and IFNULL(sn.def_value,'')=''";

                    $allpars = $dbr->getAll($q);
                    foreach ($allpars as $par_key => $par) {
                        for ($i = 0; $i <= 10; $i++) {
                            foreach ($langs as $lang => $dummy) {
                                $details['shop_par_name_' . str_replace(' ', '_', $par->Name) . '_' . $lang . '_' . $i] = '';
                            }
                            $details['shop_par_name_' . str_replace(' ', '_', $par->Name) . '_' . $i] = '';
                        }
                    }
                    $q = "select REPLACE(REPLACE(par_key,'shop_catalogue_id[',''),']','') shop_id, par_value shop_cataloue_id
						from saved_params sp
						where saved_id=" . $sa->master_saved_id . " and par_key like 'shop_catalogue_id[%]'";
                    yield ['level' => 'debug', 'message' => $q];
                    $cats = $dbr->getAll($q);
                    $cats_conds = 0;
                    foreach ($cats as $cat) $cats_conds .= " or (shop_id={$cat->shop_id} and shop_catalogue_id={$cat->shop_cataloue_id}) ";
                    $q = "select distinct sn.* from Shop_Name_Cat snc
						join Shop_Names sn on sn.id=snc.NameID and IFNULL(sn.def_value,'')=''
						where $cats_conds";

                    yield ['level' => 'debug', 'message' => $q];
                    $pars = $dbr->getAll($q);
                    
                    $par_names = [];
                    foreach ($pars as $par_key => $par) {
                        $par_names[$par->id] = $par->Name;
                        
                        if ($par->translatable) {
                            foreach ($langs as $lang => $dummy) {
                                switch ($par->ValueType) {
                                    case 'text':
                                        $q = "select spv.*
											, IFNULL(t.value, spv.FreeValueText) as value
											from saved_parvalues spv
											left join Shop_Values sv on sv.id=spv.ValueID
											left join translation t on t.table_name='Shop_Values' and t.field_name='ValueText'
												and t.language='{$lang}' and t.id=sv.id
											where spv.saved_id=" . $sa->master_saved_id . " and spv.NameID={$par->id}
											and sv.inactive=0
											order by sv.ordering";
                                        break;
                                    case 'dec':
                                        $q = "select spv.*
											, IFNULL(t.value, spv.FreeValueDec) as value
											from saved_parvalues spv
											left join Shop_Values sv on sv.id=spv.ValueID
											left join translation t on t.table_name='Shop_Values' and t.field_name='ValueDec'
												and t.language='{$lang}' and t.id=sv.id
											where spv.saved_id=" . $sa->master_saved_id . " and spv.NameID={$par->id}
											and sv.inactive=0
											order by sv.ordering";
                                        break;
                                    case 'img':
                                        $q = "select spv.*
											, t.value as value
											from saved_parvalues spv
											left join Shop_Values sv on sv.id=spv.ValueID
											left join translation t on t.table_name='Shop_Values' and t.field_name='ValueText'
												and t.language='{$lang}' and t.id=sv.id
											where spv.saved_id=" . $sa->master_saved_id . " and spv.NameID={$par->id}
											and sv.inactive=0
											order by spv.id";
                                        break;
                                }
                                yield ['level' => 'debug', 'message' => $q];
                                $values = $dbr->getAll($q);
                                foreach ($values as $i => $value) {
                                    $details['shop_par_name_' . str_replace(' ', '_', $par->Name) . '_' . $lang . '_' . $i] = $value->value;
                                } // foreach value
                            } // foreach lang
                        } else {
                            switch ($par->ValueType) {
                                case 'text':
                                    $qvalue = "IFNULL(sv.ValueText, spv.FreeValueText)";
                                    break;
                                case 'dec':
                                    $qvalue = "IFNULL(sv.ValueDec, spv.FreeValueDec)";
                                    break;
                                case 'img':
                                    $qvalue = "sv.id";
                                    break;
                            }
                            $q = "select spv.*
								, $qvalue as value
								from saved_parvalues spv
								left join Shop_Values sv on sv.id=spv.ValueID
								where saved_id=" . $sa->master_saved_id . " and spv.NameID=$par->id
										and sv.inactive=0
										order by sv.ordering";
                            yield ['level' => 'debug', 'message' => $q];
                            $values = $dbr->getAll($q);
                            foreach ($values as $i => $value) {
                                $details['shop_par_name_' . str_replace(' ', '_', $par->Name) . '_' . $i] = $value->value;
                            } // foreach value
                        } // if not translatable
                    } // foreach par

                    $par_counter = [0 => 1];
                    foreach ($par_names as $_id => $dummy) {
                        $par_counter[$_id] = 1;
                    }
                    
                    for ($i = 0; $i <= 10; $i++) {
                        $_num = $i + 1;
                        
                        $details['other' . $_num] = '';
                        $details['similar' . $_num] = '';
                        $details['similar_sa_url_' . $_num] = '';
                        $details['similar_sa_id_' . $_num] = '';
                        $details['sa_similar_products_' . $_num] = '';
                        $details['other_sa_url_' . $_num] = '';
                        $details['other_sa_id_' . $_num] = '';
                        $details['sa_other_products_' . $_num] = '';
                        
                        foreach ($par_names as $_name) {
                            $details['sa_other_products_' . $_name . '_' . $_num] = '';
                        }
                        $details['sa_other_products_not_selected_' . $_num] = '';
                    }
                    $sims = $dbr->getAll("select distinct ss.* from saved_sim ss
                        join sa" . $shop_id . " sa on sa.id=ss.sim_saved_id
						where ss.saved_id={$sa->saved_id} and ss.inactive=0
						order by ss.ordering");
                    foreach ($sims as $i => $sim) {
                        $_num = $i + 1;
                        
                        $sim_obj = $shop->getOffer($sim->sim_saved_id);
                        $details['similar' . $_num] = 'http' . ($shop->_shop->ssl ? 's' : '') . '://www.' . $shop->_shop->url . '/' . $sim_obj->ShopSAAlias . '.html';
                        $details['similar_sa_url_' . $_num] = 'http' . ($shop->_shop->ssl ? 's' : '') . '://www.' . $shop->_shop->url . '/' . $sim_obj->ShopSAAlias . '.html';
                        $details['sa_similar_products_' . $_num] = 'http' . ($shop->_shop->ssl ? 's' : '') . '://www.' . $shop->_shop->url . '/' . $sim_obj->ShopSAAlias . '.html';
                        $details['similar_sa_id_' . $_num] = $sim->sim_saved_id;
                    }
                    $others = $dbr->getAll("select so.*
						from saved_other so
                        join sa" . $shop_id . " sa on sa.id=so.other_saved_id
						where so.inactive=0 and so.saved_id={$sa->saved_id}
						group by so.NameID
						order by so.ordering");
                    
                    foreach ($others as $i => $other) {
                        $_num = $i + 1;
                        
                        $other_obj = $shop->getOffer($other->other_saved_id);
                        $details['other' . $_num] = 'http' . ($shop->_shop->ssl ? 's' : '') . '://www.' . $shop->_shop->url . '/' . $other_obj->ShopSAAlias . '.html';
                        $details['other_sa_url_' . $_num] = 'http' . ($shop->_shop->ssl ? 's' : '') . '://www.' . $shop->_shop->url . '/' . $other_obj->ShopSAAlias . '.html';
                        $details['sa_other_products_' . $_num] = 'http' . ($shop->_shop->ssl ? 's' : '') . '://www.' . $shop->_shop->url . '/' . $other_obj->ShopSAAlias . '.html';
                        $details['other_sa_id_' . $_num] = $other->other_saved_id;
                        
                        if ( ! $other->NameID) {
                            $details['sa_other_products_not_selected_' . $par_counter[0]] = 'http' . ($shop->_shop->ssl ? 's' : '') . '://www.' . $shop->_shop->url . '/' . $other_obj->ShopSAAlias . '.html';
                            $par_counter[0]++;
                        } else if (isset($par_names[$other->NameID])) {
                            $details['sa_other_products_' . $par_names[$other->NameID] . '_' . $par_counter[$other->NameID]] = 'http' . ($shop->_shop->ssl ? 's' : '') . '://www.' . $shop->_shop->url . '/' . $other_obj->ShopSAAlias . '.html';
                            $par_names[$other->NameID]++;
                        }
                    }
                } // if SHOP
                
                yield ['level' => 'debug', 'message' => 'iteration pos 4-1:'];
                
                $shop_url = 'http' . ($shop->_shop->ssl ? 's' : '') . '://www.' . $shop->_shop->url;

                $docs = \Saved::getDocs($db, $dbr, $alt_saved_id, '', '', '', 0, 1, 1);
                
                if ($shop->_shop->new_image) {
                    $saved_pic = new \SavedPic($alt_saved_id);
                    $pics = array_values($saved_pic->get(true));
                } else {
                    $pics = \Saved::getDocs($db, $dbr, $alt_saved_id, ' and inactive=0 ', '', '', 0, 0, 1);
                    foreach ($pics as $npic => $pic) {
                        $datasize = 1 * $dbr->getOne("select length(value) from prologis_log.translation_files 
                            where table_name='saved_doc' and field_name='data' and id='{$pic->doc_id}'");
                        if (!$datasize) unset($pics[$npic]);
                    }
                }
                
                foreach ($langs as $lang_id => $dummy) {
                    for ($ki = 0; $ki < 10; $ki++) {
                        $details['shop_video' . '_' . $lang_id . '_' . $ki] = '';
                    }
                }
                
                $yt_npic = [];
                
                $youtube = \Saved::getDocs($db, $dbr, $alt_saved_id, ' and inactive=0 ', '', '', 0, 0, 1);
                foreach ($youtube as $pic) {
                    $youtube_code = $dbr->getAssoc("SELECT `language`, `value` FROM `translation` WHERE `id` = '{$pic->doc_id}'
                           AND `table_name` = 'saved_doc' AND `field_name` = 'youtube_code'");
                    
                    foreach ($youtube_code as $_lang => $_code)
                    {
                        if ($_code)
                        {
                            $yt_npic[$_lang] = isset($yt_npic[$_lang]) ? $yt_npic[$_lang] : 0;
                            $details['shop_video' . '_' . $_lang . '_' . $yt_npic[$_lang]] = $_code;
                            $yt_npic[$_lang]++;
                        }
                    }
                }
                
                foreach ($langs as $lang_id => $dummy) {
                    yield ['level' => 'debug', 'message' => 'iteration pos 4-2:'];

                    foreach ($docs as $kd => $doc) {
                        $details['doc' . '_' . $lang_id . '_' . $kd] = "http://www.prologistics.info/doc.php?saved_id={$alt_saved_id}&doc_id={$doc->doc_id}&lang={$lang_id}";
                    }
                    
                    if ($shop_id) {
                        $cat_route = '/';
                        $cats_array = [];
                        foreach ($route as $cat) {
                            if ($cat) {
                                $cat_route .= $dbr->getOne("
									SELECT `value`
									FROM translation
									WHERE table_name = 'shop_catalogue'
									AND field_name = 'alias'
									AND language = '{$lang_id}'
									AND id = " . $cat . "
									") . '/';
                                $cats_array[] = $dbr->getOne("
									SELECT `value`
									FROM translation
									WHERE table_name = 'shop_catalogue'
									AND field_name = 'name'
									AND language = '{$lang_id}'
									AND id = " . $cat . "
									");
                            }
                        }
                        $q = "SELECT translation.`value`
							FROM translation
							WHERE 1
							AND translation.table_name = 'sa'
							AND translation.field_name = 'ShopSAAlias'
							AND translation.language = '" . $lang_id . "'
							AND translation.id = '" . $sa->master_saved_id . "'
							limit 1";
                        $item_alias = $dbr->getOne($q);
                        if (\PEAR::isError($item_alias)) {
                            print_r($item_alias);
                            die();
                        }
                        $details['shop_url' . '_' . $lang_id] = 'http' . ($shop->_shop->ssl ? 's' : '') . '://www.' . $shop->_shop->url . '/' . $item_alias . '.html';
                        $details['shopAvailable' . '_' . $lang_id] = $dbr->getOne("select `value` from translation	
							where table_name='translate_shop'
							and field_name='translate_shop'
							and id = '" . ($details['shopAvailable'] ? 56 : 57) . "'
							and language='" . $lang_id . "'");
                        for ($ki = 0; $ki < 10; $ki++) {
                            $details['doc' . '_' . $lang_id . '_' . $ki] = '';
//                            $details['shop_video' . '_' . $lang_id . '_' . $ki] = '';
                            $details['catalogue' . '_' . $lang_id . '_' . $ki] = $cats_array[$ki];
                            $details['catalogue_id' . '_' . $ki] = $route[$ki + 1];
                            $details['shop_img_' . $lang_id . '_' . $ki] = "";
                            $details['shop_img_color_' . $lang_id . '_' . $ki] = "";
                            $details['shop_img_quad_' . $lang_id . '_' . $ki] = "";
                            $details['shop_img_white_' . $lang_id . '_' . $ki] = "";
                            $details['shop_img_' . $lang_id . '_' . $ki . '_png'] = "";
                            $details['shop_img_color_' . $lang_id . '_' . $ki . '_png'] = "";
                            $details['shop_img_quad_' . $lang_id . '_' . $ki . '_png'] = "";
                            $details['shop_img_white_' . $lang_id . '_' . $ki . '_png'] = "";
                            $details['shop_img_color_' . $lang_id . '_' . $ki . '_withlogo'] = "";
                            if (isset($image_sizes[$ki])) {
                                foreach ($image_sizes[$ki] as $size) {
                                    $details['shop_img_' . $lang_id . '_' . $ki . '_' . $size] = "";
                                    $details['shop_img_color_' . $lang_id . '_' . $ki . '_' . $size] = "";
                                    $details['shop_img_quad_' . $lang_id . '_' . $ki . '_' . $size] = "";
                                    $details['shop_img_white_' . $lang_id . '_' . $ki . '_' . $size] = "";
                                    $details['shop_img_' . $lang_id . '_' . $ki . '_' . $size . '_png'] = "";
                                    $details['shop_img_color_' . $lang_id . '_' . $ki . '_' . $size . '_png'] = "";
                                    $details['shop_img_quad_' . $lang_id . '_' . $ki . '_' . $size . '_png'] = "";
                                    $details['shop_img_white_' . $lang_id . '_' . $ki . '_' . $size . '_png'] = "";
                                    $details['shop_img_color_' . $lang_id . '_' . $ki . '_' . $size . '_withlogo'] = "";
                                } // foreach image size
                            } // if we have diff sizes
                        } // for 10 items
                    } // if shop
                    
                    for ($ki = 0; $ki < 10; $ki++) {
                        $details['doc' . '_' . $lang_id . '_' . $ki] = '';
                    } // for 10 items

                    $white_npic = 0;
                    $quad_npic = 0;
                    $color_npic = 0;
                    
                    $dim_npic = 1;
                    $dim_white_npic = 1;
                    $dim_quad_npic = 1;
                    $dim_color_npic = 1;

//                    foreach ($youtube as $pic) {
//                        $youtube_code = $dbr->getOne("select `value` from translation where id='{$pic->doc_id}'
//						       and table_name='saved_doc' and field_name='youtube_code' and language='$lang_id'");
//                        if (strlen($youtube_code)) {
//                            $details['shop_video' . '_' . $lang_id . '_' . $yt_npic] = $youtube_code;
//                            $yt_npic++;
//                            continue;
//                        }
//                    }
                    
                    foreach ($pics as $npic => $pic) {
                        if ( ! $pic->doc_id) {
                            continue;
                        }
                        
                        $details['shop_img' . '_' . $lang_id . '_' . $npic] = $shop_url . smarty_function_imageurl([
                                'lang_id' => $lang_id,
                                'src' => 'saved',
                                'saved_id' => $sa->saved_id,
                                'picid' => $pic->doc_id,
                                'nochange' => 1], $smarty);
                        $details['shop_img' . '_' . $lang_id . '_' . $npic . '_png'] = $shop_url . smarty_function_imageurl([
                                'lang_id' => $lang_id,
                                'src' => 'saved',
                                'saved_id' => $sa->saved_id,
                                'picid' => $pic->doc_id,
                                'nochange' => 1,
                                'convert' => 1], $smarty);
                        $details['shop_img' . '_' . $lang_id . '_' . $npic . '_withlogo'] = $shop_url . smarty_function_imageurl([
                                'lang_id' => $lang_id,
                                'src' => 'saved',
                                'saved_id' => $sa->saved_id,
                                'picid' => $pic->doc_id,
                                'nochange' => 1,
                                'addlogo' => 'logo'], $smarty);
                        if (isset($image_sizes[$npic])) {
                            foreach ($image_sizes[$npic] as $size) {
                                $details['shop_img' . '_' . $lang_id . '_' . $npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                        'lang_id' => $lang_id,
                                        'src' => 'saved',
                                        'saved_id' => $sa->saved_id,
                                        'picid' => $pic->doc_id,
                                        'x' => $size,
                                        'nochange' => 1,
                                        'addlogo' => 'logo'], $smarty);
                                $details['shop_img' . '_' . $lang_id . '_' . $npic . '_' . $size . '_png'] = $shop_url . smarty_function_imageurl([
                                        'lang_id' => $lang_id,
                                        'src' => 'saved',
                                        'saved_id' => $sa->saved_id,
                                        'picid' => $pic->doc_id,
                                        'x' => $size,
                                        'nochange' => 1,
                                        'convert' => 1], $smarty);
                                $details['shop_img' . '_' . $lang_id . '_' . $npic . '_' . $size . '_withlogo'] = $shop_url . smarty_function_imageurl([
                                        'lang_id' => $lang_id,
                                        'src' => 'saved',
                                        'saved_id' => $sa->saved_id,
                                        'picid' => $pic->doc_id,
                                        'x' => $size,
                                        'nochange' => 1,
                                        'addlogo' => 'logo'], $smarty);
                            }
                        }

                        if ($pic->dimensions) {
                            $details['shop_img' . '_' . $lang_id . '_' . $dim_npic . '_dim'] = $shop_url . smarty_function_imageurl([
                                    'lang_id' => $lang_id,
                                    'src' => 'saved',
                                    'saved_id' => $sa->saved_id,
                                    'picid' => $pic->doc_id,
                                    'nochange' => 1], $smarty);
                            foreach ($dim_image_sizes[$dim_npic] as $size) {
                                $details['shop_img' . '_' . $lang_id . '_' . $dim_npic . '_' . $size . '_dim'] = $shop_url . smarty_function_imageurl([
                                        'lang_id' => $lang_id,
                                        'src' => 'saved',
                                        'saved_id' => $sa->saved_id,
                                        'picid' => $pic->doc_id,
                                        'x' => $size,
                                        'nochange' => 1], $smarty);
                            }

                            $dim_npic++;
                        }

                        if ($shop->_shop->new_image) {
                            $details['shop_img_color_' . $lang_id . '_' . $color_npic] = $shop_url . smarty_function_imageurl([
                                    'lang_id' => $lang_id,
                                    'src' => 'saved',
                                    'saved_id' => $sa->saved_id,
                                    'picid' => $pic->doc_id,
                                    'nochange' => 1], $smarty);

                            $details['shop_img_color_' . $lang_id . '_' . $color_npic . '_png'] = $shop_url . smarty_function_imageurl([
                                    'lang_id' => $lang_id,
                                    'src' => 'saved',
                                    'saved_id' => $sa->saved_id,
                                    'picid' => $pic->doc_id,
                                    'nochange' => 1,
                                    'convert' => 1], $smarty);

                            $details['shop_img_color_' . $lang_id . '_' . $color_npic . '_withlogo'] = $shop_url . smarty_function_imageurl([
                                    'lang_id' => $lang_id,
                                    'src' => 'saved',
                                    'saved_id' => $sa->saved_id,
                                    'picid' => $pic->doc_id,
                                    'nochange' => 1,
                                    'addlogo' => 'logo'], $smarty);

                            if ($pic->dimensions) {
                                $details['shop_img_color_' . $lang_id . '_' . $dim_color_npic . '_dim'] = $shop_url . smarty_function_imageurl([
                                        'lang_id' => $lang_id,
                                        'src' => 'saved',
                                        'saved_id' => $sa->saved_id,
                                        'picid' => $pic->doc_id,
                                        'nochange' => 1], $smarty);

                                if (isset($dim_image_sizes[$dim_color_npic])) {
                                    foreach ($dim_image_sizes[$dim_color_npic] as $size) {
                                        $details['shop_img_color_' . $lang_id . '_' . $dim_color_npic . '_' . $size . '_dim'] = $shop_url . smarty_function_imageurl([
                                                'lang_id' => $lang_id,
                                                'src' => 'saved',
                                                'saved_id' => $sa->saved_id,
                                                'picid' => $pic->doc_id,
                                                'x' => $size,
                                                'nochange' => 1], $smarty);
                                    }
                                }
                                $dim_color_npic++;
                            }
                            if (isset($image_sizes[$color_npic])) {
                                foreach ($image_sizes[$color_npic] as $size) {
                                    $details['shop_img_color_' . $lang_id . '_' . $color_npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                            'lang_id' => $lang_id,
                                            'src' => 'saved',
                                            'saved_id' => $sa->saved_id,
                                            'picid' => $pic->doc_id,
                                            'x' => $size,
                                            'nochange' => 1], $smarty);
                                    $details['shop_img_color_' . $lang_id . '_' . $color_npic . '_' . $size . '_png'] = $shop_url . smarty_function_imageurl([
                                            'lang_id' => $lang_id,
                                            'src' => 'saved',
                                            'saved_id' => $sa->saved_id,
                                            'picid' => $pic->doc_id,
                                            'x' => $size,
                                            'nochange' => 1,
                                            'convert' => 1], $smarty);
                                    $details['shop_img_color_' . $lang_id . '_' . $color_npic . '_' . $size . '_withlogo'] = $shop_url . smarty_function_imageurl([
                                            'lang_id' => $lang_id,
                                            'src' => 'saved',
                                            'saved_id' => $sa->saved_id,
                                            'picid' => $pic->doc_id,
                                            'x' => $size,
                                            'nochange' => 1,
                                            'addlogo' => 'logo'], $smarty);
                                }
                                $color_npic++;
                            }

                            if ($pic->white_shadow_id) {
                                $details['shop_img_quad_' . $lang_id . '_' . $quad_npic] = $shop_url . smarty_function_imageurl([
                                        'lang_id' => $lang_id,
                                        'src' => 'saved',
                                        'saved_id' => $sa->saved_id,
                                        'picid' => $pic->white_shadow_id,
                                        'nochange' => 1], $smarty);

                                $details['shop_img_quad_' . $lang_id . '_' . $quad_npic . '_png'] = $shop_url . smarty_function_imageurl([
                                        'lang_id' => $lang_id,
                                        'src' => 'saved',
                                        'saved_id' => $sa->saved_id,
                                        'picid' => $pic->white_shadow_id,
                                        'nochange' => 1,
                                        'convert' => 1], $smarty);

                                $details['shop_img_quad_' . $lang_id . '_' . $quad_npic . '_withlogo'] = $shop_url . smarty_function_imageurl([
                                        'lang_id' => $lang_id,
                                        'src' => 'saved',
                                        'saved_id' => $sa->saved_id,
                                        'picid' => $pic->white_shadow_id,
                                        'nochange' => 1,
                                        'addlogo' => 'logo'], $smarty);

                                if ($pic->dimensions) {
                                    $details['shop_img_quad_' . $lang_id . '_' . $dim_quad_npic . '_dim'] = $shop_url . smarty_function_imageurl([
                                            'lang_id' => $lang_id,
                                            'src' => 'saved',
                                            'saved_id' => $sa->saved_id,
                                            'picid' => $pic->white_shadow_id,
                                            'nochange' => 1], $smarty);

                                    if (isset($dim_image_sizes[$dim_quad_npic])) {
                                        foreach ($dim_image_sizes[$dim_quad_npic] as $size) {
                                            $details['shop_img_quad_' . $lang_id . '_' . $dim_quad_npic . '_' . $size . '_dim'] = $shop_url . smarty_function_imageurl([
                                                    'lang_id' => $lang_id,
                                                    'src' => 'saved',
                                                    'saved_id' => $sa->saved_id,
                                                    'picid' => $pic->white_shadow_id,
                                                    'x' => $size,
                                                    'nochange' => 1], $smarty);
                                        }
                                    }
                                    $dim_quad_npic++;
                                }
                                if (isset($image_sizes[$quad_npic])) {
                                    foreach ($image_sizes[$quad_npic] as $size) {
                                        $details['shop_img_quad_' . $lang_id . '_' . $quad_npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                                'lang_id' => $lang_id,
                                                'src' => 'saved',
                                                'saved_id' => $sa->saved_id,
                                                'picid' => $pic->white_shadow_id,
                                                'x' => $size,
                                                'nochange' => 1], $smarty);
                                        $details['shop_img_quad_' . $lang_id . '_' . $quad_npic . '_' . $size . '_png'] = $shop_url . smarty_function_imageurl([
                                                'lang_id' => $lang_id,
                                                'src' => 'saved',
                                                'saved_id' => $sa->saved_id,
                                                'picid' => $pic->white_shadow_id,
                                                'x' => $size,
                                                'convert' => 1,
                                                'nochange' => 1], $smarty);
                                        $details['shop_img_quad_' . $lang_id . '_' . $quad_npic . '_' . $size . '_withlogo'] = $shop_url . smarty_function_imageurl([
                                                'lang_id' => $lang_id,
                                                'src' => 'saved',
                                                'saved_id' => $sa->saved_id,
                                                'picid' => $pic->white_shadow_id,
                                                'x' => $size,
                                                'addlogo' => 'logo',
                                                'nochange' => 1], $smarty);
                                    }
                                }
                                $quad_npic++;
                            }

                            if ($pic->white_noshadow_id) {
                                $details['shop_img_white_' . $lang_id . '_' . $white_npic] = $shop_url . smarty_function_imageurl([
                                        'lang_id' => $lang_id,
                                        'src' => 'saved',
                                        'saved_id' => $sa->saved_id,
                                        'picid' => $pic->white_noshadow_id,
                                        'nochange' => 1], $smarty);

                                $details['shop_img_white_' . $lang_id . '_' . $white_npic . '_png'] = $shop_url . smarty_function_imageurl([
                                        'lang_id' => $lang_id,
                                        'src' => 'saved',
                                        'saved_id' => $sa->saved_id,
                                        'picid' => $pic->white_noshadow_id,
                                        'convert' => 1,
                                        'nochange' => 1], $smarty);

                                $details['shop_img_white_' . $lang_id . '_' . $white_npic . '_withlogo'] = $shop_url . smarty_function_imageurl([
                                        'lang_id' => $lang_id,
                                        'src' => 'saved',
                                        'saved_id' => $sa->saved_id,
                                        'picid' => $pic->white_noshadow_id,
                                        'addlogo' => 'logo',
                                        'nochange' => 1], $smarty);

                                if ($pic->dimensions) {
                                    $details['shop_img_white_' . $lang_id . '_' . $dim_white_npic . '_dim'] = $shop_url . smarty_function_imageurl([
                                            'lang_id' => $lang_id,
                                            'src' => 'saved',
                                            'saved_id' => $sa->saved_id,
                                            'picid' => $pic->white_noshadow_id,
                                            'nochange' => 1], $smarty);

                                    if (isset($dim_image_sizes[$dim_white_npic])) {
                                        foreach ($dim_image_sizes[$dim_white_npic] as $size) {
                                            $details['shop_img_white_' . $lang_id . '_' . $dim_white_npic . '_' . $size . '_dim'] = $shop_url . smarty_function_imageurl([
                                                    'lang_id' => $lang_id,
                                                    'src' => 'saved',
                                                    'saved_id' => $sa->saved_id,
                                                    'picid' => $pic->white_noshadow_id,
                                                    'x' => $size,
                                                    'nochange' => 1], $smarty);
                                        }
                                    }
                                    $dim_white_npic++;
                                }
                                if (isset($image_sizes[$white_npic])) {
                                    foreach ($image_sizes[$white_npic] as $size) {
                                        $details['shop_img_white_' . $lang_id . '_' . $white_npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                                'lang_id' => $lang_id,
                                                'src' => 'saved',
                                                'saved_id' => $sa->saved_id,
                                                'picid' => $pic->white_noshadow_id,
                                                'x' => $size,
                                                'nochange' => 1], $smarty);

                                        $details['shop_img_white_' . $lang_id . '_' . $white_npic . '_' . $size . '_png'] = $shop_url . smarty_function_imageurl([
                                                'lang_id' => $lang_id,
                                                'src' => 'saved',
                                                'saved_id' => $sa->saved_id,
                                                'picid' => $pic->white_noshadow_id,
                                                'x' => $size,
                                                'convert' => 1,
                                                'nochange' => 1], $smarty);

                                        $details['shop_img_white_' . $lang_id . '_' . $white_npic . '_' . $size . '_withlogo'] = $shop_url . smarty_function_imageurl([
                                                'lang_id' => $lang_id,
                                                'src' => 'saved',
                                                'saved_id' => $sa->saved_id,
                                                'picid' => $pic->white_noshadow_id,
                                                'x' => $size,
                                                'addlogo' => 'logo',
                                                'nochange' => 1], $smarty);
                                    }
                                }
                                $white_npic++;
                            }

                        } else {
                            switch ($pic->white_back) {
                                case 0:
                                    $details['shop_img_color_' . $lang_id . '_' . $color_npic] = $shop_url . smarty_function_imageurl([
                                            'lang_id' => $lang_id,
                                            'src' => 'saved',
                                            'saved_id' => $sa->saved_id,
                                            'picid' => $pic->doc_id,
                                            'nochange' => 1], $smarty);

                                    $details['shop_img_color_' . $lang_id . '_' . $color_npic . '_png'] = $shop_url . smarty_function_imageurl([
                                            'lang_id' => $lang_id,
                                            'src' => 'saved',
                                            'saved_id' => $sa->saved_id,
                                            'picid' => $pic->doc_id,
                                            'nochange' => 1,
                                            'convert' => 1], $smarty);

                                    $details['shop_img_color_' . $lang_id . '_' . $color_npic . '_withlogo'] = $shop_url . smarty_function_imageurl([
                                            'lang_id' => $lang_id,
                                            'src' => 'saved',
                                            'saved_id' => $sa->saved_id,
                                            'picid' => $pic->doc_id,
                                            'nochange' => 1,
                                            'addlogo' => 'logo'], $smarty);

                                    if ($pic->dimensions) {
                                        $details['shop_img_color_' . $lang_id . '_' . $dim_color_npic . '_dim'] = $shop_url . smarty_function_imageurl([
                                                'lang_id' => $lang_id,
                                                'src' => 'saved',
                                                'saved_id' => $sa->saved_id,
                                                'picid' => $pic->doc_id,
                                                'nochange' => 1], $smarty);

                                        if (isset($dim_image_sizes[$dim_color_npic])) {
                                            foreach ($dim_image_sizes[$dim_color_npic] as $size) {
                                                $details['shop_img_color_' . $lang_id . '_' . $dim_color_npic . '_' . $size . '_dim'] = $shop_url . smarty_function_imageurl([
                                                        'lang_id' => $lang_id,
                                                        'src' => 'saved',
                                                        'saved_id' => $sa->saved_id,
                                                        'picid' => $pic->doc_id,
                                                        'x' => $size,
                                                        'nochange' => 1], $smarty);
                                            }
                                        }
                                        $dim_color_npic++;
                                    }
                                    if (isset($image_sizes[$color_npic])) {
                                        foreach ($image_sizes[$color_npic] as $size) {
                                            $details['shop_img_color_' . $lang_id . '_' . $color_npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                                    'lang_id' => $lang_id,
                                                    'src' => 'saved',
                                                    'saved_id' => $sa->saved_id,
                                                    'picid' => $pic->doc_id,
                                                    'x' => $size,
                                                    'nochange' => 1], $smarty);
                                            $details['shop_img_color_' . $lang_id . '_' . $color_npic . '_' . $size . '_png'] = $shop_url . smarty_function_imageurl([
                                                    'lang_id' => $lang_id,
                                                    'src' => 'saved',
                                                    'saved_id' => $sa->saved_id,
                                                    'picid' => $pic->doc_id,
                                                    'x' => $size,
                                                    'nochange' => 1,
                                                    'convert' => 1], $smarty);
                                            $details['shop_img_color_' . $lang_id . '_' . $color_npic . '_' . $size . '_withlogo'] = $shop_url . smarty_function_imageurl([
                                                    'lang_id' => $lang_id,
                                                    'src' => 'saved',
                                                    'saved_id' => $sa->saved_id,
                                                    'picid' => $pic->doc_id,
                                                    'x' => $size,
                                                    'nochange' => 1,
                                                    'addlogo' => 'logo'], $smarty);
                                        }
                                    }
                                    $color_npic++;
                                    break;
                                case 1:
                                    $details['shop_img_quad_' . $lang_id . '_' . $quad_npic] = $shop_url . smarty_function_imageurl([
                                            'lang_id' => $lang_id,
                                            'src' => 'saved',
                                            'saved_id' => $sa->saved_id,
                                            'picid' => $pic->doc_id,
                                            'nochange' => 1], $smarty);

                                    $details['shop_img_quad_' . $lang_id . '_' . $quad_npic . '_png'] = $shop_url . smarty_function_imageurl([
                                            'lang_id' => $lang_id,
                                            'src' => 'saved',
                                            'saved_id' => $sa->saved_id,
                                            'picid' => $pic->doc_id,
                                            'nochange' => 1,
                                            'convert' => 1], $smarty);

                                    $details['shop_img_quad_' . $lang_id . '_' . $quad_npic . '_withlogo'] = $shop_url . smarty_function_imageurl([
                                            'lang_id' => $lang_id,
                                            'src' => 'saved',
                                            'saved_id' => $sa->saved_id,
                                            'picid' => $pic->doc_id,
                                            'nochange' => 1,
                                            'addlogo' => 'logo'], $smarty);

                                    if ($pic->dimensions) {
                                        $details['shop_img_quad_' . $lang_id . '_' . $dim_quad_npic . '_dim'] = $shop_url . smarty_function_imageurl([
                                                'lang_id' => $lang_id,
                                                'src' => 'saved',
                                                'saved_id' => $sa->saved_id,
                                                'picid' => $pic->doc_id,
                                                'nochange' => 1], $smarty);

                                        if (isset($dim_image_sizes[$dim_quad_npic])) {
                                            foreach ($dim_image_sizes[$dim_quad_npic] as $size) {
                                                $details['shop_img_quad_' . $lang_id . '_' . $dim_quad_npic . '_' . $size . '_dim'] = $shop_url . smarty_function_imageurl([
                                                        'lang_id' => $lang_id,
                                                        'src' => 'saved',
                                                        'saved_id' => $sa->saved_id,
                                                        'picid' => $pic->doc_id,
                                                        'x' => $size,
                                                        'nochange' => 1], $smarty);
                                            }
                                        }
                                        $dim_quad_npic++;
                                    }
                                    if (isset($image_sizes[$quad_npic])) {
                                        foreach ($image_sizes[$quad_npic] as $size) {
                                            $details['shop_img_quad_' . $lang_id . '_' . $quad_npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                                    'lang_id' => $lang_id,
                                                    'src' => 'saved',
                                                    'saved_id' => $sa->saved_id,
                                                    'picid' => $pic->doc_id,
                                                    'x' => $size,
                                                    'nochange' => 1], $smarty);
                                            $details['shop_img_quad_' . $lang_id . '_' . $quad_npic . '_' . $size . '_png'] = $shop_url . smarty_function_imageurl([
                                                    'lang_id' => $lang_id,
                                                    'src' => 'saved',
                                                    'saved_id' => $sa->saved_id,
                                                    'picid' => $pic->doc_id,
                                                    'x' => $size,
                                                    'convert' => 1,
                                                    'nochange' => 1], $smarty);
                                            $details['shop_img_quad_' . $lang_id . '_' . $quad_npic . '_' . $size . '_withlogo'] = $shop_url . smarty_function_imageurl([
                                                    'lang_id' => $lang_id,
                                                    'src' => 'saved',
                                                    'saved_id' => $sa->saved_id,
                                                    'picid' => $pic->doc_id,
                                                    'x' => $size,
                                                    'addlogo' => 'logo',
                                                    'nochange' => 1], $smarty);
                                        }
                                    }
                                    $quad_npic++;
                                    break;
                                case 2:
                                    $details['shop_img_white_' . $lang_id . '_' . $white_npic] = $shop_url . smarty_function_imageurl([
                                            'lang_id' => $lang_id,
                                            'src' => 'saved',
                                            'saved_id' => $sa->saved_id,
                                            'picid' => $pic->doc_id,
                                            'nochange' => 1], $smarty);

                                    $details['shop_img_white_' . $lang_id . '_' . $white_npic . '_png'] = $shop_url . smarty_function_imageurl([
                                            'lang_id' => $lang_id,
                                            'src' => 'saved',
                                            'saved_id' => $sa->saved_id,
                                            'picid' => $pic->doc_id,
                                            'convert' => 1,
                                            'nochange' => 1], $smarty);

                                    $details['shop_img_white_' . $lang_id . '_' . $white_npic . '_withlogo'] = $shop_url . smarty_function_imageurl([
                                            'lang_id' => $lang_id,
                                            'src' => 'saved',
                                            'saved_id' => $sa->saved_id,
                                            'picid' => $pic->doc_id,
                                            'addlogo' => 'logo',
                                            'nochange' => 1], $smarty);

                                    if ($pic->dimensions) {
                                        $details['shop_img_white_' . $lang_id . '_' . $dim_white_npic . '_dim'] = $shop_url . smarty_function_imageurl([
                                                'lang_id' => $lang_id,
                                                'src' => 'saved',
                                                'saved_id' => $sa->saved_id,
                                                'picid' => $pic->doc_id,
                                                'nochange' => 1], $smarty);

                                        if (isset($dim_image_sizes[$dim_white_npic])) {
                                            foreach ($dim_image_sizes[$dim_white_npic] as $size) {
                                                $details['shop_img_white_' . $lang_id . '_' . $dim_white_npic . '_' . $size . '_dim'] = $shop_url . smarty_function_imageurl([
                                                        'lang_id' => $lang_id,
                                                        'src' => 'saved',
                                                        'saved_id' => $sa->saved_id,
                                                        'picid' => $pic->doc_id,
                                                        'x' => $size,
                                                        'nochange' => 1], $smarty);
                                            }
                                        }
                                        $dim_white_npic++;
                                    }
                                    if (isset($image_sizes[$white_npic])) {
                                        foreach ($image_sizes[$white_npic] as $size) {
                                            $details['shop_img_white_' . $lang_id . '_' . $white_npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                                    'lang_id' => $lang_id,
                                                    'src' => 'saved',
                                                    'saved_id' => $sa->saved_id,
                                                    'picid' => $pic->doc_id,
                                                    'x' => $size,
                                                    'nochange' => 1], $smarty);

                                            $details['shop_img_white_' . $lang_id . '_' . $white_npic . '_' . $size . '_png'] = $shop_url . smarty_function_imageurl([
                                                    'lang_id' => $lang_id,
                                                    'src' => 'saved',
                                                    'saved_id' => $sa->saved_id,
                                                    'picid' => $pic->doc_id,
                                                    'x' => $size,
                                                    'convert' => 1,
                                                    'nochange' => 1], $smarty);

                                            $details['shop_img_white_' . $lang_id . '_' . $white_npic . '_' . $size . '_withlogo'] = $shop_url . smarty_function_imageurl([
                                                    'lang_id' => $lang_id,
                                                    'src' => 'saved',
                                                    'saved_id' => $sa->saved_id,
                                                    'picid' => $pic->doc_id,
                                                    'x' => $size,
                                                    'addlogo' => 'logo',
                                                    'nochange' => 1], $smarty);
                                        }
                                    }
                                    $white_npic++;
                                    break;
                            }
                        }

                        $details['shop_imgURLcached' . '_' . $lang_id . '_' . $npic] = $shop_url . smarty_function_imageurl([
                                'lang_id' => $lang_id,
                                'src' => 'saved',
                                'saved_id' => $sa->saved_id,
                                'picid' => $pic->doc_id], $smarty);
                        $details['shop_imgURL' . '_' . $npic] = $shop_url . smarty_function_imageurl([
                                'lang_id' => $lang_id,
                                'src' => 'saved',
                                'saved_id' => $sa->saved_id,
                                'picid' => $pic->doc_id,
                                'nochange' => 1], $smarty);

                        if ((int)$dbr->getOne("select count(*) from saved_csv_field where sa_csv_id={$item->id} and field like '[[shop_imgdata%'")) {

                            $_file = $dbr->getOne("select md5
                                        from prologis_log.translation_files2
                                        where id='{$pic->doc_id}' and table_name='saved_doc' and field_name='data' and language = '$lang_id'");

                            $_file = get_file_path($_file);
                            $_file = base64_encode($_file);

                            $details['shop_imgdata' . '_' . $lang_id . '_' . $npic] = "<file>$_file</file>";
                        } // if we need pic dump
                        yield ['level' => 'debug', 'message' => 'iteration pos 4-3:'];
                    }
                    $descriptionShop = '';
                    for ($i = 1; $i <= 6; $i++) {
                        if (!$details['inactivedescriptionShop' . $i][$lang_id]) {
                            $descriptionShop = $details['descriptionShop' . $i . '_' . $lang_id];
                            break;
                        }
                    }
                    $details['descriptionShop' . '_' . $lang_id] = $descriptionShop;
                    for ($i = 1; $i <= 10; $i++) {
                        if (!isset($details['shop_img' . '_' . $lang_id . '_' . $i])) {
                            $details['shop_img' . '_' . $lang_id . '_' . $i] = '';
                        }
                    }
                    yield ['level' => 'debug', 'message' => 'iteration pos 4-5:'];
                }

                // @todo Pics without `LANG` parameter, by new logic. In future we need - removed previouse block.
                yield ['level' => 'debug', 'message' => 'iteration pos 4-20:'];

                if ($shop_id) {
                    for ($ki = 0; $ki < 10; $ki++) {
                        $details['doc_' . $ki] = '';
                        $details['shop_img_' . $ki] = "";
                        $details['shop_img_color_' . $ki] = "";
                        $details['shop_img_quad_' . $ki] = "";
                        $details['shop_img_white_' . $ki] = "";
                        $details['shop_img_' . $ki . '_png'] = "";
                        $details['shop_img_color_' . $ki . '_png'] = "";
                        $details['shop_img_quad_' . $ki . '_png'] = "";
                        $details['shop_img_white_' . $ki . '_png'] = "";
                        $details['shop_img_color_' . $ki . '_withlogo'] = "";
                        $details['shop_img_details_color_' . $ki] = "";
                        $details['shop_img_details_whitesh_' . $ki] = "";
                        $details['shop_img_details_whitenosh_' . $ki] = "";
                        $details['shop_img_dimension_color_' . $ki] = "";
                        $details['shop_img_dimension_cm_color_' . $ki] = "";
                        $details['shop_img_dimension_inch_color_' . $ki] = "";
                        $details['shop_img_dimension_whitesh_' . $ki] = "";
                        $details['shop_img_dimension_cm_whitesh_' . $ki] = "";
                        $details['shop_img_dimension_inch_whitesh_' . $ki] = "";
                        $details['shop_img_dimension_whitenosh_' . $ki] = "";
                        $details['shop_img_dimension_cm_whitenosh_' . $ki] = "";
                        $details['shop_img_dimension_inch_whitenosh_' . $ki] = "";
                        if (isset($image_sizes[$ki])) {
                            foreach ($image_sizes[$ki] as $size) {
                                $details['shop_img_' . $ki . '_' . $size] = "";
                                $details['shop_img_color_' . $ki . '_' . $size] = "";
                                $details['shop_img_quad_' . $ki . '_' . $size] = "";
                                $details['shop_img_white_' . $ki . '_' . $size] = "";
                                $details['shop_img_' . $ki . '_' . $size . '_png'] = "";
                                $details['shop_img_color_' . $ki . '_' . $size . '_png'] = "";
                                $details['shop_img_quad_' . $ki . '_' . $size . '_png'] = "";
                                $details['shop_img_white_' . $ki . '_' . $size . '_png'] = "";
                                $details['shop_img_color_' . $ki . '_' . $size . '_withlogo'] = "";
                                $details['shop_img_main_color_' . $size] = "";
                                $details['shop_img_main_whitesh_' . $size] = "";
                                $details['shop_img_main_whitenosh_' . $size] = "";
                                $details['shop_img_details_color_' . $ki . '_' . $size] = "";
                                $details['shop_img_details_whitesh_' . $ki . '_' . $size] = "";
                                $details['shop_img_details_whitenosh_' . $ki . '_' . $size] = "";
                                $details['shop_img_dimension_color_' . $ki . '_' . $size] = "";
                                $details['shop_img_dimension_cm_color_' . $ki . '_' . $size] = "";
                                $details['shop_img_dimension_inch_color_' . $ki . '_' . $size] = "";
                                $details['shop_img_dimension_whitesh_' . $ki . '_' . $size] = "";
                                $details['shop_img_dimension_cm_whitesh_' . $ki . '_' . $size] = "";
                                $details['shop_img_dimension_inch_whitesh_' . $ki . '_' . $size] = "";
                                $details['shop_img_dimension_whitenosh_' . $ki . '_' . $size] = "";
                                $details['shop_img_dimension_cm_whitenosh_' . $ki . '_' . $size] = "";
                                $details['shop_img_dimension_inch_whitenosh_' . $ki . '_' . $size] = "";
                            } // foreach image size
                        } // if we have diff sizes
                    } // for 10 items
                } // if shop
                
                for ($ki = 0; $ki < 10; $ki++) {
                    $details['doc_' . $ki] = '';
                } // for 10 items
                $docs = \Saved::getDocs($db, $dbr, $alt_saved_id, '', '', '', 0, 1, 1);
                foreach ($docs as $kd => $doc) {
                    $details['doc_' . $kd] = "http://www.prologistics.info/doc.php?saved_id={$alt_saved_id}&doc_id={$doc->doc_id}";
                }

                $saved_pic = new \SavedPic($alt_saved_id);
                $pics = array_values($saved_pic->get(true));

                $white_npic = 0;
                $quad_npic = 0;
                $color_npic = 0;

                $dim_npic = 1;
                $dim_cm_npic = 1;
                $dim_inch_npic = 1;
                $dim_white_npic = 1;
                $dim_quad_npic = 1;
                $dim_color_npic = 1;
                
                $details_npic = 0;
                
                foreach ($pics as $npic => $pic) {
                    if ( ! $pic->doc_id) {
                        continue;
                    }                        
                    
                    if ($pic->img_type == 'primary') {
                        $details['shop_img_main_color'] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'picid' => $pic->doc_id,
                            'type' => 'color',
                            'nochange' => 1], $smarty);

                        $details['shop_img_main_whitesh'] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'picid' => $pic->doc_id,
                            'type' => 'whitesh',
                            'nochange' => 1], $smarty);

                        $details['shop_img_main_whitenosh'] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'picid' => $pic->doc_id,
                            'type' => 'whitenosh',
                            'nochange' => 1], $smarty);
                    }
                    
                    $details['shop_img_' . $npic] = $shop_url . smarty_function_imageurl([
                        'src' => 'sa',
                        'type' => 'color',
                        'picid' => $pic->doc_id,
                        'nochange' => 1], $smarty);
                    $details['shop_img_' . $npic . '_png'] = $shop_url . smarty_function_imageurl([
                        'src' => 'sa',
                        'type' => 'color',
                        'picid' => $pic->doc_id,
                        'nochange' => 1,
                        'convert' => 1], $smarty);
                    $details['shop_img_' . $npic . '_withlogo'] = $shop_url . smarty_function_imageurl([
                        'src' => 'sa',
                        'type' => 'color',
                        'picid' => $pic->doc_id,
                        'nochange' => 1,
                        'addlogo' => 'logo'], $smarty);
                    if (isset($image_sizes[$npic])) {
                        foreach ($image_sizes[$npic] as $size) {
                            if ($pic->img_type == 'primary') {
                                $details['shop_img_main_color_' . $size] = $shop_url . smarty_function_imageurl([
                                    'src' => 'sa',
                                    'picid' => $pic->doc_id,
                                    'x' => $size,
                                    'type' => 'color',
                                    'nochange' => 1], $smarty);
                                    
                                $details['shop_img_main_whitesh_' . $size] = $shop_url . smarty_function_imageurl([
                                    'src' => 'sa',
                                    'picid' => $pic->doc_id,
                                    'x' => $size,
                                    'type' => 'whitesh',
                                    'nochange' => 1], $smarty);

                                $details['shop_img_main_whitenosh_' . $size] = $shop_url . smarty_function_imageurl([
                                    'src' => 'sa',
                                    'picid' => $pic->doc_id,
                                    'x' => $size,
                                    'type' => 'whitenosh',
                                    'nochange' => 1], $smarty);
                            }
                        
                            $details['shop_img_' . $npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                'src' => 'sa',
                                'type' => 'color',
                                'picid' => $pic->doc_id,
                                'x' => $size,
                                'nochange' => 1,
                                'addlogo' => 'logo'], $smarty);
                            $details['shop_img_' . $npic . '_' . $size . '_png'] = $shop_url . smarty_function_imageurl([
                                'src' => 'sa',
                                'type' => 'color',
                                'picid' => $pic->doc_id,
                                'x' => $size,
                                'nochange' => 1,
                                'convert' => 1], $smarty);
                            $details['shop_img_' . $npic . '_' . $size . '_withlogo'] = $shop_url . smarty_function_imageurl([
                                'src' => 'sa',
                                'type' => 'color',
                                'picid' => $pic->doc_id,
                                'x' => $size,
                                'nochange' => 1,
                                'addlogo' => 'logo'], $smarty);
                        }
                    }
                    
                    if ($pic->img_type == 'dimensions_cm') {
                        $details['shop_img_dimension_cm_color_' . $dim_cm_npic] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'type' => 'color',
                            'picid' => $pic->doc_id,
                            'nochange' => 1], $smarty);
                            
                        $details['shop_img_dimension_cm_whitesh_' . $dim_cm_npic] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'type' => 'whitesh',
                            'picid' => $pic->doc_id,
                            'nochange' => 1], $smarty);
                            
                        $details['shop_img_dimension_cm_whitenosh_' . $dim_cm_npic] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'type' => 'whitenosh',
                            'picid' => $pic->doc_id,
                            'nochange' => 1], $smarty);
                            
                        foreach ($dim_image_sizes[$dim_cm_npic] as $size) {
                            $details['shop_img_dimension_cm_color_' . $dim_cm_npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                'src' => 'sa',
                                'picid' => $pic->doc_id,
                                'type' => 'color',
                                'x' => $size,
                                'nochange' => 1], $smarty);
                                
                            $details['shop_img_dimension_cm_whitesh_' . $dim_cm_npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                'src' => 'sa',
                                'picid' => $pic->doc_id,
                                'type' => 'whitesh',
                                'x' => $size,
                                'nochange' => 1], $smarty);

                            $details['shop_img_dimension_cm_whitenosh_' . $dim_cm_npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                'src' => 'sa',
                                'picid' => $pic->doc_id,
                                'type' => 'whitenosh',
                                'x' => $size,
                                'nochange' => 1], $smarty);
                        }
                        
                       $dim_cm_npic++;
                    }
                    
                    if ($pic->img_type == 'dimensions_inch') {
                        $details['shop_img_dimension_inch_color_' . $dim_inch_npic] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'type' => 'color',
                            'picid' => $pic->doc_id,
                            'nochange' => 1], $smarty);
                            
                        $details['shop_img_dimension_inch_whitesh_' . $dim_inch_npic] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'type' => 'whitesh',
                            'picid' => $pic->doc_id,
                            'nochange' => 1], $smarty);
                            
                        $details['shop_img_dimension_inch_whitenosh_' . $dim_inch_npic] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'type' => 'whitenosh',
                            'picid' => $pic->doc_id,
                            'nochange' => 1], $smarty);
                            
                        foreach ($dim_image_sizes[$dim_inch_npic] as $size) {
                            $details['shop_img_dimension_inch_color_' . $dim_inch_npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                'src' => 'sa',
                                'picid' => $pic->doc_id,
                                'type' => 'color',
                                'x' => $size,
                                'nochange' => 1], $smarty);
                                
                            $details['shop_img_dimension_inch_whitesh_' . $dim_inch_npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                'src' => 'sa',
                                'picid' => $pic->doc_id,
                                'type' => 'whitesh',
                                'x' => $size,
                                'nochange' => 1], $smarty);

                            $details['shop_img_dimension_inch_whitenosh_' . $dim_inch_npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                'src' => 'sa',
                                'picid' => $pic->doc_id,
                                'type' => 'whitenosh',
                                'x' => $size,
                                'nochange' => 1], $smarty);
                        }
                        
                        $dim_inch_npic++;
                    }

                    if ($pic->dimensions && $shop->_shop->dimensions == $pic->dimensions) {
                        $details['shop_img_' . $dim_npic . '_dim'] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'type' => 'color',
                            'picid' => $pic->doc_id,
                            'nochange' => 1], $smarty);
                            
                        $details['shop_img_dimension_color_' . $dim_npic] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'picid' => $pic->doc_id,
                            'type' => 'color',
                            'nochange' => 1], $smarty);
                            
                        $details['shop_img_dimension_whitesh_' . $dim_npic] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'picid' => $pic->doc_id,
                            'type' => 'whitesh',
                            'nochange' => 1], $smarty);

                        $details['shop_img_dimension_whitenosh_' . $dim_npic] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'picid' => $pic->doc_id,
                            'type' => 'whitenosh',
                            'nochange' => 1], $smarty);
                            
                        foreach ($dim_image_sizes[$dim_npic] as $size) {
                            $details['shop_img_' . $dim_npic . '_' . $size . '_dim'] = $shop_url . smarty_function_imageurl([
                                'src' => 'sa',
                                'picid' => $pic->doc_id,
                                'type' => 'color',
                                'x' => $size,
                                'nochange' => 1], $smarty);
                                
                            $details['shop_img_dimension_color_' . $dim_npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                'src' => 'sa',
                                'picid' => $pic->doc_id,
                                'x' => $size,
                                'type' => 'color',
                                'nochange' => 1], $smarty);
                                
                            $details['shop_img_dimension_whitesh_' . $dim_npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                'src' => 'sa',
                                'picid' => $pic->doc_id,
                                'x' => $size,
                                'type' => 'whitesh',
                                'nochange' => 1], $smarty);
                            
                            $details['shop_img_dimension_whitenosh_' . $dim_npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                'src' => 'sa',
                                'picid' => $pic->doc_id,
                                'x' => $size,
                                'type' => 'whitenosh',
                                'nochange' => 1], $smarty);
                        }

                        $dim_npic++;
                    }

                    $details['shop_img_color_' . $color_npic] = $shop_url . smarty_function_imageurl([
                        'src' => 'sa',
                        'type' => 'color',
                        'picid' => $pic->doc_id,
                        'nochange' => 1], $smarty);

                    $details['shop_img_color_' . $color_npic . '_png'] = $shop_url . smarty_function_imageurl([
                        'src' => 'sa',
                        'type' => 'color',
                        'picid' => $pic->doc_id,
                        'nochange' => 1,
                        'convert' => 1], $smarty);

                    $details['shop_img_color_' . $color_npic . '_withlogo'] = $shop_url . smarty_function_imageurl([
                        'src' => 'sa',
                        'type' => 'color',
                        'picid' => $pic->doc_id,
                        'nochange' => 1,
                        'addlogo' => 'logo'], $smarty);

                    if ($pic->dimensions && $shop->_shop->dimensions == $pic->dimensions) {
                        $details['shop_img_color_' . $dim_color_npic . '_dim'] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'type' => 'color',
                            'picid' => $pic->doc_id,
                            'nochange' => 1], $smarty);

                        if (isset($dim_image_sizes[$dim_color_npic])) {
                            foreach ($dim_image_sizes[$dim_color_npic] as $size) {
                                $details['shop_img_color_' . $dim_color_npic . '_' . $size . '_dim'] = $shop_url . smarty_function_imageurl([
                                    'src' => 'sa',
                                    'type' => 'color',
                                    'picid' => $pic->doc_id,
                                    'x' => $size,
                                    'nochange' => 1], $smarty);
                            }
                        }
                        $dim_color_npic++;
                    }
                    
                    if (isset($image_sizes[$color_npic])) {
                        foreach ($image_sizes[$color_npic] as $size) {
                            $details['shop_img_color_' . $color_npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                'src' => 'sa',
                                'picid' => $pic->doc_id,
                                'x' => $size,
                                'type' => 'color',
                                'nochange' => 1], $smarty);
                            $details['shop_img_color_' . $color_npic . '_' . $size . '_png'] = $shop_url . smarty_function_imageurl([
                                'src' => 'sa',
                                'type' => 'color',
                                'picid' => $pic->doc_id,
                                'x' => $size,
                                'nochange' => 1,
                                'convert' => 1], $smarty);
                            $details['shop_img_color_' . $color_npic . '_' . $size . '_withlogo'] = $shop_url . smarty_function_imageurl([
                                'src' => 'sa',
                                'type' => 'color',
                                'picid' => $pic->doc_id,
                                'x' => $size,
                                'nochange' => 1,
                                'addlogo' => 'logo'], $smarty);
                        }
                        
                        $color_npic++;
                    }

                    if ( !empty($pic->hash_whitenosh) && !empty($pic->ext_whitenosh)) {
                        $details['shop_img_quad_' . $quad_npic] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'type' => 'whitenosh',
                            'picid' => $pic->doc_id,
                            'nochange' => 1], $smarty);

                        $details['shop_img_quad_' . $quad_npic . '_png'] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'type' => 'whitenosh',
                            'picid' => $pic->doc_id,
                            'nochange' => 1,
                            'convert' => 1], $smarty);

                        $details['shop_img_quad_' . $quad_npic . '_withlogo'] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'type' => 'whitenosh',
                            'picid' => $pic->doc_id,
                            'nochange' => 1,
                            'addlogo' => 'logo'], $smarty);

                        if ($pic->dimensions && $shop->_shop->dimensions == $pic->dimensions) {
                            $details['shop_img_quad_' . $dim_quad_npic . '_dim'] = $shop_url . smarty_function_imageurl([
                                'src' => 'sa',
                                'type' => 'whitenosh',
                                'picid' => $pic->doc_id,
                                'nochange' => 1], $smarty);

                            if (isset($dim_image_sizes[$dim_quad_npic])) {
                                foreach ($dim_image_sizes[$dim_quad_npic] as $size) {
                                    $details['shop_img_quad_' . $dim_quad_npic . '_' . $size . '_dim'] = $shop_url . smarty_function_imageurl([
                                        'src' => 'sa',
                                        'type' => 'whitenosh',
                                        'picid' => $pic->doc_id,
                                        'x' => $size,
                                        'nochange' => 1], $smarty);
                                }
                            }
                            $dim_quad_npic++;
                        }
                        if (isset($image_sizes[$quad_npic])) {
                            foreach ($image_sizes[$quad_npic] as $size) {
                                $details['shop_img_quad_' . $quad_npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                    'src' => 'sa',
                                    'type' => 'whitenosh',
                                    'picid' => $pic->doc_id,
                                    'x' => $size,
                                    'nochange' => 1], $smarty);
                                $details['shop_img_quad_' . $quad_npic . '_' . $size . '_png'] = $shop_url . smarty_function_imageurl([
                                    'src' => 'sa',
                                    'type' => 'whitenosh',
                                    'picid' => $pic->doc_id,
                                    'x' => $size,
                                    'convert' => 1,
                                    'nochange' => 1], $smarty);
                                $details['shop_img_quad_' . $quad_npic . '_' . $size . '_withlogo'] = $shop_url . smarty_function_imageurl([
                                    'src' => 'sa',
                                    'type' => 'whitenosh',
                                    'picid' => $pic->doc_id,
                                    'x' => $size,
                                    'addlogo' => 'logo',
                                    'nochange' => 1], $smarty);
                            }
                        }
                        $quad_npic++;
                    }

                    if ( !empty($pic->hash_whitesh) && !empty($pic->ext_whitesh)) {
                        $details['shop_img_white_' . $white_npic] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'type' => 'whitesh',
                            'picid' => $pic->doc_id,
                            'nochange' => 1], $smarty);

                        $details['shop_img_white_' . $white_npic . '_png'] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'type' => 'whitesh',
                            'picid' => $pic->doc_id,
                            'convert' => 1,
                            'nochange' => 1], $smarty);

                        $details['shop_img_white_' . $white_npic . '_withlogo'] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'type' => 'whitesh',
                            'picid' => $pic->doc_id,
                            'addlogo' => 'logo',
                            'nochange' => 1], $smarty);

                        if ($pic->dimensions && $shop->_shop->dimensions == $pic->dimensions) {
                            $details['shop_img_white_' . $dim_white_npic . '_dim'] = $shop_url . smarty_function_imageurl([
                                'src' => 'sa',
                                'type' => 'whitesh',
                                'picid' => $pic->doc_id,
                                'nochange' => 1], $smarty);

                            if (isset($dim_image_sizes[$dim_white_npic])) {
                                foreach ($dim_image_sizes[$dim_white_npic] as $size) {
                                    $details['shop_img_white_' . $dim_white_npic . '_' . $size . '_dim'] = $shop_url . smarty_function_imageurl([
                                        'src' => 'sa',
                                        'type' => 'whitesh',
                                        'picid' => $pic->doc_id,
                                        'x' => $size,
                                        'nochange' => 1], $smarty);
                                }
                            }
                            $dim_white_npic++;
                        }
                        if (isset($image_sizes[$white_npic])) {
                            foreach ($image_sizes[$white_npic] as $size) {
                                $details['shop_img_white_' . $white_npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                    'src' => 'sa',
                                    'type' => 'whitesh',
                                    'picid' => $pic->doc_id,
                                    'x' => $size,
                                    'nochange' => 1], $smarty);

                                $details['shop_img_white_' . $white_npic . '_' . $size . '_png'] = $shop_url . smarty_function_imageurl([
                                    'src' => 'sa',
                                    'type' => 'whitesh',
                                    'picid' => $pic->doc_id,
                                    'x' => $size,
                                    'convert' => 1,
                                    'nochange' => 1], $smarty);

                                $details['shop_img_white_' . $white_npic . '_' . $size . '_withlogo'] = $shop_url . smarty_function_imageurl([
                                    'src' => 'saved',
                                    'type' => 'whitesh',
                                    'picid' => $pic->doc_id,
                                    'x' => $size,
                                    'addlogo' => 'logo',
                                    'nochange' => 1], $smarty);
                            }
                        }
                        $white_npic++;
                    }
                    
                    if ($pic->details) {
                        $details['shop_img_details_color_' . $details_npic] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'picid' => $pic->doc_id,
                            'type' => 'color',
                            'nochange' => 1], $smarty);
                            
                        $details['shop_img_details_whitesh_' . $details_npic] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'picid' => $pic->doc_id,
                            'type' => 'whitesh',
                            'nochange' => 1], $smarty);
                            
                        $details['shop_img_details_whitenosh_' . $details_npic] = $shop_url . smarty_function_imageurl([
                            'src' => 'sa',
                            'picid' => $pic->doc_id,
                            'type' => 'whitenosh',
                            'nochange' => 1], $smarty);
                        
                        if (isset($image_sizes[$details_npic])) {
                            foreach ($image_sizes[$details_npic] as $size) {
                                $details['shop_img_details_color_' . $details_npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                    'src' => 'sa',
                                    'picid' => $pic->doc_id,
                                    'x' => $size,
                                    'type' => 'color',
                                    'nochange' => 1], $smarty);

                                $details['shop_img_details_whitesh_' . $details_npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                    'src' => 'sa',
                                    'picid' => $pic->doc_id,                                    
                                    'x' => $size,
                                    'type' => 'whitesh',
                                    'nochange' => 1], $smarty);
                                
                                $details['shop_img_details_whitenosh_' . $details_npic . '_' . $size] = $shop_url . smarty_function_imageurl([
                                    'src' => 'sa',
                                    'picid' => $pic->doc_id,
                                    'x' => $size,
                                    'type' => 'whitenosh',
                                    'nochange' => 1], $smarty);
                            }
                        }
                        
                        $details_npic++;
                    }

                    yield ['level' => 'debug', 'message' => 'iteration pos 4-30:'];
                }
                
                yield ['level' => 'debug', 'message' => 'iteration pos 4-50:'];

                /******************************************************************************************************/
                
                yield ['level' => 'debug', 'message' => 'iteration pos 5:'];

                for ($i = 1; $i <= 6; $i++) {
                    if (!$details['inactivedescriptionRicardo'][$i]) {
                        $descriptionRicardo = $details['descriptionRicardo'][$i];
                        $descriptionRicardoFr = $details['descriptionRicardoFr'][$i];
                        break;
                    }
                }
                $details['RicardoDescDe'] = $descriptionRicardo;
                $details['RicardoDescFr'] = (int)$details['GermanRicardo'] ? $descriptionRicardo : $descriptionRicardoFr;
                $details['RicardoRefNo'] = $details['saved_id'] . '/' . date('Y-m-d');
                $details['RicardoWarrantyFr'] = (int)$details['GermanRicardoWarranty'] ? $details['RicardoWarranty'] : $details['RicardoWarrantyFr'];
                $details['RicardoStartprice'] = $details['Ricardo']['Channel'] == '1' ? $details['Ricardo']['startprice'] : 0;
                $details['RicardoAvailabilityValue'] = $RicardoAvailabilityValue[$details['Ricardo']['AvailabilityID']];
                $details['Ricardobold'] = $details['Ricardo']['bold'];
                $details['Ricardohighlight'] = $details['Ricardo']['highlight'];
                $details['Ricardosuper'] = $details['Ricardo']['super'];
                $q = "select * from repetition where auction_id=" . $details['saved_id'] . " and not inactive";
                $reps = $dbr->getRow($q);
                $details['featured'] = $reps->featured;
                $details['start_time'] = date('Y-m-d') . ' ' . $reps->start_at;
                $details['duration'] = $reps->days;
                $details['sitecountry'] = countryCodeToCountry(siteToCountryCode($details['siteid']));
                $qrystr = "select amt
					from eco_table_corr join eco_table_part on eco_table_corr.code_part=eco_table_part.code_part
					where code_prod='" . $orig_details['eco_code_prod'] . "'";
                $details['eco_amt'] = $dbr->getOne($qrystr);

                $margin = getSAMargin((int)$orig_details['saved_id']);
                $details['margin_abs'] = $margin->margin_abs;
                $details['margin_perc'] = $margin->margin_perc;
                $details['total_purchase_price_local'] = $margin->total_purchase_price_local;
                $details['total_purchase_price_local_sh_vat'] = $margin->total_purchase_price_local_sh_vat;

                foreach ($details['amazon'] as $key => $value) {
                    $details['amazon' . $key] = $value;
                }
                foreach ($details['descriptionAmazon'] as $key => $value) {
                    $details['descriptionAmazon' . $key] = $value;
                }
                $details['amazonTitle'] = $dbr->getOne("select name from offer_name where id=" . (int)$details['amazonTitle']);
                foreach ($langs as $lang_id => $dummy) {
                    if ($details['color_id'])
                        $details['colors_' . $lang_id] = $dbr->getOne("select group_concat(value separator ', ')
							from translation where table_name='sa_color' and field_name='name' and language='{$lang_id}'
							and id = '" . $details['color_id'] . "'");
                    else $details['colors_' . $lang_id] = '';
                    if ($details['material_id'])
                        $details['materials_' . $lang_id] = $dbr->getOne("select group_concat(value separator ', ')
							from translation where table_name='sa_material' and field_name='name' and language='{$lang_id}'
							and id = '" . $details['material_id'] . "'");
                    else $details['materials_' . $lang_id] = '';
                }
                
                foreach ($ss_list as $kss => $ss) {
                    $field_text = $ss->pp_formula;
                    $field_text = str_ireplace('[[total_purchase_price_local]]', $margin->total_purchase_price_local, $field_text);
                    $field_text = str_ireplace('[[total_purchase_price_local_sh_vat]]', $margin->total_purchase_price_local_sh_vat, $field_text);
                    $field_text = str_ireplace('[[ShopPrice]]', $details['ShopPrice'], $field_text);
                    $field_text = str_ireplace('[[ShopHPrice]]', $details['ShopHPrice'], $field_text);
                    
                    try 
                    {
                        if (is_validPHP("\$field_text = $field_text;"))
                        {
                            $code = escapeshellarg("echo $field_text;");
                            $field_text = `php -r $code`;
                            
                            if ($field_text === false) {
                                yield ['level' => 'main', 'message' => 'Eval error:' . $ss->pp_formula . ' ::: "$field_text = ' . $field_text . ';"'];
                                yield ['level' => 'main', 'message' =>
                                    'data: '
                                    . $margin->total_purchase_price_local . ' : '
                                    . $margin->total_purchase_price_local_sh_vat . ' : '
                                    . $details['ShopPrice'] . ' : '
                                    . $details['ShopHPrice']
                                ];
                            }
                        }
                    } 
                    catch (Exception $ex) {
                        
                    }
                    
                    $details["ss_pp_" . str_replace(' ', '_', $ss->name)] = $field_text;
                }
                
//                $ratings = $dbr->getRow("SELECT COUNT(DISTINCT(`PHPSESSID`)) AS `PHPSESSID`, 
//                        COUNT(DISTINCT(`ip`)) AS `ip`, COUNT(*) AS `visits`
//                    FROM (
//                    SELECT `PHPSESSID`, `ip`
//                    FROM `prologis_log`.`shop_page_log`
//                    WHERE `saved_id` = '" . (int)$orig_details['saved_id'] . "'
//                        AND SERVER IN ('" . $shop->_shop->url . "', 'www." . $shop->_shop->url . "')
//                    ) `t`");
        
                if ($shop->_shop->username == 'Beliani NL')
                {
                    $details['rating_mode_PHPSESSID']   = $shop->getRatingMode('PHPSESSID', (int)$orig_details['saved_id']);
//                    $details['rating_mode_PHPSESSID']   = $ratings->PHPSESSID;
                    $details['rating_mode_ip']          = $shop->getRatingMode('ip', (int)$orig_details['saved_id']);
//                    $details['rating_mode_ip']          = $ratings->ip;
                    $details['rating_mode_visits']      = $shop->getRatingMode('visits', (int)$orig_details['saved_id']);
//                    $details['rating_mode_visits']      = $ratings->visits;
                }
                $details['rating_mode_orders']      = $shop->getRatingMode('orders', (int)$orig_details['saved_id']);
                $details['rating_mode_rating']      = $shop->getRatingMode('rating', (int)$orig_details['saved_id']);
                
                $rating_published = $dbr->getRow("select AVG(t.code) avg_code, MIN(t.code) min_code, MAX(t.code) max_code, COUNT(*) cnt, CONCAT(ROUND(100*AVG(t.code)/5),'%') perc
                    from (
                    select af.code
                                from auction_feedback af
                                join auction au on af.auction_number=au.auction_number and af.txnid=au.txnid
                                join customer c on au.customer_id=c.id
                                left join auction subau on subau.main_auction_number=au.auction_number and subau.main_txnid=au.txnid
                                where not au.hiderating and subau.saved_id = ? and not af.hidden
                    union all
                    select rating
                                from saved_custom_ratings scr
                                where saved_id = ? and not hidden) t", null, [$orig_details['saved_id'], $orig_details['saved_id']]);

                $rating_real = $dbr->getRow("select AVG(t.code) avg_code, MIN(t.code) min_code, MAX(t.code) max_code, COUNT(*) cnt, CONCAT(ROUND(100*AVG(t.code)/5),'%') perc
                    from (
                    select af.code
                                from auction_feedback af
                                join auction au on af.auction_number=au.auction_number and af.txnid=au.txnid
                                join customer c on au.customer_id=c.id
                                left join auction subau on subau.main_auction_number=au.auction_number and subau.main_txnid=au.txnid
                                where subau.saved_id = ?
                                ) t", null, [$orig_details['saved_id']]);
                
                $rating_all = $dbr->getRow("select AVG(t.code) avg_code, MIN(t.code) min_code, MAX(t.code) max_code, COUNT(*) cnt, CONCAT(ROUND(100*AVG(t.code)/5),'%') perc
                    from (
                    select af.code
                                from auction_feedback af
                                join auction au on af.auction_number=au.auction_number and af.txnid=au.txnid
                                join customer c on au.customer_id=c.id
                                left join auction subau on subau.main_auction_number=au.auction_number and subau.main_txnid=au.txnid
                                where subau.saved_id = ?
                    union all
                    select rating
                                from saved_custom_ratings scr
                                where saved_id = ?) t", null, [$orig_details['saved_id'], $orig_details['saved_id']]);

                $details['sa_average_rating_published'] = $rating_published->avg_code;
                $details['sa_minimum_rating_published'] = $rating_published->min_code;
                $details['sa_maximum_rating_published'] = $rating_published->max_code;
                $details['sa_number_rating_published'] = $rating_published->cnt;
                
                $details['sa_average_rating_real'] = $rating_real->avg_code;
                $details['sa_minimum_rating_real'] = $rating_real->min_code;
                $details['sa_maximum_rating_real'] = $rating_real->max_code;
                $details['sa_number_rating_real'] = $rating_real->cnt;
                
                $details['sa_average_rating'] = $rating_all->avg_code;
                $details['sa_minimum_rating'] = $rating_all->min_code;
                $details['sa_maximum_rating'] = $rating_all->max_code;
                $details['sa_number_rating'] = $rating_all->cnt;

                $details['expected_delivery_time'] = '';
                
                $_offer = $shop->getOffer($sa->saved_id);

                $offer = new \Offer($db, $dbr, $_offer->offer_id, $shop->_shop->lang);
                $orig_offer = new \Offer($db, $dbr, $_offer->orig_offer_id, $shop->_shop->lang);
                
                $minstock = getMinStock($db, $dbr, $_offer->saved_id, $_offer->offer_id, $orig_details['stop_empty_warehouse_shop'], 4);
                $query = "select orig_o.*, IF(orig_o.available
                                , CONCAT('{$shop->_shop->english_shop[214]}'
                                    ,' " . ($minstock['minava'] < $shop->_shop->min_stock ? $shop->_shop->english_shop[221] : "") . "')
                                ,IF(orig_o.available_weeks
                                    , CONCAT('{$shop->_shop->english_shop[216]} ', DATE(date_add(NOW(), INTERVAL orig_o.available_weeks week)))
                                    , IF(orig_o.available_date='0000-00-00'
                                        , '{$shop->_shop->english_shop[215]}'
                                        , CONCAT('{$shop->_shop->english_shop[216]} ', orig_o.available_date)
                                    )
                                )
                            ) available_text
                        from offer orig_o
                        where orig_o.offer_id=?";
                $offer_obj = $dbr->getRow($query, null, [$_offer->orig_offer_id]);

                $offer->getAvailable($offer_obj, $shop);
                
                if ($orig_offer->data->available || ($offer->data->shopAvailableDate && $offer->data->shopAvailableDate!='0000-00-00' && $offer->data->shopAvailableDate!=$shop->_shop->english_shop['192'])) {
                    if ($shop->_shop->expected_delivery == 'shop') {
                        $details['expected_delivery_time'] = $offer->data->expecteddelivery_from;
                    } else {
                        $shippingInfoDependingOnDelivery = \ShippingMethod::getDeliveryDays(
                            $shop,
                            $_offer->orig_offer_id, 
                            'Y-m-d'
                        );

                        if ($shop->_seller->data->defshcountry == $shippingInfoDependingOnDelivery->def_country) {
                            $delivery_day = $shippingInfoDependingOnDelivery->def_day;
                            $delivery_date = $shippingInfoDependingOnDelivery->def_date;
                        } else {
                            $delivery_day = $shippingInfoDependingOnDelivery->other_day;
                            $delivery_date = $shippingInfoDependingOnDelivery->other_date;
                        }
                        
                        $details['expected_delivery_time'] = $delivery_date;
                        $details['delivery_working_days'] = $delivery_day;
                    }
                } else {
                    $details['expected_delivery_time'] = '';
                }

                unset($details['amazon']);
                unset($details['amazon[item-price]']);
                unset($details['amazon[ItemPrice]']);
                unset($details['amazon[item-price']);
                unset($details['amazon[ItemPrice']);
                
                yield ['level' => 'main', 'message' => "TO CACHE: " . count($details) . 
                    "; FUNCTION: {$function}" . 
                    "; SHOP: {$shop->_shop->id}" . 
                    "; LANG: {$shop->_shop->lang}" ];

                if ($shop) {
                    cacheSet($function, $shop->_shop->id, $shop->_shop->lang, $details);
                }
            } // if we dont have details in memcache
            
            $details = array_change_key_case($details, CASE_LOWER);
            
//            $excluded_fields = [];
            $excluded_sa = false;
//            foreach ($fields as $field) 
//            {
//                if ($field->condition)
//                {
//                    $excluded_sa = true;
//                    break;
//                }
//            }
            
            $exclude_line = [];
            
            $exclude = false;
            if ($excluded_sa || $item->exclude_empty || $item->excluded_sa) 
            {
                $expression = $item->excluded_sa;
                $expression_array = [];
                
                if (preg_match_all('#\[\[(.+?)\]\]#iu', $expression, $matches, PREG_PATTERN_ORDER))
                {
                    $matches[1] = array_unique($matches[1]);
                    foreach ($matches[1] as $found)
                    {
                        $found = strtolower($found);
                        if (isset($details[$found]))
                        {
                            $expression = str_ireplace('[[' . $found . ']]', $details[$found], $expression);
                        }
                    }
                }

                $expression = preg_replace('#\[\[.*?\]\]#iu', '', $expression);

                foreach ($fields as $key => $field) 
                {
                    $field_text = $field->field;
                    $replace = false;
                    
                    $index = $field->id . "|" . $field->line;
//                    if ($field->condition)
//                    {
//                        $expression_array[$index] = $field->condition;
//                    }
                    
                    if (stripos($field_text, '[[') !== false && stripos($field_text, ']]') !== false)
                    {
                        if (preg_match_all('#\[\[(.+?)\]\]#iu', $field_text, $matches, PREG_PATTERN_ORDER))
                        {
                            $matches[1] = array_unique($matches[1]);
                            foreach ($matches[1] as $found)
                            {
                                $found = strtolower($found);
                                if (isset($details[$found]))
                                {
                                    $field_text = str_ireplace('[[' . $found . ']]', $details[$found], $field_text);
//                                    if ($field->condition)
//                                    {
//                                        $expression_array[$index] = str_ireplace('[[' . $found . ']]', $details[$found], $expression_array[$index]);
//                                    }
                                }
                            }
                        }
                        
                        $field_text = preg_replace('#\[\[.*?\]\]#iu', '', $field_text);
                    }
                    
                    if ( ! $replace) 
                    {
                        if (strpos($field_text, '[[') !== false && strpos($field_text, ']]') !== false) 
                        {
                            $field_text = '';
                        }
                    }
                    
                    if ($item->exclude_empty)
                    {
                        $field_text = str_replace(';', "", str_replace('"', "'", $field_text));
                        if (!strlen(trim($field_text)) || $field_text == '<![CDATA[]]>') 
                        {
                            $exclude = true;
                            
//                            if ($field->mandatory)
//                            {
//                                $excluded_fields[] = (int)$field->id;
//                            }
                        }
                    }
                }
                
                if ( ! $exclude && $item->excluded_sa)
                {
                    $expression = trim($expression);
                    $expression = preg_replace('#^:=|;$#iu', '', $expression);
                    $expression = trim($expression);
                    
                    $result = true;
                    
                    if ($expression)
                    {
                        try 
                        {
                            if (is_validPHP("return (bool)(" . $expression . ");"))
                            {
                                $code = escapeshellarg("echo ($expression);");
                                $result = `php -r $code`;
                                $result = (bool)$result;
                            }
                        } 
                        catch (Exception $ex) {
                        }
                    }
                    
                    if ($result)
                    {
                        $exclude = true;
                    }
                }
                
                if ( ! $exclude && $expression_array)
                {
                    foreach ($expression_array as $field_line => $expression)
                    {
                        $expression = trim($expression);
                        $expression = preg_replace('#^:=|;$#iu', '', $expression);
                        $expression = trim($expression);

                        $result = true;
                        if ($expression)
                        {
                            try 
                            {
                                if (is_validPHP("return (bool)(" . $expression . ");"))
                                {
                                    $code = escapeshellarg("echo ($expression);");
                                    $result = `php -r $code`;
                                    $result = (bool)$result;
                                }
                            } 
                            catch (Exception $ex) {
                            }
                        }

                        if ($result)
                        {
                            $line = explode('|', $field_line);
                            $line = (int)$line[1];
                            
                            $exclude_line[] = $line;
                        }
                    }
                }
            }

            if ($exclude) 
            {
//                if ($excluded_fields)
//                {
//                    foreach ($excluded_fields as $field)
//                    {
//                        $db->query("REPLACE INTO `saved_csv_errorlog` (`sa_scv_field_id`, `saved_id`) 
//                                VALUES ('" . $field . "', '" . $details['saved_id'] . "')");
//                    }
//                }
                continue;
            }
            
//            yield ['level' => 'debug', 'message' => $details];
            yield ['level' => 'debug', 'message' => 'iteration pos 6:'];

            $xml_node = $item->xml_node ? $item->xml_node : 'xml_node';
            
            if (preg_match_all('#\[\[(.+?)\]\]#iu', $xml_node, $matches, PREG_PATTERN_ORDER))
            {
                $matches[1] = array_unique($matches[1]);
                foreach ($matches[1] as $found)
                {
                    $found = strtolower($found);
                    if (isset($details[$found]))
                    {
                        $xml_node = str_ireplace('[[' . $found . ']]', $details[$found], $xml_node);
                    }
                }
            }
            $xml .= '<' . $xml_node . '>';
            for ($i = 1; $i <= $maxline; $i++) {
                $csv_a[$i] = '';
                $csv_comma_a[$i] = '';
                $csv_pipe_a[$i] = '';
                $txt_a[$i] = '';
            }

            $exclude = [];
            
            $n++;
            $xls_key = [];
            $xls_values = [];
            
            foreach ($fields as $key => $field) {
                if ($field->hidden) {
                    continue;
                }
                
                $field->line = (int)$field->line;
                $field->line = $field->line < 1 ? 1 : $field->line;
                
                $field_text = $field->field;
                
                if (stripos($field_text, '[[') !== false && stripos($field_text, ']]') !== false)
                {
                    if (preg_match_all('#\[\[(.+?)\]\]#iu', $field_text, $matches, PREG_PATTERN_ORDER))
                    {
                        $matches[1] = array_unique($matches[1]);
                        foreach ($matches[1] as $found)
                        {
                            $found = strtolower($found);
                            if (isset($details[$found]))
                            {
                                $field_text = str_ireplace('[[' . $found . ']]', $details[$found], $field_text);
                            }
                        }
                    }
                    
                    $field_text = preg_replace('#\[\[.*?\]\]#iu', '', $field_text);
                }
                
//                foreach ($details as $sname => $svalue) {
//                    $field_text = str_ireplace('[[' . $sname . ']]', $svalue, $field_text);
//                }
                $field_text = str_replace(';', "", str_replace('"', "'", $field_text));
                
                if (!strlen(trim($field_text)) || $field_text == '<![CDATA[]]>') 
                {
                    $exclude[$field->line] = true;
                    if ($field->mandatory)
                    {
                        if ( ! isset($empty_fields[$field->id]))
                        {
                            $empty_fields[$field->id] = 0;
                        }
                        
                        $empty_fields[$field->id]++;
                    }
                }
                else 
                {
                    if ($field->mandatory)
                    {
                        if ( ! isset($fill_fields[$field->id]))
                        {
                            $fill_fields[$field->id] = 0;
                        }
                        
                        $fill_fields[$field->id]++;
                    }
                }
                
                if ( ! isset($xls_key[$field->line])) {
                    $xls_key[$field->line] = 0;
                }
                
                if (!isset($details[str_replace('[[', '', str_replace(']]', '', strtolower($field->field)))]))
                    $details[str_replace('[[', '', str_replace(']]', '', strtolower($field->field)))] = '';
                
                $field_text = $field->field;
                if (stripos($field_text, ':=') === 0) {
                    
                    if (stripos($field_text, '[[') !== false && stripos($field_text, ']]') !== false)
                    {
                        if (preg_match_all('#\[\[(.+?)\]\]#iu', $field_text, $matches, PREG_PATTERN_ORDER))
                        {
                            $matches[1] = array_unique($matches[1]);
                            foreach ($matches[1] as $found)
                            {
                                $found = strtolower($found);
                                if (isset($details[$found]))
                                {
                                    $field_text = str_ireplace('[[' . $found . ']]', str_replace("'", '', $details[$found]), $field_text);
                                }
                            }
                        }
                        $field_text = preg_replace('#\[\[.*?\]\]#iu', '', $field_text);
                    }

//                    foreach ($details as $sname => $svalue) {
//                        $field_text = str_ireplace('[[' . $sname . ']]', str_replace("'", '', $svalue), $field_text);
//                    }
                    
                    try 
                    {
                        $expression = trim($field_text);
                        $expression = preg_replace('#^:=|;$#iu', '', $expression);
                        $expression = trim($expression);
                        
                        if (is_validPHP("return (" . $expression . ");"))
                        {
                            $code = escapeshellarg("echo ($expression);");
                            $field_text = `php -r $code`;
                            //$field_text = eval("return (" . $expression . ");");
                        }
                    } 
                    catch (Exception $ex) {
                    }
                    
                } else {
                    
                    if (stripos($field_text, '[[') !== false && stripos($field_text, ']]') !== false)
                    {
                        if (preg_match_all('#\[\[(.+?)\]\]#iu', $field_text, $matches, PREG_PATTERN_ORDER))
                        {
                            $matches[1] = array_unique($matches[1]);
                            foreach ($matches[1] as $found)
                            {
                                $found = strtolower($found);
                                if (isset($details[$found]))
                                {
                                    $field_text = str_ireplace('[[' . $found . ']]', $details[$found], $field_text);
                                }
                            }
                        }
                    }
                    
//                    foreach ($details as $sname => $svalue) {
//                        $field_text = str_ireplace('[[' . $sname . ']]', $svalue, $field_text);
//                    }
                }
                
                $field_text = str_replace(';', "", str_replace('"', "'", $field_text));
                yield ['level' => 'debug', 'message' => '$field_text for ' . $field->field . '=' . $field_text];

                $xml_item = htmlspecialchars($field->title);
                $xml_pars = explode(' ', $xml_item);

                if (preg_match('#^[\d+]$#iu', $xml_item)) {
                    $xml_item = "xml_item_$xml_item";
                }

                if ($item->encoding == 'utf-8') {
                    $txt_i = '' . str_replace('	', '', $field_text) . '	';
                    $xml .= "<$xml_item><![CDATA[" . str_replace('&', '&amp;', $field_text) . "]]></{$xml_pars[0]}>\n";
                } else {
                    $txt_i = '' . utf8_decode(str_replace('	', '', $field_text)) . '	';
                    $xml .= "<$xml_item><![CDATA[" . str_replace('&', '&amp;', utf8_decode($field_text)) . "]]></{$xml_pars[0]}>\n";
                }

                switch ($field->type) {
                    case 'string':
                        $csv_i = '"' . ($field_text) . '";';
                        $csv_comma_i = '"' . ($field_text) . '",';
                        $csv_pipe_i = '"' . ($field_text) . '"|';
                        
                        $xls_values[$xls_n + $field->line][$xls_key[$field->line]] = $field_text;
                        break;
                    case 'number':
                        if ($item->encoding == 'utf-8') {
                            $csv_i = '' . str_replace('.', ',', $field_text) . ';';
                            $csv_comma_i = '' . $field_text . ',';
                            $csv_pipe_i = '' . $field_text . '|';
                        } else {
                            $csv_i = '' . utf8_decode(str_replace('.', ',', $field_text)) . ';';
                            $csv_comma_i = '' . utf8_decode($field_text) . ',';
                            $csv_pipe_i = '' . utf8_decode($field_text) . '|';
                        }
                        
                        $xls_values[$xls_n + $field->line][$xls_key[$field->line]] = $field_text;
                        break;
                    case 'date':
                        if ($item->encoding == 'utf-8') {
                            $csv_i = '"' . $field_text . '";';
                            $csv_comma_i = '"' . $field_text . '",';
                            $csv_pipe_i = '"' . $field_text . '"|';
                        } else {
                            $csv_i = '"' . utf8_decode($field_text) . '";';
                            $csv_comma_i = '"' . utf8_decode($field_text) . '",';
                            $csv_pipe_i = '"' . utf8_decode($field_text) . '"|';
                        }
                        
                        $xls_values[$xls_n + $field->line][$xls_key[$field->line]] = $field_text;
                        break;
                    case 'image':
                        $objDrawing = new \PHPExcel_Worksheet_Drawing();
                        $objDrawing->setWorksheet($objPHPExcel->getActiveSheet());
                        $img = $field_text;
                        $exts = explode('.', $img);
                        $ext = end($exts);
                        $img_fn = 'tmppic/temp' . rand(100000, 999999) . '.' . $ext;
                        file_put_contents($img_fn, file_get_contents($img));
                        usleep(100000);
                        file_put_contents($img_fn, file_get_contents($img));
                        $imgObj = imagecreatefromstring(file_get_contents($img));
                        $objDrawing->setPath($img_fn);
                        $objDrawing->setWidth(imagesx($imgObj));
                        $objDrawing->setHeight(imagesy($imgObj));
                        $objPHPExcel->getActiveSheet()->getRowDimension($xls_n + $field->line)->setRowHeight(imagesy($imgObj) / 1.3);
                        $col = $xls_key[$field->line];
                        if ($col <= 25) {
                            $colname = chr($col + 65);
                        } else {
                            $colname = chr((int)$col / 26 + 64) . chr($col - 26 * ((int)($col / 26)) + 65);
                        }
                        $objDrawing->setCoordinates($colname . ($xls_n + $field->line));
                        $imgsExcel[] = $img_fn;
                        break;
                }
                
                $xls_key[$field->line]++;
                
                for ($i = 1; $i <= $maxline; $i++) {
                    if ($i == $field->line) {
                        $txt_a[$i] .= $txt_i;
                        $csv_a[$i] .= $csv_i;
                        $csv_comma_a[$i] .= $csv_comma_i;
                        $csv_pipe_a[$i] .= $csv_pipe_i;
                    }
                }
            } // foreach
            
            $xls_n += $maxline;

            for ($i = 1; $i <= $maxline; $i++) 
            {
                if ($item->exclude_empty_line && $exclude[$i])
                {
//                    if ($empty_fields)
//                    {
//                        foreach ($empty_fields as $field)
//                        {
//                            $db->query("REPLACE INTO `saved_csv_errorlog` (`sa_scv_field_id`, `saved_id`) 
//                                    VALUES ('" . $field . "', '" . $details['saved_id'] . "')");
//                        }
//                    }
                }
                if ($exclude_line && in_array($i, $exclude_line))
                {
//                    if ($empty_fields)
//                    {
//                        foreach ($empty_fields as $field)
//                        {
//                            $db->query("REPLACE INTO `saved_csv_errorlog` (`sa_scv_field_id`, `saved_id`) 
//                                    VALUES ('" . $field . "', '" . $details['saved_id'] . "')");
//                        }
//                    }
                }
                else
                {
                    $csv .= rtrim($csv_a[$i], ';') . "\n";
                    $csv_comma .= rtrim($csv_comma_a[$i], ',') . "\n";
                    $csv_pipe .= rtrim($csv_pipe_a[$i], '|') . "\n";
                    $txt .= rtrim($txt_a[$i]) . "\n";
                }
            }
            
            foreach ($xls_values as $line => $cell_data) {
                $exclude = false;
                foreach ($cell_data as $cell => $value) {
                    $exclude = ($item->exclude_empty_line && ! $value) || ($exclude_line && in_array($i, $exclude_line));
                }
                if ( ! $exclude) {
                    foreach ($cell_data as $cell => $value) {
                        $objPHPExcel->getActiveSheet()->setCellValueByColumnAndRow($cell, $xls_key_total, $value);
                    }
                    $xls_key_total++;
                }
            }
            
            unset($details);
            $xml_pars = explode(' ', $item->xml_node ? $item->xml_node : 'node');
            $xml .= '</' . $xml_pars[0] . '>';
            yield ['level' => 'debug', 'message' => 'iteration end:'];
            $mem = exec("ps aux|grep " . getmypid() . "|grep -v grep|awk {'print $6'}");
            yield ['level' => 'report', 'message' => "mem after all =$mem"];
        } // foreach $sa_list

        yield ['level' => 'debug', 'message' => 'after circle:'];
        $xml_root = explode(' ', $item->xml_root ? $item->xml_root : 'xml_root');

        $xml .= $item->xml_footer . "\n</{$xml_root[0]}>";
        
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        ob_start();
        $objWriter->save('php://output');
        $xls = ob_get_clean();

        foreach ($imgsExcel as $img_fn) unset($img_fn);

        @unlink("tmp/sa_csv_fields_{$item->id}_xml.xml");
        file_put_contents("tmp/sa_csv_fields_{$item->id}_xml.xml", $xml);
        $db->query("update saved_csv set xml_lastchange=NOW() where id={$item->id}");

        @unlink("tmp/sa_csv_fields_{$item->id}_csv_comma.csv");
        file_put_contents("tmp/sa_csv_fields_{$item->id}_csv_comma.csv", $csv_comma);
        $db->query("update saved_csv set commacsv_lastchange=NOW() where id={$item->id}");

        @unlink("tmp/sa_csv_fields_{$item->id}_csv_pipe.csv");
        file_put_contents("tmp/sa_csv_fields_{$item->id}_csv_pipe.csv", $csv_pipe);
        $db->query("update saved_csv set pipecsv_lastchange=NOW() where id={$item->id}");

        @unlink("tmp/sa_csv_fields_{$item->id}_xls.xls");
        file_put_contents("tmp/sa_csv_fields_{$item->id}_xls.xls", $xls);
        $db->query("update saved_csv set xls_lastchange=NOW() where id={$item->id}");

        @unlink("tmp/sa_csv_fields_{$item->id}_txt.txt");
        file_put_contents("tmp/sa_csv_fields_{$item->id}_txt.txt", $txt);
        $db->query("update saved_csv set txt_lastchange=NOW() where id={$item->id}");

        @unlink("tmp/sa_csv_fields_{$item->id}_csv.csv");
        file_put_contents("tmp/sa_csv_fields_{$item->id}_csv.csv", $csv);
        $db->query("update saved_csv set csv_lastchange=NOW() where id={$item->id}");

        foreach ($empty_fields as $field_id => $empty)
        {
            $fill = isset($fill_fields[$field_id]) ? (int)$fill_fields[$field_id] : 0;
            $db->query("REPLACE INTO `saved_csv_errorlog` (`sa_csv_field_id`, `empty`, `fill`) 
                    VALUES ('" . $field_id . "', '" . $empty . "', '" . $fill . "')");
        }
        
        yield ['level' => 'debug', 'message' => 'Result is:'];
        yield ['level' => 'debug', 'message' => 'iteration end:'];
        $mem = exec("ps aux|grep " . getmypid() . "|grep -v grep|awk {'print $6'}");
        yield ['level' => 'report', 'message' => "mem after save #{$item->id} =$mem"];
        yield ['level' => 'main', 'message' => date('Y-m-d H:i:s')];
        yield ['level' => 'main', 'message' => 'Ended.'];
    }

    /**
     * Set limit of sa to export in one export file
     * Used generally for debug
     * @param int $limit
     */
    public function setLimitSA($limit)
    {
        $this->limitSA = (int)$limit;
    }

    /**
     * Set flag
     */
    public function dontUseCache()
    {
        $this->useCache = false;
    }
}