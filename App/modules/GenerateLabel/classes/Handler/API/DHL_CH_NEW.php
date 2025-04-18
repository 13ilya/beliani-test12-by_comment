<?php

namespace label\Handler\API;

use label\Config;
use label\Handler\HandlerAbstract;


class DHL_CH_NEW extends HandlerAbstract
{
    private $url = 'https://xmlpi-ea.dhl.com/XMLShippingServlet';
    private $login = 'xmlBelianiG';
    private $password = 'BV1WhEaclp';
    private $ShipperAccountNumber = '213373500';
    private $format = 1; // 1-pdf, 4-png
    private $continue = 0;

    /**
     * @param bool $continue Flag for mass labels creation
     *
     * @return \Imagick|string
     */
    public function action($continue = false)
    {
        $this->continue = $continue;
        if($continue) $this->format = 1; // we need it sd PDF in order to merge few labels 
        
        $xml = $this->getRequestXML();
        $response = $this->sendRequest($xml);
        
//        echo htmlentities($xml) . "<hr>";
//        echo htmlentities($response) . "<hr>";
//        die();
 
        $regex = '~<OutputImage>([^<>]+)<\/OutputImage>~';
        preg_match($regex, $response, $pdf);
        
        if(empty($pdf[1])){
            $auc_num = $this->auction_params->get('auction_number') . "/" . $this->auction_params->get('txnid');
            $error = '<p>Auction ' . $auc_num . ', shipping method ' . $this->request_params['method']->get('company_name') . '. Error description:</p>';
            $error .= '<pre>' . print_r(htmlentities($response), TRUE) . '</pre>';
            if(!$continue){
                die($error);
            }
            else {
                $_SESSION['messages']['errors'][$auc_num] = $error;
                return false;
            }
        } 
        else {
            $regex = '~<AirwayBillNumber>(\d*)<\/AirwayBillNumber>~';
            preg_match($regex, $response, $billing);

            $pdf = base64_decode($pdf[1]);
            $source = ($this->format == 4) ? $this->processImage($pdf) : $this->processPDF($pdf);
            
            if (!empty($billing[1])) {
                $this->saveLabel($billing[1], $source);
            }
            
            if($continue){
                return $source;
            }

            $content_type = $this->format == 4 ? "image/png" : "application/pdf";
            $ext = $this->format == 4 ? "png" : "pdf";
            header("Content-Type: $content_type");
            header("Content-disposition: attachment; filename=label.$ext");
            echo $source;
        }
    }
    
    private function processPDF($source){
        require_once(ROOT_DIR . '/PDFMerger/fpdf/fpdf.php');

        $filename = ROOT_DIR . "/tmp/label_" . md5($source) . ".pdf";
        file_put_contents($filename, $source);

        $pdf = new \FPDI();
        $pageCount = $pdf->setSourceFile($filename);
        // we dont need last page
        for ($pageNo = 1; $pageNo <= $pageCount - 1; $pageNo++) {
            $tplIdx = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tplIdx);
            $mult = 1.63;

            // add a page
            $pdf->AddPage();
            $pdf->useTemplate($tplIdx, -27, -15, $size['w'] * $mult, $size['h'] * $mult);
        }
        
        unlink($filename);

        return $pdf->Output('', 'S');
    }
    

    /**
     * @param string $pdf Source file
     *
     * @return \Imagick
     */
    private function processImage($source){
        $im = new \Imagick();
        $im->setResolution(180, 180);
        $im->readimageblob($source);
        $im->resetIterator(); // we need first page only
        $im->trimImage(0);
        $im->borderImage('white', 60, 60);
        $im->setimageformat("png");
        $im->setIteratorIndex(1); // remove second page
        $im->removeImage();
//          $ima = $im->appendImages(true); # Combine multiple images into one, stacked vertically.
        
        return $im;
    }

    /**
     * @param string $xml
     *
     * @return mixed
     */
    private function sendRequest($xml)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->login:$this->password");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }

    /**
     * @return string
     */
    private function getRequestXML()
    {
        $MessageTime = date('c');
        $MessageReference = md5($MessageTime);
        $shipmentDate = date('Y-m-d');

        $company = str_replace('&', '', $this->auction_params->get('company_shipping'));
        if (strlen($company) > 35) {
            $company = substr($company, 0, 32) . '...';
        }
        if(empty(trim($company))){
            $company = "no";
        }

        $countryCode = countryToCountryCode($this->auction_params->get('country_shipping'));
        $method = $this->request_params['method'];
        $personName = $this->auction_params->get('firstname_shipping') . ' ' . $this->auction_params->get('name_shipping');
        $CurrencyCode = siteToSymbol($this->auction_params->get('siteid'));
        $CurrencyCode = $CurrencyCode == 'Fr.' ? 'CHF' : $CurrencyCode;
        $invoice = $this->auction_params->getMyInvoice();
        if(!empty($this->auction_params->get('offer_name'))){
            if(strlen($this->auction_params->get('offer_name')) >= 90){
                $desc = substr($this->auction_params->get('offer_name'), 0, 86) . '...';
            } else {
                $desc = $this->auction_params->get('offer_name');
            }
            $desc = strip_tags($desc);
        } else {
            $desc = 'DOM';
        }
        $shipping_method_id = $this->request_params['method']->get('shipping_method_id');
        $LabelsCount = $this->auction_params->getLabelsCount($shipping_method_id);

        $weight_total = 0;
        $quantity_total = 0;
        $parcels = [];
        $allorder = \Order::listAll($this->request_params['DB'], $this->request_params['DB'],
            $this->auction_params->get('auction_number'), $this->auction_params->get('txnid'), 1,
            $this->auction_params->getMyLang(), '0,1', 1);
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
                    unset($allorder[$key]);
                    continue;
                }
                $weight = $article->parcels[0]->weight_parcel * $order->quantity;
                if(!(int)$weight) $weight = 5;
                $weight_total += $weight;
                $quantity = ceil($order->quantity / $article->data->items_per_shipping_unit);
                $quantity_total += $quantity;
            }
        }
        $labels_qty = $this->request_params['number_of_labels_ll'] ? $this->request_params['number_of_labels_ll'] : 1;
        // produce amount of pages specified here
        // http://proloheap.prologistics.info/mobile.php?branch=pl&step=3&warehouse_id=107&ramp_id=11
        for($i = 0; $i < $labels_qty; $i++){
            $parcels[] = [ // produces 1/1 label
                'id' => $i + 1,
                'weight' => round($weight_total / $labels_qty, 2),
                'quantity' => 1,
            ];
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<req:ShipmentRequest xmlns:req=\"http://www.dhl.com\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.dhl.com ship-val-global-req.xsd\" schemaVersion=\"1.0\">
<Request>
    <ServiceHeader>
        <MessageTime>$MessageTime</MessageTime>
        <MessageReference>$MessageReference</MessageReference>
        <SiteID>{$this->login}</SiteID>
        <Password>{$this->password}</Password>
    </ServiceHeader>
</Request>
<RegionCode>EU</RegionCode>
<NewShipper>N</NewShipper>
<LanguageCode>en</LanguageCode>
<PiecesEnabled>Y</PiecesEnabled>
<Billing>
    <ShipperAccountNumber>$this->ShipperAccountNumber</ShipperAccountNumber>";
        // COD
        if ($this->auction_params->get('payment_method') == '2' && !$LabelsCount) {
            $xml .= "<ShippingPaymentType>R</ShippingPaymentType>";
            $xml .= "<BillingAccountNumber>$this->ShipperAccountNumber</BillingAccountNumber>";
        }
        else {
            $xml .= "<ShippingPaymentType>S</ShippingPaymentType>";
        }
        
$xml .= "</Billing>
<Consignee>
    <CompanyName>$company</CompanyName>
    <AddressLine>{$this->auction_params->get('street_shipping')}</AddressLine>
    <AddressLine>{$this->auction_params->get('house_shipping')}</AddressLine>
    <City>{$this->auction_params->get('city_shipping')}</City>
    <PostalCode>{$this->auction_params->get('zip_shipping')}</PostalCode>
    <CountryCode>$countryCode</CountryCode>
    <CountryName>{$this->auction_params->get('country_shipping')}</CountryName>
    <Contact>
        <PersonName>$personName</PersonName>
        <PhoneNumber>{$this->auction_params->get('tel_shipping')}</PhoneNumber>
        <Email>{$this->auction_params->get('email_shipping')}</Email>
    </Contact>
</Consignee>
<Commodity>
    <CommodityCode>{$this->auction_params->get('auction_number')}</CommodityCode>
</Commodity>
<Reference>
    <ReferenceID>{$this->auction_params->get('auction_number')}</ReferenceID>
</Reference>
<ShipmentDetails>
    <NumberOfPieces>$labels_qty</NumberOfPieces>
    <Pieces>";

        foreach ($parcels as $parcel) {
            $xml .= "
            <Piece>
                <PieceID>{$parcel['id']}</PieceID>
                <Weight>{$parcel['weight']}</Weight>
                <Width>1</Width>
                <Height>1</Height>
                <Depth>1</Depth>
            </Piece>";
        }
        
        $xml .= "
    </Pieces>
    <Weight>$weight_total</Weight>
    <WeightUnit>K</WeightUnit>
    <GlobalProductCode>N</GlobalProductCode>
    <LocalProductCode>N</LocalProductCode>
    <Date>$shipmentDate</Date>
    <Contents>$desc</Contents>
    <DimensionUnit>C</DimensionUnit>
    <IsDutiable>N</IsDutiable>
    <CurrencyCode>$CurrencyCode</CurrencyCode>
</ShipmentDetails>
<Shipper>
    <ShipperID>BELIANI</ShipperID>
    <CompanyName>Beliani GmbH</CompanyName>
    <AddressLine>Industriestrasse 26</AddressLine>
    <City>Däniken</City>
    <PostalCode>4658</PostalCode>
    <CountryCode>CH</CountryCode>
    <CountryName>SWITZERLAND</CountryName>
    <Contact>
        <PersonName>no</PersonName>
        <PhoneNumber>+41435082233</PhoneNumber>
        <Email>widmer@widmerinternational.com</Email>
    </Contact>
</Shipper>
<EProcShip>N</EProcShip>
<LabelImageFormat>PDF</LabelImageFormat>
</req:ShipmentRequest>
";

        return $xml;
    }

    /**
     * @param string $country
     *
     * @return string Country code
     */
    private function CountryToCountryCode($country)
    {
        global $dbr;
        $query = "SELECT `code` FROM country WHERE `name` = '$country'";

        return $dbr->getOne($query);
    }

}