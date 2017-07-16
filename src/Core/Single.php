<?php
namespace ePHP\Core;

trait Single
{
    /**
     * @var \ePHP\Core\Server
     */
    private static $instance;

    /**
     * Dynamically handle calls to the class.
     *
     * @return \ePHP\Core\Server
     */
    public static function init()
    {
        if (!self::$instance instanceof self) {
            return self::$instance = new self();
        }
        return self::$instance;
    }
}
