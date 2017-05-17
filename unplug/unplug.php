<?php
/*
Plugin Name: unplug
Description: Unplug WP's assumptive defaults
Version: 0.0.0
Author: Emanuel Tannert, Wolfgang Schöffel
Author URI: http://unfun.de
*/

namespace unplug;

class Route {

    public $path;
    public $callback;

    public function __construct (array $path, callable $callback) {
        $this->path = $path;
        $this->callback = $callback;
    }

    // make the callback fn directly callable
    public function __call ($method, $args) {
        if (is_callable(array($this, $method))) {
            return call_user_func_array($this->$method, $args);
        }
    }
}

class Request {

    public $params;
    public $query;

    public function __construct (array $params, array $query) {
        $this->params = $params;
        $this->query = $query;
    }
}

class Response {

    protected $status;
    protected $is_json;
    protected $body;

    public function status ($status = null) {

        if ($status === null) {
            if (isset($this->status)) {
                return $this->status;
            }
            return null;
        }

        if (is_int($status)) {
            return $this->status = $status;
        }

        throw new \Exception('Status must be an integer!');
    }

    public function body ($body = null) {

        if ($body === null) {
            if (isset($this->body)) {
                return $this->body;
            }
            return null;
        }

        if (is_string($body)) {
            $this->is_json = false;
            return $this->body = $body;
        }

        throw new \Exception('Response body must be a string!');
    }

    public function json ($json = null) {

        if ($json === null) {
            if ($this->is_json && isset($this->body)) {
                return $this->body;
            }
            return null;
        }

        if (is_array($json)) {
            $this->is_json = true;
            return $this->body = $json;
        }

        throw new \Exception('Response json must be an array!');
    }

    public function __construct ($body = '', $status = 200) {

        $this->status($status);

        if (is_string($body)) {
            $this->body($body);
        } else if (is_array($body)) {
            $this->json($body);
        } else {
            throw new \Exception('Response body must be given as string or array!');
        }
    }

    public function send () {

        if (headers_sent()) {
            throw new \Exception('Headers have been sent before Response#send()!');
        }

        status_header($this->status);

        if ($this->is_json) {
            wp_send_json($this->body);
        } else {
            echo $this->body;
        }
    }
}

/**
 * Gets the current url, but without protocol/host
 * By Giuseppe Mazzapica @gmazzap as published on
 * https://roots.io/routing-wp-requests/
 *
 * @returns String url
 */
function get_current_url () {

    $current_url = trim(esc_url_raw(add_query_arg([])), '/');
    $home_path = trim(parse_url(home_url(), PHP_URL_PATH), '/');
    if ($home_path && strpos($current_url, $home_path) === 0) {
        $current_url = trim(substr($current_url, strlen($home_path)), '/');
    }

    return $current_url;
}

class Router {

    /**
     * Splits a path into segments, omitting empty strings
     *
     * @param string $path
     *
     * @returns array
     */
    protected static function split_path ($path) {
        $path_segments = explode('/', $path);
        $no_empty_str = array_filter($path_segments, function ($s) {
            return $s; // empty string evaluates to false
        });
        // reset the array keys to 0..*
        return array_values($no_empty_str);
    }

    /**
     * Returns a very basic 404 message to the client
     *
     * This should be avoided. Make sure you supply
     * exhaustive routes.
     */
    protected static function last_error_callback () {
        return new Response('404 - Page not found', 404);
    }

    protected $path;
    protected $query;
    protected $method;
    protected $get_routes = [];
    protected $post_routes = [];

    /**
     * Checks wether the path matches a route specification
     *
     * @param array $routeSpec
     *
     * @returns mixed
     */
    protected function path_matches_route (array $routeSpec) {

        $params = [];
        $PSSize = sizeof($this->path);
        $RSSize = sizeof($routeSpec);

        // match the index route
        if ($PSSize === 0 and $RSSize === 0) {
            return $params;
        }

        // match the catchall route
        if ($RSSize > 0 and $routeSpec[0] === '*') {
            return $params;
        }

        // fail if different number of path segments
        if ($PSSize !== $RSSize) {
            return FALSE;
        }

        // compare allpath segments
        for ($i = 0; $i < $PSSize; $i++) {

            // variable segment matches everything
            if (substr($routeSpec[$i], 0, 1) === ':') {

                // remove colon from parameter name
                $param_name = trim($routeSpec[$i], ':');

                // add a named parameter value
                $params[$param_name] = $this->path[$i];

            // normal segments have to match exactly
            } else if ($this->path[$i] !== $routeSpec[$i]) {

                // if they don’t, it’s a complete mismatch
                return FALSE;
            }
        }

        return $params;
    }

    /**
     * Checks all routes for a match and executes callback
     */
    protected function execute_matching_route () {

        if ($this->method === 'post') {
            $routes = $this->post_routes;
        } else {
            $routes = $this->get_routes;
        }

        foreach ($routes as $route) {

            $params = self::path_matches_route($route->path);
            $is_match = is_array($params);

            if ($is_match) {
                return $route->callback(new Request($params, $this->query));
            }
        }

        // in case the supplied routes aren’t exhaustive,
        // and none matched, this is the last resort
        return self::last_error_callback();
    }

    public function __construct () {

        $current_url = get_current_url();
        $url_parts = explode('?', $current_url, 2);

        $url_path = self::split_path($url_parts[0]);

        $url_vars = [];
        if (isset($url_parts[1])) {
            parse_str($url_parts[1], $url_vars);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $requestMethod = 'post';
        } else {
            $requestMethod = 'get';
        }

        $this->path = $url_path;
        $this->query = $url_pars;
    }

    /**
     * Registers a callback on a certain GET route
     *
     * @param string $path
     * @param callable $callback
     */
    public function get ($path, callable $callback) {
        $this->get_routes[] = new Route(self::split_path($path), $callback);
    }

    /**
     * Registers a callback on a certain POST route
     *
     * @param string $path
     * @param callable $callback
     */
    public function post ($path, callable $callback) {
        $this->post_routes[] = new Route(self::split_path($path), $callback);
    }

    /**
     * Checks all registered routes against method and path and executes callback
     *
     * Should be called after all routes have been registered
     */
    public function run () {

        $response = $this->execute_matching_route();

        if (is_string($response) || is_array($response)) {
            $response = new Response($response);
        }

        // TODO: implement middleware,
        // pass response back through middleware stack
        // before finally sending
        $response->send();
    }
}

// check if this is an admin-panel
// request, and if not, prevent wordpress from parsing
// the url and running a query based on it.
function prevent_parse_request () {

    // if the path starts with admin or login,
    // which are two convenient wordpress redirects, we don't
    // want to prevent the parsing, either. Same goes for paths
    // starting with wp-content so a '*'-route won't falsely
    // catch uploads or static theme assets.
    $path = parse_url(get_current_url(), PHP_URL_PATH);
    $allowed = !preg_match('/^(admin|login|wp-content)/', $path);
    $allowed = $allowed && (!is_admin() || (defined('DOING_AJAX') && DOING_AJAX));

    if ($allowed) {
        add_action('do_parse_request', function ($do_parse, $wp) {
            $wp->query_vars = [];
            remove_action('template_redirect', 'redirect_canonical');
            return FALSE;
        }, 30, 2);
    }
}