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


class send_content_response_Test extends TestCase {

    protected $preserveGlobalState = FALSE;
    protected $runTestInSeparateProcess = TRUE;

    function test_sends_html_response_with_string_single_arg() {
        global $status_header_calls;
        global $header_calls;
        global $wp_send_json_calls;
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        ob_start();
        send_content_response('WuRM');
        $output = ob_get_clean();
        $this->assertEquals(count($status_header_calls), 1);
        $this->assertEquals(count($header_calls), 1);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertEquals($status_header_calls[0], '200');
        $this->assertEquals($header_calls[0], 'Content-Type: text/html');
        $this->assertTrue(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertTrue(UNPLUG_RESPONSE_SENT);
        $this->assertTrue(UNPLUG_DO_CACHE);
        $this->assertEquals($output, 'WuRM');
    }

    function test_sends_html_response_with_string_single_arg_2() {
        global $status_header_calls;
        global $header_calls;
        global $wp_send_json_calls;
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        ob_start();
        send_content_response('');
        $output = ob_get_clean();
        $this->assertEquals(count($status_header_calls), 1);
        $this->assertEquals(count($header_calls), 1);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertEquals($status_header_calls[0], '200');
        $this->assertEquals($header_calls[0], 'Content-Type: text/html');
        $this->assertTrue(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertTrue(UNPLUG_RESPONSE_SENT);
        $this->assertTrue(UNPLUG_DO_CACHE);
        $this->assertEquals($output, '');
    }

    function test_sends_json_response_with_array_single_arg() {
        global $status_header_calls;
        global $header_calls;
        global $wp_send_json_calls;
        $data = array('test1' => false, 'test2' => ['w' => 908]);
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        send_content_response($data);
        $this->assertEquals(count($status_header_calls), 1);
        $this->assertEquals(count($header_calls), 1);
        $this->assertEquals(count($wp_send_json_calls), 1);
        $this->assertEquals($status_header_calls[0], '200');
        $this->assertEquals($header_calls[0], 'Content-Type: application/json');
        $this->assertEquals($wp_send_json_calls[0], $data);
        $this->assertTrue(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertTrue(UNPLUG_RESPONSE_SENT);
        $this->assertTrue(UNPLUG_DO_CACHE);
    }

    function test_sends_json_response_with_array_single_arg_2() {
        global $status_header_calls;
        global $header_calls;
        global $wp_send_json_calls;
        $data = array();
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        send_content_response($data);
        $this->assertEquals(count($status_header_calls), 1);
        $this->assertEquals(count($header_calls), 1);
        $this->assertEquals(count($wp_send_json_calls), 1);
        $this->assertEquals($status_header_calls[0], '200');
        $this->assertEquals($header_calls[0], 'Content-Type: application/json');
        $this->assertEquals($wp_send_json_calls[0], $data);
        $this->assertTrue(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertTrue(UNPLUG_RESPONSE_SENT);
        $this->assertTrue(UNPLUG_DO_CACHE);
    }

    /**
     * @expectedException Exception
     */
    function test_throws_with_single_arg_wrong_type_1() {
        global $status_header_calls;
        global $header_calls;
        global $wp_send_json_calls;
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        send_content_response(null);
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
    }

    /**
     * @expectedException Exception
     */
    function test_throws_with_single_arg_wrong_type_2() {
        global $status_header_calls;
        global $header_calls;
        global $wp_send_json_calls;
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        send_content_response(true);
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
    }

    /**
     * @expectedException Exception
     */
    function test_throws_with_single_arg_wrong_type_3() {
        global $status_header_calls;
        global $header_calls;
        global $wp_send_json_calls;
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        send_content_response(-89.3);
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
    }

    /**
     * @expectedException Exception
     */
    function test_throws_with_single_arg_wrong_type_4() {
        global $status_header_calls;
        global $header_calls;
        global $wp_send_json_calls;
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        send_content_response(new \StdClass());
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
    }

    function test_sends_html_response_with_two_args_1() {
        global $status_header_calls;
        global $header_calls;
        global $wp_send_json_calls;
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        ob_start();
        send_content_response('WuRM', true);
        $output = ob_get_clean();
        $this->assertEquals(count($status_header_calls), 1);
        $this->assertEquals(count($header_calls), 1);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertEquals($status_header_calls[0], '200');
        $this->assertEquals($header_calls[0], 'Content-Type: text/html');
        $this->assertTrue(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertTrue(UNPLUG_RESPONSE_SENT);
        $this->assertTrue(UNPLUG_DO_CACHE);
        $this->assertEquals($output, 'WuRM');
    }

    function test_sends_html_response_with_two_args_2() {
        global $status_header_calls;
        global $header_calls;
        global $wp_send_json_calls;
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        ob_start();
        send_content_response('', true);
        $output = ob_get_clean();
        $this->assertEquals(count($status_header_calls), 1);
        $this->assertEquals(count($header_calls), 1);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertEquals($status_header_calls[0], '200');
        $this->assertEquals($header_calls[0], 'Content-Type: text/html');
        $this->assertTrue(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertTrue(UNPLUG_RESPONSE_SENT);
        $this->assertTrue(UNPLUG_DO_CACHE);
        $this->assertEquals($output, '');
    }

    function test_passes_cacheable_arg_correctly_1() {
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        ob_start();
        send_content_response('ouwdawdfp@@#-0230-', false);
        ob_end_clean();
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertFalse(UNPLUG_DO_CACHE);
    }

    function test_passes_cacheable_arg_correctly_2() {
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        send_content_response(['wurm' => 'habicht'], false);
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertFalse(UNPLUG_DO_CACHE);
    }

    function test_sends_json_response_with_two_args_1() {
        global $status_header_calls;
        global $header_calls;
        global $wp_send_json_calls;
        $data = array();
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        send_content_response($data, true);
        $this->assertEquals(count($status_header_calls), 1);
        $this->assertEquals(count($header_calls), 1);
        $this->assertEquals(count($wp_send_json_calls), 1);
        $this->assertEquals($status_header_calls[0], '200');
        $this->assertEquals($header_calls[0], 'Content-Type: application/json');
        $this->assertEquals($wp_send_json_calls[0], $data);
        $this->assertTrue(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertTrue(UNPLUG_RESPONSE_SENT);
        $this->assertTrue(UNPLUG_DO_CACHE);
    }

    function test_sends_json_response_with_two_args_2() {
        global $status_header_calls;
        global $header_calls;
        global $wp_send_json_calls;
        $data = ['notos' => 9090, 'j' => new \StdClass()];
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        send_content_response($data, false);
        $this->assertEquals(count($status_header_calls), 1);
        $this->assertEquals(count($header_calls), 1);
        $this->assertEquals(count($wp_send_json_calls), 1);
        $this->assertEquals($status_header_calls[0], '200');
        $this->assertEquals($header_calls[0], 'Content-Type: application/json');
        $this->assertEquals($wp_send_json_calls[0], $data);
        $this->assertTrue(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertTrue(UNPLUG_RESPONSE_SENT);
        $this->assertFalse(UNPLUG_DO_CACHE);
    }

    function test_sends_html_response_with_three_args_1() {
        global $status_header_calls;
        global $header_calls;
        global $wp_send_json_calls;
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        ob_start();
        send_content_response('WuRM', true, true);
        $output = ob_get_clean();
        $this->assertEquals(count($status_header_calls), 1);
        $this->assertEquals(count($header_calls), 1);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertEquals($status_header_calls[0], '200');
        $this->assertEquals($header_calls[0], 'Content-Type: text/html');
        $this->assertTrue(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertTrue(UNPLUG_RESPONSE_SENT);
        $this->assertTrue(UNPLUG_DO_CACHE);
        $this->assertEquals($output, 'WuRM');
    }

    function test_sends_html_response_with_three_args_2() {
        global $status_header_calls;
        global $header_calls;
        global $wp_send_json_calls;
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        ob_start();
        send_content_response('', false, true);
        $output = ob_get_clean();
        $this->assertEquals(count($status_header_calls), 1);
        $this->assertEquals(count($header_calls), 1);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertEquals($status_header_calls[0], '200');
        $this->assertEquals($header_calls[0], 'Content-Type: text/html');
        $this->assertTrue(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertTrue(UNPLUG_RESPONSE_SENT);
        $this->assertFalse(UNPLUG_DO_CACHE);
        $this->assertEquals($output, '');
    }

    function test_sends_html_response_with_three_args_3() {
        global $status_header_calls;
        global $header_calls;
        global $wp_send_json_calls;
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        ob_start();
        send_content_response('WuRM', true, false);
        $output = ob_get_clean();
        $this->assertEquals(count($status_header_calls), 1);
        $this->assertEquals(count($header_calls), 1);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertEquals($status_header_calls[0], '404');
        $this->assertEquals($header_calls[0], 'Content-Type: text/html');
        $this->assertTrue(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertTrue(UNPLUG_RESPONSE_SENT);
        $this->assertTrue(UNPLUG_DO_CACHE);
        $this->assertEquals($output, 'WuRM');
    }

    function test_sends_json_response_with_three_args_1() {
        global $status_header_calls;
        global $header_calls;
        global $wp_send_json_calls;
        $data = array('hy', 37, 600, true, true, NULL);
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        send_content_response($data, true, true);
        $this->assertEquals(count($status_header_calls), 1);
        $this->assertEquals(count($header_calls), 1);
        $this->assertEquals(count($wp_send_json_calls), 1);
        $this->assertEquals($status_header_calls[0], '200');
        $this->assertEquals($header_calls[0], 'Content-Type: application/json');
        $this->assertEquals($wp_send_json_calls[0], $data);
        $this->assertTrue(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertTrue(UNPLUG_RESPONSE_SENT);
        $this->assertTrue(UNPLUG_DO_CACHE);
    }

    function test_sends_json_response_with_three_args_2() {
        global $status_header_calls;
        global $header_calls;
        global $wp_send_json_calls;
        $o = new \StdClass();
        $data = [new \StdClass(), new \StdClass(), $o, $o, $o];
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        send_content_response($data, false, true);
        $this->assertEquals(count($status_header_calls), 1);
        $this->assertEquals(count($header_calls), 1);
        $this->assertEquals(count($wp_send_json_calls), 1);
        $this->assertEquals($status_header_calls[0], '200');
        $this->assertEquals($header_calls[0], 'Content-Type: application/json');
        $this->assertEquals($wp_send_json_calls[0], $data);
        $this->assertTrue(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertTrue(UNPLUG_RESPONSE_SENT);
        $this->assertFalse(UNPLUG_DO_CACHE);
    }

    function test_sends_json_response_with_three_args_3() {
        global $status_header_calls;
        global $header_calls;
        global $wp_send_json_calls;
        $data = ['a' => ['b' => ['c' => ['d' => ['e' => []]]]]];
        $this->assertEmpty($status_header_calls);
        $this->assertEmpty($header_calls);
        $this->assertEmpty($wp_send_json_calls);
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        send_content_response($data, false, false);
        $this->assertEquals(count($status_header_calls), 1);
        $this->assertEquals(count($header_calls), 1);
        $this->assertEquals(count($wp_send_json_calls), 1);
        $this->assertEquals($status_header_calls[0], '404');
        $this->assertEquals($header_calls[0], 'Content-Type: application/json');
        $this->assertEquals($wp_send_json_calls[0], $data);
        $this->assertTrue(defined('UNPLUG_RESPONSE_SENT'));
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
        $this->assertTrue(UNPLUG_RESPONSE_SENT);
        $this->assertFalse(UNPLUG_DO_CACHE);
    }

    /**
     * @expectedException Exception
     */
    function test_throws_with_non_boolean_third_arg_1() {
        send_content_response('', true, 80129287123);
    }

    /**
     * @expectedException Exception
     */
    function test_throws_with_non_boolean_third_arg_2() {
        send_content_response([], true, NULL);
    }

    /**
     * @expectedException Exception
     */
    function test_throws_with_non_boolean_third_arg_3() {
        send_content_response('oiawd92eouh2e0wf', true, '');
    }

    /**
     * @expectedException Exception
     */
    function test_throws_with_non_boolean_third_arg_4() {
        send_content_response(['a' => 'a'], false, new \StdClass());
    }

    /**
     * @expectedException Exception
     */
    function test_throws_with_non_boolean_third_arg_5() {
        send_content_response('', true, array('uoiwue', 6, 6, NULL));
    }

    /**
     * @expectedException Exception
     */
    function test_throws_with_non_boolean_second_arg_1() {
        send_content_response('', 0);
    }

    /**
     * @expectedException Exception
     */
    function test_throws_with_non_boolean_second_arg_2() {
        send_content_response('', NULL, true);
    }

    /**
     * @expectedException Exception
     */
    function test_throws_with_non_boolean_second_arg_3() {
        send_content_response(array(), array(), false);
    }

    /**
     * @expectedException Exception
     */
    function test_throws_with_non_boolean_second_arg_4() {
        send_content_response('zaphod', 'BEEBLEBROX');
    }

    /**
     * @expectedException Exception
     */
    function test_throws_with_non_boolean_second_arg_5() {
        send_content_response([], new \StdClass(), true);
    }

    /**
     * @expectedException Exception
     */
    function test_throws_with_wrong_type_first_arg_1() {
        send_content_response(new \StdClass, false, true);
    }

    /**
     * @expectedException Exception
     */
    function test_throws_with_wrong_type_first_arg_2() {
        send_content_response(true, true, true);
    }

    /**
     * @expectedException Exception
     */
    function test_throws_with_wrong_type_first_arg_3() {
        send_content_response(null, true);
    }

    /**
     * @expectedException Exception
     */
    function test_throws_with_wrong_type_first_arg_4() {
        send_content_response(-10000000000000000000, false);
    }
}
