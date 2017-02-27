<?php

namespace ePHP\Exception;

use ePHP\Core\Config;

class CommonException extends \Exception
{
    // 错误堆栈
    private $ephpTraceString = '';

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

        if (is_array($previous))
        {
            $this->file = $previous['errfile'];
            $this->line = $previous['errline'];
        }
        else if (is_object($previous))
        {
            $this->file            = $previous->getFile();
            $this->line            = $previous->getLine();
            $this->ephpTraceString = $previous->getTraceAsString();
        }
    }

    public function __toString()
    {
        $str = "异常信息\n-----------------------------------\n" . $this->getMessage() . "\n"
        . "-----------------------------------\n" . $this->ephpTraceString . "\n-----------------------------------\n";

        if (Config::get('exception_log'))
        {
            wlog('ExceptionLog', $str);
        }

        // //ob_start();
        // $tpl = C('tpl_exception');
        // if (!$tpl)
        // {
        //     include FW_PATH . '/tpl/ephpException.tpl.php';
        // }
        // else
        // {
        //     include APP_PATH . '/views/public/' . $tpl;
        // }

        // //return ob_get_clean();
        // if (defined('RUN_ENV') && RUN_ENV == 'prod')
        // {
        dump('error', $str);
        // }

        return '';
    }
}
