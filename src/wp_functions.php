<?php

namespace Em4nl\Unplug;


require_once __DIR__ . '/utils.php';


if (!function_exists('Em4nl\Unplug\enhance_post')) {
    function enhance_post(&$post, $cb) {
        if (is_acf_active()) {
            $post->fields = get_fields($post);
        }
        if ($cb) {
            $res = $cb($post);
            if ($res) {
                $post = $res;
            }
        }
    }
}


/**
 * These 4 are meant as a bit more flexible replacements for
 * WordPress' functions that also autoload custom fields
 */
if (!function_exists('Em4nl\Unplug\get_post')) {
    function get_post($type='post', $name=NULL, $cb=NULL) {
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
}

if (!function_exists('Em4nl\Unplug\get_page')) {
    function get_page($name, $cb=NULL) {
        return get_post('page', $name, $cb);
    }
}

// get_posts() [0] -- returns all posts
// get_posts('post') [1] - post_type
// get_posts(function() {}) [1] - enhance_post_callback
// get_posts('post', function() {}) [2] - post_type, enhance_post_callback
// get_posts(5, 2) [2] - posts_per_page, page_number
// get_posts(5, 2, function() {}) [3] - posts_per_page, page_number, enhance_post_callback
// get_posts('post', 5, 2, function() {}) [4] - post_type, posts_per_page, page_number, enhance_post_callback
if (!function_exists('Em4nl\Unplug\get_posts')) {
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
}

// get_terms() [0] -- returns the post tags
// get_terms('post_tag') [1] - taxonomy
// get_terms(['order' => 'DESC']) [1] - options
// get_terms(function() {}) [1] - callback
// get_terms('post_tag', ['order' => 'DESC']) [2] - taxonomy, options
// get_terms('post_tag', function() {}) [2] - taxonomy, callback
// get_terms('post_tag', ['order' => 'DESC'], function() {}) [3] - taxonomy, options, callback
// actually get_terms would also work with only options and
// callback, but in reverse order get_terms(callback, options).
// but this is unintended and shouldn't be used
if (!function_exists('Em4nl\Unplug\get_terms')) {
    function get_terms($taxonomy='post_tag', $options=NULL, $callback=NULL) {
        if (is_array($taxonomy)) {
            $options = $taxonomy;
            $taxonomy = 'post_tag';
        }

        if (is_callable($taxonomy)) {
            $callback = $taxonomy;
            $taxonomy = 'post_tag';
        }

        if (is_callable($options)) {
            $callback = $options;
            $options = NULL;
        }

        if ($options) {
            $query = $options;
        } else {
            $query = array();
        }
        $query['taxonomy'] = $taxonomy;
        $terms = \get_terms($query);
        foreach ($terms as &$term) {
            enhance_post($term, $callback);
        }
        return $terms;
    }
}
