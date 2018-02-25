<?php

use ePHP\Core\Config;

/**
 * 打印，调试方法
 *
 * 使用Chrome/Firefox JavaScript的 `console.info` 方法打印服务器端信息
 * 默认使用console.info打印信息
 * 使用方法：dump('当前变量', $your_vars1, $your_vars2)
 * 进阶：可使用dump('error', 错误信息)
 *
 * @param mixed $args
 * @return void
 */
function dump()
{
    // 关闭dump
    if (!Config::get('show_dump')) {
        return false;
    }

    $args         = func_get_args();
    $console_func = func_get_arg(0);

    if (count($args) > 1 && in_array($console_func, ['log', 'info', 'error'])) {
        array_shift($args);
    } else {
        $console_func = 'info';
    }

    echo '<script type="text/javascript">if(!!window.console) console.' . $console_func . '.apply(null, ' . json_encode($args, JSON_UNESCAPED_UNICODE) . ');</script>';
}

/**
 * 等同于dump();exit;
 *
 * @return void
 */
function dumpdie()
{
    call_user_func_array('dump', func_get_args());
    throw new \ePHP\Exception\ExitException();
}

/**
 * Throw a new Exception
 *
 * @param string $message
 * @param int $code
 */
function throw_error($message, $code = 0)
{
    $ex = Config::get('handler_exception');
    if ( empty($ex) ) {
        $ex = \ePHP\Exception\CommonException::class;
    }

    throw new $ex($message, $code);
}

/**
 * 系统错误处理函数，将系统错误，重定向到CommonException去处理
 *
 * @ignore
 * @return void
 *
 * @throws throw \ePHP\Exception\CommonException
 */
function error_handler($errno, $errstr, $errfile, $errline)
{
    $ex = Config::get('handler_exception');
    if ( empty($ex) ) {
        $ex = \ePHP\Exception\CommonException::class;
    }

    throw new $ex($errstr, $errno, array('errfile' => $errfile, 'errline' => $errline));
}

/**
 * 捕获Swoole异常退出错误
 *
 * @ignore
 * @return void
 *
 */
function shutdown_handler()
{
    $error = error_get_last();
    if (isset($error['type']))
    {
        switch ($error['type'])
        {
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
                $message = $error['message'];
                $file = $error['file'];
                $line = $error['line'];

                $str = "Fatal error:\n-----------------------------------\n" . $message . ' in ' . $file . '('. $line . ")\n-----------------------------------\n";
                wlog('ExceptionLog', $str);

                $str = '<pre>'. $str .'</pre>';

                if (SERVER_MODE !== 'swoole') {
                    echo $str;
                } else {
                    $GLOBALS['__$response']->end($str);
                }
                break;
        }
    }
}


// 捕获系统所有的异常
set_error_handler("error_handler");
register_shutdown_function("shutdown_handler");

// db query cout
// 记录数据库查询执行次数，这也是一个优化的手段
// 用在run_info方法中
$GLOBALS['__$DB_QUERY_COUNT'] = 0;

function run_info($verbose = false)
{
    dump('当前系统运行耗时:', number_format((microtime(1) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2, '.', ''), 'ms');
    if ($verbose) {
        dump('当前数据库查询次数:', $GLOBALS['__$DB_QUERY_COUNT']);
    }
}


/**
 * 写文件日志
 *
 * @param string $key 日志名称，自动加上{2010-09-22.log}的作为文件名
 * @param string $value
 * @return void
 */
function wlog($key, $value)
{
    $logger = Config::get('log_writer');

    if (!empty($logger)) {
        $logger->write($key, $value);
    } else {
        show_error('ERROR: please configure log_writer item in conf/main.php');
    }
}

/**
 * 显示404页面
 *
 * @return void
 */
function show_404()
{
    // header('HTTP/1.1 404 Not Found');
    $tpl = Config::get('tpl_404');
    if (!$tpl) {
        include __DIR__ . '/../Template/404.html';
    } else {
        include APP_PATH . '/views/' . $tpl;
    }

    throw new \ePHP\Exception\ExitException();
}

/**
 * 显示一个成功的界面，几秒后，跳转到上一个界面
 *
 * 例如：show_success("操作成功！")
 * show_error("操作成功", "/user/index", 3)
 *
 * @param string  $message 要显示的消息内容
 * @param string  $url     可选，要跳转的URL，如果省略则使用referer，跳转到上一个界面
 * @param int $wait        可选，自动跳转等待时间，默认6s
 * @return void
 */
function show_success($message, $url = '', $wait = 6)
{
    if ($url === '' && isset($_SERVER['HTTP_REFERER'])) {
        $url = $_SERVER['HTTP_REFERER'];
    }

    $tpl = Config::get('tpl_success');
    if (!$tpl) {
        include __DIR__ . '/../Template/200.html';
    } else {
        include APP_PATH . '/views/' . $tpl;
    }

    throw new \ePHP\Exception\ExitException();
}

/**
 * 显示一个错误信息，几秒后跳转到上一个界面
 *
 * 例如：show_error("抱歉，操作失败！")
 * show_error("抱歉，操作失败", "/user/index", 3)
 *
 * @param string  $message 要显示的消息内容
 * @param string  $url     可选，要跳转的URL，如果省略则使用referer，跳转到上一个界面
 * @param int     $wait    可选，自动跳转等待时间，默认6s
 * @return void
 */
function show_error($message, $url = '', $wait = 6)
{
    // header('HTTP/1.1 500 Internal Server Error');
    if ($url === '' && isset($_SERVER['HTTP_REFERER'])) {
        $url = $_SERVER['HTTP_REFERER'];
    }

    $tpl = Config::get('tpl_error');
    if (!$tpl) {
        include __DIR__ . '/../Template/500.html';
    } else {
        include APP_PATH . '/views/' . $tpl;
    }

    throw new \ePHP\Exception\ExitException();
}

/**
 * 浏览器跳转
 *
 * @param string  $url     要跳转的url
 * @param int     $wait    可选，跳转等待时间，默认0s
 * @param string  $message 可选，提示信息
 */
function R($url, $wait = 0, $message = '')
{
    // header("HTTP/1.1 301 Moved Permanently");
    if (empty($message)) {
        $message = "系统将在{$wait}秒之后自动跳转到{$url}！";
    }

    if (!headers_sent() && (0 === $wait)) {
        // redirect
        header("Content-Type:text/html; charset=UTF-8");
        header("Location: {$url}");
        throw new \ePHP\Exception\ExitException();
    } else {
        // html refresh
        // header("refresh:{$wait};url={$url}"); // 直接发送header头。
        include __DIR__ . '/../Template/302.html';
        throw new \ePHP\Exception\ExitException();
    }
}

/**
 * 获取$_GET中的值，不存在，返回default的值
 *
 * @param string $key      要获取的键名
 * @param string $default  可选，如果不存在返回的默认值，默认返回空字符串
 * @param string $callback 可选，回调函数，比如 intval, floatval
 * @return mixed
 */
function getv($key, $default = '', $callback = '')
{
    return !empty($_GET[$key]) ? (empty($callback) ? trim($_GET[$key]) : call_user_func($callback, trim($_GET[$key]))) : $default;
}

/**
 * 获取url中的片段
 *
 * 例如：url: /user/info/12.html, getp(3)的值为12
 *
 * @param int    $pos      获取url片段的位置($pos>=1)
 * @param string $default  可选，返回的默认值
 * @param string $callback 可选，回调函数，比如 intval, floatval
 * @return mixed
 */
function getp($pos, $default = '', $callback = '')
{
    static $url_part = array();
    if (empty($url_part) || SERVER_MODE === 'swoole') {
        // only first time
        $posi = strpos($_SERVER['PATH_INFO'], '?');
        $url  = $posi ? substr($_SERVER['PATH_INFO'], 1, $posi) : substr($_SERVER['PATH_INFO'], 1);
        if (!empty($url)) {
            $url_part = explode('/', $url);
        } else {
            $url_part = array('index', 'index');
        }
    }
    $pos = $pos - 1;
    return !empty($url_part[$pos]) ? (empty($callback) ? trim($url_part[$pos]) : call_user_func($callback, trim($url_part[$pos]))) : $default;
}

/**
 * 获取$_POST中的值
 *
 * @param string $key      要获取的键名
 * @param string $default  可选，如果不存在返回的默认值，默认返回空字符串
 * @param string $callback 可选，回调函数，比如 intval, floatval
 * @return mixed
 */
function postv($key, $default = '', $callback = '')
{
    return !empty($_POST[$key]) ? (empty($callback) ? trim($_POST[$key]) : call_user_func($callback, trim($_POST[$key]))) : $default;
}

/**
 * 获取$_REQUEST中的值
 *
 * @param string $key      要获取的键名
 * @param string $default  可选，如果不存在返回的默认值，默认返回空字符串
 * @param string $callback 可选，回调函数，比如 intval, floatval
 * @return mixed
 */
function requestv($key, $default = '', $callback = '')
{
    return !empty($_REQUEST[$key]) ? (empty($callback) ? trim($_REQUEST[$key]) : call_user_func($callback, trim($_REQUEST[$key]))) : $default;
}

/**
 * 获取配置信息
 *
 * @param  string $key
 * @param  string $config_name 配置项名称，如mian
 * @return mixed
 */
function C($key, $config_name = 'main')
{
    return \ePHP\Core\Config::get($key, $config_name);
}
