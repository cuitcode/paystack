<?php

namespace Cuitcode\Paystack\ApiOperations;

use Cuitcode\Paystack\Utils\Util;

/**
 * Trait for resources that need to make API requests.
 *
 * This trait should only be applied to classes that derive from PaystackObject.
 */
trait Create
{
    //
    public static function create($params = null, $options = null) {
        self::_validateParams($params);
        $url = static::classUrl();

        list($response, $opts) = static::_staticRequest('post', $url, $params, $options);
        $obj = Util::convertToPaystackObject($response->json, $opts);
        $obj->setLastResponse($response);

        return $obj;
        // return $response->json;
    }
}
