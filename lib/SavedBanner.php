<?php
require_once 'lib/SavedEntity.php';

class SavedBanner extends SavedEntity
{
    public static $MULANG_FIELDS = array('top_left_banner_text', 'top_left_banner_size');
    public static $SAVED_PARAMS = array('top_left_text_color', 'top_left_banner_color',
        'top_left_banner_font', 'top_left_banner_height', 'top_left_banner_v');

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

    public static function getForSa($saved_id, $lang, $def_lang) {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        $banner = $dbr->getRow("SELECT `sab`.*, `sabs`.`top_left_banner_until`
            FROM `sa_article_banner` AS `sab`
            JOIN `sa_article_banner_sa` AS `sabs` ON `sabs`.`sa_article_banner_id` = `sab`.`id`
            WHERE NOT `sab`.`inactive` AND `sabs`.`saved_id` = " . (int)$saved_id . "
                AND IFNULL(`sabs`.`top_left_banner_until`, '9999-99-99') >= DATE_FORMAT(NOW(), '%Y-%m-%d')
            ORDER BY IFNULL(`sabs`.`top_left_banner_until`, '9999-99-99') ASC
            LIMIT 1");
        
        if ( ! $banner) {
            return false;
        }
        
        $banner->top_left_banner_until = $banner->top_left_banner_until ? strtotime($banner->top_left_banner_until) : false;
        if ($banner->top_left_banner_until && $banner->top_left_banner_until < time()) {
            return false;
        }
        
        $banner->top_left_banner_font = '"' . $banner->top_left_banner_font . '"';
        
        $mulang_data = [];
        $query = "SELECT `field_name`, `language`, `value` FROM `translation` 
                WHERE `table_name` = 'sa_article_banner'
                    AND `field_name` IN (" . implode(',', array_map(function($v) {return "'" . mysql_real_escape_string($v) . "'";}, self::$MULANG_FIELDS)) . ")
                    AND `language` IN ('$lang', '$def_lang')
                    AND `id` = '" . (int)$banner->id . "'";
        foreach ($dbr->getAll($query) as $mulang)
        {
            $mulang_data[$mulang->field_name][$mulang->language] = $mulang->value;
        }
                    
        foreach (self::$MULANG_FIELDS as $field) {
            if ($mulang_data[$field][$lang]) {
                $banner->$field = $mulang_data[$field][$lang];
            } else if ($mulang_data[$field][$def_lang]) {
                $banner->$field = $mulang_data[$field][$def_lang];
            } else  {
                $banner->$field = '';
            }
        }
        
        $banner->top_left_banner_show = true;

        return (array)$banner;
    }

    public static function getForSas($saved_ids, $lang, $def_lang) {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        $query = "SELECT `sabs`.`saved_id`, `sab`.*, `sabs`.`top_left_banner_until`
            FROM `sa_article_banner` AS `sab`
            JOIN `sa_article_banner_sa` AS `sabs` ON `sabs`.`sa_article_banner_id` = `sab`.`id`
            WHERE NOT `sab`.`inactive` AND `sabs`.`saved_id` IN (" . implode(',', $saved_ids) . ")
                AND IFNULL(`sabs`.`top_left_banner_until`, '9999-99-99') >= DATE_FORMAT(NOW(), '%Y-%m-%d')
            ORDER BY IFNULL(`sabs`.`top_left_banner_until`, '9999-99-99') ASC";
        
        $banners = [];
        foreach ($dbr->getAll($query) as $banner)
        {
            if ( ! isset($banners[$banner->saved_id]))
            {
                $banners[$banner->saved_id] = $banner;
            }
        }
        
        if ( ! $banners) {
            return false;
        }
        
        $mulang_data = [];
        $query = "SELECT `id`, `field_name`, `language`, `value` FROM `translation` 
                WHERE `table_name` = 'sa_article_banner'
                    AND `field_name` IN (" . implode(',', array_map(function($v) {return "'" . mysql_real_escape_string($v) . "'";}, self::$MULANG_FIELDS)) . ")
                    AND `language` IN ('$lang', '$def_lang')
                    AND `id` IN (" . implode(',', array_map(function($v) {return (int)$v->id;}, $banners)) . ")";
        foreach ($dbr->getAll($query) as $mulang)
        {
            $mulang_data[$mulang->id][$mulang->field_name][$mulang->language] = $mulang->value;
        }
        
        foreach ($banners as $key => $banner)
        {
            $banners[$key]->top_left_banner_until = $banner->top_left_banner_until ? strtotime($banner->top_left_banner_until) : false;
            if ($banner->top_left_banner_until && $banner->top_left_banner_until < time()) {
                unset($banners[$key]);
                continue;
            }

            $banners[$key]->top_left_banner_font = '"' . $banner->top_left_banner_font . '"';
        
            foreach (self::$MULANG_FIELDS as $field) {
                if ($mulang_data[$banner->id][$field][$lang]) {
                    $banners[$key]->$field = $mulang_data[$banner->id][$field][$lang];
                } else if ($mulang_data[$banner->id][$field][$def_lang]) {
                    $banners[$key]->$field = $mulang_data[$banner->id][$field][$def_lang];
                } else  {
                    $banners[$key]->$field = '';
                }
            }

            $banners[$key]->top_left_banner_show = true;
            
            $banners[$key] = (array)$banners[$key];
        }
        
        return $banners;
    }

    public static function listAll($only_active = false) {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        $where = '';
        if ($only_active) {
            $where = ' WHERE NOT `inactive` ';
        }

        return $dbr->getAll("SELECT `id`, `inactive`, `title`
                FROM `sa_article_banner` 
                $where
                ORDER BY `id` DESC");
    }

    public static function listChecked($saved_id) {
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);

        return $dbr->getAssoc("SELECT `sabs`.`sa_article_banner_id`, `sabs`.`top_left_banner_until`
                FROM `sa_article_banner_sa` AS `sabs`
                JOIN `sa_article_banner` AS `sab` ON `sab`.`id` = `sabs`.`sa_article_banner_id`
                WHERE NOT `sab`.`inactive` AND `sabs`.`saved_id` = ?
                ORDER BY IFNULL(`sabs`.`top_left_banner_until`, '9999-99-99') ASC", null, [$saved_id]);
    }

    /**
     * Return all data
     */
    public function get()
    {
        return $this->data;
    }
    /**
     * Loads all banner block data
     */ 
    protected function _load()
    {
        $this->data = $this->_dbr->getRow('SELECT * FROM `sa_article_banner` WHERE `id` = ?', null, [$this->id]);
        
        $mulang_data = mulang_fields_Get(self::$MULANG_FIELDS, 'sa_article_banner', $this->id, 1);
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
                    $empty->table_name = 'sa_article_banner';
                    $empty->field_name = $field;
                    $mulang_data[$field.'_translations'][$lang] = $empty;
                }		
            }
        }

		foreach(self::$MULANG_FIELDS as $mulang_field) 
		{
			$this->data->$mulang_field = $mulang_data[$mulang_field . '_translations'];
		}
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
        $this->_db->execParam("UPDATE `sa_article_banner` SET 
                `title` = ?, 
                `top_left_banner_font` = ?, `top_left_banner_height` = ?, `top_left_banner_v` = ?, 
                `top_left_banner_color` = ?, `top_left_text_color` = ?
            WHERE `id` = ?", [$this->_changed_fields['title'], 
                $this->_changed_fields['top_left_banner_font'], $this->_changed_fields['top_left_banner_height'], $this->_changed_fields['top_left_banner_v'], 
                $this->_changed_fields['top_left_banner_color'], $this->_changed_fields['top_left_text_color'], 
                $this->id]);
        
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
            mulang_fields_Update(self::$MULANG_FIELDS, 'sa_article_banner', $this->id, $source);
        }
        
        $this->_load();
    }
}