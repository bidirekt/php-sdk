<?php

namespace Acme\Shared;

use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(name: 'PetSize')]
#[BidirektSchema(name: 'Size')]
enum Size: string
{
    case Small = 'small';
}
