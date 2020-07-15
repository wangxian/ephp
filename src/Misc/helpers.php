<?php /** @noinspection SpellCheckingInspection */

use ePHP\Core\Config;

/**
 * 使用浏览器console打印，调试方法
 *
 * 使用Chrome/Firefox JavaScript的 `console.info` 方法打印服务器端信息
 * 默认使用console.info打印信息
 *
 * @return void
 * @noinspection BadExpressionStatementJS
 * @noinspection JSUnnecessarySemicolon
 */
function cc()
{
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
 * console.log and die
 *
 * @return void
 * @throws \ePHP\Exception\ExitException
 */
function ccc()
{
    call_user_func_array('cc', func_get_args());
    throw new \ePHP\Exception\ExitException();
}

/**
 * dump variable
 *
 * @return void
 */
function dd()
{
    if (!Config::get('show_dump')) {
        return;
    }

    $data = func_get_args();
    ob_start();
    foreach ($data as $v) {
        var_dump($v);
    }
    $output = ob_get_clean();
    $output = preg_replace('/]=>\n(\s+)/m', "] => ", $output);

    echo str_replace('&lt;?php', '', highlight_string("<?php\n" . $output, true));
}

/**
 * dump variable and die
 *
 * @return void
 * @throws \ePHP\Exception\ExitException
 */
function ddd()
{
    call_user_func_array('dd', func_get_args());
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
    if (empty($ex)) {
        $ex = \ePHP\Exception\CommonException::class;
    }

    throw new $ex($message, $code);
}

/**
 * 系统错误处理函数，将系统错误，重定向到CommonException去处理
 *
 * @param $errno
 * @param $errstr
 * @param $errfile
 * @param $errline
 * @return void
 *
 * @ignore
 */
function error_handler($errno, $errstr, $errfile, $errline)
{
    $ex = Config::get('handler_exception');
    if (empty($ex)) {
        $ex = \ePHP\Exception\CommonException::class;
    }

    throw new $ex($errstr, $errno, array('errfile' => $errfile, 'errline' => $errline));
}

/**
 * 捕获Swoole异常退出错误
 *
 * @ignore
 * @param \Swoole\Http\Response $swooleResponse
 * @return void
 * @throws \ePHP\Exception\ExitException
 */
function shutdown_handler($swooleResponse = null)
{
    $error = error_get_last();

    if (isset($error['type'])) {
        switch ($error['type']) {
            case E_ERROR:
            case E_PARSE:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
                $message = $error['message'];
                $file    = $error['file'];
                $line    = $error['line'];

                $str = "Fatal error:\n-----------------------------------\n" . $message . ' in ' . $file . '(' . $line . ")\n-----------------------------------\n";
                wlog('ExceptionLog', $str);

                $str = '<pre>' . $str . '</pre>';
                if (SERVER_MODE !== 'swoole') {
                    echo $str;
                } else {
                    $swooleResponse->end($str);
                }
                break;
        }
    }
}

// 记录数据库查询执行次数，这也是一个优化的手段
// 用在run_info方法中
\Swoole\Coroutine::getContext()['__$DB_QUERY_COUNT'] = 0;

function run_info($verbose = false)
{
    cc('当前系统运行耗时:', number_format((microtime(1) - serverv('REQUEST_TIME_FLOAT')) * 1000, 2, '.', ''), 'ms');
    if ($verbose) {
        cc('当前数据库查询次数:', \Swoole\Coroutine::getContext()['__$DB_QUERY_COUNT']);
    }
}


/**
 * 写文件日志
 *
 * @param string $key 日志名称，自动加上{2010-09-22.log}的作为文件名
 * @param string $value
 * @return void
 * @throws \ePHP\Exception\ExitException
 */
function wlog($key, $value)
{
    $logger = Config::get('log_writer');

    if (empty($logger)) {
        show_error('ERROR: please configure log_writer item in conf/main.php');
    }

    $logger->write($key, $value);
}

/**
 * 显示404页面
 *
 * @return void
 * @throws \ePHP\Exception\ExitException
 * @noinspection PhpIncludeInspection
 * @noinspection PhpUndefinedConstantInspection
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
 * @param string $message 要显示的消息内容
 * @param string $url 可选，要跳转的URL，如果省略则使用referer，跳转到上一个界面
 * @param int $wait 可选，自动跳转等待时间，默认6s
 * @return void
 * @throws \ePHP\Exception\ExitException
 */
function show_success($message, $url = '', $wait = 6)
{
    if ($url === '' && serverv('HTTP_HOST')) {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $url = serverv('HTTP_REFERER');
    }

    $tpl = Config::get('tpl_success');
    if (!$tpl) {
        include __DIR__ . '/../Template/200.html';
    } else {
        /** @noinspection PhpUndefinedConstantInspection */
        /** @noinspection PhpIncludeInspection */
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
 * @param string $message 要显示的消息内容
 * @param string $url 可选，要跳转的URL，如果省略则使用referer，跳转到上一个界面
 * @param int $wait 可选，自动跳转等待时间，默认6s
 * @return void
 * @throws \ePHP\Exception\ExitException
 */
function show_error($message, $url = '', $wait = 6)
{
    // header('HTTP/1.1 500 Internal Server Error');
    if ($url === '' && serverv('HTTP_REFERER')) {
        /** @noinspection PhpUnusedLocalVariableInspection */
        $url = serverv('HTTP_REFERER');
    }

    $tpl = Config::get('tpl_error');
    if (!$tpl) {
        include __DIR__ . '/../Template/500.html';
    } else {
        /** @noinspection PhpUndefinedConstantInspection */
        /** @noinspection PhpIncludeInspection */
        include APP_PATH . '/views/' . $tpl;
    }

    throw new \ePHP\Exception\ExitException();
}

/**
 * 浏览器跳转
 *
 * @param string $url 要跳转的url
 * @param int $wait 可选，跳转等待时间，默认0s
 * @param string $message 可选，提示信息
 * @throws \ePHP\Exception\ExitException
 */
function R($url, $wait = 0, $message = '')
{
    // header("HTTP/1.1 301 Moved Permanently");
    if (empty($message)) {
        /** @noinspection PhpUnusedLocalVariableInspection */
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
 * Get $_GET value
 *
 * @param string $key 要获取的键名
 * @param mixed $default 可选，如果不存在返回的默认值，默认返回空字符串
 * @param string $callback 可选，回调函数，比如 trim, intval, floatval 默认trim
 * @return mixed
 */
function getv($key = '', $default = '', $callback = 'trim')
{
    $getValue = SERVER_MODE != 'swoole' ? $_GET : (\Swoole\Coroutine::getContext()['__$request']->get ?? []);

    // Get all values
    if (!$key) {
        return $getValue;
    }

    return isset($getValue[$key]) ? call_user_func($callback, $getValue[$key]) : $default;
}

/**
 * 获取url中的片段
 * 例如：url: /user/info/12.html, getp(3)的值为12
 *
 * @param int $pos 获取url片段的位置($pos>=1)
 * @param mixed $default 可选，返回的默认值
 * @param string $callback 可选，回调函数，比如 trim, intval, floatval 默认trim
 * @return mixed
 */
function getp($pos, $default = '', $callback = 'trim')
{
    static $url_part = [];
    if (empty($url_part) || SERVER_MODE === 'swoole') {
        // only first time
        $pos = strpos(serverv('PATH_INFO'), '?');
        $url = $pos ? substr(serverv('PATH_INFO'), 1, $pos) : substr(serverv('PATH_INFO'), 1);
        if (!empty($url)) {
            $url_part = explode('/', $url);
        } else {
            $url_part = ['index', 'index'];
        }
    }
    $pos = $pos - 1;
    return isset($url_part[$pos]) ? call_user_func($callback, $url_part[$pos]) : $default;
}

/**
 * Get post value
 *
 * @param string $key 要获取的键名
 * @param mixed $default 可选，如果不存在返回的默认值，默认返回空字符串
 * @param string $callback 可选，回调函数，比如 trim, intval, floatval 默认trim
 * @return mixed
 */
function postv($key = '', $default = '', $callback = 'trim')
{
    $postValue = SERVER_MODE != 'swoole' ? $_POST : (\Swoole\Coroutine::getContext()['__$request']->post ?? []);

    // Get all values
    if (!$key) {
        return $postValue;
    }

    return isset($postValue[$key]) ? call_user_func($callback, $postValue[$key]) : $default;
}

/**
 * 获取 $_REQUEST 中的值
 *
 * @param string $key 要获取的键名
 * @param mixed $default 可选，如果不存在返回的默认值，默认返回空字符串
 * @param string $callback 可选，回调函数，比如 trim, intval, floatval 默认trim
 * @return mixed
 */
function requestv($key = '', $default = '', $callback = 'trim')
{
    $requestValue = SERVER_MODE != 'swoole' ? $_REQUEST : (\Swoole\Coroutine::getContext()['__$_REQUEST'] ?? []);

    // Get all values
    if (!$key) {
        return $requestValue;
    }

    return isset($requestValue[$key]) ? call_user_func($callback, $requestValue[$key]) : $default;
}

/**
 * Get $_SERVER value
 *
 * @param string $key
 * @param string $default
 * @return array|mixed|string
 */
function serverv($key = '', $default = '')
{
    $serverValue = SERVER_MODE != 'swoole' ? $_SERVER : (\Swoole\Coroutine::getContext()['__$_SERVER'] ?? []);

    // Get all values
    if (!$key) {
        return $serverValue;
    }

    return isset($serverValue[$key]) ? $serverValue[$key] : $default;
}

/**
 * Get $_FILES value
 *
 * @param string $key
 * @param array $default
 * @return array|mixed|string
 */
function filesv($key = '', $default = [])
{
    $filesValue = SERVER_MODE != 'swoole' ? $_FILES : (\Swoole\Coroutine::getContext()['__$request']->files ?? []);

    // Get all values
    if (!$key) {
        return $filesValue;
    }

    return isset($filesValue[$key]) ? $filesValue[$key] : $default;
}

/**
 * Get cookie value
 *
 * @param $key
 * @param string $default
 * @return bool|mixed|string
 */
function cookiev($key, $default = '')
{
    if (SERVER_MODE != 'swoole') {
        $val = (new \ePHP\Http\Cookie())->get($key);
    } else {
        $val = (new \ePHP\Http\CookieSwoole())->get($key);
    }

    return $val !== false ? $val : $default;
}

/**
 * Get session value
 * Notice: swoole not support session
 *
 * @param $key
 * @param string $default
 * @return bool|mixed|string
 */
function sessionv($key, $default = '')
{
    $session_name = Config::get('session_name');
    if (!$session_name) {
        $session_name = 'pppid';
    }

    $val = (new \ePHP\Http\Session($session_name))->get($key);
    return $val !== false ? $val : $default;
}

/**
 * Gets the value of the environment variable
 *
 * @param string $key
 * @param mixed $default
 * @return mixed
 */
function env($key, $default = '')
{
    $val = getenv($key);
    return $val !== false ? $val : $default;
}
