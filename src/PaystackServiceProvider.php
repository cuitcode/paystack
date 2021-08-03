<?php

namespace Cuitcode\Paystack;

use Cuitcode\Paystack\Models\Plan;
use Illuminate\Support\ServiceProvider;
use Cuitcode\Paystack\Observers\PlanObserver;
use Cuitcode\Paystack\Console\Commands\RetryFailedPayments;

class PaystackServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        include __DIR__ . '/routes.php';

        $this->publishes([
            __DIR__ . '/config/cc_paystack.php' => config_path('cc_paystack.php'),
        ]);

        $this->loadMigrationsFrom(__DIR__ . '/database/migrations');

        Plan::observe(PlanObserver::class);

        $this->commands([
            RetryFailedPayments::class,
        ]);
    }
}
