<?php

namespace Cuitcode\Paystack\Models;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;
// use Cuitcode\Paystack\Concerns\Prorates;
use Cuitcode\Paystack\Exceptions\IncompletePayment;
use Cuitcode\Paystack\Exceptions\TransactionUpdateFailure;
use LogicException;
use Cuitcode\Paystack\Transaction as PaystackTransaction;

class Transaction extends Model
{
    // use Prorates;

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = ['authorization'];


    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'paid_at', 'created_at', 'updated_at',
    ];

    /**
     * Get the authorization record associated with the transaction.
     */
    public function authorization()
    {
        return $this->hasOne(Authorization::class);
    }

    /**
     * Get the model related to the transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        $model = config('cc_paystack.model');

        return $this->belongsTo($model, (new $model)->getForeignKey());
    }

    /**
     * Determine if the transaction is success, on trial, or within its grace period.
     *
     * @return bool
     */
    public function success()
    {
        return $this->status === PaystackTransaction::STATUS_SUCCESS;
    }

    /**
     * Determine if the transaction is incomplete.
     *
     * @return bool
     */
    public function incomplete()
    {
        return $this->status === PaystackTransaction::STATUS_FAILED;
    }

    /**
     * Filter query by incomplete.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeIncomplete($query)
    {
        $query->where('status', PaystackTransaction::STATUS_FAILED);
    }

    /**
     * Filter query by success.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return void
     */
    public function scopeSuccess($query)
    {
        $query->where('status', PaystackTransaction::STATUS_SUCCESS);
    }

    /**
     * Sync the Paystack status of the transaction.
     *
     * @return void
     */
    public function syncPaystackStatus()
    {
        $transaction = $this->asPaystackTransaction();

        $this->status = $transaction->status;

        $this->save();
    }

    /**
     * Get the transaction as a Paystack transaction object.
     *
     * @param  array  $expand
     * @return \Paystack\Transaction
     */
    public function asPaystackTransaction(array $expand = [])
    {
        return PaystackTransaction::retrieve(
            ['id' => $this->paystack_id, 'expand' => $expand], $this->owner->paystackOptions()
        );
    }
}