<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

enum PetSize: string
{
    case Short = self::Short->value;
    case Tall = 'tall';
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public PetSize $size;
}
