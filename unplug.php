<?php
/*
Plugin Name: unplug
Description: Unplug WP's assumptive defaults
Version: 0.0.4
Author: Emanuel Tannert, Wolfgang Schöffel
Author URI: http://unfun.de
*/

//   TODO:
//   - prevent router from running more than once [?]

namespace unplug;


include_once(dirname(__FILE__) . '/urouter.php');


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


function is_acf_active() {
    return is_plugin_active('advanced-custom-fields-pro/acf.php')
        || is_plugin_active('advanced-custom-fields/acf.php');
}

function enhance_post(&$post, $cb) {
    if (is_acf_active()) {
        $post->fields = get_fields($post->ID);
    }
    if ($cb) {
        $res = call_user_func($cb, $post);
        if ($res) {
            $post = $res;
        }
    }
}


/**
 * These 3 are meant as a bit more flexible replacements for
 * WordPress' functions that also autoload custom fields
 */
function get_post($type, $name=NULL, $cb=NULL) {
    if (!$cb) {
        $cb = $name;
        $name = $type;
        $type = 'post';
    }
    $query = array(
        'post_type' => $type,
        'posts_per_page' => 1,
        'name' => $name,
    );
    $posts = \get_posts($query);
    if ($posts) {
        enhance_post($posts[0], $cb);
        return $posts[0];
    }
}

function get_page($name, $cb=NULL) {
    return get_post('page', $name, $cb);
}

// get_posts() [0]
// get_posts('post') [1]
// get_posts(function() {}) [1]
// get_posts('post', function() {}) [2]
// get_posts(5, 2) [2]
// get_posts(5, 2, function() {}) [3]
// get_posts('post', 5, 2, function() {}) [4]
function get_posts() {
    $num_args = func_num_args();
    if ($num_args > 4) {
        throw new \Exception('get_posts expects 2-4 arguments');
    }

    if ($num_args === 1) {
        $arg = func_get_arg(0);
        if (is_string($arg)) {
            $type = $arg;
        } elseif (is_callable($arg)) {
            $cb = $arg;
        } else {
            throw new \Exception(
                'single argument must be either post type or callback'
            );
        }
    } elseif ($num_args === 2) {
        list($fst, $snd) = func_get_args();
        if (is_string($fst) && is_callable($snd)) {
            $type = $fst;
            $cb = $snd;
        } elseif (is_numeric($fst) && is_numeric($fst)) {
            $per_page = $fst;
            $page = $snd;
        } else {
            throw new \Exception(
                'two options for two arguments: either post type and callback'
                . ' or posts per page and page number'
            );
        }
    } elseif ($num_args === 3) {
        list($fst, $snd, $trd) = func_get_args();
        if (is_numeric($fst) && is_numeric($snd) && is_callable($trd)) {
            $per_page = $fst;
            $page = $snd;
            $cb = $trd;
        } else {
            throw new \Exception(
                'when get_posts is called with three arguments, they must be'
                . ' posts per page, page number and callback'
            );
        }
    } elseif ($num_args === 4) {
        list($fst, $snd, $trd, $fth) = func_get_args();
        if (is_string($fst) && is_numeric($snd)
            && is_numeric($trd) && is_callable($fth)) {
            $type = $fst;
            $per_page = $snd;
            $page = $trd;
            $cb = $fth;
        } else {
            throw new \Exception(
                'get_posts argument order for four arguments is'
                . ' post type, posts per page, page number, callback'
            );
        }
    }

    if (!isset($type)) {
        $type = 'post';
    }
    if (!isset($per_page)) {
        $per_page = -1;
    }
    if (!isset($page)) {
        $page = 1;
    }
    if (!isset($cb)) {
        $cb = NULL;
    }

    $query = array(
        'post_type' => $type,
        'posts_per_page' => $per_page,
        'paged' => $page,
    );
    $posts = \get_posts($query);
    foreach ($posts as &$post) {
        enhance_post($post, $cb);
    }
    return $posts;
}


/**
 * Convenience interface to the default Router instance
 */

function _use($middleware) {
    _get_default_router()->_use($middleware);
}

function get($path, $callback) {
    _get_default_router()->get($path, $callback);
}

function post($path, $callback) {
    _get_default_router()->post($path, $callback);
}

function catchall($callback) {
    _get_default_router()->catchall($callback);
}

function dispatch() {
    _get_default_router()->run();
}

function _get_default_router() {
    static $router;
    if (!isset($router)) {
        $router = new \Em4nl\Urouter\Router();
    }
    $router->_use([
        'request' => function(&$context) {
            $site_url = get_site_url();
            $context['site_url'] = $site_url;
            // TODO sure that site_url never has a trailing slash?
            $context['current_url'] = $site_url.$context['path'];
            $context['theme_url'] = get_template_directory_uri();
            $context['site_title'] = get_bloginfo();
            $context['site_description'] = get_bloginfo('description');
        },
        'response' => function($context, $response) {
            $response = make_content_response($response);
            if (UNPLUG_CACHE && $response->is_cacheable()) {
                $cache = Cache::get_instance();
                $cache->add($context['path'], $response);
            }
            $response->send();
        },
    ]);
    // TODO set base path if WordPress is installed in subdir
    return $router;
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
            Cache::get_instance()->flush();

            if (isset($options['on_save_post'])) {
                $options['on_save_post']($cache);
            }
        };

        add_action('save_post', $after_save_post, 20);

        if (is_acf_active()) {
            add_action('acf/save_post', $after_save_post, 20);
        }
    }

    // hide the sample permalink on the edit post page when unplug
    // is in use, because the whole point of unplug is to implement
    // your own routing, so what wordpress thinks what the url of a
    // post is will often be wrong.
    add_filter('get_sample_permalink_html', function() {
        return '';
    });
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

    public static function get_instance() {
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
