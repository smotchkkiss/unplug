<?php

namespace Em4nl\Unplug;


if (!defined('UNPLUG_CACHE_DIR') || !defined('UNPLUG_CACHE_ON')) {
    $dir = realpath(__FILE__);
    do {
        $dir = dirname($dir);
        $path = $dir . '/unplug-config.php';
        $exists = @include $path;
    } while (!$exists && $dir !== '/');
    unset($dir, $path, $exists);
}

if (!defined('UNPLUG_CACHE_ON')) {
    define('UNPLUG_CACHE_ON', FALSE);
}

if (!defined('UNPLUG_CACHE_DIR')) {
    $temp_dir = sys_get_temp_dir();
    if ($temp_dir[strlen($temp_dir) - 1] !== '/') {
        $temp_dir .= '/';
    }
    $temp_dir .= 'unplug_cache.' . uniqid();
    define('UNPLUG_CACHE_DIR', $temp_dir);
    unset($temp_dir);
}
