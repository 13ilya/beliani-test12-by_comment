<?php
/**
 * @author ALEXJJ, alex@lingvo.biz
 * @copyright 2015
 */

// VARS&SETTINGS
// FORMS MANAGEMENT
// CHECK FUNCTIONS
// MISC


// VARS&SETTINGS
function get_settings($name) {
	global $dbr;
	
	$data = $dbr->getOne("SELECT value FROM config WHERE name='$name';");
	if (PEAR::isError($data)){
		//error processing
	}
	else {
		if ($data) return $data;
		else return false;
	}
}

function get_globals($name) {
	global $dbr;
	
	$data = $dbr->getAssoc("SELECT `key`, `value` FROM list_value WHERE `par_name`='$name' ORDER BY `ordering` ASC;");
	if (PEAR::isError($data)){
		//error processing
	}
	else {
		if ($data) return $data;
		else return false;
	}
}

function get_txt_var($id, $what=false, $for=false) {
	global $dbr, $lng, $template_variables;
	
	$data = $dbr->getOne("SELECT value FROM list_value WHERE par_name='$id';");
	
	if (PEAR::isError($data)){
		//error processing
	}
	else {
		if ($data) {
			$txt_var = str_replace($template_variables[0], $template_variables[1], $data);
			if ($what) $txt_var = str_replace($what, $for, $txt_var);
		}
		else $txt_var = false;
	}
	
	return $txt_var;
}


// FORMS MANAGEMENT

function create_input_field($input_type='text', $input_name, $input_value=false, $css_class=false, $input_maxlength=false, $in_table=true, $show_title=true, $required=false, $print=true) { //'*', '**' etc
	global $errors_structured;
	if ($errors_structured[$input_name]) {
		foreach ($errors_structured as $error_item => $error_item_array) {
			if ($input_name == $error_item) {
				foreach ($error_item_array as $warning_type) {
					if (get_txt_var('warning_'.$warning_type)) $warnings_output .= ' <span class="imp">'.get_txt_var('warning_'.$warning_type).'</span>';
					else $warnings_output .= ' <span class="imp">'.$warning_type.'</span>';
				}
				$warnings_output .= '<br />';
			}
		}
	}
	$output_str = '';
	if ($in_table == true) $output_str .= '<tr><td>';
	if ($show_title == true) $output_str .= "<b>".get_txt_var($input_name)."$required</b> \n";
	if ($warnings_output)  $output_str .= "<span class=\"imp\">$warnings_output</span>";
	if ($in_table == true) $output_str .= '</td><td>';
	$output_str .= "<input type=\"$input_type\" name=\"$input_name\"";
	if ($errors_structured[$input_name]) $css_class .= '_error';
	if ($css_class) $output_str .= " class=\"$css_class\"";
	if ($input_name != 'password' && $input_value) $output_str .= " value=\"$input_value\"";
	if ($input_maxlength) $output_str .= " maxlength=\"$input_maxlength\"";
	$output_str .= " />";
	if ($in_table == true) $output_str .= "</td></tr>\n";
	
	if ($print) print($output_str);
	else return($output_str);
}

function create_select_menu($form_field_name, $form_field_data, $css_class, $active_item=false, $empty_field=false, $multiple=false, $in_table=true, $show_title=true, $required=false, $print=true) { //'*', '**' etc
	global $errors_structured;
	if ($errors_structured[$form_field_name]) {
		foreach ($errors_structured as $error_item => $error_item_array) {
			if ($form_field_name == $error_item) {
				foreach ($error_item_array as $warning_type) {
					if (get_txt_var('warning_'.$warning_type)) $warnings_output .= ' <span class="imp">'.get_txt_var('warning_'.$warning_type).'</span>';
					else $warnings_output .= ' <span class="imp">'.$warning_type.'</span>';
				}
				$warnings_output .= '<br />';
			}
		}
	}
	$select_parts = '';
	if ($in_table == true) $select_parts .= '<tr><td>';
	if ($warnings_output)  $select_parts .= $warnings_output;
	if ($show_title == true) $select_parts .= '<b>'.get_txt_var($form_field_name).$required.'</b> ';
	if ($in_table == true) $select_parts .= "</td><td>\n";
	$select_parts .= "<select name=\"$form_field_name";
	if ($multiple) $select_parts .= '[]" multiple';
	else $select_parts .= '"';
	if ($errors_structured[$form_field_name]) $css_class.='_error';
	$select_parts .= " class=\"$css_class\">\n";
	if ($empty_field) $select_parts .= "<option value=\"\"></option>\n";
	foreach ($form_field_data as $form_field_key => $form_field_value) {
		$select_parts .= '<option value="'.$form_field_key.'"';
		if ($active_item) {
			if (!is_array($active_item)) $active_item = array($active_item);
			if (in_array($form_field_key, $active_item)) $select_parts .= ' selected';
		}
		$select_parts .= '>'.$form_field_value."</option>\n";
	}
	$select_parts .= "</select>\n";
	if ($in_table == true) $select_parts .= "</td></tr>\n";
	if ($print) print($select_parts);
	else return($select_parts);
}

function create_select_menu2($form_field_name, $form_field_data, $css_class, $active_item=false, $empty_field=false, $multiple=false, $in_table=true, $show_title=true, $required=false, $print=true) { //'*', '**' etc
	global $errors_structured;
	if ($errors_structured[$form_field_name]) {
		foreach ($errors_structured as $error_item => $error_item_array) {
			if ($form_field_name == $error_item) {
				foreach ($error_item_array as $warning_type) {
					if (get_txt_var('warning_'.$warning_type)) $warnings_output .= ' <span class="imp">'.get_txt_var('warning_'.$warning_type).'</span>';
					else $warnings_output .= ' <span class="imp">'.$warning_type.'</span>';
				}
				$warnings_output .= '<br />';
			}
		}
	}
	$select_parts = '';
	if ($in_table == true) $select_parts .= '<tr><td>';
	if ($warnings_output)  $select_parts .= $warnings_output;
	if ($show_title == true) $select_parts .= '<b>'.get_txt_var($form_field_name).$required.'</b> ';
	if ($in_table == true) $select_parts .= "</td><td>\n";
	$select_parts .= "<select name=\"$form_field_name";
	if ($multiple) $select_parts .= '[]" multiple';
	else $select_parts .= '"';
	if ($errors_structured[$form_field_name]) $css_class[0] .= '_error';
	$select_parts .= " class=\"$css_class[0]\">\n";
	if ($empty_field) $select_parts .= "<option value=\"\"></option>\n";
	$i=0;
	foreach ($form_field_data as $form_field_key => $form_field_value) {
		$select_parts .= '<option value="'.$form_field_key.'"';
		if ($active_item) {
			if (!is_array($active_item)) $active_item = array($active_item);
			if (in_array($form_field_key, $active_item)) $select_parts .= ' selected';
		}
		if (count($css_class)>1) {
			asort($css_class);
			reset($css_class);
			
			foreach ($css_class as $position => $style) {
				if ($i != 0 && $i >= $position) {
					$select_parts .= ' style="'.$style.';"';
					break;
				}
			}
		}
		$select_parts .= '>'.$form_field_value."</option>\n";
		$i++;
	}
	$select_parts .= "</select>\n";
	if ($in_table == true) $select_parts .= "</td></tr>\n";
	if ($print) print($select_parts);
	else return($select_parts);
}

function create_select_menu3($form_field_name, $form_field_data, $css_class, $active_item=false, $empty_field=false, $multiple=false, $in_table=true, $show_title=true, $required=false, $print=true) { //'*', '**' etc
	global $errors_structured;
	if ($errors_structured[$form_field_name]) {
		foreach ($errors_structured as $error_item => $error_item_array) {
			if ($form_field_name == $error_item) {
				foreach ($error_item_array as $warning_type) {
					if (get_txt_var('warning_'.$warning_type)) $warnings_output .= ' <span class="imp">'.get_txt_var('warning_'.$warning_type).'</span>';
					else $warnings_output .= ' <span class="imp">'.$warning_type.'</span>';
				}
				$warnings_output .= '<br />';
			}
		}
	}
	
	if (is_array($css_class)) {
		$style_class = $css_class[0];
	}
	else $style_class = $css_class;
	
	$select_parts = '';
	if ($in_table == true) $select_parts .= '<tr><td>';
	if ($warnings_output)  $select_parts .= $warnings_output;
	if ($show_title == true) $select_parts .= '<b>'.get_txt_var($form_field_name).$required.'</b> ';
	if ($in_table == true) $select_parts .= "</td><td>\n";
	$select_parts .= "<select name=\"$form_field_name";
	if ($multiple) $select_parts .= '[]" multiple';
	else $select_parts .= '"';
	if ($errors_structured[$form_field_name]) $css_class.='_error';
	$select_parts .= " class=\"$style_class\">\n";
	if ($empty_field) $select_parts .= "<option value=\"\"></option>\n";
	foreach ($form_field_data as $form_field_key => $form_field_value) {
		$select_parts .= '<option value="'.$form_field_key.'"';
		if ($active_item) {
			if (!is_array($active_item)) $active_item = array($active_item);
			if (in_array($form_field_key, $active_item)) $select_parts .= ' selected';
		}
		if (is_array($css_class)) {
			foreach ($css_class[1] as $form_field_key_marker => $style_marker) {
				if ($form_field_key_marker && strstr($form_field_key,$form_field_key_marker) != false) $select_parts .= ' style="'.$style_marker.'"';
			}
		}
		$select_parts .= '>'.$form_field_value."</option>\n";
	}
	$select_parts .= "</select>\n";
	if ($in_table == true) $select_parts .= "</td></tr>\n";
	if ($print) print($select_parts);
	else return($select_parts);
}

function get_numeric_array_for_date($start, $end, $type='days', $titles='numbers') {
	global $lng;
	
	if ($start == $end) return(array($start => $start));
	else {
		//if (!$lng) $lng = get_settings('default_lng_backend');
		$lng = 'en';
		$months_titles = get_globals('months_'.$lng);
		
		$output = array();
		
		for($i=$start; $i<=$end; $i++) {
			if ($i<10) $j = '0'.$i;
			else $j = $i;
			if ($titles == 'numbers' || $type != 'months') $output[$j] = $j;
			else {
				$output[$j] = $months_titles[$j];
			}
			unset($j);
		}
		return $output;
	}
}

$form_field_data_date['hours'] = get_numeric_array_for_date(0,23);
$form_field_data_date['minutes'] = get_numeric_array_for_date(0,59);
$form_field_data_date['days'] = get_numeric_array_for_date(1,31);
$form_field_data_date['months'] = get_numeric_array_for_date(1,12);
$form_field_data_date['years'] = get_numeric_array_for_date(2000,date('Y')+1);

function create_select_menu_date($form_field_type, $form_field_name, $css_class, $active_item=false, $empty_field=true, $print=true) {
	global $dbr, $form_field_data_date, $errors_structured;
	if ($errors_structured[$form_field_name]) {
		foreach ($errors_structured as $error_item => $error_item_array) {
			if ($form_field_name == $error_item) {
				foreach ($error_item_array as $warning_type) {
					$warnings_output .= ' '.get_txt_var('warning_'.$warning_type);
				}
				$warnings_output .= '<br />';
			}
		}
	}
	
	$select_parts = '';
	if ($warnings_output) $select_parts .= $warnings_output;
	$select_parts .= "\n<select name=\"$form_field_name\"";
	if ($errors_structured[$form_field_name]) $css_class.='_error';
	$select_parts .= " class=\"$css_class\">\n";
	if ($empty_field) $select_parts .= "<option value=\"\"></option>\n";
	foreach ($form_field_data_date[$form_field_type] as $form_field_key => $form_field_value) {
		$select_parts .= '<option value="'.$form_field_key.'"';
		if ($active_item) {
			if (!is_array($active_item)) $active_item = array($active_item);
			if (in_array($form_field_key, $active_item)) $select_parts .= ' selected';
		}
		$select_parts .= '>'.$form_field_value."</option>\n";
	}
	$select_parts .= "</select>\n";
	if ($print) print $select_parts;
	else return($select_parts);
}

function create_radio_selector($id, $title, $name, $value, $options=false, $in_table=true, $align='center', $print=true) { // 'center', 'left'
	global $dbr, $errors_structured;
	
	if ($errors_structured[$name]) {
		foreach ($errors_structured as $error_item => $error_item_array) {
			if ($name == $error_item) {
				foreach ($error_item_array as $warning_type) {
					$warnings_output .= ' <span class="imp">'.get_txt_var('warning_'.$warning_type).'</span>';
				}
				$warnings_output .= '<br />';
			}
		}
	}
	if ($value == 1) {
		$checked = ' checked';
		$unchecked = '';
	}
	else {
		$unchecked = ' checked';
		$checked = '';
	}
	
	if ($title) $title .= $warnings_output;
	if ($in_table == true) $selectors = "<tr><td>$title</td><td align=\"$align\">";
	else $selectors = "$title ";
	if ($options) $selectors .= $options[0];
	$selectors .= '<input type="radio" name="'.$name.'['.$id.']" value="0"'.$unchecked.' class="no" />';
	if ($options) $selectors .= $options[1];
	$selectors .= '<input type="radio" name="'.$name.'['.$id.']" value="1"'.$checked.' class="yes" />';
	if ($in_table == true) $selectors .= '</td></tr>';
	
	if ($print) print $selectors;
	else return($selectors);
}

function create_radio_selector2($id=false, $name, $title_values, $active_item, $in_table=true, $align='center', $print=true) { // 'center', 'left'
	global $dbr, $errors_structured;
	
	if ($errors_structured[$name]) {
		foreach ($errors_structured as $error_item => $error_item_array) {
			if ($name == $error_item) {
				foreach ($error_item_array as $warning_type) {
					$warnings_output .= ' <span class="imp">'.get_txt_var('warning_'.$warning_type).'</span>';
				}
				$warnings_output .= '<br />';
			}
		}
	}
	
	$output = '';
	
	if ($warnings_output) {
		if ($in_table) $output .= '<tr><td colspan="2">'.$warnings_output."</td></tr>\n";
		else $output .= $warnings_output;
	}
	
	$i=0;
	foreach ($title_values as $value => $title) {
		if ($in_table) $output .= '<tr><td>';
		$output .= '<input type="radio" id="'.$name.$i.'" name="';
		if ($id) $output .= $name.'['.$id.']';
		else $output .= $name;
		$output .= '" value="'.$value.'"';
		if ($value == $active_item) $output .= ' checked';
		$output .= ' /> ';
		if ($in_table) $output .= '</td><td>';
		$output .= '<label for="'.$name.$i.'">'.$title.'</label>';
		if ($in_table) $output .= "</td></tr>\n";
		else $output .= "<br />\n";
		$i++;
	}
	if ($print) print $output;
	else return $output;
}

function create_checkbox($title=false, $name, $value, $checked=false, $in_table=false, $disabled=false) { //'firstcell', 'secondcell'
	if ($checked == true) $checked = ' checked';
	else $checked = '';
	$id = rand(10000,99999);
	$checkbox = "<input type=\"checkbox\" name=\"$name\" id=\"$id\" value=\"$value\"$checked".($disabled ? ' disabled':'')." />";
	if ($title) $checkbox .= " <label for=\"$id\" style=\"cursor: pointer;\">$title</label>\n";
	if ($in_table == 'firstcell') $checkbox_str = '<tr><td>'.$checkbox."</td><td>&nbsp;</td></tr>\n";
	elseif ($in_table == 'secondcell') $checkbox_str = '<tr><td>&nbsp;</td><td>'.$checkbox."</td></tr>\n";
	else $checkbox_str = $checkbox;
	return $checkbox_str;
}

function create_checkbox_group($group_title=false, $name, $title_values, $checked=false, $in_table=false, $divider='<br />', $id_type='i') { //'firstcell', 'secondcell'
	$i=0;
	$output = '';
	$checkboxes = '';
	
	foreach ($title_values as $value => $title) {
		if ($checked && in_array($value, $checked)) $checked_items[$i] = ' checked';
		if ($id_type == 'i') $id = $i;
		else $id = $value;
		$checkboxes .= '<input type="checkbox" name="'.$name.'['.$i.']" id="'.$name.$id.'" value="'.$value.'"'.$checked_items[$i].' /> <label for="'.$name.$i."\" style=\"cursor: pointer;\">$title</label>$divider\n";
		$i++;
	}
	if ($in_table == 'firstcell') $output .= '<tr><td><b>'.$group_title.'</b><br />'.$checkboxes."</td><td>&nbsp;</td></tr>\n";
	elseif ($in_table == 'secondcell') $output .= '<tr><td><b>'.$group_title.'</b></td><td>'.$checkboxes."</td></tr>\n";
	else {
		if ($group_title) $output .= '<b>'.$group_title."</b><br />\n";
		$output .= $checkboxes;
	}
	return $output;
}

function create_submit_button($title=false, $class='smbt', $add_style='') {
	if (!$title) $title = get_txt_var('form_submit_button_title');
	if ($add_style) $add_style = ' style="'.$add_style.'"';
	$output = "<input type=\"submit\" value=\"$title\" class=\"$class\"$add_style />";
	return $output;
}

function parse_errors($type, $array, $add_params=false) {
	global $dbr, $localities;
	$keys = array_keys($array);
	foreach ($keys as $key) {
		if ($type == 'mail') {
			if (!is_email_gerken($array[$key])) $errors['mail'][] = $key;
		}
		elseif ($type == 'phone') {
			if (!contains_phone_number($array[$key])) $errors['phone'][] = $key;
		}
		elseif ($type == 'numeric') {
			if ($add_params && !is_num($array[$key], $add_params[0], $add_params[1])) $errors['numeric'][] = $key;
			elseif (!is_num($array[$key])) $errors['numeric'][] = $key;
		}
		elseif ($type == 'forbidden_characters') {
			//if (preg_match("/[^(w)|(x7F-xFF)|(s)]/",$str[$key])) $errors['forbidden_characters'][$key] = $error_str;
			if (!is_alphanumeric($array[$key])) $errors['forbidden_characters'][] = $key;
		}
		elseif ($type == 'clean_text') {
			if (!is_clean_text($array[$key])) $errors['clean_text'][] = $key;
		}
		elseif ($type == 'valid_domain_part') {
			if (!is_valid_dompart($array[$key])) $errors['valid_domain_part'][] = $key;
		}
		elseif ($type == 'city') {
			if (!in_array($array[$key], array_keys($localities))) $errors['city'][] = $key;
		}
		elseif ($type == 'url') {
			if (!is_url($array[$key])) $errors['url'][] = $key;
		}
	}
	return $errors[$type];
}

function parse_errors_maxlength($str, $limits, $limits_array_key_type='id') { // id | title
	$keys = array_keys($str);
	for ($i=0; $i<count($keys); $i++) {
		$key = $keys[$i];
		if ($limits_array_key_type == 'id') $limits_key = $i;
		else $limits_key = $key;
		if (($limits[$limits_key][0] >0 && mb_strlen($str[$key]) < $limits[$limits_key][0]) || mb_strlen($str[$key]) > $limits[$limits_key][1]) $errors['maxlength'][] = $key;
	}
	return $errors['maxlength'];
}

function deflate_errors_array($errors) {
	foreach($errors as $error_name => $error_value) {
		if (is_array($error_value)) {
			foreach ($error_value as $field_name) {
				$errors_structured[$field_name][] = $error_name;
			}
		}
	}
	return $errors_structured;
}


// CHECK FUNCTIONS

function _is_valid($string, $min_length, $max_length, $regex) {
    // Check if the string is empty
    if (is_string($string)) $str = trim($string);
    else $str = $string;
	
    if(empty($str)) {
        return false;
    }
    // Does the string entirely consist of characters of $type?
    if(!eregi("^$regex$", $string)) {
        return false;
    }
    
    // Check for the optional length specifiers
    $strlen = mb_strlen($string);
    if(($min_length != 0 && $strlen < $min_length) || ($max_length != 0 && $strlen > $max_length)) {
        return false;
    }
    // Passed all tests
    return true;
}
 
/*
 *      bool is_alpha(string string[, int min_length[, int max_length]])
 *      Check if a string consists of alphabetic characters only. Optionally
 *      check if it has a minimum length of min_length characters and/or a
 *      maximum length of max_length characters.
 */
function is_alpha($string, $min_length = 0, $max_length = 0) {
    $ret = _is_valid($string, $min_length, $max_length, "[[:alpha:]]+");
    return($ret);
}

/*
 *      bool is_numeric(string string[, int min_length[, int max_length]])
 *      Check if a string consists of digits only. Optionally
 *      check if it has a minimum length of min_length characters and/or a
 *      maximum length of max_length characters. 
 */
function is_num($string, $min_length = 0, $max_length = 0) {
    if (is_array($string)) $ret = false;
	else $ret = _is_valid($string, $min_length, $max_length, "[[:digit:]]+");
    return($ret);
}

function is_float_num($string, $min_length = 0, $max_length = 0) {
    $ret = _is_valid($string, $min_length, $max_length, "[[:digit:]\.\,]+");
    return($ret);
}
/*
 *      bool is_alphanumeric(string string[, int min_length[, int max_length]])
 *      Check if a string consists of alphanumeric characters only. Optionally
 *      check if it has a minimum length of min_length characters and/or a
 *      maximum length of max_length characters.
 */
function is_alphanumeric($string, $min_length = 0, $max_length = 0) {
    $ret = _is_valid($string, $min_length, $max_length, "[[:alnum:]]+");
    return($ret);
}
function is_email_login($string, $min_length = 0, $max_length = 0) {
    $ret = _is_valid($string, $min_length, $max_length, "[[:alnum:]._-]+");
    return($ret);
}
/*
 *      bool is_email(string string[, int min_length[, int max_length]])
 *      Check if a string is a syntactically valid mail address. Optionally
 *      check if it has a minimum length of min_length characters and/or a
 *      maximum length of max_length characters.
 */
function is_email_gerken($string) {
    $string = trim($string);
    $ret = ereg(
                '^([A-Za-z0-9_]|\\-|\\.)+'.
                '@'.
                '(([A-Za-z0-9_]|\\-)+\\.)+'.
                '[A-Za-z]{2,6}$',
                $string);
    return($ret);
}

/*
 *      bool is_clean_text(string string[, int min_length[, int max_length]])
 *      Check if a string contains only a subset of alphanumerics characters
 *      allowed in the Western alphabets. Useful for validation of names.
 *      Optionally check if it has a minimum length of min_length characters and/or a
 *      maximum length of max_length characters.
[:alpha:] – буква. 
[:digit:] – цифра
[:blank:] – пробельный символ или символ с кодом от 0 до 255.
[:space:] – пробельный символ
[:alnum:] – буква или цифра
[:lower:] – символ нижнего регистра
[:upper:] – символ верхнего регистра
[:punct:] – знак пунктуации
 */
function is_clean_text($string, $min_length = 0, $max_length = 0) {
    //$ret = _is_valid($string, $min_length, $max_length, "[a-zA-Z_[:space:][:punct:]1234567890АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнопрстуфхцчшщъыьэюя\-\,\.\/]+");
    $ret = _is_valid($string, $min_length, $max_length, "[a-zA-Z_[:space:][:punct:]1234567890АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнопрстуфхцчшщъыьэюя".iconv('cp1251','utf-8','АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнопрстуфхцчшщъыьэюя')."\-\,\.\/]+");
    return($ret);
}
function is_valid_dompart($string, $min_length = 0, $max_length = 0) {
    $ret = _is_valid($string, $min_length, $max_length, "[a-zA-Z0-9\-]+");
    return($ret);
}
/*
 *      bool contains_phone_number(string string)
 *      Check if a string contains a phone number (any 10+-digit sequence,
 *      optionally separated by "(", ' ', "-" or "/").
 */
function contains_phone_number($string) {
	if (mb_strpos($string, "'") || mb_strpos($string, '"')) return false;
	else {
		if (ereg("[[:digit:]]{1,10}[\. /\)\(-]*[[:digit:]]{2,10}[\. /\)\(-]*[[:digit:]]{5,10}", $string)) return true;
		elseif (is_num($string, 5,14)) return true;
		else return false;
	}
}

function is_url($string) {
	if (ereg("[[:alnum:]]+\.[[:alpha:]]{2,6}", $string)) return true;
	else return false;
}
/* $Id: String_Validation.inc.php,v 1.1 2000/06/15 18:04:19 tobias Exp $ */

function is_valid($var, $type) {
	$valid = false;
	switch ($type) {
		case "IP":
			if (ereg('^([0-9]{1,3}\.) {3}[0-9]{1,3}$',$var)) {
				$valid = true;
			}
			break;
		case "URL":
			//if (ereg("^[a-zA-Z0-9\-\.]+\.(com|org|net|mil|edu)$",$var)) {
			/*
			if (ereg("^[a-zA-Z0-9\-\.]+\.[a-zA-Z]+$",$var)) {
				$valid = true;
			}
			*/
/*
$urlregex = "^(https?|ftp)\:\/\/([a-z0-9+!*(),;?&=\$_.-]+(\:[a-z0-9+!*(),;?&=\$_.-]+)?@)?[a-z0-9+\$_-]+(\.[a-z0-9+\$_-]+)*(\:[0-9]{2,5})?(\/([a-z0-9+\$_-]\.?)+)*\/?(\?[a-z+&\$_.-][a-z0-9;:@/&%=+\$_.-]*)?(#[a-z_.-][a-z0-9+\$_.-]*)?\$"; 
if (eregi($urlregex, $url)) {echo "good";} else {echo "bad";}  
$url = "https://user:pass@www.somewhere.com:8080/login.php?do=login&style=%23#pagetop"; 
$url = "http://user@www.somewhere.com/#pagetop"; 
$url = "https://somewhere.com/index.html"; 
$url = "ftp://user:****@somewhere.com:21/"; 
$url = "http://somewhere.com/index.html/";  
*/
			$valid = preg_match('|^http(s)?://[a-z0-9-]+(.[a-z0-9-]+)*(:[0-9]+)?(/)?$|i', $var);
			break;
		case "SSN":
			if (ereg("^[0-9]{3}[-	][0-9]{2}[-	][0-9]{4}|[0-9]{9}$",$var)) {
				$valid = true;
			}
			break;
		case "CC":
			if (ereg("^([0-9]{4}[-	]) {3}[0-9]{4}|[0-9]{16}$",$var)) {
				$valid = true;
			}
			break;
		case "ISBN":
			if (ereg("^[0-9]{9}[[0-9]|X|x]$",$var)) {
				$valid = true;
			}
			break;
		case "Date":
			if (ereg("^([0-9][0-2]|[0-9])\/([0-2][0-9]|3[01]|[0-9])\/[0-9]{4}|([0-9][0-2]|[0-9])-([0-2][0-9]|3[01]|[0-9])-[0-9]{4}$",$var)) {
				$valid = true;
			}
			break;
		case "Zip":
			if (ereg("^[0-9]{5}(-[0-9]{4})?$",$var)) {
				$valid = true;
			}
			break;
		case "Phone":
			if (ereg("^((\([0-9]{3}\)	?)|([0-9]{3}-))?[0-9]{3}-[0-9]{4}$",$var)) {
				$valid = true;
			}
			break;
		case "HexColor":
			if (ereg('^#?([a-f]|[A-F]|[0-9]) {3}(([a-f]|[A-F]|[0-9]) {3})?$',$var)) {
				$valid = true;
			}
			break;
		case "User":
			if (ereg("^[a-zA-Z0-9_]{3,16}$",$var)) {
				$valid = true;
			}
			break;
	}
	return $valid;
}


// MISC
function leading_zero($array, $max=10){
	foreach ($array as $key => $value){
		if ($array[$key] < $max) $array[$key] = '0'.(string)$array[$key];
	}
	return $array;
}


function array_value_pad($array, $pad_count, $pad_value){
	foreach ($array as $key => $value){
		$array[$key] = str_pad($value, $pad_count, $pad_value, STR_PAD_LEFT);
	}
	return $array;
}
?>
