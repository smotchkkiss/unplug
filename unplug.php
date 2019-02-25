<?php

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
    define('UNPLUG_CACHE', FALSE);
}

if (!defined('UNPLUG_FRONT_CONTROLLER')) {
    define('UNPLUG_FRONT_CONTROLLER', FALSE);
}


/**
 * Convenience interface to the default Router instance
 */

function _use($middleware) {
    $request_middlewares = &_get_request_middlewares();
    $response_middlewares = &_get_response_middlewares();
    if (is_callable($middleware)) {
        $request_middlewares[] = $middleware;
    } else {
        if (isset($middleware['request'])) {
            $request_middlewares[] = $middleware['request'];
        }
        if (isset($middleware['response'])) {
            $response_middlewares[] = $middleware['response'];
        }
    }
}

function get($path, $callback) {
    _get_default_router()->get($path, function(&$context) use ($callback) {
        _apply_request_middlewares($context);
        $response = $callback($context);

        _apply_response_middlewares($context, $response);
        if ($response) {
            $response = make_content_response($response);
            $response->send();

            if (!defined('UNPLUG_DO_CACHE')) {
                define('UNPLUG_DO_CACHE', $response->is_cacheable());
            }
        }
    });
}

function post($path, $callback) {
    _get_default_router()->post($path, function(&$context) use ($callback) {
        _apply_request_middlewares($context);
        $response = $callback($context);
        _apply_response_middlewares($context, $response);

        if ($response) {
            $response = make_content_response($response);
            $response->send();

            if (!defined('UNPLUG_DO_CACHE')) {
                define('UNPLUG_DO_CACHE', $response->is_cacheable());
            }
        }
    });
}

function catchall($callback) {
    _get_default_router()->catchall(function(&$context) use ($callback) {
        _apply_request_middlewares($context);
        $response = $callback($context);
        _apply_response_middlewares($context, $response);

        if ($response) {
            $response = make_content_response($response);
            $response->send();

            if (!defined('UNPLUG_DO_CACHE')) {
                define('UNPLUG_DO_CACHE', $response->is_cacheable());
            }
        }
    });
}

function dispatch() {
    if (!UNPLUG_FRONT_CONTROLLER && UNPLUG_CACHE) {
        $cache = _get_default_cache();
        if (!$cache->serve()) {
            $cache->start();
            _get_default_router()->run();
            $cache->end(
                !defined('UNPLUG_DO_CACHE') || UNPLUG_DO_CACHE
            );
        }
    } else {
        _get_default_router()->run();
    }
}

function _get_default_router() {
    static $router;
    if (!isset($router)) {
        $router = new Router();
    }
    // TODO set base path if WordPress is installed in subdir
    return $router;
}

function _get_default_cache() {
    if (UNPLUG_FRONT_CONTROLLER) {
        global $_unplug_cache;
        return $_unplug_cache;
    }
    static $cache;
    if (!isset($cache)) {
        if (!defined('UNPLUG_CACHE_DIR')) {
            throw new \Exception(
                "UNPLUG_CACHE_DIR is not defined. This usually means you forgot"
                . " to call Em4nl\Unplug\unplug in your functions.php"
            );
        } else {
            $cache = new Cache(UNPLUG_CACHE_DIR);
        }
    }
    return $cache;
}

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
            $context['current_url'] = $site_url.$context['path'];
            $context['theme_url'] = get_template_directory_uri();
            $context['site_title'] = get_bloginfo();
            $context['site_description'] = get_bloginfo('description');
        };
    }
    return $request_middlewares;
}

function &_get_response_middlewares() {
    static $response_middlewares;
    if (!isset($response_middlewares)) {
        $response_middlewares = array();
    }
    return $response_middlewares;
}

function _apply_request_middlewares(&$context) {
    foreach (_get_request_middlewares() as $middleware) {
        $res = $middleware($context);
        if ($res !== NULL) {
            $context = $res;
        }
    }
}

function _apply_response_middlewares(&$context, &$response) {
    foreach (_get_response_middlewares() as $middleware) {
        $res = $middleware($context, $response);
        if ($res !== NUll) {
            $response = $res;
        }
    }
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
        UNPLUG_CACHE && _get_default_cache()->flush();
        if (isset($options['on_save_post'])) {
            call_user_func(
                $options['on_save_post'],
                UNPLUG_CACHE ? _get_default_cache() : NULL
            );
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
        UNPLUG_CACHE && _get_default_cache()->flush();
    });
}


function hide_wp_sample_permalink() {
    add_filter('get_sample_permalink_html', function() {
        return '';
    });
}
