<?php

namespace Cuitcode\Paystack\Http\Middlewares;

use Closure;
use Cuitcode\Paystack\Exceptions\SignatureVerification;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class VerifySignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $secret = config('cc_paystack.test.secret');;

        // return live credentials
        if(config('cc_paystack.live_mode')) {
            $secret = config('cc_paystack.live.secret');
        }

        try {
            WebhookSignature::verifyHeader(
                $request->body(),
                $request->header('X-Paystack-Signature'),
                $secret
            );
        } catch (SignatureVerification $exception) {
            throw new AccessDeniedHttpException($exception->getMessage(), $exception);
        }

        return $next($request);
    }
}
