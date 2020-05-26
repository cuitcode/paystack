<?php

namespace Cuitcode\Paystack\Exceptions;
use Exception;

class Authentication extends Exception
{
    /**
     * Create a new Authentication instance.
     *
     * @param string $message
     * @return static
     */
    public static function failedAuth($message)
    {
        return new static($message);
    }
}