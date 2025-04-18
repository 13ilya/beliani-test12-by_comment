<?php

require_once __DIR__ . '/core/_autoload.php';

spl_autoload_register(function ($class) {
    $class = str_replace('\\', '/', $class);
    if (is_file('lib/' . $class . '.php')) {
        include_once 'lib/' . $class . '.php';
    } elseif (is_file('lib/models/' . $class . '.php')) {
        include_once 'lib/models/' . $class . '.php';
    } elseif (property_exists('PHPExcel_Autoloader', 'Load')) {
        PHPExcel_Autoloader::Load($class);
    } else {
        //throw new Exception("couldn't load class ($class)");
    }
});
