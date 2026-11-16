<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
#[BidirektSchema(name: 'Animal')]
final class Pet
{
    #[BidirektField]
    public string $name;
}

#[BidirektSchema, BidirektSchema]
enum PetStatus: string
{
    case Available = 'available';
}
