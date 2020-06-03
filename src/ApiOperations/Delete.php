<?php

namespace Cuitcode\Paystack\ApiOperations;

use Cuitcode\Paystack\Utils\Util;

/**
 * Trait for resources that need to make API requests.
 *
 * This trait should only be applied to classes that derive from PaystackObject.
 */
trait Delete
{
    //
    public static function delete($id, $params = null, $opts = null) {
        self::_validateParams($params);

        $url = static::resourceUrl($id);
        list($response, $opts) = static::_staticRequest('delete', $url, $params, $opts);
        // static::refreshFrom($response, $opts);

        // return $this;
    }
}
