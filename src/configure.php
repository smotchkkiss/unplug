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
    define(
        'UNPLUG_CACHE_DIR',
        sys_get_temp_dir() . '/unplug_cache.' . uniqid()
    );
}
