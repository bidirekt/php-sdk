<?php

namespace Acme\Shared;

use Bidirekt\PhpSdk\BidirektSchema;

/** @bidirekt schema name=PetStatus */
#[BidirektSchema(name: 'PetStatus')]
enum Status: string
{
    case Available = 'available';
}
