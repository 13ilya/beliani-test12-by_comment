<?php

	class Kohs {

		private static $instance;
		public $mc = null;
		private $_memcache_initialised = false;

		public static function factory(){
			if(!self::$instance){
				self::$instance = new self;
				self::$instance->db = new Patch_Helper_Databases;
				self::$instance->html = new Patch_Helper_Html;
			}
			return self::$instance;
		}

		public function allowImage($time){
			$this->memcacheInit();
			$this->mc->set('image_last_update',time());
			$last_update = $this->mc->get('image_last_update');
			if($last_update && is_numeric($time) && $time > 0){
				return $time < $last_update;
			}
			return false;
		}

		public function setImageChange(){
			$this->memcacheInit();
			$this->mc->set('image_last_update',time());
		}

		private function get_static_page_hash(){
			$file = md5($_SERVER['SCRIPT_FILENAME']);
			$query = md5($_SERVER['QUERY_STRING']);
			$uri = md5($_SERVER['REQUEST_URI']);
			return $this->session.hash('md5',$file.$query.$uri);
		}

		public function staticCacheEnable(){
			$this->memcacheInit();
			$this->html->enable();
		}

		public function enableQueryCache($limit=null){
			$this->memcacheInit();
			$this->db->queryCacheState(true);
			if(!is_null($limit)){ $this->db->queryCacheTimer($limit); }
			return $this;
		}

		public function queryCache($query,$do='empty'){
			if($this->db->availableForCaching($query)){
				if($do == 'empty'){
					return $this->db->getQueryCacheResults($query);
				}
				return $this->db->insertQueryCache($query,$do);
			}
			return false;
		}

		public function memcacheInit($subversion=false){
			try{
				if(!$this->_memcache_initialised){
					$this->_memcache_initialised = true;
					$this->mc = new Memcache;
					if(!$this->mc->connect('localhost', 11211)){
						throw new Exception('Unable to connect memcache');
					}
				}
				if(!$this->mc->getVersion()){
					if(!$subversion){
						$this->_memcache_initialised = false;
						$this->memcacheInit(true);
						return;
					}
					throw new Exception("Memcache has lost connection");
				}
			}catch(Exception $e){
				die($e->getMessage());
			}
		}

	}


	class Patch_Helper_Databases {

		protected $_ignore_one_query = false;
		protected $_query_cache_state = false;
		protected $_query_cache_time = 10; // seconds

		public function queryCacheState($state=false){
			$this->_query_cache_state = $state;
			return $this;
		}

		public function queryCacheTimer($time=10){
			$this->_query_cache_time = $time;
			return $this;
		}

		public function availableForCaching($query){
			if($this->_query_cache_state === true){
				return strtolower(substr($query,0,6)) == 'select';
			}
			return false;
		}

		public function getQueryCacheResults($query){
			if($this->_ignore_one_query){
				$this->_ignore_one_query = false;
				return false;
			}
			$hash = $this->getUserHash($query);
			if($rowdata = Kohs::factory()->mc->get($hash)){
				if(substr($rowdata,0,7) == '=encode'){
					$data = substr($rowdata,7,strlen($rowdata)-7);
					$rowdata = unserialize($data);
				}
				return $rowdata=='empty'?array():($rowdata === false ? 'false' : $rowdata);
			}
			return false;
		}

		public function insertQueryCache($query,$results){
			$hash = $this->getUserHash($query);
			if(is_object($results) || is_array($results)){
				$results = '=encode'.serialize($results);
			}
			Kohs::factory()->mc->set($hash,(empty($results)?'empty':$results),0,$this->_query_cache_time);			
		}

		private function getUserHash($query){
			@session_start();
			if(!isset($_COOKIE['_user_hash'])){ setcookie('_user_hash',uniqid()); }
			$user = hash('crc32b',$_SERVER['REMOTE_ADDR']).$_COOKIE['_user_hash'];
			$query = hash('adler32',$query);
			$token = hash('crc32',$user.$query);
			$hash = "SQL{$user}_{$query}_{$token}";
			return $hash;
		}

		public function ignoreNext(){
			$this->_ignore_one_query = true;
			return $this;
		}

	}

	class Patch_Helper_Html {

		private $enabled = false;

		private function getUserHash($keyword){
			@session_start();
			if(!isset($_COOKIE['_user_hash'])){ setcookie('_user_hash',uniqid()); }
			$user = hash('crc32b',$_SERVER['REMOTE_ADDR']).$_COOKIE['_user_hash'];
			$keyword = hash('adler32',$keyword);
			$token = hash('crc32',$user.$keyword);
			$hash = "HTML{$user}_{$keyword}_{$token}";
			return $hash;
		}

		public function store($info){
			Kohs::factory()->memcacheInit();
			$address = hash('crc32b',$_SERVER['QUERY_STRING'].'_'.md5($_SERVER['REQUEST_URI']));
			$keyword = $info['keyword'].'_'.$address;
			@session_start();
			if(isset($_COOKIE["shop_lang"])){ $keyword.='_'.$_COOKIE["shop_lang"]; }
			if($info['user'] == 'true'){
				$keyword = $this->getUserHash($keyword);
			}
			$file = dirname(__FILE__).'/tmp/'.$keyword;
			file_put_contents($file, $info['content']);

			Kohs::factory()->mc->set($keyword,'htmldata',0,(int)$info['time']);
		}

		public function tryTemplateHtml($keyword,$identifyUser=true){
			$data = false;
			if($this->enabled){
				Kohs::factory()->memcacheInit();
				$address = hash('crc32b',$_SERVER['QUERY_STRING'].'_'.md5($_SERVER['REQUEST_URI']));
				$keyword = $keyword.='_'.$address;
				@session_start();
				if(isset($_COOKIE["shop_lang"])){ $keyword.='_'.$_COOKIE["shop_lang"]; }
				if($identifyUser){
					$keyword = $this->getUserHash($keyword);
				}
				$mc = Kohs::factory()->mc->get($keyword);
				if($mc && $mc == 'htmldata'){
					if(is_file(dirname(__FILE__).'/tmp/'.$keyword)){
						echo file_get_contents('tmp/'.$keyword);
						return true;
					}else{
						Kohs::factory()->mc->delete($keyword);
					}
				}
			}
			return $data;
		}

		public function enable(){
			$this->enabled = true;
			ob_start();
			register_shutdown_function('sanitize_cache');
		}

	}	

	function sanitize_cache(){
		$buffer = ob_get_contents();
		ob_end_clean();
		preg_match_all('/<cache[^>]+>(.*)<\/cache>/siU',$buffer,$caches);

		if(count($caches[0])){
			$create = array();
			foreach($caches[0] as $cacheId=>$c){
				preg_match_all('/<cache keyword="(.*)" user="(true|false)" time="(.*)">/sU', $c, $g);
				$create[] = array(
					'time' => $g[3][0],
					'keyword' => $g[1][0],
					'user' => $g[2][0],
					'content' => $caches[1][$cacheId]
				);
			}

			if(count($create)){
				foreach($create as $row){
					Kohs::factory()->html->store($row);
				}
			}
		}

		// $search = array('/\>[^\S ]+/s','/[^\S ]+\</s','/(\s)+/s');
		// $replace = array('>','<','\\1');
		// $buffer = preg_replace($search, $replace, $buffer);
		echo $buffer;
	}