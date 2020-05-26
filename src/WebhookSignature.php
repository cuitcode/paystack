<?php

namespace Paystack;
use Cuitcode\Paystack\Exceptions\SignatureVerification;

abstract class WebhookSignature
{
    public static function verifyHeader($body, $header, $secret)
    {
        // Extract timestamp and signatures from header
        
        if ($header !== hash_hmac('sha512', $body, $secret)) {
            throw Exception\SignatureVerificationException::factory(
                'No signatures found with expected scheme',
                $payload,
                $header
            );
        }
    }
}
