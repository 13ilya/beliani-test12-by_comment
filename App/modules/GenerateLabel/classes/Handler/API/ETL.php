<?php

namespace label\Handler\API;

require_once 'lib/Order.php';
require_once 'lib/Article.php';

use label\Config;
use label\Handler\HandlerAbstract;

class ETL extends HandlerAbstract
{
    private $url = "https://gclient.etlogistik.com/GService/GService.svc";
//    private $url = "https://gclient.geis.pl/GService/GService.svc";
    private $customerCode = '47000043';
    private $password = '21FB6CE8A8';
    private $language = 'PL';
    private $format = 4; // 1 - PDF; 4 - PNG 
    private $timestamp;
    private $DistributionChannel = '2'; // 1-parcel, 2-cargo
    private $holidays;
    private $pdf;
    private $isTest;
    private $weight_total = 0;
    private $height_total = 0;
    private $length_total = 0;
    private $width_total = 0;
    private $order_items_count = 0;
    private $articles = [];


    public function __construct()
    {
        $this->timestamp = time();
        $this->isTest = strpos($_SERVER['HTTP_HOST'], 'dev.') || strpos($_SERVER['HTTP_HOST'], 'heap.');
//        $this->isTest = false;
        if($this->isTest) {
          $this->url = "https://gclient.geis.pl/GServiceTest/GService.svc";
//            $this->url = "https://gclient.etlogistik.com/GServiceTest/GService.svc";
            $this->customerCode = '22122805';
            $this->password = "c332f300-4e62-4880-b7a5-4995c2585122";
            $this->DistributionChannel = 1;
        }
        
        // in case of holidays switch to next working day
        $this->holidays = [
            '17.04.2017',
            '01.05.2017',
            '03.05.2017',
            '15.06.2017',
            '15.08.2017',
            '01.11.2017',
            '25.12.2017',
            '26.12.2017',
            '06.01.2017',
        ];
    }

    public function action($continue = false)
    {
        /**
         * 1. CreatePickUp() for current date. Response: [Status] => Inserted
         * 2. InsertExport(). Response: [Status] => Inserted, [PackNumber] => 1075000009173
         * 3. GetLabel(ShipmentNumber = 1075000009173). Response: [Status] => Processed, [Data] => PDF
         *
         * CreatePickUp, InsertExport, ServiceList, AddServiceList, GetLabel
         */

        $allorder = \Order::listAll($this->request_params['DB'], $this->request_params['DB'],
            $this->auction_params->get('auction_number'), $this->auction_params->get('txnid'), 1,
            $this->auction_params->getMyLang(), '0,1', 1);
        $articles = [];

        foreach ($allorder as $key => $order) {
            if (
                $allorder[$key]->article_id != 0 && 
                $allorder[$key]->admin_id == 0 && 
                !in_array($order->article_id, $this->articlesToExclude())
            ) {
                $article = new \Article($this->request_params['DB'], $this->request_params['DB'], $order->article_id);
                if(
                    stripos($article->data->title, 'carton') !== false || 
                    stripos($article->data->title, 'cartoon') !== false 
                ){
                    continue;
                }

                $new_article = new \stdClass();
                $new_article->id = $article->id;
                $new_article->CountItems = ceil($order->quantity / $article->data->items_per_shipping_unit);
                foreach($article->parcels as $parcel){
                    $new_article->weight += $parcel->weight_parcel * $order->quantity;
                    $new_article->height += $parcel->dimension_h > 100 ? $parcel->dimension_h : 100;
                    $new_article->length += $parcel->dimension_l > 100 ? $parcel->dimension_l : 100;
                    $new_article->width += $parcel->dimension_w > 100 ? $parcel->dimension_w : 100;
                }

                $articles[$order->id] = $new_article;
                $this->weight_total += $new_article->weight;
                $this->height_total += $new_article->height;
                $this->length_total += $new_article->length;
                $this->width_total += $new_article->width;
                $this->order_items_count += $new_article->CountItems;
            }
        }

        $labels_ll = $this->request_params['number_of_labels_ll'];
        if(!$labels_ll){
            $this->articles = $articles;
        }
        else {
            for($i = 0; $i < $labels_ll; $i++){
                $new_article = new \stdClass();
                $new_article->id = $i;
                $new_article->weight = $this->weight_total / $labels_ll;
                $new_article->height = $this->height_total / $labels_ll;
                $new_article->length = $this->length_total / $labels_ll;
                $new_article->width = $this->width_total / $labels_ll;
                $new_article->CountItems = 1;
                $this->articles[] = $new_article;
            }
        }

        if($this->weight_total > 50) $this->DistributionChannel = 2;

        $auc_num = $this->auction_params->get('auction_number') . "/" . $this->auction_params->get('txnid');
        $error = '<p>Auction ' . $auc_num . ', shipping method ' . $this->request_params['method']->get('company_name') . '. Error description:</p>';

        $CountItems = 1;
        $response = $this->CreatePickUp($CountItems);

        if ($response->Body->CreatePickUpResponse->CreatePickUpResult->ErrorCode == '0'
        || $response->Body->CreatePickUpResponse->CreatePickUpResult->ErrorCode == '3000') {
            $numbers = [];
            $labels = [];
            $output = '';
            
            $response = $this->InsertExport();
            if ($response->Body->InsertExportResponse->InsertExportResult->Status == 'Inserted') {
                if(count($response->Body->InsertExportResponse->InsertExportResult->ResponseObject->MergedPackNumbers->string)){
                    $numbers = json_encode($response->Body->InsertExportResponse->InsertExportResult->ResponseObject->MergedPackNumbers->string);
                    $numbers = json_decode($numbers, 1);
                }
                if($response->Body->InsertExportResponse->InsertExportResult->ResponseObject->PackNumber){
                    $merged_number = json_encode($response->Body->InsertExportResponse->InsertExportResult->ResponseObject->PackNumber);
                    $merged_number = json_decode($merged_number, 1);
                    array_unshift($numbers, $merged_number[0]);
                }

                $counter = 0;
                foreach ($numbers as $index => $PackNumber) {
                    if($index > 0){
                        if(strpos($PackNumber, $numbers[0]) !== false){
                            continue;
                        }
                    }
                    
                    $response = $this->GetLabel($PackNumber);
                    if ($response->Body->GetLabelResponse->GetLabelResult->Status == 'Processed' && count($this->pdf)) {
                        foreach ($this->pdf as $pdf) {
                            $source = ($this->format == 4) ? $this->processImage($pdf) : base64_decode($pdf);
                            $id = $this->saveLabel($PackNumber, $source);
                            $output .= "<li><a href='doc.php?auction_label=$id' target='_blank'>Download label #" . $counter++ . "</a></li>";
                        }
                    }
                }
                // display label
                if (count($numbers) > 1) {
                    die($output);
                }
                // display dl links
                elseif(count($numbers) == 1) {
                    $content_type = $this->format == 4 ? "image/bmp" : "application/pdf";
                    $ext = $this->format == 4 ? "bmp" : "pdf";
                    header("Content-Type: $content_type");
                    header("Content-disposition: inline; filename=label.$ext");
                    echo $source;
                }
            } 
            else {
                $error .= '<pre>' . print_r($response, true) . '</pre>';
                if (!$continue) {
                    die($error);
                } else {
                    $_SESSION['messages']['errors'][] = $error;

                    return false;
                }
            }
        } else {
            echo '<pre>' . print_r($response, true) . '</pre><hr>';
            die();
        }
    }

    private function processImage($pdf)
    {
        $decoded = base64_decode($pdf);
        $im = new \Imagick();
        $im->readimageblob($decoded);

        // Add 'ETL' mark
        if ($this->DistributionChannel == 1) {
            $draw = new \ImagickDraw();
            $draw->setFillColor('black');
            $draw->setFontSize(50);
            $im->annotateImage($draw, 775, 1000, 0, 'ETL');
        }

        $im->trimImage(0);
        $im->rotateImage(new \ImagickPixel('none'), 90);
        $im->setImagePage(0, 0, 0, 0);
        $im->scaleimage(
            $im->getImageWidth() * 2,
            $im->getImageHeight() * 2
        );
        $im->borderImage('white', 225, 225);

        return $im;
    }

    private function soapResponseToXml($response)
    {
        $xml_el = new \SimpleXMLElement((string)$response);
        $namespaces = $xml_el->getNamespaces(true);
        $ns_arr = array();
        foreach ($namespaces as $key => $value) {
            if (!empty($key)) {
                $ns_arr[] = $key . ':';
            }
        }
        $res_clean = str_replace($ns_arr, '', $response);
        $xml_el = simplexml_load_string($res_clean);

        return $xml_el;
    }

    private function curlRequest($content, $location, $action)
    {
        $headers = [
            "Content-type: text/xml; charset=\"utf-8\"",
            "SoapAction: $action",
            "Content-length: " . strlen($content),
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $location);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

        $output = curl_exec($ch);

        if(!$output){
            $info = curl_getinfo($ch);
            echo 'Request fails. <pre>' . print_r($info, TRUE) . '</pre>';
            die();
        }
        
        return $output;
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
            $date = date('c', $next_ts);
        } else {
            $date = false;
        }
        
        return $date;
    }

    private function CreatePickUp($CountItems)
    {
        $xml = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/" xmlns:gser="http://schemas.datacontract.org/2004/07/GService.Manager">
   <soapenv:Header/>
   <soapenv:Body>
      <tem:CreatePickUp>
         <tem:Request>
            <gser:Header>
               <gser:CustomerCode>' . $this->customerCode . '</gser:CustomerCode>
               <gser:Language>' . $this->language . '</gser:Language>
               <gser:Password>' . $this->password . '</gser:Password>
            </gser:Header>
            <gser:RequestObject>
               <gser:Contact>
                  <gser:Email>' . $this->request_params['method']->get('email') . '</gser:Email>
                  <gser:FullName>' . $this->request_params['method']->get('name') . '</gser:FullName>
                  <gser:Phone>' . str_replace(' ', '', $this->request_params['method']->get('phone')) . '</gser:Phone>
               </gser:Contact>
               <gser:CountItems>' . $CountItems . '</gser:CountItems>
               <gser:DateFrom>' . $this->getWorkingDay() . '</gser:DateFrom>
               <gser:DistributionChannel>' . $this->DistributionChannel . '</gser:DistributionChannel>
               <gser:TotalWeight>' . round($this->weight_total, 2) . '</gser:TotalWeight>
            </gser:RequestObject>
         </tem:Request>
      </tem:CreatePickUp>
   </soapenv:Body>
</soapenv:Envelope>';

//        return $xml;
        $res = $this->curlrequest($xml, $this->url, 'http://tempuri.org/IGService/' . 'CreatePickUp');

        return $this->soapResponseToXml($res);
    }

    private function InsertExport() {
        $countryDelivery = countryToCountryCode($this->auction_params->get('country_shipping'));
        $countrySender = countryToCountryCode($this->request_params['method']->get('country_name'));
        $zip = str_replace(array('-', ' '), '', $this->auction_params->get('zip_shipping'));
        
        $phone = $this->auction_params->get('tel_shipping_formatted');
        if(strlen($this->auction_params->get('tel_shipping_formatted')) < strlen($this->auction_params->get('cel_shipping_formatted'))){
            $phone = $this->auction_params->get('cel_shipping_formatted');
        }
        $phone = trim(str_replace(' ', '', $phone));
        
//        $phone = strlen($this->auction_params->get('tel_shipping')) > strlen($this->auction_params->get('cel_shipping')) ? $this->auction_params->get('tel_shipping') : $this->auction_params->get('cel_shipping');

//        $countries = $this->request_params['DB']->getAssoc("SELECT country.code, country.* FROM country");
//        $prefix = '+' . $countries[$this->auction_params->get('tel_country_code_shipping')]['phone_prefix'];
//        $phone = $prefix . str_replace(array('-', ' '), '', $phone);

        $xml = "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:tem=\"http://tempuri.org/\" xmlns:gser=\"http://schemas.datacontract.org/2004/07/GService.Manager\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\">
   <soapenv:Header/>
   <soapenv:Body>
      <tem:InsertExport>
         <tem:Request>
            <gser:Header>
               <gser:CustomerCode>$this->customerCode</gser:CustomerCode>
               <gser:Language>$this->language</gser:Language>
               <gser:Password>$this->password</gser:Password>
            </gser:Header>
            <gser:RequestObject>
               <gser:CoverAddress xsi:nil=\"true\" />
               <gser:DeliveryAddress>
                  <gser:City>{$this->auction_params->get('city_shipping')}</gser:City>
                  <gser:Country>$countryDelivery</gser:Country>
                  <gser:Name>{$this->auction_params->get('firstname_shipping')}</gser:Name>
                  <gser:Name2>{$this->auction_params->get('name_shipping')}</gser:Name2>
                  <gser:Street>{$this->auction_params->get('street_shipping')} {$this->auction_params->get('house_shipping')}</gser:Street>
                  <gser:ZipCode>$zip</gser:ZipCode>
               </gser:DeliveryAddress>
               <gser:DeliveryContact>
                  <gser:Email>{$this->auction_params->get('email_shipping')}</gser:Email>
                  <gser:FullName>{$this->auction_params->get('firstname_shipping')} {$this->auction_params->get('name_shipping')}</gser:FullName>
                  <gser:Phone>$phone</gser:Phone>
               </gser:DeliveryContact>
               <gser:DistributionChannel>$this->DistributionChannel</gser:DistributionChannel>\n";

        if ($this->DistributionChannel == 2) {
            $xml .= "<gser:ExportItems>\n";
            foreach ($this->articles as $article) {
                $type = $this->getParcelType($article->weight, $article->length, $article->height, $article->width);
                $xml .= "<gser:ExportItem>
                     <gser:CountItems>{$article->CountItems}</gser:CountItems>
                     <gser:Description>{$article->name}</gser:Description>
                     <gser:Height>" . ($article->height ? $article->height / 100 : 0.01) . "</gser:Height>
                     <gser:Length>" . ($article->length ? $article->length / 100 : 0.01) . "</gser:Length>
                     <gser:Reference>{$article->id}</gser:Reference>
                     <gser:Type>$type</gser:Type>
                     <gser:Weight>" . ($article->weight ? $article->weight : 1) . "</gser:Weight>
                     <gser:Width>" . ($article->width ? $article->width / 100 : 0.01) . "</gser:Width>
                  </gser:ExportItem>\n";
            }
            $xml .= "</gser:ExportItems>";
        }
        
        $xml .= "<gser:ExportServices>\r\n";

        $shipping_method_id = $this->request_params['method']->get('shipping_method_id');
        $LabelsCount = $this->auction_params->getLabelsCount($shipping_method_id);

        // COD
        if ($this->auction_params->get('payment_method') == '2' && !$LabelsCount) {
            $invoice = $this->auction_params->getMyInvoice();
//            $openAmount = number_format($invoice->get("open_amount"), 2, '.', '');
            $openAmount = $invoice->get("open_amount");
            $currency = siteToSymbol($this->auction_params->get('siteid'));
            $seller_info = $this->request_params['seller'];

            $payment_id = str_replace(['-', ' '], '', $seller_info->get('bank_account'));
            $payment_id = strlen($payment_id) > 10 ? substr($payment_id, 0, 10) : $payment_id;

            $xml .= "<gser:ExportService>
                     <gser:Code>" . ($this->DistributionChannel == 1 ? '2' : '1002') . "</gser:Code> 
                     <gser:Parameter_1>$openAmount</gser:Parameter_1>
                     <gser:Parameter_2>$currency</gser:Parameter_2>
                     <gser:Parameter_3>$payment_id</gser:Parameter_3>
                     <gser:Parameter_4>{$seller_info->get('iban')}</gser:Parameter_4>
                     <gser:Parameter_5>{$seller_info->get('bic')}</gser:Parameter_5>
                  </gser:ExportService>\r\n";
        }

        if ($this->DistributionChannel == 2){
            $xml .= "<gser:ExportService>
                        <gser:Code>1091</gser:Code><!-- EML --> 
                        <gser:Parameter_1>{$this->auction_params->get('email_shipping')}</gser:Parameter_1>
                        <gser:Parameter_2 />
                        <gser:Parameter_3 />
                        <gser:Parameter_4 />
                        <gser:Parameter_5 />
                      </gser:ExportService>\r\n";
        }
        
        $xml .= "</gser:ExportServices>\r\n";

        $xml .= "<gser:MergedPackages>";
        //        $xml .= "<gser:MergedOrders>";

        $keys = array_keys($this->articles);
        foreach ($this->articles as $key => $article) {
            if (count($this->articles) > 1 && $key == $keys[0]) {
                continue;
            }
            $weight = $article->weight;
            if (!$weight) $weight = 1; 

            $xml .= "<gser:MergedItem>
                        <gser:PackNumber />
                        <gser:Weight>" . $weight . "</gser:Weight>
                    </gser:MergedItem>\n";
        }
        //        $xml .= "</gser:MergedOrders>";
        $xml .= "</gser:MergedPackages>\n";
        //        $xml .= "<gser:MergedOrder>false</gser:MergedOrder>";
        $xml .= "<gser:MergedShipment>" . (count($this->articles) > 1 ? "true" : "false") . "</gser:MergedShipment>";

        $first_article = reset($articles);
        $weight_single = $first_article->parcels[0]->weight_parcel * $first_article->quantity;
        $weight_single = round($weight_single, 2);

        $xml .= "<gser:Note />
               <gser:NoteDriver />
               <gser:PickUpDate>" . $this->getWorkingDay() . "</gser:PickUpDate>
               <gser:SenderAddress>
                    <gser:City>{$this->request_params['method']->get('city')}</gser:City>
                    <gser:Country>$countrySender</gser:Country>
                    <gser:Name>{$this->request_params['method']->get('name')}</gser:Name>
                    <gser:Name2 />
                    <gser:Street>{$this->request_params['method']->get('street')}</gser:Street>
                    <gser:ZipCode>{$this->request_params['method']->get('zip')}</gser:ZipCode>
                </gser:SenderAddress>
                <gser:SenderContact>
                    <gser:Email>{$this->request_params['method']->get('email')}</gser:Email>
                    <gser:FullName>{$this->request_params['method']->get('name')}</gser:FullName>
                   <gser:Phone>{$this->request_params['method']->get('return_phone')}</gser:Phone>
               </gser:SenderContact>
               <gser:Weight>" . ($weight_single ? $weight_single : 1) . "</gser:Weight>
            </gser:RequestObject>
         </tem:Request>
      </tem:InsertExport>
   </soapenv:Body>
</soapenv:Envelope>";

//        echo '<pre>' . print_r(htmlentities($xml), TRUE) . '</pre><hr>'; 
//        die();

//        return $xml;
        $res = $this->curlrequest($xml, $this->url, 'http://tempuri.org/IGService/' . 'InsertExport');

        return $this->soapResponseToXml($res);
    }

    private function GetLabel($PackNumber)
    {
        $xml = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/" xmlns:gser="http://schemas.datacontract.org/2004/07/GService.Manager">
   <soapenv:Header/>
   <soapenv:Body>
      <tem:GetLabel>
         <tem:Request>
            <gser:Header>
               <gser:CustomerCode>' . $this->customerCode . '</gser:CustomerCode>
               <gser:Language>' . $this->language . '</gser:Language>
               <gser:Password>' . $this->password . '</gser:Password>
            </gser:Header>
            <gser:RequestObject>
               <gser:DistributionChannel>' . $this->DistributionChannel . '</gser:DistributionChannel>
               <gser:Format>' . $this->format . '</gser:Format>
               <gser:Position>1</gser:Position>
               <gser:ShipmentNumbers>
                  <gser:LabelItem>
                     <gser:ShipmentNumber>' . $PackNumber . '</gser:ShipmentNumber>
                  </gser:LabelItem>
               </gser:ShipmentNumbers>
            </gser:RequestObject>
         </tem:Request>
      </tem:GetLabel>
   </soapenv:Body>
</soapenv:Envelope>';

//        return $xml;
        $res = $this->curlrequest($xml, $this->url, 'http://tempuri.org/IGService/' . 'GetLabel');
        preg_match_all('~<a:Data>([\S\s]*?)<\/a:Data>~' . '', $res, $matches);
        if (isset($matches[1])) {
            $this->pdf = $matches[1];
        }

        return $this->soapResponseToXml($res);
    }


    private function ServiceList()
    {
        $xml = "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:tem=\"http://tempuri.org/\" xmlns:gser=\"http://schemas.datacontract.org/2004/07/GService.Manager\">
   <soapenv:Header/>
   <soapenv:Body>
      <tem:ServiceList>
         <tem:Request>
            <gser:Header>
               <gser:CustomerCode>$this->customerCode</gser:CustomerCode>
               <gser:Language>$this->language</gser:Language>
               <gser:Password>$this->password</gser:Password>
            </gser:Header>
            <gser:RequestObject/>
         </tem:Request>
      </tem:ServiceList>
   </soapenv:Body>
</soapenv:Envelope>";

        return $xml;
    }

    private function AddServiceList()
    {
        /**
         * COD - cache on delivery
         * VDL - доказательства поставки назад ??
         * POJ - декларация ценности
         * EMA - уведомление по электронной почте
         */

        $countryDelivery = countryToCountryCode($this->auction_params->get('country_shipping'));

        $xml = "<soapenv:Envelope xmlns:soapenv=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:tem=\"http://tempuri.org/\" xmlns:gser=\"http://schemas.datacontract.org/2004/07/GService.Manager\">
   <soapenv:Header/>
   <soapenv:Body>
      <tem:AddServiceList>
         <tem:Request>
            <gser:Header>
               <gser:CustomerCode>$this->customerCode</gser:CustomerCode>
               <gser:Language>$this->language</gser:Language>
               <gser:Password>$this->password</gser:Password>
            </gser:Header>
            <gser:RequestObject>
               <gser:DeliveryCountry>$countryDelivery</gser:DeliveryCountry>
               <gser:Service>21</gser:Service> <!-- 20 is the expedition and 21 is the orders -->
            </gser:RequestObject>
         </tem:Request>
      </tem:AddServiceList>
   </soapenv:Body>
</soapenv:Envelope>";

        return $xml;
    }

    private function getParcelType($weight, $length, $height, $width)
    {
        $dimensions = $length + $height + $width;
        $array_dimensions = array($length, $height, $width);
        //        $volume = $length * $height * $width;
        if (
            $weight < 30
            && $length < 200
            && $dimensions < 300
        ) {
            $type = 'CC'; // COLLI
        } elseif (
            $weight < 30
            && $dimensions < 400
            && max($array_dimensions) < 200
        ) {
            $type = 'GE'; // GABARYT
        } elseif (
            $weight < 30
            && ($length > 200 && $length < 300)
            && $dimensions < 380
        ) {
            $type = 'BU'; // WIĄZKA
        }
        /*elseif (
            $weight < 1000 &&
            ($array_dimensions[0] < 80 && $array_dimensions[1] < 120 && $array_dimensions[2] < 200)
        ) {
            $type = 'FP'; // EUROPALETA
        }*/

        $type = (!empty($type)) ? $type : 'CC';

        return $type;
    }

}

