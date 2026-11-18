<?php

namespace App;

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema(name: 'PetStatus')]
enum Status: string
{
    case Available = 'available';
    case Sold = self::PREFIX . 'sold';

    private const PREFIX = 'x-';
}

#[BidirektSchema(name: 'PetSize', label: 'Size')]
enum Size: string
{
    case Small = 'small';
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public Status $status;

    #[BidirektField(type: 'PetStatus')]
    public $other;

    #[BidirektField]
    public Size $size;

    #[BidirektField(type: 'PetSize')]
    public $otherSize;
}
