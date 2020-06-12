<?php

namespace Cuitcode\Paystack\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
// use Cuitcode\Paystack\Concerns\Prorates;
use Cuitcode\Paystack\Exceptions\IncompletePayment;
use Cuitcode\Paystack\Exceptions\SubscriptionUpdateFailure;
use LogicException;
use Cuitcode\Paystack\Subscription as PaystackSubscription;

class Subscription extends Model
{
    // use Prorates;

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    // protected $with = ['items'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'trial_ends_at', 'ends_at',
        'created_at', 'updated_at',
    ];

    /**
     * The date on which the billing cycle should be anchored.
     *
     * @var string|null
     */
    protected $billingCycleAnchor = null;

    /**
     * Get the user that owns the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->owner();
    }

    /**
     * Get the model related to the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        $model = config('cc_paystack.model');

        return $this->belongsTo($model, (new $model)->getForeignKey());
    }

    /**
     * Get the subscription items related to the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function items()
    {
        return $this->hasMany(SubscriptionItem::class);
    }

    /**
     * Determine if the subscription has multiple plans.
     *
     * @return bool
     */
    public function hasMultiplePlans()
    {
        return is_null($this->plan_code);
    }

    /**
     * Determine if the subscription has a single plan.
     *
     * @return bool
     */
    public function hasSinglePlan()
    {
        return ! $this->hasMultiplePlans();
    }

    /**
     * Determine if the subscription has a specific plan.
     *
     * @param  string  $plan_code
     * @return bool
     */
    public function hasPlan($plan_code)
    {
        if ($this->hasMultiplePlans()) {
            return $this->items->contains(function (SubscriptionItem $item) use ($plan_code) {
                return $item->plan_code === $plan_code;
            });
        }

        return $this->plan_code === $plan_code;
    }

    /**
     * Get the subscription item for the given plan.
     *
     * @param  string  $plan
     * @return \Cuitcode\Paystack\SubscriptionItem
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findItemOrFail($plan_code)
    {
        return $this->items()->where('plan_code', $plan_code)->firstOrFail();
    }

    /**
     * Determine if the subscription is active, on trial, or within its grace period.
     *
     * @return bool
     */
    public function valid()
    {
        return $this->active();
    }

    /**
     * Determine if the subscription is incomplete.
     *
     * @return bool
     */
    public function incomplete()
    {
        return $this->status === PaystackSubscription::STATUS_INCOMPLETE;
    }

    /**
     * Filter query by incomplete.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeIncomplete($query)
    {
        $query->where('status', PaystackSubscription::STATUS_INCOMPLETE);
    }

    /**
     * Determine if the subscription is past due.
     *
     * @return bool
     */
    public function pastDue()
    {
        return $this->status === PaystackSubscription::STATUS_PAST_DUE;
    }

    /**
     * Filter query by past due.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopePastDue($query)
    {
        $query->where('status', PaystackSubscription::STATUS_PAST_DUE);
    }

    /**
     * Determine if the subscription is active.
     *
     * @return bool
     */
    public function active()
    {
        return (is_null($this->ends_at) || $this->onGracePeriod()) &&
            $this->status !== PaystackSubscription::STATUS_INCOMPLETE &&
            $this->status !== PaystackSubscription::STATUS_INCOMPLETE_EXPIRED &&
            (! Paystack::$deactivatePastDue || $this->status !== PaystackSubscription::STATUS_PAST_DUE) &&
            $this->status !== PaystackSubscription::STATUS_UNPAID;
    }

    /**
     * Filter query by active.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeActive($query)
    {
        $query->where(function ($query) {
            $query->whereNull('ends_at')
                ->orWhere(function ($query) {
                    $query->onGracePeriod();
                });
        })->where('status', '!=', PaystackSubscription::STATUS_INCOMPLETE)
            ->where('status', '!=', PaystackSubscription::STATUS_INCOMPLETE_EXPIRED)
            ->where('status', '!=', PaystackSubscription::STATUS_UNPAID);

        if (Paystack::$deactivatePastDue) {
            $query->where('status', '!=', PaystackSubscription::STATUS_PAST_DUE);
        }
    }

    /**
     * Sync the Paystack status of the subscription.
     *
     * @return void
     */
    public function syncPaystackStatus()
    {
        $subscription = $this->asPaystackSubscription();

        $this->status = $subscription->status;

        $this->save();
    }

    /**
     * Determine if the subscription is recurring and not on trial.
     *
     * @return bool
     */
    public function recurring()
    {
        return ! $this->onTrial() && ! $this->cancelled();
    }

    /**
     * Filter query by recurring.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeRecurring($query)
    {
        $query->notOnTrial()->notCancelled();
    }

    /**
     * Determine if the subscription is no longer active.
     *
     * @return bool
     */
    public function cancelled()
    {
        return ! is_null($this->ends_at);
    }

    /**
     * Filter query by cancelled.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeCancelled($query)
    {
        $query->whereNotNull('ends_at');
    }

    /**
     * Filter query by not cancelled.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeNotCancelled($query)
    {
        $query->whereNull('ends_at');
    }

    /**
     * Determine if the subscription has ended and the grace period has expired.
     *
     * @return bool
     */
    public function ended()
    {
        return $this->cancelled() && ! $this->onGracePeriod();
    }

    /**
     * Filter query by ended.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeEnded($query)
    {
        $query->cancelled()->notOnGracePeriod();
    }

    /**
     * Determine if the subscription is within its trial period.
     *
     * @return bool
     */
    public function onTrial()
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /**
     * Filter query by on trial.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeOnTrial($query)
    {
        $query->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', Carbon::now());
    }

    /**
     * Filter query by not on trial.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeNotOnTrial($query)
    {
        $query->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<=', Carbon::now());
    }

    /**
     * Determine if the subscription is within its grace period after cancellation.
     *
     * @return bool
     */
    public function onGracePeriod()
    {
        return $this->ends_at && $this->ends_at->isFuture();
    }

    /**
     * Filter query by on grace period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeOnGracePeriod($query)
    {
        $query->whereNotNull('ends_at')->where('ends_at', '>', Carbon::now());
    }

    /**
     * Filter query by not on grace period.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeNotOnGracePeriod($query)
    {
        $query->whereNull('ends_at')->orWhere('ends_at', '<=', Carbon::now());
    }

    /**
     * Increment the quantity of the subscription.
     *
     * @param  int  $count
     * @param  string|null  $plan
     * @return $this
     *
     * @throws \Cuitcode\Paystack\Exceptions\SubscriptionUpdateFailure
     */
    public function incrementQuantity($count = 1, $plan = null)
    {
        $this->guardAgainstIncomplete();

        if ($plan) {
            $this->findItemOrFail($plan)->setProrate($this->prorate)->incrementQuantity($count);

            return $this->refresh();
        }

        $this->guardAgainstMultiplePlans();

        $this->updateQuantity($this->quantity + $count, $plan);

        return $this;
    }

    /**
     *  Increment the quantity of the subscription, and invoice immediately.
     *
     * @param  int  $count
     * @param  string|null  $plan
     * @return $this
     *
     * @throws \Cuitcode\Paystack\Exceptions\IncompletePayment
     * @throws \Cuitcode\Paystack\Exceptions\SubscriptionUpdateFailure
     */
    public function incrementAndInvoice($count = 1, $plan = null)
    {
        $this->guardAgainstIncomplete();

        if ($plan) {
            $this->findItemOrFail($plan)->setProrate($this->prorate)->incrementQuantity($count);

            return $this->refresh();
        }

        $this->guardAgainstMultiplePlans();

        $this->incrementQuantity($count, $plan);

        $this->invoice();

        return $this;
    }

    /**
     * Decrement the quantity of the subscription.
     *
     * @param  int  $count
     * @param  string|null  $plan
     * @return $this
     *
     * @throws \Cuitcode\Paystack\Exceptions\SubscriptionUpdateFailure
     */
    public function decrementQuantity($count = 1, $plan = null)
    {
        $this->guardAgainstIncomplete();

        if ($plan) {
            $this->findItemOrFail($plan)->setProrate($this->prorate)->decrementQuantity($count);

            return $this->refresh();
        }

        $this->guardAgainstMultiplePlans();

        return $this->updateQuantity(max(1, $this->quantity - $count), $plan);
    }

    /**
     * Update the quantity of the subscription.
     *
     * @param  int  $quantity
     * @param  string|null  $plan
     * @return $this
     *
     * @throws \Cuitcode\Paystack\Exceptions\SubscriptionUpdateFailure
     */
    public function updateQuantity($quantity, $plan = null)
    {
        $this->guardAgainstIncomplete();

        if ($plan) {
            $this->findItemOrFail($plan)->setProrate($this->prorate)->updateQuantity($quantity);

            return $this->refresh();
        }

        $this->guardAgainstMultiplePlans();

        $paystackSubscription = $this->asPaystackSubscription();

        $paystackSubscription->quantity = $quantity;

        $paystackSubscription->proration_behavior = $this->prorateBehavior();

        $paystackSubscription->save();

        $this->quantity = $quantity;

        $this->save();

        return $this;
    }

    /**
     * Change the billing cycle anchor on a plan change.
     *
     * @param  \DateTimeInterface|int|string  $date
     * @return $this
     */
    public function anchorBillingCycleOn($date = 'now')
    {
        if ($date instanceof DateTimeInterface) {
            $date = $date->getTimestamp();
        }

        $this->billingCycleAnchor = $date;

        return $this;
    }

    /**
     * Force the trial to end immediately.
     *
     * This method must be combined with swap, resume, etc.
     *
     * @return $this
     */
    public function skipTrial()
    {
        $this->trial_ends_at = null;

        return $this;
    }

    /**
     * Extend an existing subscription's trial period.
     *
     * @param  \Carbon\CarbonInterface  $date
     * @return $this
     */
    public function extendTrial(CarbonInterface $date)
    {
        if (! $date->isFuture()) {
            throw new InvalidArgumentException("Extending a subscription's trial requires a date in the future.");
        }

        $subscription = $this->asPaystackSubscription();

        $subscription->trial_end = $date->getTimestamp();

        $subscription->save();

        $this->trial_ends_at = $date;

        $this->save();

        return $this;
    }

    /**
     * Swap the subscription to new Paystack plans.
     *
     * @param  string|string[]  $plans
     * @param  array  $options
     * @return $this
     *
     * @throws \Cuitcode\Paystack\Exceptions\SubscriptionUpdateFailure
     */
    public function swap($plans, $options = [])
    {
        if (empty($plans = (array) $plans)) {
            throw new InvalidArgumentException('Please provide at least one plan when swapping.');
        }

        $this->guardAgainstIncomplete();

        $items = $this->mergeItemsThatShouldBeDeletedDuringSwap(
            $this->parseSwapPlans($plans)
        );

        $paystackSubscription = PaystackSubscription::update(
            $this->paystack_id, $this->getSwapOptions($items, $options), $this->owner->paystackOptions()
        );

        $this->fill([
            'plan_code' => $paystackSubscription->plan ? $paystackSubscription->plan->id : null,
            'quantity' => $paystackSubscription->quantity,
            'ends_at' => null,
        ])->save();

        foreach ($paystackSubscription->items as $item) {
            $this->items()->updateOrCreate([
                'paystack_id' => $item->id,
            ], [
                'plan_code' => $item->plan->id,
                'quantity' => $item->quantity,
            ]);
        }

        // Delete items that aren't attached to the subscription anymore...
        $this->items()->whereNotIn('plan_code', $items->pluck('plan')->filter())->delete();

        $this->unsetRelation('items');

        return $this;
    }

    /**
     * Swap the subscription to new Paystack plans, and invoice immediately.
     *
     * @param  string|string[]  $plans
     * @param  array  $options
     * @return $this
     *
     * @throws \Cuitcode\Paystack\Exceptions\IncompletePayment
     * @throws \Cuitcode\Paystack\Exceptions\SubscriptionUpdateFailure
     */
    public function swapAndInvoice($plans, $options = [])
    {
        $subscription = $this->swap($plans, $options);

        $this->invoice();

        return $subscription;
    }

    /**
     * Parse the given plans for a swap operation.
     *
     * @param  string|string[]  $plans
     * @return \Illuminate\Support\Collection
     */
    protected function parseSwapPlans($plans)
    {
        return collect($plans)->mapWithKeys(function ($options, $plan) {
            $plan = is_string($options) ? $options : $plan;
            $options = is_string($options) ? [] : $options;

            return [$plan => array_merge([
                'plan' => $plan,
                'tax_rates' => $this->getPlanTaxRatesForPayload($plan),
            ], $options)];
        });
    }

    /**
     * Merge the items that should be deleted during swap into the given items collection.
     *
     * @param  \Illuminate\Support\Collection  $items
     * @return \Illuminate\Support\Collection
     */
    protected function mergeItemsThatShouldBeDeletedDuringSwap(Collection $items)
    {
        /** @var \Paystack\SubscriptionItem $paystackSubscriptionItem */
        foreach ($this->asPaystackSubscription()->items->data as $paystackSubscriptionItem) {
            $plan = $paystackSubscriptionItem->plan->id;

            if (! $item = $items->get($plan, [])) {
                $item['deleted'] = true;
            }

            $items->put($plan, $item + ['id' => $paystackSubscriptionItem->id]);
        }

        return $items;
    }

    /**
     * Get the options array for a swap operation.
     *
     * @param  \Illuminate\Support\Collection  $items
     * @param  array  $options
     * @return array
     */
    protected function getSwapOptions(Collection $items, $options)
    {
        $options = array_merge([
            'items' => $items->values()->all(),
            'proration_behavior' => $this->prorateBehavior(),
            'cancel_at_period_end' => false,
        ], $options);

        if (! is_null($this->billingCycleAnchor)) {
            $options['billing_cycle_anchor'] = $this->billingCycleAnchor;
        }

        $options['trial_end'] = $this->onTrial()
                        ? $this->trial_ends_at->getTimestamp()
                        : 'now';

        return $options;
    }

    /**
     * Add a new Paystack plan to the subscription.
     *
     * @param  string  $plan
     * @param  int  $quantity
     * @param  array  $options
     * @return $this
     *
     * @throws \Cuitcode\Paystack\Exceptions\SubscriptionUpdateFailure
     */
    public function addPlan($plan_code, $quantity = 1, $options = [])
    {
        $this->guardAgainstIncomplete();

        if ($this->items->contains('plan_code', $plan_code)) {
            throw SubscriptionUpdateFailure::duplicatePlan($this, $plan_code);
        }

        $subscription = $this->asPaystackSubscription();

        $item = $subscription->items->create(array_merge([
            'plan_code' => $plan_code,
            'quantity' => $quantity,
            'tax_rates' => $this->getPlanTaxRatesForPayload($plan_code),
            'proration_behavior' => $this->prorateBehavior(),
        ], $options));

        $this->items()->create([
            'paystack_id' => $item->id,
            'plan_code' => $plan_code,
            'quantity' => $quantity,
        ]);

        $this->unsetRelation('items');

        if ($this->hasSinglePlan()) {
            $this->fill([
                'plan_code' => null,
                'quantity' => null,
            ])->save();
        }

        return $this;
    }

    /**
     * Add a new Paystack plan to the subscription, and invoice immediately.
     *
     * @param  string  $plan
     * @param  int  $quantity
     * @param  array  $options
     * @return $this
     *
     * @throws \Cuitcode\Paystack\Exceptions\IncompletePayment
     * @throws \Cuitcode\Paystack\Exceptions\SubscriptionUpdateFailure
     */
    public function addPlanAndInvoice($plan, $quantity = 1, $options = [])
    {
        $subscription = $this->addPlan($plan, $quantity, $options);

        $this->invoice();

        return $subscription;
    }

    /**
     * Remove a Paystack plan from the subscription.
     *
     * @param  string  $plan
     * @return $this
     *
     * @throws \Cuitcode\Paystack\Exceptions\SubscriptionUpdateFailure
     */
    public function removePlan($plan)
    {
        if ($this->hasSinglePlan()) {
            throw SubscriptionUpdateFailure::cannotDeleteLastPlan($this);
        }

        $item = $this->findItemOrFail($plan);

        $item->asPaystackSubscriptionItem()->delete([
            'proration_behavior' => $this->prorateBehavior(),
        ]);

        $this->items()->where('plan_code', $plan)->delete();

        $this->unsetRelation('items');

        if ($this->items()->count() < 2) {
            $item = $this->items()->first();

            $this->fill([
                'plan_code' => $item->plan_code,
                'quantity' => $item->quantity,
            ])->save();
        }

        return $this;
    }

    /**
     * Cancel the subscription at the end of the billing period.
     *
     * @return $this
     */
    public function cancel()
    {
        $subscription = $this->asPaystackSubscription();

        $subscription->cancel_at_period_end = true;

        $subscription = $subscription->save();

        $this->status = $subscription->status;

        $this->save();

        return $this;
    }

    /**
     * Cancel the subscription immediately.
     *
     * @return $this
     */
    public function cancelNow()
    {
        $subscription = $this->asPaystackSubscription();

        $subscription->cancel([
            "code" => $subscription['data']->subscription_code,
            "token" => $subscription['data']->email_token, 
        ], $this->owner->paystackOptions());

        $this->markAsCancelled();

        return $this;
    }

    /**
     * Mark the subscription as cancelled.
     *
     * @return void
     * @internal
     */
    public function markAsCancelled()
    {
        $this->fill([
            'status' => PaystackSubscription::STATUS_CANCELED,
            'ends_at' => Carbon::now(),
        ])->save();
    }

    /**
     * Resume the cancelled subscription.
     *
     * @return $this
     *
     * @throws \LogicException
     */
    public function resume()
    {
        if (! $this->onGracePeriod()) {
            throw new LogicException('Unable to resume subscription that is not within grace period.');
        }

        $subscription = $this->asPaystackSubscription();

        $subscription->cancel_at_period_end = false;

        if ($this->onTrial()) {
            $subscription->trial_end = $this->trial_ends_at->getTimestamp();
        } else {
            $subscription->trial_end = 'now';
        }

        $subscription = $subscription->save();

        // Finally, we will remove the ending timestamp from the user's record in the
        // local database to indicate that the subscription is active again and is
        // no longer "cancelled". Then we will save this record in the database.
        $this->fill([
            'status' => $subscription->status,
            'ends_at' => null,
        ])->save();

        return $this;
    }

    /**
     * Invoice the subscription outside of the regular billing cycle.
     *
     * @param  array  $options
     * @return \Cuitcode\Paystack\Invoice|bool
     *
     * @throws \Cuitcode\Paystack\Exceptions\IncompletePayment
     */
    public function invoice(array $options = [])
    {
        try {
            return $this->user->invoice(array_merge($options, ['subscription' => $this->paystack_id]));
        } catch (IncompletePayment $exception) {
            // Set the new Paystack subscription status immediately when payment fails...
            $this->fill([
                'status' => $exception->payment->invoice->subscription->status,
            ])->save();

            throw $exception;
        }
    }

    /**
     * Sync the tax percentage of the user to the subscription.
     *
     * @return void
     * @deprecated Please migrate to the new Tax Rates API.
     */
    public function syncTaxPercentage()
    {
        $subscription = $this->asPaystackSubscription();

        $subscription->tax_percent = $this->user->taxPercentage();

        $subscription->save();
    }

    /**
     * Sync the tax rates of the user to the subscription.
     *
     * @return void
     */
    public function syncTaxRates()
    {
        $paystackSubscription = $this->asPaystackSubscription();

        $paystackSubscription->default_tax_rates = $this->user->taxRates();

        $paystackSubscription->save();

        foreach ($this->items as $item) {
            $paystackSubscriptionItem = $item->asPaystackSubscriptionItem();

            $paystackSubscriptionItem->tax_rates = $this->getPlanTaxRatesForPayload($item->plan_code);

            $paystackSubscriptionItem->save();
        }
    }

    /**
     * Get the plan tax rates for the Paystack payload.
     *
     * @param  string  $plan
     * @return array|null
     */
    public function getPlanTaxRatesForPayload($plan)
    {
        if ($taxRates = $this->owner->planTaxRates()) {
            return $taxRates[$plan] ?? null;
        }
    }

    /**
     * Determine if the subscription has an incomplete payment.
     *
     * @return bool
     */
    public function hasIncompletePayment()
    {
        return $this->pastDue() || $this->incomplete();
    }

    /**
     * Get the latest payment for a Subscription.
     *
     * @return \Cuitcode\Paystack\Payment|null
     */
    public function latestPayment()
    {
        $paymentIntent = $this->asPaystackSubscription(['latest_invoice.payment_intent'])
            ->latest_invoice
            ->payment_intent;

        return $paymentIntent
            ? new Payment($paymentIntent)
            : null;
    }

    /**
     * Make sure a subscription is not incomplete when performing changes.
     *
     * @return void
     *
     * @throws \Cuitcode\Paystack\Exceptions\SubscriptionUpdateFailure
     */
    public function guardAgainstIncomplete()
    {
        if ($this->incomplete()) {
            throw SubscriptionUpdateFailure::incompleteSubscription($this);
        }
    }

    /**
     * Make sure a plan argument is provided when the subscription is a multi plan subscription.
     *
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    public function guardAgainstMultiplePlans()
    {
        if ($this->hasMultiplePlans()) {
            throw new InvalidArgumentException(
                'This method requires a plan argument since the subscription has multiple plans.'
            );
        }
    }

    /**
     * Update the underlying Paystack subscription information for the model.
     *
     * @param  array  $options
     * @return \Paystack\Subscription
     */
    public function updatePaystackSubscription(array $options = [])
    {
        return PaystackSubscription::update(
            $this->code, $options, $this->owner->paystackOptions()
        );
    }

    /**
     * Get the subscription as a Paystack subscription object.
     *
     * @param  array  $expand
     * @return \Paystack\Subscription
     */
    public function asPaystackSubscription(array $expand = [])
    {
        return PaystackSubscription::retrieve(
            ['id' => $this->code, 'expand' => $expand], $this->owner->paystackOptions()
        );
    }
}