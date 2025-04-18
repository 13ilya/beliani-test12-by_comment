<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */

/**
 * Smarty {html_select_date} plugin
 *
 
 */
function smarty_function_java_select_date($params, &$smarty)
{
	
	foreach ($params as $_key=>$_value) {
        switch ($_key) {
            case 'start_date':
                $start_date = $_value;
                break;

            case 'name':
                $name = $_value;
                break;
			case 'size':
                $size  = $_value;
                break;
			case 'disabled':
                $disabled  = $_value;
                break;
            
        }
    }
	
	if (!empty($start_date))
    {
    	list ($Y, $M, $D) = preg_split('~[\s:-]~', $start_date);
		$t =  gmmktime(0, 0, 0, $M, $D, $Y);
		$date = date('Y-m-d', $t);
		$html_result .= "<INPUT TYPE=\"text\" NAME=\"{$name}\" style = 'width:{$size}px' {$disabled}";
    	$html_result .= " VALUE=\"$date\" SIZE=10>&nbsp;";

    }
    else 
    {
    	$date = date("Y-m-d");
    	$html_result .= "<INPUT TYPE=\"text\" NAME=\"{$name}\" style = 'width:{$size}px' {$disabled}";
    	$html_result .= " VALUE=\"\" SIZE=10>&nbsp;";
    }

	
	if (empty($disabled))
	{
		$html_result .= "<A HREF='#' onClick=\"cal1.select(document.forms[0].{$name},'anchor_{$name}','yyyy-MM-dd','{$date}'); return false;\"";
		$html_result .= " TITLE=\"cal1.select(document.forms[0].{$name},'anchor_{$name}','yyyy-MM-dd','{$date}'); return false;\" NAME='anchor_{$name}' ID='anchor_{$name}' >...</A>" ;
	}
  	return $html_result;
	
}
/* vim: set expandtab: */

?>
