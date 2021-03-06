<?php
namespace ePHP\Core;

trait Single
{
    /**
     * @var Single
     */
    private static $instance;

    /**
     * Dynamically handle calls to the class.
     *
     * @return $this
     */
    public static function init()
    {
        if (!self::$instance instanceof self) {
            return self::$instance = new self();
        }
        return self::$instance;
    }
}
