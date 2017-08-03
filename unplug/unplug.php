<?php
/*
Plugin Name: unplug
Description: Unplug WP's assumptive defaults
Version: 0.0.0
Author: Emanuel Tannert, Wolfgang Schöffel
Author URI: http://unfun.de
*/

// FEATURE IDEAS - let's see if we're going to need them
// - Add an option to switch off caching for individual routes
// - Convenience idea: let unplug\unplug() instantiate a global
//   $router so the user doesn't have to do it in their index.php,
//   and instead can just start defining routes. (Also, can we not
//   auto-start the router after all routes have been registered
//   instead of having to call run explicitly? E.g. by forcing
//   the user to register a catchall route and handle 404s?)
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

namespace unplug;

class Route {

    public $path;
    public $callback;
    public $do_cache;

    public function __construct (array $path, callable $callback, $do_cache) {
        $this->path = $path;
        $this->callback = $callback;
        $this->do_cache = $do_cache;
    }

    // make the callback fn directly callable
    public function __call ($method, $args) {
        if (is_callable(array($this, $method))) {
            return call_user_func_array($this->$method, $args);
        }
    }
}

class Request {

    public $path;
    public $params;
    public $query;

    public function __construct (array $path, array $params, array $query) {
        $this->path = $path;
        $this->params = $params;
        $this->query = $query;
    }
}

class Response {

    protected $status;
    protected $body;
    public $is_json;

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

class Cache {

    /**
     * @throws if the sha256 hash algorithm is not available
     */
    private static function assert_sha256_available () {

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
    private static function one_dir_up ($dir) {

        $segments = explode('/', $dir);
        $segments = array_filter($segments, function ($str) {
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
    private static function generate_htaccess_section ($rewrite_base) {

        $directives = [];
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
     * @param string $query
     * @returns array
     */
    private static function create_rule ($path, $query, $file) {
        $rule = [];
        $rule[] = 'RewriteCond %{QUERY_STRING} ^' . preg_quote($query) . '$';
        $rule[] = 'RewriteRule ^' . preg_quote($path) . '$ ' . $file . '? [L]';
        return $rule;
    }

    private $dir;
    private $htaccess;
    private $htaccess_path;

    /**
     * @param string $dir
     */
    public function __construct ($dir) {

        $this->dir = $dir;
        self::assert_sha256_available();
        $this->find_htaccess_path();
        $this->read_htaccess();
        $this->extract_rewrite_base();
    }

    /**
     * Public interface: cache a new response
     */
    public function add ($path, $query, $response, $extension) {

        $path = $this->prepare_path($path);

        $file = $this->save($path, $query, $response, $extension);

        $rule = self::create_rule($path, $query, $file);
        $this->insert_rule($rule);

        $this->write_htaccess();
    }

    /**
     * Public interface ii: invalidate the cache
     */
    public function flush () {

        $this->remove_htaccess_section();
        $this->write_htaccess();

        // TOTHINKABOUT:
        // we may want to delete the files
        // in the cache dir at some point
    }

    /**
     * Read the .htaccess file from the disk (if any)
     * and splits it into an array of lines.
     *
     * @throws if .htaccess isn't readable
     */
    private function read_htaccess () {

        $htaccess_str = file_get_contents($this->htaccess_path);

        if ($htaccess_str === false) {
            throw new \Exception('Could not read ' . $this->htaccess_path);
        }

        $this->htaccess = explode("\n", $htaccess_str);
    }

    /**
     * Serialise the htaccess array and attempts
     * to write it back to disk
     *
     * @throws if writing fails
     */
    private function write_htaccess () {

        $htaccess_str = join("\n", $this->htaccess);

        $success = file_put_contents($this->htaccess_path, $htaccess_str);

        if ($success === false) {
            throw new \Exception('Could not write ' . $this->htaccess_path);
        }
    }

    /**
     * Extract the first RewriteBase line from a .htaccess
     * or set $this->rewrite_base to a default 'RewriteBase /'
     */
    private function extract_rewrite_base () {

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
    private function insert_rule (array $rule) {

        // true means strict comparison (no type coercion)
        $index = array_search('# END Unplug rules', $this->htaccess, true);

        // if there is no Unplug section in the .htaccess file,
        // we insert one and search again
        if ($index === false) {

            // generate the Unplug section
            $unplug_htaccess_section =
                self::generate_htaccess_section($this->rewrite_base);

            // and insert it in front of everything else
            // into our .htaccess
            $this->htaccess =
                array_merge($unplug_htaccess_section, $this->htaccess);

            // update the index--this can't be false again,
            // since we just inserted a '# END Unplug rules' line
            $index = array_search('# END Unplug rules', $this->htaccess, true);
        }

        // insert into $this->htaccess, beginning from $index,
        // and replacing 0 of the existing items (= just pushing
        // them to the back of the array), the items in $rules
        array_splice($this->htaccess, $index, 0, $rule);
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
    private function save ($path, $query, $response, $extension) {

        if (!file_exists($this->dir)) {
            mkdir($this->dir, 0755);
        }

        // is it ridiculous to add the questionmark?
        $hash = hash('sha256', $path . '?' . $query);

        $file = $this->dir . '/' . $hash . '.' . $extension;

        $success = file_put_contents($file, $response);

        if ($success === false) {
            throw new \Exception('Failed to write ' . $file);
        }

        return $file;
    }

    /**
     * Remove the complete Unplug section from .htaccess
     */
    private function remove_htaccess_section () {

        $begin = array_search('# BEGIN Unplug', $this->htaccess, true);
        $end = array_search('# END Unplug', $this->htaccess, true);

        // plus one, because it's the total number of lines
        // we want to remove, NOT the number of lines TO GO
        // from the $begin line
        $length = $end - $begin + 1;

        array_splice($this->htaccess, $begin, $length);

        // remove empty lines from the beginning
        while (isset($this->htaccess[0]) && $this->htaccess[0] === '') {
            array_shift($this->htaccess);
        }
    }

    /**
     * Find the path to your nearest .htaccess file
     * in the directory of this file or one above it
     */
    private function find_htaccess_path () {

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
     * Strip a leading slash from $path if $this->rewrite_base
     * ends with a slash.
     *
     * @param string $path
     * @returns string
     */
    private function prepare_path ($path) {

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
        $numbered_path_segments = array_values($no_empty_str);
        // path segments may have urlencoded special charactes
        return array_map(function ($s) {
            return urldecode($s);
        }, $numbered_path_segments);
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

    protected $cache;
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

                // run the user-supplied callback function with the route
                // params plus any query parameters in an object as arguments
                return $route->callback(new Request(
                  $this->path,
                  $params,
                  $this->query
                ));
            }
        }

        // in case the supplied routes aren’t exhaustive,
        // and none matched, this is the last resort
        return self::last_error_callback();
    }

    public function __construct () {

        if (UNPLUG_CACHING) {
          $this->cache = new Cache(UNPLUG_CACHE_DIR);
        }

        $current_url = get_current_url();
        $url_parts = explode('?', $current_url, 2);

        $url_path = self::split_path($url_parts[0]);

        $url_vars = [];
        if (isset($url_parts[1])) {
            parse_str($url_parts[1], $url_vars);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->method = 'post';
        } else {
            $this->method = 'get';
        }

        $this->path = $url_path;
        $this->query = $url_vars;
    }

    /**
     * Registers a callback on a certain GET route
     *
     * $do_cache has no effect if caching isn't enabled on the router.
     *
     * @param string $path
     * @param callable $callback
     * @param bool $do_cache
     */
    public function get ($path, callable $callback, $do_cache=true) {
        $this->get_routes[] =
            new Route(self::split_path($path), $callback, $do_cache);
    }

    /**
     * Registers a callback on a certain POST route
     *
     * $do_cache has no effect if caching isn't enabled on the router.
     *
     * @param string $path
     * @param callable $callback
     * @param bool $do_cache
     */
    public function post ($path, callable $callback, $do_cache=true) {
        $this->post_routes[] =
            new Route(self::split_path($path), $callback, $do_cache);
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

        // if caching is on, save the response to a file
        // and write a new redirect rule
        if (UNPLUG_CACHING) {

            // serialise path again
            $path = join('/', $this->path);

            // serialise query again
            $query_parts = [];
            foreach ($this->query as $key => $val) {
                $query_parts[] = $key . '=' . $val;
            }
            $query = join('&', $query_parts);

            // get response string and file extension
            if ($response->is_json) {
                $response_str = json_encode($response->json());
                $extension = 'json';
            } else {
                $response_str = $response->body();
                $extension = 'html';
            }

            $this->cache->add($path, $query, $response_str, $extension);
        }

        $response->send();
    }
}

/**
 * The most important export from this plugin.
 * Call unplug\unplug in your functions.php to
 * prevent WordPress from running its default
 * query and template selection thing.
 * Also switch on caching here.
 *
 * @param array options
 */
function unplug ($options=[]) {

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
        add_action('do_parse_request', function ($do_parse, $wp) {
            $wp->query_vars = [];
            remove_action('template_redirect', 'redirect_canonical');
            return FALSE;
        }, 30, 2);
    }

    // if caching is on, make sure to empty the cache on
    // save_post and set a few constants so the router
    // knows whether we want to cache or not
    if (isset($options['caching']) && $options['caching'] === true) {

        define('UNPLUG_CACHING', true);
        if (isset($options['cache_dir'])) {
            define('UNPLUG_CACHE_DIR', $options['cache_dir']);
        } else {
            define('UNPLUG_CACHE_DIR', __DIR__ . '/_unplug_cache');
        }

        add_action('save_post', function () {

            // this function will only be called if caching
            // is on, so it's safe to assume that
            // UNPLUG_CACHE_DIR will be set
            $cache = new Cache(UNPLUG_CACHE_DIR);
            $cache->flush();
        });

    } else {

        define('UNPLUG_CACHING', false);
    }
}