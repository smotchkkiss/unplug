<?php

namespace Em4nl\Unplug;


class Cache {

    function __construct($dir, $options=array()) {
        $this->dir = $dir;
        // for a list of mime types see
        // https://wiki.selfhtml.org/wiki/MIME-Type/%C3%9Cbersicht
        $this->types = isset($options['types'])
                     ? $options['types']
                     : array(
                         'text/html' => 'html',
                         // 'application/xhtml+html' => 'html',
                         'text/xml' => 'xml',
                         // 'application/xml' => 'xml',
                         'application/json' => 'json',
                     );
        // TODO let's see if this option will be necessary or if we
        // won't be able to identify the type in many cases anyway
        $this->do_cache_unknown_types = isset($options['do_cache_unknown_types'])
                                      ? $options['do_cache_unknown_types']
                                      : FALSE;
        $this->invalidation_callbacks = array();
        self::assert_sha256_available();
    }

    function invalidate(callable $callback) {
        // if a callback returns TRUE, the cache will be skipped!
        $this->invalidation_callbacks[] = $callback;
    }

    function start() {
        ob_start();
    }

    function end($do_cache=TRUE) {
        $response = ob_get_clean();
        echo $response;
        if (http_response_code() === 200 && $do_cache) {
            return $this->add($response);
        }
        return FALSE;
    }

    function serve() {
        $uri = $this->get_current_uri();
        $extension = $this->get_extension($uri);
        $file_path = $this->get_file_path($uri, $extension);
        if (!file_exists($file_path)) {
            return FALSE;
        }
        foreach ($this->invalidation_callbacks as $callback) {
            if (call_user_func($callback, $file_path)) {
                return FALSE;
            }
        }
        $bytes = readfile($file_path);
        if ($bytes !== FALSE) {
            return TRUE;
        }
        return FALSE;
    }

    function flush() {
        // delete everything in the cache dir,
        // but not the cache dir itself.
        $this->empty_cache_directory();
    }

    function add($response) {
        $uri = $this->get_current_uri();
        $extension = $this->get_extension($uri);
        $file_path = $this->get_file_path($uri, $extension);
        return $this->save($file_path, $response);
    }

    function save($file_path, $response) {
        // create the cache dir if it doesn't exist
        if (!file_exists($this->dir)) {
            mkdir($this->dir, 0755);
        }

        $tmp_path = $file_path . '.' . uniqid('', TRUE) . '.tmp';

        $bytes = file_put_contents($tmp_path, $response);
        if ($bytes === FALSE) {
            return FALSE;
        }

        $rename_success = rename($tmp_path, $file_path);
        if (!$rename_success) {
            return FALSE;
        }

        return TRUE;
    }

    function get_file_path($uri, $extension) {
        $hash = hash('sha256', $uri);
        $filename = $hash . '.' . $extension;
        $file_path = $this->dir . '/' . $filename;
        return $file_path;
    }

    function empty_cache_directory() {
        self::empty_directory($this->dir);
    }

    function is_valid_extension($ext) {
        return in_array($ext, array_values($this->types));
    }

    function get_current_uri() {
        return $_SERVER['REQUEST_URI'];
    }

    function get_extension($uri) {
        // try to get extension from uri
        $uri_parts = explode('?', $uri);
        $path = $uri_parts[0];
        $path_parts = explode('.', $path);
        $path_parts_c = count($path_parts);
        $last_path_part = $path_parts[$path_parts_c - 1];
        if ($path_parts_c > 1 && $this->is_valid_extension($last_path_part)) {
            return $last_path_part;
        }
        // try to get extension from headers
        foreach (headers_list() as $header) {
            $matches = array();
            if (preg_match('/^Content-type:\s*(\S+)/', $header, $matches)) {
                $mime_type = $matches[1];
                if (isset($this->types[$mime_type])) {
                    return $this->types[$mime_type];
                }
            }
        }
        // return a default or NULL to signal failure
        if ($this->do_cache_unknown_types) {
            return 'html';
        }
        return NULL;
    }

    static function assert_sha256_available() {
        if (!in_array('sha256', hash_algos(), true)) {
            throw new \Exception('SHA256 is not available');
        }
    }

    static function recursive_remove_directory($directory) {
        self::empty_directory($directory);
        rmdir($directory);
    }

    static function empty_directory($directory) {
        foreach (glob("{$directory}/*") as $file) {
            if (is_dir($file)) {
                self::recursive_remove_directory($file);
            } else {
                unlink($file);
            }
        }
    }
}
