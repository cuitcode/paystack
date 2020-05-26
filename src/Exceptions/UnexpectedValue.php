<?php

namespace Cuitcode\Paystack\Exceptions;

use Exception;

class UnexpectedValue extends Exception
{
    /**
     * Create a new UnexpectedValue instance.
     *
     * @param  string  $message
     * @param  string  $rcode
     * @return static
     */
    public static function badValue($message)
    {
        return new static($message);
    }
}
