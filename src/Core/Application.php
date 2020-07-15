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
     * @return void
     * @noinspection PhpRedundantCatchClauseInspection
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

        // 捕获系统所有的异常
        set_error_handler("error_handler");

        // 注册中止时执行的函数
        // Swoole模式下必须在onRequest中单独调用，使用context无法得到response对象
        if (SERVER_MODE !== 'swoole') {
            register_shutdown_function("shutdown_handler");
        }

        try {
            $route = (Route::init())->findRoute();

            if (empty($route)) {
                \show_404();
            }

            $controller_name = $route[2];
            $action_name     = $route[1];

            if (SERVER_MODE != 'swoole') {
                $_GET['controller'] = $controller_name;
                $_GET['action']     = $action_name;
                $_REQUEST           = array_merge($_GET, $_POST);
            } else {
                \Swoole\Coroutine::getContext()['__$request']->get['controller'] = $controller_name;
                \Swoole\Coroutine::getContext()['__$request']->get['action']     = $action_name;
                \Swoole\Coroutine::getContext()['__$_REQUEST']                   = array_merge(\Swoole\Coroutine::getContext()['__$request']->get, \Swoole\Coroutine::getContext()['__$request']->post ?? []);
            }

            // Check action function is exist
            if (!method_exists($controller_name, $action_name)) {
                if (defined('RUN_ENV') && RUN_ENV == 'prod') {
                    \show_404();
                } else {
                    \show_error("method {$action_name}() is not defined in {$controller_name}");
                }
            }

            if (SERVER_MODE !== 'swoole') {
                call_user_func([new $controller_name(), $action_name]);
            } else {
                try {
                    // $c_init = new $controller_name();
                    // // $c_init->request = $request;
                    // // $c_init->response = $response;

                    // $c_init->{$action_name}();
                    call_user_func([new $controller_name(), $action_name]);
                } catch (\Swoole\ExitException $e) {
                    return; // 屏蔽exit异常，不输出任何信息
                }
            }
        } catch (\ePHP\Exception\CommonException $e) {
            // ExitException don't show error message
            if ($e->getCode() === -99) {
                return;
            }
            echo $e;
        }
    }
}
