<?php /** @noinspection PhpUnused */

namespace ePHP\Http;

use ePHP\Hash\Encrypt;

class Cookie
{
    public function __construct()
    {
    }

    // /**
    //  * @var \ePHP\Http\Cookie
    //  */
    // private static $instance;

    // /**
    //  * Handle dynamic, static calls to the object.
    //  *
    //  * @param  string  $method
    //  * @param  array   $args
    //  * @return mixed
    //  */
    // public static function __callStatic($method, $args)
    // {
    //     if (!isset(self::$instance))
    //     {
    //         self::$instance = new self();
    //     }
    //     $method = '_'.$method;
    //     return self::$instance->$method(...$args);
    // }

    /**
     * Set Cookie
     *
     * @param string $name cookie name
     * @param mixed $value
     * @param int $expire default 604800s(7days)
     * @param string $path default /
     * @param string $domain default empty
     */
    public function set($name, $value, $expire = 604800, $path = '/', $domain = '')
    {
        if (empty($domain)) {
            // $domain = '.' . serverv('HTTP_HOST');
            setcookie($name, $value, $expire + time(), $path);
        } else {
            setcookie($name, $value, $expire + time(), $path, $domain);
        }

        // Make it come into effect immediately.
        $_COOKIE[$name] = $value;
    }

    /**
     * Set Secret cookie
     *
     * @param string $name cookie name
     * @param mixed $value
     * @param int $expire default 604800s(7days)
     * @param string $path default /
     * @param string $domain default empty
     */
    public function setSecret($name, $value, $expire = 604800, $path = '/', $domain = '')
    {
        /** @noinspection PhpUndefinedConstantInspection */
        $value = Encrypt::encryptG($value, md5(serverv('HTTP_HOST') . APP_PATH . SERVER_MODE));
        $this->set($name, $value, $expire, $path, $domain);
    }

    /**
     * Get the cookie
     *
     * @param string $name
     * @return mixed
     */
    public function get($name)
    {
        return isset($_COOKIE[$name]) ? $_COOKIE[$name] : false;
    }

    /**
     * Get Secret cookie
     *
     * @param string $name
     * @return string
     */
    public function getSecret($name)
    {
        $value = $this->get($name);
        if (empty($value)) {
            return false;
        } else {
            /** @noinspection PhpUndefinedConstantInspection */
            return Encrypt::decryptG($value, md5(serverv('HTTP_HOST') . APP_PATH . SERVER_MODE));
        }
    }

    /**
     * Delete the cookie
     *
     * @param string $name
     * @param string $path default /
     * @param string $domain default empty
     */
    public function delete($name, $path = '/', $domain = '')
    {
        if (empty($domain)) {
            setcookie($name, '', time() - 3600, $path);
        } else {
            setcookie($name, '', time() - 3600, $path, $domain);
        }

        unset($_COOKIE[$name]);
    }

    /**
     * Delete all cookie
     */
    public function deleteAll()
    {
        foreach ($_COOKIE as $k => $v) {
            $this->delete($k);
        }
    }
}
