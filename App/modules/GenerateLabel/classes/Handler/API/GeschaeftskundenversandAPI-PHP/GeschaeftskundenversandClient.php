<?php

const SANDBOXURL = 'https://cig.dhl.de/services/sandbox/soap';
const CREDFILEPATH = "./Einstellungen/zugangsdaten.ini";
const KEY_IS_USER = "IntraShip_user";
const KEY_IS_PASSWORD = "IntraShip_password";
const KEY_USER = "cig_user";
const KEY_PASSWORD = "cig_password";
const gkvWSDir = "./Geschaeftskundenversand/GeschaeftskundenversandWS/";


require_once 'Geschaeftskundenversand/GeschaeftskundenversandRequestBuilder.php';

// GeschaeftskundenversandWS-Klassen werden mit __autoload() hinzugefügt.

/*
 *
 * import WS-Stub mit:
 php wsdl2php.php \
 -i https://cig.dhl.de/cig-wsdls/com/dpdhl/wsdl/geschaeftskundenversand-api/1.0/geschaeftskundenversand-api-1.0.wsdl \
 -o ./Geschaeftskundenversand/GeschaeftskundenversandWS
 *
 */

function __autoload($class_name)
{
    // echo gkvWSDir . $class_name . ".php \n";
    if (file_exists(gkvWSDir . $class_name . '.php')) {

        require_once(gkvWSDir . $class_name . '.php');
        return;
    }
}


class GeschaeftskundenversandClient
{
    private $gkvRequestBuilder = null;
    private $cred_array = null;
    private $credentials = null;
    public $shipmentNumber = null;

    public function main()
    {
        $this->gkvRequestBuilder = new GeschaeftskundenversandRequestBuilder();
        $credinput = $this->readPasswordsMenuInput($this->credentials);

        echo "Using Sandbox Endpoint ... \n";
        echo "BasicAuth User    : " . $credinput->cig_user . " \n";
        echo "BasicAuth Password: " . $credinput->cig_password . " \n";

        $IntraShip = new SoapClient("https://cig.dhl.de/cig-wsdls/com/dpdhl/wsdl/geschaeftskundenversand-api/1.0/geschaeftskundenversand-api-1.0.wsdl",
            array('login' => $credinput->cig_user,
                'password' => $credinput->cig_password,
                'location' => $credinput->cig_endpoint,
                'soap_version' => SOAP_1_1));

        $devAuthHeader = null;

        $authentication = new AuthentificationType($credinput->is_user, $credinput->is_password, NULL, 0);

        $authHeader = new SoapHeader('http://dhl.de/webservice/cisbase', 'Authentification', $authentication);

        $IntraShip->__setSoapHeaders($authHeader);

        try {
            $input = 0;
            do {
                $input = $this->readMainMenuInput();
                switch ($input) {
                    case 1:
                        $this->runCreateShipmentDDRequest($IntraShip);
                        break;
                    case 2:
                        $this->runGetLabelDDRequest($IntraShip);
                        break;
                    case 3:
                        $this->runDeleteShipmentDDRequest($IntraShip);
                        break;
                }
                if ($input != 0) {
                    echo 'Bitte, drücken Sie die Eingabe-Taste um fortzufahren.';
                    $handle = fopen("php://stdin", "r");
                    $line = fgets($handle);
                }
            } while ($input != 0);

        } catch (Exception $e) {
            echo $e;
        }
    }


    private function runCreateShipmentDDRequest($IntraShip)
    {

        try {
            $ddRequest = $this->gkvRequestBuilder->createDefaultShipmentDDRequest();
            $shResponse = $IntraShip->__soapCall(createShipmentDD, array($ddRequest));
            $GLOBALS["shipmentNumber"] = $shResponse->CreationState->ShipmentNumber->shipmentNumber;
            //var_dump($shResponse);
            //Response status
            $status = $shResponse->status;
            $statusCode = $status->StatusCode;
            $statusMessage = $status->StatusMessage;


            //Label URL
            $crState = $shResponse->CreationState;
            echo "CreateShipmentDDRequest: \n" .
                "Request Status: Code: " . $statusCode . "\n" .
                "Status-Nachricht: " . $statusMessage . "\n" .
                "Label URL: " . $crState->Labelurl . "\n" .
                "Sendungsnummer: " . $crState->ShipmentNumber->shipmentNumber . "\n";
        } catch (Exception $e) {
            echo 'Exception: ', $e->getMessage(), "\n";
            echo 'Exception: ', $e->getTraceAsString(), "\n";
        }
    }


    private function runGetLabelDDRequest($IntraShip)
    {
        //Sendungsnummer als Parameter setzen
        $ddRequest = $this->gkvRequestBuilder->getDefaultLabelDDRequest($GLOBALS["shipmentNumber"]);
        $lblResponse = $IntraShip->__soapCall(getLabelDD, array($ddRequest));

        //Response status
        $status = $lblResponse->Status;
        $statusMessage = $lblResponse->status->StatusMessage;
        $lblDataList = $lblResponse->LabelData;

        echo "getLabelDDRequest: \n" .
            "Status-Nachricht: " . $statusMessage . "\n";


        if (is_array($lblDataList)) {
            foreach ($lblDataList as &$lblData) {
                $shNumber = $lblData->ShipmentNumber;
                $lblStat = $lblData->Status;
                echo "Sendungsnummer: " . $shNumber->ShipmentNumber->shipmentNumber . "\n" .
                    "Status: " . $lblStat->Status->StatusMessage . "\n" .
                    "Label URL: " . $lblData->Labelurl . "\n";
            }
        } else {
            echo "Sendungsnummer: " . $lblDataList->ShipmentNumber->shipmentNumber . "\n" .
                "Status: " . $lblDataList->Status->StatusMessage . "\n" .
                "Label URL: " . $lblDataList->Labelurl . "\n";
        }
        unset($value);
    }


    private function runDeleteShipmentDDRequest($IntraShip)
    {
        //Sendungsnummer als Parameter setzen
        $ddRequest = $this->gkvRequestBuilder->getDeleteShipmentDDRequest($GLOBALS["shipmentNumber"]);
        $delResponse = $IntraShip->__soapCall(deleteShipmentDD, array($ddRequest));

        //Response status
        $status = $delResponse->Status;
        $statusMessage = $status->StatusMessage;
        $delStates = $delResponse->DeletionState;

        echo "deleteShipmentDDRequest: \n" .
            "Status-Nachricht: " . $statusMessage . "\n";


        if (is_array($delStates)) {
            foreach ($delStates as &$delState) {
                $shNumber = $delState->ShipmentNumber;
                $delStateStatus = $delState->Status;
                echo "Sendungsnummer: " . $shNumber . "\n" .
                    "Status: " . $delStateStatus->StatusMessage . "\n" .
                    "Status-Code: " . $delStateStatus->StatusCode . "\n";
            }
        } else {

            echo "Sendungsnummer: " . $delStates->ShipmentNumber->shipmentNumber . "\n" .
                "Status: " . $delStates->Status->StatusMessage . "\n" .
                "Status-Code: " . $delStates->Status->StatusCode . "\n";
        }
    }

    function __construct()
    {

        date_default_timezone_set("Europe/Berlin");
        error_reporting(0);
        $this->createDefaultCredentials();
        $this->credentials =
            new Credentials(
                $this->cred_array[KEY_IS_USER],
                $this->cred_array[KEY_IS_PASSWORD],
                $this->cred_array[KEY_USER],
                $this->cred_array[KEY_PASSWORD],
                SANDBOXURL);
        $this->gkvRequestBuilder = new GeschaeftskundenversandRequestBuilder();
    }

    public function createDefaultCredentials()
    {
        $this->cred_array = parse_ini_file(CREDFILEPATH);
    }

    private function readMainMenuInput()
    {

        echo "Geschaeftskundenversand Operationen: " . "\n" .
            "1 - runCreateShipmentDDRequest" . "\n" .
            "2 - runGetLabelDDRequest" . "\n" .
            "3 - runDeleteShipmentDDRequest" . "\n" .
            "0 - Programm beenden" . "\n";
        echo "Waehlen Sie die gewünschte Operation: \n";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        if (trim($line) == '0') {
            echo "Ende.\n";
            exit;
        } else {
            return $num = (int)$line;
        }

    }

    private function readPasswordsMenuInput($credentials)
    {
        echo "Wollen Sie mit folgenden Parametern weiterarbeiten? \n";
        echo "User       : " . $credentials->cig_user . " \n";
        echo "Password   : " . $credentials->cig_password . " \n";
        echo "Y/n        : ";
        if (strcasecmp(trim(fgets(STDIN)), 'n') == 0) {
            echo "Geben Sie Ihre Entwickler ID ein: \n";
            $cig_user = trim(fgets(STDIN));
            echo "Geben Sie Ihr Password ein: \n";
            $cig_password = trim(fgets(STDIN));
            return new Credentials($credentials->is_user, $credentials->is_password, $cig_user, $cig_password, SANDBOXURL);
        } else {
            return $credentials;
        }
    }
}

class Credentials
{

    /**
     *
     * @var String $is_user
     * @access public
     */
    public $is_user;

    /**
     *
     * @var String $is_password
     * @access public
     */
    public $is_password;

    /**
     *
     * @var String $cig_user
     * @access public
     */
    public $cig_user;

    /**
     *
     * @var String $cig_password
     * @access public
     */
    public $cig_password;

    /**
     *
     * @var String $cig_endpoint
     * @access public
     */
    public $cig_endpoint;

    /**
     *
     * @param String $is_user
     * @param String $is_password
     * @param String $cig_user
     * @param String $cig_password
     * @param String $cig_endpoint
     * @access public
     */
    public function __construct($is_user, $is_password, $cig_user, $cig_password, $cig_endpoint)
    {
        $this->is_user = $is_user;
        $this->is_password = $is_password;
        $this->cig_user = $cig_user;
        $this->cig_password = $cig_password;
        $this->cig_endpoint = $cig_endpoint;
    }
}


$gkvClient = new GeschaeftskundenversandClient();
$gkvClient->main();