<?php

namespace ePHP\Core;

/**
 * 父控制器
 */
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
     * 自动实例化一些类，包括：view\request\model\cache
     *
     * @ignore
     * @param string $key
     */
    public function __get($key)
    {
        // echo '__get ' . $key . '<hr />';
        switch ($key)
        {
            case 'view':
                return $this->view = new \ePHP\View\BaseView();
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
     */
    protected function stopRun()
    {
        throw new \ePHP\Exception\ExitException();
    }
}
