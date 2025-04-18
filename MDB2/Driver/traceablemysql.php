<?php

include_once __DIR__.'/mysql.php';

/**
 * Class MDB2_Driver_traceablemysql
 * Extends MDB2_Driver_mysql to trace all sql queries.
 */
class MDB2_Driver_traceablemysql extends MDB2_Driver_mysql
{
    private $traceProvider;

    /**
     * MDB2_Driver_TraceableMysql constructor.
     * Enhance parent constructor.
     * @todo devide Mysql-* names for reading and writing
     */
    public function __construct()
    {
        parent::__construct();
        $this->traceProvider = new \label\DebugToolbar\TraceablePDOEmulator();
        $debugToolbar = \label\DebugToolbar\DebugToolbar::getInstance();
        $databaseCollector = $debugToolbar->getDatabaseCollector();
        static $increment = 1;
        $databaseCollector->addConnection($this->traceProvider, 'Mysql-'.$increment++);
        $debugToolbar->addCollector($databaseCollector);
    }

    /**
     * Cover parent function query to trace all queries.
     * @param string $query
     * @param array $types default null. Array that contains the types of the columns in
     *      the result set
     * @param string|bool $result_class default true. specifies which result class to use
     * @param string|bool $result_wrap_class default true. Specifies which class to wrap results in
     * @return mixed an MDB2_Result handle on success, a MDB2 error on failure
     * @todo collect number of rows affected, deleted, returned
     * @todo collect another data supported in DebugBar
     */
    public function query($query, $types = null, $result_class = true, $result_wrap_class = true)
    {
        $trace = new DebugBar\DataCollector\PDO\TracedStatement($query);
        $trace->start();

        $result = parent::query($query, $types, $result_class, $result_wrap_class);

        $trace->end();
        $this->traceProvider->addExecutedStatement($trace);

        return $result;
    }

}