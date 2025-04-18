<?php
function smarty_function_mulang_fckedit($params, &$smarty)
{
    require_once $smarty->_get_plugin_filepath('shared','escape_special_chars');
	$size = 30;
	$width = '100%';
	$height = '500';
    foreach($params as $_key => $_val) {
        switch($_key) {
            case 'langs':
            case 'langs_selected':
            case 'table_name':
            case 'field_name':
            case 'width':
            case 'height':
            case 'values':
            case 'caption':
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
	$res = "<table><tr>";
	foreach($langs as $lang_id=>$lang) {
		if (!isset($langs_selected[$lang_id])) continue;
		$res .= '<td id="trcheckrow['.$values[$lang_id]->iid.']" '.($values[$lang_id]->unchecked?'style="background:#FFDDDD"':'').'>';
		if ($values[$lang_id]->unchecked) {
			$res .= '		<input type="button" id="trcheckbtn['.$values[$lang_id]->iid.']" value="checked" onClick="trcheck('.$values[$lang_id]->iid.')"><br/>';
		}
		$res .= '		<b>'.$lang.'</b><br>';
		$res .= '<input type="hidden" name="'.$field_name.'['.$lang_id.']" id="trnewvalue['.$values[$lang_id]->iid.']" value="'./*str_replace("'","\'",*/htmlentities($values[$lang_id]->value)/*)*/.'" style="display:none" />';
		$res .= '<input type="hidden" id="FCKeditor1___Config" value="CustomConfigurationsPath=/fckeditor.config.js" style="display:none" />';
		$res .= '<iframe id="FCKeditor1___Frame" src="/FCKeditor/editor/fckeditor.html?InstanceName='.$field_name.'['.$lang_id.']&amp;Toolbar=Default" width="'.$width.'" height="'.$height.'" frameborder="0" scrolling="no">';
		$res .= '</iframe>';
			if ($values[$lang_id]->last_on) {
				$res .= '		<br><div id="trdiv['.$values[$lang_id]->iid.']"><a target="_blank" href="change_log.php?table_name=translation&tableid='.$values[$lang_id]->iid.'">Was changed by '.$values[$lang_id]->last_by.' on '.$values[$lang_id]->last_on.'</a></div>';
			}
		$res .= '<br></td>';
    }
	$res .= "</tr></table>";
    return $res;
}
?>
