<?php
/**
+------------------------------------------------------------------------------
 * 缓存类
+------------------------------------------------------------------------------
 * @version 4.0
 * @author WangXian
 * @package libraries
 * @email wo#wangxian.me
 * @creation_date 2011-1-10
 * @last_modified 2011-8-3
+------------------------------------------------------------------------------
 */
namespace ePHP\Cache;
use ePHP\Core\Config;

class Cache
{
    /**
     * @var \ePHP\Cache\Cache
     */
    private static $instance;

    /**
     * cache handle
     *
     * @var mixed
     */
    public $handle;

    /**
     * 0:长期有效, -1:不缓存，>0:缓存一定的秒数
     *
     * @var int
     */
    public $expire = 0;

    /**
     * 初始化, 获取缓存示例，$cache = Cache::init();
     *
     * @return mixed
     */
    public static function init()
    {
        // !self::$_instance instanceof self
        if (!isset(self::$instance))
        {
            self::$instance = new self();

            // 使用哪种方式的cache
            $cache_driver          = Config::get('cache_driver');
            $cache_driver          = '\\ePHP\Cache\\' . ($cache_driver ? 'Cache' . ucfirst($cache_driver) : 'CacheFile');
            self::$instance->handle = new $cache_driver;
        }
        return self::$instance;
    }

    public function __get($key)
    {
        return $this->get($key);
    }

    public function __set($key, $value)
    {
        return $this->set($key, $value);
    }

    /**
     * 获取缓存
     *
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        return $this->handle->get($key);
    }

    /**
     * 设置缓存
     *
     * @param string $key
     * @param mixed $value
     * @param int $expire 有效期，0,长期有效。
     * @return int 写入数据大小
     */
    public function set($key, $value, $expire = 0)
    {
        // 为了兼容$cache->name = $value的方式，接收$Cache::init()->expire = 900设置有效期。
        if ($this->expire)
        {
            $expire = $this->expire;
        }

        return $this->handle->set($key, $value, $expire);
    }

    /**
     * 删除缓存
     *
     * @param string $key
     * @return bool 成功true,失败false
     */
    public function delete($key)
    {
        return $this->handle->delete($key);
    }

    /**
     * 删除所有缓存
     *
     * @return bool 成功true,失败false
     */
    public function flush()
    {
        return $this->handle->flush();
    }
}


