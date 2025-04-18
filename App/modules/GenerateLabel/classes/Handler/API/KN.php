<?php
namespace label\Handler\API;

require_once 'lib/Order.php';
require_once 'lib/Article.php';
require_once 'Smarty.class.php';
require_once "Image/Barcode.php";

use label\Config;
use label\Handler\HandlerAbstract;

class KN extends HandlerAbstract
{
    private $ftp_server = 'ftp.kuehne-nagel.com';
    private $ftp_user_name = 'edibelia';
    private $ftp_user_pass = 'PsXxpj75o0';
    private $CustomerNumber = '2021339';
    private $timestamp;
    private $shipping_number;

    public function __construct()
    {
        if(strpos($_SERVER['HTTP_HOST'], 'dev.') || strpos($_SERVER['HTTP_HOST'], 'heap.')){
            $this->ftp_user_name = 'tstbelia';
            $this->ftp_user_pass = 'uJGFyvihFB';
        }
        $this->timestamp = time();
    }

    /**
     * @param bool $continue Flag of mass label creation
     * 
     */
    public function action($continue = false)
    {
        $this->shipping_number = $this->getNVE();
        
        $allorder = \Order::listAll($this->request_params['DB'], $this->request_params['DB'], $this->auction_params->get('auction_number'), $this->auction_params->get('txnid'), 1, $this->auction_params->getMyLang(), '0,1', 1);

        $realOrderItems = array();
        foreach ($allorder as $item) {
            if ($item->article_id != 0 && $item->admin_id == 0) {
                $article = new \Article($this->request_params['DB'], $this->request_params['DB'], $item->article_id);
                $item->parcels = $article->parcels;
                $realOrderItems[] = $item;
            }
        }
        
        $xml = $this->getXML($realOrderItems);
//        echo '<pre>' . print_r(htmlentities($xml), TRUE) . '</pre>'; exit;
        $pdf = $this->generatePDFlabel();
        
        $fp = fopen('php://temp', 'r+');
        fwrite($fp, $xml);
        rewind($fp);

        $remote_fname = "orders_" . date("d-m-Y") . "_" . date("H-i-s") . ".txt";
        $upload_folder = 'pub/inbound';

        $ftp_link = "ftp://{$this->ftp_user_name}:{$this->ftp_user_pass}@{$this->ftp_server}/$upload_folder/$remote_fname";
        if (file_put_contents($ftp_link, $xml)) {
            file_put_contents($ftp_link . '.ok', '');
            $this->saveLabel($this->shipping_number, $pdf);
            header ("Content-type: application/pdf");
            header("Content-disposition: inline; filename=label.pdf");
            die($pdf);
        } 
        else {
            echo "There was a problem while uploading file $remote_fname to Kuehne + Nagel";
        }

        exit;
    }

    /**
     * @return string PDF file source
     */
    private function generatePDFlabel(){
        $smarty = new \Smarty();
        $sender = array(
            'company_name' => 'Beliani DE GmbH',
            'street_name' => 'Seeweg',
            'street_number' => '1',
            'zip' => '17291',
            'country' => 'Germany',
            'city' => 'Grünow',
        );

        $company = str_replace('&', '', $this->auction_params->get('company_shipping'));
        if (strlen($company) > 50) {
            $company = substr($company, 0, 47) . '...';
        }
        if(empty(trim($company))) $company = " ";
        
        $recipient = array(
            'company' => $company,
            'name' => $this->auction_params->get('firstname_shipping') . ' ' . $this->auction_params->get('name_shipping'),
            'street' => $this->auction_params->get('street_shipping'),
            'house' => $this->auction_params->get('house_shipping'),
            'zip' => $this->auction_params->get('zip_shipping'),
            'city' => $this->auction_params->get('city_shipping'),
            'country' => $this->auction_params->get('country_shipping'),
        );
        
        $smarty->assign('barcode', $this->shipping_number);
        
        // get route
        $csvFile = file(dirname(__FILE__) . '/kn_route.csv');
        $csv = [];
        foreach ($csvFile as $line) {
            $csv[] = str_getcsv($line, ';');
        }
        $route = [];
        foreach ($csv as $item) {
            $route[$item[0]][] = array(
                'zip_start' => $item[1],
                'zip_end' => $item[2],
                'route' => $item[3],
            );
        }
        $zip = str_replace(array('-', ' '), '', $this->auction_params->get('zip_shipping'));
        $countryCode = countryToCountryCode($this->auction_params->get('country_shipping'));
        $route_code = false;
        if(count($route[$countryCode])){
            foreach ($route[$countryCode] as $item) {
                if($zip >= $item['zip_start'] && $zip <= $item['zip_end']){
                    $route_code = $item['route'];
                    break;
                }
            }
        }
        
        $smarty->assign('route_code', $route_code);
        $smarty->assign('sender', $sender);
        $smarty->assign('recipient', $recipient);
        $smarty->assign('date', date("d.m.Y", $this->timestamp));

        $shipping_method_id = $this->request_params['method']->get('shipping_method_id');
        $tpl = $this->request_params['DB']->getOne('select labels from shipping_method where shipping_method_id=' . $shipping_method_id);
        
        $html = $smarty->fetch($_SERVER['DOCUMENT_ROOT'] . "labels/" . $tpl);

        $mpdf = new \mPDF($mode = '', $format = 'A4-L', $default_font_size = 0, $default_font = 'arial');
        $mpdf->writeHTML($html);
        
        return $mpdf->Output('', 'S');
    }

    /**
     * @param array $realOrderItems Order items
     *
     * @return string XML file source 
     */
    private function getXML($realOrderItems)
    {
        $date = date("Y-m-d", $this->timestamp);
        $time = date("H:i:s", $this->timestamp) . date("P", $this->timestamp);

        $CurrencyCode = siteToSymbol($this->auction_params->get('siteid'));

        $invoice = $this->auction_params->getMyInvoice();
        $openAmount = number_format($invoice->get("open_amount"), 2, '.', '');

        $personName = $this->auction_params->get('firstname_shipping') . ' ' . $this->auction_params->get('name_shipping');
        $countryCode = countryToCountryCode($this->auction_params->get('country_shipping'));
        
        $weightTotal = 0;
        foreach ($realOrderItems as $item) {
            $weightTotal += $item->parcels[0]->weight_parcel * $item->quantity;
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
<TransportOrderExtFO xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\">
  <Envelope>
    <SenderIdentification>BELIANI</SenderIdentification>
    <ReceiverIdentification>ZIPPHH0</ReceiverIdentification>
    <MessageType>ALKUND</MessageType>
    <MessageVersion>01.20</MessageVersion>
    <EnvelopeIdentification></EnvelopeIdentification>
    <TransmissionDateTime>
      <Date>{$date}</Date>
      <Time>{$time}</Time>
    </TransmissionDateTime>
  </Envelope>
  <Message>
    <TransportOrderHeader>
      <CustomerNumber>{$this->CustomerNumber}</CustomerNumber>
    </TransportOrderHeader>
    <TransportOrderShipment>
      <TransportOrderPosition>1</TransportOrderPosition>
      <ShipmentNumber>" . $this->shipping_number . "</ShipmentNumber>
      <ShipmentDate>
          <Date>{$date}</Date>
          <Time>{$time}</Time>
      </ShipmentDate>
      <TermsOfDelivery>
        <TermsOfDeliveryCode>05</TermsOfDeliveryCode>
        <TermsOfDeliveryText>FREI HAUS</TermsOfDeliveryText>
        <TermsOfDeliveryCity>Grünow</TermsOfDeliveryCity>
      </TermsOfDelivery>
      <ValueOfGoods>
        <Value>{$invoice->get('total_price')}</Value>
        <CurrencyCode>{$CurrencyCode}</CurrencyCode>
      </ValueOfGoods>\n";

        if ($this->isCOD()) {
            $xml .= "<CashOnDelivery>
        <CODCode>58</CODCode>
        <Value>{$openAmount}</Value>
        <CurrencyCode>{$CurrencyCode}</CurrencyCode>
      </CashOnDelivery>\n";
        }

        $xml .= "<PickUpDate>
        <PickUpStart>
            <Date>{$date}</Date>
            <Time>{$time}</Time>
        </PickUpStart>
        <PickUpEnd>
            <Date>{$date}</Date>
            <Time>{$time}</Time>
        </PickUpEnd>
      </PickUpDate>
      <ShipmentReference>{$this->auction_params->get('auction_number')}</ShipmentReference>
      <ShipperAddress>
        <Name1>Lagerhaus Prologistics</Name1>
        <Street1>Seeweg 1</Street1>
        <Country>DE</Country>
        <Zip>17291</Zip>
        <City>Grünow</City>
        <CommunicationType>TEL</CommunicationType>
        <Communication>+4903420545052</Communication>
      </ShipperAddress>
      <ConsigneeAddress>
        <Name1>{$personName}</Name1>
        <Name2></Name2>
        <Street1>{$this->auction_params->get('street_shipping')} {$this->auction_params->get('house_shipping')}</Street1>
        <Street2></Street2>
        <Country>{$countryCode}</Country>
        <Zip>{$this->auction_params->get('zip_shipping')}</Zip>
        <City>{$this->auction_params->get('city_shipping')}</City>
        <ContactPerson>{$personName}</ContactPerson>
        <CommunicationType>TEL</CommunicationType>
        <Communication>{$this->auction_params->get('tel_shipping')}</Communication>
        <CommunicationType>EMA</CommunicationType>
        <Communication>{$this->auction_params->get('email_shipping')}</Communication>
      </ConsigneeAddress>
      <AdditionalInformation>
        <DeliveryInstructionCoded>
          <DeliveryInstructionsCode>01</DeliveryInstructionsCode>
          <DeliveryInstructionsText>{$this->auction_params->get('tel_shipping')}</DeliveryInstructionsText>
        </DeliveryInstructionCoded>
        <DeliveryInstructionCoded>
          <DeliveryInstructionsCode>02</DeliveryInstructionsCode>
          <DeliveryInstructionsText>{$this->auction_params->get('cel_shipping')}</DeliveryInstructionsText>
        </DeliveryInstructionCoded>
        <DeliveryInstructionFreeText></DeliveryInstructionFreeText>
      </AdditionalInformation>\n";
        
      /*  for ($i = 0; $i < count($realOrderItems); $i++) {
            $xml .= "
           <ShipmentItem>
            <ShipmentItemCounter>" . ($i + 1) . "</ShipmentItemCounter>
            <NumberOfPackages>
              <Value>1</Value>
              <PackageType>FP</PackageType>
            </NumberOfPackages>
            <GrossWeight>
              <Value>" . round($realOrderItems[$i]->parcels[0]->weight_parcel) . "</Value>
              <MeasureUnit>KGM</MeasureUnit>
            </GrossWeight>
            <Content>{$realOrderItems[$i]->name}</Content>
            <PackageBarcodes>
                <BarcodeQualifier>NVE</BarcodeQualifier>
                <Barcode>" . $this->getNVE() . "</Barcode>
            </PackageBarcodes>
          </ShipmentItem>\n";
        }
      
      $xml .= "
      <ShipmentTotal>
        <TotalShipmentItem>" . count($realOrderItems) . "</TotalShipmentItem>
        <TotalShipmentPackages>" . count($realOrderItems) . "</TotalShipmentPackages>
        <TotalShipmentGrossWeight>
          <Value>" . round($weightTotal) . "</Value>
          <MeasureUnit>KGM</MeasureUnit>
        </TotalShipmentGrossWeight>
      </ShipmentTotal>
      
    </TransportOrderShipment>
    <TransportOrderTotal>
      <TotalTransportOrderShipments>1</TotalTransportOrderShipments>
      <TotalTransportOrderShipmentItems>" . count($realOrderItems) . "</TotalTransportOrderShipmentItems>
      <TotalTransportOrderPackages>" . count($realOrderItems) . "</TotalTransportOrderPackages>
      <TotalTransportOrderGrossWeight>
        <Value>" . round($weightTotal) . "</Value>
        <MeasureUnit>KGM</MeasureUnit>
      </TotalTransportOrderGrossWeight>\n";*/

            $xml .= "
           <ShipmentItem>
            <ShipmentItemCounter>1</ShipmentItemCounter>
            <NumberOfPackages>
              <Value>1</Value>
              <PackageType>FP</PackageType>
            </NumberOfPackages>
            <GrossWeight>
              <Value>" . round($weightTotal) . "</Value>
              <MeasureUnit>KGM</MeasureUnit>
            </GrossWeight>
            <Content>Order #" . $this->auction_params->get('auction_number') . "</Content>
            <PackageBarcodes>
                <BarcodeQualifier>NVE</BarcodeQualifier>
                <Barcode>" . $this->shipping_number . "</Barcode>
            </PackageBarcodes>
          </ShipmentItem>\n";

        $xml .= "
      <ShipmentTotal>
        <TotalShipmentItem>1</TotalShipmentItem>
        <TotalShipmentPackages>1</TotalShipmentPackages>
        <TotalShipmentGrossWeight>
          <Value>" . round($weightTotal) . "</Value>
          <MeasureUnit>KGM</MeasureUnit>
        </TotalShipmentGrossWeight>
      </ShipmentTotal>
      
    </TransportOrderShipment>
    <TransportOrderTotal>
      <TotalTransportOrderShipments>1</TotalTransportOrderShipments>
      <TotalTransportOrderShipmentItems>1</TotalTransportOrderShipmentItems>
      <TotalTransportOrderPackages>1</TotalTransportOrderPackages>
      <TotalTransportOrderGrossWeight>
        <Value>" . round($weightTotal) . "</Value>
        <MeasureUnit>KGM</MeasureUnit>
      </TotalTransportOrderGrossWeight>\n";

        if ($this->isCod()) {
            $xml .= "<TotalTransportOrderCashOnDelivery>
        <CODCode>58</CODCode>
        <Value>{$openAmount}</Value>
        <CurrencyCode>{$CurrencyCode}</CurrencyCode>
      </TotalTransportOrderCashOnDelivery>\n";
        }

        $xml .= "</TransportOrderTotal>
        </Message>
        </TransportOrderExtFO>";

        return $xml;
    }
    
    
    private function getNVE(){
        $prefix = '3404918760071';
        $rand = rand(7201, 9200);
        $tn_tmp = $prefix . $rand;
        $tn = '00' . $tn_tmp . $this->checksumModulo10($tn_tmp);

        $q = "select id from auction_label where tracking_number='$tn'";
        while ($this->request_params['DB']->getOne($q)) {
            $tn = $this->getNVE();
        }

        return $tn;
     }

    private function checksumModulo10($tn)
    {
        $arr = str_split($tn);
        $sum = 0;
        $last_op_index = count($arr) - 1;
        $divide = $last_op_index % 2 == 0;
        for ($i = $last_op_index; $i >= 0; $i--) {
            if ($i % 2 == $divide) {
                $sum += $arr[$i] * 1;

            } else {
                $sum += $arr[$i] * 3;
            }
        }
        $nearest10 = $this->roundUpToAny($sum);
        $checksum = $nearest10 - $sum;

        return $checksum;
    }

    private function roundUpToAny($n, $x = 10)
    {
        return (round($n) % $x === 0) ? round($n) : round(($n + $x / 2) / $x) * $x;
    }

}