<?php
function smarty_function_checkPermission($params, &$smarty)
{
    require_once $smarty->_get_plugin_filepath('shared','escape_special_chars');
	$size = 30;
	$width = '100%';
	$height = '500';
    foreach($params as $_key => $_val) {
        switch($_key) {
            case 'filename':
            case 'user':
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

		$res = 'style="pointer-events: none;cursor: default;color:gray;"';
//	$pagename = substr($filename, 0, strpos($filename, '.php')).'.php';
	$pagename = $filename;
	if ($user->admin || (int)$user->pages[$pagename]) {
		$res = '';
	} 

    return $res;
}
?>
