<?php

namespace Acme\Shared;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(fields: [new BidirektField('hex', type: 'string')])]
enum Color: string
{
    case Black = 'black';
}
