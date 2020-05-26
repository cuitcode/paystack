<?php

namespace Cuitcode\Paystack\Models;


use Illuminate\Database\Eloquent\Model;
use Cuitcode\Paystack\Traits\ManagesPlan;
// use Cuitcode\Paystack\Events\PlanCreated;
// use Cuitcode\Paystack\Events\PlanDeleted;
// use Cuitcode\Paystack\Events\PlanUpdated;

class Plan extends Model
{
    //
    use ManagesPlan;

    protected $fillable = ['id', 'name', 'description', 'amount', 'interval'];
}
