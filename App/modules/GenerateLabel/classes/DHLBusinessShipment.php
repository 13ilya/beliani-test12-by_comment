<?php
namespace label;
define('API_URL', 'https://cig.dhl.de/cig-wsdls/com/dpdhl/wsdl/geschaeftskundenversand-api/1.0/geschaeftskundenversand-api-1.0.wsdl');
define('DHL_SANDBOX_URL', 'https://cig.dhl.de/services/sandbox/soap');
define('DHL_PRODUCTION_URL', 'https://cig.dhl.de/services/production/soap');


/**
 *
 */
class DHLBusinessShipment
{
    private $credentials;
    private $info;
    private $client;
    public $errors;
    public $requestXML;
    protected $sandbox;

    /**
     * Constructor for Shipment SDK
     *
     * @param type $api_credentials
     * @param type $customer_info
     * @param boolean $sandbox use sandbox or production environment
     */
    function __construct($api_credentials, $customer_info, $sandbox = TRUE)
    {

        $this->credentials = $api_credentials;
        $this->info = $customer_info;
        $this->sandbox = $sandbox;
        $this->errors = array();
    }

    private function log($message)
    {
        if (isset($this->credentials['log'])) {
            if (is_array($message) || is_object($message)) {
                error_log(print_r($message, true));
            } else {
                error_log($message);
            }
        }
    }

    private function buildClient()
    {
        $header = $this->buildAuthHeader();

        if ($this->sandbox) {
            $location = DHL_SANDBOX_URL;
        } else {
            $location = DHL_PRODUCTION_URL;
        }

        $auth_params = array(
            'login' => $this->credentials['user'],
            'password' => $this->credentials['signature'],
            'location' => $location,
            'trace' => 1,
        );

        $this->log($auth_params);
        $this->client = new \SoapClient(API_URL, $auth_params);
        $this->client->__setSoapHeaders($header);
        $this->log($this->client);
    }

    function createNationalShipment($customer_details, $shipment_details = null)
    {
        $this->buildClient();

        $shipment = array(
            'Version' => array(
                'majorRelease' => '1',
                'minorRelease' => '0',
            ),
            'ShipmentOrder' => array(
                // Fixme
                'SequenceNumber' => '1',
            ),

        );

        // Shipment
        $ShipmentDetails = array(
            'ProductCode' => $this->credentials['ProductCode'],
            'ShipmentDate' => date('Y-m-d'),
            'EKP' => $this->credentials['ekp'],
            'Attendance' => array(
                'partnerID' => $this->credentials['Attendance'],
            ),
        );

        if ($shipment_details == null) {
            $ShipmentDetails['ShipmentItem'] = array(
                'WeightInKG' => '5',
                'LengthInCM' => '50',
                'WidthInCM' => '50',
                'HeightInCM' => '50',
                // FIXME: What is this
                'PackageType' => 'PK',
            );
        }

        // COD
        if (isset($this->credentials['cod']) && count($this->credentials['cod'])) {
            $ShipmentDetails['Service'] = array(
                'ServiceGroupOther' => array(
                    'COD' => $this->credentials['cod']['sum'],
                ),
            );
            $ShipmentDetails['BankData'] = $this->credentials['cod']['BankData'];


//            $ShipmentDetails['BankData'] = array(
//                'accountOwner' => 'Versand AG', // bank_owner
//                'accountNumber' => '9876543210', // bank_account
//                'bankCode' => '87050000', // blz?
//                'bankName' => 'Sparkasse', // bank
//                'iban' => 'DE34870500001234567891', // iban
//                'note' => 'Notiz Bank',
//                'bic' => 'CHEKDE81XXX', // bic
//            );

        }

        $shipment['ShipmentOrder']['Shipment']['ShipmentDetails'] = $ShipmentDetails;

        $shipment['ShipmentOrder']['Shipment']['Shipper'] = array(
            'Company' => array(
                'Company' => array(
                    'name1' => $this->info['company_name'] ? $this->info['company_name'] : '',
                ),
            ),
            'Address' => array(
                'streetName' => $this->info['street_name'],
                'streetNumber' => $this->info['street_number'],
                'Zip' => array(
                    strtolower($this->info['country']) => $this->info['zip'],
                ),
                'city' => $this->info['city'],
                'Origin' => array(
                    'countryISOCode' => 'DE'
                ),
            ),
            'Communication' => array(
                'email' => $this->info['email'],
                'phone' => $this->info['phone'],
                'internet' => $this->info['internet'],
                'contactPerson' => $this->info['contact_person'],
            ),

        );

        $company = empty(trim($customer_details['company']))
            ? array(
                'Person' => array(
                    'firstname' => $customer_details['first_name'],
                    'lastname' => $customer_details['last_name'],
                ),
            )
            : array(
                'Company' => array(
                    'name1' => $customer_details['company'],
                ),
            );
        $shipment['ShipmentOrder']['Shipment']['Receiver'] = array(
            'Company' => $company,
            'Address' => array(
                'streetName' => $customer_details['street_name'],
                'streetNumber' => $customer_details['street_number'],
                'Zip' => array('other' => $customer_details['zip']),
                'city' => $customer_details['city'],
                'Origin' => array(
                    'countryISOCode' => $customer_details['country_code']
                ),
            ),
            'Communication' => array(
                'phone' => $customer_details['phone'],
                'email' => $customer_details['email'],
                'contactPerson' => $customer_details['first_name'] . " " . $customer_details['last_name'],
            ),
        );

        file_put_contents('tmp/DHL1', print_r($shipment, true));
        
        $response = $this->client->CreateShipmentDD($shipment);
        
        file_put_contents('tmp/DHL2', print_r($response, true));
        
        if (is_soap_fault($response) || $response->status->StatusCode != 0) {
            $this->errors[] = $response;
            $this->requestXML = $this->client->__getLastRequest();
            return false;
        } else {
            $r = array();
            $r['shipment_number'] = (String)$response->CreationState->ShipmentNumber->shipmentNumber;
            $r['piece_number'] = (String)$response->CreationState->PieceInformation->PieceNumber->licensePlate;
            $r['label_url'] = (String)$response->CreationState->Labelurl;

            return $r;
        }

    }


    /*
      function getVersion() {

        $this->buildClient();

        $this->log("Response: \n");

        $response = $this->client->getVersion(array('majorRelease' => '1', 'minorRelease' => '0'));

        $this->log($response);

      }
      */


    private function buildAuthHeader()
    {
        $head = $this->credentials;

        $auth_params = array(
            'user' => $this->credentials['api_user'],
            'signature' => $this->credentials['api_password'],
            'type' => 0

        );

        return new \SoapHeader('http://dhl.de/webservice/cisbase', 'Authentification', $auth_params);
    }
    
    /**
     * lost of wsdl functions:
     * [0] => CreateShipmentResponse createShipmentTD(CreateShipmentTDRequest $part1)
     * [1] => CreateShipmentResponse createShipmentDD(CreateShipmentDDRequest $part1)
    * [2] => DeleteShipmentResponse deleteShipmentTD(DeleteShipmentTDRequest $part1)
    * [3] => DeleteShipmentResponse deleteShipmentDD(DeleteShipmentDDRequest $part1)
    * [4] => DoManifestResponse doManifestTD(DoManifestTDRequest $part1)
    * [5] => DoManifestResponse doManifestDD(DoManifestDDRequest $part1)
    * [6] => GetLabelResponse getLabelTD(GetLabelTDRequest $part1)
    * [7] => GetLabelResponse getLabelDD(GetLabelDDRequest $part1)
    * [8] => BookPickupResponse bookPickup(BookPickupRequest $part1)
    * [9] => CancelPickupResponse cancelPickup(CancelPickupRequest $part1)
    * [10] => GetVersionResponse getVersion(Version $part1)
    * [11] => GetExportDocResponse getExportDocTD(GetExportDocTDRequest $part1)
    * [12] => GetExportDocResponse getExportDocDD(GetExportDocDDRequest $part1)
    * [13] => GetManifestDDResponse getManifestDD(GetManifestDDRequest $part1)
    * [14] => UpdateShipmentResponse updateShipmentDD(UpdateShipmentDDRequest $part1)
    * [15] => CreateShipmentResponse createShipmentTD(CreateShipmentTDRequest $part1)
    * [16] => CreateShipmentResponse createShipmentDD(CreateShipmentDDRequest $part1)
    * [17] => DeleteShipmentResponse deleteShipmentTD(DeleteShipmentTDRequest $part1)
    * [18] => DeleteShipmentResponse deleteShipmentDD(DeleteShipmentDDRequest $part1)
    * [19] => DoManifestResponse doManifestTD(DoManifestTDRequest $part1)
    * [20] => DoManifestResponse doManifestDD(DoManifestDDRequest $part1)
    * [21] => GetLabelResponse getLabelTD(GetLabelTDRequest $part1)
    * [22] => GetLabelResponse getLabelDD(GetLabelDDRequest $part1)
    * [23] => BookPickupResponse bookPickup(BookPickupRequest $part1)
    * [24] => CancelPickupResponse cancelPickup(CancelPickupRequest $part1)
    * [25] => GetVersionResponse getVersion(Version $part1)
    * [26] => GetExportDocResponse getExportDocTD(GetExportDocTDRequest $part1)
    * [27] => GetExportDocResponse getExportDocDD(GetExportDocDDRequest $part1)
    * [28] => GetManifestDDResponse getManifestDD(GetManifestDDRequest $part1)
    * [29] => UpdateShipmentResponse updateShipmentDD(UpdateShipmentDDRequest $part1)
     * 
     */

}

?>