<?php

namespace ePHP\Core;

/**
 * 父控制器
 */
class Controller
{
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
            case 'request':
                return $this->request = new \ePHP\Http\Request();
                break;
            case substr($key, 0, 5) === 'model':
                if ($key === 'model')
                {
                    return $this->model = new \ePHP\Model\BaseModel();
                }
                else
                {
                    $model_name        = '\\App\\Models\\' . substr($key, 6) . 'Model';
                    return $this->$key = new $model_name;
                }
                break;
            case 'cache':
                return $this->cache = \ePHP\Cache\Cache::init();
            default:
                show_error("Undefined property {$key}");
        }
    }
}
