<?php

namespace Cuitcode\Paystack\Exceptions;

use Exception;

class InvalidPlan extends Exception
{
    /**
     * Create a new InvalidPlan instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @return static
     */
    public static function notYetCreated($owner)
    {
        return new static(class_basename($owner).' is not a Paystack plan yet. See the createAsPaystackPlan method.');
    }
}