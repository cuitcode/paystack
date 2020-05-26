<?php

namespace Cuitcode\Paystack\ApiOperations;

use Cuitcode\Paystack\Utils\Util;

/**
 * Trait for resources that need to make API requests.
 *
 * This trait should only be applied to classes that derive from PaystackObject.
 */
trait Update
{
    /**
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws Cuitcode\Paystack\Exceptions\ApiError if the request fails
     *
     * @return Cuitcode\Paystack\Collection of ApiResources
     */
     public static function update($id, $params = null, $opts = null)
     {
         self::_validateParams($params);
         $url = static::resourceUrl($id);
 
         list($response, $opts) = static::_staticRequest('put', $url, $params, $opts);
         $obj = Util::convertToPaystackObject($response->json, $opts);
         $obj->setLastResponse($response);
 
         return $obj;
     }
}
