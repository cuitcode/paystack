<?php

namespace Cuitcode\Paystack\Exceptions;

use Exception;
use Cuitcode\Paystack\Models\Subscription;

class SubscriptionUpdateFailure extends Exception
{
    /**
     * Create a new SubscriptionUpdateFailure instance.
     *
     * @param  C\Cuitcode\Paystack\Models\Subscription  $subscription
     * @return static
     */
    public static function incompleteSubscription(Subscription $subscription)
    {
        return new static(
            "The subscription \"{$subscription->code}\" cannot be updated because its payment is incomplete."
        );
    }

    /**
     * Create a new SubscriptionUpdateFailure instance.
     *
     * @param  \Cuitcode\Paystack\Models\Subscription  $subscription
     * @param  string  $plan
     * @return static
     */
    public static function duplicatePlan(Subscription $subscription, $plan)
    {
        return new static(
            "The plan \"$plan\" is already attached to subscription \"{$subscription->code}\"."
        );
    }
}
