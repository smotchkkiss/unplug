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

    private $dir;

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

        if ($response instanceof ContentResponse) {
            $filename = $this->save($path, $response);
        }
    }

    /**
     * Public interface ii: invalidate the cache
     */
    public function flush() {
        // delete everything in the cache dir,
        // but not the cache dir itself.
        // TODO make empty_cache_directory thread safe
        // TODO again: why does this have to be thread safe?
        // $this->empty_cache_directory();
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
}
