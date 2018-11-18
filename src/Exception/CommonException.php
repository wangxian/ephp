<?php

namespace ePHP\Exception;

use ePHP\Core\Config;

class CommonException extends \Exception
{
    // 错误堆栈
    protected $ephpTraceString = '';

    /**
     * @param string $message 错误消息
     * @param integer $code 错误码
     * @param mixed $previous
     */
    public function __construct($message, $code = 0, $previous = null)
    {
        // 5.3.0  The previous parameter was added.
        // 5.3.0 以后previous才新增的
        parent::__construct($message, $code);

        $this->ephpTraceString = $this->getTraceAsString();

        if (is_array($previous)) {
            $this->file = $previous['errfile'];
            $this->line = $previous['errline'];
        } elseif (is_object($previous)) {
            $this->file            = $previous->getFile();
            $this->line            = $previous->getLine();
            $this->ephpTraceString = $previous->getTraceAsString();
        }
    }

    public function __toString()
    {
        $str = "异常信息\n-----------------------------------\n" . $this->getMessage() . "\n"
        . "-----------------------------------\n" . $this->ephpTraceString . "\n-----------------------------------\n";

        // 记录异常信息到文件中
        wlog('ExceptionLog', $str);

        if (PHP_SAPI || (isset($_SERVER['HTTP_X_REQUESTED_WITH'])
            && $_SERVER['HTTP_X_REQUESTED_WITH'] == "XMLHttpRequest") ) {
            echo $str;
        } else {
            dd('error', $str);
        }

        return '';
    }
}
