<?php

/**
 * Cuitcode Limited
 * tech@cuitcode.com
 * 
 */  
namespace Cuitcode\Paystack;

class Paystack {

    /** @var string The Paystack API key to be used for requests. */
    public static $apiKey;

    /** @var string The base URL for the Paystack API. */
    public static $apiBase = 'https://api.paystack.co';

    private function __construct()
    {
        // $this->paystackHttpClient = $this->makePaystackHttpClient($key);

        // $this->customerResource = new CustomerResource($this->paystackHttpClient);
        // $this->customerModel = new Customer($this->customerResource);

        // $this->transactionResource = new TransactionResource($this->paystackHttpClient);

        // $this->planResource = new PlanResource($this->paystackHttpClient);
        // $this->planModel = new Plan($this->planResource);

        // $this->transactionHelper = TransactionHelper::make();
        // $this->transactionHelper->setTransactionResource($this->transactionResource);
    }

    /**
     * @return string the API key used for requests
     */
    public static function getApiKey()
    {
        return self::$apiKey;
    }

    /**
     * Get the default Paystack options.
     *
     * @param  array  $options
     * @return array
     */
    public static function paystackOptions(array $options = [])
    {
        // return live credentials
        if(config('cc_paystack.live_mode')) {
            return config('cc_paystack.live');
        }

        // return test credentials
        return config('cc_paystack.test');
    }
}
