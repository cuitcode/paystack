<?php

namespace Cuitcode\Paystack\Concerns;

use Cuitcode\Paystack\Paystack;
use Cuitcode\Paystack\Exceptions\CustomerAlreadyCreated;
use Cuitcode\Paystack\Exceptions\InvalidArgument;
use Cuitcode\Paystack\Exceptions\InvalidCustomer;
use Cuitcode\Paystack\Customer as PaystackCustomer;

trait ManagesCustomer
{
    /**
     * Retrieve the Paystack customer ID.
     *
     * @return string|null
     */
    public function paystackId()
    {
        return $this->paystack_id;
    }

    /**
     * Retrieve the Paystack customer ID.
     *
     * @return string|null
     */
    public function paystackCode()
    {
        return $this->paystack_code;
    }

    /**
     * Determine if the entity has a Paystack customer ID.
     *
     * @return bool
     */
    public function hasPaystackId()
    {
        return ! is_null($this->paystack_id);
    }

    /**
     * Determine if the entity has a Paystack customer ID and throw an exception if not.
     *
     * @return void
     *
     * @throws \Laravel\Cashier\Exceptions\InvalidCustomer
     */
    protected function assertCustomerExists()
    {
        if (! $this->hasPaystackId()) {
            throw InvalidCustomer::notYetCreated($this);
        }
    }

    /**
     * Create a Paystack customer for the given model.
     *
     * @param  array  $options
     * @return \Paystack\Customer
     *
     * @throws \Cuitcode\Paystack\Exceptions\CustomerAlreadyCreated
     */
    public function createAsPaystackCustomer(array $options = [])
    {
        if ($this->hasPaystackId()) {
            throw CustomerAlreadyCreated::exists($this);
        }

        if (! array_key_exists('email', $options) && $email = $this->paystackEmail()) {
            $options['email'] = $email;
        }

        if (! array_key_exists('first_name', $options) && $first_name = $this->paystackFirstName()) {
            $options['first_name'] = $first_name;
        }

        if (! array_key_exists('last_name', $options) && $last_name = $this->paystackLastName()) {
            $options['last_name'] = $last_name;
        }

        if (! array_key_exists('phone_number', $options) && $phone_number = $this->paystackPhoneNumber()) {
            $options['phone_number'] = $phone_number;
        }

        // Here we will create the customer instance on Paystack and store the ID of the
        // user from Paystack. This ID will correspond with the Paystack user instances
        // and allow us to retrieve users from Paystack later when we need to work.
        $customer = PaystackCustomer::create(
            $options, $this->paystackOptions()
        );

        $this->paystack_id = $customer["data"]->id;
        $this->paystack_code = $customer["data"]->customer_code;
        // $this->paystack_id = $customer["data"]["id"];

        $this->save();

        return $customer;
    }

    /**
     * Update the underlying Paystack customer information for the model.
     *
     * @param  array  $options
     * @return Cuitcode\Paystack\Customer
     */
     public function updatePaystackCustomer(array $options = [])
     {
         return PaystackCustomer::update(
             $this->paystack_code, $options, $this->paystackOptions()
         );
     }

    /**
     * Get the Paystack customer instance for the current user or create one.
     *
     * @param  array  $options
     * @return \Paystack\Customer
     */
     public function createOrGetPaystackCustomer(array $options = [])
     {
         if ($this->hasPaystackId()) {
             return $this->asPaystackCustomer();
         }
 
         return $this->createAsPaystackCustomer($options);
     }

     /**
     * Get the Paystack customer for the model.
     *
     * @return \Paystack\Customer
     */
    public function asPaystackCustomer()
    {
        $this->assertCustomerExists();

        return PaystackCustomer::retrieve($this->paystack_code, $this->paystackOptions());
    }
    /**
     * Get the email address used to create the customer in Paystack.
     *
     * @return string|null
     */
    public function paystackEmail()
    {
        return $this->email;
    }

    /**
     * Get the first name used to create the customer in Paystack.
     *
     * @return string|null
     */
    public function paystackFirstName()
    {
        return $this->first_name;
    }

    /**
     * Get the last name used to create the customer in Paystack.
     *
     * @return string|null
     */
    public function paystackLastName()
    {
        return $this->last_name;
    }

    /**
     * Get the phone number used to create the customer in Paystack.
     *
     * @return string|null
     */
    public function paystackPhoneNumber()
    {
        return $this->phone_number;
    }

    /**
     * Get the default Paystack API options for the current Billable model.
     *
     * @param  array  $options
     * @return array
     */
    public function paystackOptions(array $options = [])
    {
        return Paystack::paystackOptions($options);
    }
}
