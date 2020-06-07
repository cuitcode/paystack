<?php

namespace Cuitcode\Paystack;

use Illuminate\Database\Eloquent\Model;
use Cuitcode\Paystack\Models\Subscription;
// use Cuitcode\Paystack\Concerns\Prorates;
// use Paystack\SubscriptionItem as PaystackSubscriptionItem;

/**
 * @property \Cuitcode\Paystack\Subscription|null $subscription
 */
class SubscriptionItem extends Model
{
    // use Prorates;

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'quantity' => 'integer',
    ];

    /**
     * Get the subscription that the item belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Increment the quantity of the subscription item.
     *
     * @param  int  $count
     * @return $this
     *
     * @throws \Cuitcode\Paystack\Exceptions\SubscriptionUpdateFailure
     */
    public function incrementQuantity($count = 1)
    {
        $this->updateQuantity($this->quantity + $count);

        return $this;
    }

    /**
     *  Increment the quantity of the subscription item, and invoice immediately.
     *
     * @param  int  $count
     * @return $this
     *
     * @throws \Cuitcode\Paystack\Exceptions\IncompletePayment
     * @throws \Cuitcode\Paystack\Exceptions\SubscriptionUpdateFailure
     */
    public function incrementAndInvoice($count = 1)
    {
        $this->incrementQuantity($count);

        $this->subscription->invoice();

        return $this;
    }

    /**
     * Decrement the quantity of the subscription item.
     *
     * @param  int  $count
     * @return $this
     *
     * @throws \Cuitcode\Paystack\Exceptions\SubscriptionUpdateFailure
     */
    public function decrementQuantity($count = 1)
    {
        $this->updateQuantity(max(1, $this->quantity - $count));

        return $this;
    }

    /**
     * Update the quantity of the subscription item.
     *
     * @param  int  $quantity
     * @return $this
     *
     * @throws \Cuitcode\Paystack\Exceptions\SubscriptionUpdateFailure
     */
    public function updateQuantity($quantity)
    {
        $this->subscription->guardAgainstIncomplete();

        $stripeSubscriptionItem = $this->asPaystackSubscriptionItem();

        $stripeSubscriptionItem->quantity = $quantity;

        $stripeSubscriptionItem->proration_behavior = $this->prorateBehavior();

        $stripeSubscriptionItem->save();

        $this->quantity = $quantity;

        $this->save();

        if ($this->subscription->hasSinglePlan()) {
            $this->subscription->quantity = $quantity;

            $this->subscription->save();
        }

        return $this;
    }

    /**
     * Swap the subscription item to a new Paystack plan.
     *
     * @param  string  $plan
     * @param  array  $options
     * @return $this
     *
     * @throws \Cuitcode\Paystack\Exceptions\SubscriptionUpdateFailure
     */
    // public function swap($plan, $options = [])
    // {
    //     $this->subscription->guardAgainstIncomplete();

    //     $options = array_merge([
    //         'plan' => $plan,
    //         'quantity' => $this->quantity,
    //         'proration_behavior' => $this->prorateBehavior(),
    //         'tax_rates' => $this->subscription->getPlanTaxRatesForPayload($plan),
    //     ], $options);

    //     $item = PaystackSubscriptionItem::update(
    //         $this->stripe_id,
    //         $options,
    //         $this->subscription->owner->stripeOptions()
    //     );

    //     $this->fill([
    //         'stripe_plan' => $plan,
    //         'quantity' => $item->quantity,
    //     ])->save();

    //     if ($this->subscription->hasSinglePlan()) {
    //         $this->subscription->fill([
    //             'stripe_plan' => $plan,
    //             'quantity' => $item->quantity,
    //         ])->save();
    //     }

    //     return $this;
    // }

    /**
     * Swap the subscription item to a new Paystack plan, and invoice immediately.
     *
     * @param  string  $plan
     * @param  array  $options
     * @return $this
     *
     * @throws \Cuitcode\Paystack\Exceptions\IncompletePayment
     * @throws \Cuitcode\Paystack\Exceptions\SubscriptionUpdateFailure
     */
    public function swapAndInvoice($plan, $options = [])
    {
        $item = $this->swap($plan, $options);

        $this->subscription->invoice();

        return $item;
    }

    /**
     * Update the underlying Paystack subscription item information for the model.
     *
     * @param  array  $options
     * @return \Paystack\SubscriptionItem
     */
    // public function updatePaystackSubscriptionItem(array $options = [])
    // {
    //     return PaystackSubscriptionItem::update(
    //         $this->stripe_id, $options, $this->subscription->owner->stripeOptions()
    //     );
    // }

    /**
     * Get the subscription as a Paystack subscription item object.
     *
     * @return PaystackSubscriptionItem
     */
    // public function asPaystackSubscriptionItem()
    // {
    //     return PaystackSubscriptionItem::retrieve(
    //         $this->stripe_id, $this->subscription->owner->stripeOptions()
    //     );
    // }
}