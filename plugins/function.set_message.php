<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */

/**
 * Smarty {set_message} plugin
 *
 
 */
function smarty_function_set_message($params, &$smarty)
{
	
	foreach ($params as $_key=>$_value) {
        switch ($_key) {
            case 'type':
                $type = $_value;
                break;

            case 'text':
                $text = $_value;
                break;
			            
        }
    }
    
	$html_result .= "<div class=\"{$type}\">";
    $html_result .= "&deg;&nbsp;&nbsp;{$text}<br>";
	$html_result .= "</div>";	
	
	return $html_result;
	
}
/* vim: set expandtab: */

?>
