<?php

namespace ePHP\View;

use ePHP\Cache\Cache;

/**
 * 基础视图
 */
class BaseView
{
    protected $vars;

    /**
     * block current stack
     * @var array
     */
    protected $_current = array();

    protected $_layout = array();

    protected $_instack = array();

    /**
     * 补全视图名
     *
     * 默认使用 控制器/视图.tpl
     * 如果指定，则使用指定好的视图，$this->view->render('user/info.php')
     *
     * @param string $file
     * @return string $filename
     */
    protected function __filename($file)
    {
        if (empty($file))
        {
            $file = $_GET['controller'] . '/' . $_GET['action'] . '.tpl';
        }

        return $file;
    }

    /**
     * 视图渲染
     *
     * @param string $file 视图名称，默认 `控制器/视图.tpl`
     * @param int $expire 视图有效期(单位秒), 默认-1。当$expire>0缓存，=0长期缓存，<0不缓存
     * @package bool $layout_block 是否使用布局模版, 默认不使用
     */
    public function render($file = '', $expire = -1, $layout_block = false)
    {
        if ($expire < 0)
        {
            $this->_include($file, null, $layout_block, false);
        }
        else
        {
            $cache = \ePHP\Cache\Cache::init();
            if (false == ($content = $cache->get('html/' . $file)))
            {
                $content = str_replace(array('<!--{', '}-->'), array('<?php ', '?>'), $this->_include($file, null, $layout_block, true));
                $cache->set('html/' . $file, $content, $expire);
            }
            echo eval('?>' . $content . '<?');
        }
    }

    /**
     * 判断视图是否已缓存
     *
     * @param string $file 视图名
     * @return bool
     */
    public function is_cached($file = '')
    {
        if (Cache::init()->get('html/' . $file))
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    /**
     * 渲染视图, View->render() 别名
     *
     * @param string $file
     * @param int $expire
     */
    public function display($file = '', $expire = -1)
    {
        $this->render($file, $expire);
    }

    /**
     * 模板变量赋值
     *
     * @param string $key
     * @param mixed  $value
     */
    public function assign($key, $value)
    {
        $this->vars[$key] = $value;
    }

    /**
     * 布局模版
     *
     * @param string $file 视图名
     * @param int $expire 有效期
     */
    public function layout($file = '', $expire = -1)
    {
        $this->render($file, $expire, true);
    }

    /**
     * 视图继承
     *
     * @param string $file
     */
    protected function _extends($file)
    {
        $this->_include($file);
    }

    /**
     * 开始定义一个新区块
     *
     * @param string $tpl_name
     */
    protected function _block($block_name)
    {
        $this->_current[] = $block_name;
        ob_start();
    }

    /**
     * 区块结束
     */
    protected function _endblock()
    {
        $content  = ob_get_clean();
        $_current = array_pop($this->_current);

        if (!isset($this->_layout[$_current]))
        {
            echo "<!--{layout_block_{$_current}}-->";
        }

        $this->_instack[$_current] = $content;
        if (empty($this->_current))
        {
            // 延时反转得到正序的栈结构
            if (count($this->_instack) > 1)
            {
                $this->_instack = array_reverse($this->_instack);
            }

            $this->_layout  = array_merge($this->_layout, $this->_instack);
            $this->_instack = array();
        }
    }

    /**
     * 引用视图 或 视图片段
     *
     * @param string $file 变量名
     * @param string $layout_block 是否渲染布局模版
     * @param bool $layout_block 使用布局模版否
     * @param bool $return 返回模版内容 or 直接输出
     */
    public function _include($file, $__vars = null, $layout_block = false, $return = false)
    {
        if (is_array($this->vars))
        {
            extract($this->vars);
        }

        if (is_array($__vars))
        {
            extract($__vars);
        }

        if ($layout_block)
        {
            ob_start();
            include APP_PATH . '/views/' . $this->__filename($file);
            $content = ob_get_clean();
            //echo $content;print_r($this->_layout);exit;

            if ($this->_layout)
            {
                foreach ($this->_layout as $k => $v)
                {
                    $content = str_replace("<!--{layout_block_{$k}}-->", $v, $content);
                }

            }

            // 是否返回
            if ($return)
            {
                return $content;
            }
            else
            {
                echo $content;
            }

        }
        else if ($return)
        {
            ob_start();
            include APP_PATH . '/views/' . $this->__filename($file);
            return ob_get_clean();
        }
        else
        {
            include APP_PATH . '/views/' . $this->__filename($file);
        }

    }
}
