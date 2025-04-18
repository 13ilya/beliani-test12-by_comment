<?php

/**
 * @author Dmytro Ped
 * @verion 1.0
 * Date: 06.10.16
 * Time: 14:36
 *
 * Class for management of the notification of the seller
 *
 */
class SellerNotification
{
    private $_sellerId;
    /**
     * @var array
     */
    private $_listUserNotify = [];
    /**
     * @var MDB2_Driver_mysql
     */
    private $_db;
    /**
     * @var MDB2_Driver_mysql
     */
    private $_dbr;
    /**
     * @var array
     */
    private $_disableUser = [];
    private $_activeUser = [];
    private static $_notifyType = [];

    public function __construct($seller_id)
    {
        $this->_sellerId = $seller_id;
        $this->_db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $this->_dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        $res = $this->getListNotification();
        foreach ($res as $v){
            $this->_listUserNotify[$v['code']] = [];
        }
        $res = $this->_dbr->getAll(
            "select u.id as user_id,u.username,snt.code from 
seller_notif as sn
 left join users as u on u.id=sn.user_id
 left join seller_notif_type as snt on snt.id=sn.notif_type_id
where seller_id={$seller_id}"
        );
        foreach ($res as $v){
            if(!isset($this->_listUserNotify[$v->code])){
                $this->_listUserNotify[$v->code] = [];
            }
            $this->_listUserNotify[$v->code][] = (int)$v->user_id;
        }
    }
    /**
     * Update users
     */
    public function save()
    {
        foreach ($this->_listUserNotify as $index => $val){
            if( empty($this->_listUserNotify[$index]) ){
                $r = $this->_db->query("delete from seller_notif where seller_id={$this->_sellerId} and notif_type_id=(select id from seller_notif_type where code='{$index}')");
                if (PEAR::isError($r)) {
                    print_r($r);
                    die();
                }
            }else
            if( isset($this->_activeUser[$index]) ) {
                foreach ($this->_activeUser[$index] as $v) {
                    $r = $this->_dbr->getOne("select id from seller_notif where seller_id={$this->_sellerId} and user_id = {$v} and notif_type_id=(select id from seller_notif_type where code='{$index}')");
                    if (PEAR::isError($r)) {
                        print_r($r);
                        die();
                    }
                    if (!$r) {
                        $r = $this->_db->query("insert into seller_notif(seller_id,user_id,notif_type_id) values({$this->_sellerId},$v,(select id from seller_notif_type where code='{$index}'))");
                        if (PEAR::isError($r)) {
                            print_r($r);
                            die();
                        }
                    }
                }
                $r = $this->_db->query("delete from seller_notif where seller_id={$this->_sellerId} and user_id not in (" . implode(',', array_merge($this->_listUserNotify[$index], $this->_activeUser[$index])) . ") and notif_type_id=(select id from seller_notif_type where code='{$index}')");
                if (PEAR::isError($r)) {
                    print_r($r);
                    die();
                }
            }else{
                $r = $this->_db->query("delete from seller_notif where seller_id={$this->_sellerId} and user_id not in (" . implode(',', $this->_listUserNotify[$index]) . ") and notif_type_id=(select id from seller_notif_type where code='{$index}')");
                if (PEAR::isError($r)) {
                    print_r($r);
                    die();
                }
            }
        }
    }
    /**
     * Adds users to the table of the notification, all users who haven't been transmitted through this function will be removed.
     * @param $notif_type
     * @param $user_id
     */
    public function activeUser($notif_type, $user_id)
    {
        if( isset($this->_listUserNotify[$notif_type]) ){
            if( !isset($this->_activeUser[$notif_type]) ){
                $this->_activeUser[$notif_type] = [];
            }
            $this->_activeUser[$notif_type][] = (int)$user_id;

            if( in_array((int)$user_id, $this->_listUserNotify[$notif_type]) === FALSE ){
                $this->_listUserNotify[$notif_type][] = (int)$user_id;
            }
        }
    }
    /**
     * @param $notif_type
     * @param $user_id
    */
    public function disableUser($notif_type, $user_id)
    {
        if( isset($this->_listUserNotify[$notif_type]) ){
            $key = array_search($user_id, $this->_listUserNotify[$notif_type]);
            if($key !== FALSE) {
                array_splice($this->_listUserNotify[$notif_type], $key, 1);
            }
        }
    }
    /**
     * @return array
     */
    public function getListUserByNotify()
    {
        return $this->_listUserNotify;
    }

    /**
     * @return array
     */
    public static function getListNotification()
    {
        /**
         * @var MDB2_Driver_mysql $dbr
         */
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        $notif = $dbr->getAssoc('select id,code,title from seller_notif_type');
        if (PEAR::isError($notif)) { print_r($notif); die();}
        return $notif;
    }

    /**
     * @param $notif_type code of notification
     * @param $username username of user
     * @return string
     */
    public static function getEmailsBySellerUsername($notif_type, $username)
    {
        $notif = '';
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        $seller = $dbr->getOne("select id from seller_information where username='{$username}'");
        if (PEAR::isError($seller)) { print_r($seller); die();}
        if($seller) {
            $notif = $dbr->getOne("select GROUP_CONCAT(distinct u.email) as emails 
from seller_notif as sn 
join users as u on u.id=sn.user_id and u.deleted=0
join seller_notif_type as snt on snt.id=sn.notif_type_id and code='" . mysql_escape_string($notif_type) . "'
where sn.seller_id=$seller");
            if (PEAR::isError($notif)) {
                print_r($notif);
                die();
            }
        }
        return $notif;
    }

    /**
     * @param $code notify code
     * @return array
     */
    public function getListUserByCode($code)
    {
        $users = $this->_dbr->getAssoc('select distinct u.id, u.name, u.username, snt.code 
from seller_notif sn join users u on u.id=sn.user_id 
join seller_notif_type snt on snt.id = sn.notif_type_id and snt.code = "'.mysql_escape_string($code).'" 
where sn.seller_id='.$this->_sellerId);
        if (PEAR::isError($users)) { print_r($users); die();}
        return $users;
    }

}