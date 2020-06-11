<?php

namespace Cuitcode\Paystack\Concerns;

use Cuitcode\Paystack\Models\Subscription;
use Cuitcode\Paystack\SubscriptionBuilder;

trait ManagesSubscriptions
{
    /**
     * Determine if the Paystack model has a given subscription.
     *
     * @param  string  $name
     * @param  string|null  $plan
     * @return bool
     */
    public function subscribed($code, $plan_code = null)
    {
        $subscription = $this->subscription($code);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        return $plan_code ? $subscription->hasPlan($plan_code) : true;
    }

    /**
     * Get a subscription instance by name.
     *
     * @param  string  $name
     * @return \Cuitcode\Paystack\Subscription|null
     */
    public function subscription($code = null)
    {
        return $this->subscriptions->sortByDesc(function (Subscription $subscription) {
            return $subscription->created_at->getTimestamp();
        })->first(function (Subscription $subscription) use ($code) {
            if(null === $code) return $subscription->status === 'active';
            return $subscription->code === $code;
        });
    }

    /**
     * Get all of the subscriptions for the Paystack model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, $this->getForeignKey())->orderBy('created_at', 'desc');
    }

    /**
     * Determine if the customer's subscription has an incomplete payment.
     *
     * @param  string  $name
     * @return bool
     */
    public function hasIncompletePayment($name = 'default')
    {
        if ($subscription = $this->subscription($name)) {
            return $subscription->hasIncompletePayment();
        }

        return false;
    }

    /**
     * Determine if the Paystack model is actively subscribed to one of the given plans.
     *
     * @param  string|string[]  $plans
     * @param  string  $name
     * @return bool
     */
    public function subscribedToPlan($plans, $code)
    {
        $subscription = $this->subscription($code);

        if (! $subscription || ! $subscription->valid()) {
            return false;
        }

        foreach ((array) $plans as $plan) {
            if ($subscription->hasPlan($plan)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if the entity has a valid subscription on the given plan.
     *
     * @param  string  $plan
     * @return bool
     */
    public function onPlan($plan_code)
    {
        return ! is_null($this->subscriptions->first(function (Subscription $subscription) use ($plan_code) {
            return $subscription->valid() && $subscription->hasPlan($plan_code);
        }));
    }

    /**
     * Get the tax percentage to apply to the subscription.
     *
     * @return int|float
     * @deprecated Please migrate to the new Tax Rates API.
     */
    public function taxPercentage()
    {
        return 0;
    }

    /**
     * Get the tax rates to apply to the subscription.
     *
     * @return array
     */
    public function taxRates()
    {
        return [];
    }

    /**
     * Get the tax rates to apply to individual subscription items.
     *
     * @return array
     */
    public function planTaxRates()
    {
        return [];
    }
}