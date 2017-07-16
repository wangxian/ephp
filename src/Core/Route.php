<?php
namespace ePHP\Core;

class Route
{
    /**
     * @var \ePHP\Core\Route
     */
    private static $instance;

    /**
     * Store all defined routers
     *
     * @var array
     */
    private $routes = [];

    /**
     * Prefix uri, use once
     *
     * @var string
     */
    private $prefixUri = '';

    /**
     * All of the verbs supported by the router.
     *
     * @var array
     */
    // public static $verbs = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /**
     * Dynamically handle calls to the class.
     *
     * @return \ePHP\Core\Route
     */
    public static function init()
    {
        if (!self::$instance instanceof Route) {
            return self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Base function, register routes
     *
     * @param string $method
     * @param string $uri
     * @param string $controller
     * @param string $action
     * @return null
     */
    private function addRoute($method, $uri, $controller, $action)
    {
        // Prefix route uri, concat it and clear
        if (strlen($this->prefixUri) > 0) {
            $uri             = $this->prefixUri . $uri;
            $this->prefixUri = '';
        }

        // Compatible: has no namespace
        if (strrpos($controller, '\\', 1) === false) {
            $controller = 'App\\Controllers\\' . $controller;
        }

        $items = $uri ? explode('/', ltrim($uri, '/')) : [];

        $this->routes[] = [
            'method'     => $method,
            'count'      => count($items),
            'controller' => $controller,
            'action'     => $action,
            'params'     => $items,
        ];
    }

    /**
     * Find the matching route based on the current PATH_INFO
     *
     * @return array
     */
    public function findRoute()
    {
        $pathinfo = !empty($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO'] != '/' ? $_SERVER['PATH_INFO'] : '/index/index';
        $items    = $pathinfo ? explode('/', ltrim($pathinfo, '/')) : [];
        $count    = count($items);

        foreach ($this->routes as $value) {
            if ($count === $value['count'] && !is_null($value['action'])
                && ($value['method'] === 'ALL' || $_SERVER['REQUEST_METHOD'] === $value['method'])
            ) {
                // match RESTful uri
                $f        = false;
                $url_args = [];
                foreach ($items as $k => $v) {
                    $slug = $value['params'][$k];

                    $origin_key_pos = strpos($slug, '(');
                    if ($origin_key_pos !== false) {
                        // match :id(\d+)
                        $preg = substr($slug, $origin_key_pos + 1, -1);
                        if (preg_match('/^' . $preg . '$/', $v) > 0) {
                            $f               = true;
                            $slug            = substr($slug, 1, $origin_key_pos - 1);
                            $url_args[$slug] = $v;
                            continue;
                        }
                    } elseif (substr($slug, 0, 1) === ':') {
                        // match :id
                        $f               = true;
                        $slug            = substr($slug, 1);
                        $url_args[$slug] = $v;
                        continue;
                    }

                    if ($v === $slug) {
                        $f = true;
                    } else {
                        $f = false;
                        continue;
                    }
                }

                if ($f) {
                    if (count($url_args) > 0) {
                        $_GET = array_merge($_GET, $url_args);
                    }

                    return [strtolower(substr($value['controller'], strrpos($value['controller'], '\\') + 1, -10)), $value['action'], $value['controller']];
                }
            } elseif (is_null($value['action'])) {
                // match auto route
                $controller_name = strtolower(substr($value['controller'], strrpos($value['controller'], '\\') + 1, -10));

                if ($value['count'] === 0 && $items[0] === $controller_name) {
                    // has not prefixUri
                    return [$controller_name, isset($items[1])?$items[1]:'index', $value['controller']];
                } elseif ($value['count'] > 0) {
                    // has prefixUri
                    $prefix = '/' . implode($value['params'], '/');
                    // check has same prefix uri
                    if (substr($pathinfo, 0, strlen($prefix)) === $prefix) {
                        $items = $pathinfo ? explode('/', substr($pathinfo, strlen($prefix)+1)) : [];
                        // not contains prefix uri, and it must match controller name
                        if ($items[0] === $controller_name) {
                            return [$controller_name, empty($items[1])?'index':$items[1], $value['controller']];
                        }
                    }
                }
            }
        }
    }

    /**
     * Register a new GET route with the router.
     *
     * @param  string $uri
     * @param  string $controller
     * @param  string $action
     * @return null
     */
    public function get($uri, $controller, $action)
    {
        $this->addRoute('GET', $uri, $controller, $action);
    }

    /**
     * Register a new POST route with the router.
     *
     * @param  string $uri
     * @param  string $controller
     * @param  string $action
     * @return null
     */
    public function post($uri, $controller, $action)
    {
        $this->addRoute('POST', $uri, $controller, $action);
    }

    /**
     * Register a new PUT route with the router.
     *
     * @param  string $uri
     * @param  string $controller
     * @param  string $action
     * @return null
     */
    public function put($uri, $controller, $action)
    {
        $this->addRoute('PUT', $uri, $controller, $action);
    }

    /**
     * Register a new DELETE route with the router.
     *
     * @param  string $uri
     * @param  string $controller
     * @param  string $action
     * @return null
     */
    public function delete($uri, $controller, $action)
    {
        $this->addRoute('DELETE', $uri, $controller, $action);
    }

    /**
     * Register a new HEAD route with the router.
     *
     * @param  string $uri
     * @param  string $controller
     * @param  string $action
     * @return null
     */
    public function head($uri, $controller, $action)
    {
        $this->addRoute('HEAD', $uri, $controller, $action);
    }

    /**
     * Register a new OPTIONS route with the router.
     *
     * @param  string $uri
     * @param  string $controller
     * @param  string $action
     * @return null
     */
    public function options($uri, $controller, $action)
    {
        $this->addRoute('OPTIONS', $uri, $controller, $action);
    }

    /**
     * Register a new PATCH route with the router.
     *
     * @param  string $uri
     * @param  string $controller
     * @param  string $action
     * @return null
     */
    public function patch($uri, $controller, $action)
    {
        $this->addRoute('PATCH', $uri, $controller, $action);
    }

    /**
     * Register a new router with any http METHOD
     *
     * @param  string $uri
     * @param  string $controller
     * @param  string $action
     * @return null
     */
    public function all($uri, $controller, $action)
    {
        $this->addRoute('ALL', $uri, $controller, $action);
    }

    /**
     * Register a auto router
     *
     * @param  string $controller
     * @return null
     */
    public function auto($controller)
    {
        $this->addRoute('ALL', null, $controller, null);
    }

    /**
     * Set prefix uri router
     *
     * @param  string $prefix_uri like /api
     * @return \ePHP\Core\Route
     */
    public function prefix($uri)
    {
        $this->prefixUri = $uri;
        return $this;
    }
}
