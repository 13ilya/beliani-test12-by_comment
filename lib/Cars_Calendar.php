<?php
/**
 * @author ALEXJJ, alex@lingvo.biz
 */


//CALENDAR FUNCTIONS

function get_calendar($date=false, $cars_ids=false, $events=false, $host=false, $display_navlinks=true){
	global $dbr, $lng;
	
	//if (!$lng) $lng = get_settings('default_lng_backend');
	$lng = 'en';
	
	$months = get_globals('months_'.$lng);
	$days_of_week = get_globals('weekdays_abbr_'.$lng);
	
//	if ($date){
//		if ($date['year']) $year = $date['year'];
//		if ($date['month']) $month = $date['month'];
//		if ($date['day']) $day = $date['day'];
//		if ($date['hour']) $hour = $date['hour'];
//		if ($date['minute']) $minute = $date['minute'];
//	}
	if ($date){
		if ($date['year']) $year = date('Y', $date);
		if ($date['month']) $month = date('m', $date);
		if ($date['day']) $day = date('d', $date);
		if ($date['hour']) $hour = date('H', $date);
		if ($date['minute']) $minute = date('m', $date);
	}
	
	/*
	if ($cars_ids){
		$cars = $dbr->getAssoc("SELECT id, name FROM cars WHERE id IN(".implode(',',$cars_ids).") ORDER BY name ASC;");
	}
	*/
	
	// Get today, reference day, first day and last day info
	if ($year && $month) $referenceDay = getdate(mktime(0,0,0,$month,1,$year));
	else $referenceDay = getdate();
    
	$firstDay = getdate(mktime(0,0,0,$referenceDay['mon'],1,$referenceDay['year']));
	$lastDay  = getdate(mktime(0,0,0,$referenceDay['mon']+1,0,$referenceDay['year']));
	$today = getdate();
	
	// Create a table with the necessary header informations
	$output = "<table cellspacing=\"2\" cellpadding=\"0\" border=\"0\" class=\"calendar\">\n";
	$output .= '<tr>';
	if ($display_navlinks){
		//if ($referenceDay['year'] == date('Y') && $referenceDay['mon'] >= (int)date('n')) $nav_month_next = false;
		//else {
			/*
			if ($referenceDay['year'] == date('Y')) {
				$nav_month_next = $referenceDay['mon']+1;
				$nav_year_next = $referenceDay['year'];
			}
			else {
				*/
				if ($referenceDay['mon'] == 12){
					$nav_month_next = 1;
					$nav_year_next = $referenceDay['year']+1;
				}
				else {
					$nav_month_next = $referenceDay['mon']+1;
					$nav_year_next = $referenceDay['year'];
				}
			//}
		//}
		
		if ($referenceDay['mon'] == 1){
			$nav_month_previous = 12;
			$nav_year_previous = $referenceDay['year']-1;
		}
		else {
			$nav_month_previous = $referenceDay['mon']-1;
			$nav_year_previous = $referenceDay['year'];
		}
		
		//if (!info_exists($nav_year_previous.'-'.($nav_month_previous<10 ? '0'.$nav_month_previous:$nav_month_previous), 'site_content', true)) unset($nav_month_previous);
		
		//if ($nav_month_previous) $output .= '<th class="nav" title="Previous month" onclick="$(\'#calendar\').load(\'/calendar.php?year='.$nav_year_previous.'&month='.$nav_month_previous.'\');">&lt;&lt;&lt;</th>';
		if ($nav_month_previous) $output .= '<th class="nav" title="Previous month" onclick="$(location).attr(\'href\',\'/calendar.php?year='.$nav_year_previous.'&month='.$nav_month_previous.'\');">&lt;&lt;&lt;</th>';
		else $output .= '<th class="title">&nbsp;</th>';
		
		if ($referenceDay['mon']<10) $referenceDay['mon'] = '0'.$referenceDay['mon'];
		$output .= '<th colspan="5" class="title">'.$months[$referenceDay['mon']].' '.$referenceDay['year']."</th>";
		
		//if ($nav_month_next) $output .= '<th class="nav" title="Next month" onclick="$(\'#calendar\').load(\'/calendar.php?year='.$nav_year_next.'&month='.$nav_month_next.'\');">&gt;&gt;&gt;</th>';
		if ($nav_month_next) $output .= '<th class="nav" title="Next month" onclick="$(location).attr(\'href\',\'/calendar.php?year='.$nav_year_next.'&month='.$nav_month_next.'\');">&gt;&gt;&gt;</th>';
		else $output .= '<th class="title">&nbsp;</th>';
	}
	else {
		if ($referenceDay['mon']<10) $referenceDay['mon'] = '0'.$referenceDay['mon'];
		$output .= '<th colspan="7" class="title">'.$months[$referenceDay['mon']].' '.$referenceDay['year']."</th>";
	}
	
	$output .= "</tr>\n<tr>";
	for ($i=1; $i<=7; $i++){
		if ($i>5) $class = ' class="weekend"';
		else $class = '';
		$output .= "<th$class>".$days_of_week[$i].'</th>';
	}
	$output .= "</tr>\n";
	
	// Display the first calendar row with correct positioning
	$output .= '<tr>';
	if ($firstDay['wday'] == 0) $firstDay['wday'] = 7;
	for ($i=1; $i<$firstDay['wday']; $i++){
		$output .= '<td>&nbsp;</td>';
	}
	$actday = 0;
	
	if (strlen($month) == 1) $month = '0'.$month;
	
	for ($i=$firstDay['wday']; $i<=7; $i++){
		$actday++;
		if ($actday == $today['mday'] && $today['mon'] == $month && $today['year'] == $year) $class = ' class="actday" title="Today"';
		elseif ($i>5) $class = ' class="weekend2"';
		else $class = '';
		if ($actday<10) $chkday = '0'.$actday;
		else $chkday = $actday;
		$date = $year.'-'.$month.'-'.$chkday;
		$current_date = array('year' => $referenceDay['year'], 'month' => $referenceDay['mon'], 'day' => $chkday);
		
		$output .= "<td$class>";
		// commented line is valid only for the current month
		//if (mktime(0,0,0, $month, $actday, $year)< time() && info_exists($date)) $output .= "<a href=\"$host?year=$year&month=$month&day=$chkday\">$actday</a>";
		
		//$output .= '<div class="cal_day">'.$actday.'</div>';
		$output .= '<div class="cal_day">'.calendar_event_creation_button(false, $current_date)."</div>\n";		
		
		$cars_wwos = calendar_get_wwos($cars_ids, $current_date, false);
		$cars_routes = calendar_get_routes($cars_ids, $current_date, false);
		

		if ($cars_wwos) $output .= $cars_wwos; //etc
		if ($cars_routes) $output .= $cars_routes; //etc
		//if (!$cars_wwos && !$cars_routes) {
			$cars_events = calendar_get_events($cars_ids, $events, $current_date, false);
			if ($cars_events) $output .= $cars_events;
			//else $output .= calendar_event_creation_button($current_date);
		//}
		if ($cars_ids) $output .= calendar_available_cars($cars_ids, $current_date, 'list', '<br />');
		$output .= "</td>\n";
	}
	$output .= "</tr>\n";
	
	//Get how many complete weeks are in the actual month
	$fullWeeks = floor(($lastDay['mday']-$actday)/7);
	
	for ($i=0; $i<$fullWeeks; $i++){
		$output .= '<tr>';
		for ($j=0;$j<7;$j++){
			$actday++;
			if ($actday == $today['mday'] && $today['mon'] == $month && $today['year'] == $year) $class = ' class="actday" title="Today"';
			elseif ($j>4) $class = ' class="weekend2"';
			else $class = '';
			if ($actday<10) $chkday = '0'.$actday;
			else $chkday = $actday;
			$date = $year.'-'.$month.'-'.$chkday;
			$current_date = array('year' => $referenceDay['year'], 'month' => $referenceDay['mon'], 'day' => $chkday);
			
			$output .= "<td$class>";
			// commented line is valid only for the current month
			//if (mktime(0,0,0, $month, $actday, $year)< time() && info_exists($date)) $output .= "<a href=\"$host?year=$year&month=$month&day=$chkday\">$actday</a>";
			
			//$output .= '<div class="cal_day">'.$actday.'</div>';
			$output .= '<div class="cal_day">'.calendar_event_creation_button(false, $current_date)."</div>\n";
			
			$cars_wwos = calendar_get_wwos($cars_ids, $current_date, false);
			$cars_routes = calendar_get_routes($cars_ids, $current_date, false);
			
			if ($cars_wwos) $output .= $cars_wwos; //etc
			if ($cars_routes) $output .= $cars_routes; //etc
			//if (!$cars_wwos && !$cars_routes) {
				$cars_events = calendar_get_events($cars_ids, $events, $current_date, false);
				if ($cars_events) $output .= $cars_events;
				//else $output .= calendar_event_creation_button($current_date);
			//}
			if ($cars_ids) $output .= calendar_available_cars($cars_ids, $current_date, 'list', '<br />');
			$output .= "</td>\n";
		}
		$output .= "</tr>\n";
	}
	
	//Now display the rest of the month
	if ($actday < $lastDay['mday']){
		$output .= '<tr>';
		for ($i=0; $i<7;$i++){
			$actday++;
			if ($actday == $today['mday'] && $today['mon'] == $month && $today['year'] == $year) $class = ' class="actday" title="Today"';
			elseif ($i>4) $class = ' class="weekend2"';
			else $class = '';
			
			if ($actday <= $lastDay['mday']) {
				if ($actday<10) $chkday = '0'.$actday;
				else $chkday = $actday;
				$date = $year.'-'.$month.'-'.$chkday;
				$current_date = array('year' => $referenceDay['year'], 'month' => $referenceDay['mon'], 'day' => $chkday);
				
				$output .= "<td$class>";
				// commented line is valid only for the current month
				//if (mktime(0,0,0, $month, $actday, $year)< time() && info_exists($date)) $output .= "<a href=\"$host?year=$year&month=$month&day=$chkday\">$actday</a>";
				
				//$output .= '<div class="cal_day">'.$actday.'</div>';
				$output .= '<div class="cal_day">'.calendar_event_creation_button(false, $current_date)."</div>\n";
				
				$cars_wwos = calendar_get_wwos($cars_ids, $current_date, false);
				$cars_routes = calendar_get_routes($cars_ids, $current_date, false);
				
				if ($cars_wwos) $output .= $cars_wwos; //etc
				if ($cars_routes) $output .= $cars_routes; //etc
				//if (!$cars_wwos && !$cars_routes) {
					$cars_events = calendar_get_events($cars_ids, $events, $current_date, false);
					if ($cars_events) $output .= $cars_events;
					//else $output .= calendar_event_creation_button($current_date);
				//}
				
				if ($cars_ids) $output .= calendar_available_cars($cars_ids, $current_date, 'list', '<br />');
				$output .= "</td>\n";
			}
			else $output .= "<td$class>&nbsp;</td>\n";
		}
		$output .= "</tr>\n";
	}
	
	$output .= "</table>\n<div id=\"event_form\"></div>\n";
	
	return $output;
}

function calendar_available_cars($cars_ids=false, $date, $type='list', $divider='<br />'){// obsolete, replace $sql_str for the new version
	global $dbr;
	
	$sql_str = "SELECT id, name FROM cars WHERE id NOT IN(
	SELECT car_id FROM ww_order WHERE planned_arrival_date='".$date['year'].'-'.$date['month'].'-'.$date['day'].' '.$date['hour'].':'.$date['minute'].":00' 
	UNION 
	SELECT car_id FROM route WHERE start_date='".$date['year'].'-'.$date['month'].'-'.$date['day'].' '.$date['hour'].':'.$date['minute'].":00' 
	UNION 
	SELECT car_id FROM car_event WHERE (('".$date['year'].'-'.$date['month'].'-'.$date['day'].' '.$date['hour'].':'.$date['minute'].":00'>=start_date AND '".$date['year'].'-'.$date['month'].'-'.$date['day'].' '.$date['hour'].':'.$date['minute'].":00'<=end_date) OR ('".$date['year'].'-'.$date['month'].'-'.$date['day'].' '.$date['hour'].':'.$date['minute'].":00'>=start_date AND end_date='0000-00-00 00:00:00'))
	) ORDER BY name ASC;";
	
	$cars = $dbr->getAssoc($sql_str);
	$output = '';
	foreach ($cars as $car_id => $car_title){
		if (!$cars_ids || in_array($car_id, $cars_ids)){
			if ($type == 'list') $output .= '<span class="link" onclick="$(\'#event_form\').load(\'/calendar_event_form.php?action=new&car_id='.$car_id.'&year='.$date['year'].'&month='.$date['month'].'&day='.$date['day'].'&hour='.$date['hour'].'&minute='.$date['minute'].'\', function(){$(\'#event_form\').dialog({width: 600, height: 600, position: {my:\'center\',at:\'center\', of:event, collision:\'fit\'}});});" title="Add new event">'.$car_title.'</span>'.$divider;
		}
	}
	return($output);
}

function calendar_search_form($date=false, $cars_ids=false, $active_cars_ids=false, $active_events_types=false){
	global $dbr;
	
	if ($cars_ids) $sql_restr_cars = " WHERE id IN (".implode(',',$cars_ids).")";
	$cars = $dbr->getAssoc("SELECT id, name FROM cars".$sql_restr_cars." ORDER BY name ASC;");
	$events_types = get_globals('calendar_events_types');
	
	$output .= "<form method=\"GET\" action=\"calendar.php\" id=\"calendar_search\">\n";
	if ($date) {
		$output .= create_input_field('hidden','year',$date['year'],'',false, false, false, false, false);
		$output .= create_input_field('hidden','month',$date['month'],'',false, false, false, false, false);
	}
	$output .= create_checkbox_group($group_title=false, 'car', $cars, $active_cars_ids, false, ' ', 'i');
	$output .= '<br /><br />';
	$output .= create_checkbox_group(get_txt_var('calendar_event_type'), 'event', $events_types, $active_events_types, false, ' ', 'i');
	$output .= '<br /><br />'.create_submit_button('Filter');
	$output .= "</form>\n";
	return $output;
}


function calendar_event_creation_button($car_id=false, $date=false){
/*
	$output = '
<script type="text/javascript">
$(document).ready(function() {
	$(\'#event_form_button\').click(function(e){
		e.preventDefault();
		//var url = $(this).attr(\'href\');
		$(\'#event_form\').load(\'/calendar_event.php?action=new&year='.$date['year'].'&month='.$date['month'].'&day='.$date['day'].'\', function(){$(\'#event_form\').dialog();});
	});
});
</script>';
*/
	$output = '<span class="event_button" title="'.get_txt_var('calendar_add_new_event_title').'" onclick="$(\'#event_form\').load(\'/calendar_event_form.php?action=new&car_id='.$car_id.'&year='.$date['year'].'&month='.$date['month'].'&day='.$date['day'].'&hour='.$date['hour'].'&minute='.$date['minute'].'\', function(){$(\'#event_form\').dialog({width: 600, height: 600, position: {my:\'center\',at:\'center\', of:event, collision:\'fit\'}});});">'.$date['day'].'</span>'."\n";//alertify.alert(\'#event_form\');
	return $output;
}

function calendar_get_car_last_gps_locality($car_id){
	global $dbr;
	
	$data_car = $dbr->getRow("SELECT * FROM cars WHERE id=$car_id;");
	
	if (strlen($data_car->tracking_account) && strlen($data_car->tracking_password) && strlen($data_car->tracking_imei)) {
		$data = file_get_contents("http://{$data_car->tracking_account}:{$data_car->tracking_password}@tracking.itakka.at/track/{$data_car->tracking_imei}/nmea.last");
		if ($data){
			$data = explode(',', $data);
			if (count($data) == 13) {
				$date = '20'.substr($data[9],4,2).'-'.substr($data[9],2,2).'-'.substr($data[9],0,2);
				$time = substr($data[1],0,2).':'.substr($data[1],2,2).':'.substr($data[1],4,2);
				$gps_datetime = $dbr->getOne("select date_add('".$date.' '.$time."', interval 2 hour)");
				$coords = ($data[3]/100).','.($data[5]/100);
				$data2 = file_get_contents('http://maps.googleapis.com/maps/api/geocode/json?latlng='.$coords.'&sensor=false');
				if ($data2){
					$data2 = json_decode($data2, true);
					return $data2['results'][0]['formatted_address'];
				}
			}
		}
	}
	return false;
}

function calendar_event_form($form_data=false){
	global $dbr, $errors_structured, $form_field_data_date;
	
	$events_types = get_globals('calendar_events_types');
	
	$countries = $dbr->getAssoc("SELECT id, name FROM country ORDER BY name ASC;");
	
	//$output .= print_r($form_data, true);
	if ($form_data['start_date']){
		$form_data['start_year'] = substr($form_data['start_date'], 0,4);
		$form_data['start_month'] = substr($form_data['start_date'], 5,2);
		$form_data['start_day'] = substr($form_data['start_date'], 8,2);
		$form_data['start_hour'] = substr($form_data['start_date'], 11,2);
		$form_data['start_minute'] = substr($form_data['start_date'], 14,2);
	}
	else {
		if (!$form_data['start_year']) $form_data['start_year'] = date('Y');
		if (!$form_data['start_month']) $form_data['start_month'] = date('m');
		if (!$form_data['start_day']) $form_data['start_day'] = date('d');
		if (!$form_data['start_hour']) $form_data['start_hour'] = date('H');
		if (!$form_data['start_minute']) $form_data['start_minute'] = date('i');
	}
	
	if ($form_data['end_date']){
		$form_data['end_year'] = substr($form_data['end_date'], 0,4);
		$form_data['end_month'] = substr($form_data['end_date'], 5,2);
		$form_data['end_day'] = substr($form_data['end_date'], 8,2);
		$form_data['end_hour'] = substr($form_data['end_date'], 11,2);
		$form_data['end_minute'] = substr($form_data['end_date'], 14,2);
	}
	/*
	else {
		if (!$form_data['end_year']) $form_data['end_year'] = date('Y');
		if (!$form_data['end_month']) $form_data['end_month'] = date('m');
		if (!$form_data['end_day']) $form_data['end_day'] = date('d');
	}
	*/
	
	if (strlen($form_data['start_month']) == 1 && (int)$form_data['start_month']<10) $form_data['start_month'] = (string)'0'.$form_data['start_month'];
	if (strlen($form_data['start_day']) == 1 && (int)$form_data['start_day']<10) $form_data['start_day'] = (string)'0'.$form_data['start_day'];
	if (strlen($form_data['end_month']) == 1 && (int)$form_data['end_month']<10) $form_data['end_month'] = (string)'0'.$form_data['end_month'];
	if (strlen($form_data['end_day']) == 1 && (int)$form_data['end_day']<10) $form_data['end_day'] = (string)'0'.$form_data['end_day'];
	if (strlen($form_data['end_hour']) == 1 && (int)$form_data['end_hour']<10) $form_data['end_hour'] = (string)'0'.$form_data['end_hour'];
	if (strlen($form_data['end_minute']) == 1 && (int)$form_data['end_minute']<10) $form_data['end_minute'] = (string)'0'.$form_data['end_minute'];
	
	if ($form_data['end_year'] && $form_data['end_year'] !== '0000') $sql_restr_car_event = "SELECT car_id FROM car_event WHERE ((start_date BETWEEN '".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day'].' '.$form_data['start_hour'].':'.$form_data['start_minute'].":00' AND '".$form_data['end_year'].'-'.$form_data['end_month'].'-'.$form_data['end_day'].' '.$form_data['end_hour'].":59:59')
	 OR (end_date BETWEEN '".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day'].' '.$form_data['start_hour'].':'.$form_data['start_minute'].":00' AND '".$form_data['end_year'].'-'.$form_data['end_month'].'-'.$form_data['end_day'].' '.$form_data['end_hour'].":59:59')
	 OR (start_date<='".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day'].' '.$form_data['start_hour'].':'.$form_data['start_minute'].":00' AND end_date>='".$form_data['start_year'].'-'.$form_data['end_month'].'-'.$form_data['end_day'].' '.$form_data['end_hour'].":59:59'))";
	else $sql_restr_car_event = "SELECT car_id FROM car_event WHERE ((start_date BETWEEN '".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day'].' '.$form_data['start_hour'].':'.$form_data['start_minute'].":00' AND '".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day'].' '.$form_data['start_hour'].":59:59')
	 OR (end_date BETWEEN '".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day'].' '.$form_data['start_hour'].':'.$form_data['start_minute'].":00' AND '".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day'].' '.$form_data['start_hour'].":59:59')
	 OR (start_date<='".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day'].' '.$form_data['start_hour'].':'.$form_data['start_minute'].":00' AND end_date>='".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day'].' '.$form_data['start_hour'].":59:59'))";
	
	//if ($form_data['end_year'] && $form_data['end_year'] !== '0000') $sql_restr_car_event = "SELECT car_id FROM car_event WHERE (('".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day'].' '.$form_data['start_hour'].':'.$form_data['start_minute'].":00'>=start_date AND '".$form_data['end_year'].'-'.$form_data['end_month'].'-'.$form_data['end_day'].' '.$form_data['end_hour'].":59:59'<=end_date) OR ('".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day'].' '.$form_data['start_hour'].':'.$form_data['start_minute'].":00'>=start_date AND end_date='0000-00-00 00:00:00'))";
	//else $sql_restr_car_event = "SELECT car_id FROM car_event WHERE (('".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day'].' '.$form_data['start_hour'].':'.$form_data['start_minute'].":00'>=start_date AND '".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day'].' '.$form_data['start_hour'].":59:59'<=end_date) OR ('".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day'].' '.$form_data['start_hour'].':'.$form_data['start_minute'].":00'>=start_date AND end_date='0000-00-00 00:00:00'))";
	
	/*
	if ($form_data['start_hour'] !== '00' || $form_data['start_minute'] !== '00') $sql_restr_wwo = "SELECT car_id FROM ww_order WHERE planned_arrival_date='".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day']."' AND ((planned_arrival_time>='".$form_data['start_hour'].':'.$form_data['start_minute'].":00' AND planned_arrival_time<='".$form_data['start_hour'].":59:59') OR planned_arrival_time='')";
	else $sql_restr_wwo = "SELECT car_id FROM ww_order WHERE planned_arrival_date='".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day']."'";
	
	if ($form_data['start_hour'] !== '00' || $form_data['start_minute'] !== '00') $sql_restr_routes = "SELECT car_id FROM route WHERE start_date='".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day']."' AND ((start_time>='".$form_data['start_hour'].':'.$form_data['start_minute'].":00' AND start_time<='".$form_data['start_hour'].":59:59') OR start_time='')";
	else $sql_restr_routes = "SELECT car_id FROM route WHERE start_date='".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day']."'";
	*/
	
	if ($form_data['end_year']){
		if ($form_data['start_hour'] !== '00' || $form_data['start_minute'] !== '00') $sql_restr_wwo = "SELECT car_id FROM ww_order WHERE planned_arrival_date BETWEEN '".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day']."' AND '".$form_data['end_year'].'-'.$form_data['end_month'].'-'.$form_data['end_day']."' AND (planned_arrival_time>='".$form_data['end_hour'].":59:59' OR planned_arrival_time='')";
		else $sql_restr_wwo = "SELECT car_id FROM ww_order WHERE planned_arrival_date BETWEEN '".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day']."' AND '".$form_data['end_year'].'-'.$form_data['end_month'].'-'.$form_data['end_day']."'";
	}
	else {
		if ($form_data['start_hour'] !== '00' || $form_data['start_minute'] !== '00') $sql_restr_wwo = "SELECT car_id FROM ww_order WHERE planned_arrival_date='".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day']."' AND ((planned_arrival_time>='".$form_data['start_hour'].':'.$form_data['start_minute'].":00' AND planned_arrival_time<='".$form_data['start_hour'].":59:59') OR planned_arrival_time='')";
		else $sql_restr_wwo = "SELECT car_id FROM ww_order WHERE planned_arrival_date='".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day']."'";
	}
	
	if ($form_data['end_year']){
		if ($form_data['start_hour'] !== '00' || $form_data['start_minute'] !== '00') $sql_restr_routes = "SELECT car_id FROM route WHERE start_date BETWEEN '".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day']."' AND '".$form_data['end_year'].'-'.$form_data['end_month'].'-'.$form_data['end_day']."' AND (start_time>='".$form_data['end_hour'].":59:59' OR start_time='')";
		else $sql_restr_routes = "SELECT car_id FROM ww_order WHERE start_date BETWEEN '".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day']."' AND '".$form_data['end_year'].'-'.$form_data['end_month'].'-'.$form_data['end_day']."'";
	}
	else {
		if ($form_data['start_hour'] !== '00' || $form_data['start_minute'] !== '00') $sql_restr_routes = "SELECT car_id FROM route WHERE start_date='".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day']."' AND ((start_time>='".$form_data['start_hour'].':'.$form_data['start_minute'].":00' AND start_time<='".$form_data['start_hour'].":59:59') OR start_time='')";
		else $sql_restr_routes = "SELECT car_id FROM route WHERE start_date='".$form_data['start_year'].'-'.$form_data['start_month'].'-'.$form_data['start_day']."'";
	}
	
	//if ($form_data['id']) $add_restr_event .= " AND id!=".$form_data['id'];
	if ($form_data['id']) {
		$sql_str = "SELECT id, name FROM cars WHERE id NOT IN(".$sql_restr_wwo." UNION ".$sql_restr_routes." UNION ".$sql_restr_car_event.")";//.$add_restr_event
		if ($form_data['car_id']) $sql_str .= " OR id=".$form_data['car_id']; //
		$sql_str .= " ORDER BY name ASC;";
	}
	else $sql_str = "SELECT id, name FROM cars WHERE inactive=0 ORDER BY name ASC;";
	
	//$output .= "\n<br/>\nSQL2: ".$sql_str;
	
	$cars = $dbr->getAssoc($sql_str);
	
	$output .= "<form method=\"POST\" action=\"calendar_event_form.php\" id=\"event_form_content\">\n";
	$output .= "<table border=\"0\" cellspacing=\"2\" cellpadding=\"4\">\n";
	
	if ($form_data['id']) {
		$output .= create_input_field('hidden','id',$form_data['id'],'',false, true, false, false, false);
		$submit_title = get_txt_var('form_edit_title');
		$data_user = $dbr->getOne("SELECT name FROM users WHERE id=".$form_data['user_id'].";");
		$output .= '<tr><td><b>'.get_txt_var('sysuser_title').'</b></td><td class="imp">'.$data_user."</td></tr>\n";
	}
	else {
		$form_field_data_date['years'] = get_numeric_array_for_date(date('Y')-1,date('Y')+1);
		$submit_title = get_txt_var('form_create_title');
	}
	
	$output .= create_input_field('text','title',$form_data['title'],'inp5',255, true, true, false, false);
	$output .= create_select_menu('car_id', $cars, 'inp5', $form_data['car_id'], false, false, true, true, false, false);
	$output .= create_select_menu('type', $events_types, 'inp2', $form_data['type'], false, false, true, true, false, false);
	if (!$form_data['country_id']) $form_data['country_id'] = 194;
	$output .= create_select_menu('country_id', $countries, 'inp', $form_data['country_id'], false, false, true, true, false, false);
	$output .= create_input_field('text','locality',$form_data['locality'],'inp',255, true, true, false, false);

    $date = strtotime("{$form_data['start_year']}-{$form_data['start_month']}-{$form_data['start_day']} {$form_data['start_hour']}:{$form_data['start_minute']}");
    $date = $date ? date('Y-m-d H:i', $date) : '';
	$output .= create_input_field('text','from',$date,'inp datetimepicker',10, true, true, false, false);
    
    $date = strtotime("{$form_data['end_year']}-{$form_data['end_month']}-{$form_data['end_day']} {$form_data['end_hour']}:{$form_data['end_minute']}");
    $date = $date ? date('Y-m-d H:i', $date) : '';
    $output .= create_input_field('text','to',$date,'inp datetimepicker',10, true, true, false, false);
    
//	$output .= '<tr><td><b>'.get_txt_var('from').'</b></td><td>';
//	$output .= create_select_menu_date('years', 'start_year', 'inpd1', $form_data['start_year'], true, false);
//	$output .= create_select_menu_date('months', 'start_month', 'inpd1', $form_data['start_month'], true, false);
//	$output .= create_select_menu_date('days', 'start_day', 'inpd1', $form_data['start_day'], true, false);
//	$output .= create_select_menu_date('hours', 'start_hour', 'inpd1', $form_data['start_hour'], true, false);
//	$output .= create_select_menu_date('minutes', 'start_minute', 'inpd1', $form_data['start_minute'], true, false);
//	$output .= "</td></tr>\n";
//	$output .= '<tr><td><b>'.get_txt_var('to').'</b></td><td>';
//	$output .= create_select_menu_date('years', 'end_year', 'inpd1', $form_data['end_year'], true, false);
//	$output .= create_select_menu_date('months', 'end_month', 'inpd1', $form_data['end_month'], true, false);
//	$output .= create_select_menu_date('days', 'end_day', 'inpd1', $form_data['end_day'], true, false);
//	$output .= create_select_menu_date('hours', 'end_hour', 'inpd1', $form_data['end_hour'], true, false);
//	$output .= create_select_menu_date('minutes', 'end_minute', 'inpd1', $form_data['end_minute'], true, false);
//	$output .= "</td></tr>\n";
	
	$output .= "<tr><td valign=\"top\"><b>".get_txt_var('description_title').'</b></td><td>';
	if ($errors_structured['description']) $output .= '<span class="imp">'.get_txt_var('warning_'.$errors_structured['description'][0]).'</span><br />';
	$output .= "<textarea name=\"description\" class=\"txtarea2\">".stripslashes($form_data['description'])."</textarea></td></tr>\n";
	$output .= "<tr><td>";
	if ($form_data['id']) $output .= create_checkbox(get_txt_var('form_delete_title'), 'delete', '1', false, false, false);
	$output .= "&nbsp;</td><td><span class=\"event_button\" id=\"event_submit\">".$submit_title."</span></td></tr>\n</table>\n</form>\n";
/*
	$output .= "<tr><td>&nbsp;</td><td>".create_submit_button()."</td></tr>\n</table>\n</form>\n";
	$output .= '
<script type="text/javascript">
$(document).ready(function(){
	var options = {target:"#event_form_content",type:"POST",url:"calendar_event_form.php",success:function(data){$("#event_form_content").html(data);}};
	$(\'#event_form_content\').submit(function() {$(this).ajaxSubmit(options);return false;});
});
</script>
';
*/
	$output .= '
<script type="text/javascript">
$(document).ready(function(){
	var options = {target:"#event_form_content",type:"POST",url:"calendar_event_form.php",success:function(data){$("#event_form").html(data);}};
	$(\'#event_submit\').click(function() {$(\'#event_form_content\').ajaxSubmit(options);return false;});
    
    $(\'.datetimepicker\').datetimepicker({
        minuteGrid: 10,
        stepMinute: 10,
        dateFormat: \'yy-mm-dd\'
    });
});
</script>
';
	return $output;
}

function calendar_get_wwos($cars_ids=false, $date=false, $closed=false, $host=false){
	global $db, $dbr, $all_cars;
	
	$warehouses = Warehouse::listArray($db, $dbr);
	
	$sql_restrictions = [];
	if ($closed) {
        $sql_restrictions[] = "ww_order.closed='$closed'";
    }
    
	if ($date) {
		if ($date['hour'] && $date['minute']) {
            $sql_restrictions[] = "ww_order.planned_arrival_date='{$date['year']}-{$date['month']}-{$date['day']}' 
                AND ((ww_order.planned_arrival_time>='{$date['hour']}:{$date['minute']}:00' 
                AND ww_order.planned_arrival_time<='{$date['hour']}:59:59') OR planned_arrival_time='')";
        } else {
            $sql_restrictions[] = "ww_order.ww_orderplanned_arrival_date='{$date['year']}-{$date['month']}-{$date['day']}'";
        }
	}
    
	if ($cars_ids) {
        $sql_restrictions[] = "ww_order.car_id IN(".implode(',',$cars_ids).")";
    }
	
	if ($sql_restrictions) {
		$sql_restrictions_final = ' WHERE '.implode(' AND ', $sql_restrictions);
	} else {
        $sql_restrictions_final = false;
    }
	
	$output = false;
    
	$data_wwos = $dbr->getAssoc("SELECT ww_order.* 
                , destination_port.warehouse_id AS destination_port
                , wwo_port.warehouse_id AS port_port
            FROM ww_order 
            LEFT JOIN wwo_port AS destination_port ON destination_port.wwo_id = ww_order.id AND destination_port.type = 'destination'
            LEFT JOIN wwo_port ON wwo_port.wwo_id = ww_order.id AND wwo_port = 'port'
                AND destination_port.type = 'destination'
            $sql_restrictions_final
            ORDER BY ww_order.planned_arrival_date ASC, ww_order.planned_arrival_time ASC");
    
	if (PEAR::isError($data_wwos)) aprint_r($data_wwos);
	elseif ($data_wwos){
		foreach ($data_wwos as $id => $wwo_data){
			$output .= '<p><span class="imp">&raquo;</span>';
			if ($wwo_data['planned_arrival_time'] !== NULL) $output .= ' <span class="imp">'.substr($wwo_data['planned_arrival_time'],0,5).'</span><br />'; 

			$output .= ' <a href="'.$host.'car.php?id='.$wwo_data['car_id'].'" target="_tab">1'.$all_cars[$wwo_data['car_id']].'</a>:<br /><a href="'.$host.'ware2ware_order.php?id='.$id.'" target="_tab"><b>WWO</b> '.$id.'</a>';
			//if (($current_locality = calendar_get_car_last_gps_locality($wwo_data['car_id'])) !== false) $output .= '<br/><span class="gray" title="Last GPS position">&rarr; '.$current_locality.'</span>';
			if ($wwo_data['destination_port']>0 || $wwo_data['port_port']>0) {
				$output .= '<br/>';
				if ($wwo_data['destination_port']>0) $output .= '<span class="gray" title="Warehouse">&larr; '.$warehouses[$wwo_data['destination_port']].'</span>';
				if ($wwo_data['port_port']>0) $output .= ' <span class="gray" title="Port">&rarr; '.$warehouses[$wwo_data['port_port']].'</span>';
			}
			$output .= "</p>\n";
		}
	}
	return $output;
}

function calendar_get_routes($cars_ids=false, $date=false, $deleted=false, $host=false){
	global $db, $dbr, $all_cars;
	
	$warehouses = Warehouse::listArray($db, $dbr);
	
	$sql_restrictions = array();
	if ($deleted) $sql_restrictions[] = "deleted='".$deleted."'";
	if ($date) {
		if ($date['hour'] && $date['minute']) $sql_restrictions[] = "start_date='".$date['year'].'-'.$date['month'].'-'.$date['day']."' AND ((start_time>='".$date['hour'].':'.$date['minute'].":00' AND start_time<='".$date['hour'].":59:59') OR start_time='')";
		else $sql_restrictions[] = "start_date='".$date['year'].'-'.$date['month'].'-'.$date['day']."'";
	}

	if ($cars_ids) $sql_restrictions[] = "car_id IN(".implode(',',$cars_ids).")";
	
	if ($sql_restrictions) {
		foreach ($sql_restrictions as $sql_restrictions_item) {
			$sql_restrictions_final .= ' AND '.$sql_restrictions_item;
		}
		$sql_restrictions_final = ' WHERE '.mb_substr($sql_restrictions_final,5);
	}
	else $sql_restrictions_final = false;
	
	$output = false;
	$data_routes = $dbr->getAssoc("SELECT * FROM route".$sql_restrictions_final." ORDER BY start_date ASC, start_time ASC;");
	if (PEAR::isError($data_routes)) aprint_r($data_routes);
	elseif ($data_routes) {
		foreach ($data_routes as $id => $route_data){
			$output .= '<p><span class="imp">&raquo;</span>';
			if ($route_data['start_time'] !== NULL) $output .= ' <span class="imp">'.substr($route_data['start_time'],0,5).'</span><br />'; 
			
			$output .= ' <a href="'.$host.'car.php?id='.$route_data['car_id'].'" target="_tab">'.$all_cars[$route_data['car_id']].'</a>:<br /><a href="'.$host.'route.php?id='.$id.'" target="_tab"><b>Route</b> '.$route_data['name'].'</a>';
			//if (($current_locality = calendar_get_car_last_gps_locality($route_data['car_id'])) !== false) $output .= '<br/><span class="gray" title="Last GPS position">&rarr; '.$current_locality.'</span>';
			if ($route_data['start_warehouse_id']>0 || $route_data['start_address']) {
				$output .= '<br/>';
				if ($route_data['start_warehouse_id']>0) $output .= '<span class="gray" title="Start Warehouse">&larr; '.$warehouses[$route_data['start_warehouse_id']].'</span>';
				if ($route_data['start_address']) $output .= ' <span class="gray" title="Start Address">&larr; '.$route_data['start_address'].'</span>';
			}
			if ($route_data['end_warehouse_id']>0 || $route_data['end_address']) {
				$output .= '<br/>';
				if ($route_data['end_warehouse_id']>0) $output .= '<span class="gray" title="End Warehouse">&rarr; '.$warehouses[$route_data['end_warehouse_id']].'</span>';
				if ($route_data['end_address']) $output .= ' <span class="gray" title="End Address">&rarr; '.$route_data['end_address'].'</span>';
			}
			$output .= "</p>\n";
		}
	}
	return $output;
}

function calendar_get_events($cars_ids=false, $events=false, $date=false, $status=false, $host=false){
	global $dbr, $all_cars;
	
	$events_types = get_globals('calendar_events_types');
	$countries = $dbr->getAssoc("SELECT id, name FROM country ORDER BY name ASC;");
	
	$sql_restrictions = array();
	if ($status) $sql_restrictions[] = "status='".$status."'";
	if ($date) {
		if ($date['hour'] && $date['minute']) $sql_restrictions[] = "((start_date BETWEEN '".$date['year'].'-'.$date['month'].'-'.$date['day'].' '.$date['hour'].':'.$date['minute'].":00' AND '".$date['year'].'-'.$date['month'].'-'.$date['day'].' '.$date['hour'].":59:59') OR (end_date BETWEEN '".$date['year'].'-'.$date['month'].'-'.$date['day'].' '.$date['hour'].':'.$date['minute'].":00' AND '".$date['year'].'-'.$date['month'].'-'.$date['day'].' '.$date['hour'].":59:59') OR (start_date<='".$date['year'].'-'.$date['month'].'-'.$date['day'].' '.$date['hour'].':'.$date['minute'].":00' AND end_date>='".$date['year'].'-'.$date['month'].'-'.$date['day'].' '.$date['hour'].":59:59'))";
		else $sql_restrictions[] = "((start_date BETWEEN '".$date['year'].'-'.$date['month'].'-'.$date['day']." 00:00:00' AND '".$date['year'].'-'.$date['month'].'-'.$date['day']." 23:59:59') OR (end_date BETWEEN '".$date['year'].'-'.$date['month'].'-'.$date['day']." 00:00:00' AND '".$date['year'].'-'.$date['month'].'-'.$date['day']." 23:59:59') OR (start_date<='".$date['year'].'-'.$date['month'].'-'.$date['day']." 00:00:00' AND end_date>='".$date['year'].'-'.$date['month'].'-'.$date['day']." 23:59:59'))";
	}
	if ($cars_ids) $sql_restrictions[] = "car_id IN(".implode(',',$cars_ids).")";
	if ($events) $sql_restrictions[] = "type IN('".implode("','",$events)."')";
	
	if ($sql_restrictions) {
		foreach ($sql_restrictions as $sql_restrictions_item) {
			$sql_restrictions_final .= ' AND '.$sql_restrictions_item;
		}
		$sql_restrictions_final = ' WHERE '.mb_substr($sql_restrictions_final,5);
	}
	else $sql_restrictions_final = false;
	
	$output = false;
	//print "SELECT * FROM car_event".$sql_restrictions_final.";<br />\n";
	$data_events = $dbr->getAssoc("SELECT * FROM car_event".$sql_restrictions_final." ORDER BY start_date ASC;");
	if (PEAR::isError($data_events)) aprint_r($data_events);
	elseif ($data_events) {
		foreach ($data_events as $id => $event_data){
			$output .= '<p><span class="imp">&raquo;</span>';
			$output .= ' <span class="imp">'.substr($event_data['start_date'],0,16).'</span> ';
			if ($event_data['end_date'] !== '0000-00-00 00:00:00') $output .= '- <span class="imp">'.substr($event_data['end_date'],0,16).'</span>'; 
			$output .= '<br /><a href="'.$host.'car.php?id='.$event_data['car_id'].'" class="darkblue" target="_tab">'.$all_cars[$event_data['car_id']].'</a>:<br /><span class="link" onclick="$(\'#event_form\').load(\'/calendar_event_form.php?id='.$id.'\', function(){$(\'#event_form\').dialog({width: 600, height: 600, position: {my:\'center\',at:\'center\', of:event, collision:\'fit\'}});});"><b>'.$events_types[$event_data['type']].'</b> '.$event_data['title'].'</span>';
			//'<a href="'.$host.'event.php?id='.$id.'" target="_tab" title="'.$event_data['description'].'"><b>Event</b> '.$event_data['title'].'</a>';
			//if (($current_locality = calendar_get_car_last_gps_locality($event_data['car_id'])) !== false) $output .= '<br/><span class="gray" title="Last GPS position">&rarr; '.$current_locality.'</span>';
			$output .= '<br/><span class="gray" title="Event country / locality">&larr; '.$countries[$event_data['country_id']].'/ '.$event_data['locality'].'</span>';
			$output .= "</p>\n";
		}
	}
	return $output;
}
?>
