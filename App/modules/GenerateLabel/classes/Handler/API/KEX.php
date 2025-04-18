<?php
namespace label\Handler\API;

use Dompdf\Exception;
use label\Config;
use label\Handler\HandlerAbstract;

require_once 'lib/Auction.php';
require_once 'lib/Order.php';
require_once 'lib/Article.php';

class KEX extends HandlerAbstract
{
    private $WSDL_URL;
    private $KEY_AUTH;
    private $client_id;
    
    public function action($continue = false)
    {
        $test = 0;
        if ($test) {
            $this->WSDL_URL = 'http://test-express.k-ex.pl/api/ws.php?wsdl';
            $this->KEY_AUTH = 'ae5a7279-bb88-4316-88a2-81e911699ff3';
            $this->client_id = 'CK0015240';
        } else {
            $this->WSDL_URL = 'http://kurier.k-ex.pl/api/ws.php?wsdl';
            $this->KEY_AUTH = 'c332f300-4e62-4880-b7a5-4995c2585122';
            $this->client_id = 'CK0184285';
        }
//        echo '<pre>' . print_r(htmlentities($this->getParcelXML()), TRUE) . '</pre>';
//        die;
//        echo '<pre>' . print_r($this->auction_params, TRUE) . '</pre><hr>'; 
//        die();
        
        $client = new \SoapClient(
            $this->WSDL_URL,
            array(
                'features' => SOAP_SINGLE_ELEMENT_ARRAYS,
                'trace' => 1,
            )
        );
        try{
            $pdf = $client->__soapCall("CallMethod", array($this->KEY_AUTH, $this->getParcelXML()));
        } catch (\SoapFault $e){
            print $e->getMessage() ."<hr>";
            print $client->__getLastRequest() ."<hr>";
            print $client->__getLastResponse();
        }
        
        $xml = new \SimpleXMLElement($pdf);
        if(!$number = $xml->Wyniki->Przesylka->NumerPrzesylki){
            $error = '<pre>' . print_r($xml, TRUE) . '</pre>';
            if(!$continue) die($error);
        }

        $xml = $client->__soapCall("CallMethod", array($this->KEY_AUTH, $this->getLabelXML($number)));
        $xmlObject = new \SimpleXMLElement($xml);
        if(!$pdf = $xmlObject->{'Wyniki'}->{'WydrukEtykiet'}->{'Dane'}){
            $auc_num = $this->auction_params->get('auction_number') . "/" . $this->auction_params->get('txnid');
            $error = '<p>Auction ' . $auc_num . ', shipping method ' . $this->request_params['method']->get('company_name') . '. Error description:</p>';
            $error .= '<pre>' . print_r($xmlObject, TRUE) . '</pre>';
            if(!$continue){
                die($error);
            }
            else {
                $_SESSION['messages']['errors'][$auc_num] = $error;
                return false;
            }
        }

        $pdf = base64_decode($pdf);

        $this->saveLabel($number, $pdf);
        
        if ($continue) return $pdf;

        header("Content-type: application/pdf");
        header("Content-disposition: inline; filename=label.pdf");
        echo $pdf;
        exit();
    }
    
    private function getParcelXML()
    {
        /*
        <n_os_nadajaca>" . $this->auction_params->get("firstname_invoice") . " " . $this->auction_params->get('name_invoice') . "</n_os_nadajaca>
        <n_tel_st>" . $this->auction_params->get("tel_invoice") . "</n_tel_st>
        <n_tel_gsm/>
        <n_email>" . $this->auction_params->get('email_invoice') . "</n_email>
        <N_NAZWA>" . $this->auction_params->get('firstname_invoice') . "</N_NAZWA>
        <N_ULICA>" . $this->auction_params->get('street_invoice') . "</N_ULICA>
        <N_MIEJSCOWOSC>" . $this->auction_params->get('city_invoice') . "</N_MIEJSCOWOSC>
        <N_KOD_POCZTOWY>" . $this->auction_params->get('zip_invoice') . "</N_KOD_POCZTOWY>
        <N_NR_DOMU>". $this->auction_params->get('house_invoice') ."</N_NR_DOMU>
        <N_NIP/>
        <email_dla_wpr/>
        */

        $english = $this->auction_params->getMyTranslation();
        $invoice = $this->auction_params->getMyInvoice();
        $seller = $this->auction_params->getMySeller();
        $shipping_method_id = $this->request_params['method']->get('shipping_method_id');
        $LabelsCount = $this->auction_params->getLabelsCount($shipping_method_id);
        $domu = strlen($this->auction_params->get('house_shipping')) <= 10 ? 
            $this->auction_params->get('house_shipping') :
            substr($this->auction_params->get('house_shipping'), 0, 9);

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
                $weight_total += $weight;
                $quantity = ceil($order->quantity / $article->data->items_per_shipping_unit);
                $quantity_total += $quantity;
//                $parcels[] = [ // produces 1/n label
//                    'weight' => $weight,
//                    'quantity' => $quantity,
//                ];
            }
        }
        $labels = $this->request_params['number_of_labels_ll'] ? $this->request_params['number_of_labels_ll'] : 1;
        // produce amount of pages specified here
        // http://proloheap.prologistics.info/mobile.php?branch=pl&step=3&warehouse_id=107&ramp_id=11
        for($i = 0; $i < $labels; $i++){
        $parcels[] = [ // produces 1/1 label
                'weight' => $weight_total / $labels,
                'quantity' => 1,
            ];
        }
        
        
        $weight_details = $this->weightIndex($parcels);
        
        $company = trim($this->auction_params->get('company_shipping')) ? trim($this->auction_params->get('company_shipping')) : " ";
        $address = $company . " " . $this->auction_params->get('firstname_shipping') . " " . $this->auction_params->get('name_shipping');
        $address = preg_replace('~[„”]+~', ' ', $address); // Removes special chars
        $zip = str_replace([' ', '-'], '', $this->auction_params->get('zip_shipping'));
        $zip = substr_replace($zip, '-', 2, 0);

        $dane = "<Dane>
<NazwaMetody>DodajPrzesylki</NazwaMetody>
<Parametry>
<Przesylka>
<usluga>E</usluga>
<zleceniodawca>{$this->client_id}</zleceniodawca>
<platnik>ZL</platnik>
<N_CK>{$this->client_id}</N_CK>
$weight_details
<O_CK/>
<o_os_pryw>N</o_os_pryw>
<o_nazwa>" . $address . "</o_nazwa>
<o_ulica>" . $this->auction_params->get('street_shipping') . "</o_ulica>
<o_miejscowosc>" . $this->auction_params->get('city_shipping') . "</o_miejscowosc>
<o_kod_pocztowy>" . $zip . "</o_kod_pocztowy>";
        
$tel_shipping = $this->auction_params->data->tel_shipping_formatted;
$cel_shipping = $this->auction_params->data->cel_shipping_formatted;

if(strlen($tel_shipping) < 6 || strlen($cel_shipping) < 6){
    if(strlen($tel_shipping) < 6 && strlen($cel_shipping) > 6) $tel_shipping = $cel_shipping;
    if(strlen($cel_shipping) < 6 && strlen($tel_shipping) > 6) $cel_shipping = $tel_shipping;
}

$dane .= "<o_tel_st>$tel_shipping</o_tel_st>";
$dane .= "<o_tel_gsm>$cel_shipping</o_tel_gsm>";
        
$dane .= "
<o_email>" . $this->auction_params->get('email_shipping') . "</o_email>
<O_NR_DOMU>" . $domu . "</O_NR_DOMU>";

        if ($this->auction_params->get('payment_method') == '2' && $LabelsCount == 0) {
            $dane .= "<u_pobranie>T</u_pobranie>
<u_wart_pobrania>" . $invoice->get("open_amount") . "</u_wart_pobrania>
<u_rach_pobrania>" . str_replace(' ', '', $seller->get("bank_account")) . "</u_rach_pobrania>";
        } else {
            $dane .= "<u_pobranie/>
<u_wart_pobrania/>
<u_rach_pobrania/>";
        }
        
        $dane .= "
<u_ubezp>N</u_ubezp>
<u_dost_aw_mail>N</u_dost_aw_mail>
<opis>parcel</opis>
<nr_przesylki></nr_przesylki>
<UWAGI>" . $english[65] . ' ' . $this->auction_params->get('auction_number') . '/' . $this->auction_params->get('txnid') . "</UWAGI>
<lp>Zamowienie: " . $this->auction_params->get('auction_number') . "</lp>
</Przesylka>
</Parametry>
</Dane>";

        return $dane;
    }

    private function getLabelXML($number)
    {
        $dane = "<Dane>
<NazwaMetody>PobierzWydrukiEtykiet</NazwaMetody>
<Parametry>
<Format>Z</Format>
<NumerPrzesylki>$number</NumerPrzesylki>
</Parametry>
</Dane>";

        return $dane;
    }

    private function weightIndex($parcels){
        $output = '';
        $weight_index = [];
        
        foreach ($parcels as $parcel) {
            $weight = $parcel['weight'];
            $qty = $parcel['quantity'];
            if($weight > 0 && $weight <= 1){$weight_index['E_1'] += $qty;}
            elseif($weight > 1 && $weight <= 5){$weight_index['E_5'] += $qty;}
            elseif($weight > 5 && $weight <= 10){$weight_index['E_10'] += $qty;}
            elseif($weight > 10 && $weight <= 15){$weight_index['E_15'] += $qty;}
            elseif($weight > 15 && $weight <= 20){$weight_index['E_20'] += $qty;}
            elseif($weight > 20 /*&& $weight < 30*/){$weight_index['E_30'] += $qty;}
        }

        foreach ($weight_index as $index => $amount) {
            $output .= "<$index>$amount</$index>\r\n";
        }
        
        return $output;
    }

}
