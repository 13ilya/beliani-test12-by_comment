<?php
class PostLoader {
	const PATH = 'images/blog/';
	
	private $_shopCatalogue;
	
	private $_url;
	private $_posts;
	
	function __construct($shopCatalogue)
	{
		$this->_shopCatalogue = $shopCatalogue;
	}
	
	function getBlogUrl()
	{
		switch ($this->_shopCatalogue->_shop->id)
		{
			case 1:
				return 'http://blog.beliani.ch/b04dff4433109aa08735fcc9560de01f/';
				break;
			case 2:
				return 'http://blog.beliani.co.uk/b04dff4433109aa08735fcc9560de01f/';
				break;
			case 3:
				return 'http://blog.beliani.de/b04dff4433109aa08735fcc9560de01f/';
				break;
			case 6:
				return 'http://blog.beliani.com/b04dff4433109aa08735fcc9560de01f/';
				break;
			case 7:
				return 'http://blog.beliani.fr/b04dff4433109aa08735fcc9560de01f/';
				break;
			case 8:
				return 'http://blog.beliani.at/b04dff4433109aa08735fcc9560de01f/';
				break;
			case 9:
				return 'http://blog.beliani.ca/b04dff4433109aa08735fcc9560de01f/';
				break;
			case 12:
				return 'http://blog.beliani.pl/b04dff4433109aa08735fcc9560de01f/';
				break;
			case 19:
				return 'http://blog.beliani.be/b04dff4433109aa08735fcc9560de01f/';
				break;
			default:
				return false;
		}
	}
	
	private function _valid()
	{
		$valid = true;
		foreach ($this->_posts as $post)
		{
			$valid = $valid && $post->title && $post->link && $post->thumbnail;
		}
		return $valid;
	}
	
	private function _getBlogData()
	{
		$this->_posts = cacheGet('_getBlogData', $this->_shopCatalogue->_shop->id, $this->_shopCatalogue->_shop->lang);
		if ($this->_posts) 
		{
			return true;
		}
	
		$ch = curl_init();
		$timeout = 10;
		curl_setopt($ch, CURLOPT_URL, $this->_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
		curl_setopt ($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.1) Gecko/20061204 Firefox/2.0.0.1');
		$json = curl_exec($ch);
		
		if ($json === false)
		{
			$message = curl_errno($ch) . ': ' . curl_error($ch);
		}
		
		curl_close($ch);	
		
		if ($json !== false)
		{
			$posts = json_decode($json);
			if ($posts)
			{
				$this->_posts = $posts;
				if ($this->_valid())
					cacheSet(
						'_getBlogData', 
						$this->_shopCatalogue->_shop->id, 
						$this->_shopCatalogue->_shop->lang,
						$this->_posts
					);
				return true;
			}
			else
				return false;
		}
		else
		{
			mail('gordeevss@gmail.com', 'curl error', $message);
			return false;
		}
	}
	
	private function _needUpdate($filename)
	{
		if (!file_exists($filename))
			return true;
		else
		{	
			$expired = strtotime('+24 hours', filemtime($filename));
			if (time() > $expired)
				return true;
			else
				return false;
		}
	}
	
	private function _processImages()
	{
		foreach ($this->_posts as $key => $post)
		{
			if	($post->thumbnail)
			{
				$filename = basename($post->thumbnail);

				if ($this->_needUpdate(self::PATH . $filename))
				{
					$thumbnail = $post->thumbnail;
					$thumbnail = str_replace('https:', 'http:', $thumbnail);
					$ch = curl_init($thumbnail);
					$fp = fopen(self::PATH . $filename, 'wb');
					curl_setopt($ch, CURLOPT_FILE, $fp);
					curl_setopt($ch, CURLOPT_HEADER, 0);
					curl_exec($ch);
					curl_close($ch);
					fclose($fp);
					
					$pathinfo = pathinfo(self::PATH . $filename);
					$filepath = __DIR__ . '/../' . self::PATH . $filename;
					switch(strtolower($pathinfo['extension'])) 
					{
						case 'jpg':
						case 'jpeg':
							exec('jpegoptim -f -s --all-progressive ' . $filepath);
						break;
						case 'png':
							exec('pngout ' . $filepath . ' -c2 -f3 -b128 -kbKGD -v');
							exec('pngout ' . $filepath . ' -c3 -f3 -b128 -kbKGD -v');
						break;
					}
				}
				
				$this->_posts[$key]->thumbnail = self::PATH . $filename;
			}
		}
	}
	
	function getResult()
	{
		$this->_url = $this->getBlogUrl();
		if ($this->_url && $this->_getBlogData())
		{
			$this->_processImages();
			return $this->_posts;
		}
		else
			false;
	}
}
?>