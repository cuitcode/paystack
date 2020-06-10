<?php

namespace Cuitcode\Paystack\Concerns;

use Carbon\Carbon;
use Cuitcode\Paystack\Paystack;
use Cuitcode\Paystack\Exceptions\CustomerAlreadyCreated;
use Cuitcode\Paystack\Exceptions\InvalidArgument;
use Cuitcode\Paystack\Exceptions\InvalidCustomer;
use Cuitcode\Paystack\Models\Transaction;
use Cuitcode\Paystack\Transaction as PaystackTransaction;

trait ManagesTransaction
{
    /**
     * Get all of the transactions for the Paystack model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function transactions()
    {
        return $this->hasMany(Transaction::class, $this->getForeignKey())->orderBy('created_at', 'desc');
    }

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
        // void previous access codes.
        $transaction =  $this->transactions()->where("status","pending")->first();
        $transaction->status = "unused";
        $transaction->save();

        

        $this->transactions->filter(function (Transaction $transaction) {
            return $transaction->reference === $data['reference'];
        })->each(function (Transaction $transaction) use ($data, $user) {

            // Transaction Data...
            $transaction->user_id = $user->id;
            $transaction->paystack_id = $data['id'] ?? null;
            $transaction->status = $data['status'] ?? null;
            $transaction->gateway_response = $data['gateway_response'] ?? null;
            $transaction->plan_code = $data['plan']['plan_code'] ?? null;
            $transaction->amount = $data['plan']['amount'] / 100 ?? null;
            $transaction->paid_at = Carbon::createFromTimestamp($data['paid_at']);

            $transaction->save();

            // Update transaction authorization...
            if (isset($data['authorization'])) {
                $authorization = $data['authorization'];

                $transaction->authorization()->updateOrCreate([
                    'code' => $authorization['authorization_code'],
                ], [
                    'channel' => $authorization['channel']?? null,
                    'country_code' => $authorization['country_code']?? null,
                    'reusable' => $authorization['reusable']?? null,
                    'card_type' => $authorization['card_type']?? null,
                    'bin' => $authorization['bin']?? null,
                    'last_four' => $authorization['last4']?? null,
                    'exp_month' => $authorization['exp_month']?? null,
                    'exp_year' => $authorization['exp_year']?? null,
                    'brand' => $authorization['brand']?? null,
                    'bank' => $authorization['bank']?? null,
                    'signature' => $authorization['signature']?? null,
                ]);
            }
        });
 
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

        $transaction =  new Transaction;

        $this->transactions()->create([
            "access_code" => $trans["data"]->access_code,
            "reference" => $trans["data"]->reference,
        ]);
 
        return $trans;
     }
}
