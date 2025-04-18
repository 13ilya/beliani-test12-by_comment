<?php
/**
 * Smarty plugin
 * @package Smarty
 * @subpackage plugins
 */

/**
 * Smarty {append} function plugin
 *
 * Type:     function<br>
 * Name:     assign_debug_info<br>
 * Purpose:  assign debug info to the template<br>
 * @author Monte Ohrt <monte at ohrt dot com>
 * @param array unused in this plugin, this plugin uses {@link Smarty::$_config},
 *              {@link Smarty::$_tpl_vars} and {@link Smarty::$_smarty_debug_info}
 * @param Smarty
 */
function smarty_function_append($params, &$smarty)
{
    if (!isset($params['var'])) {
        $smarty->trigger_error("append: missing 'var' parameter", E_USER_WARNING);
        return;
    }

    if (!isset($params['value'])) {
        $smarty->trigger_error("append: missing 'value' parameter", E_USER_WARNING);
        return;
    }
    
    $array = $smarty->get_template_vars ($params['var']);

    if ( ! $array)
    {
        $array = [];
    }
    else if ( !is_array($array))
    {
        $array = (array)$array;
    }
    
    array_push($array, $params['value']);

    $smarty->assign($params['var'], $array);
}

/* vim: set expandtab: */

?>
