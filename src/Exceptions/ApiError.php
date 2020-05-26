<?php

namespace Cuitcode\Paystack\Exception;
use Exception;

class ApiError extends Exception
{
    /**
     * Create a new ApiError instance.
     *
     * @param string $message
     * @return static
     */
    public static function failedApi($message)
    {
        return new static($message);
    }
}