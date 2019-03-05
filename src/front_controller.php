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
// require_once __DIR__ . '/wp-content/themes/testtheme/vendor/autoload.php';
//
// Em4nl\Unplug\front_controller([
//     'cache_dir' => __DIR__ . '/_unplug_cache',
//     'wp_index_php' => __DIR__ . '/wp-index.php',
// ]);
//
// Note that this bypasses WordPress completely, which means that
// it also ignores UNPLUG_CACHE_ON constant defined in
// wp-config.php
//
// (Instead of renaming index.php, you might also e.g. change the
// default redirect rule in your .htaccess)
//


namespace Em4nl\Unplug;


if (!function_exists('Em4nl\Unplug\front_controller')) {
    function front_controller(Array $options=array()) {
        global $_unplug_cache;
        define('UNPLUG_FRONT_CONTROLLER', TRUE);
        if (!isset($options['cache_dir'])) {
            throw new \Exception('cache_dir has to be defined!');
        }
        if (!isset($options['wp_index_php'])) {
            throw new \Exception('wp_index_php has to be defined!');
        }
        $_unplug_cache = new \Em4nl\U\Cache($options['cache_dir']);
        if (isset($options['invalidate'])) {
            $_unplug_cache->invalidate($options['invalidate']);
        }
        $served_from_cache = $_unplug_cache->serve();
        if (!$served_from_cache) {
            $_unplug_cache->start();
            include_once $options['wp_index_php'];
            $_unplug_cache->end(!defined('UNPLUG_DO_CACHE') || UNPLUG_DO_CACHE);
        }
    }
}
