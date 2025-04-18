<?php
/**
 * @author Ilya Khalizov
 * @descriprion model for cars with methods
 * @version 1.0
 * 
 * @var $_db
 * @var $_dbr
 * @var $_error
 * @var $_isNew
 * @var $data
 */

require_once 'PEAR.php';

class Car 
{
    private $_db;
    private $_dbr;
    private $_error;
    private $_isNew;
    private $data;
            
    function __construct(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $id = 0)
    {       
        $this->_db = $db;
        $this->_dbr = $dbr;
        
        $id = (int)$id;
        if (!$id) {
            $r = $this->_db->query("EXPLAIN cars");
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = new stdClass;
            while ($field = $r->fetchRow()) {
                $field = $field->Field;
                $this->data->$field = '';
            }
            $this->_isNew = true;
        } else {
            $r = $this->_db->query("SELECT * FROM cars WHERE id = " . $id);
            if (PEAR::isError($r)) {
                $this->_error = $r;
                return;
            }
            $this->data = $r->fetchRow();
            if (!$this->data) {
                $this->_error = PEAR::raiseError("Cars::Car : record $id does not exist");
                return;
            }
            $this->_isNew = false;
        }
    }

    function set($field, $value)
    {
        if (isset($this->data->$field)) {
            $this->data->$field = $value;
        }
    }

    function get($field)
    {
        if (isset($this->data->$field)) {
            return $this->data->$field;
        } else {
            return null;
        }
    }

    function update()
    {
        $query = '';
        if (!is_object($this->data)) {
            $this->_error = PEAR::raiseError('Cars::update : no data');
        }
        if ($this->_isNew) {
            $this->data->id = '';
        }
        
        foreach ($this->data as $field => $value) {
            if ($query) {
                $query .= ', ';
            }
            
            if ($this->_isNew && $field == 'id' ) {
                continue;
            }
            
            $query .= "`$field`='" . ($value == null ? null : mysql_escape_string($value)) . "'";
        }
        if ($this->_isNew) {
            $command = "INSERT INTO";
            $where = '';
        } else {
            $command = "UPDATE";
            $where = "WHERE id ='" . mysql_escape_string($this->data->id) . "'";
        }
        
        $r = $this->_db->query("$command cars SET $query $where");
        if (PEAR::isError($r)) {
            $this->_error = $r;
        }
        
        if ($this->_isNew) {
            $this->data->id = mysql_insert_id();
        }
        
        return $this->data->id;
    }
    
    function delete()
    {
        $this->_db->query("DELETE FROM cars WHERE id = " . $this->data->id);
    }
    
    /**
     * @description method for getting all cars
     * 
     * @var $query
     * @var $list
     * @var DB $db
     * @var DB $dbr
     * @var $where 
    */
    static function getList(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $where = '')
    {
        $query = "
            SELECT 
                cars.*
                , " . (1 * Config::get($db, $dbr, 'carweightCfactor')) . "*weight AS weight1
                , ROUND(" . (1 * Config::get($db, $dbr, 'carvolumeCfactor')) . "*height_int*width_int*length_int/1000000,2) AS volume1
                , (SELECT max(km) FROM car_petrol WHERE car_id = cars.id) km_reading
            FROM cars "
                . ($where != '' ? (" WHERE " . $where) : '') .
                " ORDER BY name";
        $list = $dbr->getAll($query);
        if (PEAR::isError($list)) {
            echo '<pre>'; echo $query;
            echo '<pre>'; print_r($list);
            return;
        }
        return $list;
    }
    
    /**
     * @description get car's information by id
     * 
     * @var $query
     * @var $item
     * @var DB $db
     * @var DB $dbr
    */
    static function getById(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $id)
    {
        $query = "SELECT * FROM cars WHERE id = " . $id;
        $item = $dbr->getRow($query);
        if (PEAR::isError($item)) {
            echo '<pre>'; echo $query;
            echo '<pre>'; print_r($item);
            return;
        }
        return $item;
    }

    /**
     * @description method makes car inactive
     * 
     * @var DB $db
     * @var DB $dbr
     * @var $ids 
    */
    static function toggleInactive(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr, $ids) {
        $db->query("UPDATE cars SET inactive = IF(inactive,0,1) WHERE id IN ($ids)");
    }
    
    /**
     * @description alarm
     * @var $query
     * @var $item
     * @var DB $dbr
     * @var $id
     */
    static function getAlarm(MDB2_Driver_mysql $dbr, $loggedUser, $id)
    {
        $query = "
            SELECT 
                IFNULL(alarms.type,'cars') `type`
                , cars.id type_id
                , alarms.status
                , alarms.date
                , alarms.comment
            FROM cars
            LEFT JOIN alarms ON alarms.type='cars' 
                AND alarms.type_id = cars.id 
                AND alarms.username = '" . $loggedUser->get('username') . "'
            WHERE cars.id=" . $id ;
        $item = $dbr->getRow($query);
        if (PEAR::isError($item)) {
            echo '<pre>'; echo $query;
            echo '<pre>'; print_r($item);
            return;
        }
        return $item;
    }
    
    /** @description alarm
     * @var $query
     * @var $list
     * @var DB $dbr
     * @var $id
     */
    static function getAlarms(MDB2_Driver_mysql $dbr, $id)
    {
        $query = "SELECT 
            alarms.id
            , IFNULL(alarms.type,'cars') `type`
            , cars.id type_id
            , alarms.status, alarms.date, alarms.comment
            , (select updated FROM total_log WHERE table_name='alarms' AND tableid=alarms.id limit 1) created
            , alarms.username
        FROM cars
        JOIN alarms ON alarms.type='cars' 
            AND alarms.type_id=cars.id
        WHERE cars.id = " . $id;
        $list = $dbr->getAll($query);
        if (PEAR::isError($list)) {
            echo '<pre>'; echo $query;
            echo '<pre>'; print_r($list);
            return;
        }
        return $list;
    }
    
    /** @description comments
     * @var DB $dbr
     * @var DB $db
     * @var $id
     */
    static function getCarComments(MDB2_Driver_mysql $db, MDB2_Driver_mysql $dbr,  $id)
    {
        return getComments($db, $dbr, 'cars', $id);
    }
}

