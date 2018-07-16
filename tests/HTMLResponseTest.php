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

// mock the status_header function
$status_header_calls = [];
function status_header(string $status) {
    global $status_header_calls;
    $status_header_calls[] = $status;
}

// request is just a "data class"
final class HTMLResponseTest extends TestCase {

    public function testImplementsAllRequiredMethods() {
        global $status_header_calls;

        $response = new unplug\HTMLResponse('body payload', false, '404');

        // ResponseMethods
        $this->assertFalse($response->is_cacheable());
        $this->assertSame($response->get_status(), '404');
        $this->assertSame(sizeof($status_header_calls), 0);
        ob_start();
        $response->send();
        $result = ob_get_clean();
        $this->assertSame($result, 'body payload');
        $this->assertSame(sizeof($status_header_calls), 1);
        $this->assertSame($status_header_calls[0], '404');

        // ContentResponseMethods
        $this->assertSame($response->get_extension(), 'html');
        $this->assertSame($response->get_body(), 'body payload');
    }

    public function testRequireStringBody() {

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Response body must be a string');
        new unplug\HTMLResponse(null, true, '200');
    }

    public function testRequireCacheableBoolean() {

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Response _is_cacheable must be a boolean');
        new unplug\HTMLResponse('', null, '200');
    }

    public function testRequireStatusString() {

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Response status must be a string');
        new unplug\HTMLResponse('', true, null);
    }
}
