<?php
/**
 * {license_notice}
 *
 * @copyright   baidush.k
 * @license     {license_link}
 */

error_reporting(E_ALL ^ E_NOTICE);
ini_set('display_errors', true);
//date_default_timezone_set('UTC');

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . "autoload.php";

$CFG_LABEL = new \label\Registry();

require_once(ROOT_MOD_DIR . "/settings/config.ini.php");

