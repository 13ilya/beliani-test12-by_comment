<?php
function smarty_function_employee_pic($params, &$smarty)
{
    require_once $smarty->_get_plugin_filepath('shared','escape_special_chars');
    require_once $smarty->_get_plugin_filepath('function','imageurl');
    foreach($params as $_key => $_val) {
        switch($_key) {
            case 'username':
            case 'title':
            case 'width':
            case 'onclick':
            case 'color':
            case 'tag':
			case 'deftitle':
                $$_key = $_val;
                break;
            default:
                if(!is_array($_val)) {
                    $extra .= ' '.$_key.'="'.smarty_function_escape_special_chars($_val).'"';
                } else {
                    $smarty->trigger_error("html_image: extra attribute '$_key' cannot be an array", E_USER_NOTICE);
                }
                break;
        }
    }
	$res = '';
	if (!strlen($tag)) $tag='a';
	$global_users = $smarty->get_template_vars ('global_users');
	if (!strlen($title)) $title = $global_users[$username];
    
	$global_employee_usernames = $smarty->get_template_vars ('global_employee_usernames');
	$emp_id = $global_employee_usernames[$username];
	$params4image = array('src'=>'employee','picid'=>$emp_id,'x'=>$width);
	$imageurl = smarty_function_imageurl($params4image, $smarty);
    
	if (!strlen($title)) return $deftitle; 
	$res = '<'.$tag.' onmouseover="Tip(\'<img src=&quot;'.$imageurl.'&quot; width=&quot;'.$width.'&quot;>\')" onmouseout="UnTip()"'
		.($onclick?' onClick="'.$onclick.'"':'')
		.($color?' style="color:'.$color.'"':'')
		.'>'.$title.'</'.$tag.'>';
    return $res;
}
?>
