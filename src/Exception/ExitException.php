<?php

namespace ePHP\Exception;

use ePHP\Core\Config;

class ExitException extends CommonException
{
    public function __construct()
    {
        parent::__construct('', -99);
    }

    public function __toString()
    {

    }
}
