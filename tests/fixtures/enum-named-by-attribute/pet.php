<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(name: 'Status')]
enum PetStatus: string
{
    case Available = 'available';
    case Sold = 'sold';
}

#[BidirektSchema]
enum Size: string
{
    case Small = 'small';
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public PetStatus $status;
}
