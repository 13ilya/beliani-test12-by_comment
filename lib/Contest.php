<?php
/**
 * @author ALEXJJ, alex@lingvo.biz
 */
function process_contest() {
	global $db, $loggedCustomer, $shopCatalogue;

	if ($loggedCustomer->id && isset($_SESSION['contest_form_data']))
	{
		$form_data = $_SESSION['contest_form_data'];

		$sql_str = "INSERT INTO shop_contests_data 
			(id,contest_id,customer_id,gender,name,surname,birthdate,email,comment) 
			VALUES (null, '".$form_data['contest_id']."', '".$loggedCustomer->id."', '".$form_data['gender']."', '".$form_data['name']."', '".$form_data['surname']."', '".$form_data['birthdate']."', '".$form_data['email']."', '".$form_data['comment']."');";
								
		$data_insert = $db->query($sql_str);
		
		$personal = array();

		if (!$loggedCustomer->birthdate)
		{
			$parts = explode('-', $form_data['birthdate']);
			$birthdate = $parts[1] . '-' . $parts[2] . '-' . $parts[0];
			$personal['birthdate'] = $birthdate;
		}
		
		if (!$loggedCustomer->country_shipping)
			$personal['country_shipping'] = $shopCatalogue->_seller->get('defshcountry');
		if (!$loggedCustomer->country_invoice)
			$personal['country_invoice'] = $shopCatalogue->_seller->get('defshcountry');
		
		if (!$loggedCustomer->name_invoice)
			$personal['name_invoice'] = $form_data['surname'];
		if (!$loggedCustomer->name_shipping)
			$personal['name_shipping'] = $form_data['surname'];
			
		if (!$loggedCustomer->firstname_invoice)
			$personal['firstname_invoice'] = $form_data['name'];
		if (!$loggedCustomer->firstname_shipping)
			$personal['firstname_shipping'] = $form_data['name'];

		if (!empty($personal))
			$shopCatalogue->updatePerson($personal, $loggedCustomer->email, '', $loggedCustomer->id, false);
		
		if (!PEAR::isError($data_insert))
		{
			if (isset($form_data['_referrer']) && !empty($form_data['_referrer']))
				$referrer = $form_data['_referrer'];

			unset($_SESSION['contest_form_data']);
			if (isset($referrer))
				header('Location: ' . $referrer);
				die;
		}
	}
}
 
function contest_get_content_contest_id($content_id){
	global $dbr;
	
	$contest_id = $dbr->getOne("SELECT id FROM shop_contests WHERE shop_content_id=$content_id AND inactive=0 AND start_date <= now() AND end_date >= now();");
	
	if (PEAR::isError($contest_id) === false) return $contest_id;
	else return false;
}

function contest_get_contest_data($contest_id, $field='title'){
	global $dbr;
	
	$contest_data = $dbr->getOne("SELECT $field FROM shop_contests WHERE id=$contest_id;");
	
	if (PEAR::isError($contest_data) === false) return $contest_data;
	else return false;
}

function contest_get_message($contest_id){
	global $dbr, $lang;
	
	$contest_data = $dbr->getOne("SELECT message FROM shop_contests WHERE id=$contest_id;");
	
	if (PEAR::isError($contest_data) === false) {
		$contest_data = unserialize($contest_data);
		if ($contest_data[$lang]) $output = $contest_data[$lang];
		else $output = false;
	}
	else $output = false;
	
	return $output;
}	

function contest_get_form($contest_id, $form_data=false, $disable_edit=true){
	global $dbr, $lang, $loggedCustomer, $shopCatalogue;
	
	$contest_data = $dbr->getRow("SELECT * FROM shop_contests WHERE id=".$contest_id.";");
	
	if (PEAR::isError($contest_data) === false) {
		$genders = array(170 => $shopCatalogue->_shop->english[170], 171 => $shopCatalogue->_shop->english[171]);
		
		if ($loggedCustomer->id>0 && !$form_data) {
			$form_data = $dbr->getRow("SELECT * FROM shop_contests_data WHERE contest_id=".$contest_id." AND customer_id=".$loggedCustomer->id.";");
			if (PEAR::isError($form_data) === false && count($form_data)>0)
			{
				$form_data = (array)$form_data;
				$disable_edit = true;
			}
		}
		
		if (isset($_SESSION['contest_form_data']) && !empty($_SESSION['contest_form_data']))
			$session_data = $_SESSION['contest_form_data'];

		if ($disable_edit == false || ($disable_edit == true && $form_data == false)){

			$output .= "<form method=\"POST\" action=\"\" id=\"contest_form\">\n";
			if ($loggedCustomer->id>0) $output .= create_input_field('hidden', 'customer_id', $loggedCustomer->id, '', '', false, false, false, false);
			$output .= create_input_field('hidden', 'contest_id', $contest_id, '', '', false, false, false, false);
			$output .= "<table style=\"width:100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"4\">\n";
			
			if ($contest_data->display_gender) {
				if (!$form_data['gender']) 
					$form_data['gender'] = isset($session_data['gender']) 
						? $session_data['gender'] 
						: ($loggedCustomer->gender_invoice ? $loggedCustomer->gender_invoice : '');
				$output .= '<tr><td><p>'.$shopCatalogue->_shop->english[169].'</p>'.create_select_menu('gender', $genders, 'valid', $form_data['gender'], false, false, false, false, false, false)."</td></tr>\n";
			}
			if ($contest_data->display_name) {
				if (!$form_data['name']) 
					$form_data['name'] = isset($session_data['name']) 
						? $session_data['name']
						: ($loggedCustomer->firstname_invoice ? $loggedCustomer->firstname_invoice : '');
				$output .= '<tr><td><p>'.$shopCatalogue->_shop->english[166].'</p>'.create_input_field('text','name',$form_data['name'],'valid',64, false, false, false, false)."</td></tr>\n";
			}
			if ($contest_data->display_surname) {
				if (!$form_data['surname']) 
					$form_data['surname'] = isset($session_data['surname']) 
						? $session_data['surname'] 
						: ($loggedCustomer->name_invoice ? $loggedCustomer->name_invoice : '');
				$output .= '<tr><td><p>'.$shopCatalogue->_shop->english[167].'</p>'.create_input_field('text','surname',$form_data['surname'],'valid',64, false, false, false, false)."</td></tr>\n";
			}
			if ($contest_data->display_birthdate) {
				if (!$form_data['birthdate']) 
					$form_data['birthdate'] = isset($session_data['birthdate']) 
						? substr($session_data['birthdate'],8,2).'-'.substr($session_data['birthdate'],5,2).'-'.substr($session_data['birthdate'],0,4) 
						: ($loggedCustomer->birthdate ? $loggedCustomer->birthdate : '');
			
				$output .= '<tr><td><p>'.$shopCatalogue->_shop->english[235].'</p>'.create_input_field('text','birthdate',$form_data['birthdate'],'datepicker',10, false, false, false, false)."</td></tr>\n";
			}
			if ($contest_data->display_email) {
				if (!$form_data['email'] && $loggedCustomer->email) $form_data['email'] = $loggedCustomer->email;
				$output .= '<tr><td><p>'.$shopCatalogue->_shop->english[49].'</p>'.create_input_field('text','email',$form_data['email'],'valid',255, false, false, false, false)."</td></tr>\n";
			}
			if ($contest_data->display_comment) {
				if (!$form_data['comment']) 
					$form_data['comment'] = isset($session_data['comment']) ? $session_data['comment'] : '';
				$output .= '<tr><td><p>'.$shopCatalogue->_shop->english[117].'</p><textarea cols="60" rows="8" name="comment">'.$form_data['comment']."</textarea></td></tr>\n";
			}
			
			//if (!$loggedCustomer->id) 
				//$output .= '<tr><td>&nbsp;</td><td><input type="button" value="'.$shopCatalogue->_shop->english[238]."\" onclick=\"window.location.href='/checkout_register.php'\" class=\"right button red valid\"></td></tr>\n";
			//else 
				$output .= '<tr><td><input type="button" value="'.$shopCatalogue->_shop->english[238]."\" id=\"contest_form_submit\" class=\"right button valid\"></td></tr>\n";
			
			$output .= "</table>\n</form>\n";//create_submit_button()
		}
		else {
			$contest_data->message = unserialize($contest_data->message);
			
			$output .= '<div style="padding: 10px 4px;font-size: 12pt;text-align: left;font-weight: bold;">'.$contest_data->message[$lang]."</div>\n";
			$output .= "<table border=\"0\" cellspacing=\"2\" cellpadding=\"4\">\n";
			if ($contest_data->display_gender) {
				$output .= '<tr><td><strong>'.$shopCatalogue->_shop->english[169].'</strong></td><td>'.$genders[$form_data['gender']]."</td></tr>\n";
			}
			if ($contest_data->display_name) {
				$output .= '<tr><td><strong>'.$shopCatalogue->_shop->english[166].'</strong></td><td>'.$form_data['name']."</td></tr>\n";
			}
			if ($contest_data->display_surname) {
				$output .= '<tr><td><strong>'.$shopCatalogue->_shop->english[167].'</strong></td><td>'.$form_data['surname']."</td></tr>\n";
			}
			if ($contest_data->display_birthdate) {
				if ($form_data['birthdate'])
					$birthdate = substr($form_data['birthdate'],8,2).'-'.substr($form_data['birthdate'],5,2).'-'.substr($form_data['birthdate'],0,4);
				else
					$birthdate = '';
				$output .= '<tr><td><strong>'.$shopCatalogue->_shop->english[235].'</strong></td><td>'.$birthdate."</td></tr>\n";
			}
			if ($contest_data->display_email) {
				$output .= '<tr><td><strong>'.$shopCatalogue->_shop->english[49].'</strong></td><td>'.$form_data['email']."</td></tr>\n";
			}
			if ($contest_data->display_comment) $output .= '<tr><td><strong>'.$shopCatalogue->_shop->english[117].'</strong></td><td>'.str_replace(array('\r\n'),' ', $form_data['comment'])."</td></tr>\n";
			
			$output .= "</table>\n";//create_submit_button()
		}
	}
	else {
		// error processing
		$output = false;
	}
	return $output;
}

function contest_get_sa($contest_id){
    global $dbr, $shopCatalogue;
	
    $sas = $dbr->getCol("SELECT saved_id FROM shop_contest_sa WHERE shop_contest_id=$contest_id;");
    foreach($sas as $key => $sa_id) {
        $sas[$key] = $shopCatalogue->getOffer($sa_id);
    }
	
	return $sas;
}	
