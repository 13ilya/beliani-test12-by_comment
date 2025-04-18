<?php
/**
 * @author Alexander Rubtsov <RubtsovAV@gmail.com>
 * Date: 06.10.2017
 * Time: 13:13
 */

require_once __DIR__ . '/core/_autoload.php';

spl_autoload_register(function ($class) {
    $class = str_replace('\\', '/', $class);
    if (is_file(LIB_DIR . '/' . $class . '.php')) {
        include_once LIB_DIR . '/' . $class . '.php';
    } elseif (is_file(LIB_DIR . '/models/' . $class . '.php')) {
        include_once LIB_DIR . '/models/' . $class . '.php';
    } elseif (property_exists('PHPExcel_Autoloader', 'Load')) {
        PHPExcel_Autoloader::Load($class);
    } else {
        //throw new Exception("couldn't load class ($class)");
    }
});
