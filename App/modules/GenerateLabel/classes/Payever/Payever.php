<?php
/**
 * @copyright innerfly
 * @date      26.12.2016 16:01
 */

namespace label\Payever;


class Payever
{
    private $payever_id;
    private $payever_secret;
    private $url = 'https://mein.payever.de';
    private $_request;
    private $_responce;
    private $_status;
    private $token;


    public function __construct()
    {
        global $db, $dbr;

        $shopCatalogue = new \Shop_Catalogue($db, $dbr);
        $sellerInfo = new \SellerInfo($db, $dbr, $shopCatalogue->_shop->username);

        $this->payever_id = $sellerInfo->data->payever_id;
        $this->payever_secret = $sellerInfo->data->payever_secret;
    }

    public function getResponce()
    {
        return $this->_responce;
    }

    private function do_post($uri, $method = 'get', $data = [], $headers = [])
    {
        $url = $this->url . $uri;
        $this->_request = $data;

        $header = ["Content-type: application/x-www-form-urlencoded"];
        if(count($headers)){
            $header = array_merge($header, $headers);
        }

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        if ($method == 'post') {
            curl_setopt($curl, CURLOPT_POST, true);
            if (count($data)) {
                $data = http_build_query($data, '', '&');
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
            }
        }
        $result = curl_exec($curl);
        $this->_status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $this->_responce = json_decode($result, true);
        curl_close($curl);

        return $this->checkError($uri);
    }

    private function checkError($url)
    {
        if ($this->_status != 200) {
            $this->_error = '';
            foreach ($this->_responce['ErrorDetail'] as $v) {
                $this->_error .= "$url: $v\n";
            }
            return false;
        } else {
            return true;
        }
    }

    private function getToken($scope)
    {
        $data = [
            'client_id' => $this->payever_id,
            'client_secret' => $this->payever_secret,
            'grant_type' => "http://www.payever.de/api/payment",
            'scope' => $scope,
        ];
        if($this->do_post('/oauth/v2/token', 'post', $data)){
            $this->token = $this->_responce['access_token'];
            return true;
        } else {
            return false;
        }
    }
    
    public function createPayment($data)
    {
        $this->getToken("API_CREATE_PAYMENT");
        $data['channel'] = 'other_shopsystem';
        $data['access_token'] = $this->token;
        if($this->do_post('/api/payment', 'post', $data)){
            return true;
        } else {
            return false;
        }
    }

    public function retrievePayment($id)
    {
        $this->getToken("API_PAYMENT_INFO");
        $headers = ["Authorization: Bearer $this->token"];
        if($this->do_post("/api/payment/$id", 'get', [], $headers)){
            return true;
        } else {
            return false;
        }
    }

}