<?php

namespace Cuitcode\Paystack\Models;

use Illuminate\Database\Eloquent\Model;
use Cuitcode\Paystack\Traits\ManagesPlan;

class Plan extends Model
{
    use ManagesPlan;

    protected $fillable = ['id', 'name', 'description', 'amount', 'interval'];
}
