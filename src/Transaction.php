<?php

namespace Cuitcode\Paystack;

use Cuitcode\Paystack\Utils\Util;
use Cuitcode\Paystack\ApiResource;

class Transaction extends ApiResource
{
    const OBJECT_NAME = 'transaction';

    // use ApiOperations\Create;
    // use ApiOperations\Retrieve;
    // use ApiOperations\Request;
    // use ApiOperations\Update;

    const STATUS_SUCCESS = 'success';

    const STATUS_FAILED = 'failed';

    public static function initialize($params = null, $options = null)
    {
        self::_validateParams($params);
        $url = static::classUrl();
        $url = "{$url}/initialize";

        list($response, $opts) = static::_staticRequest('post', $url, $params, $options);
        $obj = Util::convertToPaystackObject($response->json, $opts);
        $obj->setLastResponse($response);

        return $obj;
        // return $response->json;
    }

    public static function chargeAuthorization($params = null, $options = null)
    {
        self::_validateParams($params);
        $url = static::classUrl();
        $url = "{$url}/charge_authorization";

        list($response, $opts) = static::_staticRequest('post', $url, $params, $options);
        $obj = Util::convertToPaystackObject($response->json, $opts);
        $obj->setLastResponse($response);

        return $obj;
    }
}
