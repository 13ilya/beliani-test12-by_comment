<?php
/**
 * @author  Dmytroped
 * @version 1.0.0
 */
namespace label\Handler\API;

use label\Handler\HandlerAbstract;

/**
 * Class GLS used in class HandlerFabric
 *
 * @package label\Handler\API
 */
class GLS190 extends HandlerAbstract
{
    private $track_and_trace_user = '2760307439';
    private $track_and_trace_password = '910010585';
    private $gls_customer_id = '2760307439';
    private $gls_contact_id = '276a45eGQJ';
    private $gls_сustomer_number  = '910010724';
    private $web_portal_name = '2760307439-910010724';
    private $shipments_api_url = 'https://api.gls-group.eu/public/v1/shipments';
    private $ref_number;
    private $sender;
    private $isTest;
    private $realOrderItems = [];
    private $articles = [];
    private $weightTotal = 0;
    private $shipmentBody;

    public function __construct()
    {
        $this->isTest = (
            strpos($_SERVER['HTTP_HOST'], 'dev.') ||
            strpos($_SERVER['HTTP_HOST'], 'heap.')
        ) ? true : false;
        $this->isTest = false;
        if($this->isTest) {
            $this->shipments_api_url = 'https://api-qs.gls-group.eu/public/v1/shipments';
            $this->gls_customer_id = "2764200001";
            $this->gls_contact_id = "276a17aFXh";
            $this->track_and_trace_user = 'shipmentsapi';
            $this->track_and_trace_password = 'shipmentsapi';
        }  
        
        $this->sender = [
            'company_name' => 'Lagerhaus Beliani',
            'company_name2' => '',
            'street_name' => 'Heideweg',
            'street_number' => '46',
            'zip' => '18273',
            'city' => 'Güstrow', // Gustrow
            'country' => 'D',
        ];
    }

    public function action($continue = false)
    {
        $this->getOrderArticles();
        $this->getRefNumber();
        $this->shipmentBody();
        $result = $this->shipmentRequest();
        
        $output = '';
        if(count($result->errors)){
            foreach ($result->errors as $error) {
                $output .= "<li>$error->exitCode: $error->exitMessage. $error->description</li>";
            }
            die($output);
        }
        if(count($result->labels)){
            foreach ($result->labels as $key => $parcel) {
                $parcelNumber = $result->parcels[0]->trackId;
                $label = base64_decode($result->labels[$key]);
                $id = $this->saveLabel($parcelNumber, $label);
                $output .= "<li><a href='doc.php?auction_label=$id' target='_blank'>Download label #" .  ($key + 1) . "</a></li>";
            }
            if (count($result->labels) > 1) {
                echo $output;
            }
            elseif(count($result->labels) == 1) {
                header("Content-Type: application/pdf");
                header("Content-disposition: inline; filename=label.pdf");
                echo $label;
            }
            die();
        }
        
/*        $data = $this->getLabelContent();
        $pdf = $this->getPDF($data);
        $this->saveLabel($this->ref_number, $pdf);
        
        if($continue) return $pdf;

        header("Content-type: application/pdf");
        header("Content-disposition: inline; filename=label.pdf");
        die($pdf);*/
    }

    private function getOrderArticles(){
        $allorder = \Order::listAll($this->request_params['DB'], $this->request_params['DB'],
            $this->auction_params->get('auction_number'), $this->auction_params->get('txnid'), 1,
            $this->auction_params->getMyLang(), '0,1', 1);

        $articles = [];
        $qty_total = 0;
        
        foreach ($allorder as $key => $order) {
            if (
                $order->article_id != 0 && 
                $order->admin_id == 0 && 
                !in_array($order->article_id, $this->articlesToExclude())
            ) {
                $this->realOrderItems[$order->article_id] = $order;
                $article = new \Article($this->request_params['DB'], $this->request_params['DB'], $order->article_id);
                if(
                    stripos($article->data->title, 'carton') !== false
                    || stripos($article->data->title, 'cartoon') !== false
//                  || stripos($article->data->title, 'karton') !== false
                ){
                    unset($allorder[$key]);
                    continue;
                };
                $article->quantity = $order->quantity;
                $article->weight = $article->parcels[0]->weight_parcel / $article->data->items_per_shipping_unit;
                $this->weightTotal += $article->weight;

                $qty = $order->quantity / $article->data->items_per_shipping_unit;
                $qty_total += $qty;
                $num_labels = ceil($qty);

                for ($i = 0; $i < $num_labels; $i++){
                    $articles[$order->article_id . "_" . $i] = $article;
                }
            }
        }

        $labels_ll = $this->request_params['number_of_labels_ll'];
        if(!$labels_ll){
            $this->articles = $articles;
        }
        else {
            for($i = 0; $i < $labels_ll; $i++){
                $article = new \stdClass();
                $article->weight = $this->weightTotal / $labels_ll;
                $article->quantity = 1;
                $this->articles[] = $article;
            }
        }

    }

    private function shipmentBody(){
        $shipperId = "$this->gls_customer_id $this->gls_contact_id";
        $tel = $this->auction_params->get('cel_shipping') ? $this->auction_params->get('cel_shipping') : $this->auction_params->get('tel_shipping');

        $data = [
            "shipperId" => $shipperId,
            "shipmentDate" => date('Y-m-d'),
            "references" => [$this->ref_number],
            "addresses" => [
                "delivery" => [
                    "name1" => $this->auction_params->get('firstname_shipping') . " " . $this->auction_params->get('name_shipping'),
                    "name2" => '',
                    "street1" => $this->auction_params->get('street_shipping') . " " . $this->auction_params->get('house_shipping'),
                    "country" => CountryToCountryCode($this->auction_params->get('country_shipping')),
                    "zipCode" => $this->auction_params->get('zip_shipping'),
                    "city" => $this->auction_params->get('city_shipping'),
                    "contact" => $this->auction_params->get('firstname_shipping') . ' ' . $this->auction_params->get('name_shipping'),
                    "email" => $this->auction_params->get('email_shipping'),
                    "phone" => $tel,
                ],
                "return" => [
                    "name1" => $this->sender['company_name'],
                    "street1" => $this->sender['street_name'] . ' ' . $this->sender['street_number'],
                    "country" => "DE",
                    "zipCode" => $this->sender['zip'],
                    "city" => $this->sender['city'],
                    "contact" => $this->sender['company_name'],
                ],
            ],
            "incoterm" => "10",
        ];

        $data["parcels"] = [];

        if ($this->isCod()) {
            $count_to_divide = count($this->articles) ? count($this->articles) : 1;
            $invoice = $this->auction_params->getMyInvoice();
            $openAmountParcel = number_format($invoice->get("open_amount") / $count_to_divide, 2, '.', '');
        }

        if(count($this->articles)){
            foreach ($this->articles as $key => $article) {
                $parcel = [
                    "weight" => $article->weight,
                    "references" => [$this->ref_number . "-" . $key],
                ];
                if ($this->isCod()) {
                    $parcel["services"][] = [
                        "name" => "cod",
                        "infos" => [
                            ["name" => "amount", "value" => $openAmountParcel,],
                            ["name" => "reference", "value" => $this->ref_number,],
                        ],
                    ];
                }
                $data["parcels"][] = $parcel;
            }
        }
        else {
            $parcel = [
                "weight" => 1,
                "references" => [$this->ref_number],
            ];
            if ($this->isCod()) {
                $parcel["services"][] = [
                    "name" => "cod",
                    "infos" => [
                        [
                            "name" => "amount",
                            "value" => $openAmountParcel,
                        ],
                        [
                            "name" => "reference",
                            "value" => $this->ref_number,
                        ],
                    ],
                ];
            }
            $data["parcels"][] = $parcel;
        }
        
        $decoded = json_encode($data);
        $this->shipmentBody = $decoded;
//        echo '<pre>' . print_r($data, TRUE) . '</pre><hr>'; 
//        die();
    }

    private function shipmentRequest(){
        $headers = [
            "Accept-Language: en",
            "Accept: application/json",
            "Content-type: application/json",
        ];

        $ch = curl_init($this->shipments_api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->shipmentBody);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->track_and_trace_user:$this->track_and_trace_password");
        $result = curl_exec ($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
        curl_close ($ch);

        return json_decode($result);
    }
    
    private function getRefNumber(){
        $auction_number = $this->auction_params->data->auction_number;
        $txnid = $this->auction_params->data->txnid;
        $shipping_method_id = $this->request_params['method']->data->shipping_method_id;
    
        $q = "SELECT COUNT(tracking_number) FROM auction_label WHERE auction_number={$auction_number} AND txnid={$txnid} AND shipping_method_id = $shipping_method_id";
        $counter = $this->request_params['DB']->getOne($q);
        $counter += 1;

        $this->ref_number = $auction_number . '/' . $txnid . '-' . $counter;
    }
    
    /**
     * OLD label generation
     */

    private function getLabelContent()
    {
        $date = date("dmy", $this->timestamp);
        $time = date("H:i:s", $this->timestamp) . date("P", $this->timestamp);
        $country_code = CountryToCountryCode($this->auction_params->get('country_shipping'));
        $country_iso_code = CountryToISOCode($this->auction_params->get('country_shipping'));
        
        $content = [];
        $content[1] = 'A';
        $content[2] = $this->gls_customer_id;
        $content[3] = $this->gls_contact_id;
        $content[4] = $country_code == 'DE' ? 'AA' : 'CC'; //Product
        $content[5] = $country_iso_code; 
//        $content[5] = CountryToISOCode($this->auction_params->get('country_shipping')); //Country ISO code
        $content[6] = $this->auction_params->get('zip_shipping'); //Zip code of Destination
        $content[7] = count($this->realOrderItems); //Total Unit of consignment
        $content[8] = '1'; //Unit sequence number in consignment
        $content[9] = ''; //Customers consignment reference
        $content[10] = substr($this->auction_params->get('firstname_shipping'), 0, 20); //Recipient Name 1
        $content[11] = substr($this->auction_params->get('name_shipping'), 0, 20); //Recipient Name 2
        $content[12] = ' '; //Recipient Name 3 (Can be used as Street 2)
        $content[13] = substr($this->auction_params->get('street_shipping'), 0, 20); //Street of recipient
        $content[14] = substr($this->auction_params->get('house_shipping'), 0, 5); //Street/House Number
        $content[15] = substr($this->auction_params->get('city_shipping'), 0, 20); //City/Village
        $content[16] = !empty($this->auction_params->get('tel_shipping')) ? $this->auction_params->get('tel_shipping') : $this->auction_params->get('cel_shipping'); //Phone number
        $content[17] = $this->ref_number; //Reference number of customer
        $content[18] = $this->gls_сustomer_number; //GLS reference Number
        $content[19] = $this->weightTotal * 100; //Weight
        
        if ($this->isCod()) {
            $invoice = $this->auction_params->getMyInvoice();
            $openAmount = number_format($invoice->get("open_amount"), 2, '.', '');
            $currency = siteToSymbol($this->auction_params->get('siteid'));
            $content[20] = 'D^' . $openAmount . '^' . $currency . '^Invoice No: ' . $invoice->get('invoice_number'); //Services and additional service information
        } else {
            $content[20] = '';
        }

        $this->unicode2ascii($content);
        
        $diff = 303 - strlen(implode('|', $content));
        $content[20] .= str_repeat(' ', $diff) . '|';
        
        return $content;
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

    private function getPDF($barcode_text)
    {
        // $orientation='P', $unit='mm', $format='A4', $unicode=true, $encoding='UTF-8', $diskcache=false, $pdfa=false
//        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetPrintHeader(false);
        $pdf->SetPrintFooter(false);

        $robo_bold = \TCPDF_FONTS::addTTFfont(ROOT_DIR . '/css/fonts/roboto/bold/roboto-bold.ttf', '', '', 32);
        $robo_norm = \TCPDF_FONTS::addTTFfont(ROOT_DIR . '/css/fonts/roboto/regular/roboto-regular.ttf', '', '', 32);
//        $bold = \TCPDF_FONTS::addTTFfont(ROOT_DIR . '/css/fonts/Swis721/swis721_cn_bt_b.ttf', 'Swis-cn-bold', '', 32);
//        $bold = \TCPDF_FONTS::addTTFfont(ROOT_DIR . '/dompdf/lib/fonts/arial.ttf', 'Arial', '', 32);
        $roman = \TCPDF_FONTS::addTTFfont(ROOT_DIR . '/css/fonts/Swis721/swis721-cn-bt-roman.ttf', 'Swis-cn-roman', '', 32);
        $lineStyle = [
            'width' => 0.1, 
            'color' => [0, 0, 0]
        ];
        
        // add a page
        $pdf->AddPage();
        $pdf->SetFont($robo_bold);
        // border 100x148,5 mm
        $pdf->Line(10, 10, 200, 10, $lineStyle);
        $pdf->Line(10, 10, 10, 290, $lineStyle);
        $pdf->Line(200, 10, 200, 290, $lineStyle);
        $pdf->Line(10, 290, 200, 290, $lineStyle);

        $html = '<div style="display: table;vertical-align: middle;background: black;color: #000;width: 100%; height: 100%;position: relative;text-align: center">
<div style="display: table-cell;vertical-align: middle;color: white;font-size: 25px;margin-top: 15px">UNI-SHIP</div>
</div>';
        $pdf->writeHTMLCell(190, 17, 10, 10, $html, 1, 0, true);

        // set style for barcode
        $style = [
            'border' => 1,
            'fgcolor' => [0, 0, 0],
            'bgcolor' => false, //array(255,255,255)
        ];

//        $pdf->serializeTCPDFtagParameters(array('UNI-SHIP test test test', 'QRCODE,H', '', '', 42, 42, $style, 'N'));
        $text = implode('|', $barcode_text);
        $pdf->write2DBarcode($text, 'DATAMATRIX', 64, 36, 75, 75, $style, 'N');

        $pdf->Line(12, 120, 198, 120, $lineStyle);
        $pdf->Line(198, 250, 198, 120, $lineStyle);
        $pdf->Line(12, 250, 198, 250, $lineStyle);
        $pdf->Line(12, 250, 12, 120, $lineStyle);
        $pdf->Line(12, 170, 157, 170, $lineStyle);
        $pdf->Line(12, 215, 157, 215, $lineStyle);
        $pdf->Line(157, 250, 157, 120, $lineStyle);
        
        $pdf->SetFont($roman, '', 15);
        $pdf->Text(170, 113, 'U2.00.0');

        //sender
        $pdf->StartTransform();
        $pdf->SetFont($roman, '', 14);
        $x = 195;
        $y = 122;
        $space = 6;
        $pdf->Rotate(-90, $x, $y);

        $this->unicode2ascii($this->sender);
        
        $text = 'Sender: Customer ID: ' . $barcode_text[2] . '      ' . 'Contact ID: ' . $barcode_text[3];
        $pdf->Text($x, $y, $text);
        $pdf->Text($x, $y += $space, $this->sender['company_name']);
        if($this->sender['company_name2']) $pdf->Text($x, $y += $space, $this->sender['company_name2']);
        $pdf->Text($x, $y += $space, $this->sender['street_name'] . ' ' . $this->sender['street_number']);
        $pdf->Text($x, $y += $space, $this->sender['country'] . '     ' . $this->sender['zip'] . '      ' . $this->sender['city']);
        $pdf->StopTransform();

        //consignee
        $x = 14;
        $y = 170;
        $pdf->SetFont($roman, '', 16);
        $pdf->Text($x + 85, $y, 'Cust-Ref: ' . $this->gls_сustomer_number);
        
        $pdf->SetFont($robo_bold, '', 30);
        $pdf->Text($x, $y, $barcode_text[10]);
        $pdf->SetFont($robo_norm, '', 26);
//        $pdf->SetFont($roman, '', 24);
        $pdf->Text($x, $y += 12, $barcode_text[11]);
        $pdf->SetFont($robo_bold, '', 30);
        $pdf->Text($x, $y += 9, $barcode_text[13] . (isset($barcode_text[14]) ? ' ' . $barcode_text[14] : ''));
        $pdf->SetFont($robo_bold, '', 26);
        $country_code = CountryToCountryCode($this->auction_params->get('country_shipping'));
        $pdf->Text($x, $y += 12, $country_code . '     ' . $barcode_text[6] . '    ' . $barcode_text[15]);

        $x = 14;
        $y = 216;
        $space = 6.6;
        $pdf->SetFont($roman, '', 16);
        $pdf->Text($x, $y, 'Contact: ' . $barcode_text[10]);
        $pdf->Text($x, $y += $space, 'Phone: ' . $barcode_text[16]);
        $pdf->Text($x, $y += $space, 'Note: ');
        $pdf->Text($x, $y += $space, 'Ref-No: ' . $barcode_text[17]);

         if($this->isCod()) {
            $x = 14;
            $y = 120;
            $code = explode('^', trim($barcode_text[20]));
            $pdf->SetFont($robo_bold, '', 35);
            $pdf->Text($x, $y, 'CASH-SERVICE');
            $pdf->SetFont($roman, '', 18);
            $pdf->Text($x, $y += 20, 'Verwendungszweck:');
            $pdf->Text($x, $y += 8, $code[3]);
        }

        $smarty = new \Smarty();
        require_once ROOT_DIR . '/plugins/function.barcodeurl.php';
        
        $url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}";
        
        $barcode = $url . smarty_function_barcodeurll([
                                'number' => $this->ref_number,
                                'barwidth' => 2,
                                'height' => 50], $smarty);
        $pdf->Image($barcode, 55, 257, 100);

        
//        $pdf->Output('label.pdf', 'I');
        return $pdf->Output('', 'S');
    }

    private function trackAndTrace(){
        $url = 'http://www.gls-group.eu/276-I-PORTAL-WEB/dLink.jsp?';
        $rf = '98881129254'; // gls parcel no
        $crf = $this->auction_params->data->auction_number . '/' . $this->auction_params->data->txnid; // customer ref 
        $key = md5($this->track_and_trace_user . $rf . $crf . $this->gls_сustomer_number . $this->track_and_trace_password);
        $params = [
            'un' => $this->track_and_trace_user,
            'key' => $key,
            'rf' => $rf,
            'crf' => $crf,
            'no' => $this->gls_сustomer_number,
            'lc' => 'en',
        ];
        $url = $url . http_build_query($params);
        $res = file_get_contents($url);
    }

}