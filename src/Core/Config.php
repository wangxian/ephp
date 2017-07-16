<?php

namespace ePHP\Core;

/**
 * 系统配置类
 */
class Config
{
    // 缓存，配置信息
    private static $_config = [];

    /**
     * 存储，配置项
     *
     * @param string $config_name 配置项名称，如main
     * @param mixed $value 项目value
     */
    public static function set($config_name, $value)
    {
        self::$_config[$config_name] = $value;
    }

    /**
     * 获取配置信息
     *
     * main为系统配置，默认bootstrap必须载入系统配置
     * 其他的app自定义配置，可以使用get进行载入，如果已经载入过一次
     * 则不在载入，相当于缓存配置。
     *
     * @param  string $key
     * @param  string $config_name 配置项名称，如mian
     * @return mixed
     */
    public static function get($key, $config_name = 'main')
    {
        // 获取值
        // 加载其他配制文件
        if ($config_name !== 'main' && !isset(self::$_config[$config_name])) {
            $filename = APP_PATH . '/conf/' . $config_name . '.php';
            if (file_exists($filename)) {
                self::$_config[$config_name] = include $filename;
            } else {
                \show_error("Config file {$filename} is not exists.");
            }
        }

        // 返回需要value
        if ($key === '') {
            return self::$_config[$config_name];
        } elseif (array_key_exists($key, self::$_config[$config_name])) {
            return self::$_config[$config_name][$key];
        } elseif (strpos($key, '.')) {
            $array = explode('.', $key);
            if (count($array) === 2) {
                return isset(self::$_config[$config_name][$array[0]][$array[1]]) ? self::$_config[$config_name][$array[0]][$array[1]] : false;
            } elseif (count($array) === 3) {
                return isset(self::$_config[$config_name][$array[0]][$array[1]][$array[2]]) ? self::$_config[$config_name][$array[0]][$array[1]][$array[2]] : false;
            } else {
                show_error('Config::get("a.b.c") Only 3 levels are allowed.');
            }
        } else {
            return false;
        }
    }
}
