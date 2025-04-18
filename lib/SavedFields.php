<?php
require_once 'lib/SavedEntity.php';

class SavedFields extends SavedEntity
{
    public static $MULANG_FIELDS = array('field_name');

    private $_langs;

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
        if ($only_active) 
        {
            $where .= ' AND NOT `sa_field`.`inactive` ';
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
        
        $sa_fields = $dbr->getAll("
                SELECT `sa_field`.* 
                FROM `sa_field` 
                LEFT JOIN `sa_field_type` ON `sa_field_type`.`field_id` = `sa_field`.`id`
                WHERE 1 
                $where
                GROUP BY `sa_field`.`id`
        ");
        
        $fields_ids = [0];
        foreach ($sa_fields as $field) {
            $fields_ids[] = (int)$field->id;
        }
        
        $contents = $dbr->getAssoc("
            SELECT `sa_content_field`.`field_id`
                , COUNT(*) AS `content`
            FROM `sa_content` 
            JOIN `sa_content_field` 
                ON `sa_content_field`.`content_id` = `sa_content`.`id`
            WHERE 
                `sa_content_field`.`field_id` IN (" . implode(',', $fields_ids) . ") 
                AND NOT `sa_content`.`inactive`
            GROUP BY `field_id`
                ");
        
        foreach ($sa_fields as $key => $field) {
            $sa_fields[$key]->content = 0;
            if (isset($contents[$field->id])) {
                $sa_fields[$key]->content = $contents[$field->id];
            }
        }
        
        return $sa_fields;
    }

    /**
     * Return all data
     */
    public function get()
    {
		$result = new stdClass();
		$result->data = $this->data;
		$result->options = $this->options;
		return $result;
    }
    /**
     * Loads all sa_field block data
     */ 
    protected function _load()
    {
        $this->data = $this->_dbr->getRow('SELECT * FROM `sa_field` WHERE `id` = ?', null, [$this->id]);
        
        $mulang_data = mulang_fields_Get(self::$MULANG_FIELDS, 'sa_field', $this->id, 1);
        foreach ($mulang_data as $field_key => $langs) {
            foreach ($langs as $lang => $data) {
                if (!in_array($lang, array_keys($this->_langs))) {
                    unset($mulang_data[$field_key][$lang]);
                }
            }
            
            // fill empty langs 
            foreach (self::$MULANG_FIELDS as $field)
            {
                $diff = array_diff(array_keys($this->_langs), array_keys($mulang_data[$field.'_translations']));
                foreach ($diff as $lang)
                {
                    $empty = new stdClass();
                    $empty->language = $lang;
                    $empty->value = '';
                    $empty->table_name = 'sa_field';
                    $empty->field_name = $field;
                    $mulang_data[$field.'_translations'][$lang] = $empty;
                }		
            }
        }

		foreach(self::$MULANG_FIELDS as $mulang_field) 
		{
			$this->data->$mulang_field = $mulang_data[$mulang_field . '_translations'];
		}
        
        $this->data->sa_types->value = (string)$this->_dbr->getOne("SELECT GROUP_CONCAT(`type_id`)
                FROM `sa_field_type` WHERE `field_id` = '{$this->id}'");
        $this->data->sa_types->value = explode(',', $this->data->sa_types->value);
        
        $this->data->sa_types->options = $this->_dbr->getAll('SELECT `id`, `parent_id`, `title`
                FROM `sa_type` WHERE NOT `inactive`');

        $this->data->content = $this->_dbr->getAll("SELECT `sa_content`.`id`, `sa_content`.`name`
            FROM `sa_content` 
            JOIN `sa_content_field` 
                ON `sa_content_field`.`content_id` = `sa_content`.`id`
            WHERE 
                `sa_content_field`.`field_id` = '{$this->id}'
                AND NOT `sa_content`.`inactive`");

        $content_ids = [];
        foreach ($this->data->content as $content) 
        {
            $content_ids[] = (int)$content->id;
        }
        
        $this->options = new stdClass;
        
        $where = "";
        if ($content_ids) 
        {
            $where = " AND `id` NOT IN ( " . implode(',', $content_ids) . " ) ";
        }
        
        $this->options->content = $this->_dbr->getAll("SELECT `id`, `name`
            FROM `sa_content` 
            WHERE 
                NOT `inactive`
                $where"); 
    }

	/**
	 * Set new data 
	 * @var array
	 */
	public function setData($in, $old = null)
	{
        $in['name'] = trim($in['name']);
        
        $in['sa_types']['value'] = (array)$in['sa_types']['value'];
        $in['sa_types']['value'][] = 0;
        
        $sa_types = $this->_dbr->getOne('SELECT GROUP_CONCAT(`id`) FROM `sa_type` 
                WHERE NOT `inactive` AND `id` IN (' . implode(',', $in['sa_types']['value']) . ')');
        
        $in['sa_types']['value'] = explode(',', $sa_types);
        
		$this->_changed_fields = $in;
	}
    
    /**
     * Save current data
     */
    public function save()
    {
        if ( ! $this->id || ! $this->_dbr->getOne("SELECT id FROM sa_field WHERE id = '{$this->id}'"))
        {
            $this->_db->execParam("INSERT INTO `sa_field` (`id`, `name`, `inactive`)
                VALUES (?, ?, ?)", [null, $this->_changed_fields['name'], 0]);
            $this->id = $this->_db->getOne('SELECT LAST_INSERT_ID() FROM sa_field');
        }
        else 
        {
            $this->_db->execParam("UPDATE `sa_field` SET  `name` = ?
                WHERE `id` = ?", [$this->_changed_fields['name'], $this->id]);
        }
        
        $source = [];
        foreach ($this->_changed_fields as $block => $value) {
            if (in_array($block, self::$MULANG_FIELDS)) {
                foreach ($value as $lang => $val)
                {
                    $source[$block][$lang] = $val['value'];
                }
            }
        }

        if ($source) {
            mulang_fields_Update(self::$MULANG_FIELDS, 'sa_field', $this->id, $source);
        }
        
        $this->_db->getOne("DELETE FROM `sa_field_type` WHERE `field_id` = '{$this->id}'");
        if ($this->_changed_fields['sa_types']['value'] && is_array($this->_changed_fields['sa_types']['value'])) {
            foreach ($this->_changed_fields['sa_types']['value'] as $sa_type) {
                $this->_db->execParam('INSERT INTO `sa_field_type` (`field_id`, `type_id`) VALUES (?, ?)', 
                        [$this->id, $sa_type]);
            }
        }
        
        $old_ids = $this->_dbr->getAssoc("SELECT `content_id`, `content_id` `v` FROM `sa_content_field` 
                WHERE `field_id` = '{$this->id}'");
        
        if ($this->_changed_fields['content'] && is_array($this->_changed_fields['content'])) {
            foreach ($this->_changed_fields['content'] as $sa_content) {
                if ( !in_array($sa_content['id'], $old_ids))
                {
                    $this->_db->execParam('INSERT INTO `sa_content_field` (`content_id`, `field_id`) VALUES (?, ?)', 
                            [$sa_content['id'], $this->id]);
                }
                else
                {
                    unset($old_ids[$sa_content['id']]);
                }
            }
        }
        
        if ($old_ids)
        {
            $this->_db->query("DELETE FROM `sa_content_field` WHERE `content_id` IN (" . implode(',', $old_ids) . ") AND  `field_id` = '{$this->id}'");
            
            $ids = $this->_dbr->getAssoc("SELECT `id`, `id` `v` FROM `sa_field_content_value_sa`
                    WHERE `field_id` = '{$this->id}' AND `content_id` IN (" . implode(',', $old_ids) . ")");
            foreach ($ids as $id)
            {
                $id = (int)$id;
                $this->_db->query("DELETE FROM `sa_field_content_value_sa` WHERE `id` = '{$id}'");
                $this->_db->query("DELETE FROM `saved_params` WHERE `par_key` = 'sa_description[]' AND `par_value` = '{$id}'");
            }
        }
        
        $this->_load();
    }
}