<?php

// Use this to implement a minimal front controller to use instead
// of WordPress' index.php
//
// it works like this: rename WordPress' index.php to something
// else, e.g. wp-index.php. create a new index.php that looks about
// so:
//
// <?php
//
// include_once __DIR__ . '/wp-content/themes/testtheme/plugins/front-controller.php';
//
// Em4nl\Unplug\front_controller(
//     __DIR__ . '/_unplug_cache',
//     __DIR__ . '/wp-index.php'
// );
//
// Note that this bypasses WordPress completely, which means that
// it also ignores UNPLUG_CACHE constant defined in wp-config.php
//
// (Instead of renaming index.php, you might also e.g. change the
// default redirect rule in your .htaccess)
//


namespace Em4nl\Unplug;


include_once dirname(__FILE__) . '/cache.php';

function front_controller(
    $cache_dir,
    $wp_index_php,
    callable $invalidate=NULL
) {
    global $_unplug_cache;
    $_unplug_cache = new Cache($cache_dir);
    if ($invalidate !== NULL) {
        $_unplug_cache->invalidate($invalidate);
    }
    $served_from_cache = $_unplug_cache->serve();
    if (!$served_from_cache) {
        $_unplug_cache->start();
        include_once $wp_index_php;
        $_unplug_cache->end(!defined('UNPLUG_DO_CACHE') || UNPLUG_DO_CACHE);
    }
}
