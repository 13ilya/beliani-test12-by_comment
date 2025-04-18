<?php
namespace label\DebugToolbar;

/**
 * Class TraceablePDOEmulator
 * Emulator for non-pdo db connection to trace sql queries.
 */
class TraceablePDOEmulator extends \DebugBar\DataCollector\PDO\TraceablePDO
{
    /**
     * TraceablePDOEmulator constructor.
     * Rewrite parent contructor
     */
    public function __construct() {}
}