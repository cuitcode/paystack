<?php

namespace Cuitcode\Paystack\Events\Subscriptions;

use Illuminate\Queue\SerializesModels;
use Cuitcode\Paystack\Models\Subscription;
use Illuminate\Foundation\Events\Dispatchable;

class Disabled
{
    use Dispatchable, SerializesModels;

    /**
     * The webhook payload.
     *
     * @var array
     */
    public $payload;

    /**
     * Create a new event instance.
     *
     * @param  array  $payload
     * @return void
     */
    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }
}
