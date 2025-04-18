<?php
require_once 'lib/SavedEntity.php';

class SavedPic extends SavedEntity {
    /**
     * Image types to exclude from shop
     */
    public $not_shop_img_types = ['customer', '3d', 'amateur'];
    /**
	 * Last inserted / updated saved_pic row
	 */
	private $_last_doc_id;
	/**
	 * Loads all pics data
	 */ 
	public function _load()
	{
        global $smarty;
        require_once __DIR__ . '/../plugins/function.imageurl.php';
        
        versions(); // reload versions from redis/db if it was cleared
        
		$q = "SELECT `doc_id` as `id`, saved_pic.* FROM `saved_pic` WHERE `saved_id` = {$this->id} AND `img_type` ";
		if ($this->_last_doc_id)
            $q .= " AND `doc_id` = {$this->_last_doc_id}";
        $q .= " ORDER BY `img_type`, `ordering` ASC";

		$this->pics = $this->_dbr->getAssoc($q);

        /**
         * Using new `img_type` field to define pic type
         */
        foreach ($this->pics as $key => $pic) {
            $this->pics[$key]['primary'] = $pic['img_type'] == 'primary' ? 1 : 0;
            
            if ($pic['img_type'] == 'dimensions') {
                $this->pics[$key]['dimensions'] = 3;
            } elseif ($pic['img_type'] == 'dimensions_cm') {
                $this->pics[$key]['dimensions'] = Saved::DIMENSION_CM;
            } elseif ($pic['img_type'] == 'dimensions_inch') {
                $this->pics[$key]['dimensions'] = Saved::DIMENSION_INCH;
            } else {
                $this->pics[$key]['dimensions'] = 0;
            }
            
            $this->pics[$key]['details'] = $pic['img_type'] == 'details' ? 1 : 0;
        }
        
        $this->_loadVersions();
		foreach ($this->pics as $id => $pic)
		{
            foreach (['color', 'whitesh', 'whitenosh'] as $type) {
                $file_exists = !empty($pic["hash_$type"]) && !empty($pic["ext_$type"]);
                
                $path_original = smarty_function_imageurl(['src' => 'sa',
					 'picid' => $pic['doc_id'],
					 'type' => $type,
					 'ext' => $pic["ext_$type"]], $smarty);
                $path_cached = smarty_function_imageurl(['src' => 'sa',
					 'picid' => $pic['doc_id'],
					 'type' => $type,
					 'x' => 200,
					 'ext' => $pic["ext_$type"]], $smarty);
                
                $this->pics[$id]["path_$type"] = $file_exists ? $path_cached : '';
                $this->pics[$id]["original_$type"] = $file_exists ? $path_original : '';
            }
            
            $this->pics[$id]['type'] = 'pic';
            $this->pics[$id]['wdoc_id'] = $this->pics[$id]['path_whitesh'] ? $this->pics[$id]['doc_id'] : 0;
            $this->pics[$id]['cdoc_id'] = $this->pics[$id]['path_whitenosh'] ? $this->pics[$id]['doc_id'] : 0;

            $this->pics[$id]['use_in_shop'] = !in_array($pic['img_type'], $this->not_shop_img_types);
		} 
	}

    /**
     * Get primary image for SA.
     * 
     * @global type $smarty
     * @param int $id
     * @return Object
     */
    public static function getPrimary($id, $lang = null) {
        global $smarty;
        require_once __DIR__ . '/../plugins/function.imageurl.php';
        
        if (is_array($id))
        {
            return self::getPrimaryIds($id, $lang);
        }
        
        $db = \label\DB::getInstance(\label\DB::USAGE_READ);
        
		$q = "SELECT * FROM `saved_pic` WHERE `saved_id` = $id AND `img_type` = 'primary' AND NOT `inactive`
                ORDER BY `img_type`, `ordering` DESC LIMIT 1";

		$pic = $db->getRow($q);
        
        if ($pic) {
            foreach (['color', 'whitesh', 'whitenosh'] as $type) {
                $file_exists = ! empty($pic->{"hash_$type"}) && ! empty($pic->{"ext_$type"});

                $path_original = smarty_function_imageurl(['src' => 'sa',
                     'picid' => $pic->doc_id,
                     'type' => $type,
                     'ext' => $pic->{"ext_$type"}], $smarty);
                $path_cached = smarty_function_imageurl(['src' => 'sa',
                     'picid' => $pic->doc_id,
                     'type' => $type,
                     'x' => 200,
                     'ext' => $pic->{"ext_$type"}], $smarty);

                $pic->{"path_$type"} = $file_exists ? $path_cached : '';
                $pic->{"original_$type"} = $file_exists ? $path_original : '';
                
                if ($lang) {
                    $pic->path_prefix[$type] = 'undef';
                }
            }

            $pic->type = 'pic';
            $pic->wdoc_id = $pic->path_whitesh ? $pic->doc_id : 0;
            $pic->cdoc_id = $pic->path_whitenosh ? $pic->doc_id : 0;
            
            if ($lang) {
                $q = "SELECT `id`, `field_name` as `type`, `value` as `html` FROM `translation` 
                        WHERE table_name = 'saved_pic'
                        AND `id` = {$pic->doc_id} AND `language` = ?";
                $translations = $db->getAll($q, null, $lang);
                
                foreach ($translations as $row) {
                    if (strpos($row->type, 'Text') !== false) {
                        $type = str_replace('Text', '', $row->type);
                        $pic->path_prefix[$type] = $lang;
                    }
                }
            }
        }

        return $pic;
    }

    /**
     * Get primary image for SA.
     * 
     * @global type $smarty
     * @param int $id
     * @return Object
     */
    private static function getPrimaryIds($ids, $lang = null) {
        global $smarty;
        $db = \label\DB::getInstance(\label\DB::USAGE_READ);
        
        $q = "SELECT * FROM `saved_pic` WHERE 
                    `saved_id` IN (" . implode(',', $ids) . ")
                    AND `img_type` = 'primary' AND `inactive` = '0'
                ORDER BY `img_type`, `ordering` 
                ";

		$pics = [];
        foreach ($db->getAll($q) as $pic)
        {
            $pics[$pic->saved_id] = $pic;
        }

        if ($pics) {
            foreach ($pics as $key => $pic)
            {
                foreach (['color', 'whitesh', 'whitenosh'] as $type) {
                    $file_exists = ! empty($pic->{"hash_$type"}) && ! empty($pic->{"ext_$type"});

                    $path_original = smarty_function_imageurl(['src' => 'sa',
                         'picid' => $pic->doc_id,
                         'type' => $type,
                         'ext' => $pic->{"ext_$type"}], $smarty);
                    $path_cached = smarty_function_imageurl(['src' => 'sa',
                         'picid' => $pic->doc_id,
                         'type' => $type,
                         'x' => 200,
                         'ext' => $pic->{"ext_$type"}], $smarty);

                    $pic->{"path_$type"} = $file_exists ? $path_cached : '';
                    $pic->{"original_$type"} = $file_exists ? $path_original : '';

                    if ($lang) {
                        $pic->path_prefix[$type] = 'undef';
                    }
                }

                $pic->type = 'pic';
                $pic->wdoc_id = $pic->path_whitesh ? $pic->doc_id : 0;
                $pic->cdoc_id = $pic->path_whitenosh ? $pic->doc_id : 0;
                
                $pics[$key] = $pic;
            }
            
            if ($lang && $pics) {
                
                $docs_ids = array_map(function($v) {return (int)$v->doc_id;}, $pics);
                $q = "SELECT `id`, `field_name` as `type`, `value` as `html` FROM `translation` 
                        WHERE table_name = 'saved_pic'
                        AND `id` IN (" . implode(',', $docs_ids) . ") AND `language` = '$lang'";
                
                $translations = $db->getAll($q);
                $translations_array = [];
                foreach ($translations as $row) {
                    $translations_array[$row->id] = $row;
                }

                foreach ($pics as $key => $pic)
                {
                    if ($translations_array[$pic->doc_id])
                    {
                        foreach ($translations_array[$pic->doc_id] as $row) {
                            if (strpos($row->type, 'Text') !== false) {
                                $type = str_replace('Text', '', $row->type);
                                $pics[$key]->path_prefix[$type] = $lang;
                            }
                        }
                    }
                }
            }
        }

        return $pics;
    }


    /**
	 * Return all data
	 * @result object
	 */
	public function get($get_object = false)
	{
        if ($get_object) {
            $pics = [];
            foreach ($this->pics as $key => $value) {
                if ( ! $value->inactive) {
                    $pics[$key] = (object)$value;
                }
            }
            return $pics;
        }
        
		return [
			'data' => $this->pics
		];
	}
	/**
	 * Set new data 
	 * @var array
	 */
	public function setData($in, $old = null)
	{
		if (isset($old['data']))
		{
			$assigned = sort(array_keys($this->pics));
			$_assigned = sort(array_keys($old['data']));
			if ($assigned != $_assigned)
				throw new Exception(Saved::MESSAGE_DATA_IS_CHANGED);	
		}
	
		// delete
		$this->_to_delete = array_diff(array_keys($this->pics), array_keys($in['data']));
		
		$this->_compare = ['name', 'description', 'inactive', 'hideinshop', 'ordering', 
			'ext_color', 'ext_whitesh', 'ext_whitenosh', 'img_type'];
			
		foreach ($in['data'] as $id => $row)
		{
			// update
			if (isset($this->pics[$id]))
			{
				foreach ($this->_compare as $field)
				{
					if (isset($old['data'][$id][$field]) && $old['data'][$id][$field] != $this->pics[$id][$field])
						throw new Exception(Saved::MESSAGE_DATA_IS_CHANGED);	
					
					if ($row[$field] != $this->pics[$id][$field])
					{
						if (!in_array($id, $this->_changed_fields))
							$this->_changed_fields[] = $id;
						$this->pics[$id][$field] = mysql_escape_string($row[$field]);
					}
				}
			}
		}
	}
	/**
	 * Save current data
	 */
	public function save()
	{
		foreach ($this->_changed_fields as $id)
		{
			$q = 'UPDATE `saved_pic` SET';
			foreach ($this->_compare as $key => $field)
			{
				$q .= $key ? ', ' : ' ';
				$q .= "$field = '{$this->pics[$id][$field]}' ";
			}
			$q .= "WHERE `saved_id` = {$this->id} AND `doc_id` = $id LIMIT 1";
			$this->_db->query($q);
            
           self::recache($id);
		}
		
		if (!empty($this->_to_delete))
		{
			$this->_db->query("DELETE FROM `saved_pic` 
			WHERE `saved_id` =  {$this->id}
			AND `doc_id` IN (" . implode(',', $this->_to_delete) . ")");
            
            foreach ($this->_to_delete as $id) {
               self::recache($id);
            }
		}
		$this->_to_delete = [];
	}
	/**
	 * Upload pic
	 */
	 public function uploadPic($params)
	 {
        $hash_field = 'hash_' . $params['color_type'];

		if ($params['hash'] && $params['doc_id'])
		{
			$doc_id = (int)$params['doc_id'];
			if ($this->pics[$doc_id][$hash_field] != $params['hash']) {
				throw new Exception(Saved::MESSAGE_DATA_IS_CHANGED);
            }
		}
		
		$img_type = $params['img_type'];
		$ext = pathinfo($_FILES['img']['name'], PATHINFO_EXTENSION);
        
        // FILE UPLOAD
        $pic = file_get_contents($_FILES['img']['tmp_name']);
        $md5 = md5($pic);
        $filename = set_file_path($md5);
        if (!is_file($filename)) {
            file_put_contents($filename, $pic);
        } 
    
        // DB UPDATE
        $extension_field = 'ext_' . $params['color_type'];
        if ($params['doc_id'])
        {
            $this->_db->query("UPDATE `saved_pic` 
                SET `$extension_field` = '$ext'
                    ," . (isset($params['row']) ?  "`" . $params['row'] . "` = 0, " : "") . "`img_type` = '$img_type'
                    , `$hash_field` = '$md5'
                WHERE `saved_id` = {$this->id}
                AND `doc_id` = " . (int)$params['doc_id'] . " LIMIT 1");
            
            $this->_last_doc_id = (int)$params['doc_id'];
        }
        else
        {
            $q = 'INSERT INTO `saved_pic` SET `saved_id` = ' . $this->id . ',
                `img_type` = "' . $params['img_type'] . '",
                `' . $hash_field . '` = "' . $md5 . '",
                `' . $extension_field . '` = "' . $ext .'"';

            $this->_db->query($q);
            $this->_last_doc_id = $this->_db->getOne('select LAST_INSERT_ID()');
        }
        
       self::recache($this->_last_doc_id);
        $this->_load();
	 }
	/**
	 * Swap pics
     * $param int $source_doc_id
     * $param string $source_type
     * $param int $target_doc_id
     * $param string $target_type
     * $param array $params
	 */
	 public function swapPics($source_doc_id, $source_type, $target_doc_id, $target_type, $params)
	 {
        if ($source_doc_id && $target_doc_id) { // SWAP EXISTING PICS
            $source_hash_field = 'hash_' . $source_type;
            $source_ext = 'ext_' . $source_type;
            $q = "SELECT `$source_hash_field` as `hash`, `$source_ext` as `ext` FROM `saved_pic` 
                WHERE `doc_id` = $source_doc_id AND `$source_hash_field` IS NOT NULL AND `$source_hash_field` <> ''";
            $source = $this->_dbr->getRow($q);
            
            $target_hash_field = 'hash_' . $target_type;
            $target_ext = 'ext_' . $target_type;
            $q = "SELECT `$target_hash_field` as `hash`, `$target_ext` as `ext` FROM `saved_pic` 
                WHERE `doc_id` = $target_doc_id AND `$target_hash_field` IS NOT NULL AND `$target_hash_field` <> ''";
            $target = $this->_dbr->getRow($q);
        
            // SOURCE TO TARGET
            $this->_db->query("UPDATE `saved_pic` SET 
                    `ext_$target_type` = '" . $source->ext . "',
                    `hash_$target_type` = '" . $source->hash . "'
                WHERE `saved_id` = {$this->id}
                AND `doc_id` = $target_doc_id LIMIT 1");                    
            
            // TARGET TO SOURCE
            $this->_db->query("UPDATE `saved_pic` SET 
                    `ext_$source_type` = '" . $target->ext . "',
                    `hash_$source_type` = '" . $target->hash . "'
                WHERE `saved_id` = {$this->id}
                AND `doc_id` = $source_doc_id LIMIT 1");                    
            
           self::recache($source_doc_id);
           self::recache($target_doc_id);
        } elseif (!$target_doc_id) { // NEW TARGET ROW
            $source_hash_field = 'hash_' . $source_type;
            $source_ext = 'ext_' . $source_type;
            
            $q = "SELECT `$source_hash_field` as `hash`, `$source_ext` as `ext` FROM `saved_pic` 
                WHERE `doc_id` = $source_doc_id AND `$source_hash_field` IS NOT NULL AND `$source_hash_field` <> ''";
            $row = $this->_db->getRow($q);
            
            $target_extension_field = 'ext_' . $target_type;
            $target_hash_field = 'hash_' . $target_type;
            
            $q = 'INSERT INTO `saved_pic` SET `saved_id` = ' . $this->id . ',
                `img_type` = ' . (int)$params['color_type'] . ',
                `' . $target_hash_field . '` = "' . $row->hash . '",
                `' . $target_extension_field . '` = "' . $row->ext .'"';

            $this->_db->query($q);
            $target_doc_id = $this->_db->getOne('select LAST_INSERT_ID()');
            
            $this->_db->query("UPDATE `saved_pic` SET 
                    `ext_$source_type` = '', 
                    `hash_$source_type` = NULL
                WHERE `saved_id` = {$this->id}
                AND `doc_id` = $source_doc_id LIMIT 1");
                
           self::recache($source_doc_id);
           self::recache($target_doc_id);
        }

        $this->_load();
        
        foreach ($this->pics as $doc_id => $row) { // LEAVE ONLY SWAPED DOC_ID
            if ($doc_id != $source_doc_id && $doc_id != $target_doc_id) {
                unset($this->pics[$doc_id ]);
            }
        }
     }
     /** 
      * Remove cache images for doc_id
      * @param int $doc_id
      */
     public static function recache($doc_id)
     {
        foreach (glob("images/cache/*picid_".$doc_id."_*.*") as $filename) {
            unlink($filename);
            self::logRecache('[DELETED] ' . $filename);
        }
            
        self::generateCachedVersions($doc_id, ['undef_src_sa_picid_[[pic_id]]_type_[[type]]_x_200_image.jpg'], true);
            
        update_version('saved_doc', 'data', $doc_id, '');
     }
    /**
      * Create cached versions of pic 
      * Use masks from `pic_mask`
      *
      * WARNING: if $fast=true can down the server
      *
      * @param int $doc_id
      * @param array $masks
      * @param bool $fast
      * @return array List of cached pics status
      */
    public function generateCachedVersions($doc_id, $masks = null, $fast = false) 
    {
        if (!$doc_id)
            return;
        
        $dbr = \label\DB::getInstance(\label\DB::USAGE_READ);
        
        if (!isset($masks)) {
            $masks = $dbr->getCol('SELECT `mask` FROM `pic_mask`');
        }
        $return = [];
        
        $list = [];
        $http = 'http://';
        $domain = $_SERVER['SERVER_NAME'];
        $path = '/images/cache/';
        $progress = [];
        foreach ($masks as $mask) {
            if (strpos($mask, '[[pic_id]]') !== false) {
                $progress[] = $http . $domain . $path . $mask;
            }
        }
        
        foreach ($progress as $key => $url) {
            if (strpos($url, '[[type]]') !== false) {
                unset($progress[$key]);
                foreach(['color', 'whitesh', 'whitenosh'] as $type) {
                    $progress[] = str_replace('[[type]]', $type, $url);
                }
            }
        }
        
        foreach ($progress as $key => $url) {
            $progress[$key] = str_replace('[[pic_id]]', $doc_id, $url);
        }
        
        $hashes = $dbr->getRow("SELECT `hash_color`, `hash_whitesh`, `hash_whitenosh` 
                FROM `saved_pic` WHERE `doc_id` = '" . $doc_id . "' ");
        
        if ($hashes)
        {
            foreach(['color', 'whitesh', 'whitenosh'] as $type) {
                if ($hashes->{"hash_{$type}"})
                {
                    $double = $dbr->getRow("SELECT `doc_id`, `hash_color`, `hash_whitesh`, `hash_whitenosh` 
                        FROM `saved_pic` 
                        WHERE 
                            '" . mysql_real_escape_string($hashes->{"hash_{$type}"}) . "' 
                                    IN (`hash_color`, `hash_whitesh`, `hash_whitenosh`) 
                                    AND `doc_id` != '" . $doc_id . "'
                        LIMIT 1");
       
                    if ($double)
                    {
                        $image_paths = [];
                        foreach ($masks as $mask) {
                            if (strpos($mask, '[[pic_id]]') !== false) {
                                $image_paths[] = $_SERVER['DOCUMENT_ROOT'] . 'images/cache/' . $mask;
                            }
                        }

                        foreach ($image_paths as $path)
                        {
                            $double_type = '';
                            foreach(['color', 'whitesh', 'whitenosh'] as $_dtype) 
                            {
                                if ($double->{"hash_{$_dtype}"} == $hashes->{"hash_{$type}"})
                                {
                                    $double_type = $_dtype;
                                    break;
                                }
                            }

                            if ($double_type)
                            {
                                $source = str_replace(['[[type]]', '[[pic_id]]'], [$double_type, $double->doc_id], $path);
                                $dest = str_replace(['[[type]]', '[[pic_id]]'], [$type, $doc_id], $path);

                                if (file_exists($source) && is_link($source))
                                {
                                    $resource = readlink($source);
                                    symlink($resource, $dest);
                                }
                            }
                        }
                    }
                }
            }
        }

        $curl_res = [];
        if ($fast) { // FAST USING curl_multi, hard for server
            $mh = curl_multi_init();
            $connections = [];
            foreach ($progress as $key => $url) {
                $ch = curl_init(); 
                curl_setopt($ch, CURLOPT_URL, $url); 
                curl_setopt($ch, CURLOPT_NOBODY, TRUE); 
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, true);
                if ((APPLICATION_ENV === 'develop') || (APPLICATION_ENV === 'heap') ) { curl_setopt($ch, CURLOPT_USERPWD, 'b:b'); }
                curl_multi_add_handle($mh, $ch);
                $connections[$key] = $ch;
            }
            
            do {
                curl_multi_exec($mh, $running);
                curl_multi_select($mh);
            } while ($running > 0);
            
            foreach($connections as $key => $ch) {
                $curl_res[$progress[$key]] = curl_multi_getcontent($ch);  
                curl_multi_remove_handle($mh, $ch);
            }
            curl_multi_close($mh);
        } else { // SLOW, easy for server
            foreach ($progress as $key => $url) {
                $ch = curl_init(); 
                curl_setopt($ch, CURLOPT_URL, $url); 
                curl_setopt($ch, CURLOPT_NOBODY, TRUE); 
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, true);
                if ((APPLICATION_ENV === 'develop') || (APPLICATION_ENV === 'heap') ) { curl_setopt($ch, CURLOPT_USERPWD, 'b:b'); }
                $curl_res[$url] = curl_exec($ch);
                curl_close();
            }
        }
        
        foreach ($progress as $key => $url) {
            $path = pathinfo($url);
            $res = file_exists($_SERVER['DOCUMENT_ROOT'] . "/images/cache/" . $path['basename']);
            
            if ($res === false) {
                $message = '[ERROR] ' . $url . '; ';
                
                $fp = fopen($_SERVER['DOCUMENT_ROOT'] . 'last_create_images_error.log', 'w');
                $string = print_r($curl_res, true);
                fwrite($fp, $string);
                fclose($fp);
            } else {
                $message = '[OK] ' . $url. '; ';
            }
            
            $return[] = $message;
            self::logRecache($message);
        }
        
        return $return;
    }
     /**
      * Load versions 
      */
     private function _loadVersions()
     {
        $doc_ids = array_keys($this->pics);
        $this->versions = $this->_dbr->getAssoc("select id, max(version) as version from versions 
            where id in (" . implode(',', $doc_ids) . ") group by id");
     }
     /**
      * Join ebay image ordering
      * @return SavedPic
      */
    public function withEbayOrdering()
    {
        $q = "SELECT doc_id, ordering
            FROM saved_master_pics
            WHERE saved_id = {$this->id}";
        $ordering = $this->_dbr->getAssoc($q);
        
        foreach ($this->pics as $pic_id => $data) {
            $this->pics[$pic_id]['ebay_order'] = isset($ordering[$pic_id]) ? $ordering[$pic_id] : 0;
        }
        
        return $this;
    }
     /**
      * Load pic text using language $lang
      * @param string $lang
      */
    public function withText($lang)
    {
        $pick_ids = [];
        foreach ($this->pics as $pic_id => $data) {
            $pick_ids[] = (int)$pic_id;
        }
        
        if ( ! $pick_ids) {
            return false;
        }
        
        $q = "SELECT `id`, `field_name` as `type`, `value` as `html` FROM `translation` 
            WHERE table_name = 'saved_pic'
            AND `id` IN (" . implode(', ', $pick_ids) . ") AND `language` = ?";
        $translations = $this->_dbr->getAll($q, null, $lang);

        foreach ($translations as $html) {
            if (isset($this->pics[$html->id])) {
                $field = $html->type;
                $this->pics[$html->id][$field] = $html->html;
            }
        }
        
        foreach ($this->pics as $pic_id => $data) {
            foreach (['color', 'whitesh', 'whitenosh'] as $type) {
                $type_field = $type . 'Text';
                $this->pics[$pic_id]['path_prefix'][$type] = !empty($this->pics[$pic_id][$type_field]) ? $lang : 'undef';
                $type_field = $type . 'Subdescription';
                $this->pics[$pic_id]['subdescription'][$type] = !empty($this->pics[$pic_id][$type_field]) ? $this->pics[$pic_id][$type_field] : false;
            }
        }
        
        return $this;
     }
     
     /**
      * Log recache operations to file
      * $param string $message
      */
    public static function logRecache($message) {
        $fp = fopen($_SERVER['DOCUMENT_ROOT'] . 'create_images.log', 'a');
        $string = '['. date('Y-m-d H:i:s') . '] ' . $message . "\r\n";
        fwrite($fp, $string);
        fclose($fp);
    }
}