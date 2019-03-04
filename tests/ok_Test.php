<?php

namespace Em4nl\Unplug;

include_once(dirname(__DIR__) . '/responses.php');

use PHPUnit\Framework\TestCase;

// mock the status_header function
if (!function_exists('Em4nl\Unplug\status_header')) {
    $status_header_calls = [];
    function status_header(string $status) {
        global $status_header_calls;
        $status_header_calls[] = $status;
    }
}

// mock the header function
if (!function_exists('Em4nl\Unplug\header')) {
    $header_calls = [];
    function header(string $header) {
        global $header_calls;
        $header_calls[] = $header;
    }
}

// mock wp_send_json function
if (!function_exists('Em4nl\Unplug\wp_send_json')) {
    $wp_send_json_calls = [];
    function wp_send_json(Array $data) {
        global $wp_send_json_calls;
        $wp_send_json_calls[] = $data;
    }
}


class ok_Test extends TestCase {

    protected $preserveGlobalState = FALSE;
    protected $runTestInSeparateProcess = TRUE;

    function test_sends_empty_html_without_args() {
        global $status_header_calls;
        global $header_calls;
        global $wp_send_json_calls;
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        ob_start();
        ok();
        $this->assertEquals(ob_get_clean(), '');
        $this->assertEquals(array_pop($status_header_calls), '200');
        $this->assertEquals(array_pop($header_calls), 'Content-Type: text/html');
        $this->assertEmpty($wp_send_json_calls);
        $this->assertTrue(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertTrue(UNPLUG_RESPONSE_SENT);
        $this->assertTrue(UNPLUG_DO_CACHE);
    }

    function test_sends_the_html_from_first_arg() {
        global $status_header_calls;
        global $header_calls;
        global $wp_send_json_calls;
        $html = '<h1>WURM</h1>';
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        ob_start();
        ok($html);
        $this->assertEquals(ob_get_clean(), $html);
        $this->assertEquals(array_pop($status_header_calls), '200');
        $this->assertEquals(array_pop($header_calls), 'Content-Type: text/html');
        $this->assertEmpty($wp_send_json_calls);
        $this->assertTrue(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertTrue(UNPLUG_RESPONSE_SENT);
        $this->assertTrue(UNPLUG_DO_CACHE);
    }

    function test_sets_the_cache_flag_true_correctly() {
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        ob_start();
        ok('', true);
        ob_end_clean();
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertTrue(UNPLUG_DO_CACHE);
    }

    function test_sets_the_cache_flag_false_correctly() {
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        ob_start();
        ok('', FALSE);
        ob_end_clean();
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertFalse(UNPLUG_DO_CACHE);
    }

    function test_sends_json_when_called_with_array() {
        global $wp_send_json_calls;
        $data = array(5, 6, 7, 8, 9);
        ok($data);
        $this->assertEquals(array_pop($wp_send_json_calls), $data);
    }

    /**
     * @expectedException Exception
     */
    function test_throws_when_called_with_wrong_first_arg_1() {
        ok(NULL);
    }

    /**
     * @expectedException Exception
     */
    function test_throws_when_called_with_wrong_first_arg_2() {
        ok(true);
    }

    /**
     * @expectedException Exception
     */
    function test_throws_when_called_with_wrong_first_arg_3() {
        ok(-0.1);
    }

    /**
     * @expectedException Exception
     */
    function test_throws_when_called_with_wrong_first_arg_4() {
        ok(new \StdClass());
    }

    /**
     * @expectedException Exception
     */
    function test_throws_when_called_with_wrong_second_arg_1() {
        ok('', 1);
    }

    /**
     * @expectedException Exception
     */
    function test_throws_when_called_with_wrong_second_arg_2() {
        ok('', null);
    }

    /**
     * @expectedException Exception
     */
    function test_throws_when_called_with_wrong_second_arg_3() {
        ok('', new \StdClass());
    }

    /**
     * @expectedException Exception
     */
    function test_throws_when_called_with_wrong_second_arg_4() {
        ok('', 'wurm');
    }

    /**
     * @expectedException Exception
     */
    function test_throws_when_called_with_wrong_second_arg_5() {
        ok('', []);
    }
}