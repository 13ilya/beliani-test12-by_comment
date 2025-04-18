<?php

require_once 'connect.php';
require_once 'util.php';

global $debug;

$dont_log = true;

$react_controller = requestVar('controller', '');

$fname = "reactjs/dist/main_{$react_controller}.js";

$smarty->assign('react_version', \Config::get(null, null, 'react_version'));
$smarty->assign('react_filetime', filemtime($fname));
$smarty->assign('react_controller', $react_controller);

$smarty->display('react.tpl');
