<?php

namespace Cuitcode\Paystack\Console\Commands;

use Cuitcode\Paystack\Paystack;
use Illuminate\Console\Command;
use Cuitcode\Paystack\Models\Plan;
use Cuitcode\Paystack\Transaction;
use Cuitcode\Paystack\Models\Retries;
use Cuitcode\Paystack\Models\Subscription;

class RetryFailedPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'paystack:start-retries';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start all failed payments debit retries';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @param  \App\Support\DripEmailer  $drip
     * @return mixed
     */
    public function handle()
    {
        $retries = Retries::active()->get();

        foreach ($retries as $retry) {
            $subscription = Subscription::find($retry->subscription_id);

            if ($retry->isValid()) {
                $this->info('Start retry for ' . $retry->subscription_id);

                $plan = Plan::where($subscription->plan_code)->first();
                $user = $subscription->user();

                $resp = Transaction::chargeAuthorization([
                    'authorization_code' => $retry->authorization_code,
                    'email' => $user->email,
                    'amount' => $plan->amount,
                ], Paystack::paystackOptions());

                if ($resp['data']['status'] == 'success') {
                    $this->info('Successfully retried ' . $retry->subscription_id);
                    $retry->status = Retries::STATUS_INACTIVE;
                } else {
                    $this->error('Failed to retry ' . $retry->subscription_id);
                    $retry->count++;
                }
            } else {
                $this->info('Disabling subscription with ID ' . $retry->subscription_id);
                $subscription->disable();
                $retry->status = Retries::STATUS_INACTIVE;
            }

            $retry->save();
        }
    }
}
