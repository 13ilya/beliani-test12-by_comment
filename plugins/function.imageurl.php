<?php

function smarty_function_imageurl($params, &$smarty)
{
    require_once $smarty->_get_plugin_filepath('shared', 'escape_special_chars');
    if (!strlen($params['ext']) && $params['src'] != 'icon') {
        $params['ext'] = 'jpg';
    }
    foreach ($params as $_key => $_val) {
        switch ($_key) {
            case 'inactive':
            case 'maxy':
            case 'maxx':
            case 'x':
            case 'y':
            case 'picid':
            case 'id':
            case 'type':
            case 'shop_id':
            case 'saved_id':
            case 'id':
            case 'src':
            case 'ext':
            case 'lang_id':
            case 'norotate':
            case 'addlogo':
            case 'white':
                $$_key = $_val;
                if ($_key != 'ext' && $_key != 'lang_id')
                    $pars .= '_' . $_key . '_' . $_val;
            case 'lang':
                break;
            case 'horizontalflip':
                if ($_val) {
                    $$_key = $_val;
                    $pars .= '_' . $_key . '_' . $_val;
                }
                break;
            default:
                if (!is_array($_val)) {
                    $extra .= ' ' . $_key . '="' . smarty_function_escape_special_chars($_val) . '"';
                } else {
                    $smarty->trigger_error("html_image: extra attribute '$_key' cannot be an array", E_USER_NOTICE);
                }
                break;
        }
    }
    $res = '';
    $versions = $smarty->get_template_vars('versions');

    if ($lang && !$lang_id) {
        $lang_id = $lang;
    }
    if ($src != 'sa') {
        if (!strlen($lang_id))
            $lang_id = $smarty->get_template_vars('def_lang');
        if (!strlen($lang_id))
            $lang_id = $smarty->get_template_vars('lang_id');
    }
    if (!strlen($lang_id))
        $lang_id = 'undef';
    
    $vid = $id;
    if (!$vid)
        $vid = $picid;
    if (!$vid)
        $vid = $shop_id;
    
    switch ($src) {
        case 'shop':
            switch ($type) {
                case 'payment':
                    $version = $versions['payment_method']['allow_payment_icon'][$vid];
                    break;
                case 'onstock':
                    $version = $versions['shop']['onstock_icon'][$vid];
                    break;
                default:
                    $version = $versions['shop_doc']['data'][$vid];
            }
            break;
        case 'cat':
            $version = $versions['shop_catalogue']['icon'][$vid];
            break;
        case 'wcat':
            $version = $versions['shop_catalogue']['wicon'][$vid];
            break;
        case 'saved':
        case 'sa':
            $version = $versions['saved_doc']['data'][$vid];
            break;
        case 'banner':
            $version = $versions['shop_banner']['pic'][$vid];
            break;
        case 'icon':
            $version = $versions['icons']['icon'][$vid];
            if (!$ext) {
                $ext = 'png';
            }
            break;
        case 'shoplogos':
            $version = $versions['shop_logos']['logo'][$vid];
            break;
        case 'shoplook':
            $version = $versions['shoplook']['look'][$vid];
            break;
        case 'shopparvalue':
            $version = $versions['Shop_Values']['ValueImg'][$vid];
            break;
        case 'mobilecat':
            if ($type && $type == 'white') {
                $version = $versions['shop_catalogue']['mob_icon_white'][$vid];
            } else {
                $version = $versions['shop_catalogue']['mob_icon'][$vid];
            }
            break;
        case 'article':
            $lang_id = 'undef';
            break;
		default:
			$version = isset($versions[$src][$vid]) ? $versions[$src][$vid] : 1;
    }

    $version = isset($version[$lang_id]) ? $version[$lang_id] : $version['alllangs'];
    $version = $version ? $version : '1';
    $version = $addlogo ? "-$version" : $version;

    return "/images/cache/{$lang_id}{$pars}_image.{$ext}?ver={$version}";
}
