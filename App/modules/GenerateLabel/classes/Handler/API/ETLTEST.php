<?php
namespace label\Handler\API;

use label\Config;
use label\Handler\HandlerAbstract;

class ETLTEST extends HandlerAbstract
{
    private $customerCode = '47000043';
    private $password = '1FB6CE8A8';
    private $language = 'PL';
    private $url = "https://gclient.etlogistik.com/GServiceTest/GService.svc";
    private $timestamp;
    private $DistributionChannel = '2';

    public function __construct()
    {
        $this->timestamp = time();
    }

    public function action($continue = false)
    {
//        $methodToCall = 'CreatePickUp';
        $methodToCall = 'InsertExport';
//        $methodToCall = 'ServiceList';
//        $methodToCall = 'AddServiceList';
//        $methodToCall = 'GetLabel';

        $xml = $this->$methodToCall();
//        echo '<pre>' . "\r\n" . print_r($xml, true) . "\r\n" . '</pre>';
        
        $res = $this->curlrequest($xml, $this->url, 'http://tempuri.org/IGService/' . $methodToCall);
        echo print_r(htmlentities($res), TRUE) . '<br>';
        
        $xml = $this->soapResponseToXml($res);
        echo '<pre>' . print_r($xml, TRUE) . '</pre>';
        
        exit;
    }
    
    private function soapResponseToXml($response){
        $xml_el = new \SimpleXMLElement((string) $response);
        $namespaces = $xml_el->getNamespaces(true);
        $ns_arr = array();
        foreach ($namespaces as $key => $value) {
            if(!empty($key)) $ns_arr[] = $key . ':';
        }
        $res_clean = str_replace($ns_arr, '', $response);
        $xml_el = simplexml_load_string($res_clean);
        
        return $xml_el;
    }
    
    private function soapRequest($content, $action){
//        $client = new \SoapClient(realpath(dirname(__FILE__)) . '/etl_wsdl.xml');
        $client = new \SoapClient(__DIR__ . '/etl_wsdl.xml');
        print_r($client->__getFunctions());
//        print_r($client->__getTypes());
        $result = $client->$action($content);
        return $result;
    }

    private function curlRequest($content, $location, $action)
    {
        $headers = array(
            "Content-type: text/xml; charset=\"utf-8\"",
            "SoapAction: $action",
            "Content-length: " . strlen($content)
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $location);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $content);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $output = curl_exec($ch);

//        $info = curl_getinfo($ch);
//        echo '<pre>' . print_r($info, TRUE) . '</pre>';
        return $output;
    }
    
    private function getWorkingDay(){
        $tomorrow = $this->timestamp + 24 * 60 * 60;
        $day = date('w', $tomorrow); // get day # - 0 (for Sunday) through 6 (for Saturday)
        if($day == 0){
            $tomorrow = $tomorrow + 24 * 60 * 60;
        }
        if($day == 6){
            $tomorrow = $tomorrow + 48 * 60 * 60;
        }
        $date = date('c', $tomorrow);
        
        return $date;
    }

    private function CreatePickUp()
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
                  <gser:Phone>' . $this->request_params['method']->get('phone') . '</gser:Phone>
               </gser:Contact>
               <gser:CountItems>1</gser:CountItems>
               <gser:DateFrom>' . $this->getWorkingDay() . '</gser:DateFrom>
               <gser:DistributionChannel>' . $this->DistributionChannel . '</gser:DistributionChannel>
               <gser:TotalWeight>5</gser:TotalWeight>
            </gser:RequestObject>
         </tem:Request>
      </tem:CreatePickUp>
   </soapenv:Body>
</soapenv:Envelope>';

        return $xml;
    }

    private function InsertExport()
    {
        $countryDelivery = countryToCountryCode($this->auction_params->get('country_shipping'));
        $countrySender = countryToCountryCode($this->request_params['method']->get('country_name'));
        
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
                  <gser:City>Raszyn</gser:City> <!--{$this->auction_params->get('city_shipping')}-->
                  <gser:Country>$countryDelivery</gser:Country>
                  <gser:Name>{$this->auction_params->get('firstname_shipping')}</gser:Name>
                  <gser:Name2>{$this->auction_params->get('name_shipping')}</gser:Name2>
                  <gser:Street>{$this->auction_params->get('street_shipping')} {$this->auction_params->get('house_shipping')}</gser:Street>
                  <gser:ZipCode>05090</gser:ZipCode> <!--{$this->auction_params->get('zip_shipping')}-->
               </gser:DeliveryAddress>
               <gser:DeliveryContact>
                  <gser:Email>text@email.pl</gser:Email> <!--{$this->auction_params->get('email_shipping')}-->
                  <gser:FullName>{$this->auction_params->get('firstname_shipping')} {$this->auction_params->get('name_shipping')}</gser:FullName>
                  <gser:Phone>+485087698</gser:Phone><!--{$this->auction_params->get('tel_shipping')}-->
               </gser:DeliveryContact>
               <gser:DistributionChannel>$this->DistributionChannel</gser:DistributionChannel>
               <gser:ExportItems>
                  <gser:ExportItem>
                     <gser:CountItems>1</gser:CountItems>
                     <gser:Description>{$this->auction_params->get('offer_name')}</gser:Description>
                     <gser:Dimensions>1</gser:Dimensions>
                     <gser:Height>1</gser:Height>
                     <gser:Length>1</gser:Length>
                     <gser:Reference>{$this->auction_params->get('auction_number')}</gser:Reference>
                     <gser:Type>1</gser:Type>
                     <gser:Volume>1</gser:Volume>
                     <gser:Weight>5</gser:Weight>
                     <gser:Width>1</gser:Width>
                  </gser:ExportItem>
               </gser:ExportItems>
               <gser:ExportServices>
                  <gser:ExportService>
                    <gser:Code>1091</gser:Code> <!-- EML -->
                    <gser:Parameter_1>text@email.pl</gser:Parameter_1><!--{$this->auction_params->get('email_shipping')}-->
                    <gser:Parameter_2 />
                    <gser:Parameter_3 />
                    <gser:Parameter_4 />
                    <gser:Parameter_5 />
                  </gser:ExportService>
                  <!--<gser:ExportService>
                     <gser:Code>COD</gser:Code>
                     <gser:Parameter_1>Value of cash on delivery</gser:Parameter_1>
                     <gser:Parameter_2>Currency of cash on delivery</gser:Parameter_2>
                     <gser:Parameter_3>Payment identification code</gser:Parameter_3>
                     <gser:Parameter_4>IBAN</gser:Parameter_4>
                     <gser:Parameter_5>BIC</gser:Parameter_5>
                  </gser:ExportService>-->
               </gser:ExportServices>
               <!--<gser:MergedPackages>
                  <gser:MergedItem>
                     <gser:PackNumber>?</gser:PackNumber>
                     <gser:Weight>?</gser:Weight>
                  </gser:MergedItem>
               </gser:MergedPackages>
               <gser:MergedShipment>?</gser:MergedShipment>-->
               <gser:Note />
               <gser:NoteDriver />
               <gser:PickUpDate>{$this->getWorkingDay()}</gser:PickUpDate>
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
               <gser:Weight>5</gser:Weight>
            </gser:RequestObject>
         </tem:Request>
      </tem:InsertExport>
   </soapenv:Body>
</soapenv:Envelope>";

        return $xml;
    }

    private function InsertOrder()
    {
        $xml = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tem="http://tempuri.org/" xmlns:gser="http://schemas.datacontract.org/2004/07/GService.Manager">
   <soapenv:Header/>
   <soapenv:Body>
      <tem:InsertOrder>
         <tem:Request>
            <gser:Header>
               <gser:CustomerCode>' . $this->customerCode . '</gser:CustomerCode>
               <gser:Language>' . $this->language . '</gser:Language>
               <gser:Password>' . $this->password . '</gser:Password>
            </gser:Header>
            <gser:RequestObject>
               <gser:BurstId>?</gser:BurstId>
               <gser:DeliveryAddress>
                  <gser:City>?</gser:City>
                  <gser:Country></gser:Country>
                  <gser:Name>?</gser:Name>
                  <gser:Name2>?</gser:Name2>
                  <gser:Street>?</gser:Street>
                  <gser:ZipCode>?</gser:ZipCode>
               </gser:DeliveryAddress>
               <gser:DeliveryContact>
                  <gser:Email>?</gser:Email>
                  <gser:FullName>?</gser:FullName>
                  <gser:Phone>?</gser:Phone>
               </gser:DeliveryContact>
               <gser:DistributionChannel>' . $this->DistributionChannel . ' </gser:DistributionChannel>
               <gser:ExportItems>
                  <gser:ExportItem>
                     <gser:CountItems>?</gser:CountItems>
                     <gser:Description>?</gser:Description>
                     <gser:Dimensions>?</gser:Dimensions>
                     <gser:Height>?</gser:Height>
                     <gser:Length>?</gser:Length>
                     <gser:Reference>?</gser:Reference>
                     <gser:Type>?</gser:Type>
                     <gser:Volume>?</gser:Volume>
                     <gser:Weight>?</gser:Weight>
                     <gser:Width>?</gser:Width>
                  </gser:ExportItem>
               </gser:ExportItems>
               <gser:ExportServices>
                  <gser:ExportService>
                     <gser:Code>?</gser:Code>
                     <gser:Parameter_1>?</gser:Parameter_1>
                     <gser:Parameter_2>?</gser:Parameter_2>
                     <gser:Parameter_3>?</gser:Parameter_3>
                     <gser:Parameter_4>?</gser:Parameter_4>
                     <gser:Parameter_5>?</gser:Parameter_5>
                     <gser:Parameter_6>?</gser:Parameter_6>
                     <gser:Parameter_7>?</gser:Parameter_7>
                     <gser:Parameter_8>?</gser:Parameter_8>
                  </gser:ExportService>
               </gser:ExportServices>
               <gser:MergedOrder>?</gser:MergedOrder>
               <gser:MergedOrders>
                  <gser:MergedItem>
                     <gser:PackNumber>?</gser:PackNumber>
                     <gser:Weight>?</gser:Weight>
                  </gser:MergedItem>
               </gser:MergedOrders>
               <gser:Note>?</gser:Note>
               <gser:NoteDriver>?</gser:NoteDriver>
               <gser:PickUpDate>?</gser:PickUpDate>
               <gser:Reference>?</gser:Reference>
               <gser:SenderAddress>
                  <gser:City>?</gser:City>
                  <gser:Country>?</gser:Country>
                  <gser:Name>?</gser:Name>
                  <gser:Name2>?</gser:Name2>
                  <gser:Street>?</gser:Street>
                  <gser:ZipCode>?</gser:ZipCode>
               </gser:SenderAddress>
               <gser:SenderContact>
                  <gser:Email>?</gser:Email>
                  <gser:FullName>?</gser:FullName>
                  <gser:Phone>?</gser:Phone>
               </gser:SenderContact>
               <gser:Volume>?</gser:Volume>
               <gser:Weight>?</gser:Weight>
            </gser:RequestObject>
         </tem:Request>
      </tem:InsertOrder>
   </soapenv:Body>
</soapenv:Envelope>';

        return $xml;
    }
    
    private function ServiceList(){
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
    
    private function AddServiceList(){
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

    private function GetLabel()
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
               <gser:Format>1</gser:Format>
               <gser:Position>1</gser:Position>
               <gser:ShipmentNumbers>
                  <gser:LabelItem>
                     <gser:ShipmentNumber>' . $this->auction_params->get('auction_number') . '</gser:ShipmentNumber>
                  </gser:LabelItem>
               </gser:ShipmentNumbers>
            </gser:RequestObject>
         </tem:Request>
      </tem:GetLabel>
   </soapenv:Body>
</soapenv:Envelope>';

        return $xml;
    }
    
}

