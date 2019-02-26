<?php

namespace Em4nl\Unplug;

include_once(dirname(__DIR__) . '/responses.php');

use PHPUnit\Framework\TestCase;

if (!function_exists('Em4nl\Unplug\get_site_url')) {
    $get_site_url_calls = 0;
    function get_site_url() {
        global $get_site_url_calls;
        $get_site_url_calls++;
        return 'https://example.com';
    }
}

if (!function_exists('Em4nl\Unplug\wp_redirect')) {
    $wp_redirect_calls = [];
    function wp_redirect(string $location, string $status) {
        global $wp_redirect_calls;
        $wp_redirect_calls[] = [
            'location' => $location,
            'status' => $status,
        ];
    }
}


class found_Test extends TestCase {

    protected $preserveGlobalState = FALSE;
    protected $runTestInSeparateProcess = TRUE;

    function test_defines_do_cache() {
        $this->assertFalse(defined('UNPLUG_DO_CACHE'));
        found();
        $this->assertTrue(defined('UNPLUG_DO_CACHE'));
    }

    function test_sets_do_cache_false() {
        found();
        $this->assertFalse(UNPLUG_DO_CACHE);
    }

    function test_defines_response_sent() {
        $this->assertFalse(defined('UNPLUG_RESPONSE_SENT'));
        found();
        $this->assertTrue(defined('UNPLUG_RESPONSE_SENT'));
    }

    function test_sets_response_sent_true() {
        found();
        $this->assertTrue(UNPLUG_RESPONSE_SENT);
    }

    function test_calls_wp_redirect_once() {
        global $wp_redirect_calls;
        $this->assertEmpty($wp_redirect_calls);
        found();
        $this->assertEquals(count($wp_redirect_calls), 1);
    }

    function test_puts_together_the_correct_url_1() {
        global $wp_redirect_calls;
        found();
        $this->assertEquals(
            array_pop($wp_redirect_calls)['location'],
            'https://example.com/'
        );
    }

    function test_puts_together_the_correct_url_1_1() {
        global $wp_redirect_calls;
        found('/');
        $this->assertEquals(
            array_pop($wp_redirect_calls)['location'],
            'https://example.com/'
        );
    }

    function test_puts_together_the_correct_url_2() {
        global $wp_redirect_calls;
        found('/wurm');
        $this->assertEquals(
            array_pop($wp_redirect_calls)['location'],
            'https://example.com/wurm/'
        );
    }

    function test_puts_together_the_correct_url_3() {
        global $wp_redirect_calls;
        found('/sein/129');
        $this->assertEquals(
            array_pop($wp_redirect_calls)['location'],
            'https://example.com/sein/129/'
        );
    }

    function test_puts_together_the_correct_url_4() {
        global $wp_redirect_calls;
        found('/wurm/sein/0000');
        $this->assertEquals(
            array_pop($wp_redirect_calls)['location'],
            'https://example.com/wurm/sein/0000/'
        );
    }

    function test_emits__the_correct_status_1() {
        global $wp_redirect_calls;
        found();
        $this->assertEquals(array_pop($wp_redirect_calls)['status'], '302');
    }

    /**
     * @expectedException Exception
     */
    function test_throws_when_called_with_wrong_type_1() {
        found(null);
    }

    /**
     * @expectedException Exception
     */
    function test_throws_when_called_with_wrong_type_2() {
        found(0.000007);
    }

    /**
     * @expectedException Exception
     */
    function test_throws_when_called_with_wrong_type_3() {
        found(array(0, 0, 0, 0,));
    }

    /**
     * @expectedException Exception
     */
    function test_throws_when_called_with_wrong_type_4() {
        found(new \StdClass());
    }

    /**
     * @expectedException Exception
     */
    function test_throws_when_called_with_wrong_type_5() {
        found(false);
    }
}
