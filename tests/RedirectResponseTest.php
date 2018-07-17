<?php

// unplug is meant to be used inside a WordPress theme, therefore
// it expects ABSPATH to be defined (and set to the WordPress
// base directory). We're setting it to a 'mock' folder inside the
// tests dir here, where we keep mock versions of the WordPress
// files unplug tries wants include.
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/mock/');
}

include_once(dirname(__DIR__) . '/unplug.php');

use PHPUnit\Framework\TestCase;

final class RedirectResponseTest extends TestCase {

    public function testLocationIsFullyQualifiedURL() {
        $get_site_url_calls = 0;
        $functions = new unplug\GlobalFunctions([
            'get_site_url' => function() use(&$get_site_url_calls) {
                $get_site_url_calls++;
                return 'https://example.com';
            },
        ]);
        $this->assertSame($get_site_url_calls, 0);
        $res = new unplug\RedirectResponse('/', true, $functions);
        $this->assertSame($get_site_url_calls, 1);
        $this->assertSame($res->get_location(), 'https://example.com/');
        $this->assertSame($get_site_url_calls, 1);
        $res = new unplug\RedirectResponse('/test', true, $functions);
        $this->assertSame($get_site_url_calls, 2);
        $this->assertSame($res->get_location(), 'https://example.com/test/');
        $this->assertSame($get_site_url_calls, 2);
        $res = new unplug\RedirectResponse('/wurm/senf', true, $functions);
        $this->assertSame($get_site_url_calls, 3);
        $this->assertSame(
            $res->get_location(),
            'https://example.com/wurm/senf/'
        );
        $this->assertSame($get_site_url_calls, 3);
    }

    public function testIsAlwaysCacheable() {
        $functions = new unplug\GlobalFunctions([
            'get_site_url' => function() {
                return '';
            },
        ]);
        $res = new unplug\RedirectResponse('/', true, $functions);
        $this->assertSame($res->is_cacheable(), true);
        $res = new unplug\RedirectResponse('/', false, $functions);
        $this->assertSame($res->is_cacheable(), true);
    }

    public function testRedirectResponseMethods() {
        $functions = new unplug\GlobalFunctions([
            'get_site_url' => function() {
                return '';
            },
        ]);
        $res = new unplug\RedirectResponse('/', true, $functions);
        $this->assertSame($res->is_cacheable(), true);
        $this->assertSame($res->get_location(), '/');
    }

    public function testSendMethodAndStatus() {
        $wp_redirect_calls = [];
        $functions = new unplug\GlobalFunctions([
            'get_site_url' => function() {
                return '';
            },
            'wp_redirect' => function($location, $status)
                use(&$wp_redirect_calls) {
                    $wp_redirect_calls[] = [
                        'location' => $location,
                        'status' => $status,
                    ];
            },
        ]);
        $res = new unplug\RedirectResponse('/', true, $functions);
        $this->assertSame(sizeof($wp_redirect_calls), 0);
        $res->send($functions);
        $this->assertSame(sizeof($wp_redirect_calls), 1);
        $this->assertSame($wp_redirect_calls[0]['location'], '/');
        $this->assertSame($wp_redirect_calls[0]['status'], '301');
        $res = new unplug\RedirectResponse('/test', false, $functions);
        $this->assertSame(sizeof($wp_redirect_calls), 1);
        $res->send($functions);
        $this->assertSame(sizeof($wp_redirect_calls), 2);
        $this->assertSame($wp_redirect_calls[1]['location'], '/test/');
        $this->assertSame($wp_redirect_calls[1]['status'], '302');
    }
}
