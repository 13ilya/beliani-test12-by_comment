<?php
/**
 * Pic url plugin for new images logic
 * @param array $params
 * @param Smarty $params
 * @return string
 */
function smarty_function_saImageUrl($params, &$smarty)
{
    require_once $smarty->_get_plugin_filepath('function', 'imageurl');
    
    if ($params['src'] = 'sa') {
        if (!in_array($params['type'], ['color', 'whitesh', 'whitenosh'])) {
            $params['type'] = 'color';
        }       
        unset($params['saved_id']);
    }

    return smarty_function_imageurl($params, $smarty);
}
?>