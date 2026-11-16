<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    #[BidirektField(name: 'pet_name')]
    public string $name;

    public function __construct(
        #[BidirektField, BidirektField] public int $age,
    ) {
    }
}
