<?php
/**
 * {license_notice}
 *
 * @copyright   {copyright}
 * @license     {license_link}
 */

use Composer\Autoload;

define('ROOT_DIR', dirname(__DIR__));
define('ROOT_APP_DIR', __DIR__);
define('LIB_DIR', ROOT_DIR.'/lib');
define('TMP_DIR', ROOT_APP_DIR.'/tmp');
define('TMP_DEPRECATED_DIR', ROOT_DIR.'/tmp');

// include the module Autoloader

require_once(ROOT_APP_DIR . '/modules/GenerateLabel/bootstrap.php');

// include the composer Autoloader
$composer_load = require_once(ROOT_APP_DIR . '/utility/autoload.php');