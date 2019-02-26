<?php

namespace Em4nl\Unplug;


interface Response {
    function send();
}


abstract class ContentResponse implements Response {

    function __construct($body, $is_cacheable, $status) {
        if (!is_string($body) && !is_array($body)) {
            throw new \Exception('Response $body must be a string or an array');
        }
        if (!is_bool($is_cacheable)) {
            throw new \Exception('Response $is_cacheable must be a boolean');
        }
        if (!is_string($status)) {
            throw new \Exception('Response $status must be a string');
        }

        $this->body = $body;
        $this->status = $status;

        $this->send();

        if (!defined('UNPLUG_DO_CACHE')) {
            define('UNPLUG_DO_CACHE', $is_cacheable);
        }
    }
}


class TextResponse extends ContentResponse {

    function send() {
        status_header($this->status);
        header('Content-Type: text/plain');
        echo $this->body;
    }
}


class HTMLResponse extends ContentResponse {

    function send() {
        status_header($this->status);
        header('Content-Type: text/html');
        echo $this->body;
    }
}


class JSONResponse extends ContentResponse {

    function send() {
        status_header($this->status);
        header('Content-Type: application/json');
        wp_send_json($this->body);
    }
}


// TODO we need a way to trigger this, manually returning new
// unplug\XMLResponses is not very convenient. HTML and JSON are
// distinguished by the type of the data given. can we detect
// if a string is HTML or XML somehow?!
class XMLResponse extends ContentResponse {

    function send() {
        status_header($this->status);
        header('Content-Type: text/xml');
        echo $this->body;
    }
}


function make_content_response($response, $is_cacheable=true, $found=true) {

    if (!is_bool($found)) {
        throw new \Exception('$found must be boolean');
    }

    $status = $found ? '200' : '404';

    if ($response instanceof Response) {
        return $response;
    }
    if (is_string($response)) {
        return new HTMLResponse($response, $is_cacheable, $status);
    }
    if (is_array($response)) {
        return new JSONResponse($response, $is_cacheable, $status);
    }
    throw new \Exception('$response must be string, array or Response');
}


class RedirectResponse implements Response {

    function __construct($location, $is_permanent=true) {
        $this->location = self::normalise_location($location);

        if ($is_permanent) {
            $this->status = '301';
        } else {
            $this->status = '302';
        }

        $this->send();

        if (!defined('UNPLUG_DO_CACHE')) {
            define('UNPLUG_DO_CACHE', FALSE);
        }
    }

    static function normalise_location($location) {
        if ($location[0] !== '/') {
            $location = '/' . $location;
        }
        if ($location[strlen($location) - 1] !== '/') {
            $location .= '/';
        }
        return get_site_url() . $location;
    }

    function send() {
        wp_redirect($this->location, $this->status);
    }
}


/**
 * Convenience functions for use in routes
 */

function ok($response='', $is_cacheable=true) {
    return make_content_response($response, $is_cacheable);
}

function not_found($response='', $is_cacheable=false) {
    return make_content_response($response, $is_cacheable, false);
}

function moved_permanently($location='/', $is_cacheable=true) {
    return new RedirectResponse($location);
}

function found($location='/', $is_cacheable=true) {
    return new RedirectResponse($location, false);
}
