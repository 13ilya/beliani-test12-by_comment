<?php
namespace label\RedisCache;

//require_once 'util.php';
//require_once 'connect.php';

/**
 * Class RedisJob
 * Class works with queue of jobs to to go recache
 */
class RedisJob
{

    /**
     * @var bool
     */
    private static $emulate = false;

    /**
     * @var callable
     */
    private static $callbackMessage;

    /**
     * @var mixed[]
     */
    public $args = [];

    /**
     * Should redis actually recache or just emulate (used for debug)
     * @param bool $value
     */
    public static function setEmulate($value)
    {
        self::$emulate = (bool)$value;
    }

    /**
     * Set function to throw message up to called code
     * @param callable $function
     */
    public static function setCallbackMessage($function)
    {
        self::$callbackMessage = $function;
    }

    /**
     * Actually do job
     * @return bool
     * @throws JobException
     */
    public function perform()
    {
        $this->throwMessage('report', 'Start');
        if ($this->args['action'] === 'recache') {
            $this->recache($this->args['function']);
        } else {
            throw new JobException();
        }
        return true;
    }

    /**
     * @param string $function
     * @param string $shop_id
     * @param string $lang
     */
    private function recache($function)
    {
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        
        $db->disconnect();
        $db->connect();
        
        $dbr->disconnect();
        $dbr->connect();
        
        if (preg_match('/^~~(.*?)~~(.*?)~~(.*?)~~$/iu', $function, $matches)) {
            $shop_id = $matches[1];
            $fn = $matches[2];
            $lang = $matches[3];
            
            $fn = $this->parseFn($fn);
            if ( ! $fn) {
                $this->throwMessage('debug', 'Function not found');
            }
            
            $this->throwMessage('debug', 'shop: ' . $shop_id . ' |matched-lang: ' . $lang . 
                    ' |matched-fn: ' . $fn['fn'] . ' |matched-params: ' . print_r($fn['params'], true));
            
            if ($shop_id) {
                $shopCatalogue = new \Shop_Catalogue($db, $dbr, $shop_id, $lang);
            }
            
            $this->throwMessage('report', 'launch functions...');
            
            if (count(explode('::', $fn['fn'])) == 2) {
                array_unshift($fn['params'], $db, $dbr);
            }
            
            if ($shop_id && method_exists($shopCatalogue, $fn['fn'])) {
                $response = call_user_func_array([$shopCatalogue, $fn['fn']], $fn['params']);
            } else if (function_exists($fn['fn'])) {
                $response = call_user_func_array($fn['fn'], $fn['params']);
            } else if (count(explode('::', $fn['fn'])) == 2) {
                $response = call_user_func_array('\\' . $fn['fn'], $fn['params']);
            }
            $this->throwMessage('debug', 'Response: ' . print_r($response, true));
        } else {
            $this->throwMessage('report', 'Key is wrong');
        }
    }

    /**
     * 
     * @param string $fn
     * @return mixed
     */
    private function parseFn($fn) {
        if (preg_match('#(.+)\((.*)\)#iu', $fn, $matches)) {
            $params = $matches[2];
            $params = strpos($params, chr(0) !== false) ? explode(chr(0), $params) : $params;
            if ($params && is_array($params)) {
                return [
                    'fn' => $matches[1],
                    'params' => $params,
                ];
            }

            return [
                'fn' => $matches[1],
                'params' => explode(',', $matches[2]),
            ];
        }
        
        return false;
    }
    
    /**
     * Throw message back to called code
     * @param string $level
     * @param string $message
     */
    private function throwMessage($level, $message)
    {
        if (isset(self::$callbackMessage)) {
            call_user_func_array(self::$callbackMessage, ['level' => $level, 'message' => $message]);
        }
    }
}