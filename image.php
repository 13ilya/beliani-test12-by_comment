<?php

//die($_SERVER['REQUEST_URI']);
// true - new resize method, false - old method
$newMethod = false;

define('TMP_DIR', __DIR__ . '/tmp/');

error_reporting(E_ALL);

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/util.php';
require_once __DIR__ . '/lib/ShopCatalogue.php';
$time = getmicrotime(true);
$timeres = (string) $_SERVER['SCRIPT_NAME'] . "?" . (string) $_SERVER['QUERY_STRING'];

$replace = [
    'shop_par_value' => 'shopparvalue',
    'shop_id' => 'shopid',
    'shop_logos' => 'shoplogos',
    'saved_id' => 'savedid',
    'op_supplier_gallery' => 'opsuppliergallery',
    '/images/cache/' => 'lang_',
];

$fn = str_replace(array_keys($replace), array_values($replace), $_SERVER['REQUEST_URI']);
$fn = preg_replace('#\?.*$#iu', '', $fn);

$fn_array = explode('_', $fn);
for ($i = 0; $i < count($fn_array); $i += 2) {
    if (!isset($fn_array[$i + 1]))
        break;
    if ($fn_array[$i] == 'savedid')
        $fn_array[$i] = 'saved_id';
    if ($fn_array[$i] == 'shoplogos')
        $fn_array[$i] = 'shop_logos';
    if ($fn_array[$i] == 'shopid')
        $fn_array[$i] = 'shop_id';
    $_GET[$fn_array[$i]] = $fn_array[$i + 1];
}

$ext = pathinfo($fn);
$ext = explode('?', $ext['extension']);
$ext = $ext[0];

if ($_GET['picid'] == 'favicon') {
    $_GET['type'] = 'favicon';
}

$var_lang = $_GET['lang'];
if (!strlen($var_lang)) {
    $var_lang = $_COOKIE["shop_lang"];
}

if (!strlen($var_lang)) {
    $var_lang = 'undef';
}

$folders = array();
$folders[] = $var_lang;
foreach ($_GET as $key => $var) {
    if ($key == 'lang')
        continue;
    if ($key == 'src' && $var == 'shop') {
        
    }
    
    $folders[] = $key;
    $folders[] = $var;
}
$folder_str = implode('_', $folders);

$pic_id = (int) requestVar('picid', 0);
if ( ! $pic_id) {
    $pic_id = (int) requestVar('pic_id', 0);
}

$src = requestVar('src', 'rma_pic');
$shop_id = requestVar('shop_id', 0);

$lang = mysql_real_escape_string(requestVar('lang'));

$inactive = requestVar('inactive', 0);
//print_r($_REQUEST); die();

//checkPermission($loggedUser, 'rma', ACCESS_FULL);


/**
 * add to log treatments to images if not logged 
 * @var $url
 * @var $pic_id
 * @var $src
 */
if (!$loggedUser->data->id) {
    $url = (!empty($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $db->query("
        insert into prologis_log.image_log 
        set 
            src = '" . mysql_real_escape_string($src) . "'
            , picid=" . (int)$pic_id . " 
            , datetime = '" . date('Y-m-d H:i:s') . "'
            , url = '" . mysql_real_escape_string($url) . "'");
}

$picture = false;
switch ($src) {
    case 'issue':
        $picture = $dbr->getOne("SELECT `hash` FROM issue_pic WHERE id = $pic_id");
        $picture = get_file_path($picture);
        break;
        
    case 'employee':
        $picture = $dbr->getOne("select picture as pic from employee where id=$pic_id");
        $picture = get_file_path($picture);
        break;

}

if (PEAR::isError($picture)) {
    aprint_r($picture);
    exit();
}

if (!strlen($ext)) {
    $ext = 'jpg';
}

//if ($src=='fork') die($picture);

ini_set('display_errors', 'off');

$img = imagecreatefromstring($picture);
if ($img === false)
{
    $img = imagecreatefromjpeg(__DIR__ . '/images/no_image.jpg');
}

if (isset($_GET['horizontalflip']) && $_GET['horizontalflip'] == 1) {
    imageflip($img, IMG_FLIP_HORIZONTAL);
}

if ( ! isset($_GET['norotate']))
{
    $exif = exif_read_data("data://image/jpeg;base64," . base64_encode($picture));
    if(!empty($exif['Orientation'])) 
    {
        switch($exif['Orientation']) 
        {
            case 8:
                $img = imagerotate($img,90,0);
                break;
            case 3:
                $img = imagerotate($img,180,0);
                break;
            case 6:
                $img = imagerotate($img,-90,0);
                break;
        }
    }
}

ini_set('display_errors', 'on');

if ($img !== false) {
    $destsx = requestVar('x', imagesx($img));
    if (!$destsx || $destsx > imagesx($img))
        $destsx = imagesx($img);
    if ($destsx > requestVar('maxx', $destsx))
        $destsx = requestVar('maxx', $destsx);
    $destsy = $destsx * imagesy($img) / imagesx($img);
    if (isset($_GET['maxy'])) {
        $maxy = (int) $_GET['maxy'];
        if ($destsy > $maxy) {
            $destsy = $maxy;
            $destsx = $destsy * imagesx($img) / imagesy($img);
        }
    }
    
    $destsy1 = requestVar('y', 0);
    if ($destsy1) {
        $destsx = $destsy1 * imagesx($img) / imagesy($img);
//        $destsx = $destsy1 * imagesx($img2) / imagesy($img2);
//        $img2 = imagecreatetruecolor($destsx, $destsy1);
        $destsy = $destsy1;
    }
    
    $destsx = (int)$destsx;
    $destsy = (int)$destsy;

    // Old method
    //imagecopyresampled ( $img2, $img, 0, 0, 0, 0, $destsx, $destsy, imagesx($img), imagesy($img));
    // New method
    resizedNewMethod($newMethod, $img, $img2, $destsx, $destsy, $ext);
    
    // define the sharpen matrix
    if (isset($_GET['addlogo']) && $shop_id) {
        $code = mysql_real_escape_string($_GET['addlogo']);
        $pic_id1 = $dbr->getOne("select doc_id from shop_doc where shop_id=$shop_id and code='$code'");
        $q = "select md5 from prologis_log.translation_files2  
				where id='" . (int) $pic_id1 . "'
				and table_name='shop_doc' 
				and field_name='data' 
				and language = '$lang'";
        $r = $dbr->getOne($q);
        if (!strlen($r) && strlen($deflang)) {
            $q = "select md5 from prologis_log.translation_files2  
				where id='$pic_id1'
				and table_name='shop_doc' 
				and field_name='data' 
				and language = '$deflang'";
            $r = $dbr->getOne($q);
        }
        
        $r = get_file_path($r);
        
        $img_logo = imagecreatefromstring($r);
        $destsx_logo = imagesx($img2);
        $destsy_logo = $destsx_logo * imagesy($img_logo) / imagesx($img_logo);

        // Old method
        $img_logo2 = imagecreatetruecolor($destsx_logo, $destsy_logo);
        imagecopyresampled($img_logo2, $img_logo, 0, 0, 0, 0, $destsx_logo, $destsy_logo, imagesx($img_logo), imagesy($img_logo));
        $img3 = imagecreatetruecolor(imagesx($img2), imagesy($img2) + $destsy_logo);
        imagecopymerge($img3, $img2, 0, 0, 0, 0, imagesx($img2), imagesy($img2), 100);
        imagecopymerge($img3, $img_logo2, 0, imagesy($img2), 0, 0, imagesx($img_logo2), imagesy($img_logo2), 100);
        $img2 = $img3;
        // New method
        // ...
    }
    
    if ($ext != 'png' && $ext != 'gif')
    {
        if (isset($_GET['x'])) {
            $sharpen = array(
                array(0.0, -1.0, 0.0),
                array(-1.0, 10.0, -1.0),
                array(0.0, -1.0, 0.0)
            );
            // calculate the sharpen divisor
            $divisor = array_sum(array_map('array_sum', $sharpen));
            // apply the matrix
            imageconvolution($img2, $sharpen, $divisor, 0);
        }
    }

    if ($inactive) {
        $img2 = greyscale($img2);
    }
    
    switch (strtolower($ext)) {
        case 'jpg':
        case 'jpeg':
            $file_name_old = "{$folder_str}_image.old." . $ext;
            $file_name = "{$folder_str}_image." . $ext;
            imagejpeg($img2, TMP_DIR . $file_name_old, 100);
            
            $exec_file_name_old = escapeshellarg(TMP_DIR . $file_name_old);
            $exec_file_name = escapeshellarg(TMP_DIR . $file_name);
            
            `/usr/bin/convert $exec_file_name_old -sampling-factor 4:2:0 -strip -quality 85 -interlace JPEG -colorspace RGB $exec_file_name`;
            unlink(TMP_DIR . $file_name_old);
            
            $img_result = file_get_contents(TMP_DIR . $file_name);

//            $result = `jpegoptim -f -s --all-progressive $exec_file_name`;
//            var_dump($result);
//            exit;
            
//            copy(TMP_DIR . $file_name, __DIR__ . "/images/cache/" . $file_name);
            
            $file = file_get_contents(TMP_DIR . $file_name);
            $file_md5 = md5($file);
            if (is_file(TMP_DIR . $file_name) === true) {
                if ( ! file_exists(__DIR__ . "/images/REPO/" . $file_md5)) {
                    copy(TMP_DIR . $file_name, __DIR__ . "/images/REPO/" . $file_md5);
                }
                
                @unlink(__DIR__ . "/images/cache/" . $file_name);
                if (!$loggedIn) symlink("../REPO/" . $file_md5, __DIR__ . "/images/cache/" . $file_name);
            }
            
            unlink(TMP_DIR . $file_name);
            
            header('HTTP/1.0 200 Ok', true, 200);
            header('Status: 200 Ok');
            header("Content-type: image/jpeg");
            
            echo $img_result;
            
//            imagejpeg($img2, NULL, 100);
            break;
        case 'png':
            $ss = $picture;
            $file_name = "{$folder_str}_image.png";
            
            imagepng($img2, TMP_DIR . $file_name);
            $exec_file_name = escapeshellarg(TMP_DIR . $file_name);
            exec("pngout $exec_file_name -v", $output, $return);
            
            $file = file_get_contents(TMP_DIR . $file_name);
            $file_md5 = md5($file);
            if (is_file(TMP_DIR . $file_name) === true) {
                if ( ! file_exists(__DIR__ . "/images/REPO/" . $file_md5)) {
                    copy(TMP_DIR . $file_name, __DIR__ . "/images/REPO/" . $file_md5);
                }
                
                @unlink(__DIR__ . "/images/cache/" . $file_name);
                if (!$loggedIn) symlink("../REPO/" . $file_md5, __DIR__ . "/images/cache/" . $file_name);
            }
            
            unlink(TMP_DIR . $file_name);
            
            header('HTTP/1.0 200 Ok', true, 200);
            header('Status: 200 Ok');
            header("Content-type: image/png");
            
            imagepng($img2);
            break;
        case 'gif':
            $ss = $picture;
            $file_name = "{$folder_str}_image.gif";
            $r = file_put_contents(TMP_DIR . $file_name, $ss);
            // here should be an optimization command but we dont have it for GIF
            
            //copy(TMP_DIR . $file_name, __DIR__ . "/images/cache/" . $file_name);
            
            $file = file_get_contents(TMP_DIR . $file_name);
            $file_md5 = md5($file);
            if (is_file(TMP_DIR . $file_name) === true) {
                if ( ! file_exists(__DIR__ . "/images/REPO/" . $file_md5)) {
                    copy(TMP_DIR . $file_name, __DIR__ . "/images/REPO/" . $file_md5);
                }
                
                @unlink(__DIR__ . "/images/cache/" . $file_name);
                if (!$loggedIn) symlink("../REPO/" . $file_md5, __DIR__ . "/images/cache/" . $file_name);
            }

            unlink(TMP_DIR . $file_name);
//			imagegif($img2,NULL);
            
            header('HTTP/1.0 200 Ok', true, 200);
            header('Status: 200 Ok');
            header("Content-type: image/gif");
            echo $ss;
            break;
    }
    
    imagedestroy($img);
    imagedestroy($img2);
    $timeres .= 'Finish: ' . (getmicrotime(true) - $time) . "\n";
    $time = getmicrotime(true);
    file_put_contents("lastTime.txt", $timeres);
}
else 
{
    $version = \Config::get(null, null, 'shop_version');
    
    if ( ! empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'];
    }
    else {
        $scheme = !empty($_SERVER['HTTPS']) ? "https" : "http";
    }
    
    $ret = new stdClass;
    $ret->url404 = "$scheme://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";
    $ret->referer = (string)$_SERVER['HTTP_REFERER'];
    $ret->get = print_r($_GET, true);
    $ret->email_invoice = $dbr->getOne('select group_concat(email) from users where deleted=0 and get_image_errors');
    
    if ($ret->email_invoice) {
        $result = standardEmail($db, $dbr, $ret, 'get_image_errors');
    }
    
    header("Location: /images/no_image.jpg?ver=$version");
    exit;
}

function resizedNewMethod($new, $img, &$img2, $destsx, $destsy, $ext = '') {
    if ($new) {
        $fn = TMP_DIR . getmicrotime() . '_' . rand(15);
        $fn = tempnam(TMP_DIR, 'IMG');
        $fn_resize = "{$fn}_resize";
        
        $destsx = (int)$destsx;
        $destsy = (int)$destsy;
        
        imagepng($img, $fn);
        
        $fn_esc = escapeshellarg($fn);
        $fn_resize_esc = escapeshellarg($fn_resize);
        
        $command = "/usr/bin/convert $fn_esc -resize {$destsx}x{$destsy} $fn_resize_esc";
        $output = `$command`;
        
//        $img_tmp = new Imagick($fn);
//        $img_tmp->resizeImage($destsx, $destsy, imagick::FILTER_CATROM, 0.9, true);
//        $img_tmp->writeImage($fn);

        $img2 = imagecreatefrompng($fn_resize);
        unlink($fn);
        unlink($fn_resize);
    }
    else {
        $img2 = imagecreatetruecolor($destsx, $destsy);
        
        if ($ext == 'png' || $ext == 'gif') {
            imagealphablending($img2, FALSE);
            imagesavealpha($img2, TRUE);
        }
        
        imagecopyresampled($img2, $img, 0, 0, 0, 0, $destsx, $destsy, imagesx($img), imagesy($img));
    }
    return true;
}

function greyscale($input_image) {
    $image = $input_image;
    $x_dimension = imagesx($image);
    $y_dimension = imagesy($image);
    $new_image = imagecreatetruecolor($x_dimension, $y_dimension);

    for ($x = 0; $x < $x_dimension; $x++) {
        for ($y = 0; $y < $y_dimension; $y++) {

            $rgb = imagecolorat($image, $x, $y);
            $r = ($rgb >> 16) & 0xFF;
            $g = ($rgb >> 8) & 0xFF;
            $b = $rgb & 0xFF;
            
            $pixel_average = ($r + $g + $b) / 3;

            $color = imagecolorallocate($image, $pixel_average, $pixel_average, $pixel_average);
            imagesetpixel($new_image, $x, $y, $color);
        }
    }

    return $new_image;
}
