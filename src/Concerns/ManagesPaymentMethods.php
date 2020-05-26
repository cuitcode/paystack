<?php

namespace Cuitcode\Paystack\Concerns;

use Exception;
// use Cuitcode\Paystack\PaymentMethod;
use Cuitcode\Paystack\BankAccount as PaystackBankAccount;
// use Cuitcode\Paystack\Card as PaystackCard;
use Cuitcode\Paystack\Customer as PaystackCustomer;
// use Cuitcode\Paystack\PaymentMethod as PaystackPaymentMethod;
use Cuitcode\Paystack\SetupIntent as PaystackSetupIntent;

trait ManagesPaymentMethods
{
    /**
     * Create a new SetupIntent instance.
     *
     * @param  array  $options
     * @return \Paystack\SetupIntent
     */
    public function createSetupIntent(array $options = [])
    {
        return PaystackSetupIntent::create(
            $options, $this->paystackOptions()
        );
    }
}