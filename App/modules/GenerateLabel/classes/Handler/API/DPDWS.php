<?php
namespace label\Handler\API;

use label\Config;
use label\Handler\HandlerAbstract;

class DPDWS extends HandlerAbstract
{

    public function action($continue = false)
    {

        if (strlen($this->request_params['method']->get('dpdws_delisId')) && strlen($this->request_params['method']->get('dpdws_password'))) {
            $number = '11111111111';
            $auth = $this->DPDWS_getAuth();
            $doc = $this->DPDWS_ShipmentService($auth, $number, $this->auction_params);

            $auc_num = $this->auction_params->get('auction_number') . "/" . $this->auction_params->get('txnid');
            $error = '<p>Auction ' . $auc_num . ', shipping method ' . $this->request_params['method']->get('company_name') . '</p>';

            if (isset($doc['soap:Body']['soap:Fault']) ||
                (isset($doc['soap:Body']['ns2:storeOrdersResponse']['orderResult']['shipmentResponses']['faults']['faultCode']))
            ) {
                $error .= '<pre>' . print_r($doc['soap:Body']['soap:Fault'], TRUE) . '</pre>';
                $error .= '<pre>' . print_r($doc['soap:Body']['ns2:storeOrdersResponse']['orderResult']['shipmentResponses']['faults']['faultCode'], TRUE) . '</pre>';
            }

            if (isset($doc['soap:Body']['soap:Fault']) || isset($doc['soap:Body']['ns2:storeOrdersResponse']['orderResult']['shipmentResponses']['faults']['faultCode'])) {
                $error .= '<pre>' . print_r($doc['soap:Body']['soap:Fault'], TRUE) . '</pre>';
                $error .= '<pre>' . print_r($doc['soap:Body']['ns2:storeOrdersResponse']['orderResult']['shipmentResponses']['faults'], TRUE) . '</pre>';
                if (!$continue) {
                    die($error);
                } else {
                    $_SESSION['messages']['errors'][$auc_num] = $error;
                    return false;
                }
            }
            $pdf = $doc['soap:Body']['ns2:storeOrdersResponse']['orderResult']['parcellabelsPDF'];
            $number = $doc['soap:Body']['ns2:storeOrdersResponse']['orderResult']['shipmentResponses']['parcelInformation']['parcelLabelNumber'];
            
            $pdf = base64_decode($pdf);
            $this->saveLabel($number, $pdf);

            if ($continue == true) {
                return $pdf;
            }

            header("Content-type: application/pdf");
            header("Content-disposition: inline; filename=label.pdf");
            echo $pdf;
            exit;
        }
    }

    public function DPDWS_getAuth()
    {

        $opts = array();
        $us = new \XML_Unserializer($opts);

        $xml_getAuth = '
		    <soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
		     xmlns:ns="http://dpd.com/common/service/types/LoginService/2.0">
		     <soapenv:Header/>
		     <soapenv:Body>
		     <ns:getAuth>
		     <delisId>' . $this->request_params['method']->get('dpdws_delisId') . '</delisId>
		     <password>' . $this->request_params['method']->get('dpdws_password') . '</password>
		     <messageLanguage>en_EN</messageLanguage>
		     </ns:getAuth>
		     </soapenv:Body>
		    <soapenv:Envelope>
		    ';

        $headers_getAuth = array(
            "POST  HTTP/1.1",
            "Content-type: application/soap+xml; charset=\"utf-8\"",
            "SOAPAction: \"http://dpd.com/common/service/LoginService/2.0/getAuth\"",
            "Content-length: " . strlen($xml_getAuth)
        );

        $url = 'https://public-ws.dpd.com/services/LoginService/V2_0/';
        $getAuth = curl_init($url);
        curl_setopt($getAuth, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($getAuth, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($getAuth, CURLOPT_POST, 1);
        curl_setopt($getAuth, CURLOPT_HTTPHEADER, $headers_getAuth);
        curl_setopt($getAuth, CURLOPT_POSTFIELDS, "$xml_getAuth");
        curl_setopt($getAuth, CURLOPT_RETURNTRANSFER, 1);

        $output_getAuth = curl_exec($getAuth);
        $us->unserialize($output_getAuth);
        $result = $us->getUnserializedData();
        return $result['soap:Body']['ns2:getAuthResponse']['return']['authToken'];
    }

    public function DPDWS_ShipmentService($auth, $number, $auction)
    {
        $opts = array();
        $us = new \XML_Unserializer($opts);

        switch ($this->auction_params->getMyLang()) {
            case 'english':
                $lang = 'en_EN';
                break;
            case 'german':
                $lang = 'de_DE';
                break;
            case 'french':
                $lang = 'fr_FR';
                break;
            case 'polish':
                $lang = 'pl_PL';
                break;
            case 'dutch':
                $lang = 'de_DE';
                break;
            case 'swedish':
                $lang = 'se_SE';
                break;
            case 'Hungarian':
                $lang = 'hu_HU';
                break;
            case 'italian':
                $lang = 'it_IT';
                break;
            case 'portugal':
                $lang = 'pt_PT';
                break;
            case 'spanish':
                $lang = 'es_ES';
                break;
            case 'danish':
                $lang = 'dk_DK';
                break;
            default:    
                $lang = 'en_EN';
                break;
        }

        $errors = array();
        
        $recipient_country = countryToCountryCode($this->auction_params->get('country_shipping'));
        if ($recipient_country == 'UK') $recipient_country = 'GB';

        $company = str_replace('&', '', $this->auction_params->get('company_shipping'));
        if(!trim($company)) $company = ' ';

        $name = $this->auction_params->get('firstname_shipping') . ' ' . $this->auction_params->get('name_shipping');
        if(strlen($name) > 35){
            $errors[] = "Combination of firstname and lastname is exceeded 35 symbols length, please correct it. Current value is: " . $name;
        }
        
        $street = $this->auction_params->get('street_shipping') . ' ' . $this->auction_params->get('house_shipping');
        $street = str_replace('º', '', $street);
        if(strlen($street) > 35){
            $errors[] = "Combination of street and house address is exceeded 35 symbols length, please correct it. Current value is: " . $street;
        }
        
        if(count($errors)){
            echo "<ul>";
            foreach ($errors as $item) {
                echo "<li>$item</li>";
            }
            echo "</ul>";
            die();
        }
        
        $xml = '
<soapenv:Envelope
		xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"
		xmlns:ns="http://dpd.com/common/service/types/Authentication/2.0"
		xmlns:ns1="http://dpd.com/common/service/types/ShipmentService/3.1">
	<soapenv:Header>
		<ns:authentication>
			<delisId>' . $this->request_params['method']->get('dpdws_delisId') . '</delisId>
			<authToken>' . $auth . '</authToken>
			<messageLanguage>' . $lang . '</messageLanguage>
		</ns:authentication>
	</soapenv:Header>
	<soapenv:Body>
		<ns1:storeOrders>
			<printOptions>
				<printerLanguage>PDF</printerLanguage>
				<paperFormat>A6</paperFormat>
			</printOptions>
			<order>
				<generalShipmentData>
					<identificationNumber>77777</identificationNumber>
					<sendingDepot>0163</sendingDepot>
					<product>CL</product>
					<mpsCompleteDelivery>0</mpsCompleteDelivery>
					<sender>
						<name1>Beliani DE GmbH</name1>
						<street>Seeweg 1</street>
						<country>DE</country>
						<zipCode>17291</zipCode>
						<city>Grünow</city>
						<customerNumber>12345679</customerNumber>
					</sender>
					<recipient>
						<name1>' . $name . '</name1>
						<name2>' . $company . '</name2>
						<street>' . $street . '</street>
						' . (strlen($this->auction_params->get('state_shipping')) ? ('<state>' . $this->auction_params->get('state_shipping') . '</state>') : '') . '
						<country>' . $recipient_country . '</country>
						<zipCode>' . $this->auction_params->get('zip_shipping') . '</zipCode>
						<city>' . $this->auction_params->get('city_shipping') . '</city>
					</recipient>
				</generalShipmentData>
				<parcels>
					<parcelLabelNumber>' . $number . '</parcelLabelNumber>
				</parcels>
				<productAndServiceData>
					<orderType>consignment</orderType>
                 <predict>
                  <channel>1</channel>
                  <value>' . $this->auction_params->get('email_shipping') . '</value>
                  <language>' . substr($lang, 3, 2) . '</language>
               </predict>
				</productAndServiceData>
			</order>
		</ns1:storeOrders>
	</soapenv:Body>
</soapenv:Envelope>
		    ';

//        echo '<pre>' . print_r(htmlentities($xml), TRUE) . '</pre><hr>'; 
//        die();

        $headers = array(
            "POST  HTTP/1.1",
            "Content-type: application/soap+xml; charset=\"utf-8\"",
            "SOAPAction: \"http://dpd.com/common/service/ShipmentService/3.1/storeOrders\"",
            "Content-length: " . strlen($xml)
        );
        if ($this->auction_params->get('auction_number') == 231545) echo $xml;
        $url = 'https://public-ws.dpd.com/services/ShipmentService/V3_1/';
        $getAuth = curl_init($url);
        curl_setopt($getAuth, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($getAuth, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($getAuth, CURLOPT_POST, 1);
        curl_setopt($getAuth, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($getAuth, CURLOPT_POSTFIELDS, "$xml");
        curl_setopt($getAuth, CURLOPT_RETURNTRANSFER, 1);
        $output_getAuth = curl_exec($getAuth);
        $us->unserialize($output_getAuth);
        $result = $us->getUnserializedData();
        return $result;
    }
}
