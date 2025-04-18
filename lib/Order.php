<?php

require_once 'PEAR.php';
require_once 'lib/Offer.php';
require_once 'plugins/function.change_log.php';

class Order {

    static function Create($db, $dbr, $auction_number, $txnid, $items, $delete = true) {
        if ($delete) {
            $q = "update orders set to_delete = 1 WHERE auction_number=$auction_number AND txnid=$txnid 
				and (not sent or manual in (2,3))";
            if ($auction_number == 10448)
                echo "$q<br>";
            $db->query($q);
        }
        $last_id = 'NULL';
        if (count($items))
            foreach ($items as $item) {
                $item[0] = mysql_escape_string($item[0]);
                $item[5] = mysql_escape_string($item[5]);
                $id = (int) $dbr->getOne("select id from orders where id='" . $item[11] . "'");
                if ($id) {
                    $q = "update orders SET to_delete=0, 
                  quantity = $item[1],
                  price = '$item[2]',
                  oldprice = '$item[13]',
                  custom_title= '$item[0]',
                  custom_description = '$item[5]',
				  ordering = '$item[8]',
				  reserve_warehouse_id = '$item[9]',
				  send_warehouse_id = '$item[10]'
				  where id=$id and (not sent or manual=4)
    	        ";
                    $r = $db->query($q);
                    if (PEAR::isError($r))
                        aprint_r($r);
                    if (!(int) $item[7])
                        $last_id = $id;
                } else {
                    if ($item[7]) {
                        $q = "select count(*) from orders where article_id = '$item[3]' 
						and not to_delete and main_id = " . ((int) $item[7] ? $last_id : 'NULL');
                        $already_have = $dbr->getOne($q);
//					echo $q.' => '.$already_have.'<br>';
                    }
                    else {
                        $already_have = 0;
                    }
                    if (!$already_have) {
                        $q = "INSERT INTO orders SET auction_number=$auction_number,
	                  txnid = $txnid,
	                  article_id = '$item[3]',
	                  alias_id = '$item[12]',
	                  quantity = $item[1],
	                  price = '$item[2]',
	                  oldprice = '$item[13]',
	                  manual = $item[4],
	                  custom_title= '$item[0]',
	                  custom_description = '$item[5]',
					  article_list_id = '$item[6]',
					  hidden = '$item[7]',
					  ordering = '$item[8]',
					  reserve_warehouse_id = '$item[9]',
					  send_warehouse_id = '$item[10]',
					  main_id = " . ((int) $item[7] ? $last_id : 'NULL') . ",
					  code_id_free = '$item[14]',
					  sent=IF($item[4]=2 OR $item[4]=3 OR $item[4]=4, 1, 0)
	    	        ";
                        
                        $r = $db->query($q);
                        if (!(int) $item[7]) {
                            $last_id = mysql_insert_id();
                            if (!$last_id)
                                $last_id = $dbr->getOne("select max(id) from orders where auction_number=$auction_number and txnid = $txnid");
                        }
                        if (PEAR::isError($r)) {
                            aprint_r($r);
                            die();
                        }
                    }
                }
            }
            
        if ($delete) {
            $obj_ids = $dbr->getOne("SELECT GROUP_CONCAT(id) FROM orders WHERE to_delete and auction_number=$auction_number AND txnid=$txnid and (not sent or manual in (2,3))");
            $db->query("DELETE FROM barcode_object WHERE obj = 'orders' and obj_id IN ($obj_ids)");
            
            $q = "DELETE FROM orders WHERE to_delete and auction_number=$auction_number AND txnid=$txnid 
				and (not sent or manual in (2,3))";
            $db->query($q);
        }
        
        return $last_id;
    }

    static function listAll($db, $dbr, $auction_number, $txnid, $sub = 0, $lang = 'german', $hidden = '0', $complete = 0) {
        global $method_filter_str, $debug, $loggedUser;
        $auction_obj = new Auction($db, $dbr, $auction_number, $txnid);
        $country_code2 = countryToCountryCode($auction_obj->get('country_shipping'));
        if ($debug)
            $time = getmicrotime();
        if ($sub) {
            $q = "select t.*, CONCAT(article_id, ': ', IF (custom_title IS NULL OR custom_title = '', name, custom_title) ) as custom_title_id,
                IF (custom_title IS NULL OR custom_title = '', name, custom_title) as custom_title_name
                , (SELECT CONCAT('Released on ', DATE_FORMAT(`tl`.`Updated`, '%Y-%m-%d %H:%i'), 
                        ' by ', IFNULL(`u`.`name`, `tl`.`username`), 
                        ' on <a href=\"/loading_area.php?la=', `la`.`id` , '\">', `la`.`la_name`, '</a>')
                    FROM `total_log` AS `tl`
                    LEFT JOIN users u ON u.system_username=tl.username
                    LEFT JOIN `orders` AS `o` ON `o`.`id` = `tl`.`TableID`
                    LEFT JOIN `picking_order` AS `po` ON `po`.`id` = `tl`.`New_value`
                    LEFT JOIN `ware_la` AS `la` ON `la`.`id` = `po`.`ware_la_id`
                    WHERE `tl`.`Table_name` = 'orders' and `tl`.`Field_name` = 'picking_order_id' AND `tl`.`New_value` > 0 AND 
                        `o`.`id` = `t`.`id` AND `o`.`picking_order_id` != 0
                    ORDER BY `tl`.`id` DESC LIMIT 1) released_log
			from (
			SELECT article.barcode_type, article.mobile_warning, auction.end_time
				, IF(article.admin_id=2, 
					(SELECT priority FROM shop_bonus where shop_bonus.article_id = article.article_id limit 1)
					, auction.priority) priority
				, IF(article.admin_id=2, 
					(SELECT show_in_table FROM shop_bonus where shop_bonus.article_id = article.article_id limit 1)
					,1) show_in_table,
					IF(article.admin_id=2, 
					(SELECT color FROM shop_bonus where shop_bonus.article_id = article.article_id limit 1)
					,'#000000') color,
				auction.code_id, fget_AShop(auction.auction_number, auction.txnid) shop_id,
				orders.*,article.weight, article.weight_per_single_unit, article.volume, article.volume_per_single_unit
				, article_warehouse_place.warehouse_place
				, article_list.group_id, 
			custom_number.custom_number_eu, custom_number.custom_number_ch, custom_number.custom_tarif_eu, custom_number.custom_tarif_ch, 
			orders.manual as admin_id
			, IF(article.admin_id=2, 
(SELECT value FROM translation join shop_bonus on translation.id = shop_bonus.id
WHERE table_name = 'shop_bonus' AND field_name = 'title' AND language = '$lang' 
AND shop_bonus.article_id = article.article_id limit 1)
				, IF(article.admin_id=3,
(SELECT CONCAT(shop_promo_codes.id, ': ', IF(shop_promo_codes.descr_is_name, shop_promo_codes.name, translation.value)
, '<br>', code, IF(IFNULL(security_code, '')='', '', CONCAT(' / ',security_code))) 
FROM shop_promo_codes left join translation on translation.id = shop_promo_codes.id
and table_name = 'shop_promo_codes' AND field_name = 'name' AND language = '$lang'
where shop_promo_codes.article_id = article.article_id limit 1)
			, IF(orders.article_id='', article.name, 
				IFNULL((SELECT t1.value
				FROM translation t1
				WHERE t1.table_name = 'article'
				AND t1.field_name = 'name'
				AND t1.language = '$lang'
				AND t1.id = article.article_id), (SELECT t2.value
				FROM translation t2
				WHERE t2.table_name = 'article'
				AND t2.field_name = 'name'
				AND t2.language = '$lang'
				AND t2.id = article.article_id
				AND IFNULL(t2.value,'')<>'' limit 1)
				)))) as name, 
			(SELECT showit FROM shop_bonus where shop_bonus.article_id = article.article_id and article.admin_id=2) showit,
			article.supplier_article_id, article.country_code as article_country_code,
			article.items_per_shipping_unit
			, IF(article.admin_id=2,
				(SELECT value FROM translation join shop_bonus on translation.id = shop_bonus.id
					WHERE table_name = 'shop_bonus' AND field_name = 'description' AND language = '$lang' 
					AND shop_bonus.article_id = article.article_id limit 1)
				, IFNULL((SELECT t1.value
				FROM translation t1
				WHERE t1.table_name = 'article'
				AND t1.field_name = 'description'
				AND t1.language = '$lang'
				AND t1.id = article.article_id), (SELECT t2.value
				FROM translation t2
				WHERE t2.table_name = 'article'
				AND t2.field_name = 'description'
				AND t2.language = '$lang'
				AND t2.id = article.article_id
				AND IFNULL(t2.value,'')<>'' limit 1)
				)) as description   
			, invoice.total_shipping, invoice.total_cod
			, auction.saved_id
			, fget_AType($auction_number, $txnid) type
			, auction.siteid
			, IF(orders.sent, CONCAT('Mark as shipped by ',IFNULL(u.name,orders.delivery_username),' on ', IFNULL(tl.updated, '(unknown)')), 'Ready to ship') state
			, u.username state_username
			, tl.updated state_updated
			, IF(wwo.id, CONCAT('Ordered from WWO#',wwo.id,';'), '') state_wwo
			, IF(orders.spec_order_id, CONCAT('Ordered from OPS#',orders.spec_order_id,';'), '') state_ops
			, IF(orders.ready2pickup, CONCAT('Mark as ready to pickup by ',IFNULL(u_r2p.name,tl_r2p.username),' on ', IFNULL(tl_r2p.updated, '(unknown)')), 'Not ready to pickup') state_pickup
			, (SELECT 
					CONCAT('<a target=\"_blank\" href=\"/mobile.php?branch=ll&step=4&warehouse_id=', ll.warehouse_id, '&method_id=', ll.method_id, '&ll_id=', ll.id, '\">#', ll.id,'</a>') 
				FROM tn_orders
					LEFT JOIN tracking_numbers tn1 ON tn1.id = tn_orders.tn_id
					LEFT JOIN mobile_loading_list_tn ll_tn ON ll_tn.tracking_number = tn1.number and ll_tn.ll_id != 0
					LEFT JOIN mobile_loading_list ll ON ll.id = ll_tn.ll_id
				WHERE tn_orders.order_id=orders.id LIMIT 1) ll_link
			, (select GROUP_CONCAT(CONCAT('Tracking number <a target=\"_blank\" href=\"'
				,REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(m.tracking_url,''), '[[number]]', tn.number), '[[zip]]', au_zip_shipping.value),'[[country_code2]]','{$country_code2}'), '&', '&amp;'),'\">'
				, tn.number, '</a>, ',tn_orders.mobile,'packed by ', IFNULL(u1.name,tn.username)
					, ' on ', tn.date_time) separator '<br>') 
				from tracking_numbers tn 
				left join auction_par_varchar au_zip_shipping on tn.auction_number=au_zip_shipping.auction_number
					and tn.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
				join tn_orders on tn.id=tn_orders.tn_id
                LEFT JOIN shipping_method m 
                    ON m.shipping_method_id=tn.shipping_method
				left join users u1 on tn.username=u1.username
				where tn_orders.order_id=orders.id
				$method_filter_str) numbers
			, (select GROUP_CONCAT(tn.number)
				from tracking_numbers tn 
				left join auction_par_varchar au_zip_shipping on tn.auction_number=au_zip_shipping.auction_number
					and tn.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
				join tn_orders on tn.id=tn_orders.tn_id
                LEFT JOIN shipping_method m 
                    ON m.shipping_method_id=tn.shipping_method
				left join users u1 on tn.username=u1.username
				where tn_orders.order_id=orders.id
				$method_filter_str) tn_numbers
			, orders.alias_id article_alias_id
			, t_alias_name.value alias_name
			, t_alias_description.value alias_description
			, tl.updated scanning_date
			, (select CONCAT(IF(new_value=1, 'Done', 'Undone'), ' by ', IFNULL(users.name, total_log.username), ' on ', Updated)
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='spec_order'
				and TableID=orders.id order by Updated desc limit 1) story
			, (select CONCAT('Changed by ', IFNULL(users.name, total_log.username), ' on ', Updated)
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='wwo_order_id'
				and TableID=orders.id order by Updated desc limit 1) wwo_story
			, (select CONCAT(IF(new_value=1, 'Done', 'Undone'), ' by ', IFNULL(users.name, total_log.username), ' on ', Updated)
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='new_article'
				and TableID=orders.id order by Updated desc limit 1) new_article_story
			, (select CONCAT(IF(new_value=1, 'Done', 'Undone'), ' by ', IFNULL(users.name, total_log.username), ' on ', Updated)
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='new_article_completed'
				and TableID=orders.id order by Updated desc limit 1) new_article_completed_story
			, wwa.taken wwa_taken
			, wwa.from_warehouse
			, wwa.to_warehouse
			, CONCAT(new_article.article_id, ': ', t_new_article.value) new_article_id_name
			, CONCAT(orders.new_article_id, ':', orders.new_article_warehouse_id) new_article_id_warehouse_id
			, wwo.id real_wwo_id
			, (select count(*) from orders o
				join auction au on o.auction_number=au.auction_number and o.txnid=au.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				where o.article_id=orders.article_id and o.manual=0 and  o.new_article_completed=0
				and o.new_article and o.new_article_id and o.new_article_qnt 
				and NOT IFNULL(o.new_article_not_deduct,0)
				and IFNULL(mau.deleted, au.deleted)=0) new_article_other_qty
			, o_uc.new_article_id uncomplete_article_id
			, o_uc.auction_number unc_auction_number
			, o_uc.txnid unc_txnid
			, w_uc.wwo_id unc_wwo_id
			, wwa_new.wwo_id new_wwo_id
			, w_new.name new_article_warehouse
			, (select GROUP_CONCAT( distinct IFNULL(
                            CONCAT('<a href=\"barcodes.php?filter[code]=',b.barcode,'\" target=\"_blank\">',b.barcode,'</a>', IF (b.new_op_order_id,CONCAT(' <span class=\"new-op-order\">OP#',b.new_op_order_id,'</span>'),''), '<br />'" . (!$loggedUser->data->barcode_shipped_unassign ? ", IF(orders.sent,'',CONCAT('<input type=\"button\" value=\"Unassign\" onClick=\"clear_order_barcode(',b.id,',',boo.id,')\">'))" : ", '<input type=\"button\" value=\"Unassign\" onClick=\"clear_order_barcode(',b.id,',',boo.id,')\">'") . "), 
                            CONCAT('NO BARCODE ALERT '," . (!$loggedUser->data->barcode_shipped_unassign ? " IF(orders.sent,'',CONCAT('<input type=\"button\" value=\"Unassign\" onClick=\"nobarcodes_clear(',boo.id,')\">'))" : " '<input type=\"button\" value=\"Unassign\" onClick=\"nobarcodes_clear(',boo.id,')\">'") . ")
					) SEPARATOR '<br>')  
				from barcode_object boo
				left join vbarcode b on boo.barcode_id=b.id and IF(ISNULL(b.barcode), 1, 0) = 0
				where boo.obj='orders' and boo.obj_id=orders.id/* and IFNULL(b.inactive,0)=0*/) barcodes
			, (select concat(ROUND(dimension_l/2.54),'/',ROUND(dimension_w/2.54),'/',ROUND(dimension_h/2.54)) 
					from article_parcel where article_id=orders.article_id
					limit 1) dimension
			, IF(auction.available
				, t214.value
					, IF(auction.available_date='0000-00-00'
						, t215.value
						, CONCAT(t216.value, ' [[DATE]]')
					)
			) available_text
			, auction.available_date date2replace
			, si.date_format_invoice
			, alias.name ShopDesription
			, offer_name.name alias
			, REPLACE(article.picture_URL,'_image.jpg','_x_200_image.jpg') picture_URL_200
			, w_res.mobile_only res_mobile_only
			, article.hide_in_route
			, article.hide_in_order
			, article.hide_in_invoice
			, article.hide_in_package_list
			, offer_group.position offer_group_position
			, article_list.position article_list_position
			, bod.barcode_id as decompleted_barcode_id
			, bor.barcode_id as recompleted_barcode_id
			, user_set_repack.name AS repack_who_set
			, tl2.Updated AS repack_when_set
            FROM orders
			LEFT JOIN (select * from wwo_article where uncomplete_article_order_id>0) wwa_new ON orders.id = wwa_new.uncomplete_article_order_id
			left JOIN ww_order wwo_new ON wwo_new.id = wwa_new.wwo_id
			LEFT JOIN users driver_users_new ON wwo_new.driver_username=driver_users_new.username 
			left JOIN warehouse w_driver_new ON driver_users_new.driver_warehouse_id = w_driver_new.warehouse_id
			left JOIN warehouse w_new ON IF(wwa_new.delivered, wwa_new.to_warehouse, IF(wwa_new.taken, w_driver_new.warehouse_id, orders.new_article_warehouse_id)) 
				= w_new.warehouse_id
			LEFT JOIN orders o_uc ON o_uc.id = orders.uncomplete_article_order_id
			LEFT JOIN (select * from wwo_article where uncomplete_article_order_id>0) w_uc ON o_uc.id = w_uc.uncomplete_article_order_id
            left JOIN article_list ON article_list.article_list_id = orders.article_list_id
            left JOIN offer_group ON article_list.group_id = offer_group.offer_group_id
			JOIN article ON article.article_id = orders.article_id and article.admin_id=orders.manual
			left join article_warehouse_place on article_warehouse_place.article_id=orders.article_id
				and article_warehouse_place.warehouse_id=orders.reserve_warehouse_id
			left JOIN warehouse w_res ON w_res.warehouse_id=orders.reserve_warehouse_id
			left JOIN article new_article ON new_article.article_id = orders.new_article_id and new_article.admin_id=orders.manual
				and orders.new_article and new_article.article_id<>''
			left join translation t_new_article on t_new_article.table_name = 'article'
				AND t_new_article.field_name = 'name'
				AND t_new_article.language = '$lang'
				AND t_new_article.id = new_article.article_id
			LEFT JOIN custom_number ON article.custom_number_id = custom_number.id
			LEFT JOIN auction ON auction.auction_number=orders.auction_number and auction.txnid=orders.txnid
			LEFT JOIN seller_information si ON auction.username=si.username
			left join offer_name on auction.name_id=offer_name.id
			LEFT JOIN invoice ON invoice.invoice_number=auction.invoice_number
			left join total_log tl on tl.tableid=orders.id and tl.table_name='orders' 
				and tl.field_name='sent' and tl.New_value=1
			left join total_log tl_r2p on tl_r2p.tableid=orders.id and tl_r2p.table_name='orders' 
				and tl_r2p.field_name='ready2pickup' and tl_r2p.New_value=1
			left join users u on u.username=orders.delivery_username
			left join users u_r2p on u_r2p.system_username=tl_r2p.username
			left join translation t_alias_name on t_alias_name.table_name = 'article_alias'
				AND t_alias_name.field_name = 'name'
				AND t_alias_name.language = '$lang'
				AND t_alias_name.id = orders.alias_id
			left join translation t_alias_description on t_alias_description.table_name = 'article_alias'
				AND t_alias_description.field_name = 'description'
				AND t_alias_description.language = '$lang'
				AND t_alias_description.id = orders.alias_id
			LEFT JOIN wwo_article wwa ON orders.wwo_order_id=wwa.id
			LEFT JOIN ww_order wwo ON wwa.wwo_id=wwo.id
			## for SA
			left join saved_auctions sa on sa.id=auction.saved_id
			left join saved_params sp_master on sp_master.par_key='master_sa' and sp_master.saved_id=sa.id
			left join saved_auctions master_sa on sp_master.par_value*1=master_sa.id
			left join saved_params sp_offer on sp_offer.par_key='offer_id' and sp_offer.saved_id=sa.id
			left join offer orig_o on orig_o.offer_id=sp_offer.par_value*1
			left join shop on shop.id = fget_AShop(auction.auction_number, auction.txnid)
			left join translation tShopDesription on tShopDesription.id=IF(shop.master_ShopDesription, IFNULL(master_sa.id, sa.id), sa.id)
				and tShopDesription.table_name='sa' 
				and tShopDesription.field_name='ShopDesription' 
				and tShopDesription.language = '$lang' 
			left join offer_name alias on tShopDesription.value=alias.id
			join translation t214 on t214.id=214
				and t214.table_name='translate_shop' 
				and t214.field_name='translate_shop' 
				and t214.language = '$lang'
			join translation t215 on t215.id=215
				and t215.table_name='translate_shop' 
				and t215.field_name='translate_shop' 
				and t215.language = '$lang'
			join translation t216 on t216.id=216
				and t216.table_name='translate_shop' 
				and t216.field_name='translate_shop' 
				and t216.language = '$lang'
			LEFT JOIN barcode_object bod ON bod.obj_id=orders.id AND bod.obj='decompleted_article'
			LEFT JOIN barcode_object bor ON bor.obj_id=orders.id AND bor.obj='recompleted_article'
			#@todo maybe we have to do stand-alone sql query for it
			LEFT JOIN total_log tl2 ON
				tl2.TableID = orders.id
				AND tl2.Table_name = 'orders'
				AND tl2.Field_name = 'repack'
                        LEFT JOIN users user_set_repack ON user_set_repack.system_username = tl2.username
            WHERE orders.hidden in ($hidden) and (orders.auction_number=" . $auction_number . " AND orders.txnid=" . $txnid . ")
		UNION ALL
			SELECT  article.barcode_type, article.mobile_warning, auction.end_time
				, IF(article.admin_id=2, 
					(SELECT priority FROM shop_bonus where shop_bonus.article_id = article.article_id limit 1)
					, auction.priority) priority
				, IF(article.admin_id=2, 
					(SELECT show_in_table FROM shop_bonus where shop_bonus.article_id = article.article_id limit 1)
					,1) show_in_table,
					IF(article.admin_id=2, 
					(SELECT color FROM shop_bonus where shop_bonus.article_id = article.article_id limit 1)
					,'#000000') color,
				auction.code_id, fget_AShop(auction.auction_number, auction.txnid) shop_id, 
				orders.*,article.weight, article.weight_per_single_unit, article.volume, article.volume_per_single_unit
				, article_warehouse_place.warehouse_place
				, article_list.group_id, 
			custom_number.custom_number_eu, custom_number.custom_number_ch, custom_number.custom_tarif_eu, custom_number.custom_tarif_ch, 
			orders.manual as admin_id
			, IF(article.admin_id=2, 
(SELECT value FROM translation join shop_bonus on translation.id = shop_bonus.id
WHERE table_name = 'shop_bonus' AND field_name = 'title' AND language = '$lang' 
AND shop_bonus.article_id = article.article_id limit 1)
				, IF(article.admin_id=3,
(SELECT CONCAT(shop_promo_codes.id, ': ', IF(shop_promo_codes.descr_is_name, shop_promo_codes.name, translation.value)
, '<br>', code, IF(IFNULL(security_code, '')='', '', CONCAT(' / ',security_code))) 
FROM shop_promo_codes left join translation on translation.id = shop_promo_codes.id
and table_name = 'shop_promo_codes' AND field_name = 'name' AND language = '$lang'
where shop_promo_codes.article_id = article.article_id limit 1)
			, IF(orders.article_id='', article.name, 
				IFNULL((SELECT t1.value
				FROM translation t1
				WHERE t1.table_name = 'article'
				AND t1.field_name = 'name'
				AND t1.language = '$lang'
				AND t1.id = article.article_id), (SELECT t2.value
				FROM translation t2
				WHERE t2.table_name = 'article'
				AND t2.field_name = 'name'
				AND t2.language = '$lang'
				AND t2.id = article.article_id
				AND IFNULL(t2.value,'')<>'' limit 1)
				)))) as name, 
			(SELECT showit FROM shop_bonus where shop_bonus.article_id = article.article_id and article.admin_id=2) showit,
			article.supplier_article_id, article.country_code as article_country_code,
			article.items_per_shipping_unit
			, IF(article.admin_id=2,
				(SELECT value FROM translation join shop_bonus on translation.id = shop_bonus.id
					WHERE table_name = 'shop_bonus' AND field_name = 'description' AND language = '$lang' 
					AND shop_bonus.article_id = article.article_id limit 1)
				, IFNULL((SELECT t1.value
				FROM translation t1
				WHERE t1.table_name = 'article'
				AND t1.field_name = 'description'
				AND t1.language = '$lang'
				AND t1.id = article.article_id), (SELECT t2.value
				FROM translation t2
				WHERE t2.table_name = 'article'
				AND t2.field_name = 'description'
				AND t2.language = '$lang'
				AND t2.id = article.article_id
				AND IFNULL(t2.value,'')<>'' limit 1)
				)) as description   
			, invoice.total_shipping, invoice.total_cod
			, auction.saved_id
			, fget_AType($auction_number, $txnid) type
			, auction.siteid
			, IF(orders.sent, CONCAT('Mark as shipped by ',IFNULL(u.name,orders.delivery_username),' on ', IFNULL(tl.updated, '(unknown)')), 'Ready to ship') state
			, u.username state_username
			, tl.updated state_updated
			, IF(wwo.id, CONCAT('Ordered from WWO#',wwo.id,';'), '') state_wwo
			, IF(orders.spec_order_id, CONCAT('Ordered from OPS#',orders.spec_order_id,';'), '') state_ops
			, IF(orders.ready2pickup, CONCAT('Mark as ready to pickup by ',IFNULL(u_r2p.name,tl_r2p.username),' on ', IFNULL(tl_r2p.updated, '(unknown)')), 'Not ready to pickup') state_pickup
			, (SELECT 
					CONCAT('<a target=\"_blank\" href=\"/mobile.php?branch=ll&step=4&warehouse_id=', ll.warehouse_id, '&method_id=', ll.method_id, '&ll_id=', ll.id, '\">#', ll.id,'</a>') 
				FROM tn_orders
					LEFT JOIN tracking_numbers tn1 ON tn1.id = tn_orders.tn_id
					LEFT JOIN mobile_loading_list_tn ll_tn ON ll_tn.tracking_number = tn1.number and ll_tn.ll_id != 0
					LEFT JOIN mobile_loading_list ll ON ll.id = ll_tn.ll_id
				WHERE tn_orders.order_id=orders.id LIMIT 1) ll_link
			, (select GROUP_CONCAT(CONCAT('Tracking number <a target=\"_blank\" href=\"'
				,REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(m.tracking_url,''), '[[number]]', tn.number), '[[zip]]', au_zip_shipping.value),'[[country_code2]]','{$country_code2}'), '&', '&amp;'),'\">'
				, tn.number, '</a>, ',tn_orders.mobile,'packed by ', IFNULL(u1.name,tn.username)
					, ' on ', tn.date_time) separator '<br>') 
				from tracking_numbers tn 
				left join auction_par_varchar au_zip_shipping on tn.auction_number=au_zip_shipping.auction_number
					and tn.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
				join tn_orders on tn.id=tn_orders.tn_id
                LEFT JOIN shipping_method m 
                    ON m.shipping_method_id=tn.shipping_method
				left join users u1 on tn.username=u1.username
				where tn_orders.order_id=orders.id) numbers
			, (select GROUP_CONCAT(tn.number)
				from tracking_numbers tn 
				left join auction_par_varchar au_zip_shipping on tn.auction_number=au_zip_shipping.auction_number
					and tn.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
				join tn_orders on tn.id=tn_orders.tn_id
                LEFT JOIN shipping_method m 
                    ON m.shipping_method_id=tn.shipping_method
				left join users u1 on tn.username=u1.username
				where tn_orders.order_id=orders.id) tn_numbers
			, orders.alias_id article_alias_id
			, t_alias_name.value alias_name
			, t_alias_description.value alias_description
			, tl.updated scanning_date
			, (select CONCAT(IF(new_value=1, 'Done', 'Undone'), ' by ', IFNULL(users.name, total_log.username), ' on ', Updated)
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='spec_order'
				and TableID=orders.id order by Updated desc limit 1) story
			, (select CONCAT('Changed by ', IFNULL(users.name, total_log.username), ' on ', Updated)
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='wwo_order_id'
				and TableID=orders.id order by Updated desc limit 1) wwo_story
			, (select CONCAT(IF(new_value=1, 'Done', 'Undone'), ' by ', IFNULL(users.name, total_log.username), ' on ', Updated)
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='new_article'
				and TableID=orders.id order by Updated desc limit 1) new_article_story
			, (select CONCAT(IF(new_value=1, 'Done', 'Undone'), ' by ', IFNULL(users.name, total_log.username), ' on ', Updated)
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='new_article_completed'
				and TableID=orders.id order by Updated desc limit 1) new_article_completed_story
			, wwa.taken wwa_taken
			, wwa.from_warehouse
			, wwa.to_warehouse
			, CONCAT(new_article.article_id, ': ', t_new_article.value) new_article_id_name
			, CONCAT(orders.new_article_id, ':', orders.new_article_warehouse_id) new_article_id_warehouse_id
			, wwo.id real_wwo_id
			, (select count(*) from orders o
				join auction au on o.auction_number=au.auction_number and o.txnid=au.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				where o.article_id=orders.article_id and o.manual=0 and  o.new_article_completed=0
				and o.new_article and o.new_article_id and o.new_article_qnt 
				and NOT IFNULL(o.new_article_not_deduct,0)
				and IFNULL(mau.deleted, au.deleted)=0) new_article_other_qty
			, o_uc.new_article_id uncomplete_article_id
			, o_uc.auction_number unc_auction_number
			, o_uc.txnid unc_txnid
			, w_uc.wwo_id unc_wwo_id
			, wwa_new.wwo_id new_wwo_id
			, w_new.name new_article_warehouse
			, (select GROUP_CONCAT( distinct IFNULL(CONCAT('<a href=\"barcodes.php?filter[code]=',b.barcode,'\" target=\"_blank\">',b.barcode,'</a>', IF (b.new_op_order_id,CONCAT(' <span class=\"new-op-order\">OP#',b.new_op_order_id,'</span>'),''), '<br />'" . (!$loggedUser->data->barcode_shipped_unassign ? ", IF(orders.sent,'',CONCAT('<input type=\"button\" value=\"Unassign\" onClick=\"clear_order_barcode(',b.id,',',boo.id,')\">'))" : ", '<input type=\"button\" value=\"Unassign\" onClick=\"clear_order_barcode(',b.id,',',boo.id,')\">'") . "
						), CONCAT('NO BARCODE ALERT '," . (!$loggedUser->data->barcode_shipped_unassign ? " IF(orders.sent,'',CONCAT('<input type=\"button\" value=\"Unassign\" onClick=\"nobarcodes_clear(',boo.id,')\">'))" : " '<input type=\"button\" value=\"Unassign\" onClick=\"nobarcodes_clear(',boo.id,')\">'") . ")
					) SEPARATOR '<br>')  
				from barcode_object boo
				left join vbarcode b on boo.barcode_id=b.id and IF(ISNULL(b.barcode), 1, 0) = 0
				where boo.obj='orders' and boo.obj_id=orders.id/* and IFNULL(b.inactive,0)=0*/) barcodes
			, (select concat(ROUND(dimension_l/2.54),'/',ROUND(dimension_w/2.54),'/',ROUND(dimension_h/2.54)) 
					from article_parcel where article_id=orders.article_id
					limit 1) dimension
			, IF(auction.available
				, t214.value
					, IF(auction.available_date='0000-00-00'
						, t215.value
						, CONCAT(t216.value, ' [[DATE]]')
					)
			) available_text
			, auction.available_date date2replace
			, si.date_format_invoice
			, alias.name ShopDesription
			, offer_name.name alias
			, REPLACE(article.picture_URL,'_image.jpg','_x_200_image.jpg') picture_URL_200
			, w_res.mobile_only res_mobile_only
			, article.hide_in_route
			, article.hide_in_order
			, article.hide_in_invoice
			, article.hide_in_package_list
			, offer_group.position offer_group_position
			, article_list.position article_list_position
			, bod.barcode_id as decompleted_barcode_id
			, bor.barcode_id as recompleted_barcode_id
			, user_set_repack.name AS repack_who_set
			, tl2.Updated AS repack_when_set
            FROM orders 
			LEFT JOIN (select * from wwo_article where uncomplete_article_order_id>0) wwa_new ON orders.id = wwa_new.uncomplete_article_order_id
			left JOIN ww_order wwo_new ON wwo_new.id = wwa_new.wwo_id
			LEFT JOIN users driver_users_new ON wwo_new.driver_username=driver_users_new.username 
			left JOIN warehouse w_driver_new ON driver_users_new.driver_warehouse_id = w_driver_new.warehouse_id
			left JOIN warehouse w_new ON IF(wwa_new.delivered, wwa_new.to_warehouse, IF(wwa_new.taken, w_driver_new.warehouse_id, orders.new_article_warehouse_id)) 
				= w_new.warehouse_id
			LEFT JOIN orders o_uc ON o_uc.id = orders.uncomplete_article_order_id
			LEFT JOIN (select * from wwo_article where uncomplete_article_order_id>0) w_uc ON o_uc.id = w_uc.uncomplete_article_order_id
			JOIN auction ON auction.auction_number = orders.auction_number AND auction.txnid = orders.txnid
			JOIN seller_information si ON auction.username=si.username
            left JOIN article_list ON article_list.article_list_id = orders.article_list_id
            left JOIN offer_group ON article_list.group_id = offer_group.offer_group_id
			JOIN article ON article.article_id = orders.article_id and article.admin_id=orders.manual
			left join article_warehouse_place on article_warehouse_place.article_id=orders.article_id
				and article_warehouse_place.warehouse_id=orders.reserve_warehouse_id
			left JOIN warehouse w_res ON w_res.warehouse_id=orders.reserve_warehouse_id
			left JOIN article new_article ON new_article.article_id = orders.new_article_id and new_article.admin_id=orders.manual
				and orders.new_article and new_article.article_id<>''
			left join translation t_new_article on t_new_article.table_name = 'article'
				AND t_new_article.field_name = 'name'
				AND t_new_article.language = '$lang'
				AND t_new_article.id = new_article.article_id
			LEFT JOIN custom_number ON article.custom_number_id = custom_number.id
			left join offer_name on auction.name_id=offer_name.id
			LEFT JOIN invoice ON invoice.invoice_number=auction.invoice_number
			left join total_log tl on tl.tableid=orders.id and tl.table_name='orders' 
				and tl.field_name='sent' and tl.New_value=1
			left join total_log tl_r2p on tl_r2p.tableid=orders.id and tl_r2p.table_name='orders' 
				and tl_r2p.field_name='ready2pickup' and tl_r2p.New_value=1
			left join users u on u.username=orders.delivery_username
			left join users u_r2p on u_r2p.system_username=tl_r2p.username
			left join translation t_alias_name on t_alias_name.table_name = 'article_alias'
				AND t_alias_name.field_name = 'name'
				AND t_alias_name.language = '$lang'
				AND t_alias_name.id = orders.alias_id
			left join translation t_alias_description on t_alias_description.table_name = 'article_alias'
				AND t_alias_description.field_name = 'description'
				AND t_alias_description.language = '$lang'
				AND t_alias_description.id = orders.alias_id
			LEFT JOIN wwo_article wwa ON orders.wwo_order_id=wwa.id
			LEFT JOIN ww_order wwo ON wwa.wwo_id=wwo.id
			## for SA
			left join saved_auctions sa on sa.id=auction.saved_id
			left join saved_params sp_master on sp_master.par_key='master_sa' and sp_master.saved_id=sa.id
			left join saved_auctions master_sa on sp_master.par_value*1=master_sa.id
			left join saved_params sp_offer on sp_offer.par_key='offer_id' and sp_offer.saved_id=sa.id
			left join offer orig_o on orig_o.offer_id=sp_offer.par_value*1
			left join shop on shop.id = fget_AShop(auction.auction_number, auction.txnid)
			left join translation tShopDesription on tShopDesription.id=IF(shop.master_ShopDesription, IFNULL(master_sa.id, sa.id), sa.id)
				and tShopDesription.table_name='sa' 
				and tShopDesription.field_name='ShopDesription' 
				and tShopDesription.language = '$lang'
			left join offer_name alias on tShopDesription.value=alias.id
			join translation t214 on t214.id=214
				and t214.table_name='translate_shop' 
				and t214.field_name='translate_shop' 
				and t214.language = '$lang'
			join translation t215 on t215.id=215
				and t215.table_name='translate_shop' 
				and t215.field_name='translate_shop' 
				and t215.language = '$lang'
			join translation t216 on t216.id=216
				and t216.table_name='translate_shop' 
				and t216.field_name='translate_shop' 
				and t216.language = '$lang'
			LEFT JOIN barcode_object bod ON bod.obj_id=orders.id AND bod.obj='decompleted_article'
			LEFT JOIN barcode_object bor ON bor.obj_id=orders.id AND bor.obj='recompleted_article'
			#@todo maybe we have to do stand-alone sql query for it
			LEFT JOIN total_log tl2 ON
				tl2.TableID = orders.id
				AND tl2.Table_name = 'orders'
				AND tl2.Field_name = 'repack'
                        LEFT JOIN users user_set_repack ON user_set_repack.system_username = tl2.username
            WHERE auction.deleted=0
				and orders.hidden in ($hidden) and " . $auction_number . ">0 and auction.main_auction_number =" . $auction_number . "
				AND auction.main_txnid =" . $txnid . "
            ORDER BY repack_when_set DESC
			) t
	            GROUP BY t.id
				order by manual, auction_number, ordering, IFNULL(main_id,0), offer_group_position, article_list_position";
        }
        else {
            $q = "select t.*, CONCAT(article_id, ': ', IF (custom_title IS NULL OR custom_title = '', name, custom_title) ) as custom_title_id
			from (SELECT article.barcode_type, article.mobile_warning
				, IF(article.admin_id=2, 
					(SELECT priority FROM shop_bonus where shop_bonus.article_id = article.article_id limit 1)
					, auction.priority) priority
				, IF(article.admin_id=2, 
					(SELECT show_in_table FROM shop_bonus where shop_bonus.article_id = article.article_id limit 1)
					,1) show_in_table,
					IF(article.admin_id=2, 
					(SELECT color FROM shop_bonus where shop_bonus.article_id = article.article_id limit 1)
					,'#000000') color,
				auction.code_id, fget_AShop(auction.auction_number, auction.txnid) shop_id, 
				orders.*,article.weight, article.weight_per_single_unit, article.volume, 
			article.volume_per_single_unit
			, article_warehouse_place.warehouse_place
			, article_list.group_id, 
			custom_number.custom_number_eu, custom_number.custom_number_ch, custom_number.custom_tarif_eu, custom_number.custom_tarif_ch, 
			orders.manual as admin_id
			, IF(article.admin_id=2, 
(SELECT value FROM translation join shop_bonus on translation.id = shop_bonus.id
WHERE table_name = 'shop_bonus' AND field_name = 'title' AND language = '$lang' 
AND shop_bonus.article_id = article.article_id limit 1)
				, IF(article.admin_id=3,
(SELECT CONCAT(shop_promo_codes.id, ': ', IF(shop_promo_codes.descr_is_name, shop_promo_codes.name, translation.value)
, '<br>', code, IF(IFNULL(security_code, '')='', '', CONCAT(' / ',security_code))) 
FROM shop_promo_codes left join translation on translation.id = shop_promo_codes.id
and table_name = 'shop_promo_codes' AND field_name = 'name' AND language = '$lang'
where shop_promo_codes.article_id = article.article_id limit 1)
			, IF(orders.article_id='', article.name, 
				IFNULL((SELECT t1.value
				FROM translation t1
				WHERE t1.table_name = 'article'
				AND t1.field_name = 'name'
				AND t1.language = '$lang'
				AND t1.id = article.article_id), (SELECT t2.value
				FROM translation t2
				WHERE t2.table_name = 'article'
				AND t2.field_name = 'name'
				AND t2.language = '$lang'
				AND t2.id = article.article_id
				AND IFNULL(t2.value,'')<>'' limit 1)
				)))) as name, 
			(SELECT showit FROM shop_bonus where shop_bonus.article_id = article.article_id and article.admin_id=2) showit,
			article.supplier_article_id, article.country_code as article_country_code,
			article.items_per_shipping_unit
			, IF(article.admin_id=2,
				(SELECT value FROM translation join shop_bonus on translation.id = shop_bonus.id
					WHERE table_name = 'shop_bonus' AND field_name = 'description' AND language = '$lang' 
					AND shop_bonus.article_id = article.article_id limit 1)
				, IFNULL((SELECT t1.value
				FROM translation t1
				WHERE t1.table_name = 'article'
				AND t1.field_name = 'description'
				AND t1.language = '$lang'
				AND t1.id = article.article_id), (SELECT t2.value
				FROM translation t2
				WHERE t2.table_name = 'article'
				AND t2.field_name = 'description'
				AND t2.language = '$lang'
				AND t2.id = article.article_id
				AND IFNULL(t2.value,'')<>'' limit 1)
				)) as description   
			, invoice.total_shipping, invoice.total_cod
			, auction.saved_id
			, fget_AType($auction_number, $txnid) type
			, auction.siteid
			, IF(orders.sent, CONCAT('Mark as shipped by ',IFNULL(u.name,orders.delivery_username),' on ', IFNULL(tl.updated, '(unknown)')), 'Ready to ship') state
			, u.username state_username
			, tl.updated state_updated
			, IF(wwo.id, CONCAT('Ordered from WWO#',wwo.id,';'), '') state_wwo
			, IF(orders.spec_order_id, CONCAT('Ordered from OPS#',orders.spec_order_id,';'), '') state_ops
			, (select GROUP_CONCAT(CONCAT('Tracking number <a target=\"_blank\" href=\"'
				,REPLACE(REPLACE(REPLACE(REPLACE(IFNULL(m.tracking_url,''), '[[number]]', tn.number), '[[zip]]', au_zip_shipping.value),'[[country_code2]]','{$country_code2}'), '&', '&amp;'),'\">'
				, tn.number, '</a>, ',tn_orders.mobile,'packed by ', IFNULL(u1.name,tn.username)
					, ' on ', tn.date_time) separator '<br>') 
				from tracking_numbers tn 
				left join auction_par_varchar au_zip_shipping on tn.auction_number=au_zip_shipping.auction_number
					and tn.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
				join tn_orders on tn.id=tn_orders.tn_id
                LEFT JOIN shipping_method m 
                    ON m.shipping_method_id=tn.shipping_method
				left join users u1 on tn.username=u1.username
				where tn_orders.order_id=orders.id) numbers
			, (select GROUP_CONCAT(tn.number) 
				from tracking_numbers tn 
				left join auction_par_varchar au_zip_shipping on tn.auction_number=au_zip_shipping.auction_number
					and tn.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
				join tn_orders on tn.id=tn_orders.tn_id
                LEFT JOIN shipping_method m 
                    ON m.shipping_method_id=tn.shipping_method
				left join users u1 on tn.username=u1.username
				where tn_orders.order_id=orders.id) tn_numbers
			, orders.alias_id article_alias_id
			, t_alias_name.value alias_name
			, t_alias_description.value alias_description
			, tl.updated scanning_date
			, (select CONCAT(IF(new_value=1, 'Done', 'Undone'), ' by ', IFNULL(users.name, total_log.username), ' on ', Updated)
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='spec_order'
				and TableID=orders.id order by Updated desc limit 1) story
			, (select CONCAT('Changed by ', IFNULL(users.name, total_log.username), ' on ', Updated)
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='wwo_order_id'
				and TableID=orders.id order by Updated desc limit 1) wwo_story
			, (select CONCAT(IF(new_value=1, 'Done', 'Undone'), ' by ', IFNULL(users.name, total_log.username), ' on ', Updated)
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='new_article'
				and TableID=orders.id order by Updated desc limit 1) new_article_story
			, (select CONCAT(IF(new_value=1, 'Done', 'Undone'), ' by ', IFNULL(users.name, total_log.username), ' on ', Updated)
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='new_article_completed'
				and TableID=orders.id order by Updated desc limit 1) new_article_completed_story
			, wwa.taken wwa_taken
			, wwa.from_warehouse
			, wwa.to_warehouse
			, CONCAT(new_article.article_id, ': ', t_new_article.value) new_article_id_name
			, CONCAT(orders.new_article_id, ':', orders.new_article_warehouse_id) new_article_id_warehouse_id
			, wwo.id real_wwo_id
			, (select count(*) from orders o
				join auction au on o.auction_number=au.auction_number and o.txnid=au.txnid
				left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
				where o.article_id=orders.article_id and o.manual=0 and  o.new_article_completed=0
				and o.new_article and o.new_article_id and o.new_article_qnt 
				and NOT IFNULL(o.new_article_not_deduct,0)
				and IFNULL(mau.deleted, au.deleted)=0) new_article_other_qty
			, o_uc.new_article_id uncomplete_article_id
			, o_uc.auction_number unc_auction_number
			, o_uc.txnid unc_txnid
			, w_uc.wwo_id unc_wwo_id
			, wwa_new.wwo_id new_wwo_id
			, w_new.name new_article_warehouse
			, (select GROUP_CONCAT( distinct IFNULL(CONCAT('<a href=\"barcodes.php?filter[code]=',b.barcode,'\" target=\"_blank\">',b.barcode,'</a>', IF (b.new_op_order_id,CONCAT(' <span class=\"new-op-order\">OP#',b.new_op_order_id,'</span>'),''), '<br>'" . (!$loggedUser->data->barcode_shipped_unassign ? ", IF(orders.sent,'',CONCAT('<input type=\"button\" value=\"Unassign\" onClick=\"clear_order_barcode(',b.id,',',boo.id,')\">'))" : ", '<input type=\"button\" value=\"Unassign\" onClick=\"clear_order_barcode(',b.id,',',boo.id,')\">'") . "
						), CONCAT('NO BARCODE ALERT '," . (!$loggedUser->data->barcode_shipped_unassign ? " IF(orders.sent,'',CONCAT('<input type=\"button\" value=\"Unassign\" onClick=\"nobarcodes_clear(',boo.id,')\">'))" : " '<input type=\"button\" value=\"Unassign\" onClick=\"nobarcodes_clear(',boo.id,')\">'") . ")
					) SEPARATOR '<br>')  
				from barcode_object boo
				left join vbarcode b on boo.barcode_id=b.id and IF(ISNULL(b.barcode), 1, 0) = 0
				where boo.obj='orders' and boo.obj_id=orders.id/* and IFNULL(b.inactive,0)=0*/) barcodes
			, (select concat(ROUND(dimension_l/2.54),'/',ROUND(dimension_w/2.54),'/',ROUND(dimension_h/2.54)) 
					from article_parcel where article_id=orders.article_id
					limit 1) dimension
			, IF(auction.available
				, t214.value
					, IF(auction.available_date='0000-00-00'
						, t215.value
						, CONCAT(t216.value, ' [[DATE]]')
					)
			) available_text
			, auction.available_date date2replace
			, si.date_format_invoice
			, alias.name ShopDesription
			, offer_name.name alias
			, REPLACE(article.picture_URL,'_image.jpg','_x_200_image.jpg') picture_URL_200
			, w_res.mobile_only res_mobile_only
			, article.hide_in_route
			, article.hide_in_order
			, article.hide_in_invoice
			, article.hide_in_package_list
			, offer_group.position offer_group_position
			, article_list.position article_list_position
			, bod.barcode_id as decompleted_barcode_id
			, bor.barcode_id as recompleted_barcode_id
            FROM orders
			LEFT JOIN (select * from wwo_article where uncomplete_article_order_id>0) wwa_new ON orders.id = wwa_new.uncomplete_article_order_id
			left JOIN ww_order wwo_new ON wwo_new.id = wwa_new.wwo_id
			LEFT JOIN users driver_users_new ON wwo_new.driver_username=driver_users_new.username 
			left JOIN warehouse w_driver_new ON driver_users_new.driver_warehouse_id = w_driver_new.warehouse_id
			left JOIN warehouse w_new ON IF(wwa_new.delivered, wwa_new.to_warehouse, IF(wwa_new.taken, w_driver_new.warehouse_id, orders.new_article_warehouse_id)) 
				= w_new.warehouse_id
			LEFT JOIN orders o_uc ON o_uc.id = orders.uncomplete_article_order_id
			LEFT JOIN (select * from wwo_article where uncomplete_article_order_id>0) w_uc ON o_uc.id = w_uc.uncomplete_article_order_id
            left JOIN article_list ON article_list.article_list_id = orders.article_list_id
            left JOIN offer_group ON article_list.group_id = offer_group.offer_group_id
			JOIN article ON article.article_id = orders.article_id and article.admin_id=orders.manual
			left join article_warehouse_place on article_warehouse_place.article_id=orders.article_id
				and article_warehouse_place.warehouse_id=orders.reserve_warehouse_id
			left JOIN warehouse w_res ON w_res.warehouse_id=orders.reserve_warehouse_id
			left JOIN article new_article ON new_article.article_id = orders.new_article_id and new_article.admin_id=orders.manual
				and orders.new_article and new_article.article_id<>''
			left join translation t_new_article on t_new_article.table_name = 'article'
				AND t_new_article.field_name = 'name'
				AND t_new_article.language = '$lang'
				AND t_new_article.id = new_article.article_id
			LEFT JOIN custom_number ON article.custom_number_id = custom_number.id
			LEFT JOIN auction ON auction.auction_number=orders.auction_number and auction.txnid=orders.txnid
			LEFT JOIN seller_information si ON auction.username=si.username
			left join offer_name on auction.name_id=offer_name.id
			LEFT JOIN invoice ON invoice.invoice_number=auction.invoice_number
			left join total_log tl on tl.tableid=orders.id and tl.table_name='orders' and tl.field_name='sent' and tl.New_value=1
			left join users u on u.username=orders.delivery_username
			left join translation t_alias_name on t_alias_name.table_name = 'article_alias'
				AND t_alias_name.field_name = 'name'
				AND t_alias_name.language = '$lang'
				AND t_alias_name.id = orders.alias_id
			left join translation t_alias_description on t_alias_description.table_name = 'article_alias'
				AND t_alias_description.field_name = 'description'
				AND t_alias_description.language = '$lang'
				AND t_alias_description.id = orders.alias_id
			LEFT JOIN wwo_article wwa ON orders.wwo_order_id=wwa.id
			LEFT JOIN ww_order wwo ON wwa.wwo_id=wwo.id
			## for SA
			left join saved_auctions sa on sa.id=auction.saved_id
			left join saved_params sp_master on sp_master.par_key='master_sa' and sp_master.saved_id=sa.id
			left join saved_auctions master_sa on sp_master.par_value*1=master_sa.id
			left join saved_params sp_offer on sp_offer.par_key='offer_id' and sp_offer.saved_id=sa.id
			left join offer orig_o on orig_o.offer_id=sp_offer.par_value*1
			left join shop on shop.id = fget_AShop(auction.auction_number, auction.txnid)
			left join translation tShopDesription on tShopDesription.id=IF(shop.master_ShopDesription, IFNULL(master_sa.id, sa.id), sa.id)
				and tShopDesription.table_name='sa' 
				and tShopDesription.field_name='ShopDesription' 
				and tShopDesription.language = '$lang' 
			left join offer_name alias on tShopDesription.value=alias.id
			join translation t214 on t214.id=214
				and t214.table_name='translate_shop' 
				and t214.field_name='translate_shop' 
				and t214.language = '$lang'
			join translation t215 on t215.id=215
				and t215.table_name='translate_shop' 
				and t215.field_name='translate_shop' 
				and t215.language = '$lang'
			join translation t216 on t216.id=216
				and t216.table_name='translate_shop' 
				and t216.field_name='translate_shop' 
				and t216.language = '$lang'
			LEFT JOIN barcode_object bod ON bod.obj_id=orders.id AND bod.obj='decompleted_article'
			LEFT JOIN barcode_object bor ON bor.obj_id=orders.id AND bor.obj='recompleted_article'
            WHERE orders.hidden in ($hidden) and (orders.auction_number=" . $auction_number . " AND orders.txnid=" . $txnid . ")) t
			order by manual, auction_number, ordering, IFNULL(main_id,0), offer_group_position, article_list_position";
        }
        
//        echo '<pre>'.htmlspecialchars($q).'</pre>';
//        exit;

//		if ($debug) echo '<br><br>'.$q.'<br><br>';
        file_put_contents('lastquery_orderlistall', $q);
        
//		  echo $q; die();
        if ($debug)
            echo 'order 1: ' . round((getmicrotime() - $time), 3) . '<br>';if ($debug)
            $time = getmicrotime();
        global $dbr_spec;
        $r = $dbr_spec->query($q);

        if ($debug)
            echo 'order 2: ' . round((getmicrotime() - $time), 3) . '<br>';if ($debug)
            $time = getmicrotime();
        if (PEAR::isError($r)) {
            echo $r->message;
            //echo '<pre>'; print_r($r);
            die();
            return;
        }
        $list = array();
        $auction = $dbr->getRow("select offer_id, lang 
			from auction where auction_number=" . $auction_number . " AND txnid=" . $txnid);
        if (!$auction->offer_id) {
            $sub_offer_id = $dbr->getOne("select max(offer_id) offer_id
				from auction where main_auction_number=" . $auction_number . " AND main_txnid=" . $txnid);
            if ($sub_offer_id) {
                $auction_alias = $dbr->getOne("select name from offer_name where deleted=0 and offer_id={$sub_offer_id}
				and lang='{$auction->lang}'
				 order by id desc limit 1");
            }
        }
        else {
            $auction_alias = $dbr->getOne("select name from offer_name where deleted=0 and offer_id={$auction->offer_id}
			and lang='{$auction->lang}'
			 order by id desc limit 1");
        }
        while ($line = $r->fetchRow()) {
            $line->available_text = str_replace('[[DATE]]', utf8_encode(strftime($line->date_format_invoice, strtotime($line->date2replace)))
                    , $line->available_text);
            if ($debug)
                echo 'order circle 0: ' . round((getmicrotime() - $time), 3) . '<br>';if ($debug)
                $time = getmicrotime();
            if (!strlen($line->alias))
                $line->alias = $auction_alias;
            $line->name = /* utf8_decode */($line->name);
//			$line->custom_title = utf8_decode($line->custom_title);
            $line->alias_name = /* utf8_decode */($line->alias_name);
            $line->custom_title_id = /* utf8_decode */($line->custom_title_id);
//			$line->custom_description = nl2br($line->custom_description);
            $line->description = ($line->description);
            if (!$line->new_article_qnt)
                $line->new_article_qnt = 1;
            $quantities = array();
            for ($i = 1; $i <= $line->quantity; $i++)
                $quantities[$i] = $i;
            $line->quantities = $quantities;
//			$line->description = nl2br($line->description);
            if ($line->type == '' && $line->oldprice == '0') {
                $line->oldprice = number_format(Offer::getShopPrice($db, $dbr, $line->saved_id, $line->article_list_id
                                , $lang, $line->siteid, 1), 2, '.', '');
            }
            if ($debug)
                echo 'order circle 1: ' . round((getmicrotime() - $time), 3) . '<br>';if ($debug)
                $time = getmicrotime();
            if ($complete) {
                if ($line->spec_order_id) {
                    $q = "select opo.* 
						, sum( opa.qnt_ordered ) as sum_ordered
				,(select CONCAT('<table border=\'1\'><tr><td nowrap>Container</td><td nowrap>Content</td><td nowrap>Dest Country</td><td nowrap>PTD</td><td nowrap>ETD</td><td nowrap>ETA</td><td nowrap>Arrival</td><td nowrap>Planed warehouse</td><td nowrap align=\'right\'>Quantity</td></tr>'
				,'<tr><td nowrap valign=\'top\'>',GROUP_CONCAT(
					CONCAT(IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00','<font color=\'gray\'>','')
						,IFNULL(opc.container_no,'')
						,IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00','</font>','')
					) order by opc.id separator '<br>')
				,'</td><td nowrap valign=\'top\'>',GROUP_CONCAT(
					CONCAT(IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00','<font color=\'gray\'>','')
						,IFNULL(opc.content,'')
						,IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00','</font>','')
					) order by opc.id separator '<br>')
				,'</td><td nowrap valign=\'top\'>',GROUP_CONCAT(
					CONCAT(IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00','<font color=\'gray\'>','')
						,IFNULL(t.value,'')
						,IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00','</font>','')
					) order by opc.id separator '<br>')
				,'</td><td nowrap valign=\'top\'>',GROUP_CONCAT(
					CONCAT(IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00','<font color=\'gray\'>','')
						,IFNULL(opc.PTD,'')
						,IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00','</font>','')
					) order by opc.id separator '<br>')
				,'</td><td nowrap valign=\'top\'>',GROUP_CONCAT(
					CONCAT(IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00','<font color=\'gray\'>','')
						,IFNULL(opc.EDD,'')
						,IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00','</font>','')
					) order by opc.id separator '<br>')
				,'</td><td nowrap valign=\'top\'>',GROUP_CONCAT(
					CONCAT(IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00','<font color=\'gray\'>','')
						,IFNULL(opc.EDA,'')
						,IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00','</font>','')
					) order by opc.id separator '<br>')
				,'</td><td nowrap valign=\'top\'>',GROUP_CONCAT(
					CONCAT(IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00','<font color=\'gray\'>','')
						,IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00',opc.arrival_date,'')
						,IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00','</font>','')
					) order by opc.id separator '<br>')
				,'</td><td nowrap valign=\'top\'>',GROUP_CONCAT(
					CONCAT(IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00','<font color=\'gray\'>','')
						,IFNULL(wc.name,'')
						,IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00','</font>','')
					) order by opc.id separator '<br>')
				,'</td><td nowrap valign=\'top\' align=\'right\'>',GROUP_CONCAT(
					CONCAT(IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00','<font color=\'gray\'>','')
						,IFNULL((select sum(qnt_ordered) from op_article opa 
							where opa.op_order_id=opc.order_id and opa.container_id=opc.id
						),0)
						,IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00','</font>','')
					) order by opc.id separator '<br>')
				,'</td></tr></table>') from op_order_container opc 
					LEFT JOIN warehouse wc ON opc.planned_warehouse_id = wc.warehouse_id
					left join country c on c.code=wc.country_code
					left join translation t on t.table_name='country' and t.field_name='name' and t.id=c.id and t.language='master'
					where opc.order_id = opo.id) containers
						from op_order opo
						JOIN op_article opa ON opo.id = opa.op_order_id
						where opo.id=" . $line->spec_order_id;
                    //				echo $q.'<br>';
                    $line->order_info = $dbr->getRow($q);
                    $line->order_info->containers = $line->order_info->containers;
                }
                if ($debug)
                    echo 'order circle 2: ' . round((getmicrotime() - $time), 3) . '<br>';if ($debug)
                    $time = getmicrotime();
                if ($debug)
                    echo 'order circle 3: ' . round((getmicrotime() - $time), 3) . '<br>';if ($debug)
                    $time = getmicrotime();
                if ((int) $line->spec_order_id) {
                    $q_minus_orders_of_this_container = "
						-IFNULL((select sum(quantity) from orders where article_id='" . $line->article_id . "' and new_article=0
							and spec_order_id=" . (int) $line->spec_order_id . " and spec_order_container_id=opa1.container_id 
							and id<>" . $line->id . "),0)
						-IFNULL((select sum(quantity) from orders where new_article_id='" . $line->article_id . "' and new_article=1
							and spec_order_id=" . (int) $line->spec_order_id . " and spec_order_container_id=opa1.container_id 
							and id<>" . $line->id . "),0)";
                }
                else {
                    $q_minus_orders_of_this_container = '';
                }
                if ($debug)
                    echo 'order circle 4: ' . round((getmicrotime() - $time), 3) . '<br>';if ($debug)
                    $time = getmicrotime();
                $q = "select distinct opo.id, CONCAT('OP Sheet #',opo.id) title
					from op_order opo
					join op_article opa on opa.op_order_id=opo.id
					where (
						(opa.article_id='" . $line->article_id . "' and " . (int) $line->new_article . "=0
							and not exists (select sum(opa1.qnt_ordered) from op_article opa1
								where opa1.article_id='" . $line->article_id . "' and opa1.op_order_id=opo.id 
								group by opa1.container_id having sum(opa1.qnt_ordered)
								$q_minus_orders_of_this_container
								>=" . $line->quantity . " )) 
						OR
						(opa.article_id='" . $line->new_article_id . "' and " . (int) $line->new_article . "=1
							and not exists (select sum(opa1.qnt_ordered) from op_article opa1
								where opa1.article_id='" . $line->article_id . "' and opa1.op_order_id=opo.id 
								group by opa1.container_id having sum(opa1.qnt_ordered)
								$q_minus_orders_of_this_container
								>=" . $line->quantity . " )) 
					) 
					and opo.close_date is null
					";
                //			echo $q.'<br>';
                $line->unavailable_ops_list = $dbr->getAll($q);
                if ($debug)
                    echo 'order circle 5: ' . round((getmicrotime() - $time), 3) . '<br>';if ($debug)
                    $time = getmicrotime();
                $q = "select distinct opo.id, CONCAT('OP Sheet #',opo.id)
					from op_order opo
					join op_article opa on opa.op_order_id=opo.id
					where (
						# new_article condition was commented by Hanna Wichrowska request on 2014-09-21
						(opa.article_id='" . $line->article_id . "' /*and " . (int) $line->new_article . "=0*/
							and exists (select sum(opa1.qnt_ordered) from op_article opa1
								where opa1.article_id='" . $line->article_id . "' and opa1.op_order_id=opo.id 
								group by opa1.container_id having sum(opa1.qnt_ordered)
								$q_minus_orders_of_this_container
								>=" . $line->quantity . " )) 
						OR
						(opa.article_id='" . $line->new_article_id . "' /*and " . (int) $line->new_article . "=1*/
							and exists (select sum(opa1.qnt_ordered) from op_article opa1
								where opa1.article_id='" . $line->article_id . "' and opa1.op_order_id=opo.id 
								group by opa1.container_id having sum(opa1.qnt_ordered)
								$q_minus_orders_of_this_container
								>=" . $line->quantity . " )) 
					) 
					and opo.close_date is null
					";
                //			echo $q.'<br>';
                $line->available_ops_list = $dbr->getAssoc($q);
                if ($debug)
                    echo 'order circle 6: ' . round((getmicrotime() - $time), 3) . '<br>';if ($debug)
                    $time = getmicrotime();
                if ($line->spec_order_id) {
                    $q = "select opa1.container_id, IFNULL(opc.container_no, mopc.container_no) container_no
						from op_article opa1
						join op_order_container opc on opa1.container_id=opc.id
						join op_order_container mopc on mopc.id=opc.master_id
						where opa1.op_order_id=" . $line->spec_order_id . "
						and opa1.article_id='" . $line->article_id . "'
						group by opa1.container_id
						having sum(opa1.qnt_ordered)
							$q_minus_orders_of_this_container
	/*						-IFNULL((select sum(quantity) from orders where ((article_id='" . $line->article_id . "' and new_article=0)
								or (new_article_id='" . $line->article_id . "' and new_article=1))
								and spec_order_id=" . $line->spec_order_id . " and spec_order_container_id=opa.container_id
								and id<>" . $line->id . "
							),0)*/
							>=" . $line->quantity . "
						";
                    //				echo $q.'<br>';
                    $line->available_ops_containers_list = $dbr->getAssoc($q);
                }
                if ($debug)
                    echo 'order circle 7: ' . round((getmicrotime() - $time), 3) . '<br>';if ($debug)
                    $time = getmicrotime();
                $q = "
					select CONCAT(article_rep.article_id,':',warehouse_id)
					, concat(warehouse.name, ' => ', article_id, ': ',
					(SELECT value
											FROM translation
											WHERE table_name = 'article'
											AND field_name = 'name'
											AND language = 'german'
											AND id = article_rep.article_id
											)
					) name
					from article_rep
					join warehouse on warehouse.inactive=0
					where fget_Article_stock_cache(article_id, warehouse.warehouse_id, 4)
					-fget_Article_reserved_cache(article_id, warehouse.warehouse_id, 4)
					+IF(" . ((int) $line->new_article_warehouse_id) . "=warehouse.warehouse_id," . ((int) $line->new_article_qnt) . ",0)
						>=" . ((int) $line->new_article_qnt) . "
					and rep_id='" . $line->article_id . "'
					";
                if ($debug)
                    echo $q . '<br>';
                global $dbr_spec;
                $line->available_new_article_list = $db->getAssoc($q);
//	if ($debug) {print_r($line->available_new_article_list); die();};
                if ($debug && (getmicrotime() - $time) > 1)
                    echo nl2br($q) . "<br>";
                if ($debug) {
                    echo 'order circle 8: ' . round((getmicrotime() - $time), 3) . '<br>';
                    $time = getmicrotime();
                }
                $reps = $dbr->getAssoc("select article_id, article_id from article_rep where rep_id='" . $line->article_id . "'");
                if (count($reps)) {
                    $q = "select o.id, CONCAT(
					IFNULL(CONCAT('now transported with wwo ',wwa.wwo_id,' '),'')
					, 'from ', o.new_article_id, /*': ', ta.value,*/' in ', IFNULL(mau.auction_number, au.auction_number),'/',IFNULL(mau.txnid, au.txnid), ' was taken ', o.article_id, /*': ', ta1.value,*/ ''
					,IFNULL(CONCAT(',', (select group_concat(CONCAT(' in ', IFNULL(mau1.auction_number, au1.auction_number),'/',IFNULL(mau1.txnid, au1.txnid), ' was taken ', o1.article_id)) from orders o1
							join auction au1 on o1.auction_number=au1.auction_number and o1.txnid=au1.txnid
							left join auction mau1 on mau1.auction_number=au1.main_auction_number and mau1.txnid=au1.main_txnid
							where o1.uncomplete_article_order_id=o.id
							and IFNULL(mau1.deleted, au1.deleted)=0
							)),''))
						from orders o
						left join wwo_article wwa on o.id=wwa.uncomplete_article_order_id and not wwa.delivered
						join article a on a.article_id=o.article_id and a.admin_id=0
						join auction au on o.auction_number=au.auction_number and o.txnid=au.txnid
						left join auction mau on mau.auction_number=au.main_auction_number and mau.txnid=au.main_txnid
						join translation ta on ta.table_name='article' and ta.field_name='name' and ta.id=a.article_id and ta.language=IFNULL(mau.lang, au.lang)
						join translation ta1 on ta1.table_name='article' and ta1.field_name='name' and ta1.id=a.article_id and ta1.language=IFNULL(mau.lang, au.lang)
						where 1 and  o.new_article_completed=0
						and o.new_article and o.new_article_id and o.new_article_qnt 
						and NOT IFNULL(o.new_article_not_deduct,0)
						and IFNULL(mau.deleted, au.deleted)=0
						and o.new_article_id in ('" . implode("','", $reps) . "')
							group by o.new_article_id
						";
                    //				echo $q.'<br>';
                    $line->available_uncomplete_article_list = $dbr->getAssoc($q);
                }
                if ($debug)
                    echo 'order circle 9: ' . round((getmicrotime() - $time), 3) . '<br>';if ($debug)
                    $time = getmicrotime();
                /* 			$q = "select orders_new_articles.id
                  ,orders_new_articles.order_id
                  ,orders_new_articles.new_article
                  ,orders_new_articles.new_article_id
                  ,orders_new_articles.new_article_qnt
                  ,orders_new_articles.new_article_completed
                  ,orders_new_articles.new_article_warehouse_id
                  , CONCAT(new_article.article_id, ': ', t_new_article.value) new_article_id_name
                  , CONCAT(orders_new_articles.new_article_id, ':', orders_new_articles.new_article_warehouse_id) new_article_id_warehouse_id
                  , (select CONCAT(IF(new_value=1, 'Done', 'Undone'), ' by ', IFNULL(users.name, total_log.username), ' on ', Updated)
                  from total_log
                  left join users on users.system_username=total_log.username
                  where table_name='orders_new_articles' and Field_name='new_article_completed'
                  and TableID=orders_new_articles.id order by Updated desc limit 1) new_article_completed_story
                  , (select CONCAT(IF(new_value=1, 'Done', 'Undone'), ' by ', IFNULL(users.name, total_log.username), ' on ', Updated)
                  from total_log
                  left join users on users.system_username=total_log.username
                  where table_name='orders_new_articles' and Field_name='new_article'
                  and TableID=orders_new_articles.id order by Updated desc limit 1) new_article_story
                  from orders_new_articles
                  left JOIN article new_article ON new_article.article_id = orders_new_articles.new_article_id
                  and new_article.admin_id=0
                  and orders_new_articles.new_article and new_article.article_id<>''
                  left join translation t_new_article on t_new_article.table_name = 'article'
                  AND t_new_article.field_name = 'name'
                  AND t_new_article.language = '$lang'
                  AND t_new_article.id = new_article.article_id
                  where order_id=".$line->id;
                  $line->new_articles = $dbr->getAll($q); */
                $line->reserve_last = $dbr->getOne("select concat(tl.updated, ' by ',IFNULL(u.name, tl.username))
				from total_log tl
				left join users u on u.system_username=tl.username
				where table_name='orders' and field_name in ('reserve_warehouse_id') and tableid=" . $line->id . "
				order by updated desc limit 1");
                $line->reserve_last_url = smarty_function_change_log(['table_name=orders', 'field_name=reserve_warehouse_id', 'tableid=' . $line->id], $smarty);
            } // if complete we need all the NEW ARTICLE stuff
            if ($debug)
                echo 'order circle 10: ' . round((getmicrotime() - $time), 3) . '<br>';if ($debug)
                $time = getmicrotime();
            $list[] = $line;
        }
//		print_r($list);
        file_put_contents('lastquery_orderlistall', print_r($list, true), FILE_APPEND);
        
        /**
         * check, if bonus was added later than end time of the auction
         * @var $list
         * @var $val
         * @var $date_info
         * @var $dbr
         */
        foreach ($list as $val) {
            $date_info = $dbr->getRow("
                select 
                    bonus_log.Updated
                    , users.name
                    , bonus_log.TableID 
                from total_log bonus_log 
                left join users on users.system_username = bonus_log.username
                where bonus_log.Field_name = 'sent' 
                    and bonus_log.Table_name = 'orders'
                    and bonus_log.TableID = $val->id
            ");

            if ($date_info->Updated > $val->end_time && $val->manual == 2) {
                $val->bonus_added_time = $date_info->Updated;
                $val->bonus_added_user = $date_info->name;
            }
        }
        return $list;
    }

    static function listBonus($db, $dbr, $auction_number, $txnid, $lang = '') {
        if (!strlen($lang)) {
            $lang = $dbr->getOne("select lang from auction where auction_number=$auction_number and txnid=$txnid");
        }
        /*        $q = "select 
          shop_bonus.title
          , shop_bonus.description
          , shop_bonus.percent
          , shop_bonus.article_id
          , auction_bonus.amount
          FROM auction_bonus
          JOIN shop_bonus ON shop_bonus.id = auction_bonus.shop_bonus_id
          WHERE auction_bonus.auction_number=".$auction_number." AND auction_bonus.txnid=".$txnid.""; */
        $q = "select 
				IF(article.admin_id=2, 
	(SELECT value FROM translation join shop_bonus on translation.id = shop_bonus.id
	WHERE table_name = 'shop_bonus' AND field_name = 'title' AND language = '$lang' 
	AND shop_bonus.article_id = article.article_id limit 1)
					, IF(article.admin_id=3,
	(SELECT IF(shop_promo_codes.descr_is_name, shop_promo_codes.name, translation.value) 
	FROM shop_promo_codes left join translation on translation.id = shop_promo_codes.id
	and table_name = 'shop_promo_codes' AND field_name = 'name' AND language = '$lang'
	where shop_promo_codes.article_id = article.article_id limit 1)
				, IF(orders.article_id='', article.name, 
					IFNULL((SELECT value
					FROM translation
					WHERE table_name = 'article'
					AND field_name = 'name'
					AND language = '$lang'
					AND id = article.article_id), article.name)))) as title
				#, shop_bonus.description
				#, shop_bonus.percent
				, orders.article_id
				, orders.price amount
				, orders.manual
				, orders.quantity
				, orders.oldprice
            FROM orders 
			join auction on auction.auction_number=orders.auction_number and auction.txnid=orders.txnid
			JOIN article ON article.article_id = orders.article_id and article.admin_id = orders.manual
            WHERE orders.auction_number=" . $auction_number . " AND orders.txnid=" . $txnid . "
			order by orders.manual, orders.ordering";
//			echo $q;
        $r = $db->query($q);
        if (PEAR::isError($r)) {
            echo $r->message;
            aprint_r($r);
            return;
        }
        $list = array();
        while ($line = $r->fetchRow()) {
//			$line->description = nl2br($line->description);
            $list[] = $line;
        }
        return $list;
    }

    static function listBonusArray($db, $dbr, $auction_number, $txnid) {
        $q = "select shop_bonus.id, shop_bonus_seller.percent+shop_bonus_seller.amount
            FROM orders 
			join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
			left join auction mau on au.main_auction_number=au.auction_number and mau.txnid=au.main_txnid
			JOIN article ON article.article_id = orders.article_id and article.admin_id = orders.manual
			JOIN shop_bonus ON article.article_id = shop_bonus.article_id
			JOIN shop_bonus_seller ON shop_bonus_seller.bonus_id = shop_bonus.id
				and shop_bonus_seller.username=IFNULL(mau.username, au.username)
            WHERE orders.manual=2 and orders.auction_number=" . $auction_number . " AND orders.txnid=" . $txnid . "";
        $r = $dbr->getAssoc($q);
        if (PEAR::isError($r)) {
            echo $r->message;
            aprint_r($r);
            die();
        }
        return $r;
    }

    static function listBonusArrayValues($db, $dbr, $auction_number, $txnid) {
        $q = "select shop_bonus.id, orders.price
            FROM orders 
			join auction au on au.auction_number=orders.auction_number and au.txnid=orders.txnid
			left join auction mau on au.main_auction_number=au.auction_number and mau.txnid=au.main_txnid
			JOIN article ON article.article_id = orders.article_id and article.admin_id = orders.manual
			JOIN shop_bonus ON article.article_id = shop_bonus.article_id
			JOIN shop_bonus_seller ON shop_bonus_seller.bonus_id = shop_bonus.id
				and shop_bonus_seller.username=IFNULL(mau.username, au.username)
            WHERE orders.manual=2 and orders.auction_number=" . $auction_number . " AND orders.txnid=" . $txnid . "";
        $r = $dbr->getAssoc($q);
        if (PEAR::isError($r)) {
            echo $r->message;
            aprint_r($r);
            die();
        }
        return $r;
    }

    static function split_order($db, $dbr, $order_id, $number) {
        $number = (int) $number;
        $order_id = (int) $order_id;
        
        $old_quantity = (int)$dbr->getOne("SELECT `quantity` FROM `orders` WHERE `id`=$order_id");
        if ($old_quantity <= $number)
        {
            return false;
        }
        
        $q = "update orders set quantity=quantity-$number
            WHERE id=$order_id";
        $r = $db->query($q);
        if (PEAR::isError($r)) {
            aprint_r($r);
            die();
        }
        $order = $dbr->getRow("select * from orders where id=$order_id");
        if (PEAR::isError($r)) {
            aprint_r($r);
            die();
        }
        if ($order->main_id) {
            $main = $dbr->getRow("select id, article_list_id from orders where splitted_from_id=" . $order->main_id);
            $main_id = (int) $main->id;
            $article_list_id = (int) $main->article_list_id;
        }
        else {
            $main_id = 'NULL';
            $q = "insert into article_list (group_id,article_id,article_price,high_price,position,additional_shipping_cost,default_quantity,show_overstocked,copy_from_article_list_id,noship,inactive,alias_id)
				select 0,article_id,article_price,high_price,position,additional_shipping_cost,default_quantity,show_overstocked,copy_from_article_list_id,noship,inactive,alias_id
	            FROM article_list 
	            WHERE article_list_id=" . $order->article_list_id;
            $r = $db->query($q);
            $article_list_id = mysql_insert_id();
        }
        $q = "insert into orders (auction_number,article_id,quantity,price,manual,custom_title,custom_description,txnid,article_list_id,hidden,ordering,send_warehouse_id,sent,delivery_username,reserve_warehouse_id,to_delete,main_id,alias_id,oldprice,ready2pickup,splitted_from_id)
			select auction_number,article_id,$number,price,manual,custom_title,custom_description,txnid,$article_list_id,hidden,ordering+1,send_warehouse_id,sent,delivery_username,reserve_warehouse_id,to_delete,$main_id,alias_id,oldprice,ready2pickup,$order_id
            FROM orders 
            WHERE id=$order_id";
        $r = $db->query($q);
        $new_id = mysql_insert_id();
        if (!$new_id)
            $new_id = $db->getOne("select max(id) from orders where article_list_id=" . $article_list_id);
        $q = "insert into tn_orders (tn_id, order_id) select tn_id, $new_id from tn_orders where order_id = $order_id";
        $r = $db->query($q);
        if (PEAR::isError($r)) {
            aprint_r($r);
            die();
        }
        return $new_id;
    }

}
