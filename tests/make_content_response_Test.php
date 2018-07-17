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

final class make_content_response_Test extends TestCase {

    public function testStringReturnsHTMLResponse() {
        $res = unplug\make_content_response('');
        $this->assertInstanceOf(unplug\HTMLResponse::class, $res);
    }

    public function testArrayReturnsJSONResponse() {
        $res = unplug\make_content_response([]);
        $this->assertInstanceOf(unplug\JSONResponse::class, $res);
    }

    public function testReturnsExistingResponseUntouched() {
        // html
        $res = new unplug\HTMLResponse('', true, '200');
        $res2 = unplug\make_content_response($res);
        $this->assertSame($res, $res2);

        // json
        $res = new unplug\JSONResponse([], false, '201');
        $res2 = unplug\make_content_response($res);
        $this->assertSame($res, $res2);

        // xml
        $res = new unplug\XMLResponse('', true, '401');
        $res2 = unplug\make_content_response($res);
        $this->assertSame($res, $res2);
    }

    public function testCreateHTMLResponseWithDefaults() {
        // 1 arg
        $res = unplug\make_content_response('');
        $this->assertSame($res->get_body(), '');
        $this->assertSame($res->is_cacheable(), true);
        $this->assertSame($res->get_status(), '200');

        // 2 args
        $res = unplug\make_content_response('<test></test>', false);
        $this->assertSame($res->get_body(), '<test></test>');
        $this->assertSame($res->is_cacheable(), false);
        $this->assertSame($res->get_status(), '200');

        // 3 args
        $res = unplug\make_content_response('1000', true, false);
        $this->assertSame($res->get_body(), '1000');
        $this->assertSame($res->is_cacheable(), true);
        $this->assertSame($res->get_status(), '404');
    }

    public function testCreateJSONResponseWithDefaults() {
        // 1 arg
        $res = unplug\make_content_response([]);
        $this->assertSame($res->get_body(), '[]');
        $this->assertSame($res->is_cacheable(), true);
        $this->assertSame($res->get_status(), '200');

        // 2 args
        $res = unplug\make_content_response(['test' => 'test'], true);
        $this->assertSame($res->get_body(), '{"test":"test"}');
        $this->assertSame($res->is_cacheable(), true);
        $this->assertSame($res->get_status(), '200');

        // 3 args
        $res = unplug\make_content_response(['test', 'test'], false, true);
        $this->assertSame($res->get_body(), '["test","test"]');
        $this->assertSame($res->is_cacheable(), false);
        $this->assertSame($res->get_status(), '200');
    }

    public function testThrowsWhenResponseNull() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            '$response must be string, array or Response'
        );
        unplug\make_content_response(null);
    }

    public function testThrowsWhenResponseNumber0() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            '$response must be string, array or Response'
        );
        unplug\make_content_response(0);
    }

    public function testThrowsWhenResponseNumber1() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            '$response must be string, array or Response'
        );
        unplug\make_content_response(1);
    }

    public function testThrowsWhenResponseNumber2() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            '$response must be string, array or Response'
        );
        unplug\make_content_response(45.8);
    }

    public function testThrowsWhenCacheableNotBool0() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Response _is_cacheable must be a boolean'
        );
        unplug\make_content_response('', null);
    }

    public function testThrowsWhenCacheableNotBool1() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Response _is_cacheable must be a boolean'
        );
        unplug\make_content_response('', 7);
    }

    public function testThrowsWhenCacheableNotBool2() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Response _is_cacheable must be a boolean'
        );
        unplug\make_content_response('', 'true');
    }

    public function testThrowsWhenCacheableNotBool3() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(
            'Response _is_cacheable must be a boolean'
        );
        unplug\make_content_response('', ['test']);
    }

    public function testThrowsWhenFoundNotBool0() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('$found must be boolean');
        unplug\make_content_response('', true, null);
    }

    public function testThrowsWhenFoundNotBool1() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('$found must be boolean');
        unplug\make_content_response('', true, 78);
    }

    public function testThrowsWhenFoundNotBool2() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('$found must be boolean');
        unplug\make_content_response('', true, 'false');
    }

    public function testThrowsWhenFoundNotBool3() {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('$found must be boolean');
        unplug\make_content_response('', true, new stdClass());
    }
}
