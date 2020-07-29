<?php /** @noinspection PhpUnused */

namespace ePHP\Core;

class Route
{
    /**
     * @var Route
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
     * @return Route
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
     * @return array contains [$controller_name, $action_name, $controller_class]
     */
    public function findRoute()
    {
        // Default path is /index/index
        $path_info = serverv('PATH_INFO', '/');
        if ($path_info == '' || $path_info == '/') {
            $path_info = '/index/index';
        }

        $items     = $path_info ? explode('/', ltrim($path_info, '/')) : [];
        $count     = count($items);

        foreach ($this->routes as $value) {
            if ($count === $value['count'] && !is_null($value['action'])
                && ($value['method'] === 'ALL' || serverv('REQUEST_METHOD') === $value['method'])
            ) {
                // match full uri
                if ($path_info === '/' . implode('/', $value['params'])) {
                    return [strtolower(substr($value['controller'], strrpos($value['controller'], '\\') + 1, -10)), $value['action'], $value['controller']];
                }

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
                }

                // Match uri success
                if ($f) {
                    if (count($url_args) > 0) {
                        $getValue = array_merge(getv(), $url_args);
                        if (SERVER_MODE != 'swoole') {
                            $_GET = $getValue;
                        } else {
                            /** @noinspection PhpSingleStatementWithBracesInspection */
                            /** @noinspection PhpFullyQualifiedNameUsageInspection */
                            \Swoole\Coroutine::getContext()['__$request']->get = $getValue;
                        }
                    }

                    return [strtolower(substr($value['controller'], strrpos($value['controller'], '\\') + 1, -10)), $value['action'], $value['controller']];
                }
            } elseif (is_null($value['action'])) {
                // match auto route
                $controller_name = strtolower(substr($value['controller'], strrpos($value['controller'], '\\') + 1, -10));

                if ($value['count'] === 0 && $items[0] === $controller_name) {
                    // has not prefixUri
                    return [$controller_name, isset($items[1]) ? $items[1] : 'index', $value['controller']];
                } elseif ($value['count'] > 0) {
                    // has prefixUri
                    $prefix = '/' . implode('/', $value['params']);
                    // check has same prefix uri
                    if (substr($path_info, 0, strlen($prefix)) === $prefix) {
                        $items = $path_info ? explode('/', substr($path_info, strlen($prefix) + 1)) : [];
                        // not contains prefix uri, and it must match controller name
                        if ($items[0] === $controller_name) {
                            return [$controller_name, empty($items[1]) ? 'index' : $items[1], $value['controller']];
                        }
                    }
                }
            }
        }

        return [];
    }

    /**
     * Find One websocket by controller handler name
     *
     * @return string $controller_class
     */
    public function findWebSocketRoute()
    {
        $path_info = serverv('PATH_INFO', '/index');

        foreach ($this->routes as $value) {
            if ($value['method'] == 'WEBSOCKET') {
                $route_uri = '/' . implode('/', $value['params']);

                if ($path_info === $route_uri) {
                    return $value['controller'];
                }
            }
        }

        return '';
    }

    /**
     * Register a new GET route with the router.
     *
     * @param string $uri
     * @param string $controller
     * @param string $action
     */
    public function get(string $uri, string $controller, string $action)
    {
        $this->addRoute('GET', $uri, $controller, $action);
    }

    /**
     * Register a new POST route with the router.
     *
     * @param string $uri
     * @param string $controller
     * @param string $action
     */
    public function post(string $uri, string $controller, string $action)
    {
        $this->addRoute('POST', $uri, $controller, $action);
    }

    /**
     * Register a new PUT route with the router.
     *
     * @param string $uri
     * @param string $controller
     * @param string $action
     */
    public function put(string $uri, string $controller, string $action)
    {
        $this->addRoute('PUT', $uri, $controller, $action);
    }

    /**
     * Register a new DELETE route with the router.
     *
     * @param string $uri
     * @param string $controller
     * @param string $action
     */
    public function delete(string $uri, string $controller, string $action)
    {
        $this->addRoute('DELETE', $uri, $controller, $action);
    }

    /**
     * Register a new HEAD route with the router.
     *
     * @param string $uri
     * @param string $controller
     * @param string $action
     */
    public function head(string $uri, string $controller, string $action)
    {
        $this->addRoute('HEAD', $uri, $controller, $action);
    }

    /**
     * Register a new OPTIONS route with the router.
     *
     * @param string $uri
     * @param string $controller
     * @param string $action
     */
    public function options(string $uri, string $controller, string $action)
    {
        $this->addRoute('OPTIONS', $uri, $controller, $action);
    }

    /**
     * Register a new PATCH route with the router.
     *
     * @param string $uri
     * @param string $controller
     * @param string $action
     */
    public function patch(string $uri, string $controller, string $action)
    {
        $this->addRoute('PATCH', $uri, $controller, $action);
    }

    /**
     * Register a new `WebSocket` route with the router.
     *
     * @param string $uri
     * @param string $controller
     */
    public function websocket(string $uri, string $controller)
    {
        $this->addRoute('WEBSOCKET', $uri, $controller, null);
    }

    /**
     * Register a new router with any http METHOD
     *
     * @param string $uri
     * @param string $controller
     * @param string $action
     */
    public function all(string $uri, string $controller, string $action)
    {
        $this->addRoute('ALL', $uri, $controller, $action);
    }

    /**
     * Register a auto router
     *
     * @param string $controller
     */
    public function auto(string $controller)
    {
        $this->addRoute('ALL', null, $controller, null);
    }

    /**
     * Set prefix uri router
     *
     * @param string $uri
     * @return Route
     */
    public function prefix(string $uri)
    {
        $this->prefixUri = $uri;
        return $this;
    }
}
