<?php

namespace Acme\Shared;

use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
enum Level: int
{
    case Low = 1;
}
