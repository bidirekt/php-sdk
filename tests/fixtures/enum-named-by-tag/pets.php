<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

/** @bidirekt schema name=PetStatus */
enum Status: string
{
    case Available = 'available';
    case Sold = 'sold';
}

enum Size: string
{
    case Small = 'small';
    case Large = 'large';
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public Status $status;

    #[BidirektField]
    public Size $size;
}
