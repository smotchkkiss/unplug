<?php
/*
Plugin Name: unplug
Description: Unplug WP's assumptive defaults
Version: 0.0.4
Author: Emanuel Tannert, Wolfgang Schöffel
Author URI: http://unfun.de
*/

// FEATURE IDEAS - let's see if we're going to need them
// - Layout the cache directory like a static version of the website.
//   So, instead of saving everything to a <sha256hash>.<extension>,
//   save a request for a path to path/to/page/index.html. This would
//   imply that we have to change the way queries are handled. Options:
//   - Build Websites that don't use urls with query strings
//     I find that hard because sometimes a page has additional state
//     that we want to preserve but it makes no sense to save it in
//     a hierarchical structure like a path, for example if a certain
//     section is expanded or not. Solution may be to not preserve such
//     state, of course.
//   - Handle queries with caching the same way we're doing it now,
//     and save the version with the query to path/to/page/<hashsum>.html
//     The Problem with this solution is that it undermines the original
//     idea of having the cache look like a static version of the site
//   - Ignore queries in the backend, just make one-line RewriteRules
//     and always send the same file; if necessary, restore state via
//     JavaScript
//   - Another thing is that if we really want it to look like a static
//     version of the site we'll have to empty the cache dir on
//     Cache#flush, otherwise it will start to look more like a static
//     version of the page plus some random old crap
// - Insane Idea: with the cache directory laid out like the static site
//   and everything, can we not make this even more like a static page
//   generator and generate everything in one step instead of waiting
//   for the user to request every single page? This would, of course,
//   mean that parametrised routes will have to know every possible
//   parameter in order to generate all the variations. But maybe that's
//   not too hard, if it knows which field of which post_type to look
//   up/transform in a certain way? (Could be just another function that
//   is given to the route and when called returns all the options for
//   each parameter or something!) And of course queries would be
//   problematic again, so we'll have to throw them out completely or
//   make them a JavaScript-only thing like mentioned before (it kind
//   of makes sense). This way, we would effectively have a static
//   page generator with the comfortable/convenient API of a router!
//   Best of both worlds?
//   TODO:
//   - prevent router from running more than once [?]

namespace unplug;

if (!defined('ABSPATH')) {
    exit;
}

include_once(ABSPATH . 'wp-admin/includes/plugin.php');

// make sure UNPLUG_CACHE is defined,
// so we don't have to check that everytime
if (!defined('UNPLUG_CACHE')) {
    define('UNPLUG_CACHE', false);
}


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


/**
 * Gets the current url, but without protocol/host
 * By Giuseppe Mazzapica @gmazzap as published on
 * https://roots.io/routing-wp-requests/
 *
 * @returns String url
 */
function get_current_url() {

    $current_url = trim(esc_url_raw(add_query_arg(array())), '/');
    $home_path = trim(parse_url(home_url(), PHP_URL_PATH), '/');
    if ($home_path && strpos($current_url, $home_path) === 0) {
        $current_url = trim(substr($current_url, strlen($home_path)), '/');
    }

    return $current_url;
}


class Router {

    static function get_default_instance() {
        static $default_instance = NULL;
        if ($default_instance === NULL) {
            $default_instance = new Router();
        }
        return $default_instance;
    }

    function __construct() {
        $this->base_path = '/';
        $this->get_trie = array('nodes' => array());
        $this->post_trie = array('nodes' => array());
        $this->middlewares = array();
    }

    public function use($callback) {
        $this->middlewares[] = $callback;
    }

    function get($path, $callback) {
        $path_segments = explode('/', trim($path, '/'));
        $path_variations = self::get_path_variations($path_segments);
        foreach ($path_variations as $path_variation) {
            // completely empty variations don't work with the
            // trie - the root path needs to be represented by an
            // array containing an empty string
            if (!$path_variation) {
                $path_variation[] = '';
            }
            $node = &self::trie_insert($this->get_trie, $path_variation, 0);
            $node['callback'] = $callback;
        }
    }

    function post($path, $callback) {
        $path_segments = explode('/', trim($path, '/'));
        $path_variations = self::get_path_variations($path_segments);
        foreach ($path_variations as $path_variation) {
            // completely empty variations don't work with the
            // trie - the root path needs to be represented by an
            // array containing an empty string
            if (!$path_variation) {
                $path_variation[] = '';
            }
            $node = &self::trie_insert($this->post_trie, $path_variation, 0);
            $node['callback'] = $callback;
        }
    }

    function catchall($callback) {
        $this->catchall_callback = $callback;
    }

    function run() {
        $current_url = get_current_url();
        $url_parts = explode('?', $current_url, 2);
        $path_segments = explode('/', $url_parts[0]);
        $query = array();
        if (isset($url_parts[1])) {
            parse_str($url_parts[1], $query);
        }
        $route_trie = $this->get_route_trie();

        $response = $this->execute_matching_route(
            $route_trie,
            $path_segments,
            $query
        );

        // if $response is already a Response object,
        // it will be returned untouched
        $response = make_content_response($response);

        if (UNPLUG_CACHE && $response->is_cacheable()) {

            // serialise path again
            $path = join('/', $this->path);

            $cache = Cache::Instance();
            $cache->add($path, $response);
        }

        $response->send();
    }

    function get_route_trie() {
        switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            return $this->get_trie;
        case 'POST':
            return $this->post_trie;
        default:
            return array('nodes' => array());
        }
    }

    function execute_matching_route($route_trie, $path_segments, $query) {
        $params = array();
        $node = self::trie_search($route_trie, $path_segments, 0, $params);
        $context = $this->get_context($path_segments, $params, $query);

        if ($node && isset($node['callback'])) {
            $response = $node['callback']($context);
        }

        // if route was matched, but didn't return a valid
        // response, we want to execute the global 404, too.
        if (!isset($response) || $response === NULL) {
            if (isset($this->catchall_callback)) {
                $response = not_found(
                    call_user_func($this->catchall_callback, $context)
                );
            } else {
                $response = not_found();
            }
        }

        return $response;
    }

    function get_context($path_segments, $params, $query) {
        $site_url = get_site_url();
        $path = self::reconstruct_path($path_segments);
        $current_url = $site_url.$path;
        if (substr($current_url, -1) !== '/') {
            $current_url .= '/';
        }
        $context = array(
            'path' => $path,
            'params' => $params,
            'query' => $query,
            'site_url' => $site_url,
            'current_url' => $current_url,
            'theme_url' => get_template_directory_uri(),
            'site_title' => get_bloginfo(),
            'site_description' => get_bloginfo('description'),
        );
        return $this->apply_middlewares($context);
    }

    function apply_middlewares($context) {
        foreach ($this->middlewares as $middleware) {
            $res = $middleware($context);
            if ($res !== NULL) {
                $context = $res;
            }
        }
        return $context;
    }

    static function &trie_insert(&$trie, $path_segments, $index) {
        if (!isset($path_segments[$index])) {
            return $trie;
        }
        $segment = $path_segments[$index];

        if (strlen($segment) > 0 && $segment[0] === '*') {
            $node = &self::trie_insert_wildcard_node($trie, $segment);
        } elseif (strlen($segment) > 0 && $segment[0] === ':') {
            $node = &self::trie_insert_param_node($trie, $segment);
        } else {
            $node = &self::trie_insert_static_node($trie, $segment);
        }
        return self::trie_insert($node, $path_segments, $index + 1);
    }

    static function &trie_insert_wildcard_node(&$trie, $segment) {
        if (!isset($trie['wildcard_node'])) {
            $node = array('nodes' => array());
            $trie['wildcard_node'] = &$node;
        } else {
            $node = &$trie['wildcard_node'];
        }
        if (strlen($segment) > 1) {
            $trie['wildcard_name'] = substr($segment, 1);
        } else {
            $trie['wildcard_name'] = 'wildcard';
        }
        return $node;
    }

    static function &trie_insert_param_node(&$trie, $segment) {
        if (!isset($trie['param_node'])) {
            $node = array('nodes' => array());
            $trie['param_node'] = &$node;
        } else {
            $node = &$trie['param_node'];
        }
        $trie['param_name'] = substr($segment, 1);
        return $node;
    }

    static function &trie_insert_static_node(&$trie, $segment) {
        if(!isset($trie['nodes'][$segment])) {
            $node = array('nodes' => array());
            $trie['nodes'][$segment] = &$node;
        } else {
            $node = &$trie['nodes'][$segment];
        }
        return $node;
    }

    static function trie_search($trie, $path, $index, &$params) {
        if (!isset($path[$index])) {
            return $trie;
        }

        if (isset($trie['nodes'][$path[$index]])) {
            return self::trie_search_static_node($trie, $path, $index, $params);
        } elseif (isset($trie['param_name'])) {
            return self::trie_search_param_node($trie, $path, $index, $params);
        } elseif (isset($trie['wildcard_name'])) {
            return self::trie_search_wildcard_node($trie, $path, $index, $params);
        }
    }

    static function trie_search_static_node($trie, $path, $index, &$params) {
        $node = $trie['nodes'][$path[$index]];
        return self::trie_search($node, $path, $index + 1, $params);
    }

    static function trie_search_param_node($trie, $path, $index, &$params) {
        $params[$trie['param_name']] = urldecode($path[$index]);
        $node = $trie['param_node'];
        return self::trie_search($node, $path, $index + 1, $params);
    }

    static function trie_search_wildcard_node($trie, $path, $index, &$params) {
        $path = array_slice($path, $index);
        $path = array_map('urldecode', $path);
        $params[$trie['wildcard_name']] = join('/', $path);
        return $trie['wildcard_node'];
    }

    static function reconstruct_path($path_segments) {
        $path = join('/', $path_segments);
        if (!strlen($path) || $path[0] !== '/') {
            return "/$path";
        }
        return $path;
    }

    static function get_path_variations($path_segments) {
        $optionals = array_keys(array_filter(
            $path_segments,
            function($segment) {
                return substr($segment, -1) === '?';
            }
        ));
        $permutations = self::get_permutations($optionals);

        $no_question = array_map(function($segment) {
            if (substr($segment, -1) === '?') {
                return substr($segment, 0, -1);
            } else {
                return $segment;
            }
        }, $path_segments);

        $variations = [$no_question];
        foreach ($permutations as $permutation) {
            $variation = $no_question;
            $length_correction = 0;
            foreach ($permutation as $index) {
                array_splice($variation, $index - $length_correction, 1);
                $length_correction++;
            }
            $variations[] = $variation;
        }
        return $variations;
    }

    static function get_permutations($input) {
        $res = array();
        while ($input) {
            $input_element = array_shift($input);
            $solution = array($input_element);
            $res[] = $solution;
            foreach ($input as $rest) {
                $solution = array_merge($solution, array($rest));
                $res[] = $solution;
            }
        }
        return $res;
    }
}


/**
 * Convenience interface to the default Router instance
 */

function _use($middleware) {
    Router::get_default_instance()->use($middleware);
}

function get($path, $callback) {
    Router::get_default_instance()->get($path, $callback);
}

// function get_one($path, $callback) [2]
// function get_one($type, $path, $callback) [3]
// function get_one($type, $param, $path, $callback) [4]
// function get_one($type, $key, $param, $path, $callback) [5]
// function get_one([$type, [[$key,] $param,]] $path, $callback)
function get_one() {
    $num_args = func_num_args();
    if ($num_args < 2 || $num_args > 5) {
        throw new \Exception('get_single expects 2-5 arguments');
    }

    // callback is always next-to-last argument,
    // path always comes before it
    $callback = func_get_arg($num_args - 1);
    $path = func_get_arg($num_args - 2);

    if ($num_args > 2) {
        $type = func_get_arg(0);
    } else {
        $path_fix_segments = array_values(array_filter(
            explode('/', $path),
            function($segment) {
                return strlen($segment) && $segment[0] !== ':';
            }
        ));
        $type_name = $path_fix_segments[0];
        foreach (get_post_types(null, 'objects') as $post_type) {
            if (strtolower($post_type->labels->singular_name) === $type_name
                || strtolower($post_type->labels->name) === $type_name) {
                $type = $post_type->name;
                break;
            }
        }
        if (!isset($type)) {
            $type = 'post';
        }
    }

    if ($num_args > 3) {
        $param = func_get_arg($num_args - 3);
    } else {
        $path_params = array_values(array_filter(
            explode('/', $path),
            function($segment) {
                return substr($segment, 0, 1) === ':';
            }
        ));
        $param = substr($path_params[0], 1);
    }

    if ($num_args > 4) {
        $key = func_get_arg(1);
    } else {
        $key = $param;
    }

    Router::get_default_instance()->get(
        $path,
        function($context) use ($type, $key, $param, $callback) {
            $query = [
                'post_type' => $type,
                'posts_per_page' => -1,
            ];
            if (isset($context['params'][$param])) {
                $query[$key] = $context['params'][$param];
            }
            $posts = get_posts($query);
            if ($posts) {
                $context['post'] = $posts[0];
                return $callback($context);
            }
        }
    );
}

// function get_many($path, $callback) [2]
// function get_many($post_type, $path, $callback) [3]
// function get_many($post_type, $posts_per_page, $path, $callback) [4]
// function get_many($path, $options, $callback) [3]
// $options = [
//     'post_type' => 'post',
//     'posts_per_page' => 10,
//     'order' => 'ASC',
//     'order_by' => 'menu_order',
//     'param' => 'page',
// ]
function get_many() {
    $num_args = func_num_args();
    if ($num_args < 2 || $num_args > 4) {
        throw new \Exception('get_page expects 2-4 arguments');
    }

    $callback = func_get_arg($num_args - 1);

    $snd_arg = func_get_arg(1);
    if (is_array($snd_arg)) {
        $options = $snd_arg;
    } else {
        $options = [];
    }

    if ($num_args === 3 && is_array($snd_arg)) {
        $path = func_get_arg(0);
    } else {
        $path = func_get_arg($num_args - 2);
    }

    if ($num_args > 2 && !is_array($snd_arg)) {
        $options['post_type'] = func_get_arg(0);
    } elseif (!isset($options['post_type'])) {
        $path_fix_segments = array_values(array_filter(
            explode('/', $path),
            function($segment) {
                return strlen($segment) && $segment[0] !== ':';
            }
        ));
        if ($path_fix_segments) {
            $type_name = $path_fix_segments[0];
            foreach (get_post_types(null, 'objects') as $post_type) {
                if (strtolower($post_type->labels->singular_name) === $type_name
                    || strtolower($post_type->labels->name) === $type_name) {
                    $options['post_type'] = $post_type->name;
                    break;
                }
            }
        }
        if (!isset($options['post_type'])) {
            $options['post_type'] = 'post';
        }
    }

    if ($num_args > 3) {
        $options['posts_per_page'] = func_get_arg(1);
    } elseif (!isset($options['posts_per_page'])) {
        $options['posts_per_page'] = -1;
    }

    if (isset($options['param'])) {
        $param = $options['param'];
        unset($options['param']);
    } else {
        $path_params = array_values(array_filter(
            explode('/', $path),
            function($segment) {
                return substr($segment, 0, 1) === ':';
            }
        ));
        if ($path_params) {
            $param = substr($path_params[0], 1);
        } else {
            $param = NULL;
        }
    }

    Router::get_default_instance()->get(
        $path,
        function($context) use ($options, $param, $callback) {
            $query = $options;
            if ($param && isset($context['params'][$param])) {
                $page = intval($context['params'][$param]);
                if (!$page) {
                    return;
                }
                $query['paged'] = $page;
            }
            $context['posts'] = get_posts($query);
            return $callback($context);
        }
    );
}

function post($path, $callback) {
    Router::get_default_instance()->post($path, $callback);
}

function catchall($callback) {
    Router::get_default_instance()->catchall($callback);
}

function dispatch() {
    Router::get_default_instance()->run();
}


/**
 * Call unplug\unplug in your functions.php to
 * prevent WordPress from running its default
 * query and template selection thing.
 * Also switch on caching here.
 *
 * @param array options
 */
function unplug($options=array()) {

    // check if this is an admin-panel
    // request, and if not, prevent wordpress from parsing
    // the url and running a query based on it.
    // ---
    // if the path starts with admin or login,
    // which are two convenient wordpress redirects, we don't
    // want to prevent the parsing, either. Same goes for paths
    // starting with wp-content so a '*'-route won't falsely
    // catch uploads or static theme assets.
    $path = parse_url(get_current_url(), PHP_URL_PATH);
    $allowed = !preg_match('/^(admin|login|wp-content)/', $path);
    $allowed = $allowed && (!is_admin() || (defined('DOING_AJAX') && DOING_AJAX));

    if ($allowed) {

        define('UNPLUG_RUN', true);

        add_action('do_parse_request', function($do_parse, $wp) {
            $wp->query_vars = array();
            remove_action('template_redirect', 'redirect_canonical');
            return FALSE;
        }, 30, 2);

    } else {

        define('UNPLUG_RUN', false);
    }

    // if caching is on, make sure to empty the cache on
    // save_post and set a few constants so the router
    // knows whether we want to cache or not
    if (UNPLUG_CACHE) {

        if (isset($options['cache_dir'])) {

            define('UNPLUG_CACHE_DIR', $options['cache_dir']);

        } else {

            define('UNPLUG_CACHE_DIR', __DIR__ . '/_unplug_cache');
        }

        $after_save_post = function() use ($options) {

            // this function will only be called if caching
            // is on, so it's safe to assume that
            // UNPLUG_CACHE_DIR will be set
            $cache = new Cache(UNPLUG_CACHE_DIR);
            $cache->flush();

            if (isset($options['on_save_post'])) {
                $options['on_save_post']($cache);
            }
        };

        add_action('save_post', $after_save_post, 20);

        $is_acf_active = is_plugin_active('advanced-custom-fields/acf.php');
        $is_acf_pro_active = is_plugin_active('advanced-custom-fields-pro/acf.php');
        if ($is_acf_active || $is_acf_pro_active) {
            add_action('acf/save_post', $after_save_post, 20);
        }
    }
}


class Cache {

    /**
     * @throws if the sha256 hash algorithm is not available
     */
    private static function assert_sha256_available() {

        if (!in_array('sha256', hash_algos(), true)) {
            throw new \Exception('SHA256 is not available');
        }
    }

    /**
     * Strip the last directory from a path if it
     * isn't already the root directory
     *
     * @param string $dir
     * @returns string
     */
    private static function one_dir_up($dir) {

        $segments = explode('/', $dir);
        $segments = array_filter($segments, function($str) {
            return $str !== '';
        });
        $length = sizeof($segments);
        $segments = array_slice($segments, 0, $length - 1);
        $newdir = '/' . join('/', $segments);
        return $newdir;
    }

    /**
    * Expects $rewrite_base to be a string like '/'
    * and $rules to be an array of associative arrays
    * like ['path': $path, 'query': $query, 'file': $file],
    * where path is the path to match, without the leading slash
    * (at least if that one's already in the $rewrite_base),
    * $query is the query string to match, without the leading questionmark,
    * and $file is the relative path to the file in the cache directory
    * that we want delivered.
    *
    * @param string $rewrite_base
    * @param array $rules
    */
    private static function generate_htaccess_section($rewrite_base) {

        $directives = array();
        $directives[] = '# BEGIN Unplug';
        $directives[] = '<IfModule mod_rewrite.c>';
        $directives[] = 'RewriteEngine On';
        $directives[] = $rewrite_base;

        $directives[] = '# BEGIN Unplug rules';

        // Actual rules will go in between here

        $directives[] = '# END Unplug rules';

        $directives[] = '</IfModule>';
        $directives[] = '# END Unplug';

        // finish with an empty line for good taste
        $directives[] = '';

        return $directives;
    }

    /**
     * Create a rule (= array of 2 lines for .htaccess)
     *
     * @param string $path
     * @param string $file
     * @returns array
     */
    private static function create_rule($path, $file) {

        // this could be written less verbose of course now that there's only
        // one line, but I don't want to remove the multiline-rule support
        // right now in case we need it later (or want to add comments to rules,
        // for example). Multiline rules are also explicitly supported in
        // rule_exists and insert_rule.
        $rule = array();
        $rule[] = 'RewriteRule ^' . preg_quote($path) . '/?$ ' . $file . '? [L]';
        return $rule;
    }

    /**
     * Create a rule (= array of 2 lines for .htaccess)
     *
     * @param string $regexp
     * @param string $file
     * @returns array
     */
    private static function create_rule_regexp($regexp, $file) {

        // this could be written less verbose of course now that there's only
        // one line, but I don't want to remove the multiline-rule support
        // right now in case we need it later (or want to add comments to rules,
        // for example). Multiline rules are also explicitly supported in
        // rule_exists and insert_rule.
        $rule = array();
        $rule[] = 'RewriteRule ' . $regexp . ' ' . $file . '? [L]';
        return $rule;
    }

    private static function create_rule_redirect($path, $location, $status) {

        $rule = array();
        $rule[] = 'RewriteRule ^' . preg_quote($path) . '/?$ '
                . $location . ' [R=' . $status . ']';
        return $rule;
    }

    private $dir;
    private $htaccess;
    private $htaccess_path;

    public function Instance() {
        static $instance = NULL;
        if ($instance === NULL) {
            $instance = new Cache(UNPLUG_CACHE_DIR);
        }
        return $instance;
    }

    /**
     * @param string $dir
     */
    private function __construct($dir) {

        // create the cache dir if it doesn't exist
        if (!file_exists($dir)) {
            mkdir($dir, 0755);
        }

        $this->dir = realpath($dir);
        self::assert_sha256_available();
        $this->find_htaccess_path();
        $this->read_htaccess();
        $this->extract_rewrite_base();
    }

    /**
     * New public interface: cache a new Response
     */
    public function add($path, $response) {

        // get the relative path to the cache dir
        $rel_dir = $this->find_rel_dir();

        $path = $this->prepare_path($path);

        if ($response instanceof ContentResponse) {

            $filename = $this->save($path, $response);
            $file = $rel_dir . '/' . $filename;

            $rule = self::create_rule($path, $file);

        } elseif ($response instanceof RedirectResponse) {

            $rule = self::create_rule_redirect(
                $path, $response->get_location(), $response->get_status());
        }

        if (isset($rule) && !$this->rule_exists($rule)) {

            $this->insert_rule($rule);
            $this->write_htaccess();
        }
    }

    public function add_regexp($regexp, $response) {

        // get the relative path to the cache dir
        $rel_dir = $this->find_rel_dir();

        $filename = $this->save($regexp, $response);
        $file = $rel_dir . '/' . $filename;

        $rule = self::create_rule_regexp($regexp, $file);

        if (!$this->rule_exists($rule)) {

            $this->insert_rule($rule);
            $this->write_htaccess();
        }
    }

    /**
     * Public interface ii: invalidate the cache
     */
    public function flush() {

        // clean up rules from the .htaccess file,
        // but don't delete the unplug section itself.
        // this way, the user can, after an unplug
        // section has been established in the file,
        // decide for other rules to come before or
        // after it, and that order will be maintained.
        // (before we would always insert a new unplug
        // section at the beginning of the htaccess
        // file, making it impossible to have e.g.
        // a global http -> https redirect before)
        $this->remove_all_rules();
        $this->write_htaccess();

        // delete everything in the cache dir,
        // but not the cache dir itself.
        // TODO make empty_cache_directory thread safe
        // $this->empty_cache_directory();
    }

    /**
     * Read the .htaccess file from the disk (if any)
     * and splits it into an array of lines.
     *
     * @throws if .htaccess isn't readable
     */
    private function read_htaccess() {

        $htaccess_str = file_get_contents($this->htaccess_path);

        if ($htaccess_str === false) {
            $message = 'Could not read ' . $this->htaccess_path;
            throw new \Exception($message);
        }

        $this->htaccess = explode("\n", $htaccess_str);
    }

    /**
     * Serialise the htaccess array and attempts
     * to write it back to disk
     *
     * @throws if writing fails
     */
    private function write_htaccess() {

        $htaccess_str = join("\n", $this->htaccess);

        $success = file_put_contents(
            $this->htaccess_path, $htaccess_str, LOCK_EX);

        if ($success === false) {
            $message = 'Could not write ' . $this->htaccess_path;
            throw new \Exception($message);
        }
    }

    /**
     * Extract the first RewriteBase line from a .htaccess
     * or set $this->rewrite_base to a default 'RewriteBase /'
     */
    private function extract_rewrite_base() {

        $lines = array_filter($this->htaccess, function ($line) {
            return substr($line, 0, 12) === 'RewriteBase ';
        });

        // reset indices
        $lines = array_values($lines);

        if (sizeof($lines) > 0) {
            $this->rewrite_base = $lines[0];
        } else {
            $this->rewrite_base = 'RewriteBase /';
        }
    }

    /**
     * Insert a given rule into our .htaccess
     *
     * @param array $rule
     */
    private function insert_rule(array $rule) {

        // true means strict comparison (no type coercion)
        $end = array_search('# END Unplug rules', $this->htaccess, true);

        // if there is no Unplug section in the .htaccess file,
        // we insert one and search again
        if ($end === false) {

            // generate the Unplug section
            $unplug_htaccess_section =
                self::generate_htaccess_section($this->rewrite_base);

            // and insert it in front of everything else
            // into our .htaccess
            $this->htaccess =
                array_merge($unplug_htaccess_section, $this->htaccess);

            // update the index--this can't be false again,
            // since we just inserted a '# END Unplug rules' line
            $end = array_search('# END Unplug rules', $this->htaccess, true);
        }

        // insert into $this->htaccess, beginning from $index,
        // and replacing 0 of the existing items (= just pushing
        // them to the back of the array), the items in $rules
        array_splice($this->htaccess, $end, 0, $rule);
    }

    /**
     * Check if a rule is already in the htaccess
     *
     * @param array $rule
     *
     * @returns bool
     */
    private function rule_exists(array $rule) {

        // true means strict comparison (no type coercion)
        $begin = array_search('# BEGIN Unplug rules', $this->htaccess, true);
        $end = array_search('# END Unplug rules', $this->htaccess, true);

        $rule_exists = false;

        if ($begin !== false && $end !== false) {

            $rule_lines = sizeof($rule);

            // iterate over the rules section of $this->htaccess;
            // step width is $rule_lines to support arbitrary-length
            // rules (but all rules must be of the same length!)
            for ($i = $begin + 1; $i < $end; $i += $rule_lines) {

                $all_the_same = true;

                foreach ($rule as $index => $line) {
                    if ($this->htaccess[$i + $index] !== $line) {
                        $all_the_same = false;
                    }
                }

                if ($all_the_same) {
                    $rule_exists = true;
                    break;
                }
            }
        }

        return $rule_exists;
    }

    /**
     * Save the $response to a file and return
     * the full path to the file
     *
     * @param string $path
     * @param string $query
     * @param string $response
     * @returns string
     * @throws if file not writable
     */
    private function save($path, $response) {

        $hash = hash('sha256', $path);

        $filename = $hash . '.' . $response->get_extension();
        $file = $this->dir . '/' . $filename;

        $success = file_put_contents($file, $response->get_body());

        if ($success === false) {
            throw new \Exception('Failed to write ' . $file);
        }

        return $filename;
    }

    /**
     * Remove all rewrite rules from the .htaccess Unplug section
     */
    private function remove_all_rules() {

        $begin = array_search('# BEGIN Unplug rules', $this->htaccess, true);
        $end = array_search('# END Unplug rules', $this->htaccess, true);

        // shift begin up by 1, because we don't want
        // to remove the # BEGIN Unplug rules line
        $begin += 1;

        $length = $end - $begin;

        // if there is no Unplug section, $length will be -1
        // if there are no rules, $length will be 0
        // in both cases, we don't want to remove anything
        if ($length > 0) {

            array_splice($this->htaccess, $begin, $length);
        }
    }

    /**
     * Remove the complete Unplug section from .htaccess
     *
     * NOTE This function is currently unused because it
     *      was superseded by remove_all_rules; If it becomes
     *      clear that we won't need it again, remove it!
     */
    private function remove_htaccess_section() {

        $begin = array_search('# BEGIN Unplug', $this->htaccess, true);
        $end = array_search('# END Unplug', $this->htaccess, true);

        // if $begin == $end, there cannot be a (complete)
        // Unplug section, so we'd better not remove anything!
        if ($begin != $end) {

            // plus one, because it's the total number of lines
            // we want to remove, NOT the number of lines TO GO
            // from the $begin line
            $length = $end - $begin + 1;

            array_splice($this->htaccess, $begin, $length);
        }

        // remove empty lines from the beginning
        while (isset($this->htaccess[0]) && $this->htaccess[0] === '') {
            array_shift($this->htaccess);
        }
    }

    /**
     * Find the path to your nearest .htaccess file
     * in the directory of this file or one above it
     */
    private function find_htaccess_path() {

        $htaccess = '/.htaccess';
        $dir = __DIR__;

        while (!file_exists($dir . $htaccess) && $dir !== '/') {
            $dir = self::one_dir_up($dir);
        }

        $path = $dir . $htaccess;

        if (!file_exists($path)) {
            throw new \Exception('No .htaccess found!');
        }

        $this->htaccess_path = $path;
    }

    /**
     * Find the relative path of the cache dir --
     * relative to the location of the .htaccess file!
     *
     * @returns string
     */
    private function find_rel_dir() {

        // split the paths to the htaccess file and
        // to the cache dir into parts
        $htaccess_path = explode('/', $this->htaccess_path);
        $dir_path = explode('/', $this->dir);

        // remove the last element of the htacess path,
        // because it is the .htaccess filename
        array_pop($htaccess_path);

        // get the number of remaining path components ...
        $htaccess_path_length = sizeof($htaccess_path);

        // ... remove that many elements from the beginning
        // of the absolute path of the cache dir
        $dir_path = array_slice($dir_path, $htaccess_path_length);

        // join the remaining parts together and prepend
        // them with ./ to mark that we mean 'relative to
        // the current directory' (may not be necessary?)
        return './' . join('/', $dir_path);
    }

    /**
     * Recursively delete all the files and folders
     * in the cache directory
     */
    private function empty_cache_directory() {

        self::empty_directory($this->dir);
    }

    /**
     * Recursively delete all the files and folders
     * in a directory and the directory itself
     *
     * @param string $directory
     */
    private static function recursive_remove_directory($directory) {

        self::empty_directory($directory);

        rmdir($directory);
    }

    /**
     * Recursively delete all the files and folders
     * in a directory
     *
     * @param string $directory
     */
    private static function empty_directory($directory) {

        foreach (glob("{$directory}/*") as $file) {

            if (is_dir($file)) {
                self::recursive_remove_directory($file);
            } else {
                unlink($file);
            }
        }
    }

    /**
     * Strip a leading slash from $path if $this->rewrite_base
     * ends with a slash.
     *
     * @param string $path
     * @returns string
     */
    private function prepare_path($path) {

        $rb_length = strlen($this->rewrite_base);
        $p_length = strlen($path);
        $rb_last_char = $rb_length ? $this->rewrite_base[$rb_length - 1] : '';
        $p_first_char = $p_length ? $path[0] : '';
        if ($rb_last_char === '/' && $p_first_char === '/') {
            $path = substr($path, 1);
        }
        return $path;
    }
}
