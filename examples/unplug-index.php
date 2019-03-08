<?php

// change to TRUE for debugging
if (false) {
    error_reporting(E_ALL);
    ini_set('display_errors', true);
    ini_set('display_startup_errors', true);
}

require_once(
    __DIR__ . '/wp-content/themes/<theme-name>/vendor/autoload.php'
);

Em4nl\Unplug\front_controller(__DIR__ . '/wp-index.php');
