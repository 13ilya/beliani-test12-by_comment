<?php
/**
 * @author Alexander Rubtsov <RubtsovAV@gmail.com>
 * Date: 06.10.2017
 * Time: 13:16
 */

/**
 * Class CoreModel for inheritance in other models
 */
abstract class CoreModel extends Model
{
    /**
     * @var string Must be defined in a child class. Contains table's name.
     */
    protected static $tableName;

    /**
     * The model constructor.
     *
     * @param int|array $data
     *  When it is an integer, then it will used as the model's ID
     *  and the model will be fill the data from the database table.
     *  When it is an array, then it will used as the model's data,
     *  without some additional SELECT-s queries to the database.
     *
     * @param null|bool $isNew
     *  Set it to false, if you don't want to use INSERT query when you call the update method.
     *  By default it depends on the ID field value: it will true, when the ID value is false.
     */
    public function __construct($data = [], $isNew = null)
    {
        static::assertTableName(static::$tableName);
        parent::__construct(static::$tableName, $data, $isNew);
    }

    /**
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
        static::assertTableName(static::$tableName);

        $conditions = func_get_arg(0);

        return parent::simpleFindBy(static::$tableName, $conditions);
    }

    /**
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
        static::assertTableName(static::$tableName);

        $conditions = func_get_arg(0);

        return parent::findBy(static::$tableName, $conditions);
    }

    /**
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
        static::assertTableName(static::$tableName);

        $conditions = func_get_arg(0);

        return parent::countBy(static::$tableName, $conditions);
    }

    /**
     * Returns the first model by the conditions or NULL when it not found
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
     * @return null|static  The model instance or NULL when it not found
     */
    public static function firstBy()
    {
        static::assertTableName(static::$tableName);

        $conditions = func_get_arg(0);

        return parent::firstBy(static::$tableName, $conditions);
    }

    /**
     * Returns the first model by the conditions or throw an exception when it not found
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
        static::assertTableName(static::$tableName);

        $conditions = func_get_arg(0);

        return parent::firstOrFail(static::$tableName, $conditions);
    }

    /**
     * take amount of rows on page
     * @param integer $take
     * @return object
    */
    public static function take($take)
    {
        $object = $this;
        if ( ! $object)
        {
            $object = "\\" . get_called_class();
            $object = new $object;
        }

        $object::$take_records = (int)max(0, $take);
        return $object;
    }

    /**
     * skip rows
     * @param integer $skip
     * @return object
    */
    public static function skip($skip)
    {
        $object = $this;
        if ( ! $object)
        {
            $object = "\\" . get_called_class();
            $object = new $object;
        }
        
        $object::$skip_records = (int)max(0, $skip);
        return $object;
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
        $object = $this;
        if ( ! $object)
        {
            $object = "\\" . get_called_class();
            $object = new $object;
        }
        
        $object::$orderby_records = $order;
        $object::$sortby_records = strtolower($sort) == 'asc' ? 'ASC' : 'DESC';
        
        return $object;
    }
}