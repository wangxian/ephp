<?php

namespace ePHP\Exception;

use ePHP\Core\Config;

class ExitException extends CommonException
{
    public function __construct($message, $file='', $line='')
    {
        parent::__construct($message);

        if ($file) $this->file = $file;
        if ($line) $this->line = $line;
    }
}
