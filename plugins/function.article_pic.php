<?php
function smarty_function_article_pic($params, &$smarty){// tiplink/ layer
    require_once $smarty->_get_plugin_filepath('shared','escape_special_chars');
    require_once $smarty->_get_plugin_filepath('function','imageurl');
    foreach($params as $_key => $_val) {
        switch($_key) {
            case 'article_id':
            case 'title':
            case 'width':
            case 'color':
            case 'add_url':
            case 'noid':
			case 'is_black':
            case 'id':
                $$_key = $_val;
                break;
            default:
                if(!is_array($_val)) {
                    $extra .= ' '.$_key.'="'.smarty_function_escape_special_chars($_val).'"';
                } else {
                    $smarty->trigger_error("html_image: extra attribute '$_key' cannot be an array", E_USER_NOTICE);
                }
                break;
        }
    }
	$res = '';
	if (!$noid && strpos($title, $article_id)===false)
		if($is_black != null && $is_black>1 )
		{
			$title='<span class=\'black\'>'.$article_id.'</span>: '.$title;
		}
		else
		{
			$title=$article_id.': '.$title;
		}


	$global_articles_pics = $smarty->get_template_vars ('global_articles_pics');
	$picid = $global_articles_pics[$article_id];
	if ($picid) {
		$params4image = array('src'=>'article','picid'=>$picid,'x'=>$width);
		$imageurl = smarty_function_imageurl($params4image, $smarty);
		if ($params['display_type'] == 'layer'){
			$image_layer_id = 'img'.$article_id.'_'.rand(10000,99999);
			$res .= '<img src="'.$imageurl.'" style="display: none; width: '.$width.'px; float: right;" id="'.$image_layer_id.'" onclick="$(\'#'.$image_layer_id.'\').toggle();return false;" />';
			$res .= '<a href="/article.php?original_article_id='.$article_id.$add_url.'" onclick="$(\'#'.$image_layer_id.'\').toggle();return false;">'.$title.'</a>';
		}
		elseif ($params['display_type'] == 'url'){
			$res .= $imageurl;
		}
		else {
			$res = '<a target="_blank" href="/article.php?original_article_id='.$article_id.$add_url.'"  onmouseover="Tip(\'<img src=&quot;'.$imageurl.'&quot; width=&quot;'.$width.'&quot;>\')" onmouseout="UnTip()"'
			.($id?'id="'.$id.'"':'')
			.($color?' style="color:'.$color.'"':'')
			.'>'.$title.'</a>';
		}
	} else {
		$res = '<a target="_blank" href="/article.php?original_article_id='.$article_id.$add_url.'" '
		.'>'.$title.'</a>';
	}
    return $res;
}
?>
