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

// request is just a "data class"
final class RequestTest extends TestCase {

    public function testKeepsItsArguments() {
        $path = [1];
        $params = [2];
        $query = [3];
        $request = new unplug\Request($path, $params, $query);
        $this->assertSame($request->path, $path);
        $this->assertSame($request->params, $params);
        $this->assertSame($request->query, $query);
    }
}
