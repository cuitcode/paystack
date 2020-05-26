<?php

namespace Cuitcode\Paystack\Traits;

use Cuitcode\Paystack\Concerns\ManagesCustomer;
use Cuitcode\Paystack\Concerns\ManagesTransaction;
use Cuitcode\Paystack\Concerns\ManagesInvoices;
use Cuitcode\Paystack\Concerns\ManagesPaymentMethods;
use Cuitcode\Paystack\Concerns\ManagesSubscriptions;
use Cuitcode\Paystack\Concerns\PerformsCharges;

trait Billable
{
    use ManagesCustomer;
    use ManagesTransaction;
    // use ManagesInvoices;
    // use ManagesPaymentMethods;
    // use ManagesSubscriptions;
    // use PerformsCharges;
}
