<?php

namespace ePHP\Exception;

class ExitException extends CommonException
{
    public function __construct()
    {
        parent::__construct('', -99);
    }

    public function __toString()
    {
        return '';
    }
}
