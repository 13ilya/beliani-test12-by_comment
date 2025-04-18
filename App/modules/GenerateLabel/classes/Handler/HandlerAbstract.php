<?php
namespace label\Handler;

use label\Logging;
use label\Config;

abstract class HandlerAbstract
{

    /** @var Logger_Interface */
    protected $logger;
    /** @var HandlerRequest */
    protected static $Action;
    /** @var Config */
    protected $config;
    /** @var array - Request params */
    protected $request_params = array();
    /** @var array - Auctions params */
    protected $auction_params = array();

    /**
     * Handler_Abstract constructor.
     * @param Config $config
     */
    public function __construct()
    {

    }

    /**
     * @param Config
     * @return $this
     */

    public function setLogger(Config $config)
    {

        // set path and name of the log file (optionally)
        $this->logger = new Logging($config->getLogDirectory() . 'request.log');
        return $this;

    }


    /**
     *  To validate request params
     * @param: array $var_arr
     * @return boolean
     */
    private function validate($var_arr, $name = 'auction')
    {



        if($name == 'auction') {
            $value = $var_arr;
        }
        else {
            $name = $var_arr[0];
            $value = $var_arr[1];
        }

        switch ($name) {

            case 'auction':

                $this->auction_params = $value;
                break;
            case 'request':

//                $valid = filter_var($value, FILTER_VALIDATE_EMAIL);
//                if (!$valid) {
//                    throw new \InvalidArgumentException("Email is not valid: " . $name . " Value: " . $value);
//                }
                $this->request_params[$name] = $value;
                break;
            default:

                $this->request_params[$name] = $value;

        }

        return true;
    }

    public function validError($name, $value)
    {
        throw new \InvalidArgumentException("Wrong parameter! Name: " . $name . " Value: " . $value);
    }

    public function setAuction($auction)
    {
        if ($this->validate($auction)) {
            return true;
        } else {
            header('HTTP/1.1 400 BAD_REQUEST');
            throw new \InvalidArgumentException("Setup non specific params");
        }
    }

    public function getAuction($name)
    {
        return $this->auction_params[$name];
    }


    public function setParam($name, $value)
    {
        if ($this->validate(array($name, $value), 'request')) {
            return $this;
        } else {
            header('HTTP/1.1 400 BAD_REQUEST');
            throw new \InvalidArgumentException("Setup non specific params");
        }

    }

    public function getParam($name)
    {
        return $this->auction_params[$name];
    }


    public function setParams($params)
    {
        foreach ($params as $key => $value) {
            $this->setParam($key, $value);

        }
    }

    public function getParams()
    {
        return $this->auction_params;
    }

    /**
     * @param array $request
     * @return $this
     */
    public function setAuctionParams($request)
    {
        try {
            if (count($request) > 0) {
                $this->setAuction($request);
            } else {
                throw new \InvalidArgumentException("Required params are absent!");
            }
        } catch (\Exception $e) {
            header('HTTP/1.1 400 BAD_REQUEST');
            $this->logger->logError($e->getMessage());
            die();
        }

        return $this;
    }

    /**
     * @param array $request
     * @return $this
     */
    public function setRequestParams($request)
    {
        try {
            if (count($request) > 0) {
                $this->setParams($request);
            } else {
                throw new \InvalidArgumentException("Required params are absent!");
            }
        } catch (\Exception $e) {
            header('HTTP/1.1 400 BAD_REQUEST');
            $this->logger->logError($e->getMessage());
            die();
        }

        return $this;
    }

    /**
     * @param Config $config
     * @return $this
     */
    public function setConfig(Config $config)
    {
        $this->config = $config;
        $this->registry = $config->getRegistry();
        return $this;
    }

    /**
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    abstract public function action();

    /**
     * Store label and log to DB
     * @param string $PackNumber
     * @param string $pdf
     *
     * @return mixed ID of the inserted row
     */
    protected function saveLabel($PackNumber, $pdf){
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);

        $md5 = md5($pdf);
        $filename = set_file_path($md5);
        if (!is_file($filename)) file_put_contents($filename, $pdf);

        $q = "INSERT INTO auction_label 
        SET auction_number={$this->auction_params->data->auction_number},
        txnid={$this->auction_params->data->txnid},
        tracking_number='$PackNumber',
        doc='$md5', 
        shipping_method_id={$this->request_params['method']->data->shipping_method_id}";
        $db->query($q);
        $last_rec = $db->queryOne('SELECT LAST_INSERT_ID()');

//        $q = "INSERT INTO printer_log 
//        SET log_date = NOW(), 
//        auction_number={$this->auction_params->data->auction_number}, 
//        txnid={$this->auction_params->data->txnid}, 
//        username='{$this->request_params['loggedUser']->data->username}', 
//        action='Print label $PackNumber'";
//        $db->query($q);

        return $last_rec;
    }

    /**
     * Check if auction payment method is cache on delivery
     */
    protected function isCod(){
        $is_cod = false;
        $shipping_method_id = $this->request_params['method']->get('shipping_method_id');
        $LabelsCount = $this->auction_params->getLabelsCount($shipping_method_id);
        if ($this->auction_params->get('payment_method') == '2' && !$LabelsCount) {
            $is_cod = true;
        }
        
        return $is_cod;
    }

    /**
     * Articles to exclude from shipping labels
     */
    protected function articlesToExclude(){
        
        $article_id_to_exclude = [
            /*3352,
            3353,
            779963,
            88812,
            89255,
            88810,
            109784,
            3888,
            999779,
            300163,*/
        ];
        
        return $article_id_to_exclude;
    }
}
