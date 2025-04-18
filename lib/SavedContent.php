<?php
require_once 'lib/SavedEntity.php';

class SavedContent extends SavedEntity
{
    private $_langs;
    
    public static $MULANG_FIELDS = 'content_values';
    
    public static $KINDS = [
        'full',
        'select',
        'digits',
    ];

    /**
     * Constructor
     * @param int $id
     * @param MDB2_Driver_mysql $db
     * @param MDB2_Driver_mysql $dbr
     * @param array $langs
     */
    public function __construct($id, $langs = null)
    {
        if (is_array($langs) && !empty($langs)) {
            $this->_langs = $langs;
        } else {
           $this->_langs = getLangsArray();
        }

        parent::__construct($id);
    }

    public static function listAll($only_active = false, $types = false) {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        $where = '';
        if ($only_active) {
            $where .= ' AND NOT `sa_content`.`inactive` ';
        }
        
//        if ($type_name)
//        {
//            $type_name = mysql_real_escape_string($type_name);
//            $q = "SELECT id AS `key`, id AS `values`
//                FROM sa_type 
//                WHERE title LIKE '%$type_name%' 
//                    AND NOT `inactive`
//                LIMIT 100";
//            $types_ids = $dbr->getAssoc($q);
//            
//            if ($types_ids)
//            {
//                $where .= " AND `sa_field_type`.`type_id` IN (" . implode(',', $types_ids) . ") ";
//            }
//        }
        
        if ($types && is_array($types))
        {
            $where .= " AND `sa_field_type`.`type_id` IN (" . implode(',', array_map('intval', $types)) . ") ";
        }

        $sa_content = $dbr->getAll("
                SELECT `sa_content`.* 
                FROM `sa_content` 
                LEFT JOIN `sa_content_field` ON `sa_content_field`.`content_id` = `sa_content`.`id`
                LEFT JOIN `sa_field_type` ON `sa_field_type`.`field_id` = `sa_content_field`.`field_id`
                WHERE 1
                $where
                GROUP BY `sa_content`.`id`
        ");
        
        return $sa_content;
    }

    /**
     * Return all data
     */
    public function get()
    {
        return $this->data;
    }
    
    private function getKindValues()
    {
        $type = $this->_dbr->getRow( "SHOW COLUMNS FROM `sa_content` WHERE Field = 'kind'" );
        preg_match("/^enum\(\'(.*)\'\)$/", $type->Type, $matches);
        return explode("','", $matches[1]);
    }
    
    /**
     * Loads all sa_content block data
     */ 
    protected function _load()
    {
        $this->data = $this->_dbr->getRow('SELECT * FROM `sa_content` WHERE `id` = ?', null, [$this->id]);
        
        $this->data->sa_fields->value = (string)$this->_dbr->getOne("SELECT GROUP_CONCAT(`field_id`)
                FROM `sa_content_field` WHERE `content_id` = '{$this->id}'");
        $this->data->sa_fields->value = explode(',', $this->data->sa_fields->value);
        
        $this->data->sa_fields->options = $this->_dbr->getAll('SELECT `id`, `name`
                FROM `sa_field` WHERE NOT `inactive`');
        
        $MULANG_FIELDS = $this->_dbr->getAssoc("SELECT `id` `k`, `id` `v` FROM `sa_content_value` WHERE `content_id` = '{$this->id}'");
        
        $mulang_data = mulang_fields_Get($MULANG_FIELDS, 'sa_content_value', $this->id, 1);
        foreach ($mulang_data as $field_key => $langs) {
            foreach ($langs as $lang => $data) {
                if (!in_array($lang, array_keys($this->_langs))) {
                    unset($mulang_data[$field_key][$lang]);
                }
            }
            
            // fill empty langs 
            foreach ($MULANG_FIELDS as $field)
            {
                $diff = array_diff(array_keys($this->_langs), array_keys($mulang_data[$field.'_translations']));
                foreach ($diff as $lang)
                {
                    $empty = new stdClass();
                    $empty->language = $lang;
                    $empty->value = '';
                    $empty->table_name = 'sa_content_value';
                    $empty->field_name = $field;
                    $mulang_data[$field.'_translations'][$lang] = $empty;
                }
            }
        }

		foreach($MULANG_FIELDS as $mulang_field) 
		{
			$this->data->content_values[$mulang_field] = $mulang_data[$mulang_field . '_translations'];
		}
        
        if ( ! isset($this->data->content_values)) {
            $this->data->content_values = new stdClass;
        }        
    }

	/**
	 * Set new data 
	 * @var array
	 */
	public function setData($in, $old = null)
	{
        if ( ! in_array($in['kind'], self::$KINDS)) {
            $in['kind'] = self::$KINDS[0];
        }
        
        $in['name'] = trim($in['name']);
        $in['alt_name'] = trim($in['alt_name']);
        $in['formula'] = trim($in['formula']);
        
        if (is_array($in['sa_fields']['value']))
        {
            $in['sa_fields']['value'] = array_map('intval', $in['sa_fields']['value']);
        }
        else 
        {
            $in['sa_fields']['value'] = explode(',', $in['sa_fields']['value']);
            $in['sa_fields']['value'] = array_map('intval', $in['sa_fields']['value']);
        }

        $in['sa_fields']['value'][] = 0;
        
        $sa_fields = $this->_dbr->getOne('SELECT GROUP_CONCAT(`id`) FROM `sa_field` 
                WHERE NOT `inactive` AND `id` IN (' . implode(',', $in['sa_fields']['value']) . ')');
        
        $in['sa_fields']['value'] = explode(',', $sa_fields);

		$this->_changed_fields = $in;
	}
    
    /**
     * Save current data
     */
    public function save()
    {
        if ( ! $this->id || ! $this->_dbr->getOne("SELECT id FROM sa_content WHERE id = '{$this->id}'"))
        {
            $this->_db->execParam("INSERT INTO `sa_content` (`id`, `name`, `alt_name`, `formula`, `kind`, `inactive`)
                VALUES (?, ?, ?, ?, ?, ?)", [null, $this->_changed_fields['name'], 
                    $this->_changed_fields['alt_name'], $this->_changed_fields['formula'], 
                    $this->_changed_fields['kind'], 0]);
            $this->id = $this->_db->getOne('SELECT LAST_INSERT_ID() FROM sa_content');
        }
        else 
        {
            $this->_db->execParam("UPDATE `sa_content` SET  
                    `name` = ?
                    , `alt_name` = ?
                    , `formula` = ?
                    , `kind` = ?
                WHERE `id` = ?", [$this->_changed_fields['name'], 
                    $this->_changed_fields['alt_name'], $this->_changed_fields['formula'], 
                    $this->_changed_fields['kind'], $this->id]);
        }
        
        $MULANG_FIELDS = [];
        
        $source = [];
        foreach ($this->_changed_fields[self::$MULANG_FIELDS] as $value_id => $value) 
        {
            if (stripos($value_id, 'new') !== false) 
            {
                $this->_db->query("INSERT INTO `sa_content_value` (`content_id`) VALUES ('{$this->id}')");
                $value_id = $this->_db->getOne('SELECT LAST_INSERT_ID() FROM sa_content_value');
            }

            $value_id = (int)$value_id;

            $MULANG_FIELDS[] = $value_id;
            foreach ($value as $lang => $val)
            {
                $source[$value_id][$lang] = $val['value'];
            }
        }
        
        if ($MULANG_FIELDS) {
            $this->_db->query("DELETE FROM `sa_content_value` 
                    WHERE `content_id` = '{$this->id}' AND `id` NOT IN ( " . implode(',', $MULANG_FIELDS) . " )");
        } else {
            $this->_db->query("DELETE FROM `sa_content_value` WHERE `content_id` = '{$this->id}'");
        }
        
        if ($source) {
            mulang_fields_Update($MULANG_FIELDS, 'sa_content_value', $this->id, $source);
        }
        
        
        $old_ids = $this->_dbr->getAssoc("SELECT `field_id`, `field_id` `v` FROM `sa_content_field` 
                WHERE `content_id` = '{$this->id}'");
        
        if ($this->_changed_fields['sa_fields']['value'] && is_array($this->_changed_fields['sa_fields']['value'])) {
            foreach ($this->_changed_fields['sa_fields']['value'] as $sa_field) {
                if ( !in_array($sa_field, $old_ids))
                {
                    $this->_db->execParam('INSERT INTO `sa_content_field` (`content_id`, `field_id`) VALUES (?, ?)', 
                            [$this->id, $sa_field]);
                }
                else
                {
                    unset($old_ids[$sa_field]);
                }
            }
        }
        
        if ($old_ids)
        {
            $this->_db->query("DELETE FROM `sa_content_field` WHERE `field_id` IN (" . implode(',', $old_ids) . ") AND  `content_id` = '{$this->id}'");
        }
        
        $this->_load();
    }
    
    /**
     * C current data
     */
    public function createContent()
    {
        $this->_db->query("INSERT INTO `sa_content_value` (`content_id`) VALUES ('{$this->id}')");
        $value_id = $this->_db->getOne('SELECT LAST_INSERT_ID() FROM sa_content_value');

        $source = [];
        
        foreach ($this->_langs as $lang => $dummy)
        {
            $empty = new stdClass();
            $empty->language = $lang;
            $empty->value = '';
            $empty->table_name = 'sa_content_value';
            $empty->field_name = $value_id;
            $source[$value_id][$lang] = $empty;
        }

        return $source;
    }
}