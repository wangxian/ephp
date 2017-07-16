<?php
namespace ePHP\Core;

class Controller
{
    /**
     * \swoole_http_request $request
     */
    public $request;

    /**
     * @var \swoole_http_response $response
     */
    public $response;

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
                return $this->view = new \ePHP\Http\Httpclient();
                break;
            case 'session':
                $session_name = Config::get('session_name');
                if (!$session_name) {
                    $session_name = 'pppid';
                }
                return $this->session = (new \ePHP\Http\Session())->start($session_name);
                break;
            case 'cookie':
                return $this->cookie = SERVER_MODE !== 'swoole' ? new \ePHP\Http\Cookie() : new \ePHP\Http\CookieSwoole($this->response);
                break;
            case 'server':
                return \ePHP\Core\Server::init()->server;
            case substr($key, 0, 5) === 'model':
                if ($key === 'model') {
                    return $this->model = new \ePHP\Model\BaseModel();
                } else {
                    $model_name        = '\\App\\Models\\' . ucfirst(substr($key, 6)) . 'Model';
                    return $this->$key = new $model_name;
                }
                break;
            case 'cache':
                return $this->cache = \ePHP\Cache\Cache::init();
            default:
                throw_error("Undefined property {$key}");
        }
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
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && $_SERVER['HTTP_X_REQUESTED_WITH'] == "XMLHttpRequest" ) {
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
            $this->response->header($key, $value);
        } else {
            if (!headers_sent()) {
                header($key . ': '. $value);
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
     */
    protected function redirect($url, $code = 302)
    {
        if (!headers_sent()) {
            if ($code == 301) {
                if (SERVER_MODE === 'swoole') {
                    $this->response->status(301);
                } else {
                    header('HTTP/1.1 301 Moved Permanently');
                }
            } else {
                if (SERVER_MODE === 'swoole') {
                    $this->response->status(302);
                } else {
                    header('HTTP/1.1 302 Found');
                }
            }

            $this->setHeader("Location", $url);
            $this->stopRun();
        } else {
            echo '<html><head><meta charset="UTF-8" /><title></title></head><body>';
            echo '<script>window.location.href="'. $url .'";</script></body></html>';
            $this->stopRun();
        }
    }
}
