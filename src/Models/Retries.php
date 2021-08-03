<?php

namespace Cuitcode\Paystack\Models;

use Illuminate\Database\Eloquent\Model;

// use Cuitcode\Paystack\Traits\ManagesPlan;

class Retries extends Model
{
    const STATUS_ACTIVE = 'active';

    const STATUS_INACTIVE = 'inactive';

    protected $fillable = ['user_id', 'authorization_id', 'subscription_id', 'status'];
}
