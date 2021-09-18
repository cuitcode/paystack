<?php

namespace Cuitcode\Paystack\Traits;

use Cuitcode\Paystack\Paystack;
use Cuitcode\Paystack\Exceptions\Invalid;
use Cuitcode\Paystack\Plan as PaystackPlan;
use Cuitcode\Paystack\Exceptions\AlreadyCreated;

trait ManagesPlan
{
    /**
     * Retrieve the Paystack customer ID.
     *
     * @return string|null
     */
    public function planCode()
    {
        return $this->plan_code;
    }

    /**
     * Determine if the entity has a Paystack customer ID.
     *
     * @return bool
     */
    public function hasPlanCode()
    {
        return !is_null($this->plan_code);
    }

    /**
     * Determine if the entity has a Paystack customer ID and throw an exception if not.
     *
     * @return void
     *
     * @throws \Laravel\Cashier\Exceptions\Invalid
     */
    protected function assertPlanCodeExists()
    {
        if (!$this->hasPlanCode()) {
            throw Invalid::notYetCreated($this);
        }
    }

    /**
     * Create a Paystack customer for the given model.
     *
     * @param  array  $options
     * @return \Paystack\Customer
     *
     * @throws \Cuitcode\Paystack\Exceptions\AlreadyCreated
     */
    public function createAsPaystackPlan(array $options = [])
    {
        if ($this->hasPlanCode()) {
            throw AlreadyCreated::exists($this, 'plan_code');
        }

        $options['name'] = $options['name'] ?? $this->name;
        $options['description'] = $options['description'] ?? $this->description;
        $options['amount'] = $options['amount'] ?? $this->amount;
        $options['interval'] = $options['interval'] ?? $this->interval;
        $options['currency'] = $options['currency'] ?? $this->currency;

        // Here we will create the customer instance on Paystack and store the ID of the
        // user from Paystack. This ID will correspond with the Paystack user instances
        // and allow us to retrieve users from Paystack later when we need to work.
        $plan = PaystackPlan::create(
            $options,
            $this->paystackOptions()
        );

        $this->plan_code = $plan['data']->plan_code;

        $this->unsetEventDispatcher();
        $this->save();

        return $plan;
    }

    /**
     * Update the underlying Paystack customer information for the model.
     *
     * @param  array  $options
     * @return Cuitcode\Paystack\Customer
     */
    public function updatePaystackPlan(array $options = [])
    {
        $options['name'] = $options['name'] ?? $this->name;
        $options['description'] = $options['description'] ?? $this->description;
        $options['amount'] = $options['amount'] ?? $this->amount;

        return PaystackPlan::update(
            $this->plan_code,
            $options,
            $this->paystackOptions()
        );
    }

    /**
     * Update the underlying Paystack customer information for the model.
     *
     * @param  array  $options
     * @return Cuitcode\Paystack\Customer
     */
    public function deletePaystackPlan(array $options = [])
    {
        $options['name'] = $options['name'] ?? $this->name;

        if (null == $this->plan_code) {
            return;
        }

        return PaystackPlan::delete(
            $this->plan_code,
            $options,
            $this->paystackOptions()
        );
    }

    /**
     * Get the Paystack customer instance for the current user or create one.
     *
     * @param  array  $options
     * @return \Paystack\Customer
     */
    public function createOrGetPaystackPlan(array $options = [])
    {
        if ($this->hasPlanCode()) {
            return $this;
        }

        return $this->createAsPaystackPlan($options);
    }

    /**
    * Get the Paystack customer for the model.
    *
    * @return \Paystack\Customer
    */
    public function asPaystackPlan()
    {
        $this->assertCustomerExists();

        return PaystackPlan::retrieve($this->plan_code, $this->paystackOptions());
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
