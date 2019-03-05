<?php

namespace Em4nl\Unplug;


if (defined('ABSPATH') && !function_exists('Em4nl\Unplug\is_acf_active')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');

    function is_acf_active() {
        return is_plugin_active('advanced-custom-fields-pro/acf.php')
            || is_plugin_active('advanced-custom-fields/acf.php');
    }
}
