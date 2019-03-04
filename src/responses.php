<?php

namespace Em4nl\Unplug;


// wrap some functions from uresponse so they can be used from the
// Em4nl\Unplug namespace, too

if (!function_exists('Em4nl\Unplug\ok')) {
    function ok($response='', $is_cacheable=true) {
        \Em4nl\U\ok($response, $is_cacheable);
    }
}

if (!function_exists('Em4nl\Unplug\not_found')) {
    function not_found($response='') {
        \Em4nl\U\not_found($response);
    }
}

if (!function_exists('Em4nl\Unplug\moved_permanently')) {
    function moved_permanently($location) {
        \Em4nl\U\moved_permanently($location);
    }
}

if (!function_exists('Em4nl\Unplug\found')) {
    function found($location) {
        \Em4nl\U\found($location);
    }
}


if (!function_exists('Em4nl\Unplug\send_content_response')) {
    function send_content_response($response, $is_cacheable=TRUE, $found=TRUE) {
        \Em4nl\U\send_content_response($response, $is_cacheable, $found);
    }
}

if (!function_exists('Em4nl\Unplug\send_redirect')) {
    function send_redirect($location, $is_permanent=TRUE) {
        \Em4nl\U\send_redirect($location, $is_permanent);
    }
}


if (!function_exists('Em4nl\Unplug\send_text')) {
    function send_text($body, $is_cacheable, $status) {
        \Em4nl\U\send_text($body, $is_cacheable, $status);
    }
}

if (!function_exists('Em4nl\Unplug\send_html')) {
    function send_html($body, $is_cacheable, $status) {
        \Em4nl\U\send_html($body, $is_cacheable, $status);
    }
}

if (!function_exists('Em4nl\Unplug\send_json')) {
    function send_json($body, $is_cacheable, $status) {
        \Em4nl\U\send_json($body, $is_cacheable, $status);
    }
}

if (!function_exists('Em4nl\Unplug\send_xml')) {
    function send_xml($body, $is_cacheable, $status) {
        \Em4nl\U\send_xml($body, $is_cacheable, $status);
    }
}
