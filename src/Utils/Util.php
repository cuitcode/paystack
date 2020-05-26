<?php

namespace Cuitcode\Paystack\Utils;

use Cuitcode\Paystack\PaystackObject;
use Cuitcode\Paystack\Utils\ObjectTypes;

abstract class Util
{
    private static $isMbstringAvailable = null;
    private static $isHashEqualsAvailable = null;

    /**
     * Whether the provided array (or other) is a list rather than a dictionary.
     * A list is defined as an array for which all the keys are consecutive
     * integers starting at 0. Empty arrays are considered to be lists.
     *
     * @param array|mixed $array
     *
     * @return bool true if the given object is a list
     */
     public static function isList($array)
     {
         if (!\is_array($array)) {
             return false;
         }
         if ($array === []) {
             return true;
         }
         if (\array_keys($array) !== \range(0, \count($array) - 1)) {
             return false;
         }
 
         return true;
     }
 
     /**
      * Converts a response from the Paystack API to the corresponding PHP object.
      *
      * @param array $resp the response from the Paystack API
      * @param array $opts
      *
      * @return array|PaystackObject
      */
     public static function convertToPaystackObject($resp, $opts)
     {
         $types = ObjectTypes::mapping;
         if (self::isList($resp)) {
             $mapped = [];
             foreach ($resp as $i) {
                 \array_push($mapped, self::convertToPaystackObject($i, $opts));
             }
 
             return $mapped;
         }
         if (\is_array($resp)) {
             if (isset($resp['object']) && \is_string($resp['object']) && isset($types[$resp['object']])) {
                 $class = $types[$resp['object']];
             } else {
                 $class = PaystackObject::class;
             }
 
             return $class::constructFrom($resp, $opts);
         }
 
         return $resp;
     }

    /**
     * @param mixed|string $value a string to UTF8-encode
     *
     * @return mixed|string the UTF8-encoded string, or the object passed in if
     *    it wasn't a string
     */
    public static function utf8($value)
    {
        if (null === self::$isMbstringAvailable) {
            self::$isMbstringAvailable = \function_exists('mb_detect_encoding');

            if (!self::$isMbstringAvailable) {
                \trigger_error('It looks like the mbstring extension is not enabled. ' .
                    'UTF-8 strings will not properly be encoded. Ask your system ' .
                    'administrator to enable the mbstring extension, or write to ' .
                    'support@paystack.com if you have any questions.', \E_USER_WARNING);
            }
        }

        if (\is_string($value) && self::$isMbstringAvailable && 'UTF-8' !== \mb_detect_encoding($value, 'UTF-8', true)) {
            return \utf8_encode($value);
        }

        return $value;
    }

    /**
     * @param string $key a string to URL-encode
     *
     * @return string the URL-encoded string
     */
    public static function urlEncode($key)
    {
        $s = \urlencode((string) $key);

        // Don't use strict form encoding by changing the square bracket control
        // characters back to their literals. This is fine by the server, and
        // makes these parameter strings easier to read.
        $s = \str_replace('%5B', '[', $s);

        return \str_replace('%5D', ']', $s);
    }

    public static function normalizeId($id)
    {
        if (\is_array($id)) {
            $params = $id;
            $id = $params['id'];
            unset($params['id']);
        } else {
            $params = [];
        }

        return [$id, $params];
    }

    /**
     * Returns UNIX timestamp in milliseconds.
     *
     * @return int current time in millis
     */
    public static function currentTimeMillis()
    {
        return (int) \round(\microtime(true) * 1000);
    }
}