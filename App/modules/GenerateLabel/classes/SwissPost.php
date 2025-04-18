<?php
/**
 * @copyright innerfly
 * @date      30.12.2016 10:01
 */

namespace label;

require_once('connect.php');
require_once('lib/ShopCatalogue.php');

class SwissPost
{
    public $swiss_post_id;
    private $swiss_post_secret;
    private $url = 'https://wedec.post.ch';
//    private $url = 'https://wedecint.post.ch';
    private $token;
    public $userInfo;
    public $currentAddress;

    public function __construct()
    {
        global $db, $dbr;

        $shopCatalogue = new \Shop_Catalogue($db, $dbr);
//        $sellerInfo = new \SellerInfo($db, $dbr, $shopCatalogue->_shop->username);
        
        $this->swiss_post_id = $shopCatalogue->_shop->swiss_post_id;
        $this->swiss_post_secret = $shopCatalogue->_shop->swiss_post_secret;
    }

    public function authorization()
    {
        $params = array(
            'scope' => 'WEDEC_READ_ADDRESS+openid+profile+email+address',
            'response_type' => 'code',
            'redirect_uri' => 'https://' . $_SERVER['HTTP_HOST'] . '/?login_sp=1',
//            'redirect_uri' => 'https://' . str_replace('www.', '', $_SERVER['HTTP_HOST']) . '/?login_sp=1',
            'client_id' => $this->swiss_post_id,
        );
        $param_str = urldecode(http_build_query($params));
        $redirect_url = $this->url . '/WEDECOAuth/authorization?' . $param_str;
        
        return $redirect_url;
    }

    public function getToken($code)
    {
        $params = [
            'client_id' => $this->swiss_post_id,
            'client_secret' => $this->swiss_post_secret,
            'grant_type' => "authorization_code",
//            'grant_type' => "client_credentials",
            'redirect_uri' => 'https://' . $_SERVER['HTTP_HOST'] . '/?login_sp=1',
//            'redirect_uri' => 'https://' . $_SERVER['HTTP_HOST'] . '/checkout_register.php?login_sp=1',
//            'redirect_uri' => 'https://' . str_replace('www.', '', $_SERVER['HTTP_HOST']) . '/?login_sp=1',
            'code' => $code,
        ];
        
        $param_str = urldecode(http_build_query($params));
        $ch = curl_init($this->url . "/WEDECOAuth/token");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $param_str);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec ($ch);
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
        curl_close ($ch);
        $decoded = json_decode($result); 
        $this->token = $decoded->access_token;
        
        return $this->token;
        }

    public function getUserInfo()
    {
        $ch = curl_init($this->url . "/userinfo/");
        $headers = [
            "Content-Type:application/json",
            "Authorization: Bearer $this->token",
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $res = curl_exec($ch);
        curl_close($ch);
        $this->userInfo = json_decode($res);
        
        return $this->userInfo;
        }
        
    public function getCurrentAddress()
    {
        $url = $this->url . "/api/address/v1";
//        $url = "https://wedec.post.ch/api/address/v1/user/current/addresses";
        $headers = [
            "http" => [
                "header" => "Authorization: Bearer $this->token",
            ]
        ];
        $context = stream_context_create($headers);
        $address = file_get_contents($url, false, $context);
        $this->currentAddress = json_decode($address);

        return $this->currentAddress;
    }

}