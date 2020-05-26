<?php

namespace Cuitcode\Paystack;

use Cuitcode\Paystack\ApiResource;

class Customer extends ApiResource {
    //
    const OBJECT_NAME = 'customer';

    use ApiOperations\Create;
    use ApiOperations\Retrieve;
    use ApiOperations\Request;
    use ApiOperations\Update;

}
