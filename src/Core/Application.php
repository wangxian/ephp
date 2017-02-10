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
    function run($request=null, $response=null)
    {
        // 设置默认错误级别, 测试环境，尽量显示所有错误
        // ini_set('display_errors', Config::get('show_errors') ? 'Off' : 'Off');
        ini_set('display_errors', 'On');
        error_reporting(E_ALL | E_STRICT);

        if ( !defined('SERVER_MODE') )
        {
            // Mark server mode
            define('SERVER_MODE', 'fpm');
        }

        try
        {
            $route = (\ePHP\Core\Route::init())->findRoute();
            // dumpdie($route);

            if (!empty($route))
            {
                $_GET['controller'] = $route[0];
                $_GET['action']     = $route[1];

                $controller_name = $route[2];
                $action_name     = $_GET['action'];

                $_REQUEST = array_merge($_GET, $_REQUEST);

                if (method_exists($controller_name, $action_name))
                {
                    $c_init = new $controller_name;
                    if (SERVER_MODE === 'swoole')
                    {
                        $c_init->request = $request;
                        $c_init->response = $response;
                    }

                    // Under swoole server, can't use call_user_func
                    // call_user_func(array($c_init, $action_name));
                    $c_init->{$action_name}();
                }
                else if (!Config::get('show_errors'))
                {
                    \show_404();
                }
                else
                {
                    \show_error("method {$action_name}() is not defined in {$controller_name}");
                }
            }
            else
            {
                \show_404();
            }
        }
        catch (\ePHP\Exception\CommonException $e)
        {
            echo $e;
        }
    }
}
