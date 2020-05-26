<?php

namespace Cuitcode\Paystack;

use \GuzzleHttp\Client;

use Cuitcode\Paystack\Paystack;
use Cuitcode\Paystack\ApiResource;
use Cuitcode\Paystack\ApiResponse;
use Cuitcode\Paystack\Utils\Util;
use Cuitcode\Paystack\Exceptions\Authentication;
use Cuitcode\Paystack\Exceptions\UnexpectedValueException;
/**
 * Class ApiRequestor.
 */
class ApiRequestor
{
    /**
     * @var null|string
     */
    private $_apiKey;

    /**
     * @var string
     */
    private $_apiBase;

    /**
     * @var HttpClient\ClientInterface
     */
    private static $_httpClient;

    /**
     * @var RequestTelemetry
     */
    private static $requestTelemetry;

    /**
     * ApiRequestor constructor.
     *
     * @param null|string $apiKey
     * @param null|string $apiBase
     */
    public function __construct($apiKey = null, $apiBase = null)
    {
        $this->_apiKey = $apiKey;
        if (!$apiBase) {
            $apiBase = Paystack::$apiBase;
        }
        $this->_apiBase = $apiBase;
    }

    /**
     * Creates a telemetry json blob for use in 'X-Paystack-Client-Telemetry' headers.
     *
     * @static
     *
     * @param RequestTelemetry $requestTelemetry
     *
     * @return string
     */
    private static function _telemetryJson($requestTelemetry)
    {
        $payload = [
            'last_request_metrics' => [
                'request_id' => $requestTelemetry->requestId,
                'request_duration_ms' => $requestTelemetry->requestDuration,
            ],
        ];

        $result = \json_encode($payload);
        if (false !== $result) {
            return $result;
        }
        Paystack::getLogger()->error('Serializing telemetry payload failed!');

        return '{}';
    }

    /**
     * @static
     *
     * @param ApiResource|array|bool|mixed $d
     *
     * @return ApiResource|array|mixed|string
     */
    private static function _encodeObjects($d)
    {
        if ($d instanceof ApiResource) {
            return Util::utf8($d->id);
        }
        if (true === $d) {
            return 'true';
        }
        if (false === $d) {
            return 'false';
        }
        if (\is_array($d)) {
            $res = [];
            foreach ($d as $k => $v) {
                $res[$k] = self::_encodeObjects($v);
            }

            return $res;
        }

        return Util::utf8($d);
    }

    /**
     * @param string     $method
     * @param string     $url
     * @param null|array $params
     * @param null|array $headers
     *
     * @throws ApiError
     *
     * @return array tuple containing (ApiReponse, API key)
     */
    public function request($method, $url, $params = null, $headers = null)
    {
        $params = $params ?: [];
        $headers = $headers ?: [];
        list($res, $myApiKey) = $this->_requestRaw($method, $url, $params, $headers);
        $json = $this->_interpretResponse($res->getBody(), $res->getStatusCode(), $res->getHeaders());
        $resp = new ApiResponse($res->getBody(), $res->getStatusCode(), $res->getHeaders(), $json);

        return [$resp, $myApiKey];
    }

    /**
     * @param string $rbody a JSON string
     * @param int $rcode
     * @param array $rheaders
     * @param array $resp
     *
     * @throws UnexpectedValue
     * @throws ApiError
     */
    public function handleErrorResponse($rbody, $rcode, $rheaders, $resp)
    {
        if (!\is_array($resp) || !isset($resp['error'])) {
            $msg = "Invalid response object from API: {$rbody} "
              . "(HTTP response code was {$rcode})";

            throw UnexpectedValue::badValue($msg);
        }

        // $errorData = $resp['error'];

        // $error = null;
        // if (\is_string($errorData)) {
        //     $error = self::_specificOAuthError($rbody, $rcode, $rheaders, $resp, $errorData);
        // }
        // if (!$error) {
        //     $error = self::_specificAPIError($rbody, $rcode, $rheaders, $resp, $errorData);
        // }

        // throw $error;
    }

    /**
     * @static
     *
     * @param string $rbody
     * @param int    $rcode
     * @param array  $rheaders
     * @param array  $resp
     * @param array  $errorData
     *
     * @return ApiError
     */
    private static function _specificAPIError($rbody, $rcode, $rheaders, $resp, $errorData)
    {
        $msg = isset($errorData['message']) ? $errorData['message'] : null;
        $param = isset($errorData['param']) ? $errorData['param'] : null;
        $code = isset($errorData['code']) ? $errorData['code'] : null;
        $type = isset($errorData['type']) ? $errorData['type'] : null;
        $declineCode = isset($errorData['decline_code']) ? $errorData['decline_code'] : null;

        switch ($rcode) {
            case 400:
                // 'rate_limit' code is deprecated, but left here for backwards compatibility
                // for API versions earlier than 2015-09-08
                if ('rate_limit' === $code) {
                    return RateLimit::factory($msg, $rcode, $rbody, $resp, $rheaders, $code, $param);
                }
                if ('idempotency_error' === $type) {
                    return Idempotency::factory($msg, $rcode, $rbody, $resp, $rheaders, $code);
                }

                // no break
            case 404:
                return InvalidRequest::factory($msg, $rcode, $rbody, $resp, $rheaders, $code, $param);
            case 401:
                return Authentication::factory($msg, $rcode, $rbody, $resp, $rheaders, $code);
            case 402:
                return Card::factory($msg, $rcode, $rbody, $resp, $rheaders, $code, $declineCode, $param);
            case 403:
                return Permission::factory($msg, $rcode, $rbody, $resp, $rheaders, $code);
            case 429:
                return RateLimit::factory($msg, $rcode, $rbody, $resp, $rheaders, $code, $param);
            default:
                return UnknownApiError::factory($msg, $rcode, $rbody, $resp, $rheaders, $code);
        }
    }

    /**
     * @static
     *
     * @param bool|string $rbody
     * @param int         $rcode
     * @param array       $rheaders
     * @param array       $resp
     * @param string      $errorCode
     *
     * @return OAuth\OAuthErrorException
     */
    private static function _specificOAuthError($rbody, $rcode, $rheaders, $resp, $errorCode)
    {
        $description = isset($resp['error_description']) ? $resp['error_description'] : $errorCode;

        switch ($errorCode) {
            case 'invalid_client':
                return OAuth\InvalidClientException::factory($description, $rcode, $rbody, $resp, $rheaders, $errorCode);
            case 'invalid_grant':
                return OAuth\InvalidGrantException::factory($description, $rcode, $rbody, $resp, $rheaders, $errorCode);
            case 'invalid_request':
                return OAuth\InvalidRequestException::factory($description, $rcode, $rbody, $resp, $rheaders, $errorCode);
            case 'invalid_scope':
                return OAuth\InvalidScopeException::factory($description, $rcode, $rbody, $resp, $rheaders, $errorCode);
            case 'unsupported_grant_type':
                return OAuth\UnsupportedGrantTypeException::factory($description, $rcode, $rbody, $resp, $rheaders, $errorCode);
            case 'unsupported_response_type':
                return OAuth\UnsupportedResponseTypeException::factory($description, $rcode, $rbody, $resp, $rheaders, $errorCode);
            default:
                return OAuth\UnknownOAuthErrorException::factory($description, $rcode, $rbody, $resp, $rheaders, $errorCode);
        }
    }

    /**
     * @static
     *
     * @param null|array $appInfo
     *
     * @return null|string
     */
    private static function _formatAppInfo($appInfo)
    {
        if (null !== $appInfo) {
            $string = $appInfo['name'];
            if (null !== $appInfo['version']) {
                $string .= '/' . $appInfo['version'];
            }
            if (null !== $appInfo['url']) {
                $string .= ' (' . $appInfo['url'] . ')';
            }

            return $string;
        }

        return null;
    }

    /**
     * @static
     *
     * @param string $apiKey
     * @param null   $clientInfo
     *
     * @return array
     */
    private static function _defaultHeaders($apiKey, $clientInfo = null)
    {
        // $uaString = 'Paystack/v1 PhpBindings/' . Paystack::VERSION;

        // $langVersion = \PHP_VERSION;
        // $uname_disabled = \in_array('php_uname', \explode(',', \ini_get('disable_functions')), true);
        // $uname = $uname_disabled ? '(disabled)' : \php_uname();

        // $appInfo = Paystack::getAppInfo();
        // $ua = [
        //     'bindings_version' => Paystack::VERSION,
        //     'lang' => 'php',
        //     'lang_version' => $langVersion,
        //     'publisher' => 'paystack',
        //     'uname' => $uname,
        // ];
        // if ($clientInfo) {
        //     $ua = \array_merge($clientInfo, $ua);
        // }
        // if (null !== $appInfo) {
        //     $uaString .= ' ' . self::_formatAppInfo($appInfo);
        //     $ua['application'] = $appInfo;
        // }

        return [
            // 'X-Paystack-Client-User-Agent' => \json_encode($ua),
            // 'User-Agent' => $uaString,
            'Authorization' => 'Bearer ' . $apiKey,
        ];
    }

    /**
     * @param string $method
     * @param string $url
     * @param array $params
     * @param array $headers
     *
     * @throws Authentication
     * @throws ApiConnection
     *
     * @return array
     */
    private function _requestRaw($method, $url, $params, $headers)
    {
        $myApiKey = $this->_apiKey;
        if (!$myApiKey) {
            $myApiKey = Paystack::$apiKey;
        }

        if (!$myApiKey) {
            $msg = 'No API key provided.  (HINT: set your API key using '
              . '"Paystack::setApiKey(<API-KEY>)".  You can generate API keys from '
              . 'the Paystack web interface.  See https://dashboard.paystack.com/#/settings/developer for '
              . 'details';

            throw Authentication::failedAuth($msg);
        }

        // Clients can supply arbitrary additional keys to be included in the
        // X-Paystack-Client-User-Agent header via the optional getUserAgentInfo()
        // method
        $clientUAInfo = null;
        // if (\method_exists($this->httpClient(), 'getUserAgentInfo')) {
        //     $clientUAInfo = $this->httpClient()->getUserAgentInfo();
        // }

        $absUrl = $this->_apiBase . $url;
        $params = self::_encodeObjects($params);
        $defaultHeaders = $this->_defaultHeaders($myApiKey, $clientUAInfo);
        // if (Paystack::$apiVersion) {
        //     $defaultHeaders['Paystack-Version'] = Paystack::$apiVersion;
        // }

        // if (Paystack::$accountId) {
        //     $defaultHeaders['Paystack-Account'] = Paystack::$accountId;
        // }

        // if (Paystack::$enableTelemetry && null !== self::$requestTelemetry) {
        //     $defaultHeaders['X-Paystack-Client-Telemetry'] = self::_telemetryJson(self::$requestTelemetry);
        // }

        $hasFile = false;
        foreach ($params as $k => $v) {
            if (\is_resource($v)) {
                $hasFile = true;
                $params[$k] = self::_processResourceParam($v);
            } elseif ($v instanceof \CURLFile) {
                $hasFile = true;
            }
        }

        if ($hasFile) {
            $defaultHeaders['Content-Type'] = 'multipart/form-data';
        } else {
            $defaultHeaders['Content-Type'] = 'application/json';
            // $defaultHeaders['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        $combinedHeaders = \array_merge($defaultHeaders, $headers);
        // $rawHeaders = [];

        // foreach ($combinedHeaders as $header => $value) {
        //     $rawHeaders[] = $header . ': ' . $value;
        // }

        $requestStartMs = Util::currentTimeMillis();

        // list($rbody, $rcode, $rheaders) = $this->httpClient()->request(
        $res = $this->httpClient()->request(
            $method,
            $absUrl,
            [
                "headers" => $combinedHeaders,
                "json" => $params
            ]
            // $hasFile
        );

        // if (isset($rheaders['request-id'], $rheaders['request-id'][0])) {
        //     self::$requestTelemetry = new RequestTelemetry(
        //         $rheaders['request-id'][0],
        //         Util::currentTimeMillis() - $requestStartMs
        //     );
        // }

        return [$res, $myApiKey];
    }

    /**
     * @param resource $resource
     *
     * @throws InvalidArgument
     *
     * @return \CURLFile|string
     */
    private function _processResourceParam($resource)
    {
        if ('stream' !== \get_resource_type($resource)) {
            throw new InvalidArgument(
                'Attempted to upload a resource that is not a stream'
            );
        }

        $metaData = \stream_get_meta_data($resource);
        if ('plainfile' !== $metaData['wrapper_type']) {
            throw new InvalidArgumentException(
                'Only plainfile resource streams are supported'
            );
        }

        // We don't have the filename or mimetype, but the API doesn't care
        return new \CURLFile($metaData['uri']);
    }

    /**
     * @param string $rbody
     * @param int    $rcode
     * @param array  $rheaders
     *
     * @throws Exception\UnexpectedValueException
     * @throws Exception\ApiErrorException
     *
     * @return array
     */
    private function _interpretResponse($rbody, $rcode, $rheaders)
    {
        $resp = \json_decode($rbody, true);
        $jsonError = \json_last_error();
        if (null === $resp && \JSON_ERROR_NONE !== $jsonError) {
            $msg = "Invalid response body from API: {$rbody} "
              . "(HTTP response code was {$rcode}, json_last_error() was {$jsonError})";

            throw UnexpectedValue::badValue($msg);
        }

        if ($rcode < 200 || $rcode >= 300) {
            $this->handleErrorResponse($rbody, $rcode, $rheaders, $resp);
        }

        return $resp;
    }

    /**
     * @static
     *
     * @param HttpClient\ClientInterface $client
     */
    public static function setHttpClient($client)
    {
        self::$_httpClient = $client;
    }

    /**
     * @static
     *
     * Resets any stateful telemetry data
     */
    public static function resetTelemetry()
    {
        self::$requestTelemetry = null;
    }

    /**
     * @return HttpClient\ClientInterface
     */
    private function httpClient()
    {
        if (!self::$_httpClient) {
            self::$_httpClient = new Client();
            // self::$_httpClient = HttpClient\CurlClient::instance();
        }

        return self::$_httpClient;
    }
}