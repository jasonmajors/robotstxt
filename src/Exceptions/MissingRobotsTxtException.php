<?php
namespace Robots\Exceptions;

use Exception;

class MissingRobotsTxtException extends Exception
{
    // Make message mandatory
    public function __construct($message)
    {
        parent::__construct($message);
    }
}