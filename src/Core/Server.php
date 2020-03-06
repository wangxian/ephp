<?php
namespace ePHP\Core;

use \Swoole\Http\Request;
use \Swoole\Http\Response;

class Server
{
    /**
     * ePHP latest verson
     *
     * @var string
     */
    private $version = '7.2';

    /**
     * Static file content type
     *
     * @var array
     */
    private $contentType = [
        'text' => 'text/plain',
        'html' => 'text/html',
        'css'  => 'text/css',
        'js'   => 'text/javascript',

        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'ico'  => 'image/x-icon',


        'ttf'   => 'font/ttf',
        'eot'   => 'font/eot',
        'otf'   => 'font/otf',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2'
    ];

    /**
     * Handle of Swoole http server
     *
     * @var \Swoole\Http\Server
     */
    public $server;

    /**
     * @var \ePHP\Core\Server
     */
    private static $instance;

    /**
     * Swoole server config setting
     *
     * @var array
     */
    public $config = [
        'host' => '0.0.0.0',
        'port' => '8000',
        'task_worker_num' => 0
    ];

    /**
     * Dynamically handle calls to the class.
     *
     * @return \ePHP\Core\Server
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
        $software      = SERVER_MODE === 'swoole' ? 'Swoole Server' : 'PHP Development Server';
        $document_root = APP_PATH . '/public';

        echo <<<EOT
-----------------------------------\033[32m
      ____    __  __  ____
     /\  _`\ /\ \/\ \/\  _`\
   __\ \ \ \ \ \ \_\ \ \ \ \ \
 /'__`\ \ ,__/\ \  _  \ \ ,__/
/\  __/\ \ \   \ \ \ \ \ \ \
\ \____ \\ \_\   \ \_\ \_\ \_\
 \/____/ \/_/    \/_/\/_/\/_/ \033[43;37mv{$version}\033[0m
 \033[0m
>>> \033[35m{$software}\033[0m started ...
Listening on \033[36;4mhttp://{$this->config['host']}:{$this->config['port']}/\033[0m
Document root is \033[34m{$document_root}\033[0m
Press Ctrl-C to quit.
-----------------------------------

EOT;

        echo "\033[32m>>> Http Server is enabled\033[0m \n";
        if ( !empty($this->config['enable_websocket']) ) {
            echo "\033[32m>>> WebSocket Server is enabled\033[0m \n";
        }
        echo "-----------------------------------\n";
    }

    /**
     * 打印访问日志
     * @return null
     */
    private function printAccessLog()
    {
        // 非STDOUT_LOG模式，不打印
        if ( getenv('STDOUT_LOG') ) {
            // 特别情况下 REMOTE_ADDR, REMOTE_PORT 没有被赋值
            if ( !isset($_SERVER['REMOTE_ADDR']) ) {
                $_SERVER['REMOTE_ADDR'] = 'UNKNOW_ADDR';
                $_SERVER['REMOTE_PORT'] = 'UNKNOW_PORT';
                $_SERVER['REQUEST_URI'] = 'UNKNOW_REQUEST_URI';
            }
            echo date('Y-m-d H:i:s') . " | \033[32m{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']}\033[0m | \033[36mGET {$_SERVER['REQUEST_URI']}\033[0m\n";
            echo 'SERVER=' . json_encode($_SERVER) . "\n------\n";
        }
    }

    /**
     * Start a PHP Development Server
     *
     * @param  string $host
     * @param  int    $port
     * @return null
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
     * @param  array $config server config
     * @return \Swoole\Http\Server
     */
    public function createServer(array $config)
    {
        // Mark server mode
        define('SERVER_MODE', 'swoole');

        $this->config = $config + [
                'host' => '0.0.0.0',
                'port' => '8000',
                'task_worker_num' => 0,
                'enable_websocket'=> false
            ];

        // Start websocket or http server
        if ( empty($config['enable_websocket']) ) {
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
     * Add envent Listener
     *
     * @param string $event
     * @return void
     */
    public function add_event_listener(string $event)
    {
        // Automatically instantiate this class
        if ( class_exists("\App\Boot") ) {
            // Excute a boot instance
            $boot = new \App\Boot();

            if ( method_exists($boot, $event) ) {
                call_user_func([$boot, $event], $this->server);
            }

            // Listen Background task listener
            if ($event == 'onBoot') {
                $this->server->on('task', [$boot, 'onTask']);
                $this->server->on('finish', [$boot, 'onFinish']);
            }
        }
    }

    /**
     * Start swoole server
     *
     * @return null
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
        if ( !empty($this->config['enable_websocket']) ) {
            $this->server->on('open', [$this, 'onOpen']);
            $this->server->on('message', [$this, 'onMessage']);
            $this->server->on('close', [$this, 'onClose']);
        }

        // Add event Listener
        $this->add_event_listener('onBoot');

        // Start a new http server
        $this->server->start();
    }

    // public function stop()
    // {
    // }

    // public function reload()
    // {
    // }

    /**
     * Compat fpm server
     * Contains: `$_GET`, `$_POST`, `$_FILES`, `$_SERVER` etc.
     *
     * @param \Swoole\Http\Request $request
     * @return void
     */
    private function _compatFPM(Request $request)
    {
        // Override php globals array
        // Store $_GET, $_POST ....
        $_GET    = $request->get ?? [];
        $_POST   = $request->post ?? [];
        $_COOKIE = $request->cookie ?? [];
        $_FILES  = $request->files ?? [];
        // $_REQUEST  = array_merge($_COOKIE, $_GET, $_POST);

        // 注入全局变量
        $GLOBALS['__$request']  = $request;
        $GLOBALS['__$DB_QUERY_COUNT'] = 0;

        // 兼容php-fpm的$_SERVER
        $_SERVER = [];
        foreach ($request->server as $key => $value) {
            $key = strtoupper($key);
            $_SERVER[$key] = $value;

            // FIXED: swoole REQUEST_URI don't contains QUERY_STRING
            if ($key === 'REQUEST_URI' && isset($_SERVER['QUERY_STRING'])) {
                $_SERVER['REQUEST_URI'] .= '?' . $_SERVER['QUERY_STRING'];
            }
        }

        // 兼容php-fpm的header传值
        foreach ($request->header as $key => $value) {
            $key = strtoupper(str_replace('-', '_', $key));
            if ($key === 'CONTENT_TYPE' || $key === 'CONTENT_LENGTH') {
                $_SERVER[$key] = $value;
            } else {
                $_SERVER['HTTP_' . $key] = $value;
            }
        }
    }

    /**
     * Linsten http server onRequest
     *
     * @param  \Swoole\Http\Request  $request
     * @param  \Swoole\Http\Response $response
     */
    public function onRequest(Request $request, Response $response)
    {
        // Compat fpm server
        $this->_compatFPM($request);

        // 注入全局变量
        $GLOBALS['__$response'] = $response;

        $response->header('Server', 'ePHP/'. $this->version);

        $filename = APP_PATH . '/public'. $_SERVER['PATH_INFO'];

        // !in_array($extname, ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico'])
        // Try files, otherwise route to app
        if ( !is_file($filename) ) {
            ob_start();
            (new \ePHP\Core\Application())->run();
            $h = ob_get_clean();

            // Fixed output o byte
            // if (strlen($h) === 0) {
            //     $h = ' ';
            // }

            $response->end($h);
        } else {
            $extname = substr($filename, strrpos($filename, '.') + 1);
            if ( isset( $this->contentType[$extname] ) ) {
                $response->header('Content-Type', $this->contentType[$extname]);
            }
            $response->sendfile($filename);
        }

        // 非调试模式，打印访问日志
        $this->printAccessLog();
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
        if ( getenv('STDOUT_LOG') ) {
            echo date('Y-m-d H:i:s') . " |\033[32m ...... http master process start[master_pid={$server->master_pid}] ......\033[0m \n";
            echo date('Y-m-d H:i:s') . " |\033[32m ...... http manager process start[manager_pid={$server->manager_pid}] ......\033[0m \n";
        }

        // Add event Listener
        $this->add_event_listener('onStart');
    }

    /**
     * On server shutdown
     *
     * @param \Swoole\Server $server
     * @return void
     */
    public function onShutdown(\Swoole\Server $server)
    {
        echo date('Y-m-d H:i:s') . " |\033[31m http server shutdown ......\033[0m \n";

        // Add event Listener
        $this->add_event_listener('onShutdown');
    }

    /**
     * On wroker started
     *
     * @param \Swoole\Server $server
     * @param integer $worker_id
     * @return void
     */
    public function onWorkerStart(\Swoole\Server $server, int $worker_id)
    {
        // STDOUT_LOG模式，不打印 worker stop 输出
        if ( getenv('STDOUT_LOG') ) {
            echo date('Y-m-d H:i:s') . " |\033[32m ...... http worker process start[id={$worker_id} pid={$server->worker_pid}] ......\033[0m \n";
        }

        // Add event Listener
        $this->add_event_listener('onWorkerStart');
    }

    /**
     * On wroker stop
     *
     * @param \Swoole\Server $server
     * @param integer $worker_id
     * @return void
     */
    public function onWorkerStop(\Swoole\Server $server, int $worker_id)
    {
        // STDOUT_LOG模式，不打印 worker stop 输出
        if ( getenv('STDOUT_LOG') ) {
            echo date('Y-m-d H:i:s') . " |\033[35m ...... http worker process stop[id={$worker_id} pid={$server->worker_pid}] ......\033[0m \n";
        }

        // Add event Listener
        $this->add_event_listener('onWorkerStop');
    }

    /**
     * On work stop
     *
     * @param \Swoole\Server $server
     * @param integer $worker_id
     * @param integer $worker_pid
     * @param integer $exit_code
     * @return void
     */
    public function onWorkerError(\Swoole\Server $server, int $worker_id, int $worker_pid, int $exit_code)
    {
        echo date('Y-m-d H:i:s') . " |\033[31m http worker process error[id={$worker_id} pid={$worker_pid}] ......\033[0m \n";

        // Add event Listener
        $this->add_event_listener('onWorkerError');
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
     * @param Swoole\WebSocket\Server $server
     * @param \Swoole\Http\Request $request
     * @return void
     */
    public function onOpen(\Swoole\WebSocket\Server $server, \Swoole\Http\Request $request)
    {
        // Compat fpm server
        $this->_compatFPM($request);

        // print_r(self::$websocketFrameContext);

        // filter websocket router class
        // route struct: [$controller_name, $controller_class]
        $controller_class = (\ePHP\Core\Route::init())->findWebSocketRoute();
        if ( !empty($controller_class) ) {
            // Save websocket connection Context
            self::$websocketFrameContext[$request->fd] = [
                'get'    => $_GET,
                'cookie' => $_COOKIE,
                'controller_class' => $controller_class
            ];

            if ( getenv('STDOUT_LOG') ) {
                echo date('Y-m-d H:i:s') . " |\033[34m [websocket][onopen]fd{$request->fd}, pid=". getmypid() .", uri={$request->server['request_uri']}, WebSocket has been CONNECTED...\033[0m\n";
                echo '>>> pid='. getmypid() . ', fds=' . implode(',', array_keys(self::$websocketFrameContext))
                    . ', connections=' . count(self::$websocketFrameContext) . "\n";
                echo '>>> GET=' . json_encode($_GET, JSON_UNESCAPED_UNICODE) . "\n------\n";
            }

            call_user_func([new $controller_class(), 'onOpen'], $server, $request);
        } else {
            $server->close($request->fd);
        }
    }

    /**
     * WebSocket on message
     *
     * @param Swoole\WebSocket\Server $server
     * @param [type] $frame
     * @return void
     */
    public function onMessage(\Swoole\WebSocket\Server $server, \Swoole\WebSocket\Frame $frame)
    {
        // $server->push($frame->fd, "this is server");
        // print_r(self::$websocketFrameContext);

        if ( empty(self::$websocketFrameContext[$frame->fd]) ) {
            if ( getenv('STDOUT_LOG') ) {
                echo date('Y-m-d H:i:s') . " |\033[31m [ERROR][onmessage]fd{$frame->fd}, WebSocket has been stoped before frame sending data\033[0m \n";
            }
            return;
        }

        // Get websocket connection Context
        $context = self::$websocketFrameContext[$frame->fd];

        // Restore global data
        $_POST   = $_SERVER = [];
        $_GET    = $_REQUEST = $context['get'];
        $_COOKIE = $context['cookie'];
        $controller_class = $context['controller_class'];

        if ( getenv('STDOUT_LOG') && $frame->data != '{"action":"ping"}' ) {
            echo date('Y-m-d H:i:s') . " |\033[36m [INFO][onmessage]fd{$frame->fd}, data={$frame->data}, opcode:{$frame->opcode}, fin:{$frame->finish}\033[0m\n";
            echo 'GET=' . json_encode($context['get']) . "\n------\n";
        }

        call_user_func([new $controller_class(), 'onMessage'], $server, $frame);
    }

    /**
     * WebSocket on close
     *
     * @param Swoole\WebSocket\Server $server
     * @param int $fd
     * @return void
     */
    public function onClose(\Swoole\WebSocket\Server $server, int $fd)
    {
        // echo "[websocket][onclose]client fd{$fd} closed\n";

        if ( empty(self::$websocketFrameContext[$fd]) ) {
            echo date('Y-m-d H:i:s') . " |\033[31m [ERROR][onClose]fd{$fd}, WebSocket fd has been stoped already, skip ...\033[0m \n";
            return;
        }

        // Get websocket connection Context
        $context = self::$websocketFrameContext[$fd];

        if ( getenv('STDOUT_LOG') ) {
            echo date('Y-m-d H:i:s') . " |\033[33m [WARNING][onClose]fd{$fd}, WebSocket fd normal quit\033[0m \n";
            echo 'GET=' . json_encode($context['get']) . "\n------\n";
        }

        // Restore global data
        $_POST   = $_SERVER = [];
        $_GET    = $_REQUEST = $context['get'];
        $_COOKIE = $context['cookie'];
        $controller_class = $context['controller_class'];

        // Clear websocket cache
        unset(self::$websocketFrameContext[$fd]);

        call_user_func([new $controller_class(), 'onClose'], $server, $fd);
    }
}
