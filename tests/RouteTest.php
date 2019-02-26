<?php

// unplug is meant to be used inside a WordPress theme, therefore
// it expects ABSPATH to be defined (and set to the WordPress
// base directory). We're setting it to a 'mock' folder inside the
// tests dir here, where we keep mock versions of the WordPress
// files unplug tries to include.
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/mock/');
}

include_once(dirname(__DIR__) . '/unplug.php');

use PHPUnit\Framework\TestCase;

final class RouteTest extends TestCase {

    public function testCanBeInstantiatedWithDefaultConstuctor() {
        $route = new unplug\Route([], function() {});
        $this->assertInstanceOf(unplug\Route::class, $route);
    }

    public function testKeepsPathAndCallback() {
        $path = [];
        $callback = function() {};
        $route = new unplug\Route($path, $callback);
        $this->assertSame($route->path, $path);
        $this->assertSame($route->callback, $callback);
    }

    public function testCallbackIsCallableDirectly() {
        $route = new unplug\Route([], function() {});
        $route->callback();
        // dummy assertion, this test actually tests that the call
        // in the above line does not throw.
        $this->assertTrue(true);
    }
}
