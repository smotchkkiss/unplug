<?php

namespace Em4nl\Unplug;


function _check_send_args($body, $is_cacheable, $status) {
    if (!is_string($body) && !is_array($body)) {
        throw new \Exception('$body must be a string or an array');
    }
    if (!is_bool($is_cacheable)) {
        throw new \Exception('$is_cacheable must be a boolean');
    }
    if (!is_string($status)) {
        throw new \Exception('$status must be a string');
    }
}


function _set_do_cache_if_undefined($is_cacheable) {
    if (!defined('UNPLUG_DO_CACHE')) {
        define('UNPLUG_DO_CACHE', $is_cacheable);
    }
}


function send_content_helper($body, $is_cacheable, $status, $content_type) {
    _check_send_args($body, $is_cacheable, $status);
    _set_do_cache_if_undefined($is_cacheable);
    status_header($status);
    header("Content-Type: $content_type");
    define('UNPLUG_RESPONSE_SENT', TRUE);
}


function send_text($body, $is_cacheable, $status) {
    send_content_helper($body, $is_cacheable, $status, 'text/plain');
    echo $body;
}


function send_html($body, $is_cacheable, $status) {
    send_content_helper($body, $is_cacheable, $status, 'text/html');
    echo $body;
}


function send_json($body, $is_cacheable, $status) {
    send_content_helper($body, $is_cacheable, $status, 'application/json');
    wp_send_json($body);
}


function send_xml($body, $is_cacheable, $status) {
    send_content_helper($body, $is_cacheable, $status, 'text/xml');
    echo $body;
}


function send_content_response($response, $is_cacheable=TRUE, $found=TRUE) {
    if (!is_bool($found)) {
        throw new \Exception('$found must be boolean');
    }

    $status = $found ? '200' : '404';

    if (defined('UNPLUG_RESPONSE_SENT') && UNPLUG_RESPONSE_SENT) {
        return;
    }

    if (is_string($response)) {
        send_html($response, $is_cacheable, $status);
    } elseif (is_array($response)) {
        send_json($response, $is_cacheable, $status);
    } else {
        throw new \Exception('$response must be string or array');
    }
}


function send_redirect($location, $is_permanent=TRUE) {
    if (defined('UNPLUG_RESPONSE_SENT') && UNPLUG_RESPONSE_SENT) {
        return;
    }

    if ($location[0] !== '/') {
        $location = '/' . $location;
    }
    if ($location[strlen($location) - 1] !== '/') {
        $location .= '/';
    }
    $location = get_site_url() . $location;

    if ($is_permanent) {
        $status = '301';
    } else {
        $status = '302';
    }

    _set_do_cache_if_undefined(FALSE);

    define('UNPLUG_RESPONSE_SENT', TRUE);

    wp_redirect($location, $status);
}


/**
 * Convenience functions for use in routes
 */

function ok($response='', $is_cacheable=true) {
    send_content_response($response, $is_cacheable);
}

function not_found($response='') {
    send_content_response($response, FALSE, FALSE);
}

function moved_permanently($location='/') {
    send_redirect($location);
}

function found($location='/') {
    send_redirect($location, false);
}
