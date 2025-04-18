<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */


function smarty_function_PrintItemList($params, &$smarty)
{
	require_once $smarty->_get_plugin_filepath('function','html_options');

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
           
		   	case 'class':
                $class = $_value;
                break;
			
			case 'title':
                $title = $_value;
                break;
			case 'total_line':
                $total_line = $_value;
                break;

			case 'header_line':
                $header_line = $_value;
                break;
			case 'from_date':
                $from_date = $_value;
                break;
			case 'to_date':
                $to_date = $_value;
                break;

        }
    }
	

   $html_result = "
    
    <!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">

<html>
<head>
	<meta http-equiv=\"content-type\" content=\"text/html; charset=windows-1251\">
	
</head>
<body onLoad=\"window.print();\">

<style>




table.main {
	width: 75%;
	margin-top: 30px;
}
table.main td.icons {
	width: 10%;
	vertical-align: top;
	padding-left: 10px;
	padding-right: 10px;
	text-align: center;
}
table.main td.item {
	width: 40%;
	vertical-align: top;
}
table.main h3 {
	margin: 0px 0px 0px 0px;
	padding: 0px 0px 0px 0px;
}
table.main ul {
	margin-left: 30px;
	list-style-type: none;
}
div.statistics {
	border: 1px solid #72BC0D;
	width: 450px;
	vertical-align: middle;
	padding: 20px;
}
div.statistics td {
	text-align: center;
}



form {
	margin: 0;
	padding: 0;
}

table.table_form {
	width: 80%;
}

table.table_form td  {
	padding: 3px;
}

table.table_form td.text {
	width: 60%;
	text-align: left;
}

table.table_form input, textarea {
	width: 100%;
}

table.table_form select {
	padding: 1px;
	color:#838383;
	width: 100%;
}

table.table_form button,
table.table_form input.btnStn {
	width: 150px;
	margin-top: 15px;
}

table.adminTable {
	background-color: White;
	color: Black;
	margin: 15px 10px 15px 0px; 
	font-size: 12px;
}
table.adminTable th {
	font-weight: bold;
	padding: 8px 15px 8px 15px;
	background-color: #ECECEC;
	color: #424242;
	white-space: nowrap;
	border-top: 1px solid White;
	border-left: 1px solid White;
	border-right: 1px solid White;
}

table.adminTable th a {
	color: #2F4F4F;
}
table.adminTable td {
	text-align: center;
	padding: 3px 3px 4px 3px;
	vertical-align: top;
	border-bottom: 1px solid #DDDDDD;
	border-top: 1px solid White;
	border-left: 1px solid White;
	border-right: 1px solid White;	
}
table.adminTable input.file {
	width:270px;
}
table.adminTable td.bord {
	text-align: center;
	padding: 3px 3px 4px 7px;
	vertical-align: top;
	border-bottom: 1px solid #DDDDDD;
	border-top: 1px solid #DDDDDD;
	border-left: 1px solid White;
	border-right: 1px solid White;	
}
table.adminTable a, table.adminTable a:link, table.adminTable a:visited {
	text-decoration: underline;	
}
table.adminTable a:hover {
	text-decoration: none;
}
table.adminTable td.text {
	text-align: left;
	padding-left: 7px;
}

table.adminTable input.qty {
	width: 40px;
	padding: 1px 2px 1px 2px;
	margin-top: 0px;
}

table.adminTable select {
	width: 85px;
	margin: 0px;
	font-size: 10px;
}

table.adminTable a.button, table.adminTable a.button:hover, table.adminTable a.button:visited {
	text-decoration: none;
	padding: 1px 6px 1px 6px;
	margin: 3px 3px 3px 3px;
	font-family: Verdana, Geneva, Arial, Helvetica, sans-serif;
	/*font-size: 10px;*/
	background-color: #F7F7F7;
	color: #424242;
	border: 1px solid #777777;
	width: auto;	
}

table.adminTable a.admin_th:link, table.adminTable a.admin_th:visited{
		text-decoration: none;
}
table.adminTable a.admin_th:hover {
	text-decoration: underline;
}

table.adminTable td.title {
	text-align: right;
	padding-right: 7px;
	font-weight: bold;
	color: #777;
}
table.adminTable button {
	padding: 0px 10px 0px 10px;
	margin: 0;
	vertical-align: middle;
}
table.adminTable input.qty {
	width: 50px;
}

.row {
	background-color: #FFFFFF;
} 
.row_over {
	background-color: #F9F9F9;
	cursor: pointer;
}



table.data {
	float:none;
}
table.data td {
	text-align: right;

}

table.data select {
	padding: 1px;
	width: 90px;
}


h1 {
	font:120% Trebuchet MS;
	color:#222;
	margin:15px 0;
	line-height:25px;
}





</style>




	<h1>{$title}</h1>
   ";


 	if (!empty($from_date))	$html_result .= "<b>From:</b> {$from_date}<br>";
	if (!empty($to_date)) $html_result .= "<b>To:</b> {$to_date}";
    

    $field_count = count($fields)+2;
  	
	$html_result .= "<table cellspacing=\"1\" cellpadding=\"0\" border=\"0\"  class=\"{$class}\" width=\"100%\">";
	
	///fields
	$html_result .= "<tr>";

	foreach ($fields as $_key=>$_value) 
	{
		$html_result .= "<th>{$_value}</th>";
	}
	
	$html_result .= "</tr>";
	
	if (!empty($source))	
	{
		foreach ($source as $_key=>$_value) 
		{
			
			$html_result .= "<tr >";
			
			foreach ($fields as $key=>$value) 
			{
				if (empty($_value->$key)) $_value->$key = "&nbsp;";
				$html_result .= "<td class=\"text\">{$_value->$key}</td>";
			}
		
			$html_result .= "</tr>";
		}
 	}
	else
	{
		$html_result .= "<tr >";
		$html_result .= "<td colspan=\"{$field_count}\" style=\"text-align: center;\"> List is empty";
		$html_result .= "</td>";
		$html_result .= "</tr>";
	}
	
	if (!empty($total_line)) 
	{
		
		$html_result .= "<tr>";
		$html_result .= "<td colspan=\"{$field_count}\" style=\"text-align: right;\">";
		$html_result .= "<b>Total Sum: $total_line</b>";
		$html_result .= "</td>";
		$html_result .= "</tr>";
	}

	$html_result .= "</table>";
		
$html_result .= "</body>";
  	return $html_result;
	
}



?>
