<?php
/**
 * Generate css for images with banner
 * @param array $params
 * @param Smarty $params
 * @return string
 */
function smarty_function_bannerCss($params, &$smarty)
{
    $res = '';
    
    if (is_array($params['banner']) && !empty($params['banner']) && is_array($params['config']) && !empty($params['config']) && $params['x']) {
        $background_color = $params['banner']['top_left_banner_color'];
        $color = $params['banner']['top_left_text_color'];
        
        $font_size = ($params['banner']['top_left_banner_size'] * $params['x']) / $params['config']['shop_img_width'];
        $font_size = str_replace(',', '.', $font_size);
        
        $top = ($params['banner']['top_left_banner_v'] * ($params['x']/2)) / $params['config']['shop_img_width'];
        $top = str_replace(',', '.', $top);
        
        $left = ($params['banner']['top_left_banner_height'] + $params['banner']['top_left_banner_height'] * $params['x']) / $params['config']['shop_img_width'];
        $left = str_replace(',', '.', $left);
        
        $font_family = $params['banner']['top_left_banner_font'];
        $res = "background-color: {$background_color};
            font-size: {$font_size}px;
            top: {$top}px;
            color: {$color};
            left: -{$left}px;
            font-family: {$font_family};";
    }

    return $res;
}