<?php

namespace Cuitcode\Paystack;

use Cuitcode\Paystack\ApiResource;
use Cuitcode\Paystack\ApiOperations\All;
use Cuitcode\Paystack\ApiOperations\Create;
use Cuitcode\Paystack\ApiOperations\Delete;
use Cuitcode\Paystack\ApiOperations\Retrieve;
use Cuitcode\Paystack\ApiOperations\Update;

class Plan extends ApiResource
{
    const OBJECT_NAME = 'plan';

    use All, Create, Delete, Retrieve, Update;
}