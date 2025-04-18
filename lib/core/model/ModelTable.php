<?php
/**
 * @author Alexander Rubtsov <RubtsovAV@gmail.com>
 * Date: 06.10.2017
 * Time: 15:33
 */

class ModelTable
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var array
     */
    protected $columns;

    /**
     * ModelTable constructor.
     *
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Returns the table name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Returns the columns of the table.
     *
     * @return array Associative array with the column name as the key.
     */
    public function getColumns()
    {
        if (!$this->columns) {
            $this->columns = $this->parseTableColumns();
        }

        return $this->columns;
    }

    /**
     * get a column
     * @param string $name
     *
     * @return array|null
     */
    public function getColumn($name)
    {
        $columns = $this->getColumns();
        if (!isset($columns[$name])) {
            return null;
        }

        return $columns[$name];
    }

    /**
     * check if a column exists
     * @param $name
     *
     * @return bool
     */
    public function hasColumnWithName($name)
    {
        $columns = $this->getColumns();
        return isset($columns[$name]);
    }

    /**
     * check if a column is nullable
     * @param string $name
     *
     * @return bool
     */
    public function columnIsNullable($name)
    {
        $column = $this->getColumn($name);
        return $column && $column['isNullable'];
    }

    /**
     * @return bool
     */
    public function hasAutoIncrementColumn()
    {
        return $this->getAutoIncrementColumnName() !== null;
    }

    /**
     * get auto increment column
     * @return null|string
     */
    public function getAutoIncrementColumnName()
    {
        foreach ($this->getColumns() as $column) {
            if ($column['isAutoIncrement']) {
                return $column['name'];
            }
        }

        return null;
    }

    /**
     * get columns with primary keys
     * @return array
     */
    public function getPrimaryColumnNames()
    {
        $primaryKeys = [];
        foreach ($this->getColumns() as $column) {
            if ($column['isPrimaryKey']) {
                $primaryKeys[] = $column['name'];
            }
        }

        return $primaryKeys;
    }

    /**
     * get table schema
     * @return array
     */
    protected function parseTableColumns()
    {
        $tableName = $this->name;
        $cacheKey = "parseTableColumns($tableName)";
        $cached = cacheGet($cacheKey, '0', '');
        if ($cached) {
            return $cached;
        }

        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        $tableName = explode('.', $tableName, 2);
        if (count($tableName) == 2)
        {
            $tableSchema = "'" . $dbr->escape($tableName[0]) . "'";
            $tableName = $tableName[1];
        }
        else
        {
            $tableSchema = 'DATABASE()';
            $tableName = $tableName[0];
        }
        $escapedTableName = $dbr->escape($tableName);

        $debug_backtrace = 'REQUEST_URI: ' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] .
            ' HTTP_REFERER: ' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');

        $result = $dbr->getAssoc("
            SELECT /* $debug_backtrace */
                `columns`.COLUMN_NAME as `assoc_key`,
                `columns`.COLUMN_NAME as `name`,
                `columns`.DATA_TYPE as `dataType`,
                (`columns`.IS_NULLABLE = 'YES') as `isNullable`,
                (`columns`.`extra` = 'auto_increment') as `isAutoIncrement`,
				(`constraints`.CONSTRAINT_NAME IS NOT NULL) as `isPrimaryKey`
            FROM information_schema.`COLUMNS` as `columns`
            LEFT JOIN information_schema.`KEY_COLUMN_USAGE` as constraints
                ON (
                    constraints.TABLE_SCHEMA = `columns`.TABLE_SCHEMA
                    AND constraints.TABLE_NAME = `columns`.TABLE_NAME
                    AND constraints.COLUMN_NAME = `columns`.COLUMN_NAME
                    AND constraints.CONSTRAINT_NAME = 'PRIMARY'
                )
            WHERE
                columns.TABLE_SCHEMA = $tableSchema
                AND columns.TABLE_NAME = '$escapedTableName'
        ");
        static::assertDbResult($result);

        cacheSet($cacheKey, '0', '', $result);

        return $result;
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
}
