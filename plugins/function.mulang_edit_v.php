<?php
function smarty_function_mulang_edit_v($params, &$smarty)
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
            case 'style':
            case 'limit':
            case 'index':
            case 'disabled':
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

	$res = '<table border="1"><tr><td><table>';
	if ($index) $index="[{$index}]";
	foreach($langs as $lang_id=>$lang) {
		if (!isset($langs_selected[$lang_id])) continue;
		$res .= '	<tr><td id="trcheckrow['.$values[$lang_id]->iid.']" valign="bottom" '.($values[$lang_id]->unchecked?'style="background:#FFDDDD"':'').'>';
		if ($values[$lang_id]->unchecked) {
			$res .= '		<input type="button" id="trcheckbtn['.$values[$lang_id]->iid.']" value="checked" onClick="trcheck('.$values[$lang_id]->iid.')"'.'><br/>';
		}
		$res .= '		<b>'.$lang.'</b><br><input type="text" id="trnewvalue['.$values[$lang_id]->iid.']" name="'.$field_name.$index.'['.$lang_id.']" value="'.$values[$lang_id]->value.'" size="'.$size.'" style="'.$style.'" '.($disabled?' disabled="disabled" ':'').($limit?' onKeyUp="color(this);" ':'').'>';
		if ($limit) {
			$res .= '<input type="Text" id="cnt'.$field_name.'['.$lang_id.']" readonly style="border: 0px;background-color:#eeeeee; text-align:left">	/
	  <input type="text" id="limit'.$field_name.'['.$lang_id.']" value="'.$limit.'" readonly style="border: 0px;background-color:#eeeeee; text-align:left">	 
	  <script>color(document.getElementsByName("'.$field_name.'['.$lang_id.']").item(0))</script>';
		}
		if ($values[$lang_id]->last_on) {
				$res .= '		<br><div id="trdiv['.$values[$lang_id]->iid.']"><a target="_blank" href="change_log.php?table_name=translation&tableid='.$values[$lang_id]->iid.'">Was changed by '.$values[$lang_id]->last_by.' on '.$values[$lang_id]->last_on.'</a></div>';
		}
		$res .= '	<br></td></tr>';
	}	
	$res .= "</td></tr></table></table>";

    return $res;
}
?>
