<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */

/**
 * Smarty {ItemList} plugin
 *
 * Type:     function<br>
 * Name:     ItemList<br>
 * Purpose:  creating list table
 * $param: parameters
 * title - table title
 * fields - array of column titles
 * source - array of row information
 * action - array of row actions 
 * URL - URL
 * action_URL - URL for actions
 * recordkey - default sort column
 * onpage_qty - number of rows on page
 * cur_order_direction - order direction
 * curr_page - current page
 * pages - total amount of pages
 * total_rec - total amount of records in source
 * qty_list - array for numbers on page dropdown list
 * 
 * 
 * 
 */

function substitute($tpl, $vars)
{
    if (count($vars)) foreach ($vars as $name=>$value) {
        $template='/(?U)\[\[('.$name.')((\|([^\]]+))?)\]\]/e';
        $tpl=preg_replace($template," \$value ? \$value : '\\4'",$tpl);
    }
    return $tpl;
}



function smarty_function_ItemList($params, &$smarty)
{
	require_once $smarty->_get_plugin_filepath('function','html_options');
	require_once $smarty->_get_plugin_filepath('function','include_js_calendar');
	require_once $smarty->_get_plugin_filepath('function','java_select_date');
	require_once $smarty->_get_plugin_filepath('function','set_message');
		foreach ($params as $_key=>$_value) {
        switch ($_key) {
            case 'title':
                $title = $_value;
                break;
                
            case 'fields':
                $fields = $_value;
                break;
                
            case 'source':
                $source = $_value;
                break;

			case 'filter':
                $filter = $_value;
                break;

            case 'action':
                $action = $_value;
                break;
                
            case 'base_URL':
                $base_URL = $_value;
                break;

			case 'recordkey':
                $recordkey = $_value;
                break;
                
            case 'mail_key':
                $mail_key = $_value;
                break;
                
            case 'action_URL':
                $action_URL = $_value;
                break;
                
             case 'back_url':
                $back_URL = $_value;
                break;
			
            case 'onpage_qty':
                $onpage_qty = $_value;
                break;

			 case 'cur_order_direction':
                $cur_order_direction = $_value;
                break;

			 case 'curr_page':
                $curr_page = $_value;
                break;
                
             case 'pages':
                $pages = $_value;
                break;

			 case 'total_rec':
                $total_rec = $_value;
                break;
			case 'qty_list':
                $qty_list = $_value;
                break;
			case 'class':
                $class = $_value;
                break;
			case 'messages':
                $messages = $_value;
                break;
			case 'button':
                $button = $_value;
                break;
			case 'group':
                $group = $_value;
                break;
    		case 'active':
                $active = $_value;
                break;

			case 'total_line':
                $total_line = $_value;
                break;

			case 'header_line':
                $header_line = $_value;
                break;
			case 'no_link':
                $no_link = $_value;
                break;

        }
    }
	
    $field_count = count($fields)+2;
    $group_action = array();
	$html_result .= smarty_function_include_js_calendar($params, &$smarty);
	$html_result .= "<script language=\"JavaScript\" type=\"text/javascript\">";

	$html_result .= "function toggle(cb){";
	$html_result .= "cbs = document.getElementsByName('group[]');";
    $html_result .=  "for (i=0; i< cbs.length; i++) cbs.item(i).checked = cb.checked;}";
    
	$html_result .= "function func(x){";
    $html_result .= "document.form1.action.value = x;";
	$html_result .= "document.form1.submit();}";

	

	$html_result .= "</script>";

	foreach ($messages as $key=>$value)
	{
		$param0['type']=$value->type;
		$param0['text']=$value->text;
		
		$html_result .= smarty_function_set_message($param0, &$smarty);
	}


	$html_result .="<div style=\"text-align: left\">";
	$flag = 0;	
	if (!empty($header_line))
	{
		foreach ($header_line as $key2=>$value2) 
			{
					$flag ++;
					if ($flag == 1)
						$html_result .= "<a href=\"{$value2->URL}\">{$value2->name}</a>";
					else 
						$html_result .= "&nbsp;|&nbsp;<a href=\"{$value2->URL}\" target=\"_blank\">{$value2->name}</a>";
				
			}
		$html_result .="<br><br>";
	}

	$html_result .="</div>";
		
	$html_result .= "<form name=\"form1\" method=\"POST\" action=\"{$base_URL}\">";
	$html_result .= "<input type=\"hidden\" name=\"current_page\" value=\"{$curr_page}\">";
	if (!empty($filter))
	{
	$html_result .= "<table cellpadding=\"2\" cellspacing=\"0\" border=\"0\" class=\"filter_table\">";
	$html_result .="<tr>";
	$html_result .="<td>";

	
	foreach($filter as $key=>$value)
	{
		switch ($value['type'])
		{ 
			case strpos($value['type'],'enum'):
	
				$html_result .= "&nbsp;&nbsp; {$value['title']}:&nbsp;&nbsp";
				$params1['name'] = "select_".$value['name'];
				$params1['options'] = $value['source'];
				$params1['selected'] = $value['default_value'];
				$params1['style'] = "width:120px";
				if (isset($value['onclick'])) $params1['onChange'] = $value['onclick'];
				else unset($params1['onChange']);
				$html_result .= smarty_function_html_options($params1,&$smarty);
				break;

			case strpos($value['type'],'varchar'):
	
				$html_result .= "&nbsp;&nbsp; {$value['title']}:&nbsp;&nbsp";
				$html_result .= "<input type=\"text\" style=\"width:120px\" name=\"select_{$value['name']}\" value=\"{$value['default_value']}\">";
				break;
			
			case 'date':
				$html_result .= "&nbsp;&nbsp; {$value['title']}:&nbsp;&nbsp";
				$params2['name'] = "select_".$value['name'];
				$params2['start_date'] = $value['default_value'];
				$params2['size'] = "100";
				$html_result .= smarty_function_java_select_date($params2,&$smarty);
				break;		
		}
	}
	
	$html_result .="</td>";
	
	$html_result .="<td>";
	
	$html_result .= "<div id=\"fm-submit\">";
	$html_result .="<div style=\"text-align: right\">";
	$html_result .= "&nbsp;&nbsp;<button type=\"button\" method=\"GET\" value=\"search\" style=\"width: 120px;\" onclick=\"document.form1.current_page.value=1;document.form1.submit();\">Filter</button>";
	$html_result .="</div>";
	$html_result .= "</div>";
	$html_result .="</td>";
	$html_result .="</tr>";
	$html_result .="</table>";
	}
	

	$html_result .= "<input type=\"hidden\" name=\"action\" value=\"\"><div style=\"margin: 10px;\">";
  	$html_result .= "<table cellspacing=\"1\" cellpadding=\"0\" border=\"0\"  class=\"{$class}\" width=\"90%\">";
	if (!empty($title))
	{
		$html_result .= "<tr>";
		$html_result .=	"<th colspan=\"{$field_count}\" class=\"grey\" style=\"text-align: center;\">{$title}</th>";
		$html_result .= "</tr>";
	}

	///fields
	$html_result .= "<tr>";

	if ($group == 'true') 
	{
		if ($active == 'true') $notactive = '';
		else $notactive = 'disabled';
		$html_result .= "<th><input style=\"width:15px;border:0;background-color:#ECECEC;\" type=\"checkbox\" onchange=\"toggle(this);\" onclick=\"toggle(this);\" {$notactive}/></th>";
	}
	
	foreach ($fields as $_key=>$_value) 
	{
		
		if (isset($cur_order_direction[$_key]))
		{
			$sort = $cur_order_direction[$_key]==-1?"DESC":"ASC";
			$order = $_key;
		}
		else $sort = "ASC";
		
		$add_dir = "&order={$_key}&sort={$sort}&qty={$onpage_qty}";

			foreach($filter as $key=>$value)
			{
				$add_dir .= "&select_".$value['name']."=".$value['default_value'];
			}

		if (empty($no_link)) $html_result .= "<th><a href=\"{$base_URL}?current_page={$curr_page}{$add_dir}\">{$_value}</a></th>";
		else $html_result .= "<th>{$_value}</th>";

	}
	
	if (isset($action)) $html_result .= "<th>Action</th>";
	$html_result .= "</tr>";
	
	if(isset($cur_order_direction[$order])) $sort = $cur_order_direction[$order]==-1?"ASC":"DESC";
	else $sort = "ASC";
	
	if (!empty($source))	
	{
		foreach ($source as $_key=>$_value) 
		{
			
			$html_result .= "<tr onmouseover=\"row_over(this)\" onmouseout=\"row_out(this)\">";
			$html_result .= "<input type=\"hidden\" name=\"keyfield[]\" value=\"{$_value->$recordkey}\"/>";
		
			if ($group == 'true') 
			{
				if ($active == 'true') $notactive = '';
				else $notactive = 'disabled';
				$html_result .= "<td><input type=\"checkbox\" class=\"checkbox\" name=\"group[]\" value=\"{$_value->$recordkey}\" {$notactive} {$_value->checked}/></td>";
				
			}
		
			foreach ($fields as $key=>$value) 
			{
				if (empty($_value->$key)) $_value->$key = "&nbsp;";

				

				$html_result .= "<td class=\"text\">{$_value->$key}</td>";
			}
		
			$html_result .= "<td style=\"text-align: center;\">";
			$flag = 0;
			foreach ($action as $key2=>$value2) 
			{
				if (!is_object($value2))
				{
					
						$flag ++;
						if ($flag == 1)
							$html_result .= "<a href=\"{$action_URL}?{$key2}={$_value->$recordkey}&qty={$onpage_qty}\">{$value2}</a>";
						else 
							$html_result .= "&nbsp;|&nbsp;<a href=\"{$action_URL}?{$key2}={$_value->$recordkey}&qty={$onpage_qty}\">{$value2}</a>";
					
				}
				else
				{
					if (!empty($value2->onclick)) $onclick = "onclick = \"$value2->onclick\"";
					else $onclick =	'';
	
					$flag ++;
					
					if ($flag != 1) $html_result .= "&nbsp;|&nbsp;";
					if (!empty($value2->unique_url))
					$html_result .= "<a href='".substitute($value2->unique_url,$source[$_key])."'>{$value2->title}</a>";
					else
					$html_result .= "<a href=\"{$action_URL}?{$key2}={$_value->$recordkey}&qty={$onpage_qty}\" {$onclick}>{$value2->title}</a>";
						
				}
			}
				
			$html_result .= "</td>";
			$html_result .= "</tr>";
		}
 	}
	else
	{
		$html_result .= "<tr onmouseover=\"row_over(this)\" onmouseout=\"row_out(this)\">";
		$html_result .= "<td colspan=\"{$field_count}\" style=\"text-align: center;\"> List is empty";
		$html_result .= "</td>";
		$html_result .= "</tr>";
	}
	
	if (!empty($total_line)) 
	{
		$fields = $field_count - 2;
		$html_result .= "<tr>";
		$html_result .= "<td colspan=\"{$fields}\" style=\"text-align: right;\">";
		$html_result .= "<b>Total Sum: ".number_format($total_line,2,".",'')."</b>";
		$html_result .= "</td>";
		$html_result .= "</tr>";
	}

	$html_result .= "</table>";
	
	

	$add_dir = "&order={$order}&sort={$sort}&qty={$onpage_qty}";

	foreach($filter as $key=>$value)
	{
		$add_dir .= "&select_".$value['name']."=".$value['default_value'];
	}


	$html_result .= "<div id=\"fm-submit\">";
	$html_result .= "<table cellspacing=\"1\" cellpadding=\"0\" border=\"0\" width=\"90%\">";
	$html_result .= "<tr>";
	$html_result .= "<td width=\"50%\" align=\"left\">";
	$PAGING_SHOWN_LINKS = 5;
	
	if($pages > 1)
	{ 	
		$from = $curr_page - floor( $PAGING_SHOWN_LINKS/2);
		if ($from < 1) $from = 1;
		$to = $from + $PAGING_SHOWN_LINKS - 1;
		if ($to > $pages) 
		{
			$to = $pages;
			$from = $to - $PAGING_SHOWN_LINKS + 1;
			if ($from < 1) $from = 1;
		}
	
	
		if($curr_page != 1)
		{
			$html_result .= "<a href=\"{$base_URL}?current_page=1{$add_dir}\">&lt;&lt;</a>&nbsp;&nbsp;";
			$prev = $curr_page - 1;
			$html_result .= "<a href=\"{$base_URL}?current_page={$prev}{$add_dir}\">&lt;</a>&nbsp;&nbsp;";
		}
	
	
		for ($i = $from; $i <= $to; $i++)
		{
				
			if (($i >= $from) && ($i < $curr_page))
			{		
				$html_result .= "<a href=\"{$base_URL}?current_page={$i}{$add_dir}\">{$i}</a>&nbsp;|&nbsp;";
			}
			if ($i == $curr_page)
			{			
				$html_result .= "{$i}";
			}
			if (($i <= $to) && ($i > $curr_page))
			{
				$html_result .= "&nbsp;|&nbsp;<a href=\"{$base_URL}?current_page={$i}{$add_dir}\">{$i}</a>";
			}
					
		}

		if($curr_page != $pages)
		{
			$next = $curr_page + 1;
			$html_result .= "&nbsp;&nbsp;<a href=\"{$base_URL}?current_page={$next}{$add_dir}\">&gt;</a>&nbsp;&nbsp;";
			$html_result .= "<a href=\"{$base_URL}?current_page={$pages}{$add_dir}\">&gt;&gt;</a>";
		}
	}
	$html_result .= "</td>";
    if (!empty($qty_list))
	{
		$html_result .= "<td width=\"50%\" align=\"right\">";
		$html_result .= "&nbsp;&nbsp; Select number of rows on page:&nbsp;&nbsp";
		$params3['name'] = 'qty';
		$params3['options'] = $qty_list;
		$params3['selected'] = $onpage_qty;
		$params3['style'] = "width:50px";
		$params3['id'] = "qty_id";
		$params3['onchange'] = "document.form1.current_page.value=1;document.form1.submit();";
		$html_result .= smarty_function_html_options($params3,&$smarty);
	}
	$html_result .= "</tr>";
	
	
	
	$html_result .= "<tr>";
	$html_result .= "<td colspan=\"2\">";
	$html_result .= "<br><br>";
	 
	foreach ($button as $key => $value)
	{
		switch ($value->value)
		{
			case "add":
			{
				$html_result .= "<button type=\"button\" name=\"{$value->value}\" style=\"width: 120px;\" onclick=\"location.href='{$action_URL}?qty={$onpage_qty}';\" {$value->disabled}>{$value->name}</button>";
				break;
			}
			case "return":
			{
				$html_result .= "<button type=\"button\" name=\"{$value->value}\" style=\"width: 120px;\" onclick=\"location.href='{$back_URL}';\" {$value->disabled}>{$value->name}</button>";
				break;
			}
			case "save": 
			{
				$html_result .= "<button type=\"submit\" name=\"{$value->value}\" style=\"width: 120px;\" onclick=\"func('save');\" {$value->disabled}>{$value->name}</button>";
				//$html_result .= "<input type=\"hidden\" name = \"action\" value=\"{$value->value}\"/>";
				break;
			}
			default:
	   		{
				$html_result .= "<button type=\"button\" value=\"{$value->value}\" style=\"width: 120px;\" onclick=\"func('{$value->value}');\" {$value->disabled}>{$value->name}</button>";
				//$html_result .= "<input type=\"hidden\" name = \"action\" value=\"{$value->value}\"/>";	
			}
		}
	}
	$html_result .= "</td>";
	$html_result .= "</tr>";

	$html_result .= "</table>";

   

	$html_result .= "</div></div>";
	$html_result .= "</div>";
	$html_result .= "</form>";
  	return $html_result;
	
}



?>
