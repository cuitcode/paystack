<?php

namespace Cuitcode\Paystack\Exceptions;
use Exception;

class InvalidArgument extends Exception
{
    /**
     * Create a new InvalidArgument instance.
     *
     * @param string $message
     * @return static
     */
    public static function badArgument($message)
    {
        return new static($message);
    }
}