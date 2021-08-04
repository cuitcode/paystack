<?php

namespace Cuitcode\Paystack\Models;

use Illuminate\Database\Eloquent\Model;

// use Cuitcode\Paystack\Traits\ManagesPlan;

class Retries extends Model
{
    const STATUS_ACTIVE = 'active';

    const STATUS_INACTIVE = 'inactive';

    protected $table = 'retries';

    protected $fillable = ['user_id', 'authorization_id', 'subscription_id', 'status'];

    public function isValid()
    {
        $retrials_max = config('cc_paystack.retrials_max');

        return $this->count < $retrials_max;
    }

    public function scopeActive($query)
    {
        return $query->active()->where('status', self::STATUS_ACTIVE);
    }

    public function scopeInactive($query)
    {
        return $query->active()->where('status', self::STATUS_INACTIVE);
    }
}
