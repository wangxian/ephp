<?php
/**
+------------------------------------------------------------------------------
 * memcached缓存
 * <code>
 * 如果选择了，memcache缓存驱动，则需要在 APP_PATH.'/conf/main.conf' 中配置memcache server信息。
 * 配置格式如：
 * ...
 * 'memcached' =>
 *   [
 *     ['host'=>'192.168.0.102', 'port'=>11211, 'weight'=>3],
 *     ['host'=>'192.168.0.103', 'port'=>11211, 'weight'=>3],
 *     ['host'=>'192.168.0.106', 'port'=>11211, 'weight'=>4]
 *   ]
 * ...
 * </code>
+------------------------------------------------------------------------------
 * @version 4.0
 * @author WangXian
 * @package libraries
 * @email wo#wangxian.me
 * @creation_date 2011-8-3
 * @last_modified 2011-8-3
 * @ignore
+------------------------------------------------------------------------------
 */

namespace ePHP\Cache;
use ePHP\Core\Config;

class CacheMemcached
{
    // 原始链接驱动链接
    public $connection;

    function __construct()
    {
        $this->connection = new MemCache;
        $config = Config::get('cache_memcached');

        foreach ($config as $v)
        {
            $this->addServer($v['host'], $v['port'], $v['weight']);
        }
    }

    /**
     * 写缓存
     *
     * @param $key 缓存名称
     * @param $data 缓存内容
     * @param $expire 缓存有效期，0:长期有效, -1: 不缓存
     * @return int
     */
    function set($key, $data, $expire = 0)
    {
        return $this->connection->set($key, $data, MEMCACHE_COMPRESSED, $expire);
    }

    /**
     * 取缓存
     *
     * @param string $key 缓存名称
     * @return mixed
     */
    function get($key)
    {
        return $this->connection->get($key);
    }

    /**
     * 删除缓存
     *
     * @param string $key 缓存名称
     * @return bool
     */
    function delete($key)
    {
        return $this->connection->delete($key);
    }

    /**
     * 添加memcache server
     *
     * @param string $host 主机名成
     * @param int $port 端口
     * @param int $weight 权重
     * @return bool
     */
    function addServer($host, $port = 11211, $weight = 10)
    {
        return $this->connection->addServer($host, $port, true, $weight);
    }

    /**
     * 刷新所有的缓存
     *
     * @return booean
     */
    public function flush()
    {
        return $this->connection->flush();
    }

    function __destruct()
    {
        $this->connection->close();
    }
}

