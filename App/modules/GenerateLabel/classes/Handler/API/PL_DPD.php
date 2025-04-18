<?php
/**
 * @copyright innerfly
 * @date      05.04.2017 12:10
 */

namespace label\Handler\API;

require_once 'lib/Order.php';
require_once 'lib/Article.php';

use Dompdf\Exception;
use label\Handler\HandlerAbstract;

class PL_DPD extends HandlerAbstract
{
//    private $login = 'test';
//    private $masterFid = '1495';
//    private $password = 'KqvsoFLT2M';
    private $login = "pmarat";
    private $masterFid = "1495";
    private $password = "pm14";
    private $endpoint;
    private $client;
    private $sessionId;
    private $shipping_number;

    public function __construct()
    {
        $this->endpoint = (strpos($_SERVER['HTTP_HOST'], 'dev.') || strpos($_SERVER['HTTP_HOST'], 'heap.')) ?
            "https://dpdservicesdemo.dpd.com.pl/DPDPackageXmlServicesService/DPDPackageXmlServices?wsdl" :
            "https://dpdservices.dpd.com.pl/DPDPackageXmlServicesService/DPDPackageXmlServices?wsdl";
    }
    
    public function action($continue = false)
    {
        $allorder = \Order::listAll($this->request_params['DB'], $this->request_params['DB'],
            $this->auction_params->get('auction_number'), $this->auction_params->get('txnid'), 1,
            $this->auction_params->getMyLang(), '0,1', 1);
        $realOrderItems = [];
        foreach ($allorder as $item) {
            if ($item->article_id != 0 && $item->admin_id == 0) {
                $article = new \Article($this->request_params['DB'], $this->request_params['DB'], $item->article_id);
                $item->parcels = $article->parcels;
                $realOrderItems[] = $item;
            }
        }
        
        $this->client = new \SoapClient($this->endpoint, ['features' => SOAP_SINGLE_ELEMENT_ARRAYS]);
        $this->shipping_number = rand(000000000, 999999999);
        
        try{
            $this->generatePackagesNumbersXV1($realOrderItems);
            $pdf = $this->generateSpedLabelsXV1();
            if($pdf){
                $this->saveLabel($this->shipping_number, $pdf);
                if($continue) return $pdf;
                header ("Content-type: application/pdf");
                header("Content-disposition: inline; filename=label.pdf");
                die($pdf);
            }
        }
        catch (Exception $e){
            echo $e->getMessage();
        }
        
// by packageId
        /*
        $dpdServiceParam2 = "
<DPDServicesParamsV1>
<Policy>STOP_ON_FIRST_ERROR</Policy>
<Session>
<SessionType>DOMESTIC</SessionType>
<Packages>
<Package>
<PackageId>" . $packageId . "</PackageId>
</Package>
</Packages>
</Session>
</DPDServicesParamsV1>";
        $params3 = new \stdClass();
        $params3->dpdServicesParamsXV1 = $dpdServiceParam2;
        $params3->outputDocFormatV1 = "PDF";
        $params3->outputDocPageFormatV1 = "A4";
        $params3->authDataV1 = $this->authData();
        $result = $client->generateSpedLabelsXV1($params3);
        $xml = simplexml_load_string($result->return);
        $pdf2 = $xml->DocumentData;
        echo $xml->Session->StatusInfo->Status;
// by ref.        
        $dpdServiceParam3 = "
<DPDServicesParamsV1>
<Policy>STOP_ON_FIRST_ERROR</Policy>
<Session>
<SessionType>DOMESTIC</SessionType>
<Packages>
<Package>
<Reference>" . $reference . "</Reference>
</Package>
</Packages>
</Session>
</DPDServicesParamsV1>";
        $params4 = new \stdClass();
        $params4->dpdServicesParamsXV1 = $dpdServiceParam3;
        $params4->outputDocFormatV1 = "PDF";
        $params4->outputDocPageFormatV1 = "A4";
        $params4->authDataV1 = $this->authData();
        $result = $client->generateSpedLabelsXV1($params4);
        $xml = simplexml_load_string($result->return);
        $pdf3 = $xml->DocumentData;
        echo $xml->Session->StatusInfo->Status;
// by parcelId
        $dpdServiceParam4 = "
<DPDServicesParamsV1>
<Policy>STOP_ON_FIRST_ERROR</Policy>
<Session>
<SessionType>DOMESTIC</SessionType>
<Packages>
<Package>
<Parcels>
<Parcel>
<ParcelId>" . $parcelId . "</ParcelId>
</Parcel>
</Parcels>
</Package>
</Packages>
</Session>
</DPDServicesParamsV1>";
        $params5 = new \stdClass();
        $params5->dpdServicesParamsXV1 = $dpdServiceParam4;
        $params5->outputDocFormatV1 = "PDF";
        $params5->outputDocPageFormatV1 = "A4";
        $params5->authDataV1 = $this->authData();
        $result = $client->generateSpedLabelsXV1($params5);
        $xml = simplexml_load_string($result->return);
        $pdf4 = $xml->DocumentData;
        echo $xml->Session->StatusInfo->Status;
// Tworzenie etykiet na podstawie waybill
        $dpdServiceParam5 = "
<DPDServicesParamsV1>
<Policy>STOP_ON_FIRST_ERROR</Policy>
<Session>
<SessionType>DOMESTIC</SessionType>
<Packages>
<Package>
<Parcels>
<Parcel>
<Waybill>" . $waybill . "</Waybill>
</Parcel>
</Parcels>
</Package>
</Packages>
</Session>
</DPDServicesParamsV1>";
        $params6 = new \stdClass();
        $params6->dpdServicesParamsXV1 = $dpdServiceParam5;
        $params6->outputDocFormatV1 = "PDF";
        $params6->outputDocPageFormatV1 = "A4";
        $params6->authDataV1 = $this->authData();
        $result = $client->generateSpedLabelsXV1($params6);
        $xml = simplexml_load_string($result->return);
        $pdf5 = $xml->DocumentData;
        echo $xml->Session->StatusInfo->Status . "<br/>";
        */
        
// Tworzenie protokołów na podstawie waybill
     /*   
        $dpdServiceParam7 = "<DPDServicesParamsV1>
<Policy>STOP_ON_FIRST_ERROR</Policy>
<PickupAddress>
<FID>" . $this->masterFid . "</FID>
</PickupAddress>
<Session>
<SessionType>DOMESTIC</SessionType>
<Packages>
<Package>
<Parcels>
<Parcel>
<Waybill>" . $waybill . "</Waybill>
</Parcel>
</Parcels>
</Package>
</Packages>
</Session>
</DPDServicesParamsV1>";
        $params8 = new \stdClass();
        $params8->dpdServicesParamsCV1 = $dpdServiceParam7;
        $params8->outputDocFormatV1 = "PDF";
        $params8->outputDocPageFormatV1 = "A4";
        $params8->authDataV1 = $this->authData();
        $result = $client->generateProtocolXV1($params8);
        $xml = simplexml_load_string($result->return);
        $pdf7 = $xml->DocumentData;
        $protocolIdTable[1] = $xml->DocumentId;
        echo $xml->Session->StatusInfo->Status;
// by parcelId
        $dpdServiceParam8 = "<DPDServicesParamsV1>
<Policy>STOP_ON_FIRST_ERROR</Policy>
<PickupAddress>
<FID>" . $this->masterFid . "</FID>
</PickupAddress>
<Session>
<SessionType>DOMESTIC</SessionType>
<Packages>
<Package>
<Parcels>
<Parcel>
<ParcelId>" . $parcelId . "</ParcelId>
</Parcel>
</Parcels>
</Package>
</Packages>
</Session>
</DPDServicesParamsV1>";
        $params9 = new \stdClass();
        $params9->dpdServicesParamsCV1 = $dpdServiceParam8;
        $params9->outputDocFormatV1 = "PDF";
        $params9->outputDocPageFormatV1 = "A4";
        $params9->authDataV1 = $this->authData();
        $result = $client->generateProtocolXV1($params9);
        $xml = simplexml_load_string($result->return);
        $pdf8 = $xml->DocumentData;
        $protocolIdTable[2] = $xml->DocumentId;
        echo $xml->Session->StatusInfo->Status;
// Tworzenie protokołów na podstawie packageId
        $dpdServiceParam9 =
            "<DPDServicesParamsV1>
<Policy>STOP_ON_FIRST_ERROR</Policy>
<PickupAddress>
<FID>" . $this->masterFid . "</FID>
</PickupAddress>
<Session>
<SessionType>DOMESTIC</SessionType>
<Packages>
<Package>
<PackageId>"
            . $packageId .
            "</PackageId>
</Package>
</Packages>
</Session>
</DPDServicesParamsV1>
";
        $params10 = new \stdClass();
        $params10->dpdServicesParamsCV1 = $dpdServiceParam9;
        $params10->outputDocFormatV1 = "PDF";
        $params10->outputDocPageFormatV1 = "A4";
        $params10->authDataV1 = $this->authData();
        $result = $client->generateProtocolXV1($params10);
        $xml = simplexml_load_string($result->return);
        $pdf9 = $xml->DocumentData;
        $protocolIdTable[3] = $xml->DocumentId;
        echo $xml->Session->StatusInfo->Status;
// Tworzenie protokołów na podstawie package ref
        $dpdServiceParam10 = "
<DPDServicesParamsV1>
<Policy>STOP_ON_FIRST_ERROR</Policy>
<PickupAddress>
<FID>" . $this->masterFid . "</FID>
</PickupAddress>
<Session>
<SessionType>DOMESTIC</SessionType>
<Packages>
<Package>
<Reference>" . $reference . "</Reference>
</Package>
</Packages>
</Session>
</DPDServicesParamsV1>";
        $params11 = new \stdClass();
        $params11->dpdServicesParamsCV1 = $dpdServiceParam10;
        $params11->outputDocFormatV1 = "PDF";
        $params11->outputDocPageFormatV1 = "A4";
        $params11->authDataV1 = $this->authData();
        $result = $client->generateProtocolXV1($params11);
        $xml = simplexml_load_string($result->return);
        $pdf10 = $xml->DocumentData;
        $protocolIdTable[4] = $xml->DocumentId;
        echo $xml->Session->StatusInfo->Status;
        */
// Zamawianie kuriera
        /*
        echo "<br/>zamawianie kuriera<br/>";
        $pickupDate = "2012-02-08";
        $pickupTimeFrom = "13:00";
        $pickupTimeTo = "15:00";
        $dpdServiceParam11 =
            "<DPDPickupCallParamsV2>
<OperationType>INSERT</OperationType>
<PickupDate>" . $pickupDate . "</PickupDate>
<PickupTimeFrom>" . $pickupTimeFrom . "</PickupTimeFrom>
<PickupTimeTo>" . $pickupTimeTo . "</PickupTimeTo>
<OrderType>DOMESTIC</OrderType>
<WaybillsReady>true</WaybillsReady>
<PickupCallSimplifiedDetails>
<PickupPayer>
<PayerNumber>57888</PayerNumber>
<PayerName>wrewerwerwer</PayerName>
<PayerCostCenter>werwerwerwee</PayerCostCenter>
</PickupPayer>
<PickupCustomer>
<CustomerName>customerName</CustomerName>
<CustomerFullName>customerFullName</CustomerFullName>
<CustomerPhone>111222333</CustomerPhone>
</PickupCustomer>
<PickupSender>
<SenderName>23049094u 2309u4 2309u4 </SenderName>
<SenderFullName>Jan Kowalski</SenderFullName>
<SenderAddress>ul. Złota 12/2</SenderAddress>
<SenderCity>Gdańsk</SenderCity>
<!--SenderPostalCode>02495</SenderPostalCode-->
<SenderPostalCode>02274</SenderPostalCode>
<SenderPhone>111222333</SenderPhone>
</PickupSender>
<PackagesParams>
<DOX>false</DOX>
<StandardParcel>true</StandardParcel>
<Pallet>false</Pallet>
<ParcelsCount>3</ParcelsCount>
<PalletsCount>5</PalletsCount>
<DOXCount>11</DOXCount>
<ParcelsWeight>10004.2</ParcelsWeight>
<ParcelMaxWeight>10.2</ParcelMaxWeight>
<ParcelMaxWidth>20.2</ParcelMaxWidth>
<ParcelMaxHeight>19.2</ParcelMaxHeight>
<ParcelMaxDepth>21</ParcelMaxDepth>
<PalletsWeight>15.2</PalletsWeight>
<PalletMaxWeight>15.2</PalletMaxWeight>
<PalletMaxHeight>15.2</PalletMaxHeight>
</PackagesParams>
</PickupCallSimplifiedDetails>
</DPDPickupCallParamsV2>
";
        $params12 = new \stdClass();
        $params12->dpdPickupParamsXV2 = $dpdServiceParam11;
        $params12->authDataV1 = $this->authData();
        $result = $client->packagesPickupCallXV2($params12);
        $xml = simplexml_load_string($result->return);
        echo "<BR/>status zamówienia: " . $xml->StatusInfo->Status . "<BR/>";
        if (strcmp($xml->StatusInfo->Status, "OK") == 0) {
            echo "<BR/>numer zamówienia kuriera: " . $xml->OrderNumber . "<BR/>";
        } else {
            $code = $xml->StatusInfo->ErrorDetails->Error->Code;
            $description = $xml->StatusInfo->Description;
            $fields = $xml->StatusInfo->ErrorDetails->Error->Fields;
            echo "<BR/>kod błędu: " . $code . ", opis błędu: " . $description . ", lista
błędnych pól: " . $fields . "<BR/>";
        }
        */
    }

    private function authData(){
        $data = new \stdClass();
        $data->login = $this->login;
        $data->masterFid = $this->masterFid;
        $data->password = $this->password;

        return $data;
    }
    
    private function generatePackagesNumbersXV1($realOrderItems){
        // Dane awizacyjne
        $openUMLFV1 = "
<Packages>
    <Package>
        <PayerType>SENDER</PayerType>
        <Sender>
            <FID>" . $this->masterFid . "</FID>
            <Company>DPD Polska Sp. z o.o.</Company>
            <Name>Jan Kowalski</Name>
            <Address>Ul. Mineralna 15</Address>
            <City>Warszawa</City>
            <CountryCode>PL</CountryCode>
            <PostalCode>02274</PostalCode>
            <Phone>022 577 55 003</Phone>
            <Email>dpd@dpd.com.pl</Email>
        </Sender>
        <Receiver>
            <Company>{$this->auction_params->data->company_shipping}</Company>
            <Name>{$this->auction_params->data->firstname_shipping} {$this->auction_params->data->name_shipping}</Name>
            <Address>{$this->auction_params->data->street_shipping} {$this->auction_params->data->house_shipping}</Address>
            <City>{$this->auction_params->data->city_shipping}</City>
            <CountryCode>" . countryToCountryCode($this->auction_params->data->country_shipping) . "</CountryCode>
            <PostalCode>" . trim(str_replace('-', '', $this->auction_params->data->zip_shipping)) . "</PostalCode>            <Phone>{$this->auction_params->data->cel_shipping}</Phone>
            <Email>{$this->auction_params->data->email_shipping}</Email>
        </Receiver>
        <Ref1>" . $this->shipping_number . "</Ref1>
        <Ref2>" . $this->shipping_number . "</Ref2>
        <Services>";

        if($this->isCod()){
            $invoice = $this->auction_params->getMyInvoice();
            $openAmount = number_format($invoice->get("open_amount"), 2, '.', '');
            $currency = siteToSymbol($this->auction_params->get('siteid'));
            $openUMLFV1 .= "
            <COD>
                <Amount>$openAmount</Amount>
                <Currency>$currency</Currency>
            </COD>";
        }

        $openUMLFV1 .= "
        </Services>
        <Parcels>";

        $weight_total = 0;
        $articles = [];
        foreach ($realOrderItems as $item) {
            foreach ($item->parcels as $parcel) {
                $weight = $parcel->weight_parcel * $item->quantity;
                $weight_total += $weight;
                $articles[] = [
                    'weight' => $weight,
                    'content' => $item->name,
                ];
                
            }
        }

        $labels_ll = $this->request_params['number_of_labels_ll'];
        if($labels_ll){
            $articles_old = $articles;
            $articles = [];
            for($i = 0; $i < $labels_ll; $i++){
                $articles[] = [
                    'weight' => $weight_total / $labels_ll,
                    'content' => $articles_old[$i - 1],
                ];
            }
        }

        foreach ($articles as $item) {
            $openUMLFV1 .= 
                "<Parcel>
                    <Weight>$weight</Weight>
                    <Content>$item->name</Content>
                    <CustomerData1>{$this->auction_params->data->auction_number}</CustomerData1>
                </Parcel>";
        }

        $openUMLFV1 .= "</Parcels>
    </Package>
</Packages>";

// walidacja danych przesyłek i nadawanie numerów listów przewozowych
        $params1 = new \stdClass();
        $params1->pkgNumsGenerationPolicyV1 = "IGNORE_ERRORS";
        $params1->openUMLXV1 = $openUMLFV1;
        $params1->authDataV1 = $this->authData();
        $result = $this->client->generatePackagesNumbersXV1($params1);
        $xml = simplexml_load_string($result->return);
        if($xml->Status == 'INCORRECT_DATA'){
            $msg = "{$xml->Packages->Package->InvalidFields->InvalidField->FieldName} validation error: {$xml->Packages->Package->InvalidFields->InvalidField->Info}";
            throw new \Exception($msg);
        }
        $this->sessionId = $xml->SessionId;
        /*$packageId = $xml->Packages->Package[0]->PackageId;
        $parcelId = $xml->Packages->Package[0]->Parcels->Parcel[1]->ParcelId;
        $waybill = $xml->Packages->Package[0]->Parcels->Parcel[1]->Waybill;
        $reference = $xml->Packages->Package[0]->Reference;*/
    }

    private function generateSpedLabelsXV1(){
        $dpdServiceParam1 = "
<DPDServicesParamsV1>
<Policy>STOP_ON_FIRST_ERROR</Policy>
<Session>
<SessionType>DOMESTIC</SessionType>
<SessionId>" . $this->sessionId . "</SessionId>
</Session>
</DPDServicesParamsV1>";
        
        $params2 = new \stdClass();
        $params2->dpdServicesParamsXV1 = $dpdServiceParam1;
        $params2->outputDocFormatV1 = "PDF";
        $params2->outputDocPageFormatV1 = "LBL_PRINTER";
        $params2->authDataV1 = $this->authData();
        $result = $this->client->generateSpedLabelsXV1($params2);
        $xml = simplexml_load_string($result->return);
        $pdf1 = $xml->DocumentData;
        
        return base64_decode($pdf1);
    }
    
    private function generateProtocolXV1(){
        // protokoły
        $protocolIdTable = array();
// Tworzenie protokołów na podstawie sessionId
        $dpdServiceParam6 = "
<DPDServicesParamsV1>
<Policy>STOP_ON_FIRST_ERROR</Policy>
<PickupAddress>
<FID>" . $this->masterFid . "</FID>
</PickupAddress>
<Session>
<SessionType>DOMESTIC</SessionType>
<SessionId>" . $this->sessionId . "</SessionId>
</Session>
</DPDServicesParamsV1>";
        
        $params7 = new \stdClass();
        $params7->dpdServicesParamsCV1 = $dpdServiceParam6;
        $params7->outputDocFormatV1 = "PDF";
        $params7->outputDocPageFormatV1 = "A4";
        $params7->authDataV1 = $this->authData();
        $result = $this->client->generateProtocolXV1($params7);
        $xml = simplexml_load_string($result->return);
        $pdf6 = $xml->DocumentData;
        $protocolIdTable[0] = $xml->DocumentId;
        
        return base64_decode($pdf6);
    }
    
}