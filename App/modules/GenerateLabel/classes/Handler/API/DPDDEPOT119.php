<?php
namespace label\Handler\API;

use label\Config;
use label\Handler\HandlerAbstract;

class DPDDEPOT119 extends HandlerAbstract
{
    private $Version = '100';
    private $Language = 'en_EN';
    private $PartnerCredentialsName = 'DPD Cloud Service Alpha2';
    private $PartnerCredentialsToken = '33879594E70436D58685';
    private $UserCredentialscloudUserID = '183864';
    private $UserCredentialsToken = 'D2B796B32443967';
    private $url = 'https://cloud.dpd.com/api/v1/setOrder';

    /* Test account:
    private $PartnerCredentialsName = 'DPD Sandbox';
    private $PartnerCredentialsToken = '06445364853584D75564';
    private $UserCredentialscloudUserID = '2209';
    private $UserCredentialsToken = '2F39484E6D63696B4D5A';
    private $url = 'https://cloud-stage.dpd.com/api/v1/setOrder'; 
    */

    public function action($continue = false)
    {
        $header = $this->DPDConfig();
        $header[] = 'Content-Type: application/json';

        $data = $this->getParcelData();
//        echo '<pre>' . print_r($data, TRUE) . '</pre>'; exit;
        $response = $this->setOrder($header, $data);

        try {
            if ($response) {
                if (count($response->ErrorDataList)) { // check if errors exists
                    $auc_num = $this->auction_params->get('auction_number') . "/" . $this->auction_params->get('txnid');
                    $error = '<p>Auction ' . $auc_num . ', shipping method ' . $this->request_params['method']->get('company_name') . '. Error description:</p>';
                    $error .= "<ul>";
                    foreach ($response->ErrorDataList as $item) {
                        $error .= '<li>' . var_export($item, true) . '</li>';
                    }
                    $error .= "</ul>";
                    
                    if(!$continue){
                        die($error);
                    }
                    else {
                        $auc_num = $this->auction_params->get('auction_number') . "/" . $this->auction_params->get('txnid');
                        $_SESSION['messages']['errors'][$auc_num] = $error;
                        return false;
                    }
                } elseif (!empty($response->LabelResponse) && isset ($response->LabelResponse->LabelPDF)) {
                    $number = $response->LabelResponse->LabelDataList[0]->ParcelNo;
                    $pdf = $response->LabelResponse->LabelPDF;
                    
                    $pdf = base64_decode($pdf);

                    $this->saveLabel($number, $pdf);
                    
                    if($continue == true){
                        return $pdf;
                    }
                    
                    // get pdf file
                    header("Content-type: application/pdf");
                    header("Content-disposition: inline; filename=label.pdf");
                    echo $pdf;
                    exit;
                }
            } else {
                throw new Exception("Error while sending request to DPD cloud");
            }
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }

    private function DPDConfig()
    {
        $cfg = array(
            'Version' => $this->Version,
            'Language' => $this->Language,
            'PartnerCredentials-Name' => $this->PartnerCredentialsName,
            'PartnerCredentials-Token' => $this->PartnerCredentialsToken,
            'UserCredentials-cloudUserID' => $this->UserCredentialscloudUserID,
            'UserCredentials-Token' => $this->UserCredentialsToken,
        );

        $header = array();
        foreach ($cfg as $k => $v) {
            $header[] = $k . " : " . $v;
        }

        return $header;
    }

    private function getParcelData()
    {
        $recipient_country = countryToCountryCode($this->auction_params->get('country_shipping'));
        if ($recipient_country == 'UK') {
            $recipient_country = 'GB';
        }

        $company = str_replace('&', '', $this->auction_params->get('company_shipping'));
        if (strlen($company) > 50) {
            $company = substr($company, 0, 47) . '...';
        }
        if(empty(trim($company))){
            $company = "no";
        }

        $content = (!empty($this->auction_params->get('offer_name'))) ? 
            $this->auction_params->get('offer_name') : 
            "Auction #{$this->auction_params->get('auction_number')}";
        
        if (strlen($content) > 35) {
            $content = substr($content, 0, 31) . '...';
        }

        // Cash on delivery
        $shipping_method_id = $this->request_params['method']->get('shipping_method_id');
        $LabelsCount = $this->auction_params->getLabelsCount($shipping_method_id);
        $invoice = $this->auction_params->getMyInvoice();
        $openAmount = number_format($invoice->get("open_amount"), 2, '.', '');
        $shipService = ($this->auction_params->get('payment_method') == '2' && $openAmount > 0 && $LabelsCount == 0) ? 'Classic_COD' : 'Classic';
//        $street = iconv("UTF-8", "ASCII//TRANSLIT", $this->auction_params->get('street_shipping'));
//        $house = iconv("UTF-8", "ASCII//TRANSLIT", $this->auction_params->get('house_shipping'));
        $house = $this->auction_params->get('house_shipping');
        $house =  strlen($house) > 8 ? substr($house, 0, 8) : $this->auction_params->get('house_shipping');
        $zip = str_replace(' ', '', $this->auction_params->get('zip_shipping'));

        $phone = $this->auction_params->get('tel_shipping_formatted');
        if(strlen($this->auction_params->get('tel_shipping_formatted')) < strlen($this->auction_params->get('cel_shipping_formatted'))){
            $phone = $this->auction_params->get('cel_shipping_formatted');
        }
        $phone = trim(str_replace(' ', '', $phone));
        
        $data = array(
            "OrderAction" => "startOrder",
            "OrderSettings" => array(
                "ShipDate" => '2020-01-01', // TODO: Check
//                "ShipDate" => date("Y-m-d", strtotime($this->auction_params->get('end_time'))),
                "LabelSize" => "PDF_A6",
                "LabelStartPosition" => "UpperLeft"
            ),
            "OrderDataList" => array(
                array(
                    "ShipAddress" => array(
                        "Company" => $company,
                        "Name" => $this->auction_params->get('firstname_shipping') . ' ' . $this->auction_params->get('name_shipping'),
                        "Street" => $this->auction_params->get('street_shipping'),
                        "HouseNo" => $house,
                        "ZipCode" => $zip,
                        "City" => $this->auction_params->get('city_shipping'),
                        "Country" => $recipient_country,
                        "State" => $this->auction_params->get('state_shipping'),
                        "Phone" => $phone,
                    ),

                    "ParcelData" => array(
                        "Weight" => "0.0",
                        "YourInternalID" => $this->UserCredentialscloudUserID,
                        "Content" => $content,
                        "Reference1" => $this->auction_params->get('seller_email'),
                        "Reference2" => $this->auction_params->get('auction_number'),
                        "ShipService" => $shipService,
                    ),

                )
            ),
        );

        if (filter_var($this->auction_params->get('email_shipping'), FILTER_VALIDATE_EMAIL)) {
            $data['OrderDataList'][0]['ShipAddress']['Mail'] = $this->auction_params->get('email_shipping');
        }

        if($shipService == 'Classic_COD'){
            $data['OrderDataList'][0]['ParcelData']['COD'] = array(
                'Purpose' => $this->auction_params->get('auction_number'),
                'Amount' => $openAmount,
                'Payment' => 'Cash',
            );
        };

        return $data;
    }

    private function setOrder($header, $data)
    {
        $mySetOrderJSONString = json_encode($data);

        $init = curl_init($this->url);
        curl_setopt_array($init, array(
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $header,
                CURLOPT_POSTFIELDS => $mySetOrderJSONString,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSL_VERIFYPEER => false,
            )
        );
        $setOrderResponse = curl_exec($init);

        return json_decode($setOrderResponse);
    }

}
