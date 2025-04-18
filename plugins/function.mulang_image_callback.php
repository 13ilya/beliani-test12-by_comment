<?php
function smarty_function_mulang_image_callback($params, &$smarty)
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
            case 'size':
            case 'width':
            case 'height':
            case 'values':
            case 'caption':
            case 'edit_id':
            case 'exts':
			case 'callback':
			case 'resized':
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
    require_once $smarty->_get_plugin_filepath('function','imageurl');
	foreach($langs as $lang_id=>$lang) {
		if (!isset($langs_selected[$lang_id])) continue;
		$res .= '<tr id="trcheckrow['.$values[$lang_id]->iid.']" '.($values[$lang_id]->unchecked?'style="background:#FFDDDD"':'').'><td width="145"><b>'.$caption.'<br>'.$lang.'</b> ver.'.$values[$lang_id]->version;
		if ($values[$lang_id]->unchecked) {
			$res .= '		<input type="button" id="trcheckbtn['.$values[$lang_id]->iid.']" value="checked" onClick="trcheck('.$values[$lang_id]->iid.')"><br/>';
		}
		$res .= '</td>';
		$res .= '<td>';
		$res .= '<input type="hidden" id="trnewvalue['.$values[$lang_id]->iid.']" value="'.$values[$lang_id]->value.'"/>';
		if ($values[$lang_id]->iid) {
			$smarty->assign('lang_id', $lang_id);
			$imgurl = smarty_function_imageurl(array('src'=>$table_name, 'picid'=>$edit_id, 'ext'=>$exts[$lang_id]), $smarty);
			$res .= '<img src="'.$imgurl.'"><br>';
			$res .= '<input type="file" name="'.$field_name.'['.$lang_id.']" value="Change" size="'.$size.'">';
		} else {
			$res .= '<input type="file" name="'.$field_name.'['.$lang_id.']" value="Add" size="'.$size.'">';
		}
		if ($values[$lang_id]->last_on) {
				$res .= '		<br><div id="trdiv['.$values[$lang_id]->iid.']"><a target="_blank" href="change_log.php?table_name=translation&tableid='.$values[$lang_id]->iid.'">Was changed by '.$values[$lang_id]->last_by.' on '.$values[$lang_id]->last_on.'</a></div>';
		}
		
		if (isset($callback))
		{
			$res .= $callback($params, $lang_id);
		}
		
		$res .= '<br></td>';
		$res .= '</tr>';
    }

    return $res;
}
?>
