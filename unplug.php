<?php
/*
Plugin Name: unplug
Description: Unplug WP's assumptive defaults
Version: 0.0.4
Author: Emanuel Tannert, Wolfgang Schöffel
Author URI: http://unfun.de
*/

// TODO: flush cache on theme deactivation/deletion

namespace unplug;


include_once dirname(__FILE__) . '/utils.php';
include_once dirname(__FILE__) . '/router.php';
include_once dirname(__FILE__) . '/responses.php';
include_once dirname(__FILE__) . '/cache.php';
include_once dirname(__FILE__) . '/wp_functions.php';


if (!defined('ABSPATH')) {
    exit;
}


// make sure UNPLUG_CACHE is defined,
// so we don't have to check that everytime
if (!defined('UNPLUG_CACHE')) {
    define('UNPLUG_CACHE', false);
}


/**
 * Gets the current url, but without protocol/host
 * By Giuseppe Mazzapica @gmazzap as published on
 * https://roots.io/routing-wp-requests/
 *
 * @returns String url
 */
function get_current_url() {

    $current_url = trim(esc_url_raw(add_query_arg(array())), '/');
    $home_path = trim(parse_url(home_url(), PHP_URL_PATH), '/');
    if ($home_path && strpos($current_url, $home_path) === 0) {
        $current_url = trim(substr($current_url, strlen($home_path)), '/');
    }

    return $current_url;
}


/**
 * Convenience interface to the default Router instance
 */

function _use($middleware) {
    _get_default_router()->_use($middleware);
}

function get($path, $callback) {
    _get_default_router()->get($path, $callback);
}

function post($path, $callback) {
    _get_default_router()->post($path, $callback);
}

function catchall($callback) {
    _get_default_router()->catchall($callback);
}

function dispatch() {
    _get_default_router()->run();
}

function _get_default_router() {
    static $router;
    if (!isset($router)) {
        $router = new Router();
        $router->_use([
            'request' => function(&$context) {
                $site_url = get_site_url();
                $context['site_url'] = $site_url;
                // TODO sure that site_url never has a trailing slash?
                $context['current_url'] = $site_url.$context['path'];
                $context['theme_url'] = get_template_directory_uri();
                $context['site_title'] = get_bloginfo();
                $context['site_description'] = get_bloginfo('description');
            },
            'response' => function($context, $response) {
                $response = make_content_response($response);
                if (UNPLUG_CACHE && $response->is_cacheable()) {
                    $cache = Cache::get_instance();
                    $cache->add($context['path'], $response);
                }
                $response->send();
            },
        ]);
    }
    // TODO set base path if WordPress is installed in subdir
    return $router;
}


/**
 * Call unplug\unplug in your functions.php to
 * prevent WordPress from running its default
 * query and template selection thing.
 * Also switch on caching here.
 *
 * @param array options
 */
function unplug($options=array()) {

    // check if this is an admin-panel
    // request, and if not, prevent wordpress from parsing
    // the url and running a query based on it.
    // ---
    // if the path starts with admin or login,
    // which are two convenient wordpress redirects, we don't
    // want to prevent the parsing, either. Same goes for paths
    // starting with wp-content so a '*'-route won't falsely
    // catch uploads or static theme assets.
    $path = parse_url(get_current_url(), PHP_URL_PATH);
    $allowed = !preg_match('/^(admin|login|wp-content)/', $path);
    $allowed = $allowed && (!is_admin() || (defined('DOING_AJAX') && DOING_AJAX));

    if ($allowed) {
        add_action('do_parse_request', function($do_parse, $wp) {
            $wp->query_vars = array();
            remove_action('template_redirect', 'redirect_canonical');
            return FALSE;
        }, 30, 2);
    }

    // if caching is on, make sure to empty the cache on
    // save_post and set a few constants so the router
    // knows whether we want to cache or not
    if (UNPLUG_CACHE) {

        if (isset($options['cache_dir'])) {

            define('UNPLUG_CACHE_DIR', $options['cache_dir']);

        } else {

            define('UNPLUG_CACHE_DIR', __DIR__ . '/_unplug_cache');
        }

        $after_save_post = function() use ($options) {

            // this function will only be called if caching
            // is on, so it's safe to assume that
            // UNPLUG_CACHE_DIR will be set
            Cache::get_instance()->flush();

            if (isset($options['on_save_post'])) {
                $options['on_save_post']($cache);
            }
        };

        add_action('save_post', $after_save_post, 20);

        if (is_acf_active()) {
            add_action('acf/save_post', $after_save_post, 20);
        }
    }

    // hide the sample permalink on the edit post page when unplug
    // is in use, because the whole point of unplug is to implement
    // your own routing, so what wordpress thinks what the url of a
    // post is will often be wrong.
    add_filter('get_sample_permalink_html', function() {
        return '';
    });
}
