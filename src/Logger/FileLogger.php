<?php

namespace ePHP\Logger;

class FileLogger
{
    private $logdir = '';

    /**
     * FileLogger Constructor
     *
     * @param string $logdir default 'logs/'
     */
    public function __construct($logdir = 'logs/')
    {
        $this->logdir = APP_PATH . '/' . $logdir;
    }

    /**
     * Write to log
     *
     * @param string $key
     * @param string $value
     * @return void
     */
    public function write($key, $value)
    {
        if (!is_string($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        if (!is_writeable($this->logdir)) {
            show_error('Failed to write log, directory is not writable of ' . $this->logdir);
        }

        $filename = $this->logdir . $key . date('Y-m-d') . '.log';

        // Checking whether the file is writeable
        // fixed: runnint under toolbox docker-machine
        // if ( file_exists($filename) && !is_writeable($filename) ) {
        //     show_error('ERROR: {' . $filename . '} is not writeable, check the file permissions');
        // }

        error_log('[' . date('H:i:s') . ']' . $value . "\n", 3, $filename);
    }
}
