<?php

namespace Cuitcode\Paystack\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Cuitcode\Paystack\Paystack;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Cuitcode\Paystack\Events\WebhookHandled;
use Cuitcode\Paystack\Events\WebhookReceived;
use Symfony\Component\HttpFoundation\Response;
use Cuitcode\Paystack\Http\Middlewares\VerifySignature;

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
        if(config('cc_paystack.live_mode')) {
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
        // dd(gettype($request->getContent()));
        // dd(json_last_error_msg());
        // dd($payload);
        
        $method = 'handle'.Str::studly(str_replace('.', '_', $payload['event']));
        // return response()->json(['method' => $method]);
        WebhookReceived::dispatch($payload);

        if (method_exists($this, $method)) {
            $response = $this->{$method}($payload);

            WebhookHandled::dispatch($payload);

            return $response;
        }

        return $this->missingMethod();
    }

    /**
     * Handle customer subscription updated.
     *
     * @param  array  $payload
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function handleSubscriptionCreate(array $payload)
    {
        if ($user = $this->getUserByPaystackCode($payload['data']['customer']['customer_code'])) {
            $data = $payload['data']['object'];
            $subscription = new Subscription;
            $subscription->paystack_id = $user->paystack_id;
            $subscription->customer_code = $payload['data']['subscription_code'];
            $subscription->sub_code = $payload['data']['subscription_code'];
            $subscription->plan_code = $payload['data']['plan']['plan_code'];
            $subscription->created_at = $payload['data']['createdAt'];

            $user->subscriptions->filter(function (Subscription $subscription) use ($data) {
                return $subscription->paystack_id === $data['id'];
            })->each(function (Subscription $subscription) use ($data) {
                if (isset($data['status']) && $data['status'] === 'incomplete_expired') {
                    $subscription->items()->delete();
                    $subscription->delete();

                    return;
                }

                // Plan...
                $subscription->paystack_plan = $data['plan']['id'] ?? null;

                // Quantity...
                $subscription->quantity = $data['quantity'];

                // Trial ending date...
                if (isset($data['trial_end'])) {
                    $trialEnd = Carbon::createFromTimestamp($data['trial_end']);

                    if (! $subscription->trial_ends_at || $subscription->trial_ends_at->ne($trialEnd)) {
                        $subscription->trial_ends_at = $trialEnd;
                    }
                }

                // Cancellation date...
                if (isset($data['cancel_at_period_end'])) {
                    if ($data['cancel_at_period_end']) {
                        $subscription->ends_at = $subscription->onTrial()
                            ? $subscription->trial_ends_at
                            : Carbon::createFromTimestamp($data['current_period_end']);
                    } else {
                        $subscription->ends_at = null;
                    }
                }

                // Status...
                if (isset($data['status'])) {
                    $subscription->paystack_status = $data['status'];
                }

                $subscription->save();

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
            });
        }

        return $this->successMethod();
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
