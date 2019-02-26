<?php

namespace Em4nl\Unplug;

include_once(dirname(__DIR__) . '/responses.php');

use PHPUnit\Framework\TestCase;

class _set_do_cache_if_undefined_Test extends TestCase {

    protected $preserveGlobalState = FALSE;
    protected $runTestInSeparateProcess = TRUE;

    function test_set_do_cache_if_undefined1() {
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        _set_do_cache_if_undefined(true);
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertTrue(UNPLUG_DO_CACHE);
        _set_do_cache_if_undefined(false);
        $this->assertTrue(UNPLUG_DO_CACHE);
    }

    function test_set_do_cache_if_undefined2() {
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        _set_do_cache_if_undefined(false);
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertFalse(UNPLUG_DO_CACHE);
        _set_do_cache_if_undefined(true);
        $this->assertFalse(UNPLUG_DO_CACHE);
    }

    function test_set_do_cache_if_undefined3() {
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        define('UNPLUG_DO_CACHE', true);
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertTrue(UNPLUG_DO_CACHE);
        _set_do_cache_if_undefined(false);
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertTrue(UNPLUG_DO_CACHE);
    }

    function test_set_do_cache_if_undefined4() {
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        define('UNPLUG_DO_CACHE', false);
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertFalse(UNPLUG_DO_CACHE);
        _set_do_cache_if_undefined(true);
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertFalse(UNPLUG_DO_CACHE);
    }
}
