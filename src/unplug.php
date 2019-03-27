<?php

namespace Em4nl\Unplug;


/**
 * Call Em4nl\Unplug\unplug in your functions.php to prevent
 * WordPress from running its default query and template selection
 * thing. Also switch on caching here.
 *
 * @param array options
 */
if (!function_exists('Em4nl\Unplug\unplug')) {
    function unplug($options=array()) {

        if (is_frontend_request()) {
            prevent_wp_default_query();
        }

        if (UNPLUG_CACHE_ON) {
            flush_cache_on_save_post_or_settings($options);
            flush_cache_on_switch_theme();
        }

        hide_wp_sample_permalink();
    }
}


if (!function_exists('Em4nl\Unplug\is_frontend_request')) {
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
}


if (!function_exists('Em4nl\Unplug\prevent_wp_default_query')) {
    function prevent_wp_default_query() {
        add_action('do_parse_request', function($do_parse, $wp) {
            $wp->query_vars = array();
            remove_action('template_redirect', 'redirect_canonical');
            return FALSE;
        }, 30, 2);
    }
}


if (!function_exists('Em4nl\Unplug\flush_cache_on_save_post_or_settings')) {
    function flush_cache_on_save_post_or_settings($options) {
        $after_save_post = function() use ($options) {
            UNPLUG_CACHE_ON && _get_default_cache()->flush();
            if (isset($options['on_save_post'])) {
                call_user_func(
                    $options['on_save_post'],
                    UNPLUG_CACHE_ON ? _get_default_cache() : NULL
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

        // flush cache when menu order is changed
        add_action('wp_ajax_update-menu-order', $after_save_post);
    }
}


if (!function_exists('Em4nl\Unplug\flush_cache_on_switch_theme')) {
    function flush_cache_on_switch_theme() {
        add_action('switch_theme', function() {
            UNPLUG_CACHE_ON && _get_default_cache()->flush();
        });
    }
}


if (!function_exists('Em4nl\Unplug\hide_wp_sample_permalink')) {
    function hide_wp_sample_permalink() {
        add_filter(
            'get_sample_permalink_html',
            function($sample_html) {
                $matches = array();
                $match = preg_match(
                    '/<span id="editable-post-name">(.*?)<\/span>/',
                    $sample_html,
                    $matches
                );
                if (count($matches) < 2) {
                    return '';
                }
                return preg_replace(
                    array(
                        '/Permalink/',
                        '/<a href=".*?">.*?<\/a>/',
                    ),
                    array(
                        'Slug',
                        "<span id=\"editable-post-name\">{$matches[1]}</span>",
                    ),
                    $sample_html
                );
            }
        );
    }
}
