<?php
function smarty_function_skype_btn($params, &$smarty)
{
    require_once $smarty->_get_plugin_filepath('shared','escape_special_chars');
    foreach($params as $_key => $_val) {
        switch($_key) {
            case 'username':
            case 'img':
            case 'width':
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
	$res = '<a href="skype:'.$username.'"><img src="'.$img.'" width="'.$width.'" border="0"></a>';

    return $res;
}
?>
