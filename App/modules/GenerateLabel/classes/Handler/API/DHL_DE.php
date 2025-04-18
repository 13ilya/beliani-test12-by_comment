<?php

namespace label\Handler\API;

require_once 'util.php';

use label\Config;
use label\DHLBusinessShipment;
use label\Handler\HandlerAbstract;


class DHL_DE extends HandlerAbstract
{
//    private $WSDL_URL = 'https://cig.dhl.de/services/soap';
//    private $WSDL_URL = 'https://cig.dhl.de/services/sandbox/soap';
//    private $KEY_AUTH = 'ae5a7279-bb88-4316-88a2-81e911699ff3';
//    private $KEY_AUTH = 'YuiEyXJM22HIisFQXePZFoWae9fDPa';

    public function action($continue = false)
    {

        // test customer and api credentials from/for dhl
//        $credentials = array(
//            'user' => 'geschaeftskunden_api',
//            'signature' => 'Dhl_ep_test1',
//            'ekp' => '5000000000',
//            'api_user' => 'baidush1305',
//            'api_password' => '{|A@q#9y',
//            'log' => true,
//            'Attendance' => '01'
//        );

        // real customer and api credentials from/for dhl
        $credentials = array(
            'user' => 'Webbellani_1',
            'signature' => 'YuiEyXJM22HIisFQXePZFoWae9fDPa',
            'ekp' => '7000837587',
            'api_user' => 'bcontrol',
            'api_password' => 'Widmer2017!',
            'log' => true,
        );

// your company info
//        $info = array(
//            'company_name'    => 'Beliani',
//            'street_name'     => 'Clayallee',
//            'street_number'   => '241',
//            'zip'             => '14165',
//            'country'         => 'germany',
//            'city'            => 'Berlin',
//            'email'           => 'bestellung@kindhochdrei.de',
//            'phone'           => '01788338795',
//            'internet'        => 'http://www.kindhochdrei.de',
//            'contact_person'  => 'Nina Boeing'
//
//        );

        $info = array(
            'company_name' => 'Beliani DE GmbH',
            'street_name' => 'Seeweg',
            'street_number' => '1',
            'zip' => '17291',
            'country' => 'germany',
            'city' => 'Grünow',
            'email' => 'mail@beliani.de',
            'phone' => '+49 221 677 89 931',
        );

// receiver details
//        $customer_details = array(
//            'first_name' => 'Tobias',
//            'last_name' => 'Redmann',
//            'c/o' => '',
//            'street_name' => 'Hocksteinweg',
//            'street_number' => '11',
//            'country' => 'Germany',
//            'country_code' => 'DE',
//            'zip' => '14165',
//            'city' => 'Berlin'
//        );

        // receiver details
        $auction = $this->auction_params->data;
        $countryCode = countryToCountryCode($this->auction_params->get('country_shipping'));
        $countryCode = ($countryCode == 'UK') ? 'GB' : $countryCode;
        $tel = empty(trim($auction->cel_shipping_formatted)) ? 
            trim($auction->tel_shipping_formatted) : 
            trim($auction->cel_shipping_formatted); 
        
        $same_address = $auction->same_address;
        $customer_details = array(
            'company' => trim($auction->company_shipping),
            'first_name' => trim($auction->firstname_shipping),
            'last_name' => trim($auction->name_shipping),
            'c/o' => '',
            'street_name' => trim($auction->street_shipping),
            'street_number' => trim($auction->house_shipping),
            'country' => trim($auction->country_shipping),
            'country_code' => trim($countryCode),
            'zip' => trim($auction->zip_shipping),
            'city' => trim($auction->city_shipping),
            'phone' => $tel,
            'email' => trim($auction->email_shipping),
        );

        if (countryToCountryCode($this->auction_params->get('country_shipping')) == 'DE') {
            $credentials['ProductCode'] = 'EPN';
            $credentials['Attendance'] = '14';
        } else {
            $credentials['ProductCode'] = 'BPI';
            $credentials['Attendance'] = '13';
        }

//        var_dump($customer_details);


        // COD
        $shipping_method_id = $this->request_params['method']->get('shipping_method_id');
        $LabelsCount = $this->auction_params->getLabelsCount($shipping_method_id);

        if ($this->auction_params->get('payment_method') == '2' && !$LabelsCount) {
            $invoice = $this->auction_params->getMyInvoice();
//            $openAmount = number_format($invoice->get("open_amount"), 2, '.', '');
            $openAmount = $invoice->get("open_amount");
            $currency = siteToSymbol($this->auction_params->get('siteid'));
            $seller_info = $this->request_params['seller'];

            $credentials['cod'] = array(
                'sum' => array(
                    'CODAmount' => $openAmount,
                    'CODCurrency' => $currency,
                ),

                'BankData' => array(
                    'accountOwner' => $seller_info->get('bank_owner'),
                    'accountNumber' => $seller_info->get('bank_account'),
                    'bankName' => $seller_info->get('bank'),
                    'iban' => $seller_info->get('iban'),
                    'bic' => $seller_info->get('bic'),
//                    'bankCode' => '87050000', // blz?
//                    'note' => 'Notiz Bank',
                ),
            );
        }

        $dhl = new DHLBusinessShipment($credentials, $info, false);

        $response = $dhl->createNationalShipment($customer_details);
        if ( ! $response) {
            var_dump($dhl->errors);
            return;
        }

        $pdf = file_get_contents($response["label_url"]);
        if (!strlen($pdf)) {
            $auc_num = $this->auction_params->get('auction_number') . "/" . $this->auction_params->get('txnid');
            $error = '<p>Auction ' . $auc_num . ', shipping method ' . $this->request_params['method']->get('company_name') . '. Error description:</p>';
            $error .= '<pre>' . print_r($dhl->errors, TRUE) . '</pre><hr>';
            $error .= '<h3>Request source:</h3>' . htmlentities($dhl->requestXML);
            if(!$continue){
                die($error);
            }
            else {
                $_SESSION['messages']['errors'][$auc_num] = $error;
                return false;
            }
        }

        $this->saveLabel($response["shipment_number"], $pdf);

        if($continue == true){
            return $pdf;
        }

        header("Content-type: application/pdf");
        header("Content-disposition: inline; filename=label.pdf");
        echo $pdf;
        exit;
    }

}
