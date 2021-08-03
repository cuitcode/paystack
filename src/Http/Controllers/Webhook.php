<?php

namespace Cuitcode\Paystack\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Cuitcode\Paystack\Paystack;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Cuitcode\Paystack\Models\Retries;
use Cuitcode\Paystack\Models\Transaction;
use Cuitcode\Paystack\Models\Subscription;
use Cuitcode\Paystack\Models\Authorization;
use Cuitcode\Paystack\Events\WebhookHandled;
use Cuitcode\Paystack\Events\WebhookReceived;
use Symfony\Component\HttpFoundation\Response;
use Cuitcode\Paystack\Http\Middlewares\VerifySignature;
use Cuitcode\Paystack\Subscription as PaystackSubscription;

class Webhook extends Controller
{
    /**
     * Create a new WebhookController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $secret = config('cc_paystack.test.secret');

        // return live credentials
        if (config('cc_paystack.live_mode')) {
            $secret = config('cc_paystack.live.secret');
        }

        if (null !== $secret) {
            $this->middleware(VerifySignature::class);
        }
    }

    /**
     * Handle a Paystack webhook call.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handleWebhook(Request $request)
    {
        $payload = json_decode($request->getContent(), true);

        Log::info('webhook data' . $request->getContent());

        $method = 'handle' . Str::studly(str_replace('.', '_', $payload['event']));

        WebhookReceived::dispatch($payload);

        if (method_exists($this, $method)) {
            // Log::info('method '. $method);
            $response = $this->{$method}($payload);

            WebhookHandled::dispatch($payload);

            return $response;
        }

        return $this->missingMethod();
    }

    /**
     * Handle customer subscription created.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleSubscriptionCreate(array $payload)
    {
        if ($user = $this->getUserByPaystackCode($payload['data']['customer']['customer_code'])) {
            $data = $payload['data'];
            $subscription = new Subscription;
            $subscription->user_id = $user->id;
            $subscription->code = $data['subscription_code'];
            $subscription->email_token = $data['email_token'];
            $subscription->status = $data['status'];
            $subscription->plan_code = $data['plan']['plan_code'];

            $subscription->starts_at = Carbon::parse($data['created_at'])->setTimezone('UTC');
            $subscription->ends_at = Carbon::parse($data['next_payment_date'])->setTimezone('UTC');

            $subscription->save(); //save subscription

            // Update subscription items...
            if (isset($data['items'])) {
                $plans = [];

                foreach ($data['items']['data'] as $item) {
                    $plans[] = $item['plan']['id'];

                    $subscription->items()->updateOrCreate([
                        'paystack_id' => $item['id'],
                    ], [
                        'paystack_plan' => $item['plan']['id'],
                        'quantity' => $item['quantity'],
                    ]);
                }

                // Delete items that aren't attached to the subscription anymore...
                $subscription->items()->whereNotIn('paystack_plan', $plans)->delete();
            }
        }

        return $this->successMethod();
    }

    /**
     * Handle customer transaction updates.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleChargeSuccess(array $payload)
    {
        if ($user = $this->getUserByPaystackCode($payload['data']['customer']['customer_code'])) {
            $data = $payload['data'];

            $transaction = Transaction::firstOrCreate(
                ['reference' => $data['reference']],
                [
                    'paystack_id' => $data['id'],
                    'access_code' => $data['access_code'] ?? $data['id'],
                    'user_id' => $user->id,
                    'status' => $data['status'] ?? null,
                    'gateway_response' => $data['gateway_response'] ?? null,
                    'plan_code' => $data['plan']['plan_code'] ?? null,
                    'amount' => $data['plan']['amount'] ?? null,
                    'currency' => $data['plan']['currency'] ?? null,
                    'fees' => $data['plan']['fees'] ?? null,
                    'paid_at' => Carbon::parse($data['paid_at'])->setTimezone('UTC'),
                ]
            );

            $transaction->save();

            if (isset($data['authorization'])) {
                $authorization = $data['authorization'];

                $transaction->authorization()->updateOrCreate([
                    'code' => $authorization['authorization_code'],
                ], [
                    'channel' => $authorization['channel'] ?? null,
                    'country_code' => $authorization['country_code'] ?? null,
                    'reusable' => $authorization['reusable'] ?? null,
                    'card_type' => $authorization['card_type'] ?? null,
                    'bin' => $authorization['bin'] ?? null,
                    'last_four' => $authorization['last4'] ?? null,
                    'exp_month' => $authorization['exp_month'] ?? null,
                    'exp_year' => $authorization['exp_year'] ?? null,
                    'brand' => $authorization['brand'] ?? null,
                    'bank' => $authorization['bank'] ?? null,
                    'signature' => $authorization['signature'] ?? null,
                ]);
            }
        }

        return $this->successMethod();
    }

    /**
     * Handle customer failed payment.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleInvoicePaymentFailed(array $payload)
    {
        $retrials_enabled = config('cc_paystack.retrials_enabled');
        $retrials_max = config('cc_paystack.retrials_max');
        $retrials_interval = config('cc_paystack.retrials_interval');

        // Check user exists
        if ($user = $this->getUserByPaystackCode($payload['data']['customer']['customer_code'])) {
            $data = $payload['data'];

            // Terminate if the authorization and subsctiption do not exist
            if (!$data['authorization'] && !$data['subscription']) {
                return $this->successMethod();
            }

            $authorization = Authorization::where($data['authorization']['authorization_code'])->first();
            $subscription = Subscription::where($data['subscription']['subscription_code'])->first();

            $retry = Retries::firstOrNew(
                [
                    'user_id' => $user->id,
                    'authorization_id' => $authorization->id,
                    'subscription_id' => $subscription->id,
                    'status' => Retries::STATUS_ACTIVE,
                ],
            );

            if (!$retrials_enabled || $retry->count > $retrials_max) {
                $this->endSubscription($subscription);
                $retry->status = Retries::STATUS_INACTIVE;
            }

            $retry->count++;
            $retry->save();
        }

        return $this->successMethod();
    }

    /**
     * Handle customer failed payment end subscription.
     *
     * @param  Cuitcode\Paystack\Models\Subscription $subscription
     * @return void
     */
    protected function endSubscription(Subscription $subscription): void
    {
        $subscription->disable();
    }

    /**
     * Get the billable entity instance by Paystack ID.
     *
     * @param  string|null  $paystackId
     * @return \Cuitcode\Paystack\Billable|null
     */
    protected function getUserByPaystackCode($paystackCode)
    {
        return Paystack::findBillableWithCode($paystackCode);
    }

    /**
     * Handle successful calls on the controller.
     *
     * @param  array  $parameters
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function successMethod($parameters = [])
    {
        return new Response('Webhook Handled', 200);
    }

    /**
     * Handle calls to missing methods on the controller.
     *
     * @param  array  $parameters
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function missingMethod($parameters = [])
    {
        return new Response;
    }
}
