<?php

namespace Cuitcode\Paystack\Concerns;

use Cuitcode\Paystack\Paystack;
use Cuitcode\Paystack\Exceptions\CustomerAlreadyCreated;
use Cuitcode\Paystack\Exceptions\InvalidArgument;
use Cuitcode\Paystack\Exceptions\InvalidCustomer;
use Cuitcode\Paystack\Transaction as PaystackTransaction;

trait ManagesTransaction
{
    /**
     * Create a Paystack customer for the given model.
     *
     * @param  array  $options
     * @return \Paystack\Customer
     *
     * @throws \Cuitcode\Paystack\Exceptions\CustomerAlreadyCreated
     */
     public function initializeTransaction(array $options = [])
     {
        //  if (!$this->hasPaystackId()) {
        //     //  throw CustomerAlreadyCreated::exists($this);
        //      $this->createAsPaystackCustomer($options);
        //  }
 
         if (! array_key_exists('email', $options) && $email = $this->paystackEmail()) {
             $options['email'] = $email;
         }
 
         if (! array_key_exists('amount', $options)) {
            if (! array_key_exists('plan', $options)) {
                $message = 'Could not determine the amount to be paid or plan to be subscribed to.';
                throw InvalidArgument::badArgument($message);
            }
            $options['amount'] = 0;
         }
 
         // Here we will create the customer instance on Paystack and store the ID of the
         // user from Paystack. This ID will correspond with the Paystack user instances
         // and allow us to retrieve users from Paystack later when we need to work.
         $trans = PaystackTransaction::initialize(
             $options, $this->paystackOptions()
         );
 
         // dd($customer);
 
        //  $this->paystack_id = $customer["data"]->id;
         // $this->paystack_id = $customer["data"]["id"];
 
        //  $this->save();
 
         return $trans;
     }
}
