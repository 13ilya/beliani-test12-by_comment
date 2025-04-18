<?php
function smarty_function_mulang_textarea_htmlentities($params, &$smarty)
{
    require_once $smarty->_get_plugin_filepath('shared','escape_special_chars');
	$size = 30;
	$width = '100%';
	$height = '200px';
    foreach($params as $_key => $_val) {
        switch($_key) {
            case 'langs':
            case 'langs_selected':
            case 'table_name':
            case 'field_name':
            case 'size':
            case 'width':
            case 'height':
            case 'values':
            case 'caption':
            case 'display':
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
	foreach($langs as $lang_id=>$lang) {
		if (!isset($langs_selected[$lang_id])) continue;
		$res .= '<tr id="trcheckrow['.$values[$lang_id]->iid.']" '.($values[$lang_id]->unchecked?'style="background:#FFDDDD"':'').'><td width="145"><b>'.$caption.'<br>'.$lang.'</b>';
		if ($values[$lang_id]->unchecked) {
			$res .= '		<input type="button" id="trcheckbtn['.$values[$lang_id]->iid.']" value="checked" onClick="trcheck('.$values[$lang_id]->iid.')"><br/>';
		}
		$res .= '</td>';
		$res .= '<td>';
		$res .= '<textarea id="trnewvalue['.$values[$lang_id]->iid.']" name="'.$field_name.'['.$lang_id.']" style="width:'.$width.';height:'.$height.'">'.htmlentities($values[$lang_id]->value).'</textarea>';
		if (strlen($display)) {
			$res .= '		<br><input type="button" onClick="openWin('."document.getElementById('trnewvalue[".$values[$lang_id]->iid."]'".').value);" value="'.$display.'">';
		}	
		if ($values[$lang_id]->last_on) {
			$res .= '		<br><div id="trdiv['.$values[$lang_id]->iid.']"><a target="_blank" href="change_log.php?table_name=translation&tableid='.$values[$lang_id]->iid.'">Was changed by '.$values[$lang_id]->last_by.' on '.$values[$lang_id]->last_on.'</a></div>';
		}
		$res .= '<br></td>';
		$res .= '</tr>';
    }

    return $res;
}
?>
