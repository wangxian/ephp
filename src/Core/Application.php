<?php

namespace ePHP\Core;

class Application
{
    // public function __construct()
    // {
    // }

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

            if (empty($route)) {
                \show_404();
            }

            // 整理参数
            $_GET['controller'] = $route[0];
            $_GET['action']     = $route[1];

            $controller_name = $route[2];
            $action_name     = $_GET['action'];

            $_REQUEST  = array_merge($_COOKIE, $_GET, $_POST);

            // 检查ACTION是否存在
            if ( !method_exists($controller_name, $action_name) ) {
                if (defined('RUN_ENV') && RUN_ENV == 'prod') {
                    \show_404();
                } else {
                    \show_error("method {$action_name}() is not defined in {$controller_name}");
                }
            }

            if (SERVER_MODE === 'fpm') {
                call_user_func([new $controller_name(), $action_name]);
            } else if (SERVER_MODE === 'swoole') {
                try {
                    // $c_init = new $controller_name();
                    // // $c_init->request = $request;
                    // // $c_init->response = $response;

                    // $c_init->{$action_name}();
                    call_user_func([new $controller_name(), $action_name]);
                } catch (\Swoole\ExitException $e) {
                    // 屏蔽exit异常，不输出任何信息
                    return ;
                }
            }
        } catch (\ePHP\Exception\CommonException $e) {
            // ExitException don't show error message
            if ($e->getCode() === -99) {
                return ;
            }
            echo $e;
        }
    }
}
