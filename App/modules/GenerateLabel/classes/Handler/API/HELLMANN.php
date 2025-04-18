<?php
/**
 * @author Dmytro Ped
 * @version 1.0.0
 */
namespace label\Handler\API;

require_once 'Smarty.class.php';

use label\Handler\HandlerAbstract;

/**
 * Class HELLMANN used in class HandlerFabric
 * @package label\Handler\API
 */
class HELLMANN extends HandlerAbstract
{
    private $ftp_server = 'emea-b2b-ftp1.hellmann.net';
    private $ftp_user_name = 'f-beli00';
    private $ftp_user_pass = 'Y35gxEBj3N4L';
    private $holidays;
    private $sender;
    private $tns = [];
    private $weightTotal = 0;
    private $itemsTotal = 0;
    private $db;
    private $dbr;
    private $debug;

    public function __construct()
    {
        $this->db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $this->dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        
        $this->sender = array(
            'company_name' => 'Beliani DE GmbH',
            'street_name' => 'Seeweg',
            'street_number' => '1',
            'zip' => '17291',
            'country' => 'Germany',
            'city' => 'Grünow',
        );
        $this->holidays = [
            '06.01.2017',
        ] ;
        $this->debug = 1;
    }

    /**
     * Main function
     */
    public function action()
    {
        $this->orderItems();
        
        $output = '';
        foreach ($this->tns as $key => $tn) {
            $pdf = $this->generatePDFlabel($key + 1, $tn['tn']);
            $id = $this->saveLabel($tn['tn'], $pdf);
            $output .= "<li><a href='doc.php?auction_label=$id' target='_blank'>Download label #" . $key . "</a></li>";
        }

        $Ladeliste = $this->getLadeliste();
        $id = $this->saveLabel($this->tns[0]['tn'] . "_ladelist", $Ladeliste);
        $output .= "<li><a href='doc.php?auction_label=$id' target='_blank'>Download ladeliste</a></li>";

        // upload data file
        $dataFile = $this->getDataFile();
        $date = date("dmy");
        $data_file_name = "TRSSPE$date.DAT";
        $directory = 'in';
        $link = "ftp://" . $this->ftp_user_name . ":" . $this->ftp_user_pass . "@" . $this->ftp_server . "/" . $directory . "/" . $data_file_name;
        /*if(!$this->debug) {
            if (file_put_contents($link, $dataFile, FILE_APPEND)) {
                $this->addLoadingList();
                
                if(count($this->tns) > 1){
                    die($output);
                }
                else {
                    header("Content-Type: application/pdf");
                    header("Content-disposition: inline; filename=label.pdf");
                    die($pdf);
                }
            } 
            else {
                echo "There was a problem while uploading $data_file_name";
            }
        }
        else {*/
            header("Content-Type: text/plain");
            header("Content-disposition: attachment; filename=$data_file_name");
            die($dataFile);
//        }
    }
    
    private function orderItems(){
        $allorder = \Order::listAll($this->request_params['DB'], $this->request_params['DB'], $this->auction_params->get('auction_number'), $this->auction_params->get('txnid'), 1, $this->auction_params->getMyLang(), '0,1', 1);

        foreach ($allorder as $key => $order) {
            if ($allorder[$key]->article_id != 0 && $allorder[$key]->admin_id == 0) {
                $article = new \Article($this->request_params['DB'], $this->request_params['DB'], $order->article_id);
                if(
                    stripos($article->data->title, 'carton') !== false ||
                    stripos($article->data->title, 'cartoon') !== false
                ){
                    continue;
                };
                
                $CountItems = ceil($order->quantity / $article->data->items_per_shipping_unit);
                $weight = round($article->parcels[0]->weight_parcel * $order->quantity / $article->data->items_per_shipping_unit);
                
                $this->itemsTotal += $CountItems;
                $this->weightTotal += $weight;

                $this->tns[] = [
                    'tn' => $this->getNVE(),
                    'weight' => $weight,
                    'article_id' => $article->data->article_id,
                    'title' => $article->data->title,
                    'qty' => $CountItems,
                    'length' => round($article->parcels[0]->dimension_l, 0),
                    'height' => round($article->parcels[0]->dimension_h, 0),
                    'width' => round($article->parcels[0]->dimension_w, 0),
                ];
                
            }
        }
    }

    private function addLoadingList(){
        $warehouse_id = 115; // Wietzendorf
        $shipping_method_id = $this->request_params['method']->get('shipping_method_id');

        $q = "INSERT INTO mobile_loading_list 
        SET warehouse_id = $warehouse_id, method_id = $shipping_method_id";
        $this->db->query($q);
        $ll_id = $this->db->queryOne('SELECT LAST_INSERT_ID()');

        foreach ($this->tns as $tn) {
            if($tn['tn']){
                $q = "INSERT IGNORE INTO mobile_loading_list_tn 
                SET ll_id = $ll_id, tracking_number = '" . $tn['tn'] . "'";
                $this->db->query($q);
                $tn_id = $this->db->queryOne('SELECT LAST_INSERT_ID()');
            }
        }
    }
    
    private function getLadeliste(){
        require_once ROOT_DIR . '/plugins/function.barcodeurl.php';
        
        $smarty = new \Smarty();
        
        $smarty->assign('tns', $this->tns);
        $smarty->assign('itemsTotal', $this->itemsTotal);
        $smarty->assign('sender', $this->sender);

        $recipient = array(
            'name' => $this->auction_params->get('firstname_shipping') . ' ' . $this->auction_params->get('name_shipping'),
            'company' =>  $this->auction_params->get('company_shipping'),
            'street' => $this->auction_params->get('street_shipping'),
            'house' => $this->auction_params->get('house_shipping'),
            'zip' => $this->auction_params->get('zip_shipping'),
            'city' => $this->auction_params->get('city_shipping'),
            'country' => $this->auction_params->get('country_shipping'),
        );
        $smarty->assign('recipient', $recipient);

        $date = date('d.m.Y');
        $smarty->assign('date', $date);
        
        $time = date('H:i');
        $smarty->assign('time', $time);
        
        $smarty->assign('reference', $this->auction_params->get('auction_number') . "_" . $this->auction_params->get('txnid'));
        $smarty->assign('weightTotal', $this->weightTotal);

        $barcodes = '<tr>';
        foreach ($this->tns as $key => $tn){
            $barcodes .= '<td width="33%"><img width="175" src="' . smarty_function_barcodeurl([
                                'number' => $tn['tn'],
                                'height' => 20], $smarty) . '"></td>';
            if(($key + 1) % 3 == 0){
                $barcodes .= '</tr><tr>';
            }
        }
        $barcodes .= '</tr>';
        $smarty->assign('barcodes', $barcodes);

        $html = $smarty->fetch($_SERVER['DOCUMENT_ROOT'] . "labels/hellmann_ladeliste.tpl");
        $mpdf = new \mPDF($mode = '', $format = 'A4', $default_font_size = 0, $default_font = 'arial');
        $mpdf->writeHTML($html);

        return $mpdf->Output('', 'S');
    }

    private function getWorkingDay(){
        $mult = 1;
        $date = $this->nextWorkingDay($mult);
        while($date == false){
            $date = $this->nextWorkingDay($mult++);
        }

        return $date;
    }

    private function nextWorkingDay($mult){
        $day_seconds = 24 * 60 * 60;
        $next_ts = time() + $day_seconds * $mult;
        $tomorrow_date = date("d.m.y", $next_ts);
        $day = date('w', $next_ts); // get day # - 0 (for Sunday) through 6 (for Saturday)
        if ($day == 0) $next_ts += $day_seconds;
        if ($day == 6) $next_ts += 2 * $day_seconds;

        $holidays = [];
        foreach ($this->holidays as $holiday) {
            $holidays[] = date("d.m.y", strtotime($holiday));
        }

        $tomorrow_date = date("d.m.y", $next_ts);
        if (!in_array($tomorrow_date, $holidays)) {
            $date = date('dmy', $next_ts);
        } else {
            $date = false;
        }

        return $date;
    }

    /**
     * convert data to ISO-8859-1
     * @param array $data
     */
    private function unicode2ascii(array &$data){
        setlocale(LC_ALL, 'de_DE');
//        setlocale(LC_ALL, 'en_US');
        foreach ($data as &$item) {
            if(mb_detect_encoding($item, 'utf-8', true)){
                $item = iconv('UTF-8', 'ASCII//TRANSLIT', $item);
//                $item = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $item);
//                $item = utf8_decode($item);
            }
        }
    }

    /**
     * Generate system DAT file for HELLMANN
     *
     * @return mixed .DAT file
     */
    private function getDataFile()
    {
        $invoice = $this->auction_params->getMyInvoice();
        $CurrencyCode = siteToSymbol($this->auction_params->get('siteid'));
        $openAmount = number_format($invoice->get("open_amount"), 2, '.', '');

        $country = countryToCountryCode($this->auction_params->get('country_shipping'));
        //set interface records
        $fields = [];
        $fields['A']['02-04'] = '300'; // 490
        $fields['A']['05-07'] = ($country == 'DE') ? 'NEL' : 'EXP'; // EUL
        $fields['A']['08-14'] = '6122261';
        $fields['A']['15-24'] = 'PCBCE'; //Sender user identification
        $fields['A']['25-26'] = '90'; //terms of sale
        $fields['A']['27-31'] = str_pad(count($this->tns), 5, '0', STR_PAD_LEFT); //number of packages (sum)
        $fields['A']['32-36'] = str_pad($this->weightTotal, 5, '0', STR_PAD_LEFT); // weight
        $fields['A']['37-42'] = substr($this->auction_params->get('name_shipping'), 0, 6); //consignee match code
        $fields['A']['43-57'] = $this->auction_params->get('auction_number'); //shipper order reference
        $fields['A']['58-63'] = $this->getWorkingDay();
        $fields['A']['64-73'] = '';
        $fields['A']['74-79'] = '';
        $fields['A']['80-91'] = 'INHOUSE431';

        $fields['B']['02-36'] = $this->auction_params->get('firstname_shipping') . ' ' . $this->auction_params->get('name_shipping'); //delivery address: name 1
        $fields['B']['37-71'] = $this->auction_params->get('street_shipping') . ' ' . $this->auction_params->get('house_shipping'); //delivery address: street
        $fields['B']['72-80'] = $this->auction_params->get('zip_shipping'); //delivery address: postcode
        $this->unicode2ascii($fields['B']);

        $fields['C']['02-04'] = $country; //consignee country
        $fields['C']['05-39'] = $this->auction_params->get('city_shipping'); //consignee town
        $this->unicode2ascii($fields['C']);

//        $fields['D']['41-43'] = $CurrencyCode; //currency of value
//        $fields['D']['44-52'] = $invoice->get('total_price'); //value of goods
        /*if($this->isCod()) {
            $fields['D']['53-55'] = $CurrencyCode; //currency of C.O.D
            $fields['D']['56-64'] = $openAmount; //amount of C.O.D
            $fields['K']['02-04'] = 402;
        }*/
        
        foreach ($this->tns as $tn){
            $title = mb_strlen($tn['title'], 'UTF-8') > 20 ? 
                mb_substr($tn['title'], 0, 20, 'UTF-8') :
                $tn['title'];
                
            $fields['J'][] = [
                '02-19' => '', // marks
                '20-23' => str_pad(1, 4, '0', STR_PAD_LEFT), //number of packages
                '24-25' => 'EP',
                '26-45' => $title,
                '47-51' => str_pad(ceil($tn['weight']), 5, '0', STR_PAD_LEFT), // weight
                '52-55' => '', // number of pieces on pallets
                '56-57' => '', // type of packaging pieces on pallets
                '58-60' => str_pad($tn['qty'], 3, '0', STR_PAD_LEFT), // number for measurement
                '61-63' => str_pad($tn['length'], 3, '0', STR_PAD_LEFT), // length in cm
                '64-66' => str_pad($tn['width'], 3, '0', STR_PAD_LEFT), // width in cm
                '67-69' => str_pad($tn['height'], 3, '0', STR_PAD_LEFT), // height in cm
            ];
        }
        
        // The Avis is set in segment K with the key 301 for shipments within Germany.
        // Key 281 is set for Avis for shipments outside Germany
        if($this->auction_params->get('country_shipping') == 'Germany'){
            $tel = strlen(trim($this->auction_params->get('cel_shipping'))) ?
                trim($this->auction_params->get('cel_shipping')) :
                trim($this->auction_params->get('tel_shipping'));
            $avis = '301' . '0' . $tel;
        }
        else {
            $countries = $this->dbr->getAssoc("select country.code, country.* from country");
            $cel_prefix = $countries[$this->auction_params->get('cel_country_code_shipping')]['phone_prefix'];
            $tel_prefix = $countries[$this->auction_params->get('tel_country_code_shipping')]['phone_prefix'];

            $tel = strlen(trim($this->auction_params->get('cel_shipping'))) ?
                trim($cel_prefix . $this->auction_params->get('cel_shipping')) :
                trim($tel_prefix . $this->auction_params->get('tel_shipping'));
            $avis = '281' . $tel;
        }
        
        $fields['K']['02-' . (strlen($avis) + 1)] = $avis;

        foreach ($this->tns as $tn) {
            $fields['O'][] = [
                '02-04' => 'NVE',
                '05-39' => $tn['tn'],
            ];   
        }
        
/*        $fields['O']['02-04'] = 'NVE';
        $fields['O']['05-39'] = $this->>tn;*/

        //get string records for file .DAT
        $output = '';
        foreach ($fields as $i => $v){
            $text = $this->getDataLine($v, $i);
            if($i == 'A' || $i == 'B'){
                $text = rtrim($text);
            }
            
            $output .= $text . "\r\n";
        }

//        $output = preg_replace("~(^[\r\n]*|[\r\n]+)~", "", $output); // remove blank lines
        $output = preg_replace('~\n\r~', '', $output);
        
        return $output;
    }

    /**
     * generated line of HELLMANN data structure
     * @param array $arr Array of one interface records
     * @param null $line Name of interface records
     *
     * @return string
     */
    private function getDataLine($arr, $line = null)
    {
        $text = '';
        $no_line = false;
        foreach ($arr as $index => $val) {
            if (is_array($val)) {
                $text .= $this->getDataLine($val, $line) . "\r\n";
                $no_line = true;
                continue;
            }
            $size = explode('-', $index);             //check text size
            if (is_array($size) && $size && count($size) == 2) {
                $size = (((int)$size[1]) - ((int)$size[0])) + 1;
            } 
            else {
                continue;
            }
            if (mb_strlen($val) > $size) {
                $text .= mb_substr($val, 0, $size);
            } 
            elseif (mb_strlen($val) < $size) {
                $text .= $val . str_repeat(' ', $size - mb_strlen($val));
            } 
            else {
                $text .= $val;
            }
        }

        return ((!$no_line && $line) ? $line : '') . $text;
    }

    /**
     * Generated pdf label
     *
     * @return string PDF file source
     */
    private function generatePDFlabel($current_num, $tn){
        $smarty = new \Smarty();
        
        $this->unicode2ascii($this->sender);
        
        $recipient = array(
            'name' => $this->auction_params->get('firstname_shipping') . ' ' . $this->auction_params->get('name_shipping'),
            'street' => $this->auction_params->get('street_shipping'),
            'house' => $this->auction_params->get('house_shipping'),
            'zip' => $this->auction_params->get('zip_shipping'),
            'city' => $this->auction_params->get('city_shipping'),
            'country' => $this->auction_params->get('country_shipping'),
        );
        $this->unicode2ascii($recipient);

        $smarty->assign('current_num', $current_num);
        $smarty->assign('itemsTotal', $this->itemsTotal);
        $smarty->assign('barcode', $tn);
        
        // get route
        $route_code = false;

        $smarty->assign('route_code', $route_code);
        $smarty->assign('sender', $this->sender);
        $smarty->assign('recipient', $recipient);
        $smarty->assign('date', date("d.m.Y"));

        $shipping_method_id = $this->request_params['method']->get('shipping_method_id');
        $tpl = $this->request_params['DB']->getOne('select labels from shipping_method where shipping_method_id=' . $shipping_method_id);

        $html = $smarty->fetch($_SERVER['DOCUMENT_ROOT'] . "labels/" . $tpl);

        $mpdf = new \mPDF($mode = '', $format = 'A4', $default_font_size = 0, $default_font = 'arial');
        $mpdf->writeHTML($html);

        return $mpdf->Output('', 'S');
    }

    /**
     * Generated NVE code
     *
     * @return string Package ID
     */
    private function getNVE()
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        $tn = $this->generateNVE();
        while ($dbr->getOne("SELECT id FROM auction_label WHERE tracking_number='$tn'")) {
            $tn = $this->generateNVE();
        }

        return $tn;
    }
    
    private function generateNVE(){
        $prefix = '003404918760071';
        $rand = rand(7201, 9200);
        $tn = $prefix . $rand;

        return $tn;
    }
    
}