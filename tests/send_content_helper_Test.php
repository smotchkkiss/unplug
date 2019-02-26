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

class send_content_helper_Test extends TestCase {

    protected $preserveGlobalState = FALSE;
    protected $runTestInSeparateProcess = TRUE;

    function test_sets_correct_status_header() {
        global $status_header_calls;
        $this->assertEmpty($status_header_calls);
        send_content_helper('', true, '200', 'text/html');
        $this->assertEquals(count($status_header_calls), 1);
        $this->assertEquals($status_header_calls[0], '200');
    }

    function test_sets_correct_content_type_header() {
        global $header_calls;
        $this->assertEmpty($header_calls);
        send_content_helper('', true, '200', 'text/html');
        $this->assertEquals(count($header_calls), 1);
        $this->assertEquals($header_calls[0], 'Content-Type: text/html');
    }

    function test_defines_unplug_response_sent() {
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        send_content_helper('', true, '200', 'text/html');
        $this->assertTrue(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertTrue(UNPLUG_RESPONSE_SENT);
    }
}
