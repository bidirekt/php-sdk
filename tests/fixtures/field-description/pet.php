<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    #[BidirektField(description: 'Shown on the card')]
    public string $nickname;

    /** The name the owner calls the pet */
    #[BidirektField]
    public string $name;
}
