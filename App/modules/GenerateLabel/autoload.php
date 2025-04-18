<?php
/**
 * {license_notice}
 *
 * @copyright   {copyright}
 * @license     {license_link}
 */

use label\Autoloader;
use Composer\Autoload;

define('ROOT_MOD_DIR', dirname(__FILE__));
// init autoloader
require_once(ROOT_MOD_DIR . '/classes/Autoloader/Interface.php');
require_once(ROOT_MOD_DIR . '/classes/Autoloader.php');
$composer_load = require_once(ROOT_APP_DIR . '/utility/autoload.php');

$loader = new Autoloader();
$loader->addNamespace("label", realpath(ROOT_MOD_DIR . DIRECTORY_SEPARATOR . "classes" . DIRECTORY_SEPARATOR));
$loader->register();

/**
 * Autoload root classes
 */
spl_autoload_register(function($class) {
    if (is_file(LIB_DIR.'/'.$class.'.php')) {
        include_once LIB_DIR.'/'.$class.'.php';
    }
    else if (property_exists('PHPExcel_Autoloader', 'Load')) {
        PHPExcel_Autoloader::Load($class);
    }
    else {
        //throw new Exception("couldn't load class ($class)");
    }
});
