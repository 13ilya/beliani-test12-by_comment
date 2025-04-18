<?php

require_once 'PEAR.php';

/**
 * Basic Model
 *
 * @author      Ilya Khalizov
 * @author      Alexander Rubtsov <RubtsovAV@gmail.com>
 */
class Model
{
    use ModelHasFindBy;

    /**
     * @var bool
     */
    protected $isNew;

    /**
     * @var \stdClass
     */
    protected $data;

    /**
     * @var array
     */
    protected $dirtyFields = [];

    /**
     * @var \MDB2_Driver_mysql
     */
    protected $_db;

    /**
     * @var \MDB2_Driver_mysql
     */
    protected $_dbr;

    /**
     * @var \ModelTable
     */
    protected $table;

    /**
     * @var array
     */
    protected $mulangFields = [];

    /**
     * @var array
     */
    protected $mulangFieldsUpdate = [];

    protected static $take_records = 0;

    protected static $skip_records = 0;

    protected static $orderby_records = '';

    protected static $sortby_records = '';

    protected static $_insert_ignore = false;

    /**
     * Model constructor.
     *
     * @param string    $tableName
     *  The table name for this model.
     *
     * @param int|array $data
     *  When it is an integer, then it will used as the model's ID
     *  and the model will be fill the data from the database table.
     *  When it is an array, then it will used as the model's data,
     *  without some additional SELECT-s queries to the database.
     *
     *
     * @param null|bool $isNew
     *  Set it to false, if you don't want to use INSERT query when you call the update method.
     *  By default it depends on the ID field: it will true, when the ID value is false.
     */
    public function __construct($tableName, $data = [], $isNew = null)
    {
        $this->data = new stdClass();

        static::assertTableName($tableName);
        $this->table = ModelTableFactory::getTableInstance($tableName);

        // The table must have an autoincrement, because it needs to define the $isNew (for backward compatibility)
        if (!$this->table->hasAutoIncrementColumn()) {
            throw new RuntimeException("The table '{$tableName}' must have an autoincrement column");
        }

        $this->_db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $this->_dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        if (is_numeric($data)) {
            $this->set($this->table->getAutoIncrementColumnName(), (int)$data);
            $this->fillDataFromDb();
        } else {
            foreach ((array)$data as $fieldName => $fieldValue) {
                $this->set($fieldName, $fieldValue);
            }
        }

        if (is_bool($isNew)) {
            $this->isNew = $isNew;
        } else {
            $this->isNew = ($this->getID() === null);
        }
    }

    /**
     * Fills the model by assoc array with a key as the field name and value as the field value.
     *
     * @param array $data   The assoc array with the model data.
     * @param bool $convertNullableToNull When TRUE, converts empty values to NULL for nullable columns.
     */
    public function fill($data, $convertNullableToNull = true)
    {
        $data = (array)$data;

        if ($convertNullableToNull) {
            $data = $this->convertNullableValuesToNull($data);
        }

        foreach ($data as $fieldName => $fieldValue) {
            $this->set($fieldName, $fieldValue);
        }
    }

    /**
     * @param $data
     *
     * @return mixed
     */
    protected function convertNullableValuesToNull($data)
    {
        foreach ($data as $fieldName => &$fieldValue) {
            if (empty($fieldValue) && $this->table->columnIsNullable($fieldName)) {
                $fieldValue = null;
            }
        }
        return $data;
    }

    /**
     * set value to a field
     * @param string $field
     * @param string $value
     *
     * @return object
     */
    public function set($field, $value)
    {
        $this->data->$field = $value;
        $this->markFieldAsDirty($field);

        return $this;
    }

    /**
     * get a field
     * @param $field
     *
     * @return null|string|integer|boolean
     */
    public function get($field)
    {
        if (isset($this->data->$field)) {
            return $this->data->$field;
        }

        return null;
    }

    /**
     * Returns data with all fields.
     *
     * @return \stdClass
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Returns the model's ID.
     *
     * @return int|null
     */
    public function getID()
    {
        return $this->get($this->table->getAutoIncrementColumnName());
    }

    /**
     * Markes the field as dirty.
     *
     * @param string $fieldName
     * @return void
     */
    public function markFieldAsDirty($fieldName)
    {
        $this->dirtyFields[$fieldName] = true;
    }

    /**
     * Returns TRUE if the field was changed by the 'set' method
     *
     * @param string $fieldName
     *
     * @return bool
     */
    public function fieldIsDirty($fieldName)
    {
        return isset($this->dirtyFields[$fieldName])
            && $this->dirtyFields[$fieldName] == true;
    }

    /**
     * Resets dirty flags.
     *
     * @param null|string $fieldName
     *  By default it NULL, which resets all dirty flags.
     *
     * @return bool
     */
    public function resetDirtyFlag($fieldName = null)
    {
        if ($fieldName === null) {
            $this->dirtyFields = [];
        } elseif (isset($this->dirtyFields[$fieldName])) {
            unset($this->dirtyFields[$fieldName]);
        }
    }

    /**
     * Saves the model data to the database.
     * @return void
     */
    public function update()
    {
        if (!is_object($this->data)) {
            throw new RuntimeException('The model does not have data');
        }

        $tableName = $this->table->getName();
        $filteredData = array_intersect_key((array)$this->data, $this->table->getColumns());

        //if flag isNew = true - it is insert query
        if ($this->isNew) {

            // We must be sure that the data have not an auto increment value.
            if ($this->table->hasAutoIncrementColumn()) {
                $autoIncrementFieldName = $this->table->getAutoIncrementColumnName();
                if (isset($filteredData[$autoIncrementFieldName])) {
                    unset($filteredData[$autoIncrementFieldName]);
                }
            }

            $quotedTableName = static::quote($tableName);
            $set = static::arrayToSqlSet($filteredData);

            $insert_ignore = $this->_insert_ignore ? ' IGNORE ' : '';

            $sql = "
                INSERT $insert_ignore INTO $quotedTableName
                SET $set
            ";
        } else {
            $dirtyData = array_intersect_key($filteredData, $this->dirtyFields);
            if (empty($dirtyData)) {
                return;
            }

            $keys = $this->getKeysForSaveQuery();

            $quotedTableName = static::quote($tableName);
            $where = static::conditionsToSqlWhere($keys);
            $set = static::arrayToSqlSet($dirtyData);

            $sql = "
                UPDATE $quotedTableName
                SET $set
                WHERE $where
            ";
        }
        $result = $this->_db->query($sql);



        static::assertDbResult($result);

        //if we have insert query - id became last inserted id to data base
        if ($this->isNew && $this->table->hasAutoIncrementColumn()) {
            $result = $this->_db->getOne("SELECT LAST_INSERT_ID()");
            static::assertDbResult($result);

            $this->set($this->table->getAutoIncrementColumnName(), (int)$result);
        }

        $this->isNew = false;
        $this->resetDirtyFlag();
    }

    /**
     * Deletes the model data from the database.
     * @return void
     */
    public function delete()
    {
        $tableName = $this->table->getName();
        $keys = $this->getKeysForSaveQuery();

        $quotedTableName = static::quote($tableName);
        $where = static::conditionsToSqlWhere($keys);

        $sql = "
            DELETE FROM $quotedTableName
            WHERE $where
        ";

        $result = $this->_db->query($sql);

        static::assertDbResult($result);
    }

    /**
     * get information about mulang fields
     *
     * @return array $res_array
     */
    public function mulang_fields_Get()
    {
        $tableName = $this->table->getName();

        $res_array = array();
        foreach ($this->mulangFields as $fld) {

            //get field language and id
            $result = $this->_dbr->getAssoc("select language, iid from translation where
                    " . get_tr_table_name($tableName) . "
                    AND " . get_tr_field_name($fld->name));
            foreach ($result as $language => $iid) {

                //get information about field from translation
                $result[$language] = $this->_dbr->getRow("select * from translation where iid=$iid");

                //get field last update
                $result[$language]->last_on =
                    $this->_dbr->getOne("select SUBSTRING_INDEX(GROUP_CONCAT(total_log.Updated order by total_log.Updated),',',-1) last_on
                    from translation
                    left join total_log on
                        " . get_table_name('translation') . "
                        and total_log.TableID=translation.iid
                    where 1
                    and (
                        " . get_field_name('value') . "
                        or (" . get_field_name('unchecked') . " and total_log.old_value=1 and total_log.new_value=0))
                    and translation.iid = $iid
                    group by translation.id
                    order by translation.id+1
                    ");

                //get who was last updater
                $result[$language]->last_by =
                    $this->_dbr->getOne("select SUBSTRING_INDEX(GROUP_CONCAT(IFNULL(users.name,'') order by total_log.Updated),',',-1) last_on
                    from translation
                    left join total_log on
                        " . get_table_name('translation') . "
                        and total_log.TableID=translation.iid
                    left join users on users.id=total_log.username_id
                    where 1
                    and (" . get_field_name('value') . "
                            or (" . get_field_name('unchecked') . " and total_log.old_value=1 and total_log.new_value=0))
                    and translation.iid = $iid
                    group by translation.id
                    order by translation.id+1
                    ");
            }
            $res_array[$fld->name . '_translations'] = $result;
        }
        return $res_array;
    }

    /**
     * update mulang fields
     * @return void
     */
    public function mulang_fields_Update()
    {
        $tableName = $this->table->getName();

        $source = !empty($this->mulangFieldsUpdate) ? $this->mulangFieldsUpdate : $_POST;
        if (empty($source)) {
            echo '<pre>';
            print_r(PEAR::raiseError("$tableName::update : no data"));
            return;
        }
        foreach ($this->mulangFields as $fld) {
            if (count($source[$fld->name])) {
                $changed = 0;
                foreach ($source[$fld->name] as $lang => $value) {
                    if ($lang == '0') $lang = '';
                    $query = "select iid from translation where
                            language = '$lang'
                            AND " . get_tr_table_name($tableName) . "
                            AND " . get_tr_field_name($fld->name) . "
                            ";
                    $iid = (int)$this->_db->getOne($query);
                    $value = mysql_real_escape_string($value);
                    if ($iid) {
                        if ($value !== '') {
                            $query = "update translation set value='$value'	where iid='$iid'";
                        } else {
                            $query = "delete from translation where iid='$iid'";
                        }
                    } else {
                        if ($value !== '') {
                            $query = "insert into translation set value='$value'
                            , id='$edit_id'
                            , " . get_tr_table_name($tableName, '') . "
                            , " . get_tr_field_name($fld->name, '') . "
                            , language = '$lang'";
                        }
                    }
                    $result = $this->_db->exec($query);
                    if (PEAR::isError($result)) aprint_r($result);
                    $affectedRows = $result;
                    $query = "update translation set `updated`=$affectedRows
                        where language='$lang'
                            AND " . get_tr_table_name($tableName) . "
                            AND " . get_tr_field_name($fld->name) . "
                            and id='$edit_id'";
                    $result = $this->_db->query($query);
                    if (PEAR::isError($result)) {
                        aprint_r($result);
                    }
                    $changed += $affectedRows;
                }
            }
            if ($changed) {
                $unchecked = $this->_dbr->getAssoc("select 0 f1,0 f2 union all
                    select iid, iid from translation where
                        not updated
                        AND " . get_tr_table_name($tableName) . "
                        AND " . get_tr_field_name($fld->name) . "
                        and id='$edit_id'");
                $query = "UPDATE translation SET `unchecked`=1	WHERE iid IN (" . implode(',', $unchecked) . ")";
                $result = $this->_db->query($query);
                if (PEAR::isError($result)) {
                    aprint_r($result);
                }
            }
        }
    }

    /**
     * Quote a string so it can be safely used as a table or column name.
     *
     * Example:
     * var_dump(quote('tableName')); // (string)"`tableName`"
     *
     * @param string $value
     *
     * @return string
     */
    public static function quote($value)
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        return $dbr->quoteIdentifier($value);
    }

    /**
     * Quotes a string so it can be safely used in a query. It will quote
     * the text so it can safely be used within a query.
     *
     * @param string $value
     *
     * @return string
     */
    public static function escape($value)
    {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        return $dbr->escape($value);
    }

    /**
     * get keys from a query
     * @return array
     */
    protected function getKeysForSaveQuery()
    {
        $keyNames = $this->table->getPrimaryColumnNames();

        if (empty($keyNames)) {
            $tableName = $this->table->getName();
            throw new RuntimeException("Primary keys is undefinded for table '{$tableName}'");
        }

        $result = [];
        foreach ($keyNames as $keyName) {
            $result[$keyName] = $this->get($keyName);
        }

        return $result;
    }

    /**
     * Fills the model's data from the DB table
     * @return object
     */
    protected function fillDataFromDb()
    {
        $tableName = $this->table->getName();
        $keys = $this->getKeysForSaveQuery();

        $quotedTableName = static::quote($tableName);
        $where = static::conditionsToSqlWhere($keys);

        if (!empty($where)) {
            $where = 'WHERE ' . $where;
        }

        $sql = "
            SELECT *
            FROM $quotedTableName
            $where
            LIMIT 1
        ";

        $result = $this->_dbr->getRow($sql);

        static::assertDbResult($result);

        $this->data = $result;
    }

    /**
     * @param $result
     *
     * @throws RuntimeException When the result is an error
     */
    protected static function assertDbResult($result)
    {
        if (PEAR::isError($result)) {
            throw new RuntimeException($result->getMessage(), $result->getCode());
        }
    }

    /**
     * @param string $tableName
     */
    protected static function assertTableName($tableName)
    {
        if (!$tableName) {
            $modelClassName = get_called_class();
            throw new RuntimeException("The table name is undefined for the '$modelClassName'");
        }

        if (!is_string($tableName)) {
            $modelClassName = get_called_class();
            throw new RuntimeException("The table name must be a string for the '$modelClassName'");
        }
    }

    /**
     * Converts a conditions to a string for use in the WHERE part of the SQL query.
     * Example
     * [
     *   'state' => 'delivering',
     *   'delivered_at' => null,
     *   ['paid_at', 'IS NOT NULL'],
     *   ['name', 'LIKE', 'Article %'],
     *   ['LENGTH(`field`) > 3']
     * ]
     * Returns
     * "`state` = 'delivering'
     *   AND`delivered_at` IS NULL
     *   AND `paid_at` IS NOT NULL
     *   AND `name` LIKE 'Article %'
     *   AND LENGTH(`field`) > 3"
     *
     * @param array $conditions An array which need to convert
     *
     * @return string Part ot the SQL-query which paste after WHERE
     */
    protected static function conditionsToSqlWhere($conditions)
    {
        $where = [];
        foreach ($conditions as $field => $value) {
            if (is_integer($field) && is_array($value) && count($value) >= 2) {
                $field = isset($value[3]) && $value[3] ? $value[0] : static::quote($value[0]);
                $operation = (string)$value[1];
                $value = isset($value[2]) ? $value[2] : null;
            } else {
                $field = static::quote($field);

                // sets default operation by the value type
                if (is_array($value)) {
                    $operation = 'IN';
                } elseif (is_null($value)) {
                    $operation = 'IS NULL';
                } else {
                    $operation = '=';
                }
            }

            // converts the value to an sql string
            switch (gettype($value)) {
                default:
                    $value = "'" . static::escape($value) . "'";
                    break;

                case 'NULL':
                    $value = '';
                    break;

                case 'array':
                    $value = array_map(function ($v) {
                        return "'" . static::escape($v) . "'";
                    }, $value);
                    $value = '(' . implode(',', $value) . ')';
                    break;
            }

            $where[] = "$field $operation $value";
        }

        return implode(' AND ', $where);
    }

    /**
     * add limit to a query
     * @return string
    */
    protected static function conditionsToSqlLimit()
    {
        $take = (int)static::$take_records;
        $skip = (int)static::$skip_records;

        $limit = '';
        if ($take > 0)
        {
            if ($skip > 0)
            {
                $limit = " LIMIT $skip, $take ";
            }
            else
            {
                $limit = " LIMIT $take ";
            }
        }

        return $limit;
    }

    /**
     * order and sort a query
     * @return string
    */
    protected static function conditionsToSqlOrder()
    {
        $orderby = '';
        if (self::$orderby_records)
        {
            $orderby = " ORDER BY " . static::escape(self::$orderby_records);
            if (self::$sortby_records)
            {
                $orderby .= " " . self::$sortby_records;
            }
        }

        return $orderby;
    }

    /**
     * Converts an assoc array to a string for use in the SET part of the SQL query.
     * Example
     * [
     *   'name' => 'Article #123',
     *   'state' => 'delivering',
     *   'delivered_at' => null,
     * ]
     * Returns
     * "`name` = 'Article #123', `state` = 'delivering', `delivered_at` = NULL"
     *
     *
     * @param array $data An array which need to convert
     *
     * @return string Part ot the SQL-query which paste after SET
     */
    protected static function arrayToSqlSet($data)
    {
        $set = [];
        foreach ($data as $field => $value) {

            $field = static::quote($field);

            switch (gettype($value)) {
                default:
                    $value = static::escape($value);
                    $set[] = "$field = '$value'";
                    break;

                case 'NULL':
                    $set[] = "$field = NULL";
                    break;
            }
        }

        return implode(',', $set);
    }

    /**
     * take amount of rows on page
     * @param integer $take
     * @return object
     */
    public static function take($take)
    {
        self::$take_records = (int)max(0, $take);
    }

    /**
     * skip rows
     * @param integer $skip
     * @return object
     */
    public static function skip($skip)
    {
        self::$skip_records = (int)max(0, $skip);
    }

    /**
     * order and sort query results
     * @param string $order
     * @param string $sort
     * @return object
     *
     */
    public static function orderBy($order, $sort = 'asc')
    {
        self::$orderby_records = $order;
        self::$sortby_records = strtolower($sort) == 'asc' ? 'ASC' : 'DESC';
    }
}
