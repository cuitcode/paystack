<?php

namespace Cuitcode\Paystack\Observers;

use Cuitcode\Paystack\Models\Plan;

class PlanObserver
{
    /**
     * Handle the plan "created" event.
     *
     * @param  \App\Cuitcode\Paystack\Models\Plan  $plan
     * @return void
     */
    public function created(Plan $plan)
    {
        //
        $plan->createAsPaystackPlan();
    }

    /**
     * Handle the plan "updated" event.
     *
     * @param  \App\Cuitcode\Paystack\Models\Plan  $plan
     * @return void
     */
    public function updated(Plan $plan)
    {
        //
        $plan->updatePaystackPlan();
    }

    /**
     * Handle the plan "deleted" event.
     *
     * @param  \App\Cuitcode\Paystack\Models\Plan  $plan
     * @return void
     */
    public function deleted(Plan $plan)
    {
        //
        $plan->deletePaystackPlan();
    }

    /**
     * Handle the plan "restored" event.
     *
     * @param  \App\Cuitcode\Paystack\Models\Plan  $plan
     * @return void
     */
    public function restored(Plan $plan)
    {
        //
    }

    /**
     * Handle the plan "force deleted" event.
     *
     * @param  \App\Cuitcode\Paystack\Models\Plan  $plan
     * @return void
     */
    public function forceDeleted(Plan $plan)
    {
        //
    }
}
