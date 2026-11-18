<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(fields: [
    new BidirektField('label', type: 'string'),
])]
enum PetStatus: string
{
    case Available = 'available';
}
