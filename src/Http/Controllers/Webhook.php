<?php

namespace Cuitcode\Paystack\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Cuitcode\Paystack\Paystack;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Cuitcode\Paystack\Models\Subscription;
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
        // Log::info('webhook data'. $request->getContent());
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
            $data = $payload['data'];
            $subscription = new Subscription;
            $subscription->user_id = $user->id;
            $subscription->code = $data['subscription_code'];
            $subscription->paystack_id = $user->paystack_id;
            $subscription->status = $data['status'];
            $subscription->plan_code = $data['plan']['plan_code'];
            $subscription->starts_at = Carbon::parse($data['created_at']);
            $subscription->ends_at = Carbon::parse($data['next_payment_date']);

            $subscription->save(); //save subscription

            Log::info('done saving subscription');

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
