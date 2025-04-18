<?php
function smarty_function_mulang_doc($params, &$smarty)
{
    require_once $smarty->_get_plugin_filepath('shared','escape_special_chars');
	$size = 50;
    foreach($params as $_key => $_val) {
        switch($_key) {
            case 'langs':
            case 'langs_selected':
            case 'table_name':
            case 'field_name':
            case 'size':
            case 'values':
            case 'edit_id':
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

	$res = "<table><tr>";
	foreach($langs as $lang_id=>$lang) {
		if (!isset($langs_selected[$lang_id])) continue;
		$res .= '	<td id="trcheckrow['.$values[$lang_id]->iid.']" valign="bottom" '.($values[$lang_id]->unchecked?'style="background:#FFDDDD"':'').'>';
		if ($values[$lang_id]->unchecked) {
			$res .= '		<input type="button" id="trcheckbtn['.$values[$lang_id]->iid.']" value="checked" onClick="trcheck('.$values[$lang_id]->iid.')"><br/>';
		}
		$res .= '		<b>'.$lang.'</b><br>';
		if ($values[$lang_id]->value) {
			$res .= '<a href="doc.php?'.$table_name.'='.$edit_id.'&lang='.$lang_id.'">'.$values[$lang_id]->value.'</a><input type="file" name="'.$field_name.'['.$lang_id.']" value="Change" size="'.$size.'">';
		} else {
			$res .= '<input type="file" name="'.$field_name.'['.$lang_id.']" value="Add" size="'.$size.'">';
		}
		$res .= '<input type="hidden" id="trnewvalue['.$values[$lang_id]->iid.']" value="'.$values[$lang_id]->value.'">';
		if ($values[$lang_id]->last_on) {
				$res .= '		<br><div id="trdiv['.$values[$lang_id]->iid.']"><a target="_blank" href="change_log.php?table_name=translation&tableid='.$values[$lang_id]->iid.'">Was changed by '.$values[$lang_id]->last_by.' on '.$values[$lang_id]->last_on.'</a></div>';
		}
		$res .= '	<br></td>';
	}	
	$res .= "</tr></table>";

    return $res;
}
?>
