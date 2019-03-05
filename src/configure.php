<?php

namespace Em4nl\Unplug;


if (!defined('UNPLUG_CACHE_DIR') || !defined('UNPLUG_CACHE_ON')) {
    do {
        $dir = dirname(__FILE__);
        $path = $dir . '/unplug-config.php';
        $exists = file_exists($path);
    } while (!$exists && $dir !== '/');
    if ($exists) {
        require_once $path;
    }
    unset($dir, $path, $exists);
}

if (!defined('UNPLUG_CACHE_ON')) {
    define('UNPLUG_CACHE_ON', FALSE);
}

if (!defined('UNPLUG_CACHE_DIR')) {
    define(
        'UNPLUG_CACHE_DIR',
        sys_get_temp_dir() . '/unplug_cache.' . uniqid()
    );
}
