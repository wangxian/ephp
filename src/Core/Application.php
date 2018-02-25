<?php

namespace ePHP\Core;

class Application
{
    public function __construct()
    {
    }

    /**
     * Start run the application
     *
     * @return null
     */
    function run()
    {
        // Set default error level, In dev env show all errors
        // ini_set('display_errors', Config::get('show_errors') ? 'Off' : 'Off');
        ini_set('display_errors', 'Off');
        error_reporting(E_ALL | E_STRICT);

        if (!defined('SERVER_MODE')) {
            // Mark server mode
            define('SERVER_MODE', 'fpm');
        }

        try {
            $route = (\ePHP\Core\Route::init())->findRoute();
            // dumpdie($route);

            if (!empty($route)) {
                $_GET['controller'] = $route[0];
                $_GET['action']     = $route[1];

                $controller_name = $route[2];
                $action_name     = $_GET['action'];

                $_REQUEST = array_merge($_GET, $_REQUEST);

                if (method_exists($controller_name, $action_name)) {
                    $c_init = new $controller_name();
                    // $c_init = new $controller_name($request, $response);
                    // if (SERVER_MODE === 'swoole') {
                    //     $c_init->request = $request;
                    //     $c_init->response = $response;
                    // }

                    // Under swoole server, can't use call_user_func
                    // call_user_func(array($c_init, $action_name));
                    $c_init->{$action_name}();
                } elseif (defined('RUN_ENV') && RUN_ENV == 'prod') {
                    \show_404();
                } else {
                    \show_error("method {$action_name}() is not defined in {$controller_name}");
                }
            } else {
                \show_404();
            }
        } catch (\ePHP\Exception\CommonException $e) {
            // ExitException don't show error message
            if ($e->getCode() !== -99) {
                echo $e;
            }
        }
    }
}
