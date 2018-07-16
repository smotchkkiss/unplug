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

final class JSONResponseTest extends TestCase {

    public function testImplementsAllRequiredMethods() {

        // mock the status_header and wp_send_json functions
        $status_header_calls = [];
        $wp_send_json_calls = [];
        $global_functions = new unplug\GlobalFunctions([
            'status_header' => function($status) use (&$status_header_calls) {
                $status_header_calls[] = $status;
            },
            'wp_send_json' => function(array $data) use (&$wp_send_json_calls) {
                $wp_send_json_calls[] = json_encode($data);
            },
        ]);

        $response = new unplug\JSONResponse(['body' => 'payload'], true, '403');

        // ResponseMethods
        $this->assertTrue($response->is_cacheable());
        $this->assertSame($response->get_status(), '403');
        $this->assertSame(sizeof($status_header_calls), 0);
        $this->assertSame(sizeof($wp_send_json_calls), 0);
        ob_start();
        $response->send($global_functions);
        $result = ob_get_clean();
        $this->assertSame(
            $wp_send_json_calls[0],
            json_encode(['body' => 'payload'])
        );
        $this->assertSame(sizeof($status_header_calls), 1);
        $this->assertSame($status_header_calls[0], '403');

        // ContentResponseMethods
        $this->assertSame($response->get_extension(), 'json');
        $this->assertSame($response->get_body(), '{"body":"payload"}');
    }

    public function testRequireArrayBody() {

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Response body must be a string or an array'
        );
        new unplug\JSONResponse(null, true, '200');
    }

    public function testRequireCacheableBoolean() {

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Response _is_cacheable must be a boolean');
        new unplug\JSONResponse('', null, '200');
    }

    public function testRequireStatusString() {

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Response status must be a string');
        new unplug\JSONResponse('', true, null);
    }
}
