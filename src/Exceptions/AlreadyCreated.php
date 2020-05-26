<?php

namespace Cuitcode\Paystack\Exceptions;

use Exception;

class AlreadyCreated extends Exception
{
    /**
     * Create a new AlreadyCreated instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $owner
     * @return static
     */
    public static function exists($owner, $id)
    {
        return new static(class_basename($owner)." is already created on Paystack with ID {$owner[$id]}.");
    }
}
