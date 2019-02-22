<?php

namespace Em4nl\Unplug;


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

    public function as_plugin() {
        return array('response' => array($this, 'plugin_response'));
    }

    public function plugin_response($context, $response) {
        $global_do = defined('UNPLUG_CACHE') && UNPLUG_CACHE;
        $res_do = !isset($context['no_cache']) || !$context['no_cache'];
        $do_cache = $global_do && $res_do;
        if ($do_cache) {
            $this->add($context['path'], $response);
        }
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

        // TODO
        // - get filename extension from headers
        // - if that fails or is unclear, get extension from path
        // - or maybe the other way round: get extension from path,
        //   and if it has no extension, use headers. maybe better.

        // get the relative path to the cache dir
        $rel_dir = $this->find_rel_dir();

        $path = $this->prepare_path($path);

        if ($response instanceof ContentResponse) {

            $filename = $this->save($path, $response);
            $file = $rel_dir . '/' . $filename;

            $rule = self::create_rule($path, $file);
        }

        if (isset($rule) && !$this->rule_exists($rule)) {

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
