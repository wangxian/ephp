<?php /** @noinspection PhpUnused */

/** @noinspection PhpDocMissingThrowsInspection */

namespace ePHP\Console;

use \ePHP\Core\Config;

class Console
{
    /**
     * Detailed debug information
     */
    const DEBUG = 100;

    /**
     * Interesting events
     *
     * Examples: User logs in, SQL logs.
     */
    const INFO = 200;

    /**
     * Uncommon events
     */
    const NOTICE = 300;

    /**
     * Exceptional occurrences that are not errors
     *
     * Examples: Use of deprecated APIs, poor use of an API,
     * undesirable things that are not necessarily wrong.
     */
    const WARNING = 400;

    /**
     * Runtime errors
     */
    const ERROR = 500;

    /**
     * Fatal errors
     */
    const FATAL = 600;

    /**
     * Logging levels defined
     *
     * This is a static variable and not a constant to serve as an extension point for custom levels
     *
     * @var string[] $levels Logging levels with the levels as key
     */
    private static $levels = [
        'DEBUG'   => self::DEBUG,
        'INFO'    => self::INFO,
        'NOTICE'  => self::NOTICE,
        'WARNING' => self::WARNING,
        'ERROR'   => self::ERROR,
        'FATAL'   => self::FATAL
    ];

    /**
     * Write message
     *
     * @param int $level
     * @param string $message
     * @return void
     * @noinspection PhpUnhandledExceptionInspection
     */
    private function _log($level, $message)
    {
        $level_config = Config::get("log_level");
        if (!$level_config) {
            $level_config = "DEBUG";
        }

        $level_val = self::$levels[$level];
        $level_config = self::$levels[$level_config];

        if ($level_config > $level_val) {
            return ;
        }

        wlog($level, $message);
    }

    /**
     * Console debug message
     *
     * @param  string $message
     * @return void
     */
    public function debug($message)
    {
        $this->_log("DEBUG", $message);
    }

    /**
     * Console info message
     *
     * @param  string $message
     * @return void
     */
    public function info($message)
    {
        $this->_log("INFO", $message);
    }

    /**
     * Console notice message
     *
     * @param  string $message
     * @return void
     */
    public function notice($message)
    {
        $this->_log("NOTICE", $message);
    }

    /**
     * Console warning message
     *
     * @param  string $message
     * @return void
     */
    public function warning($message)
    {
        $this->_log("WARNING", $message);
    }

    /**
     * Console error message
     *
     * @param  string $message
     * @return void
     */
    public function error($message)
    {
        $this->_log("ERROR", $message);
    }

    /**
     * Console fatal message
     *
     * @param  string $message
     * @return void
     */
    public function fatal($message)
    {
        $this->_log("FATAL", $message);
    }
}
