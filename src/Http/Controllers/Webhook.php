<?php

namespace Cuitcode\Paystack\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        
        Log::info('Showing client secret info '.$secret);

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
        $method = 'handle'.Str::studly(str_replace('.', '_', $payload['type']));

        // WebhookReceived::dispatch($payload);

        if (method_exists($this, $method)) {
            $response = $this->{$method}($payload);

            WebhookHandled::dispatch($payload);

            return $response;
        }

        return $this->missingMethod();
    }

}
