<?php

use Bidirekt\PhpSdk\BidirektField;
use Bidirekt\PhpSdk\BidirektSchema;

final class Address
{
    #[BidirektField]
    public string $street;
}

trait Unused
{
    #[BidirektField]
    public string $note;
}

#[BidirektSchema]
final class Pet
{
    #[BidirektField]
    public string $name;
}
