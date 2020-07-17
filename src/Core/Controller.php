<?php

namespace ePHP\Core;

/**
 * @property \ePHP\View\BaseView view
 * @property \ePHP\Http\Httpclient httpclient
 * @property \ePHP\Console\Console console
 * @property \ePHP\Http\Session session
 * @property \ePHP\Http\Cookie cookie
 * @property \ePHP\Model\BaseModel model
 * @property \ePHP\Cache\Cache cache
 */
class Controller
{
    // /**
    //  * @var \Swoole\Http\Request
    //  */
    // public $request;
    //
    // /**
    //  * @var \Swoole\Http\Response $response
    //  */
    // public $response;

    /**
     * Magic method, Automatic initialization of some commonly used classes
     *
     * @param string $key
     * @return mixed
     */
    public function __get($key)
    {
        switch ($key) {
            case 'view':
                return $this->view = new \ePHP\View\BaseView();
                break;
            case 'httpclient':
                return $this->httpclient = new \ePHP\Http\Httpclient();
                break;
            case 'console':
                return $this->console = new \ePHP\Console\Console();
                break;
            case 'session':
                $session_name = Config::get('session_name');
                if (!$session_name) {
                    $session_name = 'pppid';
                }
                return $this->session = new \ePHP\Http\Session($session_name);
                break;
            case 'cookie':
                return $this->cookie = SERVER_MODE !== 'swoole' ? new \ePHP\Http\Cookie() : new \ePHP\Http\CookieSwoole();
                break;
            case 'server':
                return Server::init()->server;
            case substr($key, 0, 5) === 'model':
                if ($key === 'model') {
                    return $this->model = new \ePHP\Model\BaseModel();
                } else {
                    $model_name = '\\App\\Models\\' . ucfirst(substr($key, 6)) . 'Model';
                    return $this->$key = new $model_name;
                }
                break;
            case 'cache':
                return $this->cache = \ePHP\Cache\Cache::init();
            default:
                throw_error("Undefined property {$key}");
        }

        return '';
    }

    /**
     * Stop run Application
     *
     * @return void
     * @throws \ePHP\Exception\ExitException
     */
    protected function stopRun()
    {
        throw new \ePHP\Exception\ExitException();
    }

    /**
     * Check if the request is from Ajax
     *
     * @return bool
     */
    protected function isAjax()
    {
        if (serverv('HTTP_X_REQUESTED_WITH') == "XMLHttpRequest") {
            return true;
        }
        return false;
    }

    /**
     * Set response header
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    protected function setHeader($key, $value)
    {
        if (SERVER_MODE === 'swoole') {
            \Swoole\Coroutine::getContext()['__$response']->header($key, $value);
        } else {
            if (!headers_sent()) {
                header($key . ': ' . $value);
            }
        }
    }

    /**
     * Redirect to url
     * Compatible swoole mode server container
     *
     * @param string $url
     * @param int $code 301 or 302
     * @return void
     * @throws \ePHP\Exception\ExitException
     * @noinspection HtmlRequiredLangAttribute
     */
    protected function redirect($url, $code = 302)
    {
        if (!headers_sent()) {
            if (SERVER_MODE === 'swoole') {
                \Swoole\Coroutine::getContext()['__$response']->status($code);
            } else if ($code == 301) {
                header('HTTP/1.1 301 Moved Permanently');
            } else {
                header('HTTP/1.1 302 Found');
            }

            $this->setHeader("Location", $url);
            $this->stopRun();
        } else {
            echo '<html><head><meta charset="UTF-8" /><title></title></head><body>';
            echo '<script>window.location.href="' . $url . '";</script></body></html>';
            $this->stopRun();
        }
    }

    /**
     * Get raw post content
     *
     * @return string
     */
    protected function rawContent()
    {
        if (SERVER_MODE !== 'swoole') {
            return file_get_contents('php://input');
        } else {
            return \Swoole\Coroutine::getContext()['__$request']->rawContent();
        }
    }
}
