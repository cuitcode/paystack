<?php

namespace Cuitcode\Paystack;

use Cuitcode\Paystack\Utils\Set;
use Cuitcode\Paystack\ApiResource;

class Subscription extends ApiResource
{
    const OBJECT_NAME = 'subscription';

    use ApiOperations\All;
    use ApiOperations\Create;
    use ApiOperations\Retrieve;
    use ApiOperations\Update;

    const STATUS_ACTIVE = 'active';
    const STATUS_CANCELED = 'canceled';
    const STATUS_INCOMPLETE = 'incomplete';
    const STATUS_INCOMPLETE_EXPIRED = 'incomplete_expired';
    const STATUS_PAST_DUE = 'past_due';
    const STATUS_TRIALING = 'trialing';
    const STATUS_UNPAID = 'unpaid';

    use ApiOperations\Delete {
        delete as protected _delete;
    }

    public static function getSavedNestedResources()
    {
        static $savedNestedResources = null;
        if (null === $savedNestedResources) {
            $savedNestedResources = new Set([
                'source',
            ]);
        }

        return $savedNestedResources;
    }

    /**
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \Cuitcode\Paystack\Exception\ApiErrorException if the request fails
     *
     * @return \Cuitcode\Paystack\Subscription the deleted subscription
     */
    public function cancel($params = null, $opts = null)
    {
        // return $this->_delete($params, $opts);
        $url = $this->instanceUrl() . '/cancel';
        list($response, $opts) = $this->_request('post', $url, $params, $opts);
        $this->refreshFrom(['discount' => null], $opts, true);
    }

    /**
     * @param null|array $params
     * @param null|array|string $opts
     *
     * @throws \Cuitcode\Paystack\Exception\ApiErrorException if the request fails
     *
     * @return \Cuitcode\Paystack\Subscription the updated subscription
     */
    public function deleteDiscount($params = null, $opts = null)
    {
        $url = $this->instanceUrl() . '/discount';
        list($response, $opts) = $this->_request('delete', $url, $params, $opts);
        $this->refreshFrom(['discount' => null], $opts, true);
    }
}