<?php

namespace Cuitcode\Paystack;
use Illuminate\Support\Facades\Log;
use Cuitcode\Paystack\Exceptions\SignatureVerification;

abstract class WebhookSignature
{
    public static function verifyHeader($payload, $header, $secret)
    {
        $expectedSignature = self::computeSignature($payload, $secret);
        if ($header !== $expectedSignature) {
            throw SignatureVerification::factory(
                'No signatures found with expected scheme',
                $payload,
                $header
            );
        }
    }

    public static function computeSignature($payload, $secret) {
        return hash_hmac('sha512', $payload, $secret);
    }
}
