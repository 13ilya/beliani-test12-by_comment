<?php
/**
 * function allows to get link to fields change log
 * @author Ilya Khalizov
 * @version 1.0
 */


/**
 * @desc get link to fields change log
 * @param $params
 * @param $smarty
 * @return string
 */
function smarty_function_change_log($params, &$smarty)
{
    return 'change_log.php?' . implode('&', $params);
}


