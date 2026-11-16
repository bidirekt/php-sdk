<?php

namespace Acme\Shared;

use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(name: 'PetStatus')]
enum Status: string
{
    case Available = 'available';
    case Sold = 'sold';
}
