<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

#[BidirektSchema]
final class Pet
{
    /** @var array{street: string} */
    #[BidirektField]
    public array $address;
}
