<?php /** @noinspection PhpUndefinedConstantInspection */

namespace ePHP\Http;

class CookieSwoole
{
    /**
     * Set Cookie
     *
     * @param string $name cookie name
     * @param mixed $value
     * @param int $expire default 604800s(7days)
     * @param string $path default /
     * @param string $domain default empty
     * @return void
     */
    public function set($name, $value, $expire = 604800, $path = '/', $domain = '')
    {
        if (empty($domain)) {
            \Swoole\Coroutine::getContext()['__$response']->cookie($name, $value, $expire + time(), $path);
        } else {
            \Swoole\Coroutine::getContext()['__$response']->cookie($name, $value, $expire + time(), $path, $domain);
        }
    }

    /**
     * Set Secret cookie
     *
     * @param string $name cookie name
     * @param mixed $value
     * @param int $expire default 604800s(7days)
     * @param string $path default /
     * @param string $domain default empty
     * @return void
     */
    public function setSecret($name, $value, $expire = 604800, $path = '/', $domain = '')
    {
        $value = \ePHP\Hash\Encrypt::encryptG($value, md5(serverv('HTTP_HOST') . APP_PATH . SERVER_MODE));
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
        return \Swoole\Coroutine::getContext()['__$request']->cookie[$name] ?? false;
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
            return \ePHP\Hash\Encrypt::decryptG($value, md5(serverv('HTTP_HOST') . APP_PATH . SERVER_MODE));
        }
    }

    /**
     * Delete the cookie
     *
     * @param string $name
     * @param string $path default /
     * @param string $domain default empty
     * @return void
     */
    public function delete($name, $path = '/', $domain = '')
    {
        if (empty($domain)) {
            \Swoole\Coroutine::getContext()['__$response']->cookie($name, null, time() - 3600, '/');
        } else {
            $domain = '.' . serverv('HTTP_HOST');
            \Swoole\Coroutine::getContext()['__$response']->cookie($name, null, time() - 3600, '/', $domain);
        }
    }

    /**
     * Delete all cookie
     *
     * @return void
     */
    public function deleteAll()
    {
        foreach (\Swoole\Coroutine::getContext()['__$request']->cookie as $k => $v) {
            $this->delete($k);
        }
    }
}
