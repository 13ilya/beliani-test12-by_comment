<?php

class Config {
    static function initialize($db = null, $dbr = null)
    {
        if (isset($GLOBALS['CONFIGURATION'])) {
            return;
        }
        
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        $r = $dbr->getAll("SELECT * FROM config");
        if (PEAR::isError($r)) {
            aprint_r($r);
            return;
        }

        foreach ($r as $row) {
            if (in_array($row->name, ['problem', 'bi_seller', 'full_backup_hrs', 'full_backup_hrs2', 'full_backup_hrs3', 'full_backup_hrs4'
                        , 'full_backup_hrs5', 'full_backup_hrs6', 'full_backup_hrs7', 'full_backup_hrs8', 'full_backup_hrs9', 'full_backup_hrs10'])) {
                $GLOBALS['CONFIGURATION'][$row->name] = unserialize($row->value);
            } else {
                $GLOBALS['CONFIGURATION'][$row->name] = $row->value;
            }
        }
    }

    static function getAll($db = null, $dbr = null)
    {
        Config::initialize();
        return $GLOBALS['CONFIGURATION'];
    }

    static function set($db, $dbr, $field, $value)
    {
        Config::initialize();
        $GLOBALS['CONFIGURATION'][$field] = $value;
        
        $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        $db->execParam('REPLACE INTO config SET name=?, value=?', [$field, $value]);
    }

    static function get($db, $dbr, $field)
    {
        Config::initialize();
        return $GLOBALS['CONFIGURATION'][$field];
    }
}
