<?php

namespace Em4nl\Unplug;


if (!function_exists('Em4nl\Unplug\is_acf_active')) {
    function is_acf_active() {
        if (defined('ABSPATH')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');

            return is_plugin_active('advanced-custom-fields-pro/acf.php')
                || is_plugin_active('advanced-custom-fields/acf.php');
        }
        return FALSE;
    }
}
