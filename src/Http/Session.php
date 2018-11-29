<?php
namespace ePHP\Http;

class Session
{
    /**
     * init session
     *
     * @param string $name session name
     */
    public function __construct($name)
    {
        if ( !headers_sent() && !isset($_SESSION)) {
            if ($name) {
                session_name($name);
            }
            session_start();
        }

        return $this;
    }

    /**
     * Store session
     *
     * @param string $name
     * @param mixed $value
     */
    public function set($name, $value)
    {
        if ( isset($_SESSION) ) {
            $_SESSION[$name] = $value;
        }
    }

    /**
     * Delete session
     *
     * @param string $name
     */
    public function delete($name)
    {
        if ( isset($_SESSION) ) {
            unset($_SESSION[$name]);
        }
    }

    /**
     * Delete all session
     */
    public function deleteAll()
    {
        if ( isset($_SESSION) ) {
            $_SESSION = array();
        }
    }

    /**
     * Get session value, Support new Session()->get('user.info.name')
     *
     * @param string $key
     * @return mixed
     */
    public function get($key = '')
    {
        if ( !isset($_SESSION) ) {
            return false;
        }

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
