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


class send_json_Test extends TestCase {

    protected $preserveGlobalState = FALSE;
    protected $runTestInSeparateProcess = TRUE;

    function test_sets_correct_status_header() {
        global $status_header_calls;
        $this->assertEmpty($status_header_calls);
        send_json(array(), true, '200');
        $this->assertEquals(count($status_header_calls), 1);
        $this->assertEquals($status_header_calls[0], '200');
    }

    function test_sets_correct_content_type_header() {
        global $header_calls;
        $this->assertEmpty($header_calls);
        send_json(array(), true, '200');
        $this->assertEquals(count($header_calls), 1);
        $this->assertEquals($header_calls[0], 'Content-Type: application/json');
    }

    function test_defines_unplug_response_sent() {
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        send_json(array(), true, '200');
        $this->assertTrue(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertTrue(UNPLUG_RESPONSE_SENT);
    }

    function test_sends_data_with_wp_send_json() {
        global $wp_send_json_calls;
        $data = array('test' => 178, 'test2' => array());
        $this->assertEmpty($wp_send_json_calls);
        send_json($data, true, '200');
        $this->assertEquals(count($wp_send_json_calls), 1);
        $this->assertEquals($wp_send_json_calls[0], $data);
    }
}
