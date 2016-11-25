<?php

namespace ePHP\Core;

/**
 * 应用程序调度器
 */
class Application
{

    function run()
    {
        // 设置默认错误级别, 测试环境，尽量显示所有错误
        if (Config::get('show_error'))
        {
            ini_set('display_errors', 'On');
            error_reporting(E_ALL | E_STRICT);
        }
        else
        {
            ini_set('display_errors', 'Off');
            error_reporting(0);
        }

        // echo 'Application.php';
        // var_dump(microtime(1) - $_SERVER['REQUEST_TIME_FLOAT']);
        dump("SERVER内容", $_SERVER);
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
        show_error("Application.php");
    }
}
