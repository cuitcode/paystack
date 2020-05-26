<?php

namespace Cuitcode\Paystack\ApiOperations;

/**
 * Trait for retrievable resources. Adds a `retrieve()` static method to the
 * class.
 *
 * This trait should only be applied to classes that derive from PaystackObject.
 */
trait Retrieve
{
    /**
     * @param array|string $id the ID of the API resource to retrieve,
     *     or an options array containing an `id` key
     * @param null|array|string $opts
     *
     * @throws \Paystack\Exception\ApiError if the request fails
     *
     * @return static
     */
    public static function retrieve($id, $opts = null)
    {
        $opts = \Cuitcode\Paystack\Utils\RequestOptions::parse($opts);
        $instance = new static($id, $opts);
        $instance->refresh($opts);

        return $instance;
    }
}
