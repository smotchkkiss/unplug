<?php

namespace Em4nl\Unplug;


class Router {

    function __construct() {
        $this->base_path = '';
        $this->get_trie = new Trie();
        $this->post_trie = new Trie();
        $this->catchall_callback = NULL;
    }

    function base($base_path) {
        $this->base_path = trim($base_path, '/');
    }

    function get($path, $callback) {
        $path = explode('/', trim($path, '/'));
        $variations = $this->get_path_variations($path);
        foreach ($variations as $variation) {
            if (!$variation) {
                $variation[] = '';
            }
            $node = &$this->get_trie->insert($variation);
            $node->callback = $callback;
        }
    }

    function post($path, $callback) {
        $path = explode('/', trim($path, '/'));
        $variations = $this->get_path_variations($path);
        foreach ($variations as $variation) {
            if (!$variation) {
                $variation[] = '';
            }
            $node = $this->post_trie->insert($variation);
            $node->callback = $callback;
        }
    }

    function catchall($callback) {
        $this->catchall_callback = $callback;
    }

    function run() {
        $request_path = $this->get_request_path();
        $path_parts = explode('?', $request_path, 2);
        $path = explode('/', trim($path_parts[0], '/'));
        $query = array();
        if (isset($path_parts[1])) {
            parse_str($path_parts[1], $query);
        }
        $route_trie = $this->get_route_trie();

        $this->execute_matching_route($route_trie, $path, $query);
    }

    function get_route_trie() {
        switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            return $this->get_trie;
        case 'POST':
            return $this->post_trie;
        default:
            return new Trie();
        }
    }

    function execute_matching_route($trie, $path, $query) {
        $params = array();
        $node = $trie->search($path);
        $context = $this->get_context($path, $params, $query);

        if ($node && isset($node->callback)) {
            $callback = $node->callback;
            $callback($context);
        } elseif ($this->catchall_callback) {
            $callback = $this->catchall_callback;
            $callback($context);
        }
    }

    function get_context($path, $params, $query) {
        $request_path = $this->reconstruct_request_path($path);
        return array(
            'path' => $request_path,
            'params' => $params,
            'query' => $query,
        );
  }

    function reconstruct_request_path($path) {
        $path = join('/', $path);
        if (!$path || $path[0] !== '/') {
            return "/$path";
        }
        return $path;
    }

    function get_path_variations($path) {
        $optionals = array_keys(array_filter(
            $path,
            function($segment) {
                return substr($segment, -1) === '?';
            }
        ));
        $permutations = $this->get_permutations($optionals);

        $no_question = array_map(function($segment) {
            if (substr($segment, -1) === '?') {
                return substr($segment, 0, -1);
            } else {
                return $segment;
            }
        }, $path);

        $variations = array($no_question);
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

    function get_permutations($input) {
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

    function get_request_path() {
        $req_path = trim(urldecode($_SERVER['REQUEST_URI']), '/');
        if ($this->base_path && strpos($req_path, $this->base_path) === 0) {
            $req_path = trim(substr($req_path, strlen($this->base_path)), '/');
        }
        return $req_path;
    }
}


class Trie {

    function __construct() {
        $this->root = new Node();
    }

    function &insert($path) {
        return $this->_insert($this->root, $path, 0);
    }

    function &_insert(&$trie, $path, $index) {
        if (!isset($path[$index])) {
            return $trie;
        }
        $segment = $path[$index];

        if ($segment && $segment[0] === '*') {
            $node = &$this->_insert_wildcard_node($trie, $segment);
        } elseif ($segment && $segment[0] === ':') {
            $node = &$this->_insert_param_node($trie, $segment);
        } else {
            $node = &$this->_insert_static_node($trie, $segment);
        }
        return $this->_insert($node, $path, $index + 1);
    }

    function &_insert_wildcard_node(&$trie, $segment) {
        if (!$trie->wildcard_node) {
            $name = $segment ? substr($segment, 1) : 'wildcard';
            $trie->wildcard_node = new Node();
            $trie->wildcard_name = $name;
        }
        return $trie->wildcard_node;
    }

    function &_insert_param_node(&$trie, $segment) {
        if (!$trie->param_node) {
            $name = substr($segment, 1);
            $trie->param_node = new Node();
            $trie->param_name = $name;
        }
        return $trie->param_node;
    }

    function &_insert_static_node(&$trie, $segment) {
        if (!isset($trie->static_nodes[$segment])) {
            $trie->static_nodes[$segment] = new Node();
        }
        return $trie->static_nodes[$segment];
    }

    function search($path) {
        $params = array();
        return $this->_search($this->root, $path, 0, $params);
    }

    function _search($trie, $path, $index, &$params) {
        if (!isset($path[$index])) {
            return $trie;
        }

        if (isset($trie->static_nodes[$path[$index]])) {
            return $this->_search_static_node($trie, $path, $index, $params);
        } elseif ($trie->param_node) {
            return $this->_search_param_node($trie, $path, $index, $params);
        } elseif ($trie->wildcard_node) {
            return $this->_search_wildcard_node($trie, $path, $index, $params);
        }
    }

    function _search_static_node($trie, $path, $index, &$params) {
        $node = $trie->static_nodes[$path[$index]];
        return $this->_search($node, $path, $index + 1, $params);
    }

    function _search_param_node($trie, $path, $index, &$params) {
        $params[$trie->param_name] = urldecode($path[$index]);
        $node = $trie->param_node;
        return $this->_search($node, $path, $index + 1, $params);
    }

    function _search_wildcard_node($trie, $path, $index, &$params) {
        $path = array_slice($path, $index);
        $path = array_map('urldecode', $path);
        $path = join('/', $path);
        $params[$trie->wildcard_name] = $path;
        return $trie->wildcard_node;
    }
}


class Node {

    function __construct() {
        $this->wildcard_node = NULL;
        $this->wildcard_name = '';
        $this->param_node = NULL;
        $this->param_name = '';
        $this->static_nodes = array();
    }
}
