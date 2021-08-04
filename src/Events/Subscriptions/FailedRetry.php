<?php

namespace Cuitcode\Paystack\Events\Subscriptions;

use Cuitcode\Paystack\Models\Retries;
use Illuminate\Queue\SerializesModels;
use Cuitcode\Paystack\Models\Subscription;
use Cuitcode\Paystack\Models\Authorization;
use Illuminate\Foundation\Events\Dispatchable;

class FailedRetry
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
    public function __construct(Authorization $authorization, Retries $retry, Subscription $subscription)
    {
        $this->authorization = $authorization;
        $this->subscription = $subscription;
        $this->retry = $retry;
    }
}
