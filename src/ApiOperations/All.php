<?php

namespace Cuitcode\Paystack\ApiOperations;

use Cuitcode\Paystack\Utils\Util;
use Cuitcode\Paystack\Collection;
use Cuitcode\Paystack\Exceptions\UnexpectedValue;

/**
 * Trait for resources that need to make API requests.
 *
 * This trait should only be applied to classes that derive from PaystackObject.
 */
trait All
{
    /**
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws Cuitcode\Paystack\Exceptions\ApiError if the request fails
     *
     * @return Cuitcode\Paystack\Collection of ApiResources
     */
     public static function all($params = null, $opts = null)
     {
         self::_validateParams($params);
         $url = static::classUrl();
 
         list($response, $opts) = static::_staticRequest('get', $url, $params, $opts);
         $obj = Util::convertToPaystackObject($response->json, $opts);
        //  if (!($obj instanceof Collection)) {
        //      throw UnexpectedValue::badValue(
        //          'Expected type ' . Collection::class . ', got "' . \get_class($obj) . '" instead.'
        //      );
        //  }
         $obj->setLastResponse($response);
        //  $obj->setFilters($params);
 
         return $obj;
     }
}
