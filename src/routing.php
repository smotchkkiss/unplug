<?php

namespace Em4nl\Unplug;


require_once __DIR__ . '/utils.php';


/**
 * Convenience interface to the default Router instance
 */

if (!function_exists('Em4nl\Unplug\_use')) {
    function _use($middleware) {
        $request_middlewares = &_get_request_middlewares();
        $request_middlewares[] = $middleware;
    }
}

if (!function_exists('Em4nl\Unplug\get')) {
    function get($path, $callback) {
        _get_default_router()->get($path, function($context) use ($callback) {
            _apply_request_middlewares($context);
            $response = $callback($context);
            if ($response) {
                \Em4nl\U\send_content_response($response);
            }
        });
    }
}

if (!function_exists('Em4nl\Unplug\post')) {
    function post($path, $callback) {
        _get_default_router()->post($path, function($context) use ($callback) {
            define('UNPLUG_DO_CACHE', FALSE);
            _apply_request_middlewares($context);
            $response = $callback($context);
            if ($response) {
                \Em4nl\U\send_content_response($response);
            }
        });
    }
}

if (!function_exists('Em4nl\Unplug\catchall')) {
    function catchall($callback) {
        _get_default_router()->catchall(function($context) use ($callback) {
            define('UNPLUG_DO_CACHE', FALSE);
            _apply_request_middlewares($context);
            $response = $callback($context);
            if ($response) {
                \Em4nl\U\send_content_response($response);
            }
        });
    }
}

if (!function_exists('Em4nl\Unplug\dispatch')) {
    function dispatch() {
        if (UNPLUG_CACHE_ON &&
            !(defined('UNPLUG_FRONT_CONTROLLER') && UNPLUG_FRONT_CONTROLLER)) {
            // if caching is on, and the front_controller wasn't
            // used, then we want to do the whole thing, including
            // caching, now
            $cache = _get_default_cache();
            $served_from_cache = $cache->serve();
            if (!$served_from_cache) {
                $cache->start();
                _get_default_router()->run();
                $cache->end(!defined('UNPLUG_DO_CACHE') || UNPLUG_DO_CACHE);
            }
        } else {
            // if either we don't want to use caching, or the
            // front_controller was indeed used, which means that
            // it decided to still load up WordPress, we just run
            // the router
            _get_default_router()->run();
        }
    }
}

if (!function_exists('Em4nl\Unplug\_get_default_router')) {
    function _get_default_router() {
        static $router;
        if (!isset($router)) {
            $router = new \Em4nl\U\Router();
        }
        // TODO set base path if WordPress is installed in subdir
        return $router;
    }
}

if (!function_exists('Em4nl\Unplug\_get_default_cache')) {
    function _get_default_cache() {
        if (defined('UNPLUG_FRONT_CONTROLLER') && UNPLUG_FRONT_CONTROLLER) {
            global $_unplug_cache;
            return $_unplug_cache;
        }
        static $cache;
        if (!isset($cache)) {
            $cache = new \Em4nl\U\Cache(UNPLUG_CACHE_DIR);
        }
        return $cache;
    }
}

if (!function_exists('Em4nl\Unplug\_get_request_middlewares')) {
    function &_get_request_middlewares() {
        static $request_middlewares;
        if (!isset($request_middlewares)) {
            $request_middlewares = array();
            $request_middlewares[] = function(&$context) {
                $site_url = get_site_url();
                while (substr($site_url, -1) === '/') {
                    $site_url = substr($site_url, 0, -1);
                }
                $context['site_url'] = $site_url;
                $context['current_url'] = $site_url . $context['path'];
                $context['theme_url'] = get_template_directory_uri();
                $context['site_title'] = get_bloginfo();
                $context['site_description'] = get_bloginfo('description');
            };
        }
        return $request_middlewares;
    }
}

if (!function_exists('Em4nl\Unplug\_apply_request_middlewares')) {
    function _apply_request_middlewares(&$context) {
        foreach (_get_request_middlewares() as $middleware) {
            $res = $middleware($context);
            if ($res !== NULL) {
                $context = $res;
            }
        }
    }
}
