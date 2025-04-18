<?php

namespace label\BeslistAPI;

/**
 * Class RecacheSA
 * Class used to process recache and recreate export for all SA
 */
class RecacheSA
{
    /**
     * Process recache and recreate export
     * @param object $item single row from saved_csv table
     * @return \Generator messages about current operation
     */
    public function recache($item)
    {
        global $db, $dbr;
        $langs = getLangsArray();

        $csv = [];

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
            and sa.inactive=0 and sa.old=0 and sa.export";

        $sa_list = $dbr->getAll($q);
        if (\PEAR::isError($sa_list)) {
            die('Error');
        }

        // Mixin articles into $SA_LIST

        $sa_ids = [];
        foreach ($sa_list as $_sa) {
            $sa_ids[] = (int)$_sa->saved_id;
        }
        $sa_ids = array_values(array_unique($sa_ids));

        $ii = 0;
        
        $maxline = $dbr->getOne("select max(line) from saved_csv_field where sa_csv_id={$item->id}");
        $q = "select * from saved_csv_field where sa_csv_id={$item->id} order by pos";

        $fields = $dbr->getAll($q);

        foreach ($sa_list as $sa) {
            if (!(int)$sa->saved_id) break;

            // @todo Cache disabled, this can cause issues
            $details = \Saved::getDetails($sa->master_saved_id);
            $orig_details = \Saved::getDetails($sa->saved_id);

            $alt_saved_id = $details['saved_id'];
            $details['saved_id'] = $sa->saved_id;

            $details['articleId'] = isset($sa->articleId) ? $sa->articleId : '';
            $details['articleType'] = isset($sa->articleType) ? $sa->articleType : '';
            $details['articleSoldToDate'] = isset($sa->articleSoldToDate) ? $sa->articleSoldToDate : '';

            foreach ($langs as $lang => $dummy) {
                $lang = strtolower($lang);
                $details["articleName_$lang"] = isset($sa->{"articleName_$lang"}) ? $sa->{"articleName_$lang"} : '';
                $details["articleName"][$lang] = isset($sa->{"articleName_$lang"}) ? $sa->{"articleName_$lang"} : '';
            }
            $details["articleName"] = implode(',', $details["articleName"]);

            $details['ShopPrice_master'] = $details['ShopPrice'];
            $details['ShopPrice'] = $orig_details['ShopPrice'];
            $details['ShopHPrice'] = $orig_details['ShopHPrice'];
            $details['shipping_cost_seller'] = $orig_details['shipping_cost_seller'];
            $details['total_cost_seller'] = $orig_details['total_cost_seller'];
            $details['shopAvailable'] = $orig_details['shopAvailable'];

            $stop_empty_warehouse = [];
            switch ($sa->seller_channel_id) {
                case 1:
                    $stop_empty_warehouse = $orig_details['stop_empty_warehouse'];
                    if ($details['fixedprice']) {
                        $shipping_plan_id_fn = 'f';
                    } else {
                        $shipping_plan_id_fn = '';
                    }
                    break;
                case 2:
                    $stop_empty_warehouse = $orig_details['stop_empty_warehouse_ricardo'];
                    if ($details['Ricardo']['Channel'] == 2)
                        $shipping_plan_id_fn = 'f';
                    else
                        $shipping_plan_id_fn = '';
                    break;
                case 3:
                    $stop_empty_warehouse = $orig_details['stop_empty_warehouse_amazon'];
                    $shipping_plan_id_fn = '';
                    break;
                case 4:
                    $stop_empty_warehouse = $orig_details['stop_empty_warehouse_shop'];
                    $shipping_plan_id_fn = 's';
                    break;
                case 5:
                    $stop_empty_warehouse = $orig_details['stop_empty_warehouse_Allegro'];
                    $shipping_plan_id_fn = 'a';
                    break;
            }

            if (!$details['offer_id']) continue;
            $resMinStock = getMinStock($db, $dbr, (int)$details['saved_id'], (int)$orig_details['offer_id'], $stop_empty_warehouse, 0);
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

            /******************************************************************************************************/

            $details['minstock'] = $minstock;
            $details['minavailable'] = $minava;
            $details['weight_kg'] = $weight;
            $details['weight_g'] = $weight * 1000;
            
            $excluded_sa = false;
            foreach ($fields as $key => $field) 
            {
                if ($field->condition)
                {
                    $excluded_sa = true;
                    break;
                }
            }
            
            $exclude_line = [];
            
            $exclude = false;
            if ($excluded_sa || $item->exclude_empty || $item->excluded_sa) 
            {
                $expression = $item->excluded_sa;
                $expression_array = [];
                
                foreach ($fields as $key => $field) 
                {
                    $field_text = $field->field;
                    $replace = false;
                    
                    $index = $field->id . "|" . $field->line;
                    if ($field->condition)
                    {
                        $expression_array[$index] = $field->condition;
                    }
                    
                    foreach ($details as $sname => $svalue) 
                    {
                        if (stripos($field_text, '[[' . $sname . ']]') !== false) 
                        {
                            $field_text = str_ireplace('[[' . $sname . ']]', $svalue, $field_text);
                            $expression = str_ireplace('[[' . $sname . ']]', $svalue, $expression);
                            
                            if ($field->condition)
                            {
                                $expression_array[$index] = str_ireplace('[[' . $sname . ']]', $svalue, $expression_array[$index]);
                            }
                            break;
                        }
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
                        }
                    }
                }
                
                if ( ! $exclude && $item->excluded_sa)
                {
                    $expression = preg_replace('#^:=#iu', '', $expression);
                    $expression = trim($expression);
                    
                    $result = true;
                    
                    if ($expression)
                    {
                        try 
                        {
                            if (is_validPHP("return (bool)(" . $expression . ");"))
                            {
                                $result = @eval("return (bool)(" . $expression . ");");
                            }
                        } 
                        catch (Exception $ex) {
                        }
                    }
                    
                    if ( ! $result)
                    {
                        $exclude = true;
                    }
                }
                
                if ( ! $exclude && $expression_array)
                {
                    foreach ($expression_array as $field_line => $expression)
                    {
                        $expression = preg_replace('#^:=#iu', '', $expression);
                        $expression = trim($expression);

                        $result = true;
                        if ($expression)
                        {
                            try 
                            {
                                if (is_validPHP("return (bool)(" . $expression . ");"))
                                {
                                    $result = @eval("return (bool)(" . $expression . ");");
                                }
                            } 
                            catch (Exception $ex) {
                            }
                        }

                        if ( ! $result)
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
                continue;
            }
            
            $exclude = [];
            
            $_csv = [];
            foreach ($fields as $key => $field) {
                if ($field->hidden) {
                    continue;
                }
                
                $field->line = (int)$field->line;
                $field->line = $field->line < 1 ? 1 : $field->line;
                
                $field_text = $field->field;
                foreach ($details as $sname => $svalue) {
                    $field_text = str_ireplace('[[' . $sname . ']]', $svalue, $field_text);
                }
                $field_text = str_replace(';', "", str_replace('"', "'", $field_text));
                if (!strlen(trim($field_text)) || $field_text == '<![CDATA[]]>') $exclude[$field->line] = true;
                
                if (!isset($details[str_replace('[[', '', str_replace(']]', '', $field->field))]))
                    $details[str_replace('[[', '', str_replace(']]', '', $field->field))] = '';
                
                $field_text = $field->field;
                if ($field_text[0] == ':') {
                    foreach ($details as $sname => $svalue) {
                        $field_text = str_ireplace('[[' . $sname . ']]', str_replace("'", '', $svalue), $field_text);
                    }
                    
                    try 
                    {
                        if (is_validPHP("\$field_text = " . str_replace(":=", "", $field_text) . ";"))
                        {
                            @eval("\$field_text = " . str_replace(":=", "", $field_text) . ";");
                        }
                    } 
                    catch (Exception $ex) {
                    }
                } else {
                    foreach ($details as $sname => $svalue) {
                        $field_text = str_ireplace('[[' . $sname . ']]', $svalue, $field_text);
                    }
                }
                
                $_csv[$field->title] = $field_text;
            } // foreach
            
            $csv[] = $_csv;
        } // foreach $sa_list

        return $csv;
    }
}