<?php
abstract class SavedEntity {
    /**
    * Reference to database
    * @var object
    */
    protected $_db;
	protected $_dbr;
	/**
	 * Fields that was changed
	 */
	protected $_changed_fields;
	/**
	 * SA id
	 */
	public $id;
	/**
	 * Data
	 */ 
	public $data = array();
	/**
	 * Options
	 * @var array
	 */ 
	public $options = array();
	/**
	 * Constructor
	 */
	public function __construct($id, MDB2_Driver_mysql $db = null, MDB2_Driver_mysql $dbr = null)
	{	
		$this->id = (int)$id;
		if (!$this->id)
            return false;
//			throw new Exception('ERROR SA ID');
			
        if ( ! $db) {
            $db = \label\DB::getInstance(\label\DB::USAGE_WRITE);
        }
                
        if ( ! $dbr) {
            $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        }
                
		$this->_db = $db;
		$this->_dbr = $dbr;
		$this->_load();
	}
	/**
	 * Load entity data
	 */
	abstract protected function _load();
	/**
	 * Save current data
	 */
	abstract public function save();
	/**
	 * Return all data
	 */
	abstract public function get();

}