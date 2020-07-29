<?php
/** @noinspection PhpUnused */
/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection PhpUndefinedConstantInspection */

namespace ePHP\Core;

use \Swoole\Http\Request;
use \Swoole\Http\Response;

class Server
{
    /**
     * ePHP latest version
     * @var string
     */
    private $version = '7.3.0';

    // /**
    //  * Static file content type
    //  *
    //  * @var array
    //  */
    // private $contentType = [
    //     'text' => 'text/plain',
    //     'html' => 'text/html',
    //     'css'  => 'text/css',
    //     'js'   => 'text/javascript',
    //
    //     'png'  => 'image/png',
    //     'jpg'  => 'image/jpeg',
    //     'jpeg' => 'image/jpeg',
    //     'gif'  => 'image/gif',
    //     'ico'  => 'image/x-icon',
    //
    //
    //     'ttf'   => 'font/ttf',
    //     'eot'   => 'font/eot',
    //     'otf'   => 'font/otf',
    //     'woff'  => 'font/woff',
    //     'woff2' => 'font/woff2'
    // ];

    /**
     * Handle of Swoole http server
     *
     * @var \Swoole\Http\Server
     */
    public $server;

    /**
     * @var Server
     */
    private static $instance;

    /**
     * Swoole server config setting
     *
     * @var array
     */
    public $config = [
        'host'            => '0.0.0.0',
        'port'            => '8000',
        'task_worker_num' => 0
    ];

    /**
     * Dynamically handle calls to the class.
     *
     * @return Server
     */
    public static function init()
    {
        if (!self::$instance instanceof self) {
            return self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Print server finger description
     *
     * @return void
     */
    private function printServerFinger()
    {
        $version       = $this->version;
        $software      = SERVER_MODE == 'swoole' ? 'Swoole Server' : 'PHP Development Server';
        $document_root = APP_PATH . '/public';

        echo <<<EOT
-----------------------------------\e[32m
      ____    __  __  ____
     /\  _`\ /\ \/\ \/\  _`\
   __\ \ \ \ \ \ \_\ \ \ \ \ \
 /'__`\ \ ,__/\ \  _  \ \ ,__/
/\  __/\ \ \   \ \ \ \ \ \ \
\ \____ \\ \_\   \ \_\ \_\ \_\
 \/____/ \/_/    \/_/\/_/\/_/ \e[1;43;30mv{$version}\e[0m
 \e[0m
>>> \e[1;43;30m{$software}\e[0m started ...
Listening on \e[36;4mhttp://{$this->config['host']}:{$this->config['port']}/\e[0m
Document root is \e[34m{$document_root}\e[0m
Press Ctrl-C to quit.
-----------------------------------

EOT;

        echo "\e[32m>>> Http Server is enabled\e[0m \n";
        if (!empty($this->config['enable_websocket'])) {
            echo "\e[32m>>> WebSocket Server is enabled\e[0m \n";
        }
        echo "-----------------------------------\n";
    }

    /**
     * 打印访问日志
     * @param Request $request
     */
    private function printAccessLog(Request $request)
    {
        // 非STDOUT_LOG模式，不打印
        if (getenv('STDOUT_LOG')) {
            // 不显示上传的文件内容
            $post_data = http_build_query(postv());
            if (!$post_data) {
                $post_data = '-';
            }

            echo  "\e[1m[ACCESS_LOG] \e[0m | \e[35m" . (new \DateTime())->format('Y-m-d H:i:s.u') . "\e[0m | \e[1;33m" . serverv('REMOTE_ADDR') . "\e[0m"
                . " | \e[1;46m " . serverv('REQUEST_METHOD') . " \e[0m \e[4;30m" . serverv('REQUEST_URI') . "\e[0m"
                . ' | ' . $post_data . " |\e[1;30m fd=" . $request->fd . "\e[0m"
                . " | \e[1;36m" . number_format((microtime(true) - serverv('REQUEST_TIME_FLOAT', 0)) * 1000, 2) . "ms\e[0m\n";

            // echo "\tSERVER=" . json_encode(serverv()) . "\n";
        }
    }

    /**
     * Start a PHP Development Server
     *
     * @param string $host
     * @param int $port
     * @noinspection SpellCheckingInspection
     */
    public function devServer(string $host, int $port)
    {
        // Mark server mode
        define('SERVER_MODE', 'buildin');

        $this->config['host'] = $host;
        $this->config['port'] = $port;

        $this->printServerFinger();

        // Start cli Development Server
        $bind_http = $host . ':' . $port;
        passthru("php -S {$bind_http} -t " . APP_PATH . "/public");
    }

    /**
     * Create a swoole server
     *
     * @param array $config server config
     * @return Server
     */
    public function createServer(array $config)
    {
        // Mark server mode
        define('SERVER_MODE', 'swoole');

        $this->config = $config + [
                'host'             => '0.0.0.0',
                'port'             => '8000',
                'task_worker_num'  => 0,
                'enable_websocket' => false
            ];

        // Start websocket or http server
        if (empty($config['enable_websocket'])) {
            $this->server = new \Swoole\Http\Server($this->config['host'], $this->config['port']);
        } else {
            $this->config['open_http_protocol'] = true;
            // 参考文档 https://wiki.swoole.com/#/server/methods
            // 这里推荐使用SWOOLE_PROCESS模式，当worker挂掉时，fd连接并不会一起断掉，manger会重新创建worker且保持fd不变
            // 因为fd是由reactor进行管理的，这样不会影响websocketFrameContext缓存的映射关系，也不会影响应用中全局 如共享table数据，因为fd并没有变化
            $this->server = new \Swoole\WebSocket\Server($this->config['host'], $this->config['port'], SWOOLE_PROCESS, SWOOLE_SOCK_TCP);
        }

        $this->server->set($this->config);

        // Show welcome finger
        $this->printServerFinger();

        return $this;
    }

    /**
     * Trigger user event Of \App\Boot
     *
     * @param string $event
     * @return void
     */
    private function trigger_user_event(string $event)
    {
        // Automatically instantiate this class
        if (class_exists("\App\Boot")) {
            // Execute a boot instance
            /** @noinspection PhpUndefinedNamespaceInspection */
            /** @noinspection PhpUndefinedClassInspection */
            $boot = new \App\Boot();

            if (method_exists($boot, $event)) {
                // Args
                $args = func_get_args();
                array_shift($args);

                call_user_func_array([$boot, $event], $args);
            }
        }
    }

    /**
     * Start swoole server
     */
    public function start()
    {
        // Linsten http Event
        $this->server->on('request', [$this, 'onRequest']);

        $this->server->on('start', [$this, 'onStart']);
        $this->server->on('shutdown', [$this, 'onShutdown']);

        $this->server->on('workerStart', [$this, 'onWorkerStart']);
        $this->server->on('workerStop', [$this, 'onWorkerStop']);
        $this->server->on('workerError', [$this, 'onWorkerError']);

        // listen websocket
        if (!empty($this->config['enable_websocket'])) {
            $this->server->on('open', [$this, 'onOpen']);
            $this->server->on('message', [$this, 'onMessage']);
        }

        $this->server->on('close', [$this, 'onConnect']);
        $this->server->on('close', [$this, 'onClose']);

        // Trigger tasks event
        $this->server->on('task', [$this, 'onTask']);
        $this->server->on('finish', [$this, 'onFinish']);

        // Add event Listener before boot
        $this->trigger_user_event("onBoot", $this->server);

        // Start a new http server
        $this->server->start();
    }

    /**
     * Compat fpm server
     *
     * @param Request $request
     * @return void
     */
    private function _compatFPM(Request $request)
    {
        // 保证get/post为数组
        if (empty($request->get)) {
            $request->get = [];
        }
        if (empty($request->post)) {
            $request->post = [];
        }

        // 注入全局变量
        \Swoole\Coroutine::getContext()['__$request'] = $request;

        // 兼容php-fpm的$_SERVER，存入context中，使用`serverv()`获取
        $serverValue = [];
        foreach ($request->server as $key => $value) {
            $key               = strtoupper($key);
            $serverValue[$key] = $value;

            // FIXED: swoole REQUEST_URI don't contains QUERY_STRING
            if ($key === 'REQUEST_URI' && isset($serverValue['QUERY_STRING'])) {
                $serverValue['REQUEST_URI'] .= '?' . $serverValue['QUERY_STRING'];
            }
        }

        // 兼容php-fpm的header传值
        foreach ($request->header as $key => $value) {
            $key = strtoupper(str_replace('-', '_', $key));
            if ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                $serverValue[$key] = $value;
            } else {
                $serverValue['HTTP_' . $key] = $value;
            }
        }

        \Swoole\Coroutine::getContext()['__$_SERVER'] = $serverValue;
    }

    /**
     * Listen http server onRequest
     *
     * @param Request $request
     * @param Response $response
     */
    public function onRequest(Request $request, Response $response)
    {
        // 捕获 Swoole Server 运行期致命错误
        register_shutdown_function("shutdown_handler", $response);

        // Compat fpm server
        $this->_compatFPM($request);

        // 注入上下文
        \Swoole\Coroutine::getContext()['__$response'] = $response;

        // Set server container
        $response->header('Server', 'ePHP/' . $this->version);

        // filename full path
        $filename = APP_PATH . '/public' . serverv('PATH_INFO');

        // !in_array($extname, ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico'])
        // Try files, otherwise route to app
        if (!is_file($filename)) {
            ob_start();
            (new Application())->run();
            $h = ob_get_clean();

            $response->end($h);
        } else {
            // $extname = substr($filename, strrpos($filename, '.') + 1);
            // if (isset($this->contentType[$extname])) {
            //     $response->header('Content-Type', $this->contentType[$extname]);
            // }
            $response->sendfile($filename);
        }

        // 非调试模式，打印访问日志
        $this->printAccessLog($request);
    }

    /**
     * On server started
     *
     * @param \Swoole\Server $server
     * @return void
     */
    public function onStart(\Swoole\Server $server)
    {
        // STDOUT_LOG 开启才显示日志
        if (getenv('STDOUT_LOG')) {
            echo (new \DateTime())->format('Y-m-d H:i:s.u') . " |\e[30;46m INFO \e[0m ...... http \e[45mmaster\e[0m process start[master_pid={$server->master_pid}] ...... \n";
            echo (new \DateTime())->format('Y-m-d H:i:s.u') . " |\e[30;46m INFO \e[0m ...... http \e[46mmanager\e[0m process start[manager_pid={$server->manager_pid}] ......\e[0m \n";
        }

        // Add event Listener
        $this->trigger_user_event('onStart', $server);
    }

    /**
     * On server shutdown
     *
     * @param \Swoole\Server $server
     * @return void
     */
    public function onShutdown(\Swoole\Server $server)
    {
        echo (new \DateTime())->format('Y-m-d H:i:s.u') . " |\e[30;46m INFO \e[0m \e[31m http server shutdown ......\e[0m \n";

        // Add event Listener
        $this->trigger_user_event('onShutdown', $server);
    }

    /**
     * On worker started
     *
     * @param \Swoole\Server $server
     * @param int $workerId
     * @return void
     */
    public function onWorkerStart(\Swoole\Server $server, int $workerId)
    {
        // STDOUT_LOG模式，不打印 worker stop 输出
        if (getenv('STDOUT_LOG')) {
            echo (new \DateTime())->format('Y-m-d H:i:s.u') . " |\e[30;46m INFO \e[0m \e[33m...... ". ($server->taskworker?'TASK':'HTTP') ." worker process start[id={$workerId} pid={$server->worker_pid}] ...... \e[0m\n";
        }

        // Add event Listener
        $this->trigger_user_event('onWorkerStart', $server, $workerId);
    }

    /**
     * On worker stop
     *
     * @param \Swoole\Server $server
     * @param int $workerId
     * @return void
     */
    public function onWorkerStop(\Swoole\Server $server, int $workerId)
    {
        // STDOUT_LOG模式，不打印 worker stop 输出
        if (getenv('STDOUT_LOG')) {
            echo (new \DateTime())->format('Y-m-d H:i:s.u') . " |\e[30;46m INFO \e[0m \e[35m...... ". ($server->taskworker?'TASK':'HTTP') ." worker process stop[id={$workerId} pid={$server->worker_pid}] ......\n";
        }

        // Add event Listener
        $this->trigger_user_event('onWorkerStop', $server, $workerId);
    }

    /**
     * On work error
     *
     * @param \Swoole\Server $server
     * @param int $workerId
     * @param int $worker_pid
     * @param int $exit_code
     * @param int $signal
     * @return void
     */
    public function onWorkerError(\Swoole\Server $server, int $workerId, int $worker_pid, int $exit_code, int $signal)
    {
        echo (new \DateTime())->format('Y-m-d H:i:s.u') . " |\e[37;41m ERROR \e[0m\e[31m http worker process error[id={$workerId} pid={$worker_pid}] ......\e[0m \n";

        // Add event Listener
        $this->trigger_user_event('onWorkerError', $server, $workerId, $worker_pid, $exit_code, $signal);
    }

    /**
     * 在 task 进程内被调用。worker 进程可以使用 task 函数向 task_worker 进程投递新的任务。
     * 当前的 Task 进程在调用 onTask 回调函数时会将进程状态切换为忙碌，这时将不再接收新的 Task，
     * 当 onTask 函数返回时会将进程状态切换为空闲然后继续接收新的 Task。
     *
     * v4.2.12 起如果开启了 task_enable_coroutine 第二个参数是 \Swoole\Server\Task
     *
     * https://wiki.swoole.com/#/server/events?id=ontask
     *
     * @param \Swoole\Server $server
     * @param \Swoole\Server\Task $task
     */
    public function onTask(\Swoole\Server $server, \Swoole\Server\Task $task)
    {
        // Add event Listener
        $this->trigger_user_event('onTask', $server, $task);
    }

    /**
     * 此回调函数在 worker 进程被调用，当 worker 进程投递的任务在 task 进程中完成时，
     * task 进程会通过 Swoole\Server->finish() 方法将任务处理的结果发送给 worker 进程。
     *
     * @param \Swoole\Server $server
     * @param int $task_id
     * @param string $data
     */
    public function onFinish(\Swoole\Server $server, int $task_id, string $data)
    {
        // Add event Listener
        $this->trigger_user_event('onFinish', $server, $task_id, $data);
    }

    /**
     * WebSocket frame context
     *
     * @var array
     */
    public static $websocketFrameContext = [];

    /**
     * WebSocket on open
     *
     * @param \Swoole\WebSocket\Server $server
     * @param Request $request
     * @return void
     */
    public function onOpen(\Swoole\WebSocket\Server $server, Request $request)
    {
        // 检查连接是否为有效的 WebSocket 客户端连接
        if (!$server->isEstablished($request->fd)) {
            $server->disconnect($request->fd);
            return;
        }

        // Compat fpm server
        $this->_compatFPM($request);

        // print_r(self::$websocketFrameContext);

        // filter websocket router class
        // route struct: [$controller_name, $controller_class]
        $controller_class = (Route::init())->findWebSocketRoute();
        if (!empty($controller_class)) {
            \Swoole\Coroutine::getContext()['controller_class'] = $controller_class;
            // Save websocket connection Context
            self::$websocketFrameContext[$request->fd] = [
                'get'              => $request->get ?? [],
                'cookie'           => $request->cookie ?? [],
                'controller_class' => $controller_class
            ];

            if (getenv('STDOUT_LOG')) {
                echo (new \DateTime())->format('Y-m-d H:i:s.u') . " |\e[30;46m INFO \e[0m\e[34m [websocket][onOpen]fd{$request->fd}, pid=" . getmypid() . ", uri={$request->server['request_uri']}, WebSocket has been CONNECTED...\e[0m\n";
                echo '>>> pid=' . getmypid() . ', fds=' . implode(',', array_keys(self::$websocketFrameContext))
                    . ', connections=' . count(self::$websocketFrameContext) . "\n";
                echo '>>> GET=' . json_encode(getv(), JSON_UNESCAPED_UNICODE) . "\n------\n";
            }

            call_user_func([new $controller_class(), 'onOpen'], $server, $request);
        } else {
            // 无效的路由，则关闭客户端连接
            $server->disconnect($request->fd);
        }
    }

    /**
     * WebSocket on message
     *
     * @param \Swoole\WebSocket\Server $server
     * @param $frame
     */
    public function onMessage(\Swoole\WebSocket\Server $server, \Swoole\WebSocket\Frame $frame)
    {
        // $server->push($frame->fd, "this is server");
        // print_r(self::$websocketFrameContext);
        if (empty(self::$websocketFrameContext[$frame->fd]) || !$server->isEstablished($frame->fd)) {
            if (getenv('STDOUT_LOG')) {
                echo (new \DateTime())->format('Y-m-d H:i:s.u') . " |\e[37;41m ERROR \e[0m\e[31m [onmessage]fd{$frame->fd}, WebSocket has been stoped before frame sending data\e[0m \n";
            }

            $server->disconnect($frame->fd);
            return;
        }

        // Get websocket connection context
        $context = self::$websocketFrameContext[$frame->fd];

        // Restore context data
        if (empty(\Swoole\Coroutine::getContext()['__$request'])) {
            \Swoole\Coroutine::getContext()['__$request'] = new \Stdclass();
        }
        \Swoole\Coroutine::getContext()['__$request']->get    = $context['get'];
        \Swoole\Coroutine::getContext()['__$request']->cookie = $context['cookie'];
        $controller_class                                     = $context['controller_class'];

        if (getenv('STDOUT_LOG') && $frame->data != '{"action":"ping"}') {
            echo (new \DateTime())->format('Y-m-d H:i:s.u') . " |\e[30;46m INFO \e[0m\e[36m [INFO][onmessage]fd{$frame->fd}, data={$frame->data}, opcode:{$frame->opcode}, fin:{$frame->finish}\e[0m\n";
            echo '>>> pid=' . getmypid() . ', fds=' . implode(',', array_keys(self::$websocketFrameContext)) . "\n";
            echo '>>> GET=' . json_encode($context['get']) . "\n------\n";
        }

        call_user_func([new $controller_class(), 'onMessage'], $server, $frame);
    }

    /**
     * 有新的连接进入时，在 worker 进程中回调。
     *
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $reactorId
     */
    public function onConnect(\Swoole\Server $server, int $fd, int $reactorId)
    {
        // Add event Listener
        $this->trigger_user_event('onConnect', $server, $fd, $reactorId);
    }

    /**
     * TCP 客户端连接关闭后，在 worker 进程中回调此函数。
     *
     * @param \Swoole\Server $server
     * @param int $fd
     * @param int $reactorId
     */
    public function onClose(\Swoole\Server $server, int $fd, int $reactorId)
    {
        // http发生onClose时，不进行websocketFrameContext的清理
        if (empty(self::$websocketFrameContext[$fd])) {
            // if (getenv('STDOUT_LOG')) {
            //     echo (new \DateTime())->format('Y-m-d H:i:s.u') . " |\e[37;41m ERROR \e[0m\e[31m [onClose]fd{$fd}, WebSocket fd has been stoped already, skip ...\e[0m \n";
            // }
            return;
        }

        // Get websocket connection Context
        $context = self::$websocketFrameContext[$fd];

        if (getenv('STDOUT_LOG')) {
            echo (new \DateTime())->format('Y-m-d H:i:s.u') . " |\e[30;43m INFO \e[0m\e[33m [WARNING][onClose]fd{$fd}, WebSocket fd normal quit\e[0m \n";
            echo 'GET=' . json_encode($context['get']) . "\n------\n";
        }

        // Restore context data
        if (empty(\Swoole\Coroutine::getContext()['__$request'])) {
            \Swoole\Coroutine::getContext()['__$request'] = new \Stdclass();
        }
        \Swoole\Coroutine::getContext()['__$request']->get    = $context['get'];
        \Swoole\Coroutine::getContext()['__$request']->cookie = $context['cookie'];
        $controller_class                                     = $context['controller_class'];

        // Clear websocket cache
        unset(self::$websocketFrameContext[$fd]);

        // Call xxxController::onClose()
        call_user_func([new $controller_class(), 'onClose'], $server, $fd);

        // Call \App\Boot::onclose
        $this->trigger_user_event('onClose', $server, $fd, $reactorId);
    }
}
