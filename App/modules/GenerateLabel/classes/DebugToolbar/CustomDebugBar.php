<?php
namespace label\DebugToolbar;

use \DebugBar\DebugBarException;
use \DebugBar\DataCollector\DataCollectorInterface;
use \DebugBar\DataCollector\PDO\PDOCollector;

/**
 * Class CustomDebugBar cover StandardDebugBar
 */
class CustomDebugBar extends \DebugBar\StandardDebugBar
{
    private $databaseCollector;

    /**
     * Cover parent method addCollector.
     * Used to refresh collector.
     * @param DataCollectorInterface $collector
     * @return $this
     * @throws DebugBarException
     */
    public function addCollector(DataCollectorInterface $collector)
    {
        if ($this->hasCollector($collector->getName())) {
            $this->removeCollector($collector->getName());
        }
        return parent::addCollector($collector);
    }

    /**
     * Remove collector from collector list
     * @param string $name
     */
    private function removeCollector($name)
    {
        unset($this->collectors[$name]);
    }

    /**
     * Return collector for database access
     * @return PDOCollector
     */
    public function getDatabaseCollector()
    {
        if (!isset($this->databaseCollector)) {
            $this->databaseCollector = new PDOCollector();
        }
        return $this->databaseCollector;
    }
}