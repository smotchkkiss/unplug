<?php

// necessary for mocking
namespace unplug;

// unplug is meant to be used inside a WordPress theme, therefore
// it expects ABSPATH to be defined (and set to the WordPress
// base directory). We're setting it to a 'mock' folder inside the
// tests dir here, where we keep mock versions of the WordPress
// files unplug tries wants include.
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/mock/');
}

// mock get_site_url and wp_redirect functions
if (!function_exists('unplug\get_site_url')) {
    $get_site_url_calls = 0;
    function get_site_url() {
        global $get_site_url_calls;
        $get_site_url_calls++;
        return 'https://example.com';
    }
}
if (!function_exists('unplug\wp_redirect')) {
    $wp_redirect_calls = [];
    function wp_redirect($location, $status) {
        global $wp_redirect_calls;
        $wp_redirect_calls[] = [
            'location' => $location,
            'status' => $status,
        ];
    }
}

include_once(dirname(__DIR__) . '/unplug.php');

use PHPUnit\Framework\TestCase;

final class RedirectResponseTest extends TestCase {

    public function testLocationIsFullyQualifiedURL() {
        global $get_site_url_calls;
        $get_site_url_calls = 0;

        $this->assertSame($get_site_url_calls, 0);
        $res = new RedirectResponse('/');
        $this->assertSame($get_site_url_calls, 1);
        $this->assertSame($res->get_location(), 'https://example.com/');
        $this->assertSame($get_site_url_calls, 1);
        $res = new RedirectResponse('/test');
        $this->assertSame($get_site_url_calls, 2);
        $this->assertSame($res->get_location(), 'https://example.com/test/');
        $this->assertSame($get_site_url_calls, 2);
        $res = new RedirectResponse('/wurm/senf');
        $this->assertSame($get_site_url_calls, 3);
        $this->assertSame(
            $res->get_location(),
            'https://example.com/wurm/senf/'
        );
        $this->assertSame($get_site_url_calls, 3);
    }

    public function testIsAlwaysCacheable() {
        $res = new RedirectResponse('/', true);
        $this->assertSame($res->is_cacheable(), true);
        $res = new RedirectResponse('/', false);
        $this->assertSame($res->is_cacheable(), true);
    }

    public function testRedirectResponseMethods() {
        $res = new RedirectResponse('/');
        $this->assertSame($res->is_cacheable(), true);
        $this->assertSame($res->get_location(), 'https://example.com/');
    }

    public function testSendMethodAndStatus() {
        global $wp_redirect_calls;
        $wp_redirect_calls = [];

        $res = new RedirectResponse('/', true);
        $this->assertSame(sizeof($wp_redirect_calls), 0);
        $res->send();
        $this->assertSame(sizeof($wp_redirect_calls), 1);
        $this->assertSame(
            $wp_redirect_calls[0]['location'],
            'https://example.com/'
        );
        $this->assertSame($wp_redirect_calls[0]['status'], '301');

        $res = new RedirectResponse('/test', false);
        $this->assertSame(sizeof($wp_redirect_calls), 1);
        $res->send();
        $this->assertSame(sizeof($wp_redirect_calls), 2);
        $this->assertSame(
            $wp_redirect_calls[1]['location'],
            'https://example.com/test/'
        );
        $this->assertSame($wp_redirect_calls[1]['status'], '302');
    }
}
