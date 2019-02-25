<?php

namespace Em4nl\Unplug;


// TODO include legible version of pathname (with illegal chars
// stripped of course) in filename, before hash?

class Cache {

    function __construct($dir, $options=array()) {
        $this->dir = $dir;
        // for a list of mime types see
        // https://wiki.selfhtml.org/wiki/MIME-Type/%C3%9Cbersicht
        $this->types = isset($options['types'])
                     ? $options['types']
                     : array('html', 'xml', 'json');
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
        $filename = $this->get_filename($uri, $extension);
        $file_path = "{$this->dir}/$filename";
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
        $filename = $this->get_filename($uri, $extension);
        return $this->save($filename, $extension, $response);
    }

    function save($filename, $extension, $response) {
        // create the cache dir if it doesn't exist
        if (!file_exists($this->dir)) {
            mkdir($this->dir, 0755);
        }

        $temp_dir = sys_get_temp_dir();
        $unique_id = uniqid('', TRUE);
        $temp_path = "$temp_dir/$filename.$unique_id.$extension";

        $file_path = "{$this->dir}/$filename";

        $bytes = file_put_contents($temp_path, $response);
        if ($bytes === FALSE) {
            return FALSE;
        }

        $rename_success = rename($temp_path, $file_path);
        if (!$rename_success) {
            return FALSE;
        }

        return TRUE;
    }

    function get_filename($uri, $extension) {
        $hash = hash('sha256', $uri);
        $slug = sanitize($uri);
        if ($slug !== '') {
            $slug .= '.';
        }
        $filename = $slug . $hash . '.' . $extension;
        return $filename;
    }

    function empty_cache_directory() {
        self::empty_directory($this->dir);
    }

    function is_valid_extension($ext) {
        return in_array($ext, $this->types);
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
        return 'html';
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


// https://web.archive.org/web/20130208144021/http://neo22s.com/slug
function sanitize($s) {
	// everything to lower and no spaces begin or end
	$res = strtolower(trim($s));
 
	//replace accent characters, depends your language is needed
	$res = replace_accents($res);
 
	// decode html maybe needed if there's html I normally don't use this
	//$res = html_entity_decode($res, ENT_QUOTES, 'UTF8');
 
	// adding - for spaces and union characters
	$find = array(' ', '&', '\r\n', '\n', '+', ',');
	$res = str_replace ($find, '-', $res);
 
	// replace rest of special chars
	$res = preg_replace('/[^a-z0-9\-]/', '-', $res);
 
    // replace multiple dashes in a row with a single dash
	$res = preg_replace('/[\-]+/', '-', $res);

    // trim dashes in front end end
    return trim($res, '-');
}

function replace_accents($s) {
    //replace for accents catalan spanish and more
    $a = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ'); 
    $b = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o'); 
    return str_replace($a, $b, $s);
}
