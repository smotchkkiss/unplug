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

// mock status_header and header functions
if (!function_exists('unplug\status_header')) {
    $status_header_calls = [];
    function status_header($status) {
        global $status_header_calls;
        $status_header_calls[] = $status;
    }
}
if (!function_exists('unplug\header')) {
    $header_calls = [];
    function header($header) {
        global $header_calls;
        $header_calls[] = $header;
    }
}

include_once(dirname(__DIR__) . '/unplug.php');

use PHPUnit\Framework\TestCase;

final class XMLResponseTest extends TestCase {

    public function testImplementsAllRequiredMethods() {
        global $status_header_calls;
        global $header_calls;
        $status_header_calls = [];
        $header_calls = [];

        $response = new XMLResponse('body payload', true, '201');

        // ResponseMethods
        $this->assertTrue($response->is_cacheable());
        $this->assertSame($response->get_status(), '201');
        $this->assertSame(sizeof($status_header_calls), 0);
        $this->assertSame(sizeof($header_calls), 0);
        ob_start();
        $response->send();
        $result = ob_get_clean();
        $this->assertSame($result, 'body payload');
        $this->assertSame(sizeof($status_header_calls), 1);
        $this->assertSame($status_header_calls[0], '201');
        $this->assertSame(sizeof($header_calls), 1);
        $this->assertSame($header_calls[0], 'Content-Type: text/xml');

        // ContentResponseMethods
        $this->assertSame($response->get_extension(), 'xml');
        $this->assertSame($response->get_body(), 'body payload');
    }

    public function testRequireStringBody() {

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Response body must be a string or an array'
        );
        new XMLResponse(null, true, '200');
    }

    public function testRequireCacheableBoolean() {

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Response _is_cacheable must be a boolean');
        new XMLResponse('', null, '200');
    }

    public function testRequireStatusString() {

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Response status must be a string');
        new XMLResponse('', true, null);
    }
}
