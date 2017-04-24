<?php
namespace ePHP\Http;

class Session
{
    /**
     * Store session
     *
     * @param string $name
     * @param mixed $value
     */
    public function set($name, $value)
    {
        $_SESSION[$name] = $value;
    }

    /**
     * Delete session
     *
     * @param string $name
     */
    public function delete($name)
    {
        unset($_SESSION[$name]);
    }

    /**
     * Delete all session
     */
    public function deleteAll()
    {
        if (isset($_SESSION)) {
            $_SESSION = array();
        }
    }

    /**
     * Start session if it is not started
     *
     * @param string $name Session name
     * @return \ePHP\Http\Session
     */
    public function start($name = '')
    {
        if (!isset($_SESSION)) {
            if ($name) {
                session_name($name);
            }
            session_start();
        }

        return $this;
    }

    /**
     * Get session value, Support new Session()->get('user.info.name')
     *
     * @param string $key
     * @return mixed
     */
    public function get($key = '')
    {
        if ($key == '') {
            return $_SESSION;
        } elseif (array_key_exists($key, $_SESSION)) {
            return $_SESSION[$key];
        } elseif (strpos($key, '.')) {
            $names = explode('.', $key);

            $value = $_SESSION;
            foreach ($names as $v) {
                if (isset($value[$v])) {
                    $value = $value[$v];
                } else {
                    $value = false;
                    break;
                }
            }

            return $value;
        } else {
            return false;
        }
    }
}
