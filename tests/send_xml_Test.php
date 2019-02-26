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


class send_xml_Test extends TestCase {

    protected $preserveGlobalState = FALSE;
    protected $runTestInSeparateProcess = TRUE;

    function test_sets_correct_status_header() {
        global $status_header_calls;
        $this->assertEmpty($status_header_calls);
        ob_start();
        send_xml('iusdfasdf', true, '200');
        ob_end_clean();
        $this->assertEquals(count($status_header_calls), 1);
        $this->assertEquals($status_header_calls[0], '200');
    }

    function test_sets_correct_content_type_header() {
        global $header_calls;
        $this->assertEmpty($header_calls);
        ob_start();
        send_xml('iusdfasdf', true, '200');
        ob_end_clean();
        $this->assertEquals(count($header_calls), 1);
        $this->assertEquals($header_calls[0], 'Content-Type: text/xml');
    }

    function test_defines_unplug_response_sent() {
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        ob_start();
        send_xml('iusdfasdf', true, '200');
        ob_end_clean();
        $this->assertTrue(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertTrue(UNPLUG_RESPONSE_SENT);
    }

    function test_echoes_body_text() {
        ob_start();
        send_xml('iusdfasdf', true, '200');
        $output = ob_get_clean();
        $this->assertEquals($output, 'iusdfasdf');
    }
}
