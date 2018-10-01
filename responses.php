<?php

namespace unplug;


interface ResponseMethods {

    public function is_cacheable();
    public function get_status();
    public function send();
}


interface ContentResponseMethods {

    public function get_extension();
    public function get_body();
}


interface RedirectResponseMethods {

    public function get_location();
}


abstract class Response implements ResponseMethods {

    protected $status;

    public function get_status() {

        return $this->status;
    }
}


abstract class ContentResponse extends Response implements ContentResponseMethods {

    protected $body;

    public function __construct($body, $_is_cacheable, $status) {

        if (!is_string($body) && !is_array($body)) {
            throw new \Exception('Response body must be a string or an array');
        }
        if (!is_bool($_is_cacheable)) {
            throw new \Exception('Response _is_cacheable must be a boolean');
        }
        if (!is_string($status)) {
            throw new \Exception('Response status must be a string');
        }

        $this->body = $body;
        $this->_is_cacheable = $_is_cacheable;
        $this->status = $status;
    }

    public function is_cacheable() {

        return $this->_is_cacheable;
    }
}


class HTMLResponse extends ContentResponse {

    public function get_extension() {

        return 'html';
    }

    public function get_body() {

        return $this->body;
    }

    public function send() {

        status_header($this->status);
        echo $this->body;
    }
}


class JSONResponse extends ContentResponse {

    public function get_extension() {

        return 'json';
    }

    public function get_body() {

        return json_encode($this->body);
    }

    public function send() {

        status_header($this->status);
        wp_send_json($this->body);
    }
}


// TODO we need a way to trigger this, manually returning new
// unplug\XMLResponses is not very convenient. HTML and JSON are
// distinguished by the type of the data given. can we detect
// if a string is HTML or XML somehow?!
class XMLResponse extends ContentResponse {

    public function get_extension() {

        return 'xml';
    }

    public function get_body() {

        return $this->body;
    }

    public function send() {

        status_header($this->status);
        header('Content-Type: text/xml');
        echo $this->body;
    }
}


function make_content_response($response, $is_cacheable=true, $found=true) {

    if (!is_bool($found)) {
        throw new \Exception('$found must be boolean');
    }

    if ($found) {
        $status = '200';
    } else {
        $status = '404';
    }

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


class RedirectResponse extends Response implements RedirectResponseMethods {

    protected $location;
    protected $status;

    public function __construct($location, $is_permanent=true) {

        $this->location = self::normalise_location($location);

        if ($is_permanent) {
            $this->status = '301';
        } else {
            $this->status = '302';
        }
    }

    protected static function normalise_location($location) {

        if ($location[0] !== '/') {
            $location = '/' . $location;
        }
        if ($location[strlen($location) - 1] !== '/') {
            $location .= '/';
        }
        return get_site_url() . $location;
    }

    public function is_cacheable() {

        return true;
    }

    public function get_location() {

        return $this->location;
    }

    public function send() {

        wp_redirect($this->get_location(), $this->get_status());
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
