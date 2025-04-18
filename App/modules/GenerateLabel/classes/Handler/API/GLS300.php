<?php
/**
 * @author  Dmytroped
 * @version 1.0.0
 */
namespace label\Handler\API;

use label\Handler\HandlerAbstract;

/**
 * Class GLS used in class HandlerFabric
 *
 * @package label\Handler\API
 */
class GLS300 extends HandlerAbstract
{
    private $track_and_trace_user = '2760307439';
    private $track_and_trace_password = '910010585';
    private $gls_customer_id = '2760307439';
    private $gls_contact_id = '276a45eGCG';
    private $gls_сustomer_number  = '910010585';
    private $web_portal_name = '2760307439';
    private $shipments_api_url = 'https://api.gls-group.eu/public/v1/shipments';
    private $ref_number;
    private $sender;
    private $isTest;
    private $realOrderItems = [];
    private $articles = [];
    private $weightTotal = 0;
    private $shipmentBody;
    
    public function __construct()
    {
        $this->isTest = (
            strpos($_SERVER['HTTP_HOST'], 'dev.') ||
            strpos($_SERVER['HTTP_HOST'], 'heap.')
        ) ? true : false;
        $this->isTest = false;
        if($this->isTest) {
            $this->shipments_api_url = 'https://api-qs.gls-group.eu/public/v1/shipments';
            $this->gls_customer_id = "2764200001";
            $this->gls_contact_id = "276a17aFXh";
            $this->track_and_trace_user = 'shipmentsapi';
            $this->track_and_trace_password = 'shipmentsapi';
        }

        $this->sender = array(
            'company_name' => 'Business Control Establishment',
            'company_name2' => 'Lagerhaus Beliani',
            'street_name' => 'Am Twistelmoor',
            'street_number' => '2',
            'zip' => '29649',
            'city' => 'Wietzendorf', // Wietzendorf
            'country' => 'D',
        );
    }

    public function action($continue = false)
    {
        $this->getOrderArticles();
        $this->getRefNumber();
        $this->shipmentBody();
        $result = $this->shipmentRequest();

        $output = '';
        if(count($result->errors)){
            foreach ($result->errors as $error) {
                $output .= "<li>$error->exitCode: $error->exitMessage. $error->description</li>";
            }
            die($output);
        }
        if(count($result->labels)){
            foreach ($result->labels as $key => $parcel) {
                $parcelNumber = $result->parcels[0]->trackId;
                $label = base64_decode($result->labels[$key]);
                $id = $this->saveLabel($parcelNumber, $label);
                $output .= "<li><a href='doc.php?auction_label=$id' target='_blank'>Download label #" .  ($key + 1) . "</a></li>";
            }
            if (count($result->labels) > 1) {
                echo $output;
            }
            elseif(count($result->labels) == 1) {
                header("Content-Type: application/pdf");
                header("Content-disposition: inline; filename=label.pdf");
                echo $label;
            }
            die();
        }

        /*        $data = $this->getLabelContent();
                $pdf = $this->getPDF($data);
                $this->saveLabel($this->ref_number, $pdf);
                
                if($continue) return $pdf;
        
                header("Content-type: application/pdf");
                header("Content-disposition: inline; filename=label.pdf");
                die($pdf);*/
    }

    private function getOrderArticles(){
        $allorder = \Order::listAll($this->request_params['DB'], $this->request_params['DB'],
            $this->auction_params->get('auction_number'), $this->auction_params->get('txnid'), 1,
            $this->auction_params->getMyLang(), '0,1', 1);

        $articles = [];
        $qty_total = 0;
        
        foreach ($allorder as $key => $order) {
            if (
                $order->article_id != 0 && 
                $order->admin_id == 0 && 
                !in_array($order->article_id, $this->articlesToExclude())
            ) {
                $this->realOrderItems[$order->article_id] = $order;
                $article = new \Article($this->request_params['DB'], $this->request_params['DB'], $order->article_id);
                if(
                    stripos($article->data->title, 'carton') !== false
                    || stripos($article->data->title, 'cartoon') !== false
//                  || stripos($article->data->title, 'karton') !== false
                ){
                    unset($allorder[$key]);
                    continue;
                };
                $article->quantity = $order->quantity;
                $article->weight = $article->parcels[0]->weight_parcel / $article->data->items_per_shipping_unit;
                $this->weightTotal += $article->weight;

                $qty = $order->quantity / $article->data->items_per_shipping_unit;
                $qty_total += $qty;
                $num_labels = ceil($qty);

                for ($i = 0; $i < $num_labels; $i++){
                    $articles[$order->article_id . "_" . $i] = $article;
                }
            }
        }

        $labels_ll = $this->request_params['number_of_labels_ll'];
        if(!$labels_ll){
            $this->articles = $articles;
        }
        else {
            for($i = 0; $i < $labels_ll; $i++){
                $article = new \stdClass();
                $article->weight = $this->weightTotal / $labels_ll;
                $article->quantity = 1;
                $this->articles[] = $article;
            }
        }

    }

    private function shipmentBody(){
        $shipperId = "$this->gls_customer_id $this->gls_contact_id";
        $tel = $this->auction_params->get('cel_shipping') ? $this->auction_params->get('cel_shipping') : $this->auction_params->get('tel_shipping');

        $data = [
            "shipperId" => $shipperId,
            "shipmentDate" => date('Y-m-d'),
            "references" => [$this->ref_number],
            "addresses" => [
                "delivery" => [
                    "name1" => $this->auction_params->get('firstname_shipping') . " " . $this->auction_params->get('name_shipping'),
                    "name2" => '',
                    "street1" => $this->auction_params->get('street_shipping') . " " . $this->auction_params->get('house_shipping'),
                    "country" => CountryToCountryCode($this->auction_params->get('country_shipping')),
                    "zipCode" => $this->auction_params->get('zip_shipping'),
                    "city" => $this->auction_params->get('city_shipping'),
                    "contact" => $this->auction_params->get('firstname_shipping') . ' ' . $this->auction_params->get('name_shipping'),
                    "email" => $this->auction_params->get('email_shipping'),
                    "phone" => $tel,
                ],
                "return" => [
                    "name1" => $this->sender['company_name'],
                    "street1" => $this->sender['street_name'] . ' ' . $this->sender['street_number'],
                    "country" => "DE",
                    "zipCode" => $this->sender['zip'],
                    "city" => $this->sender['city'],
                    "contact" => $this->sender['company_name'],
                ],
            ],
            "incoterm" => "10",
        ];

        $data["parcels"] = [];

        if ($this->isCod()) {
            $count_to_divide = count($this->articles) ? count($this->articles) : 1;
            $invoice = $this->auction_params->getMyInvoice();
            $openAmountParcel = number_format($invoice->get("open_amount") / $count_to_divide, 2, '.', '');
        }

        if(count($this->articles)){
            foreach ($this->articles as $key => $article) {
                $parcel = [
                    "weight" => $article->weight,
                    "references" => [$this->ref_number . "-" . $key],
                ];
                if ($this->isCod()) {
                    $parcel["services"][] = [
                        "name" => "cod",
                        "infos" => [
                            ["name" => "amount", "value" => $openAmountParcel,],
                            ["name" => "reference", "value" => $this->ref_number,],
                        ],
                    ];
                }
                $data["parcels"][] = $parcel;
            }
        }
        else {
            $parcel = [
                "weight" => 1,
                "references" => [$this->ref_number],
            ];
            if ($this->isCod()) {
                $parcel["services"][] = [
                    "name" => "cod",
                    "infos" => [
                        [
                            "name" => "amount",
                            "value" => $openAmountParcel,
                        ],
                        [
                            "name" => "reference",
                            "value" => $this->ref_number,
                        ],
                    ],
                ];
            }
            $data["parcels"][] = $parcel;
        }

        $decoded = json_encode($data);
        $this->shipmentBody = $decoded;
//        echo '<pre>' . print_r($data, TRUE) . '</pre><hr>'; 
//        die();
    }

    private function shipmentRequest(){
        $headers = [
            "Accept-Language: en",
            "Accept: application/json",
            "Content-type: application/json",
        ];

        $ch = curl_init($this->shipments_api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->shipmentBody);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->track_and_trace_user:$this->track_and_trace_password");
        $result = curl_exec ($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
        curl_close ($ch);

        return json_decode($result);
    }

    private function getRefNumber(){
        $auction_number = $this->auction_params->data->auction_number;
        $txnid = $this->auction_params->data->txnid;
        $shipping_method_id = $this->request_params['method']->data->shipping_method_id;

        $q = "SELECT COUNT(tracking_number) FROM auction_label WHERE auction_number={$auction_number} AND txnid={$txnid} AND shipping_method_id = $shipping_method_id";
        $counter = $this->request_params['DB']->getOne($q);
        $counter += 1;

        $this->ref_number = $auction_number . '/' . $txnid . '-' . $counter;
    }

}