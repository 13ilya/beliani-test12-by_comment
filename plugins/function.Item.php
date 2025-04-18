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
 * Purpose:  
 */
function smarty_function_Item($params, &$smarty)
{
	require_once $smarty->_get_plugin_filepath('function','html_options');
	require_once $smarty->_get_plugin_filepath('function','include_js_calendar');
	require_once $smarty->_get_plugin_filepath('function','java_select_date');
	require_once $smarty->_get_plugin_filepath('function','set_message');

		foreach ($params as $_key=>$_value) {
        switch ($_key) 
        {
           
            case 'source':
                $source = $_value;
                break;

            case 'action':
                $action = $_value;
                break;
                
            case 'BACK_URL':
                $back_URL = $_value;
                break;

			case 'size':
                $size = $_value;
                break;

			case 'no_id':
                $no_id = $_value;
                break;
            case 'action_URL':
                $action_URL = $_value;
                break;
			
			case 'messages':
                $messages = $_value;
                break;

			case 'button':
                $button = $_value;
                break;
	      }

    }
	
	foreach ($messages as $key=>$value)
	{
		$param0['type']=$value->type;
		$param0['text']=$value->text;
		
		$html_result .= smarty_function_set_message($param0, &$smarty);
	}
	
	$html_result .= smarty_function_include_js_calendar($params, &$smarty);
	$html_result .= "<div align=\"left\">";
  	$html_result .= "<table cellspacing=\"1\" cellpadding=\"0\" border=\"0\" class=\"form_table\">";
	$html_result .= "<form enctype=\"multipart/form-data\" action='' method='POST' name='form' id='fm-form'>";
	$html_result .= "<script type=\"text/javascript\" src=\"/js/FCKeditor/fckeditor.js\"></script>";
	$html_result .= "<script language=\"JavaScript\" type=\"text/javascript\">";

   	$html_result .= "function func(x){";
    $html_result .= "document.form.action.value = x;";
	$html_result .= "document.form.submit();}";

	$html_result .= "</script>";
		
	$list = array();
	$html_result .= "<input type=\"hidden\" name='action' value=''>";

	$read = 0;
	//	print_r($source);
    foreach($source as $key=>$value)
    {
		$html_result .= "<tr>";
   		//$html_result .= "<div class=\"fm-req\">";
		
		$html_result .= "<td valign='top'><label for='name'>{$source[$key]->Comment}&nbsp;&nbsp;</label></td>";
		$html_result .= "<td>";
		
		$name = $source[$key]->Field;
		
		$mvalue = str_replace("\\\\", "\\",$source[$key]->Value);	

		if (strpos($source[$key]->Type,'int')== 'true') $source[$key]->Type = 'int';
		if (strpos($source[$key]->Type,'varchar')== 'true') $source[$key]->Type = 'varchar';
		if (strpos($source[$key]->Type,'char')== 'true') $source[$key]->Type = 'char';
		
		if (strpos($source[$key]->Type,'enum')== 'true')
		{
			$source[$key]->Type = 'enum';
			$list = $source[$key]->Source;
		}

		if ($source[$key]->Read_only == 'read_only') {$readonly = 'readonly'; $read++;}
		else $readonly = '';
		
		switch ($source[$key]->Type) 
		{
			case 'int': 
				
				if ($source[$key]->Key == 'PRI') 
				{
					if (!empty($mvalue)) $html_result .= "{$mvalue}<input type='hidden' name = '{$name}' value='{$mvalue}'/>";
					else $html_result .= "{$no_id}<input type='hidden' name = '{$name}' value=''/>";
				}
				else $html_result .= "<input type='Text' name = '{$name}' value='{$mvalue}' style='width:{$size}px;' {$readonly}/>"; 
				
			break;

			case 'varchar': 
				
				$html_result .= "<input type='Text' name = '{$name}' value='{$mvalue}' style='width:{$size}px;' {$readonly}/>"; 
				
			break;

			case 'checkbox':
 
				if ($mvalue) $checked = 'checked';
				else $checked = '';
				$html_result .= "<input type=\"checkbox\" name = \"{$name}\" value =\"checked\" {$checked} {$readonly}/>"; 
				
			break;

			case 'char': 
				
				$html_result .= "<input type='Text' name = '{$name}' value='{$mvalue}' style='width:{$size}px;' {$readonly}/>"; 
				
			break;

			case 'file': 
				
				$html_result .= "<input type='file' name = '{$name}' value='{$mvalue}' style='width:{$size}px;' {$readonly}/>"; 
				
			break;

			case 'textarea': 
				
				$html_result .= "<textarea type='Text' name = '{$name}' style='width:{$size}px;' {$readonly}>{$mvalue}</textarea>"; 
				
			break;


			case 'FCKeditor': 
				$dir = "http://".$_SERVER['HTTP_HOST']."/js/FCKeditor/";
				
				$mvalue = str_replace("'","\'",$mvalue);
				$html_result .= "<script type=\"text/javascript\" language=\"JavaScript\">";
				$html_result .= "var oFCKeditor = new FCKeditor('{$name}');";
				$html_result .= "oFCKeditor.BasePath = '{$dir}';";
				$html_result .= "oFCKeditor.Width = 550;";
				$html_result .= "oFCKeditor.Height = 350;";
				$html_result .= "oFCKeditor.Value = '{$mvalue}';";
				$html_result .= "oFCKeditor.EnableSafari = true ;";
				$html_result .= "oFCKeditor.Create();";
				$html_result .= "</script>";
			break;

			case 'date': 

				$params1['name'] = $source[$key]->Field;
				$params1['start_date']= $source[$key]->Value;
				$params1['size']= $size;
				$params1['disabled']= $readonly;
				$html_result .= smarty_function_java_select_date($params1,&$smarty); 
				
			break;

			case 'datetime': 
				
				$params3['name'] = $source[$key]->Field;
				$params3['start_date']= $source[$key]->Value;
				$params3['size']= $size;
				$params3['disabled']= $readonly;
				$html_result .= smarty_function_java_select_date($params3,&$smarty); 
				
			break;
			
			case 'enum':
				unset($params2); 
				$params2['name'] = $source[$key]->Field;
				$params2['options'] = $list;
				$params2['selected'] = $source[$key]->Value;
				$params2['style'] = "width:{$size}px";
				if (!empty($readonly)) $params2['disabled']= '';
				if (!empty($source[$key]->OnClick)) $params2['onChange']= $source[$key]->OnClick;
				$html_result .= smarty_function_html_options($params2,&$smarty);
						
			break;
			default: 
		}
		
	    $html_result .= "</td>";
	    //$html_result .= "</div>";
		$html_result .= "</tr>";
    }

	
	$html_result .= "</table>";
	$html_result .= "<div style=\"float:center\" id=\"fm-submit\">";
	$html_result .= "<button type=\"button\" value=\"return\" style=\"width: 120px;\" onclick=\"location.href='{$back_URL}'; \">Return</button>";
	if (count($source) != $read and empty($button))	$html_result .= "&nbsp;&nbsp;<button type=\"submit\" value=\"update\" style=\"width: 120px;\" onclick=\"func('update'); return false;\">Save</button>";

	foreach ($button as $key => $value)
	{

		$html_result .= "<button type=\"button\" value=\"{$value->value}\" style=\"width: 120px;\" onclick=\"func('{$value->value}');\" {$value->disabled}>{$value->name}</button>";
	}


	$html_result .= "</div>";
	$html_result .= "</form>";
	$html_result .= "</div>";
	
	return $html_result;
	
}



?>
