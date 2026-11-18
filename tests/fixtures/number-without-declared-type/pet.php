<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public float $weight;

    #[BidirektField(type: 'integer')]
    public float $age;

    #[BidirektField(type: 'float')]
    public float $height;
}
