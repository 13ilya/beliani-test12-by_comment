<?php
require_once('./App/utility/autoload.php');

use MatthiasMullie\Minify;

class Minifier {
    /**
     * CDN domain for templates
     */
    public $cdn_domain;
	public $enabled;
	public $version;
	
	private $_settings;
	
	function __construct()
	{
		$this->enabled = isset($GLOBALS['CONFIGURATION']['enable_minify']) ? (bool)$GLOBALS['CONFIGURATION']['enable_minify'] : false;
		$this->version = isset($GLOBALS['CONFIGURATION']['shop_version']) ? (int)$GLOBALS['CONFIGURATION']['shop_version'] : null;
		$this->_settings = parse_ini_file('settings.ini', true);
		if ($this->_settings === false)
			throw new Exception('Can\'t read settings.ini file');
	}
    
    /**
     * Set cdn domain used for css/js path
     * @param string $cdn_domain
     */
    public function setCDN($cdn_domain) 
    {
        $this->cdn_domain = $cdn_domain;
    }
	
	function minify()
	{
		foreach ($this->_settings as $shop => $params)
		{
			$layout = $shop == 'main' ? '' : '_' . $shop;
			if (isset($params['css']) && is_array($params['css']) && !empty($params['css'])) {
				$minifier = new Minify\CSS();
				foreach ($params['css'] as $source) {
					$minifier->add($source);
				}
				$minifiedPath = "css/styles$layout.min.css";
				$minifier->minify($minifiedPath);
			}
			
			if (isset($params['js']) && is_array($params['js']) && !empty($params['js'])) {
				$minifier = new Minify\JS();
				foreach ($params['js'] as $source) {
					$minifier->add($source);
				}
				$minifiedPath = "js/js$layout.min.js";
				$minifier->minify($minifiedPath);
			}
		}			
	}
	
	function insertCss($layout)
	{
		if ($this->enabled) {
			$file = empty($layout) ? 'styles.min.css' : "styles$layout.min.css";
			$html = '<link rel="stylesheet" href="' . $this->cdn_domain . '/css/' . $file . '?v' . $this->version . '" type="text/css"/>';
		} else {
			$html = '';
			$layout = empty($layout) ? 'main' : str_replace('_', '', $layout);
			foreach($this->_settings[$layout]['css'] as $params) {
				$html .= "<link rel=\"stylesheet\" href=\"" . $this->cdn_domain . "/$params" . '?v' . $this->version . "\" type=\"text/css\"/>\r\n";
			}
		}
		return $html;
	}
	
	function insertJs($layout)
	{
		if ($this->enabled) {
			$file = empty($layout) ? 'js.min.js' : "js$layout.min.js";
			$html = '<script defer type="text/javascript" src="' . $this->cdn_domain . '/js/' . $file . '?v' . $this->version . '"></script>';
		} else {
			$html = '';
			$layout = empty($layout) ? 'main' : str_replace('_', '', $layout);
			foreach($this->_settings[$layout]['js'] as $params) {
				$html .= "<script defer type=\"text/javascript\" src=\"" . $this->cdn_domain . "/$params" . '?v' . $this->version . "\"></script>\r\n";
			}
		}
		return $html;
	}
    
    /**
     * Generate html to insert custom font 
     * @param array $collection
     * @return string
     */
    function insertFont($collection)
	{
        $html = '<style type="text/css">';
        foreach ($collection as $name => $font) {
            $html .= '@font-face {src: url("' . $this->cdn_domain . '/' . $font . '") format("truetype");font-family: "' . $name . '";}';
        }
        $html .= '</style>';
        return $html;
    }
	
	function minifyHtml($html) 
	{
		require('./lib/Minify_HTML.php');
		$html = Minify_HTML::minify($html, array(
			'cssMinifier' => __NAMESPACE__ .'\Minifier::minifyCss'
		));
		return $html;	
	}
	
	public static function minifyCss($css)
	{
		$minifier = new Minify\CSS();
		$minifier->add($css);
		return $minifier->minify();
	}
	
	public static function minifyJs($js)
	{
		$minifier = new Minify\JS();
		$minifier->add($js);
		return $minifier->minify();
	}
}
?>