<?php
require_once 'lib/SavedEntity.php';

class SavedTemplates extends SavedEntity
{
    private $_langs;
    
    private $_template_types = [
        'shop', 
        'ebay', 
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
        
        $this->setOptions();
    }
    
    public static function listAll($only_active = false) {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        $where = '';
        if ($only_active) {
            $where = ' WHERE NOT `inactive` ';
        }

        $templates = $dbr->getAll("SELECT * FROM `sa_template` $where");
        $templates_ids = array_map(function($v) {return (int)$v->id;}, $templates);
        
        $templates_types = [];
        if ($templates_ids)
        {
            $templates_types = $dbr->getAll("SELECT `tt`.`template_id`
                        , `sa_type`.`id`, `sa_type`.`title`
                    FROM `sa_template_sa_type` AS `tt`
                    JOIN `sa_type` ON `tt`.`type_id` = `sa_type`.`id`
                    
                    WHERE NOT `sa_type`.`inactive`
                        AND `tt`.`template_id` IN (" . implode(',', $templates_ids) . ")
                    ");
        }
        
        foreach ($templates as $key => $template)
        {
            $templates[$key]->types = [];
            foreach ($templates_types as $type)
            {
                if ($type->template_id == $template->id)
                {
                    $templates[$key]->types[$type->id] = $type->title;
                }
            }
        }
        
        return $templates;
    }

    public function getLayout($block_id, $col_id)
    {
        return $this->_dbr->getAll("
            SELECT `id`, `block_id`, `col_id`, `element_type_id` AS `type`, `ordering` FROM `sa_template_block_element`
            WHERE `block_id` = '" . $block_id . "' AND `col_id` = '" . $col_id . "'
            ORDER BY `ordering` ASC
        ");
    }

    public function createElement($block_id, $col_id, $element_type) 
    {
        $max_ordering = (int)$this->_dbr->getOne("
                SELECT MAX(`ordering`) 
                FROM `sa_template_block_element` 
                WHERE `block_id` = '" . $block_id . "' 
        ");
        $max_ordering += 1;

        $this->_db->execParam("
            INSERT INTO `sa_template_block_element` 
                (`id`, `block_id`, `col_id`, `element_type_id`, `ordering`)
            VALUES 
                (?, ?, ?, ?, ?)
        ", [null, $block_id, $col_id, $element_type, $max_ordering]);
        
        return (int)$this->_db->getOne('SELECT LAST_INSERT_ID() FROM sa_template_block_element');
    }

    public function updateElement($element_id, $block_id, $col_id, $ordering = 0) 
    {
        $this->_db->query("
            UPDATE `sa_template_block_element` 
            SET
                `block_id` = '" . (int)$block_id . "'
                , `col_id` = '" . (int)$col_id . "'
                , `ordering` = " . (int)$ordering . "
            WHERE 
                `id` = '" . (int)$element_id . "'
        ");
    }

    public function saveElement($element_id) 
    {
        $element_id = (int)$element_id;
        $element = $this->getElement($element_id);
        
        foreach ($this->_changed_fields as $id => $value)
        {
            $id = (int)$id;

            if ($element->options && is_array($element->options))
            {
                foreach ($element->options as $option)
                {
                    if ($option->id == $id)
                    {
                        $prop_id = (int)$this->_dbr->getOne("SELECT `id` 
                            FROM `sa_template_block_element_prop`
                            WHERE 
                                `block_element_id` = '" . $element_id . "'
                                AND `prop_id` = '" . $id . "'");
                        
                        if ($option->values && is_array($option->values))
                        {
                            if ($value > 0 && ! in_array($value, array_keys($option->values)))
                            {
                                $value = array_keys($option->values);
                                $value = (int)$value[0];
                            }

                            if ($prop_id)
                            {
                                $old_value = $this->_dbr->getOne("SELECT `value_id` 
                                    FROM `sa_template_block_element_prop`
                                    WHERE `id` = '" . $prop_id . "'");

                                if ($old_value != $value)
                                {
                                    $this->_db->execParam("UPDATE `sa_template_block_element_prop`
                                            SET `value_id` = ?
                                        WHERE `id` = ?", [$value, $prop_id]);
                                }
                            }
                            else 
                            {
                                $this->_db->execParam("INSERT INTO `sa_template_block_element_prop`
                                        (`id`, `block_element_id`, `prop_id`, `value_id`, `value`)
                                    VALUES (?, ?, ?, ?, ?)", [null, $element_id, $id, $value, null]);
                            }
                        }
                        else
                        {
                            if ( ! $option->mulang)
                            {
                                if ($prop_id)
                                {
                                    $old_value = $this->_dbr->getOne("SELECT `value_id` 
                                        FROM `sa_template_block_element_prop`
                                        WHERE `id` = '" . $prop_id . "'");

                                    if ($old_value != $value)
                                    {
                                        $this->_db->execParam("UPDATE `sa_template_block_element_prop`
                                                SET `value` = ?
                                            WHERE `id` = ?", [(string)$value, $prop_id]);
                                    }
                                }
                                else 
                                {
                                    $this->_db->execParam("INSERT INTO `sa_template_block_element_prop`
                                            (`id`, `block_element_id`, `prop_id`, `value_id`, `value`)
                                        VALUES (?, ?, ?, ?, ?)", [null, $element_id, $id, null, (string)$value]);
                                }
                            }
                            else 
                            {
                                if ( ! $prop_id)
                                {
                                    $this->_db->execParam("INSERT INTO `sa_template_block_element_prop`
                                            (`id`, `block_element_id`, `prop_id`, `value_id`, `value`)
                                        VALUES (?, ?, ?, ?, ?)", [null, $element_id, $id, null, null]);

                                    $prop_id = (int)$this->_db->getOne('SELECT LAST_INSERT_ID() FROM sa_template_block_element_prop');
                                }
                                
                                $source = [];
                                foreach ($value as $lang => $val)
                                {
                                    $source[$option->id][$lang] = $val['value'];
                                }
                                
                                if ($source) {
                                    mulang_fields_Update([$option->id], 'sa_template_block_element_prop', $prop_id, $source);
                                }
                            }
                        }
                    }
                }
            }
        }
        
        $this->_load();
    }
    
    public function deleteElement($id)
    {
        $id = (int)$id;
        
        $this->_db->query("DELETE FROM `sa_template_block_element_prop` WHERE `block_element_id` = '" . $id . "'");
        $this->_db->query("DELETE FROM `sa_template_block_element` WHERE `id` = '" . $id . "'");
        
        $this->_load();
    }
    
    /**
     * Create empty element, without database record
     * 
     * @param element type $type
     * @return object
     */
    public function getEmptyElement($type)
    {
        return $this->getElement(0, $type);
    }
    
    /**
     * Get element by ID, or i
     * 
     * @param Element id $id
     * @param Element id $type
     * @return object
     */
    public function getElement($id = 0, $type = false)
    {
        $id = (int)$id;
        $type = (int)$type;
        
        if ($id)
        {
            $type = (int)$this->_dbr->getOne("SELECT `element_type_id` FROM `sa_template_block_element` WHERE `id` = '" . $id . "'");
            $element_values = $this->_dbr->getAssoc("
                    SELECT `prop_id`, `id`, IFNULL(`value_id`, `value`) `value`
                    FROM `sa_template_block_element_prop` 
                    WHERE `block_element_id` = '" . $id . "'
            ");
        }
        
        if ( ! $id && $type == 4)
        {
            $element_values[14]['value'] = 1;
            $element_values[15]['value'] = 1;
        }

        $element = $this->_dbr->getRow("SELECT *
                FROM `sa_template_element_type` WHERE `id` = '" . $type . "'");

        if ( ! $element)
        {
            return false;
        }
        
        $element->values = null;
        
        $properties = $this->_dbr->getAll("
            SELECT * FROM `sa_template_element_type_prop` WHERE `element_id` = '" . $element->id . "'
        ");
        
        $values = [];
        if ($properties)
        {
            $values = $this->_dbr->getAll("
                    SELECT * FROM `sa_template_element_type_prop_value` WHERE `prop_id` IN (" . 
                    implode(', ', array_map(function($v) {return (int)$v->id;}, $properties))
                    . ")
            ");
        }

        $element->options = [];
        foreach ($properties as $prop)
        {
            $prop->values = null;
            foreach ($values as $val)
            {
                if ($val->prop_id == $prop->id)
                {
                    $prop->values[$val->id] = $val;
                }
            }

            if ($prop->values)
            {
                if (isset($element_values[$prop->id]))
                {
                    $element->values[$prop->id] = $element_values[$prop->id]['value'];
                }
                else
                {
                    $keys = array_keys($prop->values);
                    if (count($keys) > 1)
                    {
                        $element->values[$prop->id] = $element->values[$prop->id][0];
                    }
                    else 
                    {
                        $element->values[$prop->id] = 0;
                    }
                }
            }
            else
            {
                if ( ! $prop->mulang)
                {
                    if (isset($element_values[$prop->id]))
                    {
                        $element->values[$prop->id] = $element_values[$prop->id]['value'];
                    }
                    else
                    {
                        $element->values[$prop->id] = '';
                    }
                }
                else 
                {
                    $mulang_data = mulang_fields_Get([$prop->id], 'sa_template_block_element_prop', $element_values[$prop->id]['id'], 1);
                    foreach ($mulang_data as $field_key => $langs) {
                        foreach ($langs as $lang => $data) {
                            if (!in_array($lang, array_keys($this->_langs))) {
                                unset($mulang_data[$field_key][$lang]);
                            }
                        }

                        // fill empty langs 
                        $diff = array_diff(array_keys($this->_langs), array_keys($mulang_data[$prop->id.'_translations']));
                        foreach ($diff as $lang)
                        {
                            $empty = new stdClass();
                            $empty->language = $lang;
                            $empty->value = '';
                            $empty->table_name = 'sa_template_block_element_prop';
                            $empty->field_name = $prop->id;
                            $mulang_data[$prop->id.'_translations'][$lang] = $empty;
                        }
                    }
                    $element->values[$prop->id] = $mulang_data[$prop->id . '_translations'];
                }
            }

            $element->options[] = $prop;
        }

        return $element;
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
     * Return one block data
     */
    public function getLayuot($block_id)
    {
        $result = new stdClass;
        $sa_template = $this->get();
        foreach ($sa_template->data['blocks'] as $block)
        {
            if ($block->id == $block_id)
            {
                $result = $block;
                break;
            }
        }
		return $result;
    }

    /**
     * Return all data
     */
    public function getBlock($block_id)
    {
		return $this->_dbr->getRow("
                SELECT 
                    `sa_template_block`.* 
                    , `sa_template_block_layout`.`title` AS `grid`
                    , `sa_template_block_layout`.`icon`
                
                FROM `sa_template_block` 
                LEFT JOIN `sa_template_block_layout` ON 
                    `sa_template_block_layout`.`id` = `sa_template_block`.`layout_id`
                    AND NOT `sa_template_block_layout`.`inactive`
                
                WHERE 
                    `sa_template_block`.`id` = '" . (int)$block_id . "'
                ");
    }
    
    /**
     * Create new block
     */
    public function createBlock()
    {
        $max_ordering = (int)$this->_dbr->getOne("SELECT MAX(`ordering`) FROM `sa_template_block` WHERE `template_id` = '" . $this->id . "'");
        $max_ordering += 1;
        
        $layout_id = (int)$this->_dbr->getOne("SELECT `id` FROM `sa_template_block_layout` WHERE NOT `inactive` ORDER BY `id` ASC LIMIT 1");
        $layout_id = $layout_id ? $layout_id : null;

        $this->_db->query("INSERT INTO `sa_template_block` (`id`, `template_id`, `layout_id`, `ordering`) 
                VALUES (null, '" . $this->id . "', $layout_id, '" . $max_ordering . "')");
        return (int)$this->_db->getOne('SELECT LAST_INSERT_ID() FROM sa_template_block');
    }
    
    /**
     * Copy block
     */
    public function copyBlock($block_id)
    {
        $this->_db->query("
            INSERT INTO `sa_template_block` (`id`, `template_id`, `layout_id`, `ordering`)
                SELECT NULL, `template_id`, `layout_id`, `ordering`
                FROM `sa_template_block` AS `stb` 
                WHERE `stb`.`id` = '" . (int)$block_id . "'
        ");
        
        $block_id = (int)$this->_db->getOne('SELECT LAST_INSERT_ID() FROM sa_template_block');
        $template_id = (int)$this->_db->getOne("SELECT `template_id` FROM `sa_template_block` WHERE `id` = '" . $block_id . "'");
        
        $max_ordering = (int)$this->_dbr->getOne("SELECT MAX(`ordering`) FROM `sa_template_block` WHERE `template_id` = '" . $template_id . "'");
        $max_ordering += 1;

        $this->_db->query("UPDATE `sa_template_block` SET `ordering` = '" . $max_ordering . "'
                WHERE `id` = '" . $block_id . "'");
        
        return $block_id;
    }
    
    /**
     * Duplicate template
     */
    public function duplicate($title = '', $type = 'shop') 
    {
        $template = $this->_dbr->getRow("SELECT * FROM `sa_template` WHERE `id` = '" . $this->id . "'");
        if ( ! $template)
        {
            return false;
        }
        
        if ( ! $title)
        {
            $template->title .= ' (Copy)';
        }
        else 
        {
            $template->title = $title;
        }
        
        if ( !in_array($type, $this->_template_types))
        {
            $type = $this->_template_types[0];
        }
        
        $template->type = $type;
        
        $this->_db->query("
            INSERT INTO `sa_template` (`id`, `title`, `type`, `inactive`) 
            VALUES (NULL, '" . mysql_real_escape_string($template->title) . "', '" . $template->type . "', 
                '" . (int)$template->inactive . "')
        ");
        
        $template_id = (int)$this->_db->getOne('SELECT LAST_INSERT_ID() FROM sa_template');
        if ( ! $template_id)
        {
            return false;
        }
        
        $template_types = $this->_dbr->getAssoc("SELECT `id`, `type_id` 
                FROM `sa_template_sa_type` WHERE `template_id` = '" . $this->id . "'");
        foreach ($template_types as $type)
        {
            $this->_db->query("
                INSERT INTO `sa_template_sa_type` (`id`, `template_id`, `type_id`) 
                VALUES (NULL, '" . $template_id . "', '" . (int)$type . "')
            ");
        }
        
        $template_blocks = $this->_dbr->getAll("SELECT *
                FROM `sa_template_block` WHERE `template_id` = '" . $this->id . "'");
        foreach ($template_blocks as $block)
        {
            $this->_db->query("
                INSERT INTO `sa_template_block` (`id`, `template_id`, `layout_id`, `ordering`) 
                VALUES (NULL, '" . $template_id . "', 
                    '" . (int)$block->layout_id . "', '" . (int)$block->ordering . "')
            ");
            
            $block_id = (int)$this->_db->getOne('SELECT LAST_INSERT_ID() FROM sa_template_block');
            if ( ! $block_id)
            {
                continue;
            }
            
            $template_blocks_elements = $this->_dbr->getAll("SELECT *
                    FROM `sa_template_block_element` WHERE `block_id` = '" . $block->id . "'");
            foreach ($template_blocks_elements as $element)
            {
                $this->_db->query("
                    INSERT INTO `sa_template_block_element` (`id`, `block_id`, `col_id`, `element_type_id`, `ordering`) 
                    VALUES (NULL, '" . $block_id . "', 
                        '" . (int)$element->col_id . "', '" . (int)$element->element_type_id . "', '" . (int)$element->ordering . "')
                ");

                $element_id = (int)$this->_db->getOne('SELECT LAST_INSERT_ID() FROM sa_template_block_element');
                if ( ! $element_id)
                {
                    continue;
                }
                $template_blocks_elements_prop = $this->_dbr->getAll("SELECT *
                        FROM `sa_template_block_element_prop` WHERE `block_element_id` = '" . $element->id . "'");
                foreach ($template_blocks_elements_prop as $prop)
                {
                    $value_id = is_numeric($prop->value_id) && $prop->value_id ? "'" . (int)$prop->value_id . "'" : "NULL";
                    $value = is_string($prop->value) && $prop->value ? "'" . mysql_real_escape_string($prop->value) . "'" : "NULL";
                    
                    $this->_db->query("
                        INSERT INTO `sa_template_block_element_prop` (`id`, `block_element_id`, `prop_id`, `value_id`, `value`) 
                        VALUES (NULL, '" . $element_id . "', 
                            '" . (int)$prop->prop_id . "', $value_id, $value)
                    ");
                    
                    $prop_id = (int)$this->_db->getOne('SELECT LAST_INSERT_ID() FROM sa_template_block_element_prop');
                    
                    $translation = $this->_dbr->getAll("SELECT * FROM `translation` 
                            WHERE `table_name` = 'sa_template_block_element_prop' AND `id` = '" . (int)$prop->id . "'");
                    
                    $source = [];
                    $options = [];
                    foreach ($translation as $trans)
                    {
                        $source[$trans->field_name][$trans->language] = $trans->value;
                        $options[$trans->field_name] = true;
                    }

                    if ($source) {
                        mulang_fields_Update(array_keys($options), 'sa_template_block_element_prop', $prop_id, $source);
                    }
                }
            }
        }
        
        return $template_id;
    }
    
	/**
	 * Set new data 
	 * @var array
	 */
	public function setData($in, $old = null)
	{
        $this->_changed_fields = $in;
	}
    
    /**
     * Save current data
     */
    public function save()
    {
        $blocks_ids = [];
        
        if (isset($this->_changed_fields['blocks']) && is_array($this->_changed_fields['blocks']))
        {
            foreach ($this->_changed_fields['blocks'] as $block)
            {
                $blocks_ids[] = (int)$block['id'];
                
                $layout_id = (int)$this->_dbr->getOne("SELECT `id` FROM `sa_template_block_layout` 
                        WHERE `id` = '" . (int)($block['layout_id']) . "'");
                $layout_id = $layout_id ? $layout_id : null;
                
                $this->_db->execParam("UPDATE `sa_template_block` SET 
                        `ordering` = ?
                        , `layout_id` = ?
                    WHERE `id` = ? AND `template_id` = ?", 
                        [(int)$block['ordering'], $layout_id, (int)$block['id'], $this->id]);
                
                if (isset($block['layouts']) && is_array($block['layouts']))
                {
                    foreach ($block['layouts'] as $layout)
                    {
                        $this->updateElement($layout['id'], $layout['block_id'], $layout['col_id'], $layout['ordering']);
                    }
                }
            }
        }
        
        $old_block_ids = $this->_dbr->getAssoc("SELECT `id` AS `k`, `id` AS `v`
                    FROM `sa_template_block` WHERE `template_id` = '" . $this->id . "'");
        
        sort(array_values($blocks_ids));
        sort(array_values($old_block_ids));
        
        $blocks_ids = array_diff($old_block_ids, $blocks_ids);
        foreach ($blocks_ids as $block_id)
        {
            $elements = $this->_dbr->getAssoc("SELECT `id` AS `k`, `id` AS `v`
                    FROM `sa_template_block_element` WHERE `block_id` = '" . $block_id . "'");
            
            if ($elements)
            {
                $this->_db->query("DELETE FROM `sa_template_block_element_prop` WHERE `block_element_id` IN (" . implode(',', $elements) . ")");
                $this->_db->query("DELETE FROM `sa_template_block_element` WHERE `id` IN (" . implode(',', $elements) . ")");
            }
            
            $this->_db->query("DELETE FROM `sa_template_block` WHERE `id` = '" . $block_id . "'");
        }
        
        $this->updateOrdering();
        
        $this->_load();
    }
    
    private function updateOrdering() 
    {
        $blocks = $this->_dbr->getAssoc("
            SELECT `id`, `ordering`
            FROM `sa_template_block`
            WHERE `template_id` = '" . $this->id . "'
            ORDER BY `ordering` ASC
        ");

        $ordering = 1;
        foreach ($blocks as $block_id => $dummy)
        {
            $block_id = (int)$block_id;
            
            $this->_db->execParam("UPDATE `sa_template_block` SET `ordering` = ?
                WHERE `id` = ? AND `template_id` = ?", 
                [$ordering, $block_id, $this->id]);
            $ordering++;

            $elements = $this->_dbr->getAll("
                SELECT `id`, `ordering`
                FROM `sa_template_block_element`
                WHERE `block_id` = '" . $block_id . "'
                ORDER BY `ordering` ASC, `col_id` ASC
            ");

            if ($elements && is_array($elements))
            {
                $elements_ordering = 1;

                foreach ($elements as $element)
                {
                    $this->_db->execParam("UPDATE `sa_template_block_element` SET `ordering` = ?
                        WHERE `id` = ? AND `block_id` = ?", 
                        [$elements_ordering, $element->id, $block_id]);
                    $elements_ordering++;
                }
            }
        }
    }
    
    /**
     * Save current data
     */
    public function saveTitle($title = '')
    {
        if ( ! in_array($title['type'], $this->_template_types))
        {
            $title['type'] = $this->_template_types[0];
        }
        
        $this->_db->execParam("UPDATE `sa_template` SET `title` = ? , `type` = ?
                WHERE `id` = ?", [trim($title['title']), $title['type'], $this->id]);

        $this->_db->query("DELETE FROM `sa_template_sa_type` 
                WHERE `template_id` = '" . $this->id . "'");
        
        if ($title['sa_template_sa_type'] && is_array($title['sa_template_sa_type']))
        {
            $insert = [];
            foreach ($title['sa_template_sa_type'] as $type_id)
            {
                $insert[] = " ( null, '" . $this->id . "', '" .  (int)$type_id . "' ) ";
            }
            
            if ($insert)
            {
                $this->_db->query("INSERT INTO `sa_template_sa_type` (`id`, `template_id`, `type_id`) VALUES " .
                    implode(', ', $insert));
            }
        }
        
        $this->_load();
    }
    
    /**
     * Loads all sa_field block data
     */ 
    protected function _load()
    {
        $this->data['template'] = $this->_dbr->getRow("
                SELECT 
                    `sa_template`.* 
                    , GROUP_CONCAT(`sa_template_sa_type`.`type_id`) `sa_template_id_sa_type_id`
                
                FROM `sa_template` 
                
                LEFT JOIN `sa_template_sa_type` ON 
                    `sa_template_sa_type`.`template_id` = `sa_template`.`id`
                
                WHERE 
                    `sa_template`.`id` = '" . $this->id . "'
                        
                GROUP BY `sa_template`.`id`
                ");
        
        $this->data['template']->sa_template_sa_type = explode(',', $this->data['template']->sa_template_id_sa_type_id);
        unset($this->data['template']->sa_template_id_sa_type_id);
        
		$this->data['blocks'] = $this->_dbr->getAll("
                SELECT 
                    `sa_template_block`.* 
                    , `sa_template_block_layout`.`title` AS `grid`
                    , `sa_template_block_layout`.`icon`
                    , NULL `layouts`
                
                FROM `sa_template_block` 
                LEFT JOIN `sa_template_block_layout` ON 
                    `sa_template_block_layout`.`id` = `sa_template_block`.`layout_id`
                    AND NOT `sa_template_block_layout`.`inactive`
                
                WHERE 
                    `sa_template_block`.`template_id` = '" . $this->id . "'
                        
                ORDER BY `sa_template_block`.`ordering` ASC
                ");
        
        if ($this->data['blocks'])
        {
            $layouts = $this->_dbr->getAll("
                SELECT `id`, `block_id`, `col_id`, `element_type_id` AS `type`, `ordering`
                FROM `sa_template_block_element`
                WHERE `block_id` IN (" . 
                    implode(',', array_map(function($v) {return (int)$v->id;}, $this->data['blocks'])) . 
                ")
                ORDER BY `ordering` ASC
            ");

            $element_values = [];
            if ($layouts)
            {
                $query = "
                    SELECT 
                        `el`.`id`
                        , `el`.`block_element_id`
                        , `el`.`prop_id`
                        , IFNULL(IFNULL(`prop_val`.`value`, `el`.`value_id`), `el`.`value`) AS `value`
                        , `prop`.`mulang`

                    FROM `sa_template_block_element_prop` `el`

                    JOIN `sa_template_element_type_prop` `prop` ON `prop`.`id` = `el`.`prop_id`
                    LEFT JOIN `sa_template_element_type_prop_value` AS `prop_val` ON 
                        `prop_val`.`prop_id` = `prop`.`id`
                        AND `prop_val`.`id` = `el`.`value_id`

                    WHERE 
                        `el`.`block_element_id` IN (" . 
                        implode(',', array_map(function($v) {return (int)$v->id;}, $layouts)) . 
                        ")

                    GROUP BY 
                        `el`.`block_element_id`, `el`.`prop_id`
                ";

                $element_values = [];
                foreach ($this->_dbr->getAll($query) as $_value)
                {
                    $element_values[$_value->block_element_id][$_value->prop_id] = $_value->value;
                    if ($_value->mulang)
                    {
                        $mulang_data = mulang_fields_Get([$_value->prop_id], 'sa_template_block_element_prop', $_value->id, 1);
                        foreach ($mulang_data as $field_key => $langs) 
                        {
                            foreach ($langs as $lang => $data) {
                                if (!in_array($lang, array_keys($this->_langs))) {
                                    unset($mulang_data[$field_key][$lang]);
                                }
                            }
                            
                            // fill empty langs 
                            $diff = array_diff(array_keys($this->_langs), array_keys($mulang_data[$_value->id.'_translations']));
                            foreach ($diff as $lang)
                            {
                                $empty = new stdClass();
                                $empty->language = $lang;
                                $empty->value = '';
                                $empty->table_name = 'sa_template_block_element_prop';
                                $empty->field_name = $prop->id;
                                $mulang_data[$prop->id.'_translations'][$lang] = $empty;
                            }
                        }
                        
                        $element_values[$_value->block_element_id][$_value->prop_id] = $mulang_data[$_value->prop_id . '_translations'];
                    }
                }
            }
            
            foreach ($layouts as $_layout)
            {
                foreach ($this->data['blocks'] as $key => $_block)
                {
                    if ($_block->id == $_layout->block_id)
                    {
                        $_layout->values = null;
                        if (isset($element_values[$_layout->id]))
                        {
                            $_layout->values = $element_values[$_layout->id];
                        }
                        
                        $this->data['blocks'][$key]->layouts[] = $_layout;
                    }
                }
            }
        }
    }
    
    /**
     * Loads all sa_field block data
     */ 
    public static function getBlocks($template_id, $lang)
    {
        $__time = microtime(true);
                
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        
		$blocks = $dbr->getAll("
                SELECT 
                    `sa_template_block`.* 
                    , `sa_template_block_layout`.`title` AS `grid`
                    , `sa_template_block_layout`.`icon`
                    , NULL `layouts`
                
                FROM `sa_template_block` 
                LEFT JOIN `sa_template_block_layout` ON 
                    `sa_template_block_layout`.`id` = `sa_template_block`.`layout_id`
                    AND NOT `sa_template_block_layout`.`inactive`
                
                WHERE 
                    `sa_template_block`.`template_id` = '" . $template_id . "'
                        
                ORDER BY `sa_template_block`.`ordering` ASC
                ");
        
        if ($blocks)
        {
            $layouts = $dbr->getAll("
                SELECT `id`, `block_id`, `col_id`, `element_type_id` AS `type`, `ordering`
                FROM `sa_template_block_element`
                WHERE `block_id` IN (" . 
                    implode(',', array_map(function($v) {return (int)$v->id;}, $blocks)) . 
                ")
                ORDER BY `ordering` ASC
            ");

            $element_values = [];
            if ($layouts)
            {
                $query = "
                    SELECT 
                        `el`.`id`
                        , `el`.`block_element_id`
                        , `el`.`prop_id`
                        , IFNULL(IFNULL(`prop_val`.`value`, `el`.`value_id`), `el`.`value`) AS `value`
                        , `prop`.`mulang`

                    FROM `sa_template_block_element_prop` `el`

                    JOIN `sa_template_element_type_prop` `prop` ON `prop`.`id` = `el`.`prop_id`
                    LEFT JOIN `sa_template_element_type_prop_value` AS `prop_val` ON 
                        `prop_val`.`prop_id` = `prop`.`id`
                        AND `prop_val`.`id` = `el`.`value_id`

                    WHERE 
                        `el`.`block_element_id` IN (" . 
                        implode(',', array_map(function($v) {return (int)$v->id;}, $layouts)) . 
                        ")

                    GROUP BY 
                        `el`.`block_element_id`, `el`.`prop_id`
                ";
                        
                $elements = $dbr->getAll($query);
                        
                $mulang_ids = [];
                $mulang_fields_ids = [];
                $element_values = [];
                foreach ($elements as $_value)
                {
                    $element_values[$_value->block_element_id][$_value->prop_id] = $_value->value;
                    if ($_value->mulang)
                    {
                        $mulang_ids[] = (int)$_value->id;
                        $mulang_fields_ids[] = "'" . mysql_real_escape_string($_value->prop_id) . "'";
                    }
                }
                
                $mulang_ids = array_unique($mulang_ids);
                $mulang_fields_ids = array_unique($mulang_fields_ids);
                
                if ($mulang_ids)
                {
                    $mulang_data = $dbr->getAll("SELECT `id`, `field_name`, `value` FROM `translation` 
                        WHERE `table_name` = 'sa_template_block_element_prop'
                            AND `value` != ''
                            AND `language` = '" . mysql_real_escape_string($lang) . "'
                            AND `id` IN (" . implode(',', $mulang_ids) . ")
                            AND `field_name` IN (" . implode(',', $mulang_fields_ids) . ")");
                    
                    $mulang_array = [];
                    foreach ($mulang_data as $mulang)
                    {
                        if ($mulang->value)
                        {
                            $mulang_array[$mulang->id][$mulang->field_name] = $mulang->value;
                        }
                    }

                    foreach ($elements as $_value)
                    {
                        if ($_value->mulang)
                        {
                            $element_values[$_value->block_element_id][$_value->prop_id] = $mulang_array[$_value->id][$_value->prop_id];
                        }
                    }
                }
            }
            
            foreach ($layouts as $_layout)
            {
                foreach ($blocks as $key => $_block)
                {
                    if ($_block->id == $_layout->block_id)
                    {
                        $_layout->values = null;
                        if (isset($element_values[$_layout->id]))
                        {
                            $_layout->values = $element_values[$_layout->id];
                        }
                        
                        $blocks[$key]->layouts[] = $_layout;
                    }
                }
            }
        }

        $template = new stdClass;
        $template->blocks = $blocks;
        $template->elements = $dbr->getAssoc('
                SELECT `id` AS `k`, `id`, `title`, `hardcoded`
                FROM `sa_template_element_type`
                ');
        
        return $template;
    }

    /**
     * Set $this->options array
     */
    private function setOptions() 
    {
        $this->options['elements'] = $this->_dbr->getAssoc('
                SELECT `id` AS `k`, `id`, `title`, `hardcoded`
                FROM `sa_template_element_type`
                ');
        
        $this->options['grids'] = $this->_dbr->getAssoc('
                SELECT `id` AS `k`, `id`, `title` AS `grid`, `icon`
                FROM `sa_template_block_layout`
                WHERE NOT `inactive`
                ');
        
        $this->options['sa_types'] = $this->_dbr->getAssoc('
                SELECT `id` AS `k`, `id`, `title`, `parent_id`
                FROM `sa_type`
                WHERE NOT `inactive`
                ');
    }
    
    /**
     * Set FULL $this->options array
     */
    private function setOptionsFull() 
    {
        $this->options['elements'] = $this->_dbr->getAssoc('
                SELECT `id` AS `k`, `id`, `title`, `hardcoded`
                FROM `sa_template_element_type`
                ');
        
        $properties = $this->_dbr->getAll('SELECT * FROM `sa_template_element_type_prop`');
        $values = $this->_dbr->getAll('SELECT * FROM `sa_template_element_type_prop_value`');
        
        foreach ($this->options['elements'] as $key => $element)
        {
            if ( ! isset($this->options['elements'][$key]['prop']))
            {
                $this->options['elements'][$key]['prop'] = [];
            }
            
            foreach ($properties as $prop)
            {
                if ($key == $prop->element_id)
                {
                    $prop->values = [];
                    
                    foreach ($values as $val)
                    {
                        if ($val->prop_id == $prop->id)
                        {
                            $prop->values[$val->id] = $val;
                        }
                    }
                    
                    $this->options['elements'][$key]['prop'][$prop->id] = $prop;
                }
            }
        }
    }
}