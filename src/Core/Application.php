<?php

namespace ePHP\Core;

/**
 * 应用程序调度器
 */
class Application
{
    public function __construct()
    {
        $path_info = $this->_path_info();

        if (!empty($path_info))
        {
            $splits = explode('/', trim($path_info, '/'));
        }
        else
        {
            $splits = '';
        }

        $_GET['controller'] = isset($_GET['controller']) ? $_GET['controller'] : 'index';
        $_GET['action']     = isset($_GET['action']) ? $_GET['action'] : 'index';

        if (!empty($splits[0]))
        {
            // 在保证安全的前提下，进行兼容PATH_INFO和GET方式，如果是url混合方式，则以path_info为主
            $_GET['controller'] = $splits[0];
            $_GET['action']     = isset($splits[1]) ? $splits[1] : 'index';
        }

        $ucount = count($splits);
        for ($i = 2; $i < $ucount; $i += 2)
        {
            if (isset($splits[$i]) && isset($splits[$i + 1]))
            {
                $_GET[$splits[$i]] = $splits[$i + 1];
            }

        }

        if (is_array($_GET))
        {
            $_REQUEST = array_merge($_GET, $_REQUEST);
        }

    }

    /**
     * 获取PAHT_INFO，没有则返回空字符串
     *
     * @return  string $path_info
     */
    private function _path_info()
    {
        $path_info = '';
        if (!empty($_SERVER['PATH_INFO']))
        {
            $path_info = $_SERVER['PATH_INFO'];

            // 无目录的user-info-15.html
            $nodir = Config::get('url_type');
            if ($nodir == 'NODIR')
            {
                $path_info = str_replace('-', '/', $path_info);
            }

            // 是否开启了路由
            if (Config::get('url_router'))
            {
                // 获取url上的第一个参数，用于对象router中的路由规则；
                $first_param = substr($path_info, 1, strpos($path_info, '/', 1) - 1);

                // 开启路由重写钱，请确认router.php存在
                $config = include APP_PATH . '/conf/router.php';

                if (isset($config[$first_param]))
                {
                    foreach ($config[$first_param] as $v)
                    {
                        $count = 0; // 记录成功替换的个数

                        // 如果是NODIR方式的URL，正则要替换
                        if ($nodir === 'NODIR')
                        {
                            $v[0] = str_replace('-', '/', $v[0]);
                        }

                        $path_info = preg_replace($v[0], $v[1], $path_info, -1, $count);

                        // 只要匹配上一个，则停止匹配，故在router.php从上到下有优先权。
                        if ($count > 0)
                        {
                            break;
                        }

                    }
                }
            }

            // 去掉扩展名
            $html_url_subffix = Config::get('html_url_suffix');
            if ($html_url_subffix && TRUE == ($url_suffix_pos = strrpos($path_info, $html_url_subffix)))
            {
                $path_info = substr($path_info, 0, $url_suffix_pos);
            }

        }
        return $path_info;
    }

    /**
     * Application running
     *
     * @return void
     */
    function run()
    {
        // 设置默认错误级别, 测试环境，尽量显示所有错误
        // ini_set('display_errors', Config::get('show_errors') ? 'Off' : 'Off');
        ini_set('display_errors', 'On');
        error_reporting(E_ALL | E_STRICT);

        try
        {
            $controller_name = "\\App\\Controllers\\" . ucfirst($_GET['controller']) . 'Controller';
            $action_name     = $_GET['action'];

            if (method_exists($controller_name, $action_name))
            {
                $c_init = new $controller_name;
                call_user_func(array($c_init, $action_name));
            }
            else if (!Config::get('show_errors'))
            {
                show_404();
            }
            else
            {
                show_error("在控制器 <b>{$controller_name}</b> 中 <b>{$action_name}()</b> 未定义！ ");
            }

        }
        catch (\ePHP\Exception\CommonException $e)
        {
            echo $e;
        }

        // echo 'Application.php';
        // var_dump(microtime(1) - $_SERVER['REQUEST_TIME_FLOAT']);
        // 1/0;
        // dump($_SERVER);
        // dump((($sdir = dirname($_SERVER['SCRIPT_NAME'])) == '/' || $sdir == '\\') ? '' : $sdir);
        // run_info(1);

        // try
        // {
        //     // $x = 1 / 0;
        //     throw new CommonException("Application.php 发生了错误！");

        // }
        // catch (CommonException $e)
        // {
        //     echo $e;
        // }
        // \wlog("test", "Application.php");
        // run_info();
        // \show_404();
        // show_success("Application.php");
        // show_error("Application.php");
    }
}
