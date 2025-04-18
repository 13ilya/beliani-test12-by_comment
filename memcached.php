<?php

/**
 * File to work with redis
 * @todo rename file
 */

$redis = false;
//if (class_exists('Redis')) {
//    $redis = \label\RedisProvider::getInstance(\label\RedisProvider::USAGE_CACHE);
//}

#usage sample
#$v = 'asd';
#cacheSet('getBlock(0, topmenu)', '1', 'german',$v); 
#cacheGet('getBlock(0, topmenu)', '1', 'german') . "\n";
#
#cacheSet('sa_csv(ShopDesription_french,1177)', '0', '','test'); 
#cacheGet('sa_csv(ShopDesription_french,1177)', '0', '');
#cacheClear('%'.''.'%', '');
#cacheShow('%'.''.'%', '');
#sample end

/**
 * @todo filter params used for $key (like mysql_real_escape_string for sql)
 */
function cacheGet($fn, $shop_id, $lang) {
    if ($fn != 'versions()')
    {
        if (file_exists(__DIR__ . '/DISABLEREDIS'))
        {
            return false;
        }
    }
    
    global $redis;
    if (!$redis) {
        return false;
    }

    $fn = str_replace(' ', '', $fn);
    $shop_id = (int)$shop_id;

    $key = "~~{$shop_id}~~{$fn}~~{$lang}~~";
    if (!$redis->get($key))
        return false;
    $val = gzuncompress($redis->get($key));
    return unserialize($val);
}

/**
 * 
 * @global type $redis
 * @param type $fn
 * @param type $shop_id
 * @param type $lang
 * @param type $value
 * @param type $ttl default 86400 - 1 day
 * @return boolean
 */
function cacheSet($fn, $shop_id, $lang, $value, $ttl = 86400) {

    global $redis;
    if (!$redis) {
        return false;
    }

    $fn = str_replace(' ', '', $fn);
    $shop_id = (int)$shop_id;

    $key = "~~{$shop_id}~~{$fn}~~{$lang}~~";
    return $redis->set($key, gzcompress(serialize($value), 9), $ttl);
}

function cacheClear($fn, $shop_id = 0, $lang = '', $delete = false) {
    
    if (stripos($_SERVER['HTTP_HOST'], 'proloheap') !== false || stripos($_SERVER['HTTP_HOST'], 'prolodev') !== false)
    {
        $delete = true;
    }
    
    $recache = new \label\RedisCache\RedisBuild();
    if ( ! $delete) {
        logRedis("cacheClear FN: $fn");
        $recache->pushClearQueue($fn, $shop_id, $lang);
        return false;
    }

    global $redis;
    if (!$redis) {
        return false;
    }

    $fn = str_replace(' ', '', $fn);
    $fn = str_replace('(', '\(', $fn);
    $fn = str_replace(')', '\)', $fn);

    $shop_id = (int)$shop_id;

    if (!$shop_id)
        $shop_id = '[^~]*';
    if (!strlen($lang))
        $lang = '[^~]*';

    $fn = preg_replace('/(\%)/', '[^~]*', $fn);
    $like_pattern = "/^~~{$shop_id}~~{$fn}~~{$lang}~~$/u";

    $deleted = [];
    
    $keys = RPGetList();
    foreach ($keys as $k) {
        if (preg_match($like_pattern, $k)) {
            $redis->delete($k);
            $deleted[] = $k;
        }
    }
    
    return $deleted;
}

function cacheClearFast($fn, $shop_id = 0, $lang = '') {
    global $redis;
    if (!$redis) {
        return false;
    }

    $shop_id = (int)$shop_id;

    $like_pattern = "~~{$shop_id}~~{$fn}~~{$lang}~~";
    
    $redis->delete($like_pattern);
    
    logRedis("cacheClearFast FN: $fn");
}

function cacheShow($fn, $shop_id = 0, $lang = '') {
    global $redis;
    if (!$redis) {
        return false;
    }

    $fn = str_replace(' ', '', $fn);
    $fn = str_replace('(', '\(', $fn);
    $fn = str_replace(')', '\)', $fn);

    $shop_id = (int)$shop_id;

    if (!$shop_id)
        $shop_id = '[^~]*';
    if (!strlen($lang))
        $lang = '[^~]*';

    $fn = preg_replace('/(\%)/', '[^\~~]*', $fn);
    $like_pattern = "/^~~{$shop_id}~~{$fn}~~{$lang}~~$/u";

    $keys = RPGetList();
    foreach ($keys as $k) {
        if (preg_match($like_pattern, $k)) {
            print "like_fn matched: $like_pattern -> key:$k Value:" . gzuncompress($redis->get($k)) . "\n<br>\n";
        }
    }
}

function RPGetList() {
    global $redis;
    if (!$redis) {
        return false;
    }

    return $redis->keys('*');
}

function logRedis($message) {
    global $loggedUser;
    $user = $loggedUser ? $loggedUser->get("username") : (isset($_COOKIE["ebas_username"]) ? $_COOKIE["ebas_username"] : "[USERNAME]");

    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $host = preg_replace('#^www\.#iu', '', $host);

    $self = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';
    $query = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';

    $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

    $file = basename(__FILE__);

    //echo date('Y-m-d H:i:s') . "\t$message\n";
    file_put_contents('redis.txt', date('Y-m-d H:i:s') . "\t$file\t$host\t$self\t$query\t$ip\t$user\t$message\n", FILE_APPEND | LOCK_EX);
}
