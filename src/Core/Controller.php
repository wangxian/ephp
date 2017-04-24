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
        switch ($key)
        {
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
                if ($key === 'model')
                {
                    return $this->model = new \ePHP\Model\BaseModel();
                }
                else
                {
                    $model_name        = '\\App\\Models\\' . ucfirst(substr($key, 6)) . 'Model';
                    return $this->$key = new $model_name;
                }
                break;
            case 'cache':
                return $this->cache = \ePHP\Cache\Cache::init();
            default:
                throw new \ePHP\Exception\CommonException("Undefined property {$key}");
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
}
