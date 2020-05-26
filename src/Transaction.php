<?php

namespace Cuitcode\Paystack;

use Cuitcode\Paystack\ApiResource;
use Cuitcode\Paystack\Utils\Util;

class Transaction extends ApiResource {
    //
    const OBJECT_NAME = 'transaction';

    // use ApiOperations\Create;
    // use ApiOperations\Retrieve;
    // use ApiOperations\Request;
    // use ApiOperations\Update;

    //
    public static function initialize($params = null, $options = null) {
        self::_validateParams($params);
        $url = static::classUrl();
        $url = "{$url}/initialize";

        list($response, $opts) = static::_staticRequest('post', $url, $params, $options);
        $obj = Util::convertToPaystackObject($response->json, $opts);
        $obj->setLastResponse($response);

        return $obj;
        // return $response->json;
    }
}
