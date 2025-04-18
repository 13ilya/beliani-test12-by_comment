<?php
/**
 * @author Alexander Rubtsov <RubtsovAV@gmail.com>
 * Date: 06.10.2017
 * Time: 15:31
 */

trait ModelHasFindBy
{
    /**
     * @param string $tableName The table name for the SELECT query.
     *
     * @param array $where      The conditions for the SELECT query.
     * Example
     * [
     *   'state' => 'delivering',
     *   'delivered_at' => null,
     *   ['paid_at', 'IS NOT NULL'],
     *   ['name', 'LIKE', 'Article %'],
     *   ['LENGTH(`field`) > 3']
     * ]
     * It will be converted to this
     * "WHERE `state` = 'delivering'
     *      AND `delivered_at` IS NULL
     *      AND `paid_at` IS NOT NULL
     *      AND `name` LIKE 'Article %'
     *      AND LENGTH(`field`) > 3
     * "
     *
     * @return array Array with rows as assoc arrays from the table
     */
    public static function simpleFindBy()
    {
        $tableName = func_get_arg(0);
        $conditions = func_get_arg(1);

        $quotedTableName = static::quote($tableName);
        $where = static::conditionsToSqlWhere($conditions);

        if (!empty($where)) {
            $where = 'WHERE ' . $where;
        }

        $limit = static::conditionsToSqlLimit();
        $order = static::conditionsToSqlOrder();
        
        $sql = "
            SELECT *
            FROM $quotedTableName
            $where
            $order
            $limit
        ";

        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        $result = $dbr->getAll($sql);
        static::assertDbResult($result);

        return $result;
    }

    /**
     * @param string $tableName The table name for the SELECT query.
     *
     * @param array $where      The conditions for the SELECT query.
     * Example
     * [
     *   'state' => 'delivering',
     *   'delivered_at' => null,
     *   ['paid_at', 'IS NOT NULL'],
     *   ['name', 'LIKE', 'Article %'],
     *   ['LENGTH(`field`) > 3']
     * ]
     * It will be converted to this
     * "WHERE `state` = 'delivering'
     *      AND `delivered_at` IS NULL
     *      AND `paid_at` IS NOT NULL
     *      AND `name` LIKE 'Article %'
     *      AND LENGTH(`field`) > 3
     * "
     *
     * @return array Array with model instances
     */
    public static function findBy()
    {
        $tableName = func_get_arg(0);
        $conditions = func_get_arg(1);

        $quotedTableName = static::quote($tableName);
        $where = static::conditionsToSqlWhere($conditions);

        if (!empty($where)) {
            $where = 'WHERE ' . $where;
        }
        
        $limit = static::conditionsToSqlLimit();
        $order = static::conditionsToSqlOrder();

        $sql = "
            SELECT *
            FROM $quotedTableName
            $where
            $order
            $limit
        ";
        
        // we can't use findBySql for the Model class, because it has another constructor (with 3 arguments)
        if (get_called_class() != 'Model') {
            $items = static::findBySql($sql);
        } else {
            $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

            $result = $dbr->getAll($sql);
            static::assertDbResult($result);

            $items = [];
            foreach ($result as $itemData) {
                $items[] = new Model($tableName, $itemData, false);
            }
        }

        return $items;
    }

    /**
     * @param string $tableName The table name for the SELECT query.
     *
     * @param array $where      The conditions for the SELECT query.
     * Example
     * [
     *   'state' => 'delivering',
     *   'delivered_at' => null,
     *   ['paid_at', 'IS NOT NULL'],
     *   ['name', 'LIKE', 'Article %'],
     *   ['LENGTH(`field`) > 3']
     * ]
     * It will be converted to this
     * "WHERE `state` = 'delivering'
     *      AND `delivered_at` IS NULL
     *      AND `paid_at` IS NOT NULL
     *      AND `name` LIKE 'Article %'
     *      AND LENGTH(`field`) > 3
     * "
     *
     * @return array Array with model instances
     */
    public static function countBy()
    {
        $tableName = func_get_arg(0);
        $conditions = func_get_arg(1);

        $quotedTableName = static::quote($tableName);
        $where = static::conditionsToSqlWhere($conditions);

        if (!empty($where)) {
            $where = 'WHERE ' . $where;
        }
        
        $sql = "
            SELECT COUNT(*) AS count
            FROM $quotedTableName
            $where
        ";
        
        // we can't use findBySql for the Model class, because it has another constructor (with 3 arguments)
        if (get_called_class() != 'Model') {
            $result = static::findBySql($sql);
            $result = (int)$result[0]->count;
        } else {
            $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
            $result = $dbr->getOne($sql);
            static::assertDbResult($result);
        }
        
        return $result;
    }

    /**
     * @param string $sql The SELECT SQL-query to get the models data from a database.
     *
     * @return static[]   Array with model instances
     */
    protected static function findBySql($sql)
    {
        $sql = (string)$sql;
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        $result = $dbr->getAll($sql);
        static::assertDbResult($result);

        $items = [];
        foreach ($result as $itemData) {
            $items[] = new static($itemData, false);
        }

        return $items;
    }

    /**
     * Returns the first model by the conditions or NULL when it not found.
     *
     * @param string $tableName
     *  The table name for the SELECT query.
     *
     * @param integer|array $conditions
     *  The conditions for the SELECT query.
     *  When it is an integer, then it will used as the model's ID.
     *  When it is an array, then it will used as the where conditions.
     *
     *  Example with an array
     *  [
     *    'state' => 'delivering',
     *    'delivered_at' => null,
     *    ['paid_at', 'IS NOT NULL'],
     *    ['name', 'LIKE', 'Article %'],
     *    ['LENGTH(`field`) > 3']
     *  ]
     *  It will be converted to this
     *  "WHERE `state` = 'delivering'
     *       AND `delivered_at` IS NULL
     *       AND `paid_at` IS NOT NULL
     *       AND `name` LIKE 'Article %'
     *       AND LENGTH(`field`) > 3
     *  "
     *
     * @return null|static The model instance or NULL when it not found
     */
    protected static function firstBy()
    {
        $tableName = func_get_arg(0);
        $conditions = func_get_arg(1);

        if (is_numeric($conditions)) {
            $table = ModelTableFactory::getTableInstance($tableName);
            $autoIncrementFieldName = $table->getAutoIncrementColumnName();

            $conditions = [
                $autoIncrementFieldName => (int)$conditions,
            ];
        }

        $quotedTableName = static::quote($tableName);
        $where = static::conditionsToSqlWhere($conditions);
        
        if (!empty($where)) {
            $where = 'WHERE ' . $where;
        }
        
        $sql = "
            SELECT *
            FROM $quotedTableName
            $where
            LIMIT 1
        ";

        // we can't use findBySql for the Model class, because it has another constructor (with 3 arguments)
        if (get_called_class() != 'Model') {
            $items = static::findBySql($sql);
        } else {
            $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

            $result = $dbr->getAll($sql);
            static::assertDbResult($result);

            $items = [];
            foreach ($result as $itemData) {
                $items[] = new Model($tableName, $itemData, false);
            }
        }

        if (empty($items)) {
            return null;
        }

        return $items[0];
    }

    /**
     * Returns the first model by the conditions or throw an exception when it not found
     *
     * @param string $tableName
     *  The table name for the SELECT query.
     *
     * @param integer|array $conditions
     *  The conditions for the SELECT query.
     *  When it is an integer, then it will used as the model's ID.
     *  When it is an array, then it will used as the where conditions.
     *
     *  Example with an array
     *  [
     *    'state' => 'delivering',
     *    'delivered_at' => null,
     *    ['paid_at', 'IS NOT NULL'],
     *    ['name', 'LIKE', 'Article %'],
     *    ['LENGTH(`field`) > 3']
     *  ]
     *  It will be converted to this
     *  "WHERE `state` = 'delivering'
     *       AND `delivered_at` IS NULL
     *       AND `paid_at` IS NOT NULL
     *       AND `name` LIKE 'Article %'
     *       AND LENGTH(`field`) > 3
     *  "
     *
     * @return static
     * @throws \ModelNotFoundException When the model not found
     */
    public static function firstOrFail()
    {
        $tableName = func_get_arg(0);
        $conditions = func_get_arg(1);

        $result = self::firstBy($tableName, $conditions);
        if ($result === null) {
            $modelClassName = get_called_class();
            throw new ModelNotFoundException($modelClassName, $conditions);
        }

        return $result;
    }
}