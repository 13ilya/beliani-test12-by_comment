<?php

function smarty_function_barcodeurl($params, &$smarty)
{
    require_once $smarty->_get_plugin_filepath('shared', 'escape_special_chars');
    
    foreach ($params as $_key => $_val) {
        $_val = rawurlencode(str_replace('/', '%2F', $_val));
        
        switch ($_key) {
            case 'number':
            case 'font':
            case 'line2':
            case 'color':
            case 'leftside':
            case 'txnid':
            case 'type':
                $$_key = $_val;
                if ($_key != 'number')
                {
                    $pars .= '_' . $_key . '_' . $_val;
                }
                break;
            case 'frame':
            case 'fontsize':
            case 'height':
            case 'barwidth':
                $_val = (int)$_val;
                $pars .= '_' . $_key . '_' . $_val;
                break;
            default:
                $smarty->trigger_error("barcodehtml: extra attribute '$_key' not found in plugin", E_USER_NOTICE);
                break;
        }
    }
    
    if (!$number)
    {
        $number = 'undef';
    }

    return "/barcodes/{$number}{$pars}_barcode.png";
}
