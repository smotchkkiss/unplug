<?php

// TODO
// - layout cache dir more like it could be a static site
// - but still cache queries via .htaccess
// - add option to use wordpress base dir as cache dir
// - in addition to requests with queries, use .htaccess redirects
//   for paths that would otherwise overwrite wordpress files
//   (like xmlrpc.php)
// - save a list of all cached files somewhere that we can use to
//   delete them all when the cache is flushed
// - create a cache pre-filling mechanism close to ssg

namespace Em4nl\Unplug;


include_once dirname(__FILE__) . '/utils.php';
include_once dirname(__FILE__) . '/router.php';
include_once dirname(__FILE__) . '/responses.php';
include_once dirname(__FILE__) . '/cache.php';
include_once dirname(__FILE__) . '/wp_functions.php';


if (!defined('ABSPATH')) {
    exit;
}


if (!defined('UNPLUG_CACHE')) {
    define('UNPLUG_CACHE', false);
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
        $router->_use(function(&$context) {
            $site_url = get_site_url();
            while (substr($site_url, -1) === '/') {
                $site_url = substr($site_url, 0, -1);
            }
            $context['site_url'] = $site_url;
            $context['current_url'] = $site_url.$context['path'];
            $context['theme_url'] = get_template_directory_uri();
            $context['site_title'] = get_bloginfo();
            $context['site_description'] = get_bloginfo('description');
        });
        $cache = Cache::get_instance();
        $router->_use($cache->as_plugin());
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

    if (is_frontend_request()) {
        prevent_wp_default_query();
    }

    if (UNPLUG_CACHE) {
        set_cache_dir($options);
        flush_cache_on_save_post_or_settings($options);
        flush_cache_on_switch_theme();
    }

    hide_wp_sample_permalink();
}


function is_frontend_request() {
    $path = _get_default_router()->get_request_path();
    $wp_path_regex = '/^(admin|login|wp-content|wp-json)/';
    $is_wp_path = preg_match($wp_path_regex, $path);
    if ($is_wp_path) {
        return FALSE;
    }

    // as to why we have to exclude DOING_AJAX explicitly, see:
    // https://codex.wordpress.org/AJAX_in_Plugins
    // ("Both front-end and back-end Ajax requests use
    // admin-ajax.php so is_admin() will always return true in your
    // action handling code. When selectively loading your Ajax
    // script handlers for the front-end and back-end, and using
    // the is_admin() function, your wp_ajax_(action) and
    // wp_ajax_nopriv_(action) hooks MUST be inside the is_admin()
    // === true part.")
    $doing_ajax = defined('DOING_AJAX') && DOING_AJAX;
    if (is_admin() && !$doing_ajax) {
        return FALSE;
    }

    return TRUE;
}


function prevent_wp_default_query() {
    add_action('do_parse_request', function($do_parse, $wp) {
        $wp->query_vars = array();
        remove_action('template_redirect', 'redirect_canonical');
        return FALSE;
    }, 30, 2);
}


function set_cache_dir($options) {
    if (isset($options['cache_dir'])) {
        define('UNPLUG_CACHE_DIR', $options['cache_dir']);
    } else {
        define('UNPLUG_CACHE_DIR', __DIR__ . '/_unplug_cache');
    }
}


function flush_cache_on_save_post_or_settings($options) {
    $after_save_post = function() use ($options) {
        Cache::get_instance()->flush();
        if (isset($options['on_save_post'])) {
            $options['on_save_post']($cache);
        }
    };

    if (is_acf_active()) {
        add_action('acf/save_post', $after_save_post, 20);
    } else {
        add_action('save_post', $after_save_post, 20);
    }

    add_action(
        'updated_option',
        function($option_name, $old_value, $value) use ($after_save_post) {
            $after_save_post();
        },
        10,
        3
    );
}


function flush_cache_on_switch_theme() {
    add_action('switch_theme', function() {
        Cache::get_instance()->flush();
    });
}


function hide_wp_sample_permalink() {
    add_filter('get_sample_permalink_html', function() {
        return '';
    });
}
