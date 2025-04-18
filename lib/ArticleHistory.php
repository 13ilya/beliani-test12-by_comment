<?php
require_once 'PEAR.php';

class ArticleHistory
{
    static function Log($db, $dbr, $article_id, $quantity, $comment, $user='', $warehouse_id=0, $move=0)
    {
	global $loggedUser;
	if (!strlen($user)) $user=$loggedUser ? $loggedUser->get('username') : 'customer';
        $article_id = mysql_escape_string($article_id);
        $comment = mysql_escape_string($comment);
		if (strpos($comment,'Order')===0) $add = ", Order_ID=SUBSTRING_INDEX(TRIM(SUBSTRING( COMMENT , 6)), ' ', 1 )";
		if (strpos($comment,'RMA Ticket#')===0) $add = ", Rma_ID=SUBSTRING( COMMENT , 12)";
		if (strpos($comment,'Auftrag')===0) $add = ", auction_number=SUBSTRING_INDEX( SUBSTRING(COMMENT , 9 ) , ' ', 1 )
				, txnid=SUBSTRING_INDEX( SUBSTRING(COMMENT , 9 ) , ' / ' , -1 )";
		if (strpos($comment,'Reserved by Auftrag')===0) $add = ", auction_number=SUBSTRING_INDEX( SUBSTRING(COMMENT , 21 ) , ' ', 1 )
				, txnid=SUBSTRING_INDEX( SUBSTRING(COMMENT , 21 ) , ' / ' , -1 )";
		if ($move<0) {
			$add = $dbr->getOne("select IFNULL((select max(move_id)+1 from article_history),1)");
			$add = ", move_id=$add";
		} elseif ($move>0) $add = ", move_id=$move";	
		elseif ($move==0) $add='';	
        $r = $db->query("INSERT INTO article_history SET `date` = NOW(), article_id='$article_id', 
				quantity=$quantity, comment='$comment', user='$user', warehouse_id=$warehouse_id
				 $add");
        if (PEAR::isError($r)) aprint_r($r);
		if ($move<0) return $dbr->getOne("select max(move_id) from article_history");
    }


    static function listAll($db, $dbr, $article_id, $direction='', $step=0, $sort='', $dir=1, $filter=array())
    {	
        if (!strlen($sort)) $orderby = "ORDER BY `date` ".$dir;
        else $orderby = "ORDER BY `$sort`".($dir==-1 ? " desc" : "");
	global $warehouse_filter;
		$where = '';
		if (is_array($filter['warehouse_id'])) {
			$where .= " and t.send_warehouse_id in (".implode(',',$filter['warehouse_id']).")";
		} else {
			$where .= " and ww.inactive=0 ";
		}
		if (strlen($filter['datefrom'])) 
			$where .= " and DATE(`date`) >= '".$filter['datefrom']."'";
		if (strlen($filter['dateto'])) 
			$where .= " and DATE(`date`) <= '".$filter['dateto']."'";

	  $q = "select t.*
	  	, IFNULL( uu.name, t.user ) as username
		 from (SELECT 
			  	o.article_id
				, tl.updated as date
				, CONCAT('Auftrag ', IFNULL(mau.auction_number,au.auction_number), ' / ', IFNULL(mau.txnid,au.txnid)) as comment
				, -o.quantity as quantity
				, null as id
				, null as user
				, null as rma_id
				, null as order_id
				, 'Auction' as TYPE
				, IFNULL(mau.auction_number,au.auction_number) auction_number
		        , IFNULL(mau.txnid,au.txnid) txnid 
#		        , null as username
			, au.end_time
			, o.send_warehouse_id
			, w.name warehouse_name
			, CONCAT(''
				, IFNULL(IF(au_company_shipping.value='', '', CONCAT(au_company_shipping.value, ' ')),'')
				, IFNULL(CONCAT(au_firstname_shipping.value, ' '),'')
				, IFNULL(CONCAT(au_name_shipping.value, ', '),'')
				, IFNULL(CONCAT(au_street_shipping.value, ' '),'')
				, IFNULL(CONCAT(au_house_shipping.value, ' '),'')
				, IFNULL(CONCAT(au_zip_shipping.value, ', '),'')
				, IFNULL(CONCAT(au_city_shipping.value, ', '),'')
				, IFNULL(CONCAT(au_country_shipping.value, ''),'')
			) address
			, au_country_shipping.value country_shipping
			, (select sm.company_name
				from tracking_numbers tn
				join shipping_method sm on tn.shipping_method=sm.shipping_method_id
				join tn_orders tno on tno.tn_id=tn.id
				where tno.order_id=o.id
				limit 1
				) shipping_method
		  FROM orders o
			left join total_log tl on tl.tableid=o.id and table_name='orders' and field_name='sent' and New_value=1
			JOIN auction au ON o.auction_number = au.auction_number AND o.txnid = au.txnid
			LEFT JOIN auction mau ON au.main_auction_number = mau.auction_number AND au.main_txnid = mau.txnid
					left join auction_par_varchar au_company_shipping on au.auction_number=au_company_shipping.auction_number 
						and au.txnid=au_company_shipping.txnid and au_company_shipping.key='company_shipping'
					left join auction_par_varchar au_firstname_shipping on au.auction_number=au_firstname_shipping.auction_number 
						and au.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
					left join auction_par_varchar au_name_shipping on au.auction_number=au_name_shipping.auction_number 
						and au.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
					left join auction_par_varchar au_street_shipping on au.auction_number=au_street_shipping.auction_number 
						and au.txnid=au_street_shipping.txnid and au_street_shipping.key='street_shipping'
					left join auction_par_varchar au_house_shipping on au.auction_number=au_house_shipping.auction_number 
						and au.txnid=au_house_shipping.txnid and au_house_shipping.key='house_shipping'
					left join auction_par_varchar au_zip_shipping on au.auction_number=au_zip_shipping.auction_number 
						and au.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
					left join auction_par_varchar au_country_shipping on au.auction_number=au_country_shipping.auction_number 
						and au.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
					left join auction_par_varchar au_city_shipping on au.auction_number=au_city_shipping.auction_number 
						and au.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
			JOIN invoice i ON au.invoice_number = i.invoice_number
			left JOIN warehouse w ON o.send_warehouse_id = w.warehouse_id
		WHERE o.article_id = '$article_id' and o.manual=0
			AND o.sent
			AND au.deleted = 0
			and o.send_warehouse_id in ($warehouse_filter)
union all
SELECT 
			  	oa.article_id
				, oa.add_to_warehouse_date as date
				, CONCAT('Container ',IF(master.id, CONCAT('(',oac.container_no,')'),oac.container_no),' from Order ', o.id, 
	IF(oa.add_to_warehouse,' delivered',' removed')
				) as comment
				, oa.qnt_delivered
				, null as id
				, oa.add_to_warehouse_uname as user
				, null as rma_id
				, o.id as order_id
				, 'Order' as TYPE
			, null as auction_number
		        , null as txnid
#		        , IFNULL( users.name, oa.add_to_warehouse_uname) as username
			, null as end_time
			, oa.warehouse_id
			, w.name warehouse_name
			, '' address
			, '' country_shipping
			, '' shipping_method
		  FROM op_article oa
		LEFT JOIN op_order_container ON op_order_container.id = oa.container_id
		left join op_order_container master on master.id=op_order_container.master_id
		left join op_order_container oac on IFNULL(master.id, op_order_container.id) = oac.id
			join op_order o on o.id=oa.op_order_id
			left JOIN warehouse w ON oa.warehouse_id = w.warehouse_id
			LEFT JOIN users ON oa.add_to_warehouse_uname=users.username 
		WHERE oa.article_id = '$article_id' and add_to_warehouse
		and oa.warehouse_id in ($warehouse_filter)
union all
SELECT 
			  	rs.article_id
				, total_log.updated as date
				, CONCAT(rp.name, '# ', r.rma_id) as comment
				, 1 as quantity
				, null as id
				, IFNULL(users.name,total_log.username) as user
				, r.rma_id as rma_id
				, null as order_id
				, 'Wrong' as TYPE
			, r.auction_number
		        , r.txnid
#		        , IFNULL( users.name, r.responsible_uname ) as username
			, auction.end_time
			, IFNULL(auw.warehouse_id, w.warehouse_id) warehouse_id
			, IFNULL(auw.name, w.name) warehouse_name
			, '' address
			, '' country_shipping
			, '' shipping_method
		  FROM rma r
			join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
			JOIN rma_spec rs ON r.rma_id=rs.rma_id
			JOIN rma_problem rp ON rp.problem_id=rs.problem_id
			left join (select 
				SUBSTRING_INDEX(GROUP_CONCAT(Updated order by updated desc),',',1)  updated
				, SUBSTRING_INDEX(GROUP_CONCAT(username order by updated desc),',',1)  username
				, TableID 
				from total_log where 1
				and Table_name='rma_spec'
				and Field_name='problem_id'
				and New_value in ('4','11')
				group by TableID
			) total_log on 1 and TableID=rs.rma_spec_id
			left join users on users.system_username=total_log.username
			left JOIN warehouse w ON `default`
			LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
			left JOIN warehouse auw ON rs.send_warehouse_id=auw.warehouse_id
		WHERE rs.article_id = '$article_id' and rs.problem_id in (4,11)
		and IFNULL(auw.warehouse_id, w.warehouse_id) in ($warehouse_filter)
union all
SELECT 
			  	rs.article_id
				, IFNULL(rs.return_date, '0000-00-00') as date
				, CONCAT('Back to warehouse wrong item sent Ticket# ', r.rma_id) as comment
				, -1 as quantity
				, null as id
				, rs.responsible_uname as user
				, r.rma_id as rma_id
				, null as order_id
				, 'RMA' as TYPE
			, r.auction_number
		        , r.txnid
#		        , IFNULL( users.name, rs.responsible_uname ) as username
			, auction.end_time
			, w.warehouse_id
			, w.name warehouse_name
			, '' address
			, '' country_shipping
			, '' shipping_method
		  FROM rma r
			JOIN rma_spec rs ON r.rma_id=rs.rma_id
			LEFT JOIN users ON rs.responsible_uname=users.username 
			left JOIN warehouse w ON rs.warehouse_id = w.warehouse_id
			LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
		WHERE rs.article_id = '$article_id' and IFNULL(rs.add_to_stock,0) = 0 and rs.back_wrong_delivery=1
			and rs.warehouse_id in ($warehouse_filter)
union all
SELECT 
			  	rs.article_id
				, IFNULL(rs.return_date, '0000-00-00') as date
				, CONCAT('Back to warehouse wrong item sent Ticket# ', r.rma_id) as comment
				, 1 as quantity
				, null as id
				, rs.responsible_uname as user
				, r.rma_id as rma_id
				, null as order_id
				, 'RMA' as TYPE
			, r.auction_number
		        , r.txnid
#		        , IFNULL( users.name, rs.responsible_uname ) as username
			, auction.end_time
			, w.warehouse_id
			, w.name warehouse_name
			, '' address
			, '' country_shipping
			, '' shipping_method
		  FROM rma r
			JOIN rma_spec rs ON r.rma_id=rs.rma_id
			LEFT JOIN users ON rs.responsible_uname=users.username 
			left JOIN warehouse w ON rs.returned_warehouse_id = w.warehouse_id
			LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
		WHERE rs.article_id = '$article_id' and rs.add_to_stock = 1 and rs.back_wrong_delivery=1
			and rs.returned_warehouse_id in ($warehouse_filter)
union all
SELECT 
			  	rs.article_id
				, IFNULL(rs.return_date, '0000-00-00') as date
				, CONCAT('Back to warehouse wrong item sent Ticket# ', r.rma_id) as comment
				, -1 as quantity
				, null as id
				, rs.responsible_uname as user
				, r.rma_id as rma_id
				, null as order_id
				, 'RMA' as TYPE
			, r.auction_number
		        , r.txnid
#		        , IFNULL( users.name, rs.responsible_uname ) as username
			, auction.end_time
			, w.warehouse_id
			, w.name warehouse_name
			, '' address
			, '' country_shipping
			, '' shipping_method
		  FROM rma r
			JOIN rma_spec rs ON r.rma_id=rs.rma_id
			LEFT JOIN users ON rs.responsible_uname=users.username 
			left JOIN warehouse w ON rs.warehouse_id = w.warehouse_id
			LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
		WHERE rs.article_id = '$article_id' and rs.add_to_stock = 1 and rs.back_wrong_delivery=1
			and rs.warehouse_id in ($warehouse_filter)
union all
SELECT 
			  	rs.article_id
				, IFNULL(max(tl.updated), rs.return_date) as date
				, CONCAT('Back to warehouse correct item sent Ticket# ', r.rma_id) as comment
				, 1 as quantity
				, null as id
				, rs.responsible_uname as user
				, r.rma_id as rma_id
				, null as order_id
				, 'RMA' as TYPE
			, r.auction_number
		        , r.txnid
#		        , IFNULL( users.name, rs.responsible_uname ) as username
			, auction.end_time
			, w.warehouse_id
			, w.name warehouse_name
			, '' address
			, '' country_shipping
			, '' shipping_method
		  FROM rma r
			JOIN rma_spec rs ON r.rma_id=rs.rma_id
			left join total_log tl on tl.table_name='rma_spec' and tl.field_name='add_to_stock' and tl.tableid=rs.rma_spec_id and tl.new_value='1'
			LEFT JOIN users ON rs.responsible_uname=users.username 
			left JOIN warehouse w ON rs.warehouse_id = w.warehouse_id
			LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
		WHERE rs.article_id = '$article_id' and rs.add_to_stock = 1 and IFNULL(rs.back_wrong_delivery, 0)=0
			and rs.warehouse_id in ($warehouse_filter)
		group by rs.rma_spec_id	
union all
SELECT 
			  	wwa.article_id
				, (select tl.updated from total_log tl
				left join users u on u.system_username=tl.username
				where table_name='wwo_article' and field_name='taken' and tableid=wwa.id
				order by updated desc limit 1) as date
				, CONCAT('WWO# ', wwo.id, ' taken from ', w_from.name, IFNULL(CONCAT(' to ', w_driver.name),'')) as comment
				, -wwa.qnt as quantity
				, wwo.id as id
				, (select u.username from total_log tl
				left join users u on u.system_username=tl.username
				where table_name='wwo_article' and field_name='taken' and tableid=wwa.id
				order by updated desc limit 1) as user
#				, wwo.username as user
				, null as rma_id
				, null as order_id
				, 'WWO' as TYPE
			, NULL auction_number
		        , NULL txnid
#		        , IFNULL( users.name, wwo.username) as username
			, null as end_time
			, w.warehouse_id
			, w.name warehouse_name
			, '' address
			, '' country_shipping
			, '' shipping_method
		  FROM ww_order wwo
			JOIN wwo_article wwa ON wwo.id=wwa.wwo_id
			LEFT JOIN users ON wwo.username=users.username 
			LEFT JOIN users driver_users ON wwo.driver_username=driver_users.username 
			left JOIN warehouse w ON wwa.from_warehouse = w.warehouse_id
			left JOIN warehouse w_from ON wwa.from_warehouse = w_from.warehouse_id
			left JOIN warehouse w_to ON wwa.to_warehouse = w_to.warehouse_id
			left JOIN warehouse w_driver ON driver_users.driver_warehouse_id = w_driver.warehouse_id
		WHERE wwa.article_id = '$article_id' and wwa.taken = 1
			and wwa.taken_not_deducted=0
			and wwa.from_warehouse in ($warehouse_filter)
union all
SELECT 
			  	wwa.article_id
				, (select tl.updated from total_log tl
				left join users u on u.system_username=tl.username
				where table_name='wwo_article' and field_name='taken' and tableid=wwa.id
				order by updated desc limit 1) as date
				, CONCAT('WWO# ', wwo.id, ' taken from ', w_from.name, IFNULL(CONCAT(' to ', w_driver.name),'')) as comment
				, wwa.qnt as quantity
				, wwo.id as id
				, (select u.username from total_log tl
				left join users u on u.system_username=tl.username
				where table_name='wwo_article' and field_name='taken' and tableid=wwa.id
				order by updated desc limit 1) as user
#				, wwo.username as user
				, null as rma_id
				, null as order_id
				, 'WWO' as TYPE
			, NULL auction_number
		        , NULL txnid
#		        , IFNULL( users.name, wwo.username) as username
			, null as end_time
			, w.warehouse_id
			, w.name warehouse_name
			, '' address
			, '' country_shipping
			, '' shipping_method
		  FROM ww_order wwo
			JOIN wwo_article wwa ON wwo.id=wwa.wwo_id
			LEFT JOIN users ON wwo.username=users.username 
			LEFT JOIN users driver_users ON wwo.driver_username=driver_users.username 
			left JOIN warehouse w ON driver_users.driver_warehouse_id = w.warehouse_id
			left JOIN warehouse w_from ON wwa.from_warehouse = w_from.warehouse_id
			left JOIN warehouse w_to ON wwa.to_warehouse = w_to.warehouse_id
			left JOIN warehouse w_driver ON driver_users.driver_warehouse_id = w_driver.warehouse_id
		WHERE wwa.article_id = '$article_id' and wwa.taken = 1
			and w.warehouse_id in ($warehouse_filter)
union all
SELECT 
			  	wwa.article_id
				, (select tl.updated from total_log tl
				left join users u on u.system_username=tl.username
				where table_name='wwo_article' and field_name='delivered' and tableid=wwa.id
				order by updated desc limit 1) as date
				, CONCAT('1WWO# ', wwo.id, ' delivered from ', IFNULL(w_driver.name,''), IFNULL(CONCAT(' to ', w_to.name),'')) as comment
				, -wwa.qnt as quantity
				, wwo.id as id
				, (select u.username from total_log tl
				left join users u on u.system_username=tl.username
				where table_name='wwo_article' and field_name='delivered' and tableid=wwa.id
				order by updated desc limit 1) as user
#				, wwo.username as user
				, null as rma_id
				, null as order_id
				, 'WWO' as TYPE
			, NULL auction_number
		        , NULL txnid
#		        , IFNULL( users.name, wwo.username) as username
			, null as end_time
			, w.warehouse_id
			, w.name warehouse_name
			, '' address
			, '' country_shipping
			, '' shipping_method
		  FROM ww_order wwo
			JOIN wwo_article wwa ON wwo.id=wwa.wwo_id
			LEFT JOIN users ON wwo.username=users.username 
			LEFT JOIN users driver_users ON wwo.driver_username=driver_users.username 
			left JOIN warehouse w ON driver_users.driver_warehouse_id = w.warehouse_id
			left JOIN warehouse w_from ON wwa.from_warehouse = w_from.warehouse_id
			left JOIN warehouse w_to ON wwa.to_warehouse = w_to.warehouse_id
			left JOIN warehouse w_driver ON driver_users.driver_warehouse_id = w_driver.warehouse_id
		WHERE wwa.article_id = '$article_id' and wwa.delivered = 1
			and w.warehouse_id in ($warehouse_filter)
union all
SELECT 
			  	wwa.article_id
				, (select tl.updated from total_log tl
				left join users u on u.system_username=tl.username
				where table_name='wwo_article' and field_name='delivered' and tableid=wwa.id
				order by updated desc limit 1) as date
				, CONCAT('2WWO# ', wwo.id, ' delivered from ', IFNULL(w_driver.name,''), IFNULL(CONCAT(' to ', w_to.name),'')) as comment
				, wwa.qnt as quantity
				, wwo.id as id
				, (select u.username from total_log tl
				left join users u on u.system_username=tl.username
				where table_name='wwo_article' and field_name='delivered' and tableid=wwa.id
				order by updated desc limit 1) as user
#				, wwo.username as user
				, null as rma_id
				, null as order_id
				, 'WWO' as TYPE
			, NULL auction_number
		        , NULL txnid
#		        , IFNULL( users.name, wwo.username) as username
			, null as end_time
			, w.warehouse_id
			, w.name warehouse_name
			, '' address
			, '' country_shipping
			, '' shipping_method
		  FROM ww_order wwo
			JOIN wwo_article wwa ON wwo.id=wwa.wwo_id
			LEFT JOIN users ON wwo.username=users.username 
			LEFT JOIN users driver_users ON wwo.driver_username=driver_users.username 
			left JOIN warehouse w ON wwa.to_warehouse = w.warehouse_id
			left JOIN warehouse w_from ON wwa.from_warehouse = w_from.warehouse_id
			left JOIN warehouse w_to ON wwa.to_warehouse = w_to.warehouse_id
			left JOIN warehouse w_driver ON driver_users.driver_warehouse_id = w_driver.warehouse_id
		WHERE wwa.article_id = '$article_id' and wwa.delivered = 1
			and wwa.delivered_not_added=0
			and w.warehouse_id in ($warehouse_filter)
union all
SELECT 
			  	ats.article_id
				, ats.order_date as date
				, CONCAT('ATS# ', ats.id, ' ', ats.comment) as comment
				, -ats.quantity
				, ats.id as id
				, users.username as user
				, null as rma_id
				, null as order_id
				, 'ATS' as TYPE
				, NULL auction_number
		        , NULL txnid
			, null as end_time
			, w.warehouse_id
			, w.name warehouse_name
			, '' address
			, '' country_shipping
			, '' shipping_method
		  FROM ats
			LEFT JOIN users ON ats.username=users.username 
			left JOIN warehouse w ON ats.warehouse_id = w.warehouse_id
		WHERE ats.article_id = '$article_id' and ats.booked = 1
			and w.warehouse_id in ($warehouse_filter)
union all
SELECT 
			  	ats_item.article_id
				, ats.order_date as date
				, CONCAT('ATS# ', ats.id, ' ', ats.comment) as comment
				, ats_item.quantity
				, ats.id as id
				, users.username as user
				, null as rma_id
				, null as order_id
				, 'ATS' as TYPE
				, NULL auction_number
		        , NULL txnid
			, null as end_time
			, w.warehouse_id
			, w.name warehouse_name
			, '' address
			, '' country_shipping
			, '' shipping_method
		  FROM ats
			JOIN ats_item ON ats.id=ats_item.ats_id
			LEFT JOIN users ON ats.username=users.username 
			left JOIN warehouse w ON ats.warehouse_id = w.warehouse_id
		WHERE ats_item.article_id = '$article_id' and ats.booked = 1
			and w.warehouse_id in ($warehouse_filter)
union all
SELECT 
			  	o.article_id
				, (select Updated
  					from total_log 
		 			left join users on users.system_username=total_log.username
					where table_name='orders' and Field_name='new_article'
					and TableID=o.id order by Updated desc limit 1) as date
				, CONCAT('Rep part from New article in auftrag ', o.auction_number, '/', o.txnid) as comment
				, o.new_article_qnt
				, null as id
				, (select IFNULL(users.name, total_log.username)
					from total_log 
					left join users on users.system_username=total_log.username
					where table_name='orders' and Field_name='new_article'
					and TableID=o.id order by Updated desc limit 1) as user
				, null as rma_id
				, null as order_id
				, 'Auction' as TYPE
			,o.auction_number
		        , o.txnid
			, NULL end_time
			, w.warehouse_id
			, w.name warehouse_name
			, CONCAT(''
				, IFNULL(IF(au_company_shipping.value='', '', CONCAT(au_company_shipping.value, ' ')),'')
				, IFNULL(CONCAT(au_firstname_shipping.value, ' '),'')
				, IFNULL(CONCAT(au_name_shipping.value, ', '),'')
				, IFNULL(CONCAT(au_street_shipping.value, ' '),'')
				, IFNULL(CONCAT(au_house_shipping.value, ' '),'')
				, IFNULL(CONCAT(au_zip_shipping.value, ', '),'')
				, IFNULL(CONCAT(au_city_shipping.value, ', '),'')
				, IFNULL(CONCAT(au_country_shipping.value, ''),'')
			) address
			, au_country_shipping.value country_shipping
			, (select sm.company_name
				from tracking_numbers tn
				join shipping_method sm on tn.shipping_method=sm.shipping_method_id
				join tn_orders tno on tno.tn_id=tn.id
				where tno.order_id=o.id
				limit 1
				) shipping_method
		  FROM orders o
			LEFT JOIN wwo_article wwa ON o.id = wwa.uncomplete_article_order_id
			left JOIN ww_order wwo ON wwo.id = wwa.wwo_id
			LEFT JOIN users driver_users ON wwo.driver_username=driver_users.username 
			left JOIN warehouse w_driver ON driver_users.driver_warehouse_id = w_driver.warehouse_id
			JOIN auction au ON o.auction_number = au.auction_number
			AND o.txnid = au.txnid
					left join auction_par_varchar au_company_shipping on au.auction_number=au_company_shipping.auction_number 
						and au.txnid=au_company_shipping.txnid and au_company_shipping.key='company_shipping'
					left join auction_par_varchar au_firstname_shipping on au.auction_number=au_firstname_shipping.auction_number 
						and au.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
					left join auction_par_varchar au_name_shipping on au.auction_number=au_name_shipping.auction_number 
						and au.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
					left join auction_par_varchar au_street_shipping on au.auction_number=au_street_shipping.auction_number 
						and au.txnid=au_street_shipping.txnid and au_street_shipping.key='street_shipping'
					left join auction_par_varchar au_house_shipping on au.auction_number=au_house_shipping.auction_number 
						and au.txnid=au_house_shipping.txnid and au_house_shipping.key='house_shipping'
					left join auction_par_varchar au_zip_shipping on au.auction_number=au_zip_shipping.auction_number 
						and au.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
					left join auction_par_varchar au_country_shipping on au.auction_number=au_country_shipping.auction_number 
						and au.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
					left join auction_par_varchar au_city_shipping on au.auction_number=au_city_shipping.auction_number 
						and au.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
			left JOIN warehouse w ON o.new_article_warehouse_id = w.warehouse_id
		WHERE o.article_id = '$article_id' and o.new_article = 1 #AND not o.sent
			and NOT IFNULL(o.new_article_not_deduct,0)
			and w.warehouse_id in ($warehouse_filter)
union all
SELECT 
			  	o.article_id
				, (select Updated
  					from total_log 
		 			left join users on users.system_username=total_log.username
					where table_name='orders' and Field_name='lost_new_article'
					and TableID=o.id order by Updated desc limit 1) as date
				, CONCAT('Carton reported lost after taken from new Auftrag ', o.auction_number, '/', o.txnid) as comment
				, -1 as quantity
				, null as id
				, (select IFNULL(users.name, total_log.username)
					from total_log 
					left join users on users.system_username=total_log.username
					where table_name='orders' and Field_name='lost_new_article'
					and TableID=o.id order by Updated desc limit 1) as user
				, null as rma_id
				, null as order_id
				, 'Auction' as TYPE
			,o.auction_number
		        , o.txnid
			, NULL end_time
			, w.warehouse_id
			, w.name warehouse_name
			, CONCAT(''
				, IFNULL(IF(au_company_shipping.value='', '', CONCAT(au_company_shipping.value, ' ')),'')
				, IFNULL(CONCAT(au_firstname_shipping.value, ' '),'')
				, IFNULL(CONCAT(au_name_shipping.value, ', '),'')
				, IFNULL(CONCAT(au_street_shipping.value, ' '),'')
				, IFNULL(CONCAT(au_house_shipping.value, ' '),'')
				, IFNULL(CONCAT(au_zip_shipping.value, ', '),'')
				, IFNULL(CONCAT(au_city_shipping.value, ', '),'')
				, IFNULL(CONCAT(au_country_shipping.value, ''),'')
			) address
			, au_country_shipping.value country_shipping
			, (select sm.company_name
				from tracking_numbers tn
				join shipping_method sm on tn.shipping_method=sm.shipping_method_id
				join tn_orders tno on tno.tn_id=tn.id
				where tno.order_id=o.id
				limit 1
				) shipping_method
		  FROM orders o
			LEFT JOIN wwo_article wwa ON o.id = wwa.uncomplete_article_order_id
			left JOIN ww_order wwo ON wwo.id = wwa.wwo_id
			LEFT JOIN users driver_users ON wwo.driver_username=driver_users.username 
			left JOIN warehouse w_driver ON driver_users.driver_warehouse_id = w_driver.warehouse_id
			JOIN auction au ON o.auction_number = au.auction_number
			AND o.txnid = au.txnid
					left join auction_par_varchar au_company_shipping on au.auction_number=au_company_shipping.auction_number 
						and au.txnid=au_company_shipping.txnid and au_company_shipping.key='company_shipping'
					left join auction_par_varchar au_firstname_shipping on au.auction_number=au_firstname_shipping.auction_number 
						and au.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
					left join auction_par_varchar au_name_shipping on au.auction_number=au_name_shipping.auction_number 
						and au.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
					left join auction_par_varchar au_street_shipping on au.auction_number=au_street_shipping.auction_number 
						and au.txnid=au_street_shipping.txnid and au_street_shipping.key='street_shipping'
					left join auction_par_varchar au_house_shipping on au.auction_number=au_house_shipping.auction_number 
						and au.txnid=au_house_shipping.txnid and au_house_shipping.key='house_shipping'
					left join auction_par_varchar au_zip_shipping on au.auction_number=au_zip_shipping.auction_number 
						and au.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
					left join auction_par_varchar au_country_shipping on au.auction_number=au_country_shipping.auction_number 
						and au.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
					left join auction_par_varchar au_city_shipping on au.auction_number=au_city_shipping.auction_number 
						and au.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
			left JOIN warehouse w ON o.new_article_warehouse_id = w.warehouse_id
		WHERE o.new_article_id = '$article_id' and o.new_article and o.lost_new_article #AND not o.sent 
			and NOT IFNULL(o.new_article_not_deduct,0)
			and w.warehouse_id in ($warehouse_filter)
union all
SELECT 
			  	o.article_id
				, (select Updated
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='new_article'
				and TableID=o.id order by Updated desc limit 1) as date
				, CONCAT('Rep part from uncomplete article in auftrag ', o.auction_number, '/', o.txnid) as comment
				, o.new_article_qnt
				, null as id
				, (select IFNULL(users.name, total_log.username)
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='new_article'
				and TableID=o.id order by Updated desc limit 1) as user
				, null as rma_id
				, null as order_id
				, 'Auction' as TYPE
			,o.auction_number
		        , o.txnid
			, NULL end_time
			, w.warehouse_id
			, w.name warehouse_name
			, CONCAT(''
				, IFNULL(IF(au_company_shipping.value='', '', CONCAT(au_company_shipping.value, ' ')),'')
				, IFNULL(CONCAT(au_firstname_shipping.value, ' '),'')
				, IFNULL(CONCAT(au_name_shipping.value, ', '),'')
				, IFNULL(CONCAT(au_street_shipping.value, ' '),'')
				, IFNULL(CONCAT(au_house_shipping.value, ' '),'')
				, IFNULL(CONCAT(au_zip_shipping.value, ', '),'')
				, IFNULL(CONCAT(au_city_shipping.value, ', '),'')
				, IFNULL(CONCAT(au_country_shipping.value, ''),'')
			) address
			, au_country_shipping.value country_shipping
			, (select sm.company_name
				from tracking_numbers tn
				join shipping_method sm on tn.shipping_method=sm.shipping_method_id
				join tn_orders tno on tno.tn_id=tn.id
				where tno.order_id=o.id
				limit 1
				) shipping_method
		  FROM orders o
			JOIN orders o_uc ON o_uc.id = o.uncomplete_article_order_id
			LEFT JOIN wwo_article wwa_unc ON o_uc.id = wwa_unc.uncomplete_article_order_id
			left JOIN ww_order wwo_unc ON wwo_unc.id = wwa_unc.wwo_id
			LEFT JOIN users driver_users ON wwo_unc.driver_username=driver_users.username 
			left JOIN warehouse w_driver ON driver_users.driver_warehouse_id = w_driver.warehouse_id
			JOIN auction au ON o.auction_number = au.auction_number
			AND o.txnid = au.txnid
					left join auction_par_varchar au_company_shipping on au.auction_number=au_company_shipping.auction_number 
						and au.txnid=au_company_shipping.txnid and au_company_shipping.key='company_shipping'
					left join auction_par_varchar au_firstname_shipping on au.auction_number=au_firstname_shipping.auction_number 
						and au.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
					left join auction_par_varchar au_name_shipping on au.auction_number=au_name_shipping.auction_number 
						and au.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
					left join auction_par_varchar au_street_shipping on au.auction_number=au_street_shipping.auction_number 
						and au.txnid=au_street_shipping.txnid and au_street_shipping.key='street_shipping'
					left join auction_par_varchar au_house_shipping on au.auction_number=au_house_shipping.auction_number 
						and au.txnid=au_house_shipping.txnid and au_house_shipping.key='house_shipping'
					left join auction_par_varchar au_zip_shipping on au.auction_number=au_zip_shipping.auction_number 
						and au.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
					left join auction_par_varchar au_country_shipping on au.auction_number=au_country_shipping.auction_number 
						and au.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
					left join auction_par_varchar au_city_shipping on au.auction_number=au_city_shipping.auction_number 
						and au.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
			left JOIN warehouse w ON IF(wwa_unc.delivered, wwa_unc.to_warehouse, IF(wwa_unc.taken, w_driver.warehouse_id, o_uc.reserve_warehouse_id)) 
				 = w.warehouse_id
		WHERE o.article_id = '$article_id' and o.uncomplete_article_order_id #AND not o.sent
#			and w.warehouse_id in ($warehouse_filter)
union all
SELECT 
			  	o.article_id
				, (select Updated
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='new_article_completed'
				and TableID=o.id order by Updated desc limit 1) as date
				, CONCAT('Rep part completed from New article in auftrag ', o.auction_number, '/', o.txnid) as comment
				, -o.new_article_qnt
				, null as id
				, (select IFNULL(users.name, total_log.username)
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='new_article_completed'
				and TableID=o.id order by Updated desc limit 1) as user
				, null as rma_id
				, null as order_id
				, 'Auction' as TYPE
			,o.auction_number
		        , o.txnid
			, NULL end_time
			, w.warehouse_id
			, w.name warehouse_name
			, CONCAT(''
				, IFNULL(IF(au_company_shipping.value='', '', CONCAT(au_company_shipping.value, ' ')),'')
				, IFNULL(CONCAT(au_firstname_shipping.value, ' '),'')
				, IFNULL(CONCAT(au_name_shipping.value, ', '),'')
				, IFNULL(CONCAT(au_street_shipping.value, ' '),'')
				, IFNULL(CONCAT(au_house_shipping.value, ' '),'')
				, IFNULL(CONCAT(au_zip_shipping.value, ', '),'')
				, IFNULL(CONCAT(au_city_shipping.value, ', '),'')
				, IFNULL(CONCAT(au_country_shipping.value, ''),'')
			) address
			, au_country_shipping.value country_shipping
			, (select sm.company_name
				from tracking_numbers tn
				join shipping_method sm on tn.shipping_method=sm.shipping_method_id
				join tn_orders tno on tno.tn_id=tn.id
				where tno.order_id=o.id
				limit 1
				) shipping_method
		  FROM orders o
left join barcode_object bo_dc on bo_dc.obj_id=o.id and bo_dc.obj='decompleted_article'
left join barcode_object bo_rc on bo_rc.obj_id=o.id and bo_rc.obj='recompleted_article'
left join barcode_object bo_ww on bo_ww.barcode_id=bo_dc.barcode_id and bo_ww.obj='wwo_article' and bo_ww.id between bo_dc.id and bo_rc.id
left JOIN wwo_article wwa_unc1 ON bo_ww.obj_id = wwa_unc1.id
left JOIN ww_order wwo_unc1 ON wwo_unc1.id = wwa_unc1.wwo_id
LEFT JOIN users driver_users1 ON wwo_unc1.driver_username=driver_users1.username 
left JOIN warehouse w_driver1 ON driver_users1.driver_warehouse_id = w_driver1.warehouse_id
			left JOIN wwo_article wwa_unc ON o.id = wwa_unc.uncomplete_article_order_id
			left JOIN ww_order wwo_unc ON wwo_unc.id = wwa_unc.wwo_id
			LEFT JOIN users driver_users ON wwo_unc.driver_username=driver_users.username 
			left JOIN warehouse w_driver ON driver_users.driver_warehouse_id = w_driver.warehouse_id
			JOIN auction au ON o.auction_number = au.auction_number
			AND o.txnid = au.txnid
					left join auction_par_varchar au_company_shipping on au.auction_number=au_company_shipping.auction_number 
						and au.txnid=au_company_shipping.txnid and au_company_shipping.key='company_shipping'
					left join auction_par_varchar au_firstname_shipping on au.auction_number=au_firstname_shipping.auction_number 
						and au.txnid=au_firstname_shipping.txnid and au_firstname_shipping.key='firstname_shipping'
					left join auction_par_varchar au_name_shipping on au.auction_number=au_name_shipping.auction_number 
						and au.txnid=au_name_shipping.txnid and au_name_shipping.key='name_shipping'
					left join auction_par_varchar au_street_shipping on au.auction_number=au_street_shipping.auction_number 
						and au.txnid=au_street_shipping.txnid and au_street_shipping.key='street_shipping'
					left join auction_par_varchar au_house_shipping on au.auction_number=au_house_shipping.auction_number 
						and au.txnid=au_house_shipping.txnid and au_house_shipping.key='house_shipping'
					left join auction_par_varchar au_zip_shipping on au.auction_number=au_zip_shipping.auction_number 
						and au.txnid=au_zip_shipping.txnid and au_zip_shipping.key='zip_shipping'
					left join auction_par_varchar au_country_shipping on au.auction_number=au_country_shipping.auction_number 
						and au.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
					left join auction_par_varchar au_city_shipping on au.auction_number=au_city_shipping.auction_number 
						and au.txnid=au_city_shipping.txnid and au_city_shipping.key='city_shipping'
			left JOIN warehouse w ON IFNULL(IF(wwa_unc1.delivered, wwa_unc1.to_warehouse, IF(wwa_unc1.taken, w_driver1.warehouse_id, o.new_article_warehouse_id))
	, IF(wwa_unc.delivered, wwa_unc.to_warehouse, IF(wwa_unc.taken, w_driver.warehouse_id, o.new_article_warehouse_id))) = w.warehouse_id
		WHERE o.article_id = '$article_id' and o.new_article = 1 and o.new_article_completed = 1 
			and NOT IFNULL(o.new_article_not_deduct,0)
			and w.warehouse_id in ($warehouse_filter)
union all
SELECT 
			  	ah.article_id
				, ah.date
				, ah.comment
				, ah.quantity
				, ah.id
				, ah.user as user
				, null as rma_id
				, null as order_id
				, '' as TYPE
			, null as auction_number
		        , null as txnid
#		        , IFNULL( users.name, ah.user ) as username
			, null as end_time
			, w.warehouse_id
			, w.name warehouse_name
			, '' address
			, '' country_shipping
			, '' shipping_method
		  FROM article_history ah
			LEFT JOIN users ON ah.user=users.username 
			left JOIN warehouse w ON ah.warehouse_id = w.warehouse_id
		WHERE ah.article_id = '$article_id'
			and ah.warehouse_id in ($warehouse_filter)
		) t 
			LEFT JOIN users uu ON t.user=uu.username 
			LEFT JOIN warehouse ww ON ww.warehouse_id=t.send_warehouse_id 
			where 1 $where
			"
	  .$orderby.($step>=0? " LIMIT ".($step*100).", 100" : '');	
	  global $dbr_spec;
      
        $r = $dbr_spec->getAll($q);
        if (PEAR::isError($r)) {
           aprint_r($r);
        }
		foreach($r as $k=>$dummy) {
			$r[$k]->comment = trim(/*utf8_decode*/($r[$k]->comment));
		}
//		echo $q;		
        return $r;
    }


    static function listAuctions($db, $dbr, $article_id, $step=0, $sort='', $dir=1, $filter=array())
    {	
        if (!strlen($sort)) $orderby = "ORDER BY `last_date` desc";
        else $orderby = "ORDER BY `$sort`".($dir==-1 ? " desc" : "");
		global $warehouse_filter;
		/* Table for barcode warehouse, if use denormalization - barcode_dn */
		$vbw = 'vbarcode_warehouse';
		if ($GLOBALS['CONFIGURATION']['use_dn']) $vbw = 'barcode_dn';
		$where = '';
		if ($filter['warehouse_id']) {
			$where .= " and o.reserve_warehouse_id=".$filter['warehouse_id'];
			$where1 .= " and w.warehouse_id=".$filter['warehouse_id'];
			$where2 .= " and wwa.reserved_warehouse=".$filter['warehouse_id'];
		}
		$q = "select t.* from (
			SELECT 
				'Auction' type,
			  	o.article_id
				, i.invoice_date as date
				, CONCAT('Reserved by Auftrag ', IFNULL(mau.auction_number,au.auction_number), ' / ', IFNULL(mau.txnid,au.txnid)) comment
				, IF(o.wwo_order_id, CONCAT(' WWO#',wwa.wwo_id),'') wwo_comment
				, wwa.wwo_id
				, o.quantity
			  	,  o.quantity as sumquantity
				, i.invoice_date as last_date 
		       , IFNULL(mau.auction_number,au.auction_number) auction_number
		       , IFNULL(mau.txnid,au.txnid) txnid
			   , IFNULL(mau.end_time, au.end_time) end_time
			, o.reserve_warehouse_id
			, w.name warehouse_name
			, o.id
			, au.route_id
			, route.name route_name
			, au_country_shipping.value country_shipping
			, (select sm.company_name
				from tracking_numbers tn
				join shipping_method sm on tn.shipping_method=sm.shipping_method_id
				join tn_orders tno on tno.tn_id=tn.id
				where tno.order_id=o.id
				limit 1
				) shipping_method
			, GROUP_CONCAT(b.barcode SEPARATOR ', ') barcodes
		  FROM orders o
			JOIN auction au ON o.auction_number = au.auction_number
				AND o.txnid = au.txnid
			LEFT JOIN auction mau ON mau.auction_number = au.main_auction_number
				AND mau.txnid = au.main_txnid
			left join auction_par_varchar au_country_shipping on au.auction_number=au_country_shipping.auction_number 
				and au.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
			LEFT JOIN invoice i ON au.invoice_number = i.invoice_number
			left JOIN warehouse w ON o.reserve_warehouse_id = w.warehouse_id
			left JOIN wwo_article wwa ON o.wwo_order_id = wwa.id
			left join route on IFNULL(mau.route_id, au.route_id) = route.id
			left join barcode_object bo ON bo.obj = 'orders' and bo.obj_id = o.id
			left join vbarcode b ON b.id = bo.barcode_id
		WHERE o.article_id = '$article_id' and o.manual=0
			AND o.sent=0
			AND au.deleted = 0	and IFNULL(mau.deleted,0)=0
			$where
			and IFNULL(o.reserve_warehouse_id,0) in ($warehouse_filter)
		GROUP BY o.id

		UNION ALL

			/* Part taken for case when barcode was added */
			SELECT 
				'Auction' type,
			  	o.new_article_id
				, IF(wwa_unc.delivered, 
					(select Updated
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='wwo_article' and Field_name='delivered' and new_value=1
				and TableID=wwa_unc.id order by Updated desc limit 1), 
					IF(wwa_unc.taken, 
					(select Updated
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='wwo_article' and Field_name='taken' and new_value=1
				and TableID=wwa_unc.id order by Updated desc limit 1), 
					(select Updated
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='new_article'
				and TableID=o.id order by Updated desc limit 1)))
				  as date
				, CONCAT(IF(new_article_completed, '', GROUP_CONCAT(CONCAT('Part taken for Auftrag ', o.auction_number, ' / ', o.txnid) SEPARATOR ', '))
, IFNULL(CONCAT(',', (select group_concat(CONCAT('Part taken for Auftrag ', o2.auction_number, ' / ', o2.txnid)) 
from orders o2 where o2.uncomplete_article_order_id=o.id and not o2.new_article_completed)),'')
) comment
				, IF(o.wwo_order_id, CONCAT(' WWO#',wwa.wwo_id),'') wwo_comment
				, wwa.wwo_id
				, 1 #o.new_article_qnt
			  	, 1 #o.new_article_qnt as sumquantity
				, IF(wwa_unc.delivered, 
					(select Updated
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='wwo_article' and Field_name='delivered' and new_value=1
				and TableID=wwa_unc.id order by Updated desc limit 1), 
					IF(wwa_unc.taken, 
					(select Updated
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='wwo_article' and Field_name='taken' and new_value=1
				and TableID=wwa_unc.id order by Updated desc limit 1), 
					(select Updated
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='new_article'
				and TableID=o.id order by Updated desc limit 1)))
				  as last_date 
		       , CONCAT(IF(new_article_completed, '',group_concat(au.auction_number))
, IFNULL(CONCAT(',', (select group_concat(o2.auction_number) 
from orders o2 where o2.uncomplete_article_order_id=o.id)),'')
	) auction_number
		       , CONCAT(IF(new_article_completed, '',group_concat(au.txnid))
, IFNULL(CONCAT(',', (select group_concat(o2.txnid) 
from orders o2 where o2.uncomplete_article_order_id=o.id)),'')
	) txnid
			   , au.end_time
			, w.warehouse_id
			, w.name warehouse_name
			, o.id
			, au.route_id
			, route.name route_name
			, au_country_shipping.value country_shipping
			, (select sm.company_name
				from tracking_numbers tn
				join shipping_method sm on tn.shipping_method=sm.shipping_method_id
				join tn_orders tno on tno.tn_id=tn.id
				where tno.order_id=o.id
				limit 1
				) shipping_method
			, GROUP_CONCAT(b.barcode SEPARATOR ', ') barcodes
		  FROM orders o
			left JOIN wwo_article wwa_unc ON o.id = wwa_unc.uncomplete_article_order_id
			left JOIN ww_order wwo_unc ON wwo_unc.id = wwa_unc.wwo_id
			LEFT JOIN users driver_users ON wwo_unc.driver_username=driver_users.username 
			left JOIN warehouse w_driver ON driver_users.driver_warehouse_id = w_driver.warehouse_id
			left JOIN wwo_article wwa ON o.wwo_order_id = wwa.id
			JOIN auction au ON o.auction_number = au.auction_number
				AND o.txnid = au.txnid
			LEFT JOIN auction mau ON mau.auction_number = au.main_auction_number
				AND mau.txnid = au.main_txnid
			left join auction_par_varchar au_country_shipping on au.auction_number=au_country_shipping.auction_number 
				and au.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
			LEFT JOIN invoice i ON au.invoice_number = i.invoice_number
		    left join barcode_object bo ON bo.obj = 'decompleted_article' and bo.obj_id = o.id
		    left join {$vbw} bw ON bw.id = bo.barcode_id
		    left join vbarcode b ON b.id = bo.barcode_id
		    left JOIN warehouse w ON IF(wwa_unc.delivered, wwa_unc.to_warehouse, IF(wwa_unc.taken, w_driver.warehouse_id, IF(bo.barcode_id, bw.last_warehouse_id, o.new_article_warehouse_id))) = w.warehouse_id
			left join route on IFNULL(mau.route_id, au.route_id) = route.id
		WHERE o.new_article_id = '$article_id'
			AND au.deleted = 0	
			$where1
			and new_article and not lost_new_article
			and (NOT new_article_completed or exists (select NULL
				from orders o2 where o2.uncomplete_article_order_id=o.id  and NOT o2.new_article_completed))
			and IFNULL(new_article_not_deduct,0)=0
			and IFNULL(o.new_article_warehouse_id,0) in ($warehouse_filter)
			and (bo.barcode_id != 0 and not ISNULL(bo.barcode_id))
			GROUP BY bo.barcode_id

		UNION ALL

			/* Part taken for case when barcode was NOT added */
			SELECT 
				'Auction' type,
			  	o.new_article_id
				, IF(wwa_unc.delivered, 
					(select Updated
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='wwo_article' and Field_name='delivered' and new_value=1
				and TableID=wwa_unc.id order by Updated desc limit 1), 
					IF(wwa_unc.taken, 
					(select Updated
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='wwo_article' and Field_name='taken' and new_value=1
				and TableID=wwa_unc.id order by Updated desc limit 1), 
					(select Updated
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='new_article'
				and TableID=o.id order by Updated desc limit 1)))
				  as date
				, CONCAT(IF(new_article_completed, '', CONCAT('Part taken for Auftrag ', o.auction_number, ' / ', o.txnid))
, IFNULL(CONCAT(',', (select group_concat(CONCAT('Part taken for Auftrag ', o2.auction_number, ' / ', o2.txnid)) 
from orders o2 where o2.uncomplete_article_order_id=o.id and not o2.new_article_completed)),'')
) comment
				, IF(o.wwo_order_id, CONCAT(' WWO#',wwa.wwo_id),'') wwo_comment
				, wwa.wwo_id
				, 1 #o.new_article_qnt
			  	, 1 #o.new_article_qnt as sumquantity
				, IF(wwa_unc.delivered, 
					(select Updated
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='wwo_article' and Field_name='delivered' and new_value=1
				and TableID=wwa_unc.id order by Updated desc limit 1), 
					IF(wwa_unc.taken, 
					(select Updated
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='wwo_article' and Field_name='taken' and new_value=1
				and TableID=wwa_unc.id order by Updated desc limit 1), 
					(select Updated
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='new_article'
				and TableID=o.id order by Updated desc limit 1)))
				  as last_date 
		       , CONCAT(IF(new_article_completed, '',au.auction_number)
, IFNULL(CONCAT(',', (select group_concat(o2.auction_number) 
from orders o2 where o2.uncomplete_article_order_id=o.id)),'')
	) auction_number
		       , CONCAT(IF(new_article_completed, '',au.txnid)
, IFNULL(CONCAT(',', (select group_concat(o2.txnid) 
from orders o2 where o2.uncomplete_article_order_id=o.id)),'')
	) txnid
			   , au.end_time
			, w.warehouse_id
			, w.name warehouse_name
			, o.id
			, au.route_id
			, route.name route_name
			, au_country_shipping.value country_shipping
			, (select sm.company_name
				from tracking_numbers tn
				join shipping_method sm on tn.shipping_method=sm.shipping_method_id
				join tn_orders tno on tno.tn_id=tn.id
				where tno.order_id=o.id
				limit 1
				) shipping_method
			, GROUP_CONCAT(b.barcode SEPARATOR ', ') barcodes
		  FROM orders o
			left JOIN wwo_article wwa_unc ON o.id = wwa_unc.uncomplete_article_order_id
			left JOIN ww_order wwo_unc ON wwo_unc.id = wwa_unc.wwo_id
			LEFT JOIN users driver_users ON wwo_unc.driver_username=driver_users.username 
			left JOIN warehouse w_driver ON driver_users.driver_warehouse_id = w_driver.warehouse_id
			left JOIN wwo_article wwa ON o.wwo_order_id = wwa.id
			JOIN auction au ON o.auction_number = au.auction_number
				AND o.txnid = au.txnid
			LEFT JOIN auction mau ON mau.auction_number = au.main_auction_number
				AND mau.txnid = au.main_txnid
			left join auction_par_varchar au_country_shipping on au.auction_number=au_country_shipping.auction_number 
				and au.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
			LEFT JOIN invoice i ON au.invoice_number = i.invoice_number
		    left join barcode_object bo ON bo.obj = 'decompleted_article' and bo.obj_id = o.id
		    left join {$vbw} bw ON bw.id = bo.barcode_id
		    left join vbarcode b ON b.id = bo.barcode_id
		    left JOIN warehouse w ON IF(wwa_unc.delivered, wwa_unc.to_warehouse, IF(wwa_unc.taken, w_driver.warehouse_id, IF(bo.barcode_id, bw.last_warehouse_id, o.new_article_warehouse_id))) = w.warehouse_id
			left join route on IFNULL(mau.route_id, au.route_id) = route.id
		WHERE o.new_article_id = '$article_id'
			AND au.deleted = 0	
			$where1
			and new_article and not lost_new_article
			and (NOT new_article_completed or exists (select NULL
				from orders o2 where o2.uncomplete_article_order_id=o.id  and NOT o2.new_article_completed))
			and IFNULL(new_article_not_deduct,0)=0
			and IFNULL(o.new_article_warehouse_id,0) in ($warehouse_filter)
			and (bo.barcode_id = 0 or ISNULL(bo.barcode_id))
		GROUP BY o.id
			
		UNION ALL
			SELECT 
				'Auction' type,
			  	o.new_article_id
				, (select Updated
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='new_article_completed'
				and TableID=o.id order by Updated desc limit 1)  as date
				, CONCAT('New article completed by Auftrag ', o.auction_number, ' / ', o.txnid) comment
				, IF(o.wwo_order_id, CONCAT(' WWO#',wwa.wwo_id),'') wwo_comment
				, wwa.wwo_id
				, -o.new_article_qnt
			  	, -o.new_article_qnt as sumquantity
				, (select Updated
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='orders' and Field_name='new_article_completed'
				and TableID=o.id order by Updated desc limit 1) as last_date 
		       , au.auction_number
		       , au.txnid
			   , au.end_time
			, o.new_article_warehouse_id
			, w.name warehouse_name
			, o.id
			, au.route_id
			, route.name route_name
			, au_country_shipping.value country_shipping
			, (select sm.company_name
				from tracking_numbers tn
				join shipping_method sm on tn.shipping_method=sm.shipping_method_id
				join tn_orders tno on tno.tn_id=tn.id
				where tno.order_id=o.id
				limit 1
				) shipping_method
			, '' barcodes
		  FROM orders o
			left JOIN wwo_article wwa ON o.wwo_order_id = wwa.id
			JOIN auction au ON o.auction_number = au.auction_number
				AND o.txnid = au.txnid
			LEFT JOIN auction mau ON mau.auction_number = au.main_auction_number
				AND mau.txnid = au.main_txnid
			left join auction_par_varchar au_country_shipping on au.auction_number=au_country_shipping.auction_number 
				and au.txnid=au_country_shipping.txnid and au_country_shipping.key='country_shipping'
			LEFT JOIN invoice i ON au.invoice_number = i.invoice_number
			left JOIN warehouse w ON o.new_article_warehouse_id = w.warehouse_id
			left join route on IFNULL(mau.route_id, au.route_id) = route.id
		WHERE 0 and o.new_article_id = '$article_id'
			AND au.deleted = 0	
			$where1
			and new_article and new_article_completed and NOT IFNULL(o.new_article_not_deduct,0)
			and IFNULL(o.new_article_warehouse_id,0) in ($warehouse_filter)

		UNION ALL
			SELECT 
				'WWO' type,
			  	wwa.article_id	
				, (select Updated
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='wwo_article' and Field_name='article_id'
				and TableID=wwa.id order by Updated desc limit 1)  as date
				, CONCAT('Reserved by WWO ', wwa.wwo_id) comment
				, NULL wwo_comment
				, wwa.wwo_id
				, wwa.qnt
			  	, wwa.qnt as sumquantity
				, (select Updated
				from total_log 
				left join users on users.system_username=total_log.username
				where table_name='wwo_article' and Field_name='article_id'
				and TableID=wwa.id order by Updated desc limit 1) as last_date 
		       , NULL
		       , NULL
			   , NULL #endtime
			, wwa.reserved_warehouse
			, w.name warehouse_name
			, wwa.id
			, 0 route_id
			, '' route_name
			, '' country_shipping
			, '' shipping_method
			, GROUP_CONCAT(b.barcode SEPARATOR ', ') barcodes
		  FROM wwo_article wwa 
			left JOIN warehouse w ON wwa.reserved_warehouse = w.warehouse_id
			left join barcode_object bo ON bo.obj = 'wwo_article' and bo.obj_id = wwa.id
			left join vbarcode b ON b.id = bo.barcode_id
		WHERE wwa.article_id = '$article_id'
			$where2
			and not wwa.taken
			and IFNULL(wwa.reserved_warehouse,0) in ($warehouse_filter)
		GROUP BY wwa.id
			) t
			"
	  .$orderby." LIMIT ".($step*100).", 100";
//		echo $q;
        $r = $dbr->getAll($q);
        if (PEAR::isError($r)) {
           aprint_r($r);
        }
        return $r;
    }

    static function deleteAuction($db, $dbr, $auction_number, $txnid, $article_id)
    {	
            return;
    }

    static function listOrders($db, $dbr, $article_id, $step=0, $not_arrived=0)
    {	
		$where = '';
		if (!is_array($article_id)) $article_id = array($article_id);
        
        $article_id = array_map('intval', $article_id);
        
		if ($not_arrived) $where .= " and IFNULL(opc.arrival_date,'2999-12-31')>NOW() ";
		$q = "SELECT opo.id, opo.number, opo.invoice_number, opo.EDD, opo.EDA, sum( opa.qnt_ordered ) as sum_ordered, sum( opa.qnt_delivered ) as sum_delivered
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
					,wc.name
					,IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00','</font>','')
				) order by opc.id separator '<br>')
			,'</td><td nowrap valign=\'top\' align=\'right\'>',GROUP_CONCAT(
				CONCAT(IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00','<font color=\'gray\'>','')
					,(select sum(qnt_ordered) from op_article opa 
						where opa.op_order_id=opc.order_id and opa.container_id=opc.id
						and opa.article_id in (".implode(",", $article_id).")
					)
					,IF(IFNULL(opc.arrival_date,'0000-00-00')<>'0000-00-00','</font>','')
				) order by opc.id separator '<br>')
			,'</td></tr></table>') from op_order_container opc 
				LEFT JOIN warehouse wc ON opc.planned_warehouse_id = wc.warehouse_id
				left join country c on c.code=wc.country_code
				left join translation t on t.table_name='country' and t.field_name='name' and t.id=c.id and t.language='master'
				where opc.order_id = opo.id) containers1
			, opo.warehouse_id
			, w.name warehouse_name
#			, GROUP_CONCAT(distinct opc.id) containers
			, GROUP_CONCAT(distinct opc.id) container_ids
			, IFNULL(GROUP_CONCAT(distinct op_order_container.id),0) container_ids1
			FROM op_order_container
			left join op_order_container master on master.id=op_order_container.master_id
			left join op_order_container opc on IFNULL(master.id, op_order_container.id) = opc.id
			LEFT JOIN op_order opo ON opc.order_id = opo.id 
			JOIN op_article opa ON op_order_container.id=opa.container_id
			left JOIN warehouse w ON opa.warehouse_id = w.warehouse_id
			WHERE opa.article_id in (".implode(",", $article_id).")
			$where
			AND (NOT opa.add_to_warehouse OR opa.add_to_warehouse IS NULL)
			and IFNULL(opo.close_date, '0000-00-00 00:00:00')='0000-00-00 00:00:00'
			GROUP BY opo.id, opo.number, opo.invoice_number ".
			" LIMIT ".($step*100).", 100";
//		echo nl2br($q).'<br>';
        $r = $dbr->getAll($q);
		global $smarty;
		$cont_statuses = $dbr->getAssoc("select id, name from op_container_status");
		$smarty->assign('cont_statuses', $cont_statuses);
		$smarty->assign('warehouses', Warehouse::listArray($db, $dbr));
		$companiesShipping = op_Order::listCompaniesArray($db, $dbr, 'shipping');
		$smarty->assign('companiesShipping', $companiesShipping);
		$destination_terminals = $dbr->getAssoc("select id, name from op_destination_terminal");
		$smarty->assign('destination_terminals', $destination_terminals);
        foreach($r as $kk=>$row) {
			if (strlen($row->container_ids) || strlen($row->container_ids1)) {
                
                $container_ids = explode(',', $row->container_ids);
                $container_ids = array_map(function($v){
                    return "'" . mysql_real_escape_string($v) . "'";
                }, $container_ids);
                
                $container_ids1 = explode(',', $row->container_ids1);
                $container_ids1 = array_map(function($v){
                    return "'" . mysql_real_escape_string($v) . "'";
                }, $container_ids1);
                
                $container_ids = array_merge($container_ids, $container_ids1);
                $container_ids = implode(',', $container_ids);
                
				$q = "select opc.*
					, (op_order_container.master_id) real_master_id, (master.order_id) master_order_id
					, opc.id real_id
					, c.code dest_country_code
					, ocs.name status_name
					, t.value dest_country_name
					, od.name demurrage_name
					, wc.name planned_warehouse
					, DATE_FORMAT(opc.delivery_time, '%H:%i') delivery_time_hm
					, (select DATE(el.`date`)
                    from email_log el
					where el.template='op_container_delivery_document' and el.notes*1=opc.id
					and el.auction_number=opc.order_id and el.txnid=-1
					and el.recipient like CONCAT('%',seller_information.email,'%')
					order by el.`date` desc limit 1) importer_last_sent
					, (select DATE(el.`date`)
					from email_log el
					where el.template='op_container_delivery_document' and el.notes*1=opc.id
					and el.auction_number=opc.order_id and el.txnid=-1
					and el.recipient like CONCAT('%',op_return_terminal.email,'%')
					order by el.`date` desc limit 1) return_terminal_last_sent
					, (select DATE(el.`date`)
					from email_log el
					where el.template='op_container_delivery_document' and el.notes*1=opc.id
					and el.auction_number=opc.order_id and el.txnid=-1
					and el.recipient like CONCAT('%',op_destination_terminal.email,'%')
					order by el.`date` desc limit 1) destination_terminal_last_sent
					, (select DATE(el.`date`)
					from email_log el
					where el.template='op_container_delivery_document' and el.notes*1=opc.id
					and el.auction_number=opc.order_id and el.txnid=-1
					and el.recipient like CONCAT('%',op_company.email,'%')
					order by el.`date` desc limit 1) trucking_company_last_sent
					, (select DATE(el.`date`)
					from email_log el
					where el.template='op_container_delivery_document' and el.notes*1=opc.id
					and el.auction_number=opc.order_id and el.txnid=-1
					and el.recipient like CONCAT('%',op_address_resp.email,'%')
					order by el.`date` desc limit 1) resp_address_last_sent
					, (select DATE(el.`date`)
					from email_log el
					where el.template='op_container_delivery_document' and el.notes*1=opc.id
					and el.auction_number=opc.order_id and el.txnid=-1
					and el.recipient like CONCAT('%',fget_WarehouseEmail(wc.warehouse_id),'%')
					order by el.`date` desc limit 1) planned_warehouse_last_sent
					, CONCAT(seller_information.username, ' = ', seller_information.seller_name) importer_name
					, opc1.name shipping_company_name
					, op_destination_terminal.name destination_terminal_name
					, op_container.name container_name
					, op_content.content op_content
					, op_address_resp.name resp_address_name
					, users.name counted_by_name
					, (select sum(qnt_ordered) from op_article where op_article.container_id=op_order_container.id 
						and op_article.article_id in (".implode(",", $article_id).")) article_qnt_ordered
                            
                    , (SELECT CONCAT('Was changed by ', IFNULL(`u`.`name`, `tl`.`username`), ' on ', `tl`.`updated`)
                        FROM `total_log` AS `tl`
                        LEFT JOIN `users` AS `u` ON `u`.`system_username` = `tl`.`username`
                        WHERE `table_name` = 'op_order_container' AND `field_name` = 'ptd' AND `tableid` = `opc`.`id`
                        ORDER BY `updated` limit 1) AS `change_ptd`
                    , (SELECT CONCAT('Was changed by ', IFNULL(`u`.`name`, `tl`.`username`), ' on ', `tl`.`updated`)
                        FROM `total_log` AS `tl`
                        LEFT JOIN `users` AS `u` ON `u`.`system_username` = `tl`.`username`
                        WHERE `table_name` = 'op_order_container' AND `field_name` = 'eda' AND `tableid` = `opc`.`id`
                        ORDER BY `updated` limit 1) AS `change_eda`
                    , (SELECT CONCAT('Was changed by ', IFNULL(`u`.`name`, `tl`.`username`), ' on ', `tl`.`updated`)
                        FROM `total_log` AS `tl`
                        LEFT JOIN `users` AS `u` ON `u`.`system_username` = `tl`.`username`
                        WHERE `table_name` = 'op_order_container' AND `field_name` = 'edd' AND `tableid` = `opc`.`id`
                        ORDER BY `updated` limit 1) AS `change_edd`            

			from op_order_container 
			left join op_order_container master on master.id=op_order_container.master_id
			left join op_order_container opc on opc.id = IFNULL(master.id, op_order_container.id)
					LEFT JOIN op_container_status ocs ON ocs.id = opc.status_id 
					left join op_content on op_content.id=opc.op_content_id
					LEFT JOIN op_demurrage od ON od.id = opc.demurrage
					LEFT JOIN warehouse wc ON opc.planned_warehouse_id = wc.warehouse_id
					left join country c on c.code=wc.country_code
					left join translation t on t.table_name='country' and t.field_name='name' and t.id=c.id and t.language='master'
					left join op_address_resp on op_address_resp.id=opc.resp_address_id
					left join seller_information on seller_information.username=opc.importer_id
					left join op_company on op_company.id=opc.trucking_company_id
					left join op_company opc1 on opc1.id=opc.shipping_company_id
					left join op_destination_terminal on op_destination_terminal.id=opc.destination_terminal_id
					left join op_destination_terminal op_return_terminal on op_return_terminal.id=opc.return_terminal_id
					left join op_container on op_container.id=opc.container
					left join users on users.username = opc.counted_by
				where opc.id in ($container_ids)
					ORDER BY id";
                
		        $containers = $dbr->getAll($q);
				foreach($containers as $k=>$dummy) {
					$containers[$k]->planned_warehouse = utf8_decode($containers[$k]->planned_warehouse);
					$containers[$k]->subs = $dbr->getAll("select * from op_order_container where master_id=".$containers[$k]->real_id);
				}
				$smarty->assign('containers', $containers);
				$smarty->assign('article_id', implode("','", $article_id));
				$r[$kk]->containers = $smarty->fetch('_op_containers_short.tpl');
			} // if count of containers
		} // foreach	
//		echo $q;
        if (PEAR::isError($r)) {
            aprint_r($r);
        }
        return $r;
    }

    static function listByOrder($db, $dbr, $order_id)
    {	
		$q = "SELECT 
			  	oa.article_id
				, oa.add_to_warehouse_date as date
				, CONCAT('Order ', o.id, 
			IF(oa.add_to_warehouse,' delivered',' removed')) as comment
				, IF(oa.add_to_warehouse,1,-1)*oa.qnt_delivered quantity
				, null as id
				, oa.add_to_warehouse_uname as user
				, null as rma_id
				, o.id as order_id
				, 'Order' as TYPE
			, null as auction_number
		        , null as txnid
		        , IFNULL( users.name, oa.add_to_warehouse_uname) as username
			, null as end_time
			, oa.warehouse_id
			, w.name warehouse_name
			, (SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = 'german'
				AND id = a.article_id) article_name
		  FROM op_article oa
		  	join article a on oa.article_id=a.article_id and a.admin_id=0
			join op_order o on o.id=oa.op_order_id
			left JOIN warehouse w ON oa.warehouse_id = w.warehouse_id
			LEFT JOIN users ON oa.add_to_warehouse_uname=users.username 
			WHERE oa.op_order_id = $order_id and oa.add_to_warehouse 
			#is not null
		";
        $r = $dbr->getAll($q);
//		echo $q;
		if (PEAR::isError($r)) {
            aprint_r($r);
        }
        return $r;
    }

    static function listByMoving($db, $dbr)
    {	
		$q = "SELECT 
			  	min(ah.article_id) article_id
				, min(ah.date) `date`
				, min(ah.comment) `comment`
				, max(ah.quantity) quantity
				, move_id
				, min(ah.user) as user
		        , max(IFNULL( users.name, ah.user )) as username
				, ah.comment
			, GROUP_CONCAT(IFNULL(w1.name,'') SEPARATOR '') warehouse_from
			, GROUP_CONCAT(IFNULL(w2.name,'') SEPARATOR '') warehouse_to
			, (SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = 'german'
				AND id = a.article_id) article_name
		  FROM article_history ah
			LEFT JOIN article a ON ah.article_id=a.article_id and a.admin_id=0
			LEFT JOIN users ON ah.user=users.username 
			left JOIN warehouse w1 ON IF(ah.quantity<0, ah.warehouse_id,0) = w1.warehouse_id
			left JOIN warehouse w2 ON IF(ah.quantity>0, ah.warehouse_id,0) = w2.warehouse_id
		WHERE IFNULL(move_id,0)
		group by move_id
		";
//		echo $q;
        $r = $dbr->getAll($q);
		if (PEAR::isError($r)) {
            aprint_r($r);
        }
        return $r;
    }

    static function listByRMA($db, $dbr, $rma_id)
    {
        $r = $dbr->getAll("select * from (
		SELECT 
			  	rs.article_id
				, total_log.updated as date
				, CONCAT(rp.name, '# ', r.rma_id) as comment
				, 1 as quantity
				, null as id
				, IFNULL(users.name,total_log.username) as user
				, r.rma_id as rma_id
				, null as order_id
				, 'Wrong' as TYPE
			, r.auction_number
		        , r.txnid
		        , IFNULL( users.name, r.responsible_uname ) as username
			, auction.end_time
			, IFNULL(auw.warehouse_id, w.warehouse_id) warehouse_id
			, IFNULL(auw.name, w.name) warehouse_name
			, (SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = 'german'
				AND id = a.article_id) article_name
		  FROM rma r
			join auction au on r.auction_number=au.auction_number and r.txnid=au.txnid
			JOIN rma_spec rs ON r.rma_id=rs.rma_id
			JOIN rma_problem rp ON rp.problem_id=rs.problem_id
		  	join article a on rs.article_id=a.article_id and a.admin_id=0
			left join (select 
				SUBSTRING_INDEX(GROUP_CONCAT(Updated order by updated desc),',',1)  updated
				, SUBSTRING_INDEX(GROUP_CONCAT(username order by updated desc),',',1)  username
				, TableID 
				from total_log where 1
				and Table_name='rma_spec'
				and Field_name='problem_id'
				and New_value in ('4','11')
				group by TableID
			) total_log on 1 and TableID=rs.rma_spec_id
			left join users on users.system_username=total_log.username
			left JOIN warehouse w ON `default`
			LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
			left JOIN warehouse auw ON rs.send_warehouse_id=auw.warehouse_id
		WHERE r.rma_id = $rma_id and rs.problem_id in (4,11)
union all
SELECT 
			  	rs.article_id
				, IFNULL(rs.return_date, '0000-00-00') as date
				, CONCAT('Back to warehouse wrong item sent Ticket# ', r.rma_id) as comment
				, -1 as quantity
				, null as id
				, rs.responsible_uname as user
				, r.rma_id as rma_id
				, null as order_id
				, 'RMA' as TYPE
			, r.auction_number
		        , r.txnid
		        , IFNULL( users.name, rs.responsible_uname ) as username
			, auction.end_time
			, w.warehouse_id
			, w.name warehouse_name
			, (SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = 'german'
				AND id = a.article_id) article_name
		  FROM rma r
			JOIN rma_spec rs ON r.rma_id=rs.rma_id
		  	join article a on rs.article_id=a.article_id and a.admin_id=0
			LEFT JOIN users ON rs.responsible_uname=users.username 
			left JOIN warehouse w ON rs.warehouse_id = w.warehouse_id
			LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
		WHERE r.rma_id = $rma_id and IFNULL(rs.add_to_stock,0) = 0 and rs.back_wrong_delivery=1
union all
SELECT 
			  	rs.article_id
				, IFNULL(rs.return_date, '0000-00-00') as date
				, CONCAT('Back to warehouse wrong item sent Ticket# ', r.rma_id) as comment
				, -1 as quantity
				, null as id
				, rs.responsible_uname as user
				, r.rma_id as rma_id
				, null as order_id
				, 'RMA' as TYPE
			, r.auction_number
		        , r.txnid
		        , IFNULL( users.name, rs.responsible_uname ) as username
			, auction.end_time
			, w.warehouse_id
			, w.name warehouse_name
			, (SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = 'german'
				AND id = a.article_id) article_name
		  FROM rma r
			JOIN rma_spec rs ON r.rma_id=rs.rma_id
		  	join article a on rs.article_id=a.article_id and a.admin_id=0
			LEFT JOIN users ON rs.responsible_uname=users.username 
			left JOIN warehouse w ON rs.warehouse_id = w.warehouse_id
			LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
		WHERE r.rma_id = $rma_id and rs.add_to_stock = 1 and rs.back_wrong_delivery=1
union all
SELECT 
			  	rs.article_id
				, IFNULL(rs.return_date, '0000-00-00') as date
				, CONCAT('Back to warehouse wrong item sent Ticket# ', r.rma_id) as comment
				, 1 as quantity
				, null as id
				, rs.responsible_uname as user
				, r.rma_id as rma_id
				, null as order_id
				, 'RMA' as TYPE
			, r.auction_number
		        , r.txnid
		        , IFNULL( users.name, rs.responsible_uname ) as username
			, auction.end_time
			, w.warehouse_id
			, w.name warehouse_name
			, (SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = 'german'
				AND id = a.article_id) article_name
		  FROM rma r
			JOIN rma_spec rs ON r.rma_id=rs.rma_id
		  	join article a on rs.article_id=a.article_id and a.admin_id=0
			LEFT JOIN users ON rs.responsible_uname=users.username 
			left JOIN warehouse w ON rs.returned_warehouse_id = w.warehouse_id
			LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
		WHERE r.rma_id = $rma_id and rs.add_to_stock = 1 and rs.back_wrong_delivery=1
union all
SELECT 
			  	rs.article_id
				, IFNULL(max(tl.updated), rs.return_date) as date
				, CONCAT('Back to warehouse correct item sent Ticket# ', r.rma_id) as comment
				, 1 as quantity
				, null as id
				, rs.responsible_uname as user
				, r.rma_id as rma_id
				, null as order_id
				, 'RMA' as TYPE
			, r.auction_number
		        , r.txnid
		        , IFNULL( users.name, rs.responsible_uname ) as username
			, auction.end_time
			, w.warehouse_id
			, w.name warehouse_name
			, (SELECT value
				FROM translation
				WHERE table_name = 'article'
				AND field_name = 'name'
				AND language = 'german'
				AND id = a.article_id) article_name
		  FROM rma r
			JOIN rma_spec rs ON r.rma_id=rs.rma_id
			left join total_log tl on tl.table_name='rma_spec' and tl.field_name='add_to_stock' and tl.tableid=rs.rma_spec_id and tl.new_value='1'
		  	join article a on rs.article_id=a.article_id and a.admin_id=0
			LEFT JOIN users ON rs.responsible_uname=users.username 
			left JOIN warehouse w ON rs.warehouse_id = w.warehouse_id
			LEFT JOIN auction ON r.auction_number=auction.auction_number and r.txnid=auction.txnid
		WHERE r.rma_id = $rma_id and rs.add_to_stock = 1 and IFNULL(rs.back_wrong_delivery, 0)=0
		group by rs.rma_spec_id	
		) t order by `date`");
        if (PEAR::isError($r)) {
            aprint_r($r);
        }
        return $r;
    }

    static function remove($db, $dbr, $id)
    {
        $db->query("DELETE FROM article_history WHERE id=$id");
    }

    static function listAllOrdered($db, $dbr, $opened){
        $r = $dbr->getAssoc("SELECT opa.article_id, sum( opa.qnt_ordered ) as sum_ordered
			FROM op_order opo
			JOIN op_article opa ON opo.id = opa.op_order_id
			AND (NOT opa.add_to_warehouse OR opa.add_to_warehouse IS NULL)
			".($opened?" and opo.close_date is null ":'')."
			GROUP BY opa.article_id");
        return $r;
    }

    static function getStatistic($db, $dbr, $article_id){
		$sites = $dbr->getAssoc("SELECT distinct config_api.siteid, config_api_values.description
			FROM orders 
			join auction on orders.auction_number=auction.auction_number and auction.txnid=orders.txnid
			join config_api on config_api.siteid=auction.siteid
			join config_api_values on config_api_values.par_id=config_api.par_id and config_api_values.value=config_api.value 
			WHERE article_id ='$article_id' and config_api.par_id=7");
//		echo $q;
		$res = array();
		foreach ($sites as $siteid=>$curr_code) {
			$q="SELECT 
				max( orders.price ) AS max_price, 
				min( orders.price ) AS min_price, 
				round(sum( orders.price * orders.quantity ) / sum( orders.quantity ), 2) AS avg_price, 
				(
				SELECT o1.price
				FROM orders o1
				join auction a1 on o1.auction_number=a1.auction_number and a1.txnid=o1.txnid
				WHERE o1.article_id ='$article_id' and a1.siteid=$siteid
				LIMIT 0 , 1) AS last_price
			FROM orders
			join auction on orders.auction_number=auction.auction_number and auction.txnid=orders.txnid
			WHERE article_id ='$article_id' and siteid=$siteid";
#			echo $q.'<br>';
			$res[$curr_code] = $dbr->getRow($q);
			if (PEAR::isError($r)) {
				aprint_r($r);
				return;
			}
		}
		return $res;
    }

    static function getSoldCountDay($db, $dbr, $article_id, $days, $cons = '', $warehouses=array()){
        if (!is_a($dbr, 'MDB2_Driver_mysql')) {
            $error = PEAR::raiseError('Auction::Auction expects its argument to be a MDB2_Driver_mysql object');
			print_r($error); die();
            return;
        }
		if ($cons == 'cons') 
			$art_cond = "article_id in (select article_id FROM article WHERE cons_id='$article_id')";
		else
			$art_cond = "article_id='$article_id'";
		if (!count($warehouses)) $warehouses[]=0;
        $r = $dbr->getOne("
		(select IFNULL((select SUM(o.quantity)
			FROM orders o
			JOIN auction au ON o.auction_number = au.auction_number
				AND o.txnid = au.txnid
			left join total_log on total_log.table_name='orders' and total_log.field_name='sent' 
				and total_log.tableid=o.id and total_log.New_value='1'
			WHERE $art_cond and o.manual=0
			AND total_log.updated >= DATE_SUB(NOW(), INTERVAL $days DAY)
			AND o.send_warehouse_id in (".implode(',', $warehouses).")
			AND au.deleted = 0),0))");
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
		return $r;
    }

    static function getSoldCountMonth($db, $dbr, $article_id, $mnth, $cons = ''){
		if ($cons == 'cons') 
			$art_cond = "article_id in (select article_id FROM article WHERE cons_id='$article_id')";
		else
			$art_cond = "article_id='$article_id'";
        if (!is_a($dbr, 'MDB2_Driver_mysql')) {
            $error = PEAR::raiseError('Auction::Auction expects its argument to be a MDB2_Driver_mysql object');
            return;
        }
        $r = $dbr->getOne("(select IFNULL((select SUM(o.quantity)
			FROM orders o
			JOIN auction au ON o.auction_number = au.auction_number
				AND o.txnid = au.txnid
			left join total_log on total_log.table_name='orders' and total_log.field_name='sent' 
				and total_log.tableid=o.id and total_log.New_value='1'
			WHERE $art_cond and o.manual=0
			AND total_log.updated >= DATE_SUB(NOW(), INTERVAL $mnth MONTH)
			AND au.deleted = 0),0))");
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
		return $r;
    }
    static function getAvgDailySoldCount($db, $dbr, $days){
		list($Y,$M,$D) = preg_split('/-/', date("Y-m-d"));
        $r = $dbr->getOne("
		(select IFNULL((select SUM(o.quantity)/$days
			FROM orders o
			JOIN auction au ON o.auction_number = au.auction_number
				AND o.txnid = au.txnid
			left join total_log on total_log.table_name='orders' and total_log.field_name='sent' 
				and total_log.tableid=o.id and total_log.New_value='1'
			WHERE $art_cond and o.manual=0
			AND total_log.updated >= DATE_SUB(NOW(), INTERVAL $days DAY)
			AND au.deleted = 0),0))");
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
		return $r;
    }
	
    static function getPeriodSales($db, $dbr, $days, $article_id){
        $r = $dbr->getAssoc("select FROM_DAYS( TO_DAYS( total_log.updated ) ) AS date, SUM(o.quantity)
			FROM orders o
			JOIN auction au ON o.auction_number = au.auction_number
				AND o.txnid = au.txnid
			left join total_log on total_log.table_name='orders' and total_log.field_name='sent' 
				and total_log.tableid=o.id and total_log.New_value='1'
			WHERE article_id='$article_id' and o.manual=0
			AND total_log.updated >= DATE_SUB(NOW(), INTERVAL $days DAY)
			AND au.deleted = 0
			GROUP BY TO_DAYS( total_log.updated )");
        if (PEAR::isError($r)) {
			aprint_r($r);
            return;
        }
		return $r;
	}
	
	static function stockRecals($db, $dbr, $article_id) {
		$db->query("call sp_Stock_recalc_by_Article('$article_id')");
	}
}
?>